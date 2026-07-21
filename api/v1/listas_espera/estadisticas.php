<?php
/**
 * GET /api/v1/listas_espera/estadisticas
 *
 * Obtiene estadísticas de listas de espera vigentes
 *
 * Parámetros de Query:
 *   - tipo: CNE, IQ, PROC, todos (default: todos)
 *   - especialidad: filtrar por especialidad
 *   - establecimiento: filtrar por establecimiento
 *
 * Respuesta: JSON con KPIs por tipo de lista
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../v1/_auth.php';
require_once __DIR__ . '/../v1/_respuesta.php';

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ApiResponse::error('Método no permitido. Use GET.', 405);
}

// Autenticar
$cliente = verificarTokenAPI();
verificarPermiso($cliente, 'lectura');

try {
    $pdo = getConexion();

    // Parámetros
    $tipo = $_GET['tipo'] ?? 'todos';
    $especialidad = $_GET['especialidad'] ?? null;
    $establecimiento = $_GET['establecimiento'] ?? null;

    // Filtros opcionales
    $filtro_extra = "";
    $params = [];

    if ($especialidad) {
        $filtro_extra .= " AND ESPECIALIDAD_ESTANDAR LIKE ?";
        $params[] = '%' . $especialidad . '%';
    }

    if ($establecimiento) {
        $filtro_extra .= " AND (ESTAB_DEST LIKE ? OR ESTAB_DEST = ?)";
        $params[] = '%' . $establecimiento . '%';
        $params[] = intval($establecimiento);
    }

    $donde = "WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL $filtro_extra";

    // Función para obtener estadísticas de una tabla
    function obtenerEstadisticas($pdo, $tabla, $donde, $params) {
        $sql = "
            SELECT
                COUNT(*) as vigentes,
                ROUND(AVG(DIAS_ESPERA)) as promedio_dias,
                MAX(DIAS_ESPERA) as maximo_dias,
                MIN(DIAS_ESPERA) as minimo_dias,
                SUM(CASE WHEN DIAS_ESPERA >= 180 THEN 1 ELSE 0 END) as critica,
                SUM(CASE WHEN DIAS_ESPERA >= 90 AND DIAS_ESPERA < 180 THEN 1 ELSE 0 END) as alta,
                SUM(CASE WHEN DIAS_ESPERA >= 30 AND DIAS_ESPERA < 90 THEN 1 ELSE 0 END) as media,
                SUM(CASE WHEN DIAS_ESPERA < 30 THEN 1 ELSE 0 END) as baja
            FROM $tabla
            $donde
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener estadísticas
    $estadisticas = [];

    if ($tipo === 'CNE' || $tipo === 'todos') {
        $stats = obtenerEstadisticas($pdo, 'demanda_cne', $donde, $params);
        $estadisticas['CNE'] = [
            'nombre' => 'Consulta Nueva Especialidad',
            'vigentes' => intval($stats['vigentes']),
            'promedio_dias_espera' => intval($stats['promedio_dias']),
            'maximo_dias_espera' => intval($stats['maximo_dias']),
            'minimo_dias_espera' => intval($stats['minimo_dias']),
            'por_urgencia' => [
                'CRÍTICA (>180 días)' => intval($stats['critica']),
                'ALTA (90-180 días)' => intval($stats['alta']),
                'MEDIA (30-90 días)' => intval($stats['media']),
                'BAJA (<30 días)' => intval($stats['baja'])
            ]
        ];
    }

    if ($tipo === 'IQ' || $tipo === 'todos') {
        $stats = obtenerEstadisticas($pdo, 'demanda_iq', $donde, $params);
        $estadisticas['IQ'] = [
            'nombre' => 'Intervención Quirúrgica',
            'vigentes' => intval($stats['vigentes']),
            'promedio_dias_espera' => intval($stats['promedio_dias']),
            'maximo_dias_espera' => intval($stats['maximo_dias']),
            'minimo_dias_espera' => intval($stats['minimo_dias']),
            'por_urgencia' => [
                'CRÍTICA (>180 días)' => intval($stats['critica']),
                'ALTA (90-180 días)' => intval($stats['alta']),
                'MEDIA (30-90 días)' => intval($stats['media']),
                'BAJA (<30 días)' => intval($stats['baja'])
            ]
        ];
    }

    if ($tipo === 'PROC' || $tipo === 'todos') {
        $stats = obtenerEstadisticas($pdo, 'demanda_proc', $donde, $params);
        $estadisticas['PROC'] = [
            'nombre' => 'Procedimientos',
            'vigentes' => intval($stats['vigentes']),
            'promedio_dias_espera' => intval($stats['promedio_dias']),
            'maximo_dias_espera' => intval($stats['maximo_dias']),
            'minimo_dias_espera' => intval($stats['minimo_dias']),
            'por_urgencia' => [
                'CRÍTICA (>180 días)' => intval($stats['critica']),
                'ALTA (90-180 días)' => intval($stats['alta']),
                'MEDIA (30-90 días)' => intval($stats['media']),
                'BAJA (<30 días)' => intval($stats['baja'])
            ]
        ];
    }

    // Total vigentes
    $total_vigentes = array_sum(array_column($estadisticas, 'vigentes'));

    // Respuesta
    $respuesta = new ApiResponse();
    $respuesta->setDatos($estadisticas)
        ->agregarMetadato('total_vigentes', $total_vigentes)
        ->agregarMetadato('tipos_incluidos', array_keys($estadisticas))
        ->agregarMetadato('timestamp', date('c'))
        ->agregarMetadato('cliente', $cliente['nombre'])
        ->enviar();

} catch (PDOException $e) {
    registrarAccesoAPI($cliente, '/listas_espera/estadisticas', 'GET', 'error');
    error_log("Error en estadisticas.php: " . $e->getMessage());
    ApiResponse::error('Error en base de datos', 500);
}

registrarAccesoAPI($cliente, '/listas_espera/estadisticas', 'GET', 'ok');
?>
