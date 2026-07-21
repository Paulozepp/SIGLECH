<?php
/**
 * PUT /api/v1/egresos/{egreso_id}
 *
 * Actualiza un egreso existente
 *
 * Body JSON: los campos a actualizar
 *
 * Respuesta: 200 OK con datos actualizados
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_respuesta.php';

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    ApiResponse::error('Método no permitido. Use PUT.', 405);
}

// Autenticar
$cliente = verificarTokenAPI();
verificarPermiso($cliente, 'escritura');

// Obtener egreso_id de la URL
$egreso_id = $_GET['egreso_id'] ?? null;
if (!$egreso_id) {
    ApiResponse::error('Parámetro requerido: egreso_id', 400);
}

// Obtener JSON del body
$json = file_get_contents('php://input');
$datos_actualizacion = json_decode($json, true);

if (!$datos_actualizacion || empty($datos_actualizacion)) {
    ApiResponse::error('JSON vacío o inválido', 400);
}

try {
    $pdo = getConexionSiglech();

    // Obtener egreso actual
    $stmt = $pdo->prepare("
        SELECT * FROM egresos WHERE id = ?
    ");
    $stmt->execute([$egreso_id]);
    $egreso_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$egreso_actual) {
        ApiResponse::noEncontrado();
    }

    // Campos permitidos para actualizar
    $campos_permitidos = [
        'razon_egreso', 'establecimiento_destino_nombre',
        'especialista_nombre', 'especialista_especialidad', 'fecha_cita',
        'diagnostico_principal', 'diagnostico_secundarios',
        'procedimiento_realizado', 'cie10_principal', 'cie10_secundarios',
        'resultado_tratamiento', 'requiere_seguimiento',
        'recomendaciones_seguimiento', 'intervalo_seguimiento_dias',
        'medicamentos_prescritos', 'observaciones'
    ];

    // Construir SET SQL
    $set_parts = [];
    $params = [];
    $valores_nuevos = [];

    foreach ($campos_permitidos as $campo) {
        if (isset($datos_actualizacion[$campo])) {
            $valor = $datos_actualizacion[$campo];

            // Validaciones específicas
            if ($campo === 'resultado_tratamiento') {
                $resultados_validos = ['Curado', 'Mejorado', 'Sin Cambios', 'Empeorado', 'No Respondió', 'No Evaluable'];
                if (!in_array($valor, $resultados_validos)) {
                    ApiResponse::error("Resultado de tratamiento inválido", 400);
                }
            }

            $set_parts[] = "$campo = ?";
            $params[] = $valor;
            $valores_nuevos[$campo] = $valor;
        }
    }

    if (empty($set_parts)) {
        ApiResponse::error('No hay campos válidos para actualizar', 400);
    }

    // Agregar usuario y fecha de modificación
    $set_parts[] = "usuario_modifica_nombre = ?";
    $params[] = $cliente['nombre'];

    $set_parts[] = "fecha_modificacion = NOW()";

    // Agregar ID para WHERE
    $params[] = $egreso_id;

    // Ejecutar UPDATE
    $sql_update = "
        UPDATE egresos
        SET " . implode(", ", $set_parts) . "
        WHERE id = ?
    ";

    $stmt = $pdo->prepare($sql_update);
    $stmt->execute($params);

    // Registrar en auditoría
    $stmt_audit = $pdo->prepare("
        INSERT INTO egresos_auditoria (egreso_id, usuario_nombre, accion, valores_anteriores, valores_nuevos)
        VALUES (?, ?, 'actualizar', ?, ?)
    ");
    $stmt_audit->execute([
        $egreso_id,
        $cliente['nombre'],
        json_encode($egreso_actual, JSON_UNESCAPED_UNICODE),
        json_encode($valores_nuevos, JSON_UNESCAPED_UNICODE)
    ]);

    // Obtener datos actualizados
    $stmt = $pdo->prepare("
        SELECT * FROM egresos WHERE id = ?
    ");
    $stmt->execute([$egreso_id]);
    $egreso_actualizado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Registrar acceso API
    registrarAccesoAPI($cliente, '/egresos/actualizar', 'PUT', 'ok');

    // Respuesta
    http_response_code(200);
    $respuesta = new ApiResponse();
    $respuesta->setDatos($egreso_actualizado)
        ->setMensaje('Egreso actualizado exitosamente')
        ->agregarMetadato('campos_actualizados', array_keys($valores_nuevos))
        ->agregarMetadato('timestamp', date('c'))
        ->enviar();

} catch (PDOException $e) {
    registrarAccesoAPI($cliente, '/egresos/actualizar', 'PUT', 'error');
    error_log("Error en PUT /egresos/actualizar: " . $e->getMessage());
    ApiResponse::error('Error al actualizar egreso', 500);
} catch (Exception $e) {
    registrarAccesoAPI($cliente, '/egresos/actualizar', 'PUT', 'error');
    error_log("Error en PUT /egresos/actualizar: " . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
?>
