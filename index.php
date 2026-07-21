<?php
/**
 * SIGLECH - Dashboard Principal
 * Gestión de Listas de Espera Chiloé v1.0
 */

require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/lib/SICOCHClient.php';
require_once __DIR__ . '/partials/layout.php';

$user = requiereLogin();
$pdo = getConexion();

// Cliente de SICOCH
$sicoch = new SICOCHClient(SICOCH_API_BASE_URL, SICOCH_API_KEY);

// Verificar conexión con SICOCH
$sicoch_conectado = $sicoch->testConexion();

// Obtener estadísticas locales
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'EN_GESTION' THEN 1 ELSE 0 END) as en_gestion,
        SUM(CASE WHEN estado = 'ATENDIDA' THEN 1 ELSE 0 END) as atendidas
    FROM lista_espera_interconsultas
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?? [
    'total' => 0,
    'pendientes' => 0,
    'en_gestion' => 0,
    'atendidas' => 0,
];

// Obtener especialidades (desde SICOCH vía API)
$especialidades = $sicoch->obtenerEspecialidades();

layoutHeader('Dashboard - SIGLECH', $user, 'dashboard');
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
  <div class="max-w-7xl mx-auto px-4 py-12">

    <!-- Encabezado -->
    <div class="mb-12">
      <h1 class="text-5xl font-bold mb-4 text-slate-900 dark:text-white">
        🗂️ SIGLECH v1.0
      </h1>
      <p class="text-xl text-slate-600 dark:text-slate-400 mb-2">
        Gestión de Listas de Espera Chiloé
      </p>
      <p class="text-sm text-slate-500 dark:text-slate-500">
        Sistema independiente de SICOCH • Comunicación vía API REST
      </p>

      <!-- Status de Conexión -->
      <div class="mt-6 flex items-center gap-4">
        <?php if ($sicoch_conectado): ?>
          <span class="px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full text-sm font-semibold">
            ✓ Conectado a SICOCH
          </span>
        <?php else: ?>
          <span class="px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-full text-sm font-semibold">
            ✗ No conectado a SICOCH
          </span>
        <?php endif; ?>
        <span class="px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-sm font-semibold">
          🔄 BD Compartida
        </span>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
      <!-- Total -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold">Total Interconsultas</p>
            <p class="text-4xl font-bold text-brand-600 dark:text-brand-400 mt-3">
              <?= number_format($stats['total'] ?? 0, 0, ',', '.') ?>
            </p>
          </div>
          <div class="text-5xl opacity-20">📊</div>
        </div>
      </div>

      <!-- Pendientes -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold">Pendientes</p>
            <p class="text-4xl font-bold text-rose-600 dark:text-rose-400 mt-3">
              <?= number_format($stats['pendientes'] ?? 0, 0, ',', '.') ?>
            </p>
          </div>
          <div class="text-5xl opacity-20">⏳</div>
        </div>
      </div>

      <!-- En Gestión -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold">En Gestión</p>
            <p class="text-4xl font-bold text-amber-600 dark:text-amber-400 mt-3">
              <?= number_format($stats['en_gestion'] ?? 0, 0, ',', '.') ?>
            </p>
          </div>
          <div class="text-5xl opacity-20">📞</div>
        </div>
      </div>

      <!-- Atendidas -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold">Atendidas</p>
            <p class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mt-3">
              <?= number_format($stats['atendidas'] ?? 0, 0, ',', '.') ?>
            </p>
          </div>
          <div class="text-5xl opacity-20">✓</div>
        </div>
      </div>
    </div>

    <!-- Navegación Principal -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">

      <!-- Listas de Espera -->
      <a href="/SIGLECH//SIGLECH/modules/listas_espera/" class="group bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 hover:border-brand-500 dark:hover:border-brand-400 transition-all">
        <div class="text-4xl mb-4">📋</div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2 group-hover:text-brand-600">
          Gestión de Listas
        </h3>
        <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
          Visualiza y gestiona listas de espera por categoría
        </p>
        <span class="inline-block text-brand-600 dark:text-brand-400 font-semibold">Ir →</span>
      </a>

      <!-- Reportes -->
      <a href="/SIGLECH//SIGLECH/modules/reportes/" class="group bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 hover:border-blue-500 dark:hover:border-blue-400 transition-all">
        <div class="text-4xl mb-4">📊</div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2 group-hover:text-blue-600">
          Reportes
        </h3>
        <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
          Reportes avanzados, KPIs y alertas de tiempo espera
        </p>
        <span class="inline-block text-blue-600 dark:text-blue-400 font-semibold">Ir →</span>
      </a>

      <!-- Pacientes -->
      <a href="/SIGLECH//SIGLECH/modules/pacientes/" class="group bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 hover:border-green-500 dark:hover:border-green-400 transition-all">
        <div class="text-4xl mb-4">👥</div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2 group-hover:text-green-600">
          Pacientes
        </h3>
        <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">
          Perfiles de pacientes (sincronizados desde SICOCH)
        </p>
        <span class="inline-block text-green-600 dark:text-green-400 font-semibold">Ir →</span>
      </a>

    </div>

    <!-- Información de Sistema -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700">
      <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">
        ℹ️ Información del Sistema
      </h2>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div>
          <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold mb-2">SIGLECH Version</p>
          <p class="text-lg font-mono text-slate-900 dark:text-white"><?= SIGLECH_VERSION ?></p>
        </div>
        <div>
          <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold mb-2">Usuario Actual</p>
          <p class="text-lg font-mono text-slate-900 dark:text-white">
            <?= htmlspecialchars($user['nombre'] ?? $user['usuario']) ?>
          </p>
        </div>
        <div>
          <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold mb-2">Rol</p>
          <p class="text-lg font-mono text-slate-900 dark:text-white capitalize">
            <?= htmlspecialchars($user['rol']) ?>
          </p>
        </div>
        <div>
          <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold mb-2">Hora del Servidor</p>
          <p class="text-lg font-mono text-slate-900 dark:text-white">
            <?= date('H:i:s') ?>
          </p>
        </div>
      </div>

      <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
          <a href="/SIGLECH//SIGLECH/modules/integracion/sync_manual.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
            🔄 Sincronizar con SICOCH
          </a>
          <a href="/SIGLECH/ARQUITECTURA_SIGLECH_API.md" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg transition">
            📖 Documentación
          </a>
          <a href="/SIGLECH/API_ENDPOINTS.md" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg transition">
            🔌 API Reference
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<?php layoutFooter(); ?>

