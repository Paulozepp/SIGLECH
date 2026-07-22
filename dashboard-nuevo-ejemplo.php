<?php
/**
 * EJEMPLO: Dashboard Rediseñado con Dark Mode
 * Esta es una plantilla de ejemplo mostrando el nuevo diseño
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/partials/layout.php';

$user = requiereLogin();
$pdo = getConexionSiglech();

// Obtener estadísticas
$stats = [
    'total_vigentes' => 0,
    'total_egresados' => 0,
    'en_progreso' => 0,
];

try {
    $stmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM demanda_cne WHERE ESTADO = 'VIGENTE') +
            (SELECT COUNT(*) FROM demanda_iq WHERE ESTADO = 'VIGENTE') +
            (SELECT COUNT(*) FROM demanda_proc WHERE ESTADO = 'VIGENTE') as vigentes,
            (SELECT COUNT(*) FROM demanda_cne WHERE ESTADO = 'EGRESADO') +
            (SELECT COUNT(*) FROM demanda_iq WHERE ESTADO = 'EGRESADO') +
            (SELECT COUNT(*) FROM demanda_proc WHERE ESTADO = 'EGRESADO') as egresados
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_vigentes'] = $result['vigentes'] ?? 0;
    $stats['total_egresados'] = $result['egresados'] ?? 0;
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
}

layoutHeader('Dashboard Nuevo', $user, 'dashboard');
?>

<div class="mb-8">
    <h1 class="section-header">📊 Dashboard Principal</h1>
    <p class="section-subtitle">Gestión de Listas de Espera - Vista General</p>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Vigentes -->
    <div class="card p-6">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-2">Pacientes Vigentes</p>
                <p class="text-4xl font-bold text-gray-900 dark:text-white">
                    <?php echo number_format($stats['total_vigentes']); ?>
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-500 mt-2">📈 En espera de atención</p>
            </div>
            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                <span class="text-2xl">👥</span>
            </div>
        </div>
    </div>

    <!-- Total Egresados -->
    <div class="card p-6">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-2">Pacientes Egresados</p>
                <p class="text-4xl font-bold text-gray-900 dark:text-white">
                    <?php echo number_format($stats['total_egresados']); ?>
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-500 mt-2">✅ Atención completada</p>
            </div>
            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                <span class="text-2xl">✔️</span>
            </div>
        </div>
    </div>

    <!-- Tasa de Atención -->
    <div class="card p-6">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-600 dark:text-slate-400 text-sm mb-2">Tasa de Atención</p>
                <p class="text-4xl font-bold bg-gradient-to-r from-green-600 to-emerald-500 bg-clip-text text-transparent">
                    <?php
                        $total = $stats['total_vigentes'] + $stats['total_egresados'];
                        $tasa = $total > 0 ? round(($stats['total_egresados'] / $total) * 100) : 0;
                        echo $tasa . '%';
                    ?>
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-500 mt-2">📊 Del total procesado</p>
            </div>
            <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                <span class="text-2xl">📈</span>
            </div>
        </div>
    </div>
</div>

<!-- Secciones por Tipo -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <?php
    $tipos = [
        ['nombre' => 'CNE', 'icono' => '🏥', 'color' => 'blue'],
        ['nombre' => 'IQ', 'icono' => '⚕️', 'color' => 'purple'],
        ['nombre' => 'PROC', 'icono' => '🔬', 'color' => 'green'],
    ];

    foreach ($tipos as $tipo):
        try {
            $tabla = 'demanda_' . strtolower($tipo['nombre']);
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN ESTADO = 'VIGENTE' THEN 1 ELSE 0 END) as vigentes,
                    SUM(CASE WHEN ESTADO = 'EGRESADO' THEN 1 ELSE 0 END) as egresados
                FROM $tabla
            ");
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="card-highlighted p-6 border-l-purple-500">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg text-gray-900 dark:text-white">
                        <?php echo $tipo['icono'] . ' ' . $tipo['nombre']; ?>
                    </h3>
                    <span class="badge badge-info"><?php echo $data['total']; ?></span>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Vigentes</span>
                        <span class="font-bold text-<?php echo $tipo['color']; ?>-600 dark:text-<?php echo $tipo['color']; ?>-400">
                            <?php echo $data['vigentes']; ?>
                        </span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full"
                             style="width: <?php echo $data['total'] > 0 ? ($data['vigentes'] / $data['total']) * 100 : 0; ?>%">
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Egresados</span>
                        <span class="font-bold text-green-600 dark:text-green-400">
                            <?php echo $data['egresados']; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php
        } catch (Exception $e) {
            echo "<!-- Error: " . $e->getMessage() . " -->";
        }
    endforeach;
    ?>
</div>

<!-- Panel de Acciones Rápidas -->
<div class="card p-6 mb-8">
    <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">⚡ Acciones Rápidas</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <a href="/SIGLECH/modules/listas_espera/" class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors text-center">
            <div class="text-2xl mb-2">📋</div>
            <p class="text-sm font-medium text-blue-700 dark:text-blue-300">Listas de Espera</p>
        </a>
        <a href="/SIGLECH/modules/reportes/" class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40 transition-colors text-center">
            <div class="text-2xl mb-2">📊</div>
            <p class="text-sm font-medium text-green-700 dark:text-green-300">Reportes</p>
        </a>
        <a href="/SIGLECH/modules/demanda_le/" class="p-4 rounded-lg bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/40 transition-colors text-center">
            <div class="text-2xl mb-2">📥</div>
            <p class="text-sm font-medium text-purple-700 dark:text-purple-300">Demanda LE</p>
        </a>
        <a href="/SIGLECH/modules/integracion_python/" class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors text-center">
            <div class="text-2xl mb-2">🐍</div>
            <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Integración</p>
        </a>
    </div>
</div>

<!-- Información del Sistema -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="card p-6">
        <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">ℹ️ Información del Sistema</h3>
        <ul class="space-y-3 text-sm">
            <li class="flex justify-between">
                <span class="text-slate-600 dark:text-slate-400">Versión</span>
                <span class="font-bold text-gray-900 dark:text-white">1.0.0</span>
            </li>
            <li class="flex justify-between">
                <span class="text-slate-600 dark:text-slate-400">Ambiente</span>
                <span class="font-bold text-gray-900 dark:text-white">Producción</span>
            </li>
            <li class="flex justify-between">
                <span class="text-slate-600 dark:text-slate-400">Última Actualización</span>
                <span class="font-bold text-gray-900 dark:text-white"><?php echo date('d/m/Y H:i'); ?></span>
            </li>
            <li class="flex justify-between">
                <span class="text-slate-600 dark:text-slate-400">Base de Datos</span>
                <span class="font-bold text-green-600 dark:text-green-400">✅ Conectada</span>
            </li>
        </ul>
    </div>

    <div class="card p-6">
        <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">🎯 Próximas Acciones</h3>
        <ul class="space-y-3 text-sm">
            <li class="flex items-start gap-3">
                <span class="text-amber-500 mt-0.5">•</span>
                <span class="text-slate-600 dark:text-slate-400">Revisar listas de espera pendientes</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="text-blue-500 mt-0.5">•</span>
                <span class="text-slate-600 dark:text-slate-400">Generar reportes mensuales</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="text-green-500 mt-0.5">•</span>
                <span class="text-slate-600 dark:text-slate-400">Sincronizar datos con SICOCH</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="text-purple-500 mt-0.5">•</span>
                <span class="text-slate-600 dark:text-slate-400">Validar importaciones Python</span>
            </li>
        </ul>
    </div>
</div>

<?php layoutFooter(); ?>
