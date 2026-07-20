<?php
/**
 * Registrar nueva gestión en la bitácora
 */

require_once __DIR__ . '/../SICOCH/db.php';
require_once __DIR__ . '/../SICOCH/auth/guard.php';

$pdoSiglech = getConexionSiglech();
$user = requiereLogin();

$sigte = trim($_POST['sigte'] ?? '');
$tipo = trim($_POST['tipo'] ?? '');

if (!$sigte || !$tipo) {
    die(json_encode(['error' => 'Parámetros requeridos']));
}

try {
    // Obtener datos del demanda
    $sqlPaciente = match($tipo) {
        'CNE' => "SELECT RUN FROM demanda_cne WHERE SIGTE_ID = ? LIMIT 1",
        'IQ' => "SELECT RUN FROM demanda_iq WHERE SIGTE_ID = ? LIMIT 1",
        'PROC' => "SELECT RUN FROM demanda_proc WHERE SIGTE_ID = ? LIMIT 1",
        default => null
    };

    $stPac = $pdoSiglech->prepare($sqlPaciente);
    $stPac->execute([$sigte]);
    $paciente = $stPac->fetch();

    if (!$paciente) {
        throw new Exception('Paciente no encontrado');
    }

    // Obtener o crear ficha
    $stFicha = $pdoSiglech->prepare("
        SELECT id FROM ficha_gestion_egreso
        WHERE tipo_demanda = ? AND sigte_id = ? AND run_paciente = ?
        LIMIT 1
    ");
    $stFicha->execute([$tipo, $sigte, $paciente['RUN']]);
    $ficha = $stFicha->fetch();

    if (!$ficha) {
        throw new Exception('Ficha no existe. Crear ficha primero');
    }

    // Insertar gestión
    $stGestion = $pdoSiglech->prepare("
        INSERT INTO bitacora_gestiones_siglech (
            ficha_id, sigte_id, run_paciente,
            tipo_gestion, resultado,
            numero_usado, profesional_nombre, profesional_rut,
            observacion, creado_por
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stGestion->execute([
        $ficha['id'],
        $sigte,
        $paciente['RUN'],
        $_POST['tipo_gestion'] ?? 'OTRO',
        $_POST['resultado'] ?? 'OTRO',
        $_POST['numero_usado'] ?? null,
        $user['nombre'] ?? $user['email'] ?? 'Sistema',
        $user['rut'] ?? '',
        $_POST['observacion'] ?? '',
        $user['nombre'] ?? 'Sistema'
    ]);

    // Actualizar contador en ficha
    $pdoSiglech->prepare("
        UPDATE ficha_gestion_egreso SET
            total_contactos = total_contactos + 1,
            ultimo_contacto = NOW()
        WHERE id = ?
    ")->execute([$ficha['id']]);

    // Redirigir
    header('Location: ficha_gestion_egreso.php?sigte=' . urlencode($sigte) . '&tipo=' . urlencode($tipo) . '&msg=Gestión%20registrada');
    exit;

} catch (Exception $e) {
    header('Location: ficha_gestion_egreso.php?sigte=' . urlencode($sigte) . '&tipo=' . urlencode($tipo) . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>
