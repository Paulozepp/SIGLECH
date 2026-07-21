<?php
/**
 * Módulo Reportes - SIGLECH
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../partials/layout.php';

$user = requiereLogin();
$pdo = getConexion();

layoutHeader('Reportes', $user, 'reportes');
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">📊 Reportes</h2>
    <p class="text-slate-600 dark:text-slate-400">KPIs, análisis de tiempos de espera y alertas</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Reporte de Tiempos -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 hover:shadow-xl transition">
        <p class="text-4xl mb-4">📈</p>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Tiempos de Espera</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Análisis por categoría y prioridad</p>
        <a href="reporte_tiempos.php" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Ver →</a>
    </div>

    <!-- Reporte de Gestiones -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 hover:shadow-xl transition">
        <p class="text-4xl mb-4">📞</p>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Gestiones de Contacto</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Intentos de contacto y resultados</p>
        <a href="reporte_gestiones.php" class="inline-block px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">Ver →</a>
    </div>

    <!-- Alertas -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 hover:shadow-xl transition">
        <p class="text-4xl mb-4">🚨</p>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Alertas de Tiempo Espera</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Interconsultas que exceden límites</p>
        <a href="reporte_alertas.php" class="inline-block px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">Ver →</a>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">📊 Dashboard de KPIs</h3>
    <div class="text-center py-12">
        <p class="text-slate-600 dark:text-slate-400">Gráficos y estadísticas en construcción...</p>
    </div>
</div>

<?php layoutFooter(); ?>
