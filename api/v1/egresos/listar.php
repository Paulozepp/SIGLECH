<?php
/**
 * GET /api/v1/egresos/listar
 *
 * Obtiene listado de egresos con filtros opcionales
 *
 * Parámetros de Query:
 *   - tipo: CNE, IQ, PROC (default: todos)
 *   - razon_egreso: filtrar por razón
 *   - resultado_tratamiento: filtrar por resultado
 *   - requiere_seguimiento: true/false
 *   - registro_completo: true/false (solo registros completos)
 *   - fecha_desde: YYYY-MM-DD
 *   - fecha_hasta: YYYY-MM-DD
 *   - run: buscar por RUN específico
 *   - pagina: número de página (default: 1)
 *   - por_pagina: registros por página (default: 50)
 *
 * Respuesta: Array de egresos con paginación
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
    $pdo = getConexionSiglech();

    // Parámetros
    $tipo = $_GET['tipo'] ?? null;
    $razon_egreso = $_GET['razon_egreso'] ?? null;
    $resultado = $_GET['resultado_tratamiento'] ?? null;
    $requiere_seguimiento = $_GET['requiere_seguimiento'] ?? null;
    $completo = $_GET['registro_completo'] ?? null;
    $fecha_desde = $_GET['fecha_desde'] ?? null;
    $fecha_hasta = $_GET['fecha_hasta'] ?? null;
    $run = $_GET['run'] ?? null;

    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $por_pagina = min(500, max(10, intval($_GET['por_pagina'] ?? 50)));

    // Construir WHERE
    $where_parts = [];
    $params = [];

    if ($tipo && in_array(strtoupper($tipo), ['CNE', 'IQ', 'PROC'])) {
        $where_parts[] = "tipo_lista = ?";
        $params[] = strtoupper($tipo);
    }

    if ($razon_egreso) {
        $where_parts[] = "razon_egreso = ?";
        $params[] = $razon_egreso;
    }

    if ($resultado) {
        $where_parts[] = "resultado_tratamiento = ?";
        $params[] = $resultado;
    }

    if ($requiere_seguimiento !== null) {
        $where_parts[] = "requiere_seguimiento = ?";
        $params[] = $requiere_seguimiento === 'true' ? 1 : 0;
    }

    if ($completo !== null) {
        $where_parts[] = "registro_completo = ?";
        $params[] = $completo === 'true' ? 1 : 0;
    }

    if ($fecha_desde) {
        $where_parts[] = "fecha_egreso >= ?";
        $params[] = $fecha_desde;
    }

    if ($fecha_hasta) {
        $where_parts[] = "fecha_egreso <= ?";
        $params[] = $fecha_hasta;
    }

    if ($run) {
        $where_parts[] = "run LIKE ?";
        $params[] = '%' . $run . '%';
    }

    $where_sql = count($where_parts) > 0 ? "WHERE " . implode(" AND ", $where_parts) : "";

    // Obtener total
    $sql_count = "SELECT COUNT(*) as total FROM egresos $where_sql";
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Obtener datos paginados
    $offset = ($pagina - 1) * $por_pagina;
    $params_paginacion = array_merge($params, [$offset, $por_pagina]);

    $sql_datos = "
        SELECT
            id, egreso_id, run, tipo_lista, fecha_egreso,
            razon_egreso, especialista_nombre, especialista_especialidad,
            diagnostico_principal, procedimiento_realizado,
            resultado_tratamiento, requiere_seguimiento,
            recomendaciones_seguimiento, intervalo_seguimiento_dias,
            registro_completo, usuario_registra_nombre, fecha_registro,
            fecha_modificacion
        FROM egresos
        $where_sql
        ORDER BY fecha_egreso DESC, id DESC
        LIMIT ?, ?
    ";

    $stmt = $pdo->prepare($sql_datos);
    $stmt->execute($params_paginacion);
    $egresos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Registrar acceso API
    registrarAccesoAPI($cliente, '/egresos/listar', 'GET', 'ok');

    // Respuesta
    $respuesta = new ApiResponse();
    $respuesta->setDatos($egresos)
        ->setPaginacion($pagina, $por_pagina, $total)
        ->agregarMetadato('total_egresos', $total)
        ->agregarMetadato('filtros_aplicados', [
            'tipo' => $tipo,
            'razon_egreso' => $razon_egreso,
            'resultado_tratamiento' => $resultado,
            'requiere_seguimiento' => $requiere_seguimiento,
            'registro_completo' => $completo,
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
        ])
        ->agregarMetadato('timestamp', date('c'))
        ->enviar();

} catch (PDOException $e) {
    registrarAccesoAPI($cliente, '/egresos/listar', 'GET', 'error');
    error_log("Error en GET /egresos/listar: " . $e->getMessage());
    ApiResponse::error('Error en base de datos', 500);
}
?>
