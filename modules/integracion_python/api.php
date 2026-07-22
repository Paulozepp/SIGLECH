<?php
/**
 * API para datos de integración Python
 * GET /modules/integracion_python/api.php
 * Retorna estadísticas en JSON para monitoreo remoto
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

try {
    $pdo = getConexionSiglech();

    // Estadísticas globales
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_importaciones,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as con_error,
            MAX(fecha_inicio) as ultima_actualizacion
        FROM importaciones
        WHERE metodo = 'json'
    ");
    $stats_globales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Por tipo
    $stmt = $pdo->query("
        SELECT
            tipo,
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as con_error
        FROM importaciones
        WHERE metodo = 'json'
        GROUP BY tipo
    ");
    $stats_por_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimas 5 importaciones
    $stmt = $pdo->query("
        SELECT
            importacion_id,
            tipo,
            total_registros,
            registros_exitosos,
            registros_fallidos,
            estado,
            fecha_inicio,
            cliente_id
        FROM importaciones
        WHERE metodo = 'json'
        ORDER BY fecha_inicio DESC
        LIMIT 5
    ");
    $ultimas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Registros de prueba
    $stmt = $pdo->prepare("
        SELECT tipo, COUNT(*) as cantidad, MAX(fecha_carga) as ultima_carga
        FROM (
            SELECT 'CNE' as tipo, fecha_carga FROM demanda_cne WHERE _id LIKE '%PRUEBA%'
            UNION ALL
            SELECT 'IQ' as tipo, fecha_carga FROM demanda_iq WHERE _id LIKE '%PRUEBA%'
            UNION ALL
            SELECT 'PROC' as tipo, fecha_carga FROM demanda_proc WHERE _id LIKE '%PRUEBA%'
        ) as pruebas
        GROUP BY tipo
    ");
    $stmt->execute();
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta
    echo json_encode([
        'success' => true,
        'timestamp' => date('c'),
        'estadisticas' => [
            'global' => $stats_globales,
            'por_tipo' => $stats_por_tipo,
            'ultimas_importaciones' => $ultimas,
            'registros_prueba' => $pruebas
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
