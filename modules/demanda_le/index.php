<?php
/**
 * Módulo Demanda LE (CNE / IQ / PROC) - SIGLECH
 * Visualización de las 3 listas de demanda importadas (Consulta Nueva
 * Especialidad, Intervención Quirúrgica, Procedimientos).
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../partials/layout.php';
require_once __DIR__ . '/_config.php';

$user = requiereLogin();
$pdo  = getConexionSiglech();

$tipo = strtolower((string)($_GET['tipo'] ?? 'cne'));
$info = demandaLeTabla($tipo);
if ($info === null) {
    $tipo = 'cne';
    $info = demandaLeTabla($tipo);
}
$tabla = $info['tabla'];

// --- Filtros ---
$buscar      = trim((string)($_GET['buscar'] ?? ''));
$estado      = trim((string)($_GET['estado'] ?? ''));
$especialidad = trim((string)($_GET['especialidad'] ?? ''));
$sigteId     = trim((string)($_GET['sigte_id'] ?? ''));
$pagina      = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina   = REGISTROS_POR_PAGINA;
$offset      = ($pagina - 1) * $porPagina;

$where  = [];
$params = [];

if ($buscar !== '') {
    $where[] = '(RUN LIKE :buscar OR NOMBRES LIKE :buscar OR PRIMER_APELLIDO LIKE :buscar OR SEGUNDO_APELLIDO LIKE :buscar)';
    $params['buscar'] = '%' . $buscar . '%';
}
if ($estado !== '') {
    $where[] = 'ESTADO = :estado';
    $params['estado'] = $estado;
}
if ($especialidad !== '') {
    $where[] = 'ESPECIALIDAD_ESTANDAR = :especialidad';
    $params['especialidad'] = $especialidad;
}
if ($sigteId !== '') {
    $where[] = 'SIGTE_ID LIKE :sigte_id';
    $params['sigte_id'] = '%' . $sigteId . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- Totales (sin filtros, panel general) ---
$totales = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(ESTADO = 'EGRESADO') AS egresados,
        SUM(ESTADO = 'VIGENTE') AS vigentes,
        ROUND(AVG(CASE WHEN ESTADO = 'VIGENTE' THEN DIAS_ESPERA END)) AS promedio_espera_vigentes
    FROM {$tabla}
")->fetch();

$topEspecialidades = $pdo->query("
    SELECT ESPECIALIDAD_ESTANDAR, COUNT(*) AS cantidad
    FROM {$tabla}
    WHERE ESPECIALIDAD_ESTANDAR IS NOT NULL AND ESPECIALIDAD_ESTANDAR <> ''
    GROUP BY ESPECIALIDAD_ESTANDAR
    ORDER BY cantidad DESC
    LIMIT 5
")->fetchAll();

$especialidadesDisponibles = $pdo->query("
    SELECT DISTINCT ESPECIALIDAD_ESTANDAR
    FROM {$tabla}
    WHERE ESPECIALIDAD_ESTANDAR IS NOT NULL AND ESPECIALIDAD_ESTANDAR <> ''
    ORDER BY ESPECIALIDAD_ESTANDAR
")->fetchAll(PDO::FETCH_COLUMN);

// --- Resultados filtrados + paginación ---
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM {$tabla} {$whereSql}");
$stmtTotal->execute($params);
$totalFiltrado = (int)$stmtTotal->fetchColumn();
$totalPaginas  = max(1, (int)ceil($totalFiltrado / $porPagina));

$sql = "
    SELECT id, RUN, DV, NOMBRES, PRIMER_APELLIDO, SEGUNDO_APELLIDO,
           ESPECIALIDAD_ESTANDAR, ESTADO, NOMBRE_ORIG, NOMBRE_DEST,
           F_ENTRADA, DIAS_ESPERA, SIGTE_ID
    FROM {$tabla}
    {$whereSql}
    ORDER BY F_ENTRADA DESC
    LIMIT {$porPagina} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll();

function demandaLeTabUrl(string $tipo, array $extra = []): string {
    $params = array_merge(['tipo' => $tipo], $extra);
    return '/SIGLECH/index.php?' . http_build_query($params);
}

layoutHeader('Demanda LE - ' . $info['label'], $user, 'demanda_le');
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">📥 Demanda Listas de Espera</h2>
    <p class="text-slate-600 dark:text-slate-400">Datos importados desde CNE, IQ y PROC (MINSAL)</p>
</div>

<!-- Tabs -->
<div class="flex gap-2 mb-6 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
    <?php foreach (DEMANDA_LE_TABLAS as $codigo => $t): ?>
        <a href="<?= htmlspecialchars(demandaLeTabUrl($codigo)) ?>"
           class="px-4 py-3 text-sm font-medium whitespace-nowrap <?= $tipo === $codigo ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-600 dark:text-slate-400 hover:text-brand-600' ?>">
            <?= $t['icono'] ?> <?= htmlspecialchars($t['label']) ?>
        </a>
    <?php endforeach; ?>
    <a href="cargar.php" class="px-4 py-3 text-sm font-medium whitespace-nowrap text-slate-600 dark:text-slate-400 hover:text-brand-600">
        📤 Cargar CSV
    </a>
</div>

<p class="text-sm text-slate-500 dark:text-slate-400 mb-6"><?= htmlspecialchars($info['nombre']) ?> — tabla <code class="bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded"><?= htmlspecialchars($tabla) ?></code></p>

<!-- Paneles de totales -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Total registros</p>
        <p class="text-3xl font-bold text-slate-900 dark:text-white"><?= number_format((int)$totales['total'], 0, ',', '.') ?></p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Vigentes (en espera)</p>
        <p class="text-3xl font-bold text-amber-600 dark:text-amber-400"><?= number_format((int)$totales['vigentes'], 0, ',', '.') ?></p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Egresados</p>
        <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?= number_format((int)$totales['egresados'], 0, ',', '.') ?></p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Días de espera prom. (vigentes)</p>
        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?= $totales['promedio_espera_vigentes'] !== null ? number_format((float)$totales['promedio_espera_vigentes'], 0) : '—' ?></p>
    </div>
</div>

<!-- Top especialidades -->
<?php if ($topEspecialidades): ?>
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 mb-8">
    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Top 5 Especialidades</h3>
    <div class="space-y-2">
        <?php
        $maxCant = (int)$topEspecialidades[0]['cantidad'];
        foreach ($topEspecialidades as $esp):
            $pct = $maxCant > 0 ? round(($esp['cantidad'] / $maxCant) * 100) : 0;
        ?>
        <div>
            <div class="flex justify-between text-sm mb-1">
                <span class="text-slate-700 dark:text-slate-300"><?= htmlspecialchars($esp['ESPECIALIDAD_ESTANDAR']) ?></span>
                <span class="text-slate-500 dark:text-slate-400"><?= number_format((int)$esp['cantidad'], 0, ',', '.') ?></span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2">
                <div class="bg-brand-600 h-2 rounded-full" style="width: <?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
        <div>
            <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Buscar (RUN o nombre)</label>
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="12345678-9 o Nombre"
                   class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
        </div>
        <div>
            <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Folio SIGTE</label>
            <input type="text" name="sigte_id" value="<?= htmlspecialchars($sigteId) ?>" placeholder="ID SIGTE"
                   class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
        </div>
        <div>
            <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Estado</label>
            <select name="estado" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                <option value="">Todos</option>
                <option value="VIGENTE" <?= $estado === 'VIGENTE' ? 'selected' : '' ?>>Vigente</option>
                <option value="EGRESADO" <?= $estado === 'EGRESADO' ? 'selected' : '' ?>>Egresado</option>
            </select>
        </div>
        <div>
            <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Especialidad</label>
            <select name="especialidad" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                <option value="">Todas</option>
                <?php foreach ($especialidadesDisponibles as $esp): ?>
                    <option value="<?= htmlspecialchars($esp) ?>" <?= $especialidad === $esp ? 'selected' : '' ?>><?= htmlspecialchars($esp) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-4 flex gap-2">
            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm transition">Filtrar</button>
            <a href="<?= htmlspecialchars(demandaLeTabUrl($tipo)) ?>" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm transition">Limpiar</a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Resultados</h3>
        <span class="text-sm text-slate-500 dark:text-slate-400"><?= number_format($totalFiltrado, 0, ',', '.') ?> registros</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/50 text-left text-slate-600 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">RUN</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Especialidad</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Origen</th>
                    <th class="px-4 py-3">Destino</th>
                    <th class="px-4 py-3">F. Entrada</th>
                    <th class="px-4 py-3 text-right">Días espera</th>
                    <th class="px-4 py-3">Folio SIGTE</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <?php if (!$filas): ?>
                    <tr><td colspan="10" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Sin registros para los filtros seleccionados.</td></tr>
                <?php endif; ?>
                <?php foreach ($filas as $fila): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars(($fila['RUN'] ?? '') . '-' . ($fila['DV'] ?? '')) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars(trim(($fila['NOMBRES'] ?? '') . ' ' . ($fila['PRIMER_APELLIDO'] ?? '') . ' ' . ($fila['SEGUNDO_APELLIDO'] ?? ''))) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($fila['ESPECIALIDAD_ESTANDAR'] ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <?php if ($fila['ESTADO'] === 'VIGENTE'): ?>
                            <span class="px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Vigente</span>
                        <?php elseif ($fila['ESTADO'] === 'EGRESADO'): ?>
                            <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Egresado</span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300"><?= htmlspecialchars($fila['ESTADO'] ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3"><?= htmlspecialchars($fila['NOMBRE_ORIG'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($fila['NOMBRE_DEST'] ?? '—') ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($fila['F_ENTRADA'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right"><?= $fila['DIAS_ESPERA'] !== null ? number_format((int)$fila['DIAS_ESPERA']) : '—' ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($fila['SIGTE_ID'] ?? '—') ?></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="<?= htmlspecialchars('detalle.php?tipo=' . $tipo . '&id=' . (int)$fila['id']) ?>" class="text-brand-600 hover:underline">Ver ficha →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <span class="text-sm text-slate-500 dark:text-slate-400">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
        <div class="flex gap-2">
            <?php if ($pagina > 1): ?>
                <a href="<?= htmlspecialchars(demandaLeTabUrl($tipo, ['buscar' => $buscar, 'estado' => $estado, 'especialidad' => $especialidad, 'sigte_id' => $sigteId, 'pagina' => $pagina - 1])) ?>" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm">← Anterior</a>
            <?php endif; ?>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="<?= htmlspecialchars(demandaLeTabUrl($tipo, ['buscar' => $buscar, 'estado' => $estado, 'especialidad' => $especialidad, 'sigte_id' => $sigteId, 'pagina' => $pagina + 1])) ?>" class="px-3 py-1.5 bg-brand-600 text-white rounded-lg text-sm">Siguiente →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php layoutFooter(); ?>

