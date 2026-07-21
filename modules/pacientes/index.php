<?php
/**
 * Módulo Pacientes - SIGLECH
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../lib/SICOCHClient.php';
require_once __DIR__ . '/../../partials/layout.php';

$user = requiereLogin();
$pdo = getConexion();
$sicoch = new SICOCHClient(SICOCH_API_BASE_URL, SICOCH_API_KEY);

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
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">📋 Listado de Pacientes</h3>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">RUN</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Nombre</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Fecha Nac.</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Interconsultas</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-900 dark:text-white">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    <td colspan="5" class="text-center py-8 text-slate-500 dark:text-slate-400">
                        Ingresa un RUN o nombre para buscar pacientes
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php layoutFooter(); ?>
