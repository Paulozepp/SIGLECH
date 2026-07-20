<?php
/**
 * Ficha de Gestión y Egreso - SIGLECH
 * Registro de trazabilidad de gestiones, llamadas y mensajes a pacientes en lista de espera
 */

require_once __DIR__ . '/../SICOCH/db.php';
require_once __DIR__ . '/../SICOCH/auth/guard.php';

// Usar conexión SIGLECH
$pdoSiglech = getConexionSiglech();
$pdoSicoch = getConexion();

$user = requiereLogin();
$sigte_id = trim($_GET['sigte'] ?? '');
$tipo_demanda = trim($_GET['tipo'] ?? '');

if (!$sigte_id || !$tipo_demanda) {
    die('Parámetros requeridos: sigte, tipo');
}

// Obtener datos del paciente desde SIGLECH
$sqlPaciente = match($tipo_demanda) {
    'CNE' => "SELECT * FROM demanda_cne WHERE SIGTE_ID = ? LIMIT 1",
    'IQ' => "SELECT * FROM demanda_iq WHERE SIGTE_ID = ? LIMIT 1",
    'PROC' => "SELECT * FROM demanda_proc WHERE SIGTE_ID = ? LIMIT 1",
    default => null
};

if (!$sqlPaciente) {
    die('Tipo de demanda inválido');
}

$stPac = $pdoSiglech->prepare($sqlPaciente);
$stPac->execute([$sigte_id]);
$paciente = $stPac->fetch();

if (!$paciente) {
    die('Paciente no encontrado');
}

// Obtener o crear ficha
$stFicha = $pdoSiglech->prepare("
    SELECT * FROM ficha_gestion_egreso
    WHERE tipo_demanda = ? AND sigte_id = ? AND run_paciente = ?
    LIMIT 1
");
$stFicha->execute([$tipo_demanda, $sigte_id, $paciente['RUN']]);
$ficha = $stFicha->fetch();

// Procesar formulario
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$ficha) {
            // Crear nueva ficha
            $stCreate = $pdoSiglech->prepare("
                INSERT INTO ficha_gestion_egreso (
                    tipo_demanda, demanda_id, sigte_id, run_paciente, dv_paciente,
                    nombres_paciente, especialidad, f_entrada,
                    estado_egreso, causal_egreso, codigo_egreso, observaciones,
                    digitador_nombre, digitador_rut, creado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stCreate->execute([
                $tipo_demanda,
                $paciente['id'],
                $sigte_id,
                $paciente['RUN'],
                $paciente['DV'],
                trim(($paciente['PRIMER_APELLIDO'] ?? '') . ' ' . ($paciente['SEGUNDO_APELLIDO'] ?? '') . ' ' . ($paciente['NOMBRES'] ?? '')),
                $paciente['ESPECIALIDAD_ESTANDAR'] ?? '',
                $paciente['F_ENTRADA'],
                $_POST['estado_egreso'] ?? 'ACTIVO',
                $_POST['causal_egreso'] ?? '',
                $_POST['codigo_egreso'] ?? '',
                $_POST['observaciones'] ?? '',
                $user['nombre'] ?? $user['email'] ?? 'Sistema',
                $user['rut'] ?? '',
                $user['nombre'] ?? 'Sistema'
            ]);
            $ficha_id = $pdoSiglech->lastInsertId();
            $mensaje = 'Ficha creada exitosamente';
        } else {
            // Actualizar ficha existente
            $stUpdate = $pdoSiglech->prepare("
                UPDATE ficha_gestion_egreso SET
                    estado_egreso = ?,
                    causal_egreso = ?,
                    codigo_egreso = ?,
                    observaciones = ?,
                    f_egreso = ?,
                    actualizado_por = ?,
                    actualizado_en = NOW()
                WHERE id = ?
            ");
            $stUpdate->execute([
                $_POST['estado_egreso'] ?? 'ACTIVO',
                $_POST['causal_egreso'] ?? '',
                $_POST['codigo_egreso'] ?? '',
                $_POST['observaciones'] ?? '',
                !empty($_POST['f_egreso']) ? $_POST['f_egreso'] : null,
                $user['nombre'] ?? 'Sistema',
                $ficha['id']
            ]);
            $mensaje = 'Ficha actualizada exitosamente';
        }

        // Recargar ficha actualizada
        $stFicha = $pdoSiglech->prepare("
            SELECT * FROM ficha_gestion_egreso
            WHERE tipo_demanda = ? AND sigte_id = ? AND run_paciente = ?
            LIMIT 1
        ");
        $stFicha->execute([$tipo_demanda, $sigte_id, $paciente['RUN']]);
        $ficha = $stFicha->fetch();

    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
    }
}

// Obtener bitácora de gestiones
$stGestiones = $pdoSiglech->prepare("
    SELECT * FROM bitacora_gestiones_siglech
    WHERE sigte_id = ? AND run_paciente = ?
    ORDER BY creado_en DESC
    LIMIT 50
");
$stGestiones->execute([$sigte_id, $paciente['RUN']]);
$gestiones = $stGestiones->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Gestión Egreso - <?= htmlspecialchars($sigte_id) ?></title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-slate-50 dark:bg-slate-950">
<div class="max-w-6xl mx-auto p-4 sm:p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">📋 Ficha de Gestión Egreso</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-2">Folio SIGTE: <code class="bg-slate-200 dark:bg-slate-800 px-2 py-1 rounded"><?= htmlspecialchars($sigte_id) ?></code></p>
    </div>

    <?php if ($mensaje): ?>
    <div class="mb-4 p-4 rounded-lg <?= strpos($mensaje, 'Error') === false ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Datos Paciente -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-6">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">Datos del Paciente</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-slate-500">Nombre</p>
                    <p class="font-semibold"><?= htmlspecialchars(trim(($paciente['PRIMER_APELLIDO'] ?? '') . ' ' . ($paciente['SEGUNDO_APELLIDO'] ?? '') . ' ' . ($paciente['NOMBRES'] ?? ''))) ?></p>
                </div>
                <div>
                    <p class="text-slate-500">RUN</p>
                    <p class="font-semibold"><?= htmlspecialchars($paciente['RUN'] . '-' . $paciente['DV']) ?></p>
                </div>
                <div>
                    <p class="text-slate-500">Especialidad</p>
                    <p class="font-semibold"><?= htmlspecialchars($paciente['ESPECIALIDAD_ESTANDAR'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-slate-500">Días en Espera</p>
                    <p class="font-semibold"><?= $paciente['DIAS_ESPERA'] ?? 0 ?> días</p>
                </div>
            </div>
        </div>

        <!-- Resumen Ficha -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-6">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">Estado Ficha</h2>
            <?php if ($ficha): ?>
                <div class="space-y-2 text-sm">
                    <div>
                        <p class="text-slate-500">Estado</p>
                        <p class="font-semibold text-lg"><?= htmlspecialchars($ficha['estado_egreso']) ?></p>
                    </div>
                    <div>
                        <p class="text-slate-500">Total Contactos</p>
                        <p class="font-semibold text-2xl text-blue-600"><?= $ficha['total_contactos'] ?></p>
                    </div>
                    <div>
                        <p class="text-slate-500">Último Contacto</p>
                        <p class="font-semibold"><?= $ficha['ultimo_contacto'] ? date('d/m/Y H:i', strtotime($ficha['ultimo_contacto'])) : 'Sin contactos' ?></p>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-slate-500 text-center py-4">Sin ficha creada</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulario -->
    <form method="POST" class="mt-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">Información de Egreso</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Estado</label>
                <select name="estado_egreso" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
                    <option value="ACTIVO" <?= ($ficha['estado_egreso'] ?? '') === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                    <option value="PENDIENTE" <?= ($ficha['estado_egreso'] ?? '') === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="EGRESADO" <?= ($ficha['estado_egreso'] ?? '') === 'EGRESADO' ? 'selected' : '' ?>>Egresado</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha Egreso</label>
                <input type="date" name="f_egreso" value="<?= ($ficha['f_egreso'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Causal de Egreso</label>
                <input type="text" name="causal_egreso" value="<?= htmlspecialchars($ficha['causal_egreso'] ?? '') ?>" placeholder="Ej: Atendido, Rechazo, etc." class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Código Egreso</label>
                <input type="text" name="codigo_egreso" value="<?= htmlspecialchars($ficha['codigo_egreso'] ?? '') ?>" placeholder="Ej: 1, 0, X" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Observaciones</label>
            <textarea name="observaciones" rows="4" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800"><?= htmlspecialchars($ficha['observaciones'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg">
            <?= $ficha ? 'Actualizar Ficha' : 'Crear Ficha' ?>
        </button>
    </form>

    <!-- Bitácora de Gestiones -->
    <div class="mt-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">📞 Bitácora de Gestiones</h2>

        <?php if ($gestiones): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-2 text-left">Fecha</th>
                            <th class="px-4 py-2 text-left">Tipo</th>
                            <th class="px-4 py-2 text-left">Resultado</th>
                            <th class="px-4 py-2 text-left">Profesional</th>
                            <th class="px-4 py-2 text-left">Observación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <?php foreach ($gestiones as $g): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-2 whitespace-nowrap"><?= date('d/m H:i', strtotime($g['creado_en'])) ?></td>
                            <td class="px-4 py-2"><span class="inline-block px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs font-semibold"><?= htmlspecialchars($g['tipo_gestion']) ?></span></td>
                            <td class="px-4 py-2"><span class="inline-block px-2 py-1 rounded text-xs font-semibold <?= $g['resultado'] === 'CONTACTADO' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= htmlspecialchars($g['resultado']) ?></span></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($g['profesional_nombre'] ?? '-') ?></td>
                            <td class="px-4 py-2 max-w-xs truncate"><?= htmlspecialchars($g['observacion'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-slate-500 text-center py-8">Sin gestiones registradas</p>
        <?php endif; ?>
    </div>

    <!-- Agregar Gestión -->
    <div class="mt-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">➕ Registrar Nueva Gestión</h2>
        <form action="registrar_gestion.php" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <input type="hidden" name="sigte" value="<?= htmlspecialchars($sigte_id) ?>">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_demanda) ?>">

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tipo Gestión</label>
                <select name="tipo_gestion" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
                    <option>LLAMADA</option>
                    <option>SMS</option>
                    <option>WHATSAPP</option>
                    <option>EMAIL</option>
                    <option>PRESENCIAL</option>
                    <option>OTRO</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Resultado</label>
                <select name="resultado" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
                    <option>CONTACTADO</option>
                    <option>NO_CONTESTA</option>
                    <option>NUMERO_INVALIDO</option>
                    <option>RECHAZO</option>
                    <option>NO_DISPONIBLE</option>
                    <option>OTRO</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Número/Dato</label>
                <input type="text" name="numero_usado" placeholder="Teléfono, email, etc." class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Observación</label>
                <textarea name="observacion" rows="2" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800"></textarea>
            </div>

            <button type="submit" class="sm:col-span-2 lg:col-span-3 px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg">
                Registrar Gestión
            </button>
        </form>
    </div>
</div>
</body>
</html>
