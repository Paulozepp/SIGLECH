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
$pdo_siglech = getConexionSiglech();

// Cliente de SICOCH
$sicoch = new SICOCHClient(SICOCH_API_BASE_URL, SICOCH_API_KEY);

// Verificar conexión con SICOCH
$sicoch_conectado = $sicoch->testConexion();

// Obtener estadísticas desde SIGLECH (demanda de listas de espera)
$stats = [
    'total' => 0,
    'pendientes' => 0,
    'en_gestion' => 0,
    'atendidas' => 0,
];

try {
    // Contar vigentes (ESTADO = 'VIGENTE') desde demanda_cne/iq/proc
    $stmt_vigentes = $pdo_siglech->query("
        SELECT
            (SELECT COUNT(*) FROM demanda_cne WHERE ESTADO = 'VIGENTE') +
            (SELECT COUNT(*) FROM demanda_iq WHERE ESTADO = 'VIGENTE') +
            (SELECT COUNT(*) FROM demanda_proc WHERE ESTADO = 'VIGENTE')
        as vigentes
    ");
    $vigentes = $stmt_vigentes->fetch(PDO::FETCH_ASSOC)['vigentes'] ?? 0;

    // Contar egresados (ATENDIDAS)
    $stmt_egresados = $pdo_siglech->query("
        SELECT
            (SELECT COUNT(*) FROM demanda_cne WHERE ESTADO = 'EGRESADO') +
            (SELECT COUNT(*) FROM demanda_iq WHERE ESTADO = 'EGRESADO') +
            (SELECT COUNT(*) FROM demanda_proc WHERE ESTADO = 'EGRESADO')
        as egresados
    ");
    $egresados = $stmt_egresados->fetch(PDO::FETCH_ASSOC)['egresados'] ?? 0;

    // Contar total
    $stmt_total = $pdo_siglech->query("
        SELECT
            (SELECT COUNT(*) FROM demanda_cne) +
            (SELECT COUNT(*) FROM demanda_iq) +
            (SELECT COUNT(*) FROM demanda_proc)
        as total
    ");
    $total = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stats = [
        'total' => (int)$total,
        'vigentes' => (int)$vigentes,
        'egresados' => (int)$egresados,
    ];
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas SIGLECH: " . $e->getMessage());
}

// Obtener especialidades (desde SICOCH vía API)
$especialidades = $sicoch->obtenerEspecialidades();

// Obtener causales de salida (código) de los egresados con descripción
$causales_salida = [];
$causales_por_comuna = [];
try {
    $stmt_causales = $pdo_siglech->query("
        SELECT
            ac.C_SALIDA as codigo,
            dcs.nombre as descripcion,
            dcs.tipo_causal,
            COUNT(*) as cantidad
        FROM (
            SELECT C_SALIDA FROM demanda_cne WHERE ESTADO = 'EGRESADO' AND C_SALIDA IS NOT NULL
            UNION ALL
            SELECT C_SALIDA FROM demanda_iq WHERE ESTADO = 'EGRESADO' AND C_SALIDA IS NOT NULL
            UNION ALL
            SELECT C_SALIDA FROM demanda_proc WHERE ESTADO = 'EGRESADO' AND C_SALIDA IS NOT NULL
        ) as ac
        LEFT JOIN dim_causal_salida dcs ON ac.C_SALIDA = dcs.id
        GROUP BY codigo, descripcion, tipo_causal
        ORDER BY cantidad DESC
        LIMIT 10
    ");
    $causales_salida = $stmt_causales->fetchAll(PDO::FETCH_ASSOC) ?? [];

    // Obtener desglose por comuna para cada causal
    $stmt_comuna = $pdo_siglech->query("
        SELECT
            ac.C_SALIDA as codigo,
            ac.CIUDAD as comuna,
            COUNT(*) as cantidad
        FROM (
            SELECT C_SALIDA, CIUDAD FROM demanda_cne WHERE ESTADO = 'EGRESADO' AND C_SALIDA IS NOT NULL
            UNION ALL
            SELECT C_SALIDA, CIUDAD FROM demanda_iq WHERE ESTADO = 'EGRESADO' AND C_SALIDA IS NOT NULL
            UNION ALL
            SELECT C_SALIDA, CIUDAD FROM demanda_proc WHERE ESTADO = 'EGRESADO' AND C_SALIDA IS NOT NULL
        ) as ac
        GROUP BY codigo, comuna
        ORDER BY codigo, cantidad DESC
    ");
    $causales_por_comuna = $stmt_comuna->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) {
    error_log("Error obteniendo causales de salida: " . $e->getMessage());
}

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
        Sistema independiente con datos sincronizados • API REST disponible
      </p>

      <!-- Status de Conexión -->
      <div class="mt-6 flex items-center gap-4 flex-wrap">
        <?php if ($sicoch_conectado): ?>
          <span class="px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full text-sm font-semibold">
            ✓ Conectado a SICOCH
          </span>
        <?php else: ?>
          <span class="px-4 py-2 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded-full text-sm font-semibold" title="Sistema funciona de manera independiente">
            ⚠️ SICOCH sin conexión
          </span>
        <?php endif; ?>
        <span class="px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-sm font-semibold">
          🔄 BD Compartida
        </span>
        <span class="px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full text-sm font-semibold">
          ✓ API REST
        </span>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
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

      <!-- Vigentes -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold">En Espera (Vigentes)</p>
            <p class="text-4xl font-bold text-rose-600 dark:text-rose-400 mt-3">
              <?= number_format($stats['vigentes'] ?? 0, 0, ',', '.') ?>
            </p>
          </div>
          <div class="text-5xl opacity-20">⏳</div>
        </div>
      </div>

      <!-- Egresados -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold">Egresados</p>
            <p class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mt-3">
              <?= number_format($stats['egresados'] ?? 0, 0, ',', '.') ?>
            </p>
          </div>
          <div class="text-5xl opacity-20">✓</div>
        </div>
      </div>
    </div>

    <!-- Causales de Salida -->
    <?php if (!empty($causales_salida)): ?>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 mb-12">
      <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
        📤 Causales de Salida (Egresados)
      </h2>
      <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Análisis de las razones de egreso y distribución geográfica</p>

      <!-- Grid principal: Causales + Total -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Causales -->
        <div class="lg:col-span-2">
          <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Top 10 Causales</h3>
          <div class="space-y-4">
            <?php
            $total_causales = array_sum(array_column($causales_salida, 'cantidad'));
            foreach ($causales_salida as $causal):
                $pct = $total_causales > 0 ? round(($causal['cantidad'] / $total_causales) * 100) : 0;
                $color = match(true) {
                    $pct >= 20 => 'bg-emerald-500',
                    $pct >= 10 => 'bg-blue-500',
                    $pct >= 5 => 'bg-amber-500',
                    default => 'bg-slate-400'
                };
            ?>
            <div class="space-y-2">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="text-sm font-semibold text-slate-900 dark:text-white">
                    <?= htmlspecialchars($causal['descripcion'] ?? 'Causal sin descripción') ?>
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Tipo: <span class="inline-block px-2 py-0.5 bg-slate-100 dark:bg-slate-700 rounded text-xs">
                      <?= htmlspecialchars($causal['tipo_causal'] ?? 'N/A') ?>
                    </span>
                  </div>
                </div>
                <div class="text-right ml-2">
                  <span class="text-sm font-bold text-slate-900 dark:text-white">
                    <?= number_format((int)$causal['cantidad'], 0, ',', '.') ?>
                  </span>
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    <?= $pct ?>%
                  </div>
                </div>
              </div>
              <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5">
                <div class="h-2.5 rounded-full <?= $color ?>" style="width: <?= $pct ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Total de Interconsultas Egresadas -->
        <div>
          <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Resumen</h3>
          <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-900/40 rounded-lg p-6 border-2 border-emerald-200 dark:border-emerald-700">
            <p class="text-sm text-emerald-700 dark:text-emerald-300 mb-1">Total Egresados</p>
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
              <?= number_format($total_causales, 0, ',', '.') ?>
            </p>
            <p class="text-xs text-emerald-600 dark:text-emerald-300 mt-3">
              de <?= number_format($stats['egresados'], 0, ',', '.') ?> total
            </p>
            <div class="mt-3 pt-3 border-t border-emerald-200 dark:border-emerald-700">
              <p class="text-xs text-emerald-600 dark:text-emerald-300">
                <strong><?= round(($total_causales / $stats['egresados'] * 100)) ?>%</strong> con causal registrada
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Desglose por Comuna -->
      <div class="border-t border-slate-200 dark:border-slate-700 pt-8">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Desglose por Comuna</h3>
        <div class="space-y-6">
          <?php
          // Agrupar por causal
          $causales_agrupadas = [];
          foreach ($causales_por_comuna as $item) {
            $codigo = $item['codigo'];
            if (!isset($causales_agrupadas[$codigo])) {
              $causales_agrupadas[$codigo] = [];
            }
            $causales_agrupadas[$codigo][] = $item;
          }

          // Mostrar solo las top 5 causales
          $top_5_ids = array_slice(array_column($causales_salida, 'codigo'), 0, 5);

          foreach ($top_5_ids as $causal_id):
            if (!isset($causales_agrupadas[$causal_id])) continue;

            $causal_info = null;
            foreach ($causales_salida as $c) {
              if ($c['codigo'] == $causal_id) {
                $causal_info = $c;
                break;
              }
            }
            if (!$causal_info) continue;

            $comunas_data = $causales_agrupadas[$causal_id];
            $total_causal = array_sum(array_column($comunas_data, 'cantidad'));
          ?>
          <div class="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-4">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-3">
              <?= htmlspecialchars($causal_info['descripcion'] ?? 'Causal') ?>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">(<?= number_format($total_causal) ?> casos)</span>
            </h4>
            <div class="space-y-2">
              <?php foreach (array_slice($comunas_data, 0, 5) as $com):
                $pct_com = round(($com['cantidad'] / $total_causal) * 100);
              ?>
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-700 dark:text-slate-300">
                  <?= htmlspecialchars($com['comuna'] ?? 'Sin especificar') ?>
                </span>
                <div class="flex items-center gap-2">
                  <div class="w-24 bg-slate-200 dark:bg-slate-600 rounded h-1.5">
                    <div class="bg-blue-500 h-1.5 rounded" style="width: <?= $pct_com ?>%"></div>
                  </div>
                  <span class="text-slate-600 dark:text-slate-400 font-medium w-10 text-right">
                    <?= $pct_com ?>%
                  </span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Navegación Principal -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">

      <!-- Listas de Espera -->
      <a href="/SIGLECH/modules/listas_espera/" class="group bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 hover:border-brand-500 dark:hover:border-brand-400 transition-all">
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
      <a href="/SIGLECH/modules/reportes/" class="group bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 hover:border-blue-500 dark:hover:border-blue-400 transition-all">
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
      <a href="/SIGLECH/modules/pacientes/" class="group bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 border border-slate-200 dark:border-slate-700 hover:border-green-500 dark:hover:border-green-400 transition-all">
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

      <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">📚 Recursos y Documentación</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Sincronizar -->
          <a href="/SIGLECH/modules/integracion/sync_manual.php" class="group bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/40 border-2 border-blue-200 dark:border-blue-700 rounded-xl p-6 hover:shadow-lg transition-all hover:scale-105">
            <div class="text-4xl mb-3">🔄</div>
            <h4 class="font-bold text-blue-900 dark:text-blue-100 mb-1">Sincronizar SICOCH</h4>
            <p class="text-sm text-blue-800 dark:text-blue-200">Actualizar datos desde SICOCH</p>
            <p class="text-xs text-blue-700 dark:text-blue-300 mt-3 group-hover:font-semibold">Ir a sincronización →</p>
          </a>

          <!-- Documentación -->
          <a href="/SIGLECH/documentacion-viewer.php" class="group bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-900/40 border-2 border-purple-200 dark:border-purple-700 rounded-xl p-6 hover:shadow-lg transition-all hover:scale-105">
            <div class="text-4xl mb-3">📖</div>
            <h4 class="font-bold text-purple-900 dark:text-purple-100 mb-1">Documentación</h4>
            <p class="text-sm text-purple-800 dark:text-purple-200">Guía completa del sistema</p>
            <p class="text-xs text-purple-700 dark:text-purple-300 mt-3 group-hover:font-semibold">Ver documentación →</p>
          </a>

          <!-- API Reference -->
          <a href="/SIGLECH/documentacion-viewer.php" class="group bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-900/40 border-2 border-amber-200 dark:border-amber-700 rounded-xl p-6 hover:shadow-lg transition-all hover:scale-105">
            <div class="text-4xl mb-3">🔌</div>
            <h4 class="font-bold text-amber-900 dark:text-amber-100 mb-1">API Reference</h4>
            <p class="text-sm text-amber-800 dark:text-amber-200">Referencia de endpoints REST</p>
            <p class="text-xs text-amber-700 dark:text-amber-300 mt-3 group-hover:font-semibold">Ver endpoints →</p>
          </a>
        </div>

        <!-- Info cards de documentación -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
          <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4 text-sm">
            <p class="font-semibold text-slate-900 dark:text-white mb-2">💾 Bases de Datos</p>
            <p class="text-slate-600 dark:text-slate-400">SIGLECH (245k+) + SICOCH (compartida)</p>
          </div>
          <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4 text-sm">
            <p class="font-semibold text-slate-900 dark:text-white mb-2">🔐 Autenticación</p>
            <p class="text-slate-600 dark:text-slate-400">Bearer Token + JWT seguro</p>
          </div>
          <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4 text-sm">
            <p class="font-semibold text-slate-900 dark:text-white mb-2">⚡ Rendimiento</p>
            <p class="text-slate-600 dark:text-slate-400">100 requests/min • 2s timeout</p>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php layoutFooter(); ?>

