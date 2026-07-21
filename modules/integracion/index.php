<?php
/**
 * Módulo Integración - SIGLECH
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../lib/SICOCHClient.php';
require_once __DIR__ . '/../../partials/layout.php';

$user = requiereLogin();
$pdo = getConexion();
$sicoch = new SICOCHClient(SICOCH_API_BASE_URL, SICOCH_API_KEY);

layoutHeader('Integración con SICOCH', $user, 'integracion');
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">🔌 Integración con SICOCH</h2>
    <p class="text-slate-600 dark:text-slate-400">Estado de conexión y sincronización de datos</p>
</div>

<!-- Estado de Conexión -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- SICOCH Status -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">🏥 Estado de SICOCH</h3>
        <?php
        $sicoch_conectado = $sicoch->testConexion();
        if ($sicoch_conectado):
        ?>
            <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                <p class="text-green-800 dark:text-green-200 font-semibold">✓ Conectado</p>
                <p class="text-sm text-green-700 dark:text-green-300 mt-1">SICOCH está disponible y responde correctamente</p>
            </div>
        <?php else: ?>
            <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                <p class="text-red-800 dark:text-red-200 font-semibold">✗ No conectado</p>
                <p class="text-sm text-red-700 dark:text-red-300 mt-1">No se puede establecer conexión con SICOCH</p>
            </div>
        <?php endif; ?>

        <div class="text-sm text-slate-600 dark:text-slate-400 space-y-1">
            <p><strong>API Base URL:</strong> <code class="bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded"><?= SICOCH_API_BASE_URL ?></code></p>
            <p><strong>API Key:</strong> <code class="bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded">***<?= substr(SICOCH_API_KEY, -6) ?></code></p>
        </div>
    </div>

    <!-- Base de Datos -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">💾 Base de Datos</h3>
        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
            <p class="text-blue-800 dark:text-blue-200 font-semibold">✓ Conectado</p>
            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">Base de datos local está disponible</p>
        </div>

        <div class="text-sm text-slate-600 dark:text-slate-400 space-y-1">
            <p><strong>Host:</strong> <code class="bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded"><?= DB_HOST ?></code></p>
            <p><strong>BD:</strong> <code class="bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded"><?= DB_NAME ?></code></p>
            <p><strong>Usuario:</strong> <code class="bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded"><?= DB_USER ?></code></p>
        </div>
    </div>
</div>

<!-- Opciones de Sincronización -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 mb-8">
    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">🔄 Sincronización</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <a href="sync_manual.php" class="block px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-semibold">
            🔄 Sincronización Manual
        </a>
        <a href="sync_logs.php" class="block px-6 py-4 bg-slate-600 hover:bg-slate-700 text-white rounded-lg transition font-semibold">
            📋 Ver Registros de Sincronización
        </a>
    </div>

    <p class="text-slate-600 dark:text-slate-400 text-sm">
        La sincronización automática se ejecuta cada 30 minutos. Usa la opción manual para forzar una sincronización inmediata.
    </p>
</div>

<!-- Endpoints disponibles -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">📚 API Endpoints SICOCH</h3>

    <div class="space-y-3">
        <div class="bg-slate-50 dark:bg-slate-700 p-4 rounded-lg">
            <p class="font-mono text-sm text-slate-900 dark:text-white mb-1">GET /api/pacientes</p>
            <p class="text-sm text-slate-600 dark:text-slate-400">Obtener lista de pacientes</p>
        </div>
        <div class="bg-slate-50 dark:bg-slate-700 p-4 rounded-lg">
            <p class="font-mono text-sm text-slate-900 dark:text-white mb-1">GET /api/pacientes/:id</p>
            <p class="text-sm text-slate-600 dark:text-slate-400">Obtener detalles de un paciente</p>
        </div>
        <div class="bg-slate-50 dark:bg-slate-700 p-4 rounded-lg">
            <p class="font-mono text-sm text-slate-900 dark:text-white mb-1">GET /api/especialidades</p>
            <p class="text-sm text-slate-600 dark:text-slate-400">Obtener listado de especialidades</p>
        </div>
        <div class="bg-slate-50 dark:bg-slate-700 p-4 rounded-lg">
            <p class="font-mono text-sm text-slate-900 dark:text-white mb-1">GET /api/establecimientos</p>
            <p class="text-sm text-slate-600 dark:text-slate-400">Obtener listado de establecimientos</p>
        </div>
    </div>
</div>

<?php layoutFooter(); ?>
