<?php
/**
 * GET /api/v1/importar/estado/{importacion_id}
 *
 * Obtiene el estado de una importación en progreso
 *
 * Parámetros de Query:
 *   - importacion_id: ID de la importación (ej: IMP-2026-07-21-xyz123)
 *   - incluir_errores: si incluir detalles de errores (default: false)
 *
 * Respuesta: Información de estado y progreso
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_respuesta.php';

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ApiResponse::error('Método no permitido. Use GET.', 405);
}

// Autenticar
$cliente = verificarTokenAPI();
verificarPermiso($cliente, 'lectura');

// Obtener parámetros
$importacion_id = $_GET['importacion_id'] ?? null;
$incluir_errores = isset($_GET['incluir_errores']) && $_GET['incluir_errores'] === 'true';

if (!$importacion_id) {
    ApiResponse::error('Parámetro requerido: importacion_id', 400);
}

try {
    $pdo = getConexionSiglech();

    // Obtener información de importación
    $stmt = $pdo->prepare("
        SELECT id, importacion_id, tipo, metodo, total_registros,
               registros_exitosos, registros_fallidos, estado,
               progreso_porcentaje, fecha_inicio, fecha_fin,
               observaciones
        FROM importaciones
        WHERE importacion_id = ?
        LIMIT 1
    ");
    $stmt->execute([$importacion_id]);
    $importacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$importacion) {
        registrarAccesoAPI($cliente, '/importar/estado', 'GET', 'not_found');
        ApiResponse::noEncontrado();
    }

    // Construir respuesta
    $respuesta_datos = [
        'importacion_id' => $importacion['importacion_id'],
        'tipo' => $importacion['tipo'],
        'metodo' => $importacion['metodo'],
        'estado' => $importacion['estado'],
        'progreso_porcentaje' => intval($importacion['progreso_porcentaje']),
        'registros' => [
            'total' => intval($importacion['total_registros']),
            'exitosos' => intval($importacion['registros_exitosos']),
            'fallidos' => intval($importacion['registros_fallidos']),
            'tasa_exito' => $importacion['total_registros'] > 0
                ? round((intval($importacion['registros_exitosos']) / intval($importacion['total_registros'])) * 100, 2)
                : 0
        ],
        'timestamps' => [
            'inicio' => $importacion['fecha_inicio'],
            'fin' => $importacion['fecha_fin'],
            'duracion' => $importacion['fecha_fin']
                ? round(strtotime($importacion['fecha_fin']) - strtotime($importacion['fecha_inicio'])) . 's'
                : 'en progreso'
        ]
    ];

    // Incluir errores si se solicita
    if ($incluir_errores && $importacion['registros_fallidos'] > 0) {
        $stmt_errores = $pdo->prepare("
            SELECT linea_numero, run, mensaje
            FROM importacion_detalles
            WHERE importacion_id = ? AND estado = 'error'
            ORDER BY linea_numero
            LIMIT 100
        ");
        $stmt_errores->execute([$importacion['id']]);
        $errores = $stmt_errores->fetchAll(PDO::FETCH_ASSOC);
        $respuesta_datos['errores'] = $errores;
    }

    // Registrar acceso API
    registrarAccesoAPI($cliente, '/importar/estado', 'GET', 'ok');

    // Retornar respuesta
    $respuesta = new ApiResponse();
    $respuesta->setDatos($respuesta_datos)
        ->agregarMetadato('timestamp', date('c'))
        ->agregarMetadato('cliente', $cliente['nombre'])
        ->enviar();

} catch (PDOException $e) {
    registrarAccesoAPI($cliente, '/importar/estado', 'GET', 'error');
    error_log("Error en GET /importar/estado: " . $e->getMessage());
    ApiResponse::error('Error en base de datos', 500);
}
?>
