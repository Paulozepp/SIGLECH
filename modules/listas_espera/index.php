<?php
/**
 * Módulo Listas de Espera SIGLECH - Rediseño CNE / IQ / PROC
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../partials/layout.php';

$user = requiereLogin();

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=siglech;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Error conectando a SIGLECH: ' . $e->getMessage());
}

$tipos = [
    'CNE' => ['tabla' => 'demanda_cne', 'nombre' => 'Consulta Nueva Especialidad', 'icono' => '🏥', 'color' => 'blue'],
    'IQ' => ['tabla' => 'demanda_iq', 'nombre' => 'Intervención Quirúrgica', 'icono' => '🔪', 'color' => 'red'],
    'PROC' => ['tabla' => 'demanda_proc', 'nombre' => 'Procedimientos', 'icono' => '⚕️', 'color' => 'amber'],
];

$estadisticas = [];

foreach ($tipos as $tipo => $info) {
    $tabla = $info['tabla'];

    // Total de registros
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM $tabla");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Vigentes (ESTADO = 'VIGENTE' y F_SALIDA IS NULL)
    $stmt = $pdo->query("SELECT COUNT(*) as vigentes FROM $tabla WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL");
    $vigentes = $stmt->fetch(PDO::FETCH_ASSOC)['vigentes'];

    // Dias de espera: promedio y máximo
    $stmt = $pdo->query("SELECT AVG(DIAS_ESPERA) as promedio, MAX(DIAS_ESPERA) as maximo FROM $tabla WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL");
    $dias = $stmt->fetch(PDO::FETCH_ASSOC);
    $dias_promedio = round($dias['promedio'] ?? 0);
    $dias_maximo = $dias['maximo'] ?? 0;

    // Por establecimiento destino (top 5)
    $stmt = $pdo->query("
        SELECT de.nombre as establecimiento, COUNT(*) as cantidad
        FROM $tabla le
        LEFT JOIN dim_establecimiento de ON le.ESTAB_DEST = de.id
        WHERE le.ESTADO = 'VIGENTE' AND le.F_SALIDA IS NULL
        GROUP BY le.ESTAB_DEST
        ORDER BY cantidad DESC
        LIMIT 5
    ");
    $establecimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prioridades (si existen)
    $stmt = $pdo->query("
        SELECT
            CASE
                WHEN DIAS_ESPERA >= 180 THEN 'CRÍTICA (>180 días)'
                WHEN DIAS_ESPERA >= 90 THEN 'ALTA (90-180 días)'
                WHEN DIAS_ESPERA >= 30 THEN 'MEDIA (30-90 días)'
                ELSE 'BAJA (<30 días)'
            END as prioridad,
            COUNT(*) as cantidad
        FROM $tabla
        WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL
        GROUP BY prioridad
        ORDER BY cantidad DESC
    ");
    $prioridades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimos registros
    $stmt = $pdo->query("
        SELECT
            SIGTE_ID as folio,
            CONCAT(PRIMER_APELLIDO, ', ', NOMBRES) as paciente,
            RUN,
            F_ENTRADA as fecha_ingreso,
            DIAS_ESPERA as dias_espera,
            PRESTA_EST as prestacion
        FROM $tabla
        WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL
        ORDER BY F_ENTRADA DESC
        LIMIT 10
    ");
    $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $estadisticas[$tipo] = [
        'total' => $total,
        'vigentes' => $vigentes,
        'dias_promedio' => $dias_promedio,
        'dias_maximo' => $dias_maximo,
        'establecimientos' => $establecimientos,
        'prioridades' => $prioridades,
        'ultimos' => $ultimos
    ];
}

layoutHeader('Listas de Espera SIGLECH', $user, 'listas');
?>

<div class="mb-8">
    <h2 class="text-4xl font-bold text-slate-900 dark:text-white mb-2">📋 Listas de Espera SIGLECH</h2>
    <p class="text-slate-600 dark:text-slate-400">Análisis integral de CNE, IQ y PROC - Demanda Vigente</p>
</div>

<!-- Tarjetas principales de los 3 tipos -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <?php foreach ($tipos as $tipo => $info):
        $stats = $estadisticas[$tipo];
        $colorMap = [
            'blue' => 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800',
            'red' => 'bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-800',
            'amber' => 'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800'
        ];
        $textMap = [
            'blue' => 'text-blue-600 dark:text-blue-400',
            'red' => 'text-red-600 dark:text-red-400',
            'amber' => 'text-amber-600 dark:text-amber-400'
        ];
    ?>
    <div class="<?= $colorMap[$info['color']] ?> rounded-xl shadow-md p-6 border-2">
        <div class="flex items-start justify-between mb-4">
            <div>
                <p class="text-4xl mb-2"><?= $info['icono'] ?></p>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white"><?= $tipo ?></h3>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1"><?= $info['nombre'] ?></p>
            </div>
        </div>

        <div class="space-y-3">
            <div>
                <p class="text-xs text-slate-600 dark:text-slate-400">VIGENTES</p>
                <p class="text-3xl font-bold <?= $textMap[$info['color']] ?>"><?= number_format($stats['vigentes']) ?></p>
                <p class="text-xs text-slate-500 dark:text-slate-500 mt-1">de <?= number_format($stats['total']) ?> totales</p>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-300 dark:border-slate-600">
                <div>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Promedio espera</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white"><?= $stats['dias_promedio'] ?> días</p>
                </div>
                <div>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Máximo</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white"><?= $stats['dias_maximo'] ?> días</p>
                </div>
            </div>
        </div>

        <a href="#<?= strtolower($tipo) ?>" class="mt-4 inline-block px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm transition">
            Ver detalle →
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Secciones detalladas por tipo -->
<?php foreach ($tipos as $tipo => $info):
    $stats = $estadisticas[$tipo];
    $colorMap = [
        'blue' => 'blue',
        'red' => 'red',
        'amber' => 'amber'
    ];
    $color = $colorMap[$info['color']];
?>
<div id="<?= strtolower($tipo) ?>" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 mb-8 scroll-mt-20">
    <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-3">
        <span class="text-3xl"><?= $info['icono'] ?></span>
        <?= $tipo ?> - <?= $info['nombre'] ?>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Prioridades / Tiempo de espera -->
        <div class="col-span-1">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Urgencia por tiempo de espera</h3>
            <div class="space-y-3">
                <?php foreach ($stats['prioridades'] as $p): ?>
                <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-700 rounded-lg">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= $p['prioridad'] ?></span>
                    <span class="inline-block px-3 py-1 bg-slate-200 dark:bg-slate-600 text-slate-900 dark:text-white rounded-full text-sm font-bold">
                        <?= number_format($p['cantidad']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Establecimientos destino -->
        <div class="col-span-2">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Top 5 Establecimientos destino</h3>
            <div class="space-y-2">
                <?php foreach ($stats['establecimientos'] as $est):
                    $pct = ($est['cantidad'] / $stats['vigentes'] * 100);
                ?>
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= substr($est['establecimiento'] ?? 'Sin establecimiento', 0, 40) ?></span>
                        <span class="text-sm font-bold text-slate-900 dark:text-white"><?= $est['cantidad'] ?> (<?= round($pct) ?>%)</span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                        <div class="h-2 rounded-full" style="width: <?= $pct ?>%; background-color: <?php
                            $colors = ['blue' => '#3b82f6', 'red' => '#ef4444', 'amber' => '#f59e0b'];
                            echo $colors[$color];
                        ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Últimos registros -->
    <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Últimos 10 registros vigentes</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-900 dark:text-white">Folio</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-900 dark:text-white">Paciente</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-900 dark:text-white">RUN</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-900 dark:text-white">Fecha Ingreso</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-900 dark:text-white">Días Espera</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-900 dark:text-white">Prestación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    <?php foreach ($stats['ultimos'] as $reg): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        <td class="px-4 py-3 text-slate-900 dark:text-white font-medium"><?= $reg['folio'] ?></td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?= $reg['paciente'] ?></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= $reg['RUN'] ?></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= date('d/m/Y', strtotime($reg['fecha_ingreso'])) ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php
                                if ($reg['dias_espera'] >= 180) echo 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300';
                                elseif ($reg['dias_espera'] >= 90) echo 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300';
                                elseif ($reg['dias_espera'] >= 30) echo 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300';
                                else echo 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300';
                            ?>">
                                <?= $reg['dias_espera'] ?> días
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs"><?= substr($reg['prestacion'] ?? '', 0, 50) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php layoutFooter(); ?>
