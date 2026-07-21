<?php
/**
 * POST /api/v1/importar/json
 *
 * Importa datos de listas de espera desde JSON
 * Ideal para scripts Python y carga programática
 *
 * Body JSON esperado:
 * {
 *   "tipo": "CNE|IQ|PROC",
 *   "datos": [
 *     {
 *       "_id": "sigte-cne-12345",
 *       "run": "12345678-9",
 *       "estab_orig": "1000",
 *       "estab_dest": "1100",
 *       "especialidad": "Cardiología",
 *       "prestacion": "Consulta",
 *       "fecha_ingreso": "2026-06-06",
 *       "estado": "VIGENTE",
 *       "dias_espera": 45,
 *       "cie10": "I10",
 *       "primer_apellido": "Pérez",
 *       "segundo_apellido": "García",
 *       "nombres": "Juan"
 *     }
 *   ]
 * }
 *
 * Respuesta: 202 Accepted + importacion_id
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../v1/_auth.php';
require_once __DIR__ . '/../v1/_respuesta.php';

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ApiResponse::error('Método no permitido. Use POST.', 405);
}

// Autenticar
$cliente = verificarTokenAPI();
verificarPermiso($cliente, 'escritura');

// Obtener JSON del body
$json = file_get_contents('php://input');
$payload = json_decode($json, true);

if (!$payload) {
    ApiResponse::error('JSON inválido o vacío', 400);
}

// Validar estructura
if (empty($payload['tipo']) || empty($payload['datos'])) {
    ApiResponse::error('Campo requerido: "tipo" y "datos"', 400);
}

$tipo = strtoupper($payload['tipo']);
$datos = $payload['datos'];

if (!in_array($tipo, ['CNE', 'IQ', 'PROC'])) {
    ApiResponse::error("Tipo inválido: $tipo. Debe ser CNE, IQ o PROC", 400);
}

if (!is_array($datos) || empty($datos)) {
    ApiResponse::error('Array "datos" vacío o inválido', 400);
}

try {
    $pdo = getConexionSiglech();

    // Generar ID de importación
    $importacion_id = "IMP-" . date('Y-m-d') . "-" . uniqid();

    // Crear registro de importación
    $stmt = $pdo->prepare("
        INSERT INTO importaciones (importacion_id, tipo, metodo, total_registros, cliente_id, estado)
        VALUES (?, ?, 'json', ?, ?, 'en_progreso')
    ");
    $stmt->execute([$importacion_id, $tipo, count($datos), $cliente['id']]);
    $importacion_bd_id = $pdo->lastInsertId();

    // Tabla destino según tipo
    $tabla_destino = match($tipo) {
        'CNE' => 'demanda_cne',
        'IQ' => 'demanda_iq',
        'PROC' => 'demanda_proc'
    };

    // Procesar datos
    $exitosos = 0;
    $fallidos = 0;
    $errores = [];

    $stmt_insert = $pdo->prepare("
        INSERT IGNORE INTO $tabla_destino (
            _id, RUN, PRIMER_APELLIDO, SEGUNDO_APELLIDO, NOMBRES,
            ESTAB_ORIG, ESTAB_DEST, ESPECIALIDAD_ESTANDAR, PRESTA_MIN,
            F_INGRE, F_SALIDA, ESTADO, PRIORIDAD_LE, DIAS_ESPERA, CIE10_HOMOLOGADO
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($datos as $indice => $registro) {
        try {
            // Validar campos requeridos
            if (empty($registro['_id']) || empty($registro['run'])) {
                throw new Exception("Campos requeridos: _id, run");
            }

            // Ejecutar INSERT
            $stmt_insert->execute([
                $registro['_id'] ?? null,
                $registro['run'] ?? null,
                $registro['primer_apellido'] ?? null,
                $registro['segundo_apellido'] ?? null,
                $registro['nombres'] ?? null,
                $registro['estab_orig'] ?? null,
                $registro['estab_dest'] ?? null,
                $registro['especialidad'] ?? null,
                $registro['prestacion'] ?? null,
                $registro['fecha_ingreso'] ?? null,
                $registro['fecha_salida'] ?? null,
                $registro['estado'] ?? 'VIGENTE',
                $registro['prioridad'] ?? null,
                $registro['dias_espera'] ?? null,
                $registro['cie10'] ?? null
            ]);

            $exitosos++;

            // Registrar detalle de éxito
            $stmt_detalle = $pdo->prepare("
                INSERT INTO importacion_detalles (importacion_id, linea_numero, run, estado, mensaje)
                VALUES (?, ?, ?, 'exitoso', 'Registro importado')
            ");
            $stmt_detalle->execute([$importacion_bd_id, $indice + 1, $registro['run']]);

        } catch (Exception $e) {
            $fallidos++;
            $error_msg = $e->getMessage();
            $errores[] = [
                'linea' => $indice + 1,
                'run' => $registro['run'] ?? 'N/A',
                'error' => $error_msg
            ];

            // Registrar detalle de error
            $stmt_detalle = $pdo->prepare("
                INSERT INTO importacion_detalles (importacion_id, linea_numero, run, estado, mensaje)
                VALUES (?, ?, ?, 'error', ?)
            ");
            $stmt_detalle->execute([$importacion_bd_id, $indice + 1, $registro['run'] ?? 'N/A', $error_msg]);
        }
    }

    // Actualizar registro de importación
    $estado_final = ($fallidos === 0) ? 'completado' : 'completado';
    $stmt_actualizar = $pdo->prepare("
        UPDATE importaciones
        SET registros_exitosos = ?, registros_fallidos = ?, estado = ?, fecha_fin = NOW(), progreso_porcentaje = 100
        WHERE id = ?
    ");
    $stmt_actualizar->execute([$exitosos, $fallidos, $estado_final, $importacion_bd_id]);

    // Registrar acceso API
    registrarAccesoAPI($cliente, '/importar/json', 'POST', 'ok');

    // Respuesta exitosa (202 Accepted)
    http_response_code(202);
    $respuesta = new ApiResponse();
    $respuesta->setDatos([
        'importacion_id' => $importacion_id,
        'tipo' => $tipo,
        'metodo' => 'json',
        'total_registros' => count($datos),
        'registros_exitosos' => $exitosos,
        'registros_fallidos' => $fallidos,
        'tasa_exito' => round(($exitosos / count($datos)) * 100, 2) . '%'
    ])
    ->setMensaje('Importación iniciada. Consulta estado con GET /importar/estado/' . $importacion_id)
    ->agregarMetadato('importacion_id', $importacion_id)
    ->agregarMetadato('estado_url', "/api/v1/importar/estado/$importacion_id")
    ->agregarMetadato('timestamp', date('c'))
    ->enviar();

} catch (PDOException $e) {
    registrarAccesoAPI($cliente, '/importar/json', 'POST', 'error');
    error_log("Error en POST /importar/json: " . $e->getMessage());
    ApiResponse::error('Error en base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    registrarAccesoAPI($cliente, '/importar/json', 'POST', 'error');
    error_log("Error en POST /importar/json: " . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
?>
