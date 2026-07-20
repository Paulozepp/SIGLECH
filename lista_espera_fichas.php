<?php
/**
 * Lista de Espera - Fichas de Gestión Egreso
 * Acceso rápido a fichas de gestión para pacientes vigentes en lista de espera
 */

require_once __DIR__ . '/../SICOCH/db.php';
require_once __DIR__ . '/../SICOCH/auth/guard.php';

$user = requiereLogin();
$pdoSiglech = getConexionSiglech();

// Filtros
$q = trim($_GET['q'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');
$especialidad = trim($_GET['especialidad'] ?? '');

$where = ['ESTADO = "VIGENTE"', 'SIGTE_ID IS NOT NULL', 'SIGTE_ID != ""'];
$params = [];

if ($q !== '') {
    $qNorm = preg_replace('/[^0-9K]/', '', strtoupper($q));
    $where[] = '(SIGTE_ID LIKE ? OR RUN LIKE ? OR NOMBRES LIKE ?)';
    array_push($params, "%$q%", "%$qNorm%", "%$q%");
}

if ($tipo) {
    // tipo es 'CNE', 'IQ', o 'PROC'
}

if ($especialidad) {
    $where[] = 'ESPECIALIDAD_ESTANDAR = ?';
    $params[] = $especialidad;
}

$wsql = implode(' AND ', $where);

// Obtener datos UNION de las 3 tablas
$union = "
    SELECT 'CNE' as TIPO, " . implode(', ', [
        'id', 'SIGTE_ID', 'RUN', 'DV', 'NOMBRES', 'PRIMER_APELLIDO', 'SEGUNDO_APELLIDO',
        'ESPECIALIDAD_ESTANDAR', 'F_ENTRADA', 'DIAS_ESPERA', 'ESTADO', 'CIUDAD'
    ]) . "
    FROM demanda_cne WHERE $wsql
    UNION ALL
    SELECT 'IQ' as TIPO, " . implode(', ', [
        'id', 'SIGTE_ID', 'RUN', 'DV', 'NOMBRES', 'PRIMER_APELLIDO', 'SEGUNDO_APELLIDO',
        'ESPECIALIDAD_ESTANDAR', 'F_ENTRADA', 'DIAS_ESPERA', 'ESTADO', 'CIUDAD'
    ]) . "
    FROM demanda_iq WHERE $wsql
    UNION ALL
    SELECT 'PROC' as TIPO, " . implode(', ', [
        'id', 'SIGTE_ID', 'RUN', 'DV', 'NOMBRES', 'PRIMER_APELLIDO', 'SEGUNDO_APELLIDO',
        'ESPECIALIDAD_ESTANDAR', 'F_ENTRADA', 'DIAS_ESPERA', 'ESTADO', 'CIUDAD'
    ]) . "
    FROM demanda_proc WHERE $wsql
    ORDER BY DIAS_ESPERA DESC
    LIMIT 500
";

$st = $pdoSiglech->prepare($union);
$st->execute($params);
$pacientes = $st->fetchAll();

// Obtener especialidades
$especialidadesDisponibles = $pdoSiglech->query("
    SELECT DISTINCT ESPECIALIDAD_ESTANDAR FROM (
        SELECT ESPECIALIDAD_ESTANDAR FROM demanda_cne WHERE ESPECIALIDAD_ESTANDAR IS NOT NULL
        UNION SELECT ESPECIALIDAD_ESTANDAR FROM demanda_iq WHERE ESPECIALIDAD_ESTANDAR IS NOT NULL
        UNION SELECT ESPECIALIDAD_ESTANDAR FROM demanda_proc WHERE ESPECIALIDAD_ESTANDAR IS NOT NULL
    ) e
    ORDER BY ESPECIALIDAD_ESTANDAR
")->fetchAll(PDO::FETCH_COLUMN);

// Obtener fichas existentes
$sigteIds = array_column($pacientes, 'SIGTE_ID');
$fichasMap = [];
if ($sigteIds) {
    $placeholders = implode(',', array_fill(0, count($sigteIds), '?'));
    $stFichas = $pdoSiglech->prepare("
        SELECT sigte_id, id, estado_egreso, total_contactos, ultimo_contacto
        FROM ficha_gestion_egreso
        WHERE sigte_id IN ($placeholders)
    ");
    $stFichas->execute($sigteIds);
    foreach ($stFichas->fetchAll() as $f) {
        $fichasMap[$f['sigte_id']] = $f;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Espera - Fichas Gestión Egreso</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-slate-50 dark:bg-slate-950">
<div class="max-w-7xl mx-auto p-4 sm:p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">📋 Fichas Gestión Egreso</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-2">Gestión de trazabilidad de contactos con pacientes en lista de espera</p>
    </div>

    <!-- Filtros -->
    <form method="get" class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-4 sm:p-5 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Buscar (SIGTE / RUN / nombre)</label>
                <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ej: 202400001, 12345678"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Especialidad</label>
                <select name="especialidad" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm">
                    <option value="">Todas</option>
                    <?php foreach ($especialidadesDisponibles as $esp): ?>
                    <option value="<?= htmlspecialchars($esp) ?>" <?= $especialidad === $esp ? 'selected' : '' ?>>
                        <?= htmlspecialchars($esp) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <button class="w-full rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold py-1.5">
                    Filtrar
                </button>
            </div>
        </div>
    </form>

    <!-- Tabla -->
    <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">SIGTE</th>
                        <th class="px-4 py-3 text-left font-semibold">Paciente</th>
                        <th class="px-4 py-3 text-left font-semibold">Especialidad</th>
                        <th class="px-4 py-3 text-left font-semibold">Días</th>
                        <th class="px-4 py-3 text-center font-semibold">Ficha</th>
                        <th class="px-4 py-3 text-center font-semibold">Contactos</th>
                        <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <?php foreach ($pacientes as $p):
                        $ficha = $fichasMap[$p['SIGTE_ID']] ?? null;
                        $dias = (int)($p['DIAS_ESPERA'] ?? 0);
                        $diasClass = $dias > 180 ? 'text-red-600 dark:text-red-400 font-semibold' : ($dias > 90 ? 'text-amber-600 dark:text-amber-400 font-semibold' : '');
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 font-mono font-semibold"><?= htmlspecialchars($p['SIGTE_ID']) ?></td>
                        <td class="px-4 py-3">
                            <div class="font-semibold"><?= htmlspecialchars(trim($p['PRIMER_APELLIDO'] . ' ' . $p['SEGUNDO_APELLIDO'])) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($p['RUN'] . '-' . $p['DV']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= htmlspecialchars($p['ESPECIALIDAD_ESTANDAR'] ?? '-') ?></td>
                        <td class="px-4 py-3 <?= $diasClass ?>"><?= $dias ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($ficha): ?>
                                <span class="inline-block px-2 py-1 rounded text-xs font-semibold
                                    <?php if ($ficha['estado_egreso'] === 'EGRESADO'): ?>
                                        bg-green-100 text-green-700
                                    <?php elseif ($ficha['estado_egreso'] === 'PENDIENTE'): ?>
                                        bg-amber-100 text-amber-700
                                    <?php else: ?>
                                        bg-blue-100 text-blue-700
                                    <?php endif; ?>">
                                    <?= htmlspecialchars($ficha['estado_egreso']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold">
                            <?php if ($ficha): ?>
                                <span class="text-blue-600 dark:text-blue-400"><?= $ficha['total_contactos'] ?></span>
                            <?php else: ?>
                                <span class="text-slate-400">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="ficha_gestion_egreso.php?sigte=<?= urlencode($p['SIGTE_ID']) ?>&tipo=<?= urlencode($p['TIPO']) ?>"
                               class="inline-block px-3 py-1.5 rounded bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-xs font-semibold transition-colors">
                                📋 Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$pacientes): ?>
                    <tr>
                        <td colspan="7" class="text-center text-slate-400 py-10">
                            Sin pacientes vigentes encontrados
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900 rounded-lg">
        <p class="text-sm text-blue-700 dark:text-blue-300">
            <strong>ℹ️ Información:</strong> Total de pacientes vigentes: <strong><?= count($pacientes) ?></strong> |
            Con ficha: <strong><?= count(array_filter($fichasMap)) ?></strong> |
            Sin ficha: <strong><?= count($pacientes) - count(array_filter($fichasMap)) ?></strong>
        </p>
    </div>
</div>
</body>
</html>
