<?php
/**
 * SIGLECH - Módulo de Integración Python
 * Monitor de datos recibidos desde Navegador de Paciente
 */

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../partials/layout.php';

// Verificar sesión
$user = requiereLogin();

try {
    $pdo = getConexionSiglech();

    // Obtener estadísticas de importaciones
    $stmt = $pdo->query("
        SELECT
            tipo,
            COUNT(*) as total_importaciones,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as con_error,
            MAX(fecha_inicio) as ultima_importacion
        FROM importaciones
        WHERE metodo = 'json'
        GROUP BY tipo
    ");
    $stats_importaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimas importaciones (últimas 10) con detalles de pacientes
    $stmt = $pdo->query("
        SELECT
            i.importacion_id,
            i.tipo,
            i.total_registros,
            i.registros_exitosos,
            i.registros_fallidos,
            i.estado,
            i.fecha_inicio
        FROM importaciones i
        WHERE i.metodo = 'json'
        ORDER BY i.fecha_inicio DESC
        LIMIT 10
    ");
    $ultimas_importaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada importación, obtener datos de pacientes recientemente cargados
    foreach ($ultimas_importaciones as &$imp) {
        // Buscar pacientes importados en las últimas 2 horas (aproximadamente cuando fue la importación)
        $fecha_desde = date('Y-m-d H:i:s', strtotime($imp['fecha_inicio'] . ' -2 hours'));
        $fecha_hasta = date('Y-m-d H:i:s', strtotime($imp['fecha_inicio'] . ' +1 hour'));

        $tabla = match($imp['tipo']) {
            'CNE' => 'demanda_cne',
            'IQ' => 'demanda_iq',
            'PROC' => 'demanda_proc',
            default => 'demanda_cne'
        };

        $stmt_pac = $pdo->prepare("
            SELECT DISTINCT RUN, PRIMER_APELLIDO, SEGUNDO_APELLIDO, NOMBRES, ESPECIALIDAD_ESTANDAR
            FROM $tabla
            WHERE fecha_carga BETWEEN ? AND ?
            LIMIT 5
        ");
        $stmt_pac->execute([$fecha_desde, $fecha_hasta]);
        $pacientes = $stmt_pac->fetchAll(PDO::FETCH_ASSOC);

        $imp['runs'] = implode(', ', array_column($pacientes, 'RUN'));
        $imp['pacientes'] = implode('; ', array_map(
            fn($p) => trim($p['PRIMER_APELLIDO'] . ' ' . $p['SEGUNDO_APELLIDO'] . ', ' . $p['NOMBRES']),
            $pacientes
        ));
        $imp['especialidades'] = implode(', ', array_unique(array_filter(array_column($pacientes, 'ESPECIALIDAD_ESTANDAR'))));
    }

    // Registros de prueba (con _id que contiene PRUEBA)
    $stmt = $pdo->prepare("
        SELECT tipo, COUNT(*) as cantidad, MAX(fecha_carga) as ultima_carga
        FROM (
            SELECT 'CNE' as tipo, fecha_carga FROM demanda_cne WHERE _id LIKE '%PRUEBA%'
            UNION ALL
            SELECT 'IQ' as tipo, fecha_carga FROM demanda_iq WHERE _id LIKE '%PRUEBA%'
            UNION ALL
            SELECT 'PROC' as tipo, fecha_carga FROM demanda_proc WHERE _id LIKE '%PRUEBA%'
        ) as pruebas
        GROUP BY tipo
    ");
    $stmt->execute();
    $registros_prueba = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Detalles de importación más reciente
    $stmt = $pdo->query("
        SELECT
            importacion_id,
            registros_exitosos,
            registros_fallidos,
            estado,
            fecha_inicio
        FROM importaciones
        WHERE metodo = 'json'
        ORDER BY fecha_inicio DESC
        LIMIT 1
    ");
    $detalles_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

?>
<?php
// Usar el layout de SIGLECH
layoutHeader('Integración Python', $user, 'integracion_python');

// Estilos personalizados con soporte dark mode
echo '<style>
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    .status-completado {
        background-color: #d1fae5;
        color: #065f46;
    }
    .dark .status-completado {
        background-color: #10b98133;
        color: #86efac;
    }
    .status-en_progreso {
        background-color: #dbeafe;
        color: #0c4a6e;
    }
    .dark .status-en_progreso {
        background-color: #3b82f633;
        color: #93c5fd;
    }
    .status-error {
        background-color: #fee2e2;
        color: #7f1d1d;
    }
    .dark .status-error {
        background-color: #ef444433;
        color: #fca5a5;
    }
</style>';
?>

<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">🐍 Integración Python</h1>
    <p class="text-gray-600 dark:text-gray-400">Monitor de datos recibidos desde Navegador de Paciente</p>
</div>
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold mb-2">🐍 Integración Python</h1>
            <p class="text-gray-400">Monitor de datos recibidos desde Navegador de Paciente</p>
        </div>

        <!-- Alertas -->
        <?php if (!empty($error_msg)): ?>
        <div class="bg-red-900/30 border border-red-500/50 rounded-lg p-4 mb-6">
            <p class="text-red-200">⚠️ Error: <?php echo htmlspecialchars($error_msg); ?></p>
        </div>
        <?php endif; ?>

        <!-- Estadísticas de Importaciones -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-slate-800/50 backdrop-blur border border-slate-200 dark:border-slate-700 rounded-lg p-6">
                <div class="text-slate-600 dark:text-gray-400 text-sm mb-2">📦 Total Importaciones</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo count($stats_importaciones) > 0 ? array_sum(array_column($stats_importaciones, 'total_importaciones')) : 0; ?></div>
            </div>

            <div class="bg-green-50 dark:bg-green-900/30 backdrop-blur border border-green-200 dark:border-green-500/50 rounded-lg p-6">
                <div class="text-green-700 dark:text-green-200 text-sm mb-2">✅ Completadas</div>
                <div class="text-3xl font-bold text-green-600 dark:text-green-300"><?php echo count($stats_importaciones) > 0 ? array_sum(array_column($stats_importaciones, 'completadas')) : 0; ?></div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/30 backdrop-blur border border-blue-200 dark:border-blue-500/50 rounded-lg p-6">
                <div class="text-blue-700 dark:text-blue-200 text-sm mb-2">⏳ En Progreso</div>
                <div class="text-3xl font-bold text-blue-600 dark:text-blue-300"><?php echo count($stats_importaciones) > 0 ? array_sum(array_column($stats_importaciones, 'en_progreso')) : 0; ?></div>
            </div>

            <div class="bg-red-50 dark:bg-red-900/30 backdrop-blur border border-red-200 dark:border-red-500/50 rounded-lg p-6">
                <div class="text-red-700 dark:text-red-200 text-sm mb-2">❌ Con Error</div>
                <div class="text-3xl font-bold text-red-600 dark:text-red-300"><?php echo count($stats_importaciones) > 0 ? array_sum(array_column($stats_importaciones, 'con_error')) : 0; ?></div>
            </div>
        </div>

        <!-- Registros de Prueba -->
        <?php if (!empty($registros_prueba)): ?>
        <div class="bg-white dark:bg-slate-800/50 backdrop-blur border border-amber-200 dark:border-amber-500/30 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-bold mb-4 flex items-center text-gray-900 dark:text-white">
                <span class="text-amber-500 dark:text-amber-400 mr-2">🧪</span>
                Registros de Prueba Recibidos
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($registros_prueba as $prueba): ?>
                <div class="bg-amber-50 dark:bg-slate-700/50 rounded p-4 border border-amber-200 dark:border-amber-500/30">
                    <div class="font-semibold text-amber-700 dark:text-amber-300"><?php echo htmlspecialchars($prueba['tipo']); ?></div>
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-200 mt-2"><?php echo $prueba['cantidad']; ?> registros</div>
                    <div class="text-sm text-slate-600 dark:text-gray-400 mt-2">
                        Última carga: <?php echo $prueba['ultima_carga'] ? date('d/m/Y H:i', strtotime($prueba['ultima_carga'])) : 'N/A'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Últimas Importaciones -->
        <div class="bg-white dark:bg-slate-800/50 backdrop-blur border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">📊 Últimas Importaciones</h2>

            <?php if (!empty($ultimas_importaciones)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-300 dark:border-slate-700">
                            <th class="text-left py-3 px-4 text-slate-600 dark:text-gray-400">ID Importación</th>
                            <th class="text-center py-3 px-4 text-slate-600 dark:text-gray-400">Tipo</th>
                            <th class="text-center py-3 px-4 text-slate-600 dark:text-gray-400">Total</th>
                            <th class="text-center py-3 px-4 text-slate-600 dark:text-gray-400">Exitosos</th>
                            <th class="text-center py-3 px-4 text-slate-600 dark:text-gray-400">Errores</th>
                            <th class="text-left py-3 px-4 text-slate-600 dark:text-gray-400">Pacientes</th>
                            <th class="text-left py-3 px-4 text-slate-600 dark:text-gray-400">Especialidades</th>
                            <th class="text-center py-3 px-4 text-slate-600 dark:text-gray-400">Estado</th>
                            <th class="text-left py-3 px-4 text-slate-600 dark:text-gray-400">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimas_importaciones as $imp): ?>
                        <tr class="border-b border-slate-200 dark:border-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700/30 transition">
                            <td class="py-3 px-4 font-mono text-blue-600 dark:text-blue-300 text-xs">
                                <a href="#detalles-<?php echo htmlspecialchars($imp['importacion_id']); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars(substr($imp['importacion_id'], 0, 15) . '...'); ?>
                                </a>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="bg-slate-200 dark:bg-slate-700 px-2 py-1 rounded text-xs text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($imp['tipo']); ?></span>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-gray-900 dark:text-white"><?php echo $imp['total_registros']; ?></td>
                            <td class="py-3 px-4 text-center text-green-600 dark:text-green-300 font-bold"><?php echo $imp['registros_exitosos']; ?></td>
                            <td class="py-3 px-4 text-center text-red-600 dark:text-red-300 font-bold"><?php echo $imp['registros_fallidos']; ?></td>
                            <td class="py-3 px-4 text-left text-xs">
                                <?php if (!empty($imp['pacientes'])): ?>
                                    <div class="text-slate-700 dark:text-gray-300 max-w-xs truncate" title="<?php echo htmlspecialchars($imp['pacientes']); ?>">
                                        <?php echo htmlspecialchars(implode('; ', array_slice(explode('; ', $imp['pacientes']), 0, 2))); ?>
                                        <?php if (substr_count($imp['pacientes'], ';') >= 1): ?><span class="text-amber-500 dark:text-amber-400">...</span><?php endif; ?>
                                    </div>
                                    <div class="text-slate-600 dark:text-gray-500 text-xs">RUNs: <?php echo htmlspecialchars(implode(', ', array_slice(explode(', ', $imp['runs']), 0, 2))); ?></div>
                                <?php else: ?>
                                    <span class="text-slate-600 dark:text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-left text-xs">
                                <?php if (!empty($imp['especialidades'])): ?>
                                    <div class="text-slate-700 dark:text-gray-300 max-w-xs truncate" title="<?php echo htmlspecialchars($imp['especialidades']); ?>">
                                        <?php echo htmlspecialchars(implode(', ', array_slice(explode(', ', $imp['especialidades']), 0, 2))); ?>
                                        <?php if (substr_count($imp['especialidades'], ',') >= 1): ?><span class="text-amber-500 dark:text-amber-400">...</span><?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-600 dark:text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="status-badge status-<?php echo htmlspecialchars($imp['estado']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $imp['estado'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-gray-400 text-xs">
                                <?php echo $imp['fecha_inicio'] ? date('d/m/Y H:i', strtotime($imp['fecha_inicio'])) : 'N/A'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-slate-600 dark:text-gray-400 py-4">No hay importaciones registradas aún</p>
            <?php endif; ?>
        </div>

        <!-- Conexión a API -->
        <div class="bg-white dark:bg-slate-800/50 backdrop-blur border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">🔌 Configuración de Conexión</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-blue-600 dark:text-blue-300 mb-3">Endpoint API</h3>
                    <div class="bg-slate-100 dark:bg-slate-900 rounded p-3 font-mono text-sm text-slate-700 dark:text-gray-300 break-all">
                        http://10.8.154.240/SIGLECH/api/v1/importar/json
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-600 dark:text-blue-300 mb-3">Token Bearer</h3>
                    <div class="bg-slate-100 dark:bg-slate-900 rounded p-3 font-mono text-sm text-slate-700 dark:text-gray-300 break-all">
                        552821b77ba50f33fe49c3046f6dea7a
                    </div>
                </div>
            </div>
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-500/30 rounded">
                <p class="text-blue-700 dark:text-blue-200 text-sm">
                    <strong>💡 Tip:</strong> Usa estos datos en tu script Python junto con siglech_client.py
                </p>
            </div>
        </div>

        <!-- Instrucciones de Integración -->
        <div class="bg-white dark:bg-slate-800/50 backdrop-blur border border-slate-200 dark:border-slate-700 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">📚 Instrucciones de Integración</h2>
            <div class="space-y-4">
                <div class="border-l-4 border-blue-500 pl-4">
                    <h3 class="font-semibold text-blue-600 dark:text-blue-300 mb-2">1. Preparar datos en formato JSON</h3>
                    <p class="text-slate-700 dark:text-gray-300 text-sm">Los registros deben incluir: _id, run, primer_apellido, segundo_apellido, nombres, especialidad, estab_orig, estab_dest, fecha_ingreso, estado, dias_espera, cie10</p>
                </div>
                <div class="border-l-4 border-green-500 pl-4">
                    <h3 class="font-semibold text-green-600 dark:text-green-300 mb-2">2. Enviar POST a /api/v1/importar/json</h3>
                    <p class="text-slate-700 dark:text-gray-300 text-sm">Con el token Bearer en el header Authorization</p>
                </div>
                <div class="border-l-4 border-amber-500 pl-4">
                    <h3 class="font-semibold text-amber-600 dark:text-amber-300 mb-2">3. Monitorear estado aquí</h3>
                    <p class="text-slate-700 dark:text-gray-300 text-sm">Los datos importados aparecerán en esta pestaña automáticamente</p>
                </div>
            </div>
        </div>
    </div>

<?php layoutFooter(); ?>
