<?php
/**
 * GET /api/v1/listas_espera/vigentes
 *
 * Obtiene lista de espera vigentes (ESTADO='VIGENTE', F_SALIDA IS NULL)
 *
 * Parámetros de Query:
 *   - tipo: CNE, IQ, PROC (default: todos)
 *   - especialidad: nombre de especialidad (búsqueda parcial)
 *   - establecimiento: ID o nombre del establecimiento (búsqueda)
 *   - prioridad: CRÍTICA, ALTA, MEDIA, BAJA
 *   - dias_espera_min: mínimo días de espera
 *   - dias_espera_max: máximo días de espera
 *   - pagina: número de página (default: 1)
 *   - por_pagina: registros por página (default: 100, máximo: 1000)
 *   - ordenar_por: dias_espera, fecha_ingreso, prioridad (default: fecha_ingreso DESC)
 *
 * Respuesta: JSON con array de vigentes y paginación
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

try {
    $pdo = getConexionSiglech();

    // Parámetros
    $tipo = $_GET['tipo'] ?? null;
    $especialidad = $_GET['especialidad'] ?? null;
    $establecimiento = $_GET['establecimiento'] ?? null;
    $prioridad = $_GET['prioridad'] ?? null;
    $dias_min = isset($_GET['dias_espera_min']) ? intval($_GET['dias_espera_min']) : null;
    $dias_max = isset($_GET['dias_espera_max']) ? intval($_GET['dias_espera_max']) : null;

    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $por_pagina = min(1000, max(1, intval($_GET['por_pagina'] ?? 100)));
    $ordenar_por = $_GET['ordenar_por'] ?? 'F_ENTRADA DESC';

    // Validar ordenamiento
    $ordenamientos_permitidos = ['dias_espera', 'fecha_ingreso', 'prioridad', 'F_ENTRADA DESC'];
    if (!in_array($ordenar_por, $ordenamientos_permitidos)) {
        $ordenar_por = 'F_ENTRADA DESC';
    }

    // Construir query base (UNION de las 3 tablas)
    $where_base = "WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL";

    $conditions = [];
    $params = [];

    // Filtros comunes
    if ($especialidad) {
        $conditions[] = "ESPECIALIDAD_ESTANDAR LIKE ?";
        $params[] = '%' . $especialidad . '%';
    }

    if ($establecimiento) {
        $conditions[] = "(ESTAB_DEST LIKE ? OR ESTAB_DEST = ?)";
        $params[] = '%' . $establecimiento . '%';
        $params[] = intval($establecimiento);
    }

    if ($prioridad) {
        // Las tablas demanda_* no tienen PRIORIDAD_LE, calcular basado en DIAS_ESPERA
        if ($prioridad === 'CRÍTICA') {
            $conditions[] = "DIAS_ESPERA >= 180";
        } elseif ($prioridad === 'ALTA') {
            $conditions[] = "DIAS_ESPERA >= 90 AND DIAS_ESPERA < 180";
        } elseif ($prioridad === 'MEDIA') {
            $conditions[] = "DIAS_ESPERA >= 30 AND DIAS_ESPERA < 90";
        } elseif ($prioridad === 'BAJA') {
            $conditions[] = "DIAS_ESPERA < 30";
        }
    }

    if ($dias_min !== null) {
        $conditions[] = "DIAS_ESPERA >= ?";
        $params[] = $dias_min;
    }

    if ($dias_max !== null) {
        $conditions[] = "DIAS_ESPERA <= ?";
        $params[] = $dias_max;
    }

    $where_sql = $where_base . (count($conditions) > 0 ? ' AND ' . implode(' AND ', $conditions) : '');

    // Query unificada según tipo
    if ($tipo === 'CNE') {
        $tabla = 'demanda_cne';
    } elseif ($tipo === 'IQ') {
        $tabla = 'demanda_iq';
    } elseif ($tipo === 'PROC') {
        $tabla = 'demanda_proc';
    } else {
        // Todas las tablas si no especifica tipo
        $tabla = null;
    }

    if ($tabla) {
        // Una sola tabla
        $sql_count = "SELECT COUNT(*) as total FROM $tabla $where_sql";
        $sql_datos = "
            SELECT
                id, SIGTE_ID as folio, '$tipo' as tipo,
                RUN, CONCAT(PRIMER_APELLIDO, ' ', SEGUNDO_APELLIDO, ', ', NOMBRES) as paciente,
                ESPECIALIDAD_ESTANDAR as especialidad,
                ESTAB_ORIG as est_origen, ESTAB_DEST as est_destino,
                DIAS_ESPERA as dias_espera, F_ENTRADA as fecha_ingreso,
                CASE
                    WHEN DIAS_ESPERA >= 180 THEN 'CRÍTICA'
                    WHEN DIAS_ESPERA >= 90 THEN 'ALTA'
                    WHEN DIAS_ESPERA >= 30 THEN 'MEDIA'
                    ELSE 'BAJA'
                END as prioridad, ESTADO,
                CIE10_HOMOLOGADO as cie10
            FROM $tabla
            $where_sql
            ORDER BY $ordenar_por
            LIMIT ?, ?
        ";
    } else {
        // UNION de todas las tablas
        $sql_union = "
            (SELECT id, SIGTE_ID as folio, 'CNE' as tipo, RUN, CONCAT(PRIMER_APELLIDO, ' ', SEGUNDO_APELLIDO, ', ', NOMBRES) as paciente,
                    ESPECIALIDAD_ESTANDAR, ESTAB_ORIG, ESTAB_DEST, DIAS_ESPERA, F_ENTRADA,
                    CASE WHEN DIAS_ESPERA >= 180 THEN 'CRÍTICA' WHEN DIAS_ESPERA >= 90 THEN 'ALTA' WHEN DIAS_ESPERA >= 30 THEN 'MEDIA' ELSE 'BAJA' END as prioridad,
                    ESTADO, CIE10_HOMOLOGADO
             FROM demanda_cne WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL)
            UNION ALL
            (SELECT id, SIGTE_ID, 'IQ', RUN, CONCAT(PRIMER_APELLIDO, ' ', SEGUNDO_APELLIDO, ', ', NOMBRES),
                    ESPECIALIDAD_ESTANDAR, ESTAB_ORIG, ESTAB_DEST, DIAS_ESPERA, F_ENTRADA,
                    CASE WHEN DIAS_ESPERA >= 180 THEN 'CRÍTICA' WHEN DIAS_ESPERA >= 90 THEN 'ALTA' WHEN DIAS_ESPERA >= 30 THEN 'MEDIA' ELSE 'BAJA' END,
                    ESTADO, CIE10_HOMOLOGADO
             FROM demanda_iq WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL)
            UNION ALL
            (SELECT id, SIGTE_ID, 'PROC', RUN, CONCAT(PRIMER_APELLIDO, ' ', SEGUNDO_APELLIDO, ', ', NOMBRES),
                    ESPECIALIDAD_ESTANDAR, ESTAB_ORIG, ESTAB_DEST, DIAS_ESPERA, F_ENTRADA,
                    CASE WHEN DIAS_ESPERA >= 180 THEN 'CRÍTICA' WHEN DIAS_ESPERA >= 90 THEN 'ALTA' WHEN DIAS_ESPERA >= 30 THEN 'MEDIA' ELSE 'BAJA' END,
                    ESTADO, CIE10_HOMOLOGADO
             FROM demanda_proc WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL)
        ";

        $sql_count = "SELECT COUNT(*) as total FROM ($sql_union) as unificada WHERE 1=1";
        if (count($conditions) > 0) {
            $where_filtros = implode(' AND ', $conditions);
            $sql_count .= " AND ($where_filtros)";
        }

        $sql_datos = "SELECT * FROM ($sql_union) as unificada WHERE 1=1";
        if (count($conditions) > 0) {
            $sql_datos .= " AND ($where_filtros)";
        }
        $sql_datos .= " ORDER BY $ordenar_por LIMIT ?, ?";
    }

    // Total
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Datos paginados
    $offset = ($pagina - 1) * $por_pagina;
    $params_pagina = array_merge($params, [$offset, $por_pagina]);

    $stmt = $pdo->prepare($sql_datos);
    $stmt->execute($params_pagina);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta
    $respuesta = new ApiResponse();
    $respuesta->setDatos($datos)
        ->setPaginacion($pagina, $por_pagina, $total)
        ->agregarMetadato('timestamp', date('c'))
        ->agregarMetadato('cliente', $cliente['nombre'])
        ->enviar();

} catch (PDOException $e) {
    registrarAccesoAPI($cliente, '/listas_espera/vigentes', 'GET', 'error');
    error_log("Error en vigentes.php: " . $e->getMessage());
    ApiResponse::error('Error en base de datos', 500);
}

registrarAccesoAPI($cliente, '/listas_espera/vigentes', 'GET', 'ok');
?>
