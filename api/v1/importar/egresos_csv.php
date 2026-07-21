<?php
/**
 * POST /api/v1/importar/egresos/csv
 *
 * Importa egresos desde archivo CSV
 *
 * Form Data:
 *   - archivo: CSV con columnas de egresos
 *
 * Formato CSV esperado:
 * run,tipo_lista,fecha_egreso,razon_egreso,especialista_nombre,diagnostico_principal,resultado_tratamiento
 * 12345678-9,CNE,2026-07-21,Atendido,Dr. Juan,HTA controlada,Mejorado
 *
 * Respuesta: 202 Accepted con ID de importación
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../v1/_auth.php';
require_once __DIR__ . '/../v1/_respuesta.php';
require_once __DIR__ . '/../../lib/CsvParser.php';

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ApiResponse::error('Método no permitido. Use POST.', 405);
}

// Autenticar
$cliente = verificarTokenAPI();
verificarPermiso($cliente, 'escritura');

// Validar archivo
if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    ApiResponse::error('Archivo requerido y válido', 400);
}

$archivo = $_FILES['archivo'];

// Validar tipo MIME
$mime_types = ['text/csv', 'text/plain', 'application/vnd.ms-excel'];
if (!in_array($archivo['type'], $mime_types)) {
    ApiResponse::error('Solo se aceptan archivos CSV', 400);
}

// Validar tamaño (máximo 5MB)
if ($archivo['size'] > 5 * 1024 * 1024) {
    ApiResponse::error('El archivo es demasiado grande (máximo 5MB)', 400);
}

try {
    // Guardar archivo temporal
    $temp_dir = sys_get_temp_dir();
    $temp_file = tempnam($temp_dir, 'egreso_');

    if (!move_uploaded_file($archivo['tmp_name'], $temp_file)) {
        ApiResponse::error('No se pudo procesar el archivo', 400);
    }

    $pdo = getConexionSiglech();

    // Generar ID de importación
    $importacion_id = "IMP-EGR-" . date('Y-m-d') . "-" . uniqid();

    // Crear registro de importación
    $stmt = $pdo->prepare("
        INSERT INTO importaciones (importacion_id, tipo, metodo, total_registros, cliente_id, estado)
        VALUES (?, 'EGRESOS', 'csv', 0, ?, 'en_progreso')
    ");
    $stmt->execute([$importacion_id, $cliente['id']]);
    $importacion_bd_id = $pdo->lastInsertId();

    // Procesar CSV
    $exitosos = 0;
    $fallidos = 0;
    $linea = 0;
    $errores = [];

    if (($handle = fopen($temp_file, 'r')) !== false) {
        // Leer cabecera
        $cabecera = fgetcsv($handle);
        if (!$cabecera) {
            throw new Exception("Archivo CSV vacío");
        }

        $cabecera = array_map('strtolower', $cabecera);

        // Procesar líneas
        while (($datos = fgetcsv($handle)) !== false) {
            $linea++;

            if (count($datos) === 1 && empty($datos[0])) {
                continue; // Saltar líneas vacías
            }

            try {
                // Mapear datos
                $registro = array_combine($cabecera, $datos);

                // Validaciones básicas
                if (empty($registro['run']) || empty($registro['tipo_lista']) || empty($registro['fecha_egreso'])) {
                    throw new Exception("Campos requeridos: run, tipo_lista, fecha_egreso");
                }

                // Validar tipo_lista
                $tipo_lista = strtoupper($registro['tipo_lista']);
                if (!in_array($tipo_lista, ['CNE', 'IQ', 'PROC'])) {
                    throw new Exception("Tipo lista inválido: $tipo_lista");
                }

                // Validar fecha
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $registro['fecha_egreso'])) {
                    throw new Exception("Fecha inválida: {$registro['fecha_egreso']}");
                }

                // Generar egreso_id
                $egreso_id = "EGR-" . date('Y-m-d') . "-" . uniqid();

                // Insertar egreso
                $stmt_insert = $pdo->prepare("
                    INSERT INTO egresos (
                        egreso_id, run, tipo_lista, fecha_egreso, razon_egreso,
                        especialista_nombre, diagnostico_principal,
                        procedimiento_realizado, resultado_tratamiento,
                        usuario_registra_nombre
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt_insert->execute([
                    $egreso_id,
                    $registro['run'],
                    $tipo_lista,
                    $registro['fecha_egreso'],
                    $registro['razon_egreso'] ?? 'Atendido',
                    $registro['especialista_nombre'] ?? null,
                    $registro['diagnostico_principal'] ?? null,
                    $registro['procedimiento_realizado'] ?? null,
                    $registro['resultado_tratamiento'] ?? null,
                    $cliente['nombre']
                ]);

                $exitosos++;

                // Registrar en detalles
                $stmt_detalle = $pdo->prepare("
                    INSERT INTO importacion_detalles (importacion_id, linea_numero, run, estado, mensaje)
                    VALUES (?, ?, ?, 'exitoso', 'Egreso importado')
                ");
                $stmt_detalle->execute([$importacion_bd_id, $linea, $registro['run']]);

            } catch (Exception $e) {
                $fallidos++;
                $error_msg = $e->getMessage();
                $errores[] = [
                    'linea' => $linea,
                    'run' => $registro['run'] ?? 'N/A',
                    'error' => $error_msg
                ];

                // Registrar en detalles
                $stmt_detalle = $pdo->prepare("
                    INSERT INTO importacion_detalles (importacion_id, linea_numero, run, estado, mensaje)
                    VALUES (?, ?, ?, 'error', ?)
                ");
                $stmt_detalle->execute([$importacion_bd_id, $linea, $registro['run'] ?? 'N/A', $error_msg]);
            }
        }

        fclose($handle);
    }

    // Actualizar importación
    $stmt_update = $pdo->prepare("
        UPDATE importaciones
        SET total_registros = ?, registros_exitosos = ?, registros_fallidos = ?,
            estado = 'completado', fecha_fin = NOW(), progreso_porcentaje = 100
        WHERE id = ?
    ");
    $stmt_update->execute([$exitosos + $fallidos, $exitosos, $fallidos, $importacion_bd_id]);

    // Limpiar archivo temporal
    @unlink($temp_file);

    // Registrar acceso API
    registrarAccesoAPI($cliente, '/importar/egresos/csv', 'POST', 'ok');

    // Respuesta
    http_response_code(202);
    $respuesta = new ApiResponse();
    $respuesta->setDatos([
        'importacion_id' => $importacion_id,
        'tipo' => 'EGRESOS',
        'metodo' => 'csv',
        'total_registros' => $exitosos + $fallidos,
        'registros_exitosos' => $exitosos,
        'registros_fallidos' => $fallidos,
        'tasa_exito' => ($exitosos + $fallidos) > 0 ? round(($exitosos / ($exitosos + $fallidos)) * 100, 2) . '%' : '0%'
    ])
    ->setMensaje('Importación de egresos completada')
    ->agregarMetadato('importacion_id', $importacion_id)
    ->agregarMetadato('estado_url', "/api/v1/importar/estado?importacion_id=$importacion_id")
    ->agregarMetadato('timestamp', date('c'))
    ->enviar();

} catch (Exception $e) {
    registrarAccesoAPI($cliente, '/importar/egresos/csv', 'POST', 'error');
    error_log("Error en POST /importar/egresos/csv: " . $e->getMessage());
    ApiResponse::error('Error al procesar archivo: ' . $e->getMessage(), 500);
}
?>
