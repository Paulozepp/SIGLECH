<?php
/**
 * POST /api/v1/egresos/crear
 *
 * Crea un nuevo registro de egreso (alta hospitalaria/salida de lista de espera)
 * Soporta estructura MINSAL Norm 118
 *
 * Body JSON esperado:
 * {
 *   "run": "12345678-9",
 *   "tipo_lista": "CNE",
 *   "fecha_egreso": "2026-07-21",
 *   "razon_egreso": "Atendido",
 *   "especialista_nombre": "Dr. Juan Rodríguez",
 *   "especialista_especialidad": "Cardiología",
 *   "fecha_cita": "2026-07-28",
 *   "diagnostico_principal": "HTA controlada",
 *   "diagnostico_secundarios": "Dislipidemia",
 *   "procedimiento_realizado": "Consulta cardiológica",
 *   "cie10_principal": "I10",
 *   "resultado_tratamiento": "Mejorado",
 *   "requiere_seguimiento": true,
 *   "recomendaciones_seguimiento": "Control en 30 días",
 *   "intervalo_seguimiento_dias": 30,
 *   "medicamentos_prescritos": "Losartan 50mg, Atorvastatina 20mg"
 * }
 *
 * Respuesta: 201 Created con datos del egreso creado
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_respuesta.php';

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
$datos = json_decode($json, true);

if (!$datos) {
    ApiResponse::error('JSON inválido o vacío', 400);
}

// Validar campos requeridos
$campos_requeridos = ['run', 'tipo_lista', 'fecha_egreso', 'razon_egreso'];
foreach ($campos_requeridos as $campo) {
    if (empty($datos[$campo])) {
        ApiResponse::error("Campo requerido: $campo", 400);
    }
}

// Validar tipo de lista
$tipo_lista = strtoupper($datos['tipo_lista']);
if (!in_array($tipo_lista, ['CNE', 'IQ', 'PROC'])) {
    ApiResponse::error("Tipo de lista inválido: $tipo_lista", 400);
}

// Validar razon_egreso
$razones_validas = ['Atendido', 'Cancelado', 'No Comparece', 'Cambio de establecimiento', 'Derivado', 'Otro'];
if (!in_array($datos['razon_egreso'], $razones_validas)) {
    ApiResponse::error("Razon de egreso inválida", 400);
}

// Validar resultado_tratamiento
$resultados_validos = ['Curado', 'Mejorado', 'Sin Cambios', 'Empeorado', 'No Respondió', 'No Evaluable'];
if (!empty($datos['resultado_tratamiento']) && !in_array($datos['resultado_tratamiento'], $resultados_validos)) {
    ApiResponse::error("Resultado de tratamiento inválido", 400);
}

try {
    $pdo = getConexionSiglech();

    // Generar egreso_id único
    $egreso_id = "EGR-" . date('Y-m-d') . "-" . uniqid();

    // Validaciones adicionales
    if (!validarRUN($datos['run'])) {
        ApiResponse::error("RUN inválido: {$datos['run']}", 400);
    }

    if (!validarFecha($datos['fecha_egreso'])) {
        ApiResponse::error("Fecha egreso inválida", 400);
    }

    // Preparar INSERT
    $stmt = $pdo->prepare("
        INSERT INTO egresos (
            egreso_id, run, tipo_lista, fecha_egreso, razon_egreso,
            establecimiento_destino_nombre, especialista_nombre,
            especialista_especialidad, fecha_cita,
            diagnostico_principal, diagnostico_secundarios,
            procedimiento_realizado, cie10_principal, cie10_secundarios,
            resultado_tratamiento, requiere_seguimiento,
            recomendaciones_seguimiento, intervalo_seguimiento_dias,
            medicamentos_prescritos, usuario_registra_nombre, registro_completo
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?
        )
    ");

    $completo = !empty($datos['diagnostico_principal']) && !empty($datos['resultado_tratamiento']);

    $stmt->execute([
        $egreso_id,
        $datos['run'],
        $tipo_lista,
        $datos['fecha_egreso'],
        $datos['razon_egreso'],
        $datos['establecimiento_destino_nombre'] ?? null,
        $datos['especialista_nombre'] ?? null,
        $datos['especialista_especialidad'] ?? null,
        $datos['fecha_cita'] ?? null,
        $datos['diagnostico_principal'] ?? null,
        $datos['diagnostico_secundarios'] ?? null,
        $datos['procedimiento_realizado'] ?? null,
        $datos['cie10_principal'] ?? null,
        $datos['cie10_secundarios'] ?? null,
        $datos['resultado_tratamiento'] ?? null,
        isset($datos['requiere_seguimiento']) ? ($datos['requiere_seguimiento'] ? 1 : 0) : 0,
        $datos['recomendaciones_seguimiento'] ?? null,
        $datos['intervalo_seguimiento_dias'] ?? null,
        $datos['medicamentos_prescritos'] ?? null,
        $cliente['nombre'],
        $completo ? 1 : 0
    ]);

    $egreso_bd_id = $pdo->lastInsertId();

    // Registrar en auditoría
    $stmt_audit = $pdo->prepare("
        INSERT INTO egresos_auditoria (egreso_id, usuario_nombre, accion, valores_nuevos)
        VALUES (?, ?, 'crear', ?)
    ");
    $stmt_audit->execute([
        $egreso_bd_id,
        $cliente['nombre'],
        json_encode($datos, JSON_UNESCAPED_UNICODE)
    ]);

    // Registrar acceso API
    registrarAccesoAPI($cliente, '/egresos/crear', 'POST', 'ok');

    // Respuesta exitosa (201 Created)
    http_response_code(201);
    $respuesta = new ApiResponse();
    $respuesta->setDatos([
        'egreso_id' => $egreso_id,
        'id' => $egreso_bd_id,
        'run' => $datos['run'],
        'tipo_lista' => $tipo_lista,
        'fecha_egreso' => $datos['fecha_egreso'],
        'razon_egreso' => $datos['razon_egreso'],
        'registro_completo' => $completo,
        'estado' => 'creado'
    ])
    ->setMensaje('Egreso registrado exitosamente')
    ->agregarMetadato('egreso_id', $egreso_id)
    ->agregarMetadato('url_detalle', "/api/v1/egresos/$egreso_bd_id")
    ->agregarMetadato('timestamp', date('c'))
    ->enviar();

} catch (PDOException $e) {
    registrarAccesoAPI($cliente, '/egresos/crear', 'POST', 'error');
    error_log("Error en POST /egresos/crear: " . $e->getMessage());
    ApiResponse::error('Error al crear egreso: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    registrarAccesoAPI($cliente, '/egresos/crear', 'POST', 'error');
    error_log("Error en POST /egresos/crear: " . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}

/**
 * Valida formato RUN
 */
function validarRUN(string $run): bool {
    $run = preg_replace('/[^0-9kK]/', '', $run);
    if (strlen($run) < 7 || strlen($run) > 9) {
        return false;
    }

    $numero = substr($run, 0, -1);
    $digito = strtoupper(substr($run, -1));
    $suma = 0;
    $multiplicador = 2;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += intval($numero[$i]) * $multiplicador;
        $multiplicador++;
        if ($multiplicador > 7) $multiplicador = 2;
    }

    $digito_calc = 11 - ($suma % 11);
    if ($digito_calc === 11) $digito_calc = 0;
    elseif ($digito_calc === 10) $digito_calc = 'K';

    return $digito === (string)$digito_calc;
}

/**
 * Valida formato de fecha
 */
function validarFecha(string $fecha): bool {
    $dt = \DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt && $dt->format('Y-m-d') === $fecha;
}
?>
