<?php
/**
 * Módulo Pacientes - SIGLECH
 * Búsqueda de pacientes desde demanda de listas de espera
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../lib/SICOCHClient.php';
require_once __DIR__ . '/../../partials/layout.php';

$user = requiereLogin();
$pdo = getConexion();
$pdo_siglech = getConexionSiglech();
$sicoch = new SICOCHClient(SICOCH_API_BASE_URL, SICOCH_API_KEY);

// Parámetros de búsqueda
$run = trim($_GET['run'] ?? '');
$nombre = trim($_GET['nombre'] ?? '');
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 50;

$pacientes = [];
$total = 0;
$hay_busqueda = !empty($run) || !empty($nombre);

// Realizar búsqueda si hay parámetros
if ($hay_busqueda) {
    try {
        // Construir consulta UNION de las 3 tablas de demanda
        $sql = "
            SELECT RUN, NOMBRES, FECHA_NAC, 'CNE' as tipo, ESPECIALIDAD_ESTANDAR as especialidad, 1 as casos
            FROM demanda_cne
            WHERE 1=1
        ";

        $params = [];

        if (!empty($run)) {
            $sql .= " AND RUN LIKE ?";
            $params[] = '%' . str_replace(['-', '.'], '', $run) . '%';
        }

        if (!empty($nombre)) {
            $sql .= " AND NOMBRES LIKE ?";
            $params[] = '%' . $nombre . '%';
        }

        $sql .= "
            UNION ALL
            SELECT RUN, NOMBRES, FECHA_NAC, 'IQ' as tipo, ESPECIALIDAD_ESTANDAR as especialidad, 1 as casos
            FROM demanda_iq
            WHERE 1=1
        ";

        if (!empty($run)) {
            $sql .= " AND RUN LIKE ?";
            $params[] = '%' . str_replace(['-', '.'], '', $run) . '%';
        }

        if (!empty($nombre)) {
            $sql .= " AND NOMBRES LIKE ?";
            $params[] = '%' . $nombre . '%';
        }

        $sql .= "
            UNION ALL
            SELECT RUN, NOMBRES, FECHA_NAC, 'PROC' as tipo, ESPECIALIDAD_ESTANDAR as especialidad, 1 as casos
            FROM demanda_proc
            WHERE 1=1
        ";

        if (!empty($run)) {
            $sql .= " AND RUN LIKE ?";
            $params[] = '%' . str_replace(['-', '.'], '', $run) . '%';
        }

        if (!empty($nombre)) {
            $sql .= " AND NOMBRES LIKE ?";
            $params[] = '%' . $nombre . '%';
        }

        // Contar total
        $sql_count = "SELECT COUNT(*) as total FROM ($sql) as resultado";
        $stmt_count = $pdo_siglech->prepare($sql_count);
        $stmt_count->execute($params);
        $result_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
        $total = $result_count['total'] ?? 0;

        // Obtener datos paginados
        $offset = ($pagina - 1) * $por_pagina;
        $sql_paginado = $sql . " ORDER BY NOMBRES ASC LIMIT ? OFFSET ?";
        $params[] = $por_pagina;
        $params[] = $offset;

        $stmt = $pdo_siglech->prepare($sql_paginado);
        $stmt->execute($params);
        $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Error en búsqueda de pacientes: " . $e->getMessage());
    }
}

layoutHeader('Gestión de Pacientes', $user, 'pacientes');
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">👥 Gestión de Pacientes</h2>
    <p class="text-slate-600 dark:text-slate-400">Perfiles de pacientes sincronizados desde SICOCH</p>
</div>

<!-- Búsqueda de Pacientes -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 mb-8">
    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">🔍 Buscar Paciente</h3>

    <form method="GET" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    RUN
                </label>
                <input
                    type="text"
                    name="run"
                    placeholder="12345678-9"
                    class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 outline-none"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    Nombre
                </label>
                <input
                    type="text"
                    name="nombre"
                    placeholder="Buscar por nombre"
                    class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 outline-none"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    &nbsp;
                </label>
                <button
                    type="submit"
                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-semibold"
                >
                    🔍 Buscar
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Listado de Pacientes -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 mb-8">
    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">
        📋 Listado de Pacientes
        <?php if ($hay_busqueda && $total > 0): ?>
            <span class="text-lg text-slate-500 dark:text-slate-400 font-normal">
                (<?= number_format($total) ?> resultado<?= $total !== 1 ? 's' : '' ?>)
            </span>
        <?php endif; ?>
    </h3>

    <?php if ($hay_busqueda && $total === 0): ?>
        <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200 px-4 py-3 rounded-lg mb-4">
            ℹ️ No se encontraron pacientes con los criterios de búsqueda
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">RUN</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Nombre</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Fecha Nac.</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Tipo</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Especialidad</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pacientes) > 0):
                    foreach ($pacientes as $paciente):
                ?>
                    <tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        <td class="py-3 px-4 font-mono text-blue-600 dark:text-blue-400">
                            <?= htmlspecialchars($paciente['RUN']) ?>
                        </td>
                        <td class="py-3 px-4 font-medium text-slate-900 dark:text-white">
                            <?= htmlspecialchars($paciente['NOMBRES']) ?>
                        </td>
                        <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
                            <?= !empty($paciente['FECHA_NAC']) ? htmlspecialchars($paciente['FECHA_NAC']) : 'N/A' ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-block px-3 py-1 bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded text-xs font-semibold">
                                <?= htmlspecialchars($paciente['tipo']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-600 dark:text-slate-400 text-xs">
                            <?= htmlspecialchars($paciente['especialidad'] ?? 'N/A') ?>
                        </td>
                    </tr>
                <?php
                    endforeach;
                else:
                ?>
                    <tr class="border-b border-slate-200 dark:border-slate-700">
                        <td colspan="5" class="text-center py-8 text-slate-500 dark:text-slate-400">
                            <?= $hay_busqueda ? '❌ No se encontraron resultados' : '🔍 Ingresa un RUN o nombre para buscar pacientes' ?>
                        </td>
                    </tr>
                <?php
                endif;
                ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($hay_busqueda && $total > $por_pagina):
        $total_paginas = ceil($total / $por_pagina);
    ?>
        <div class="mt-6 flex items-center justify-between">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Mostrando <?= (($pagina - 1) * $por_pagina) + 1 ?> - <?= min($pagina * $por_pagina, $total) ?> de <?= number_format($total) ?>
            </p>
            <div class="flex gap-2">
                <?php if ($pagina > 1): ?>
                    <a href="?run=<?= urlencode($run) ?>&nombre=<?= urlencode($nombre) ?>&pagina=<?= $pagina - 1 ?>"
                       class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white rounded-lg transition hover:bg-slate-300 dark:hover:bg-slate-600">
                        ← Anterior
                    </a>
                <?php endif; ?>

                <span class="px-4 py-2 text-slate-600 dark:text-slate-400">
                    Página <?= $pagina ?> de <?= $total_paginas ?>
                </span>

                <?php if ($pagina < $total_paginas): ?>
                    <a href="?run=<?= urlencode($run) ?>&nombre=<?= urlencode($nombre) ?>&pagina=<?= $pagina + 1 ?>"
                       class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white rounded-lg transition hover:bg-slate-300 dark:hover:bg-slate-600">
                        Siguiente →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php layoutFooter(); ?>
