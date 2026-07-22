<?php
/**
 * Carga incremental de CSV (CNE / IQ / PROC) - SIGLECH
 * Inserta únicamente filas nuevas (según _id) en la tabla demanda_* correspondiente.
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../partials/layout.php';
require_once __DIR__ . '/_config.php';

$user = requiereLogin();

$resultado = null;
$error     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $tipo = strtolower((string)($_POST['tipo'] ?? ''));
    $info = demandaLeTabla($tipo);

    if ($info === null) {
        $error = 'Tipo de lista inválido.';
    } elseif (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $codigosError = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo permitido por el formulario.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente. Intenta nuevamente.',
            UPLOAD_ERR_NO_FILE    => 'Debes seleccionar un archivo CSV.',
        ];
        $codigo = $_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = $codigosError[$codigo] ?? 'Error al subir el archivo (código ' . $codigo . ').';
    } else {
        $resultado = demandaLeProcesarCsv($info['tabla'], $_FILES['csv']['tmp_name']);
        if ($resultado === null) {
            $error = 'No se pudo leer el archivo CSV o el encabezado no contiene las columnas esperadas.';
        }
    }
}

/**
 * Procesa el CSV subido e inserta filas nuevas (idempotente vía UNIQUE _id).
 * Retorna un resumen ['leidas'=>, 'nuevas'=>, 'duplicadas'=>, 'errores'=>] o null si falla la lectura.
 */
function demandaLeProcesarCsv(string $tabla, string $rutaTmp): ?array {
    $fh = fopen($rutaTmp, 'r');
    if ($fh === false) {
        return null;
    }

    // Detecta y descarta BOM UTF-8
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($fh);
    }

    // Detecta delimitador a partir de la primera línea
    $primeraLinea = fgets($fh);
    if ($primeraLinea === false) {
        fclose($fh);
        return null;
    }
    $delimitador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';
    $encabezado  = str_getcsv($primeraLinea, $delimitador);
    $encabezado  = array_map('trim', $encabezado);

    $columnasRequeridas = DEMANDA_LE_COLUMNAS;
    $faltantes = array_diff($columnasRequeridas, $encabezado);
    if (in_array('_id', $faltantes, true) || in_array('RUN', $faltantes, true)) {
        fclose($fh);
        return null;
    }

    $indice = array_flip($encabezado);

    $sql = 'INSERT INTO ' . $tabla . ' (' . implode(', ', $columnasRequeridas) . ')
            VALUES (' . implode(', ', array_fill(0, count($columnasRequeridas), '?')) . ')
            ON DUPLICATE KEY UPDATE _id = _id';

    $pdo  = getConexionSiglech();
    $stmt = $pdo->prepare($sql);

    $leidas = 0;
    $nuevas = 0;
    $duplicadas = 0;
    $errores = 0;
    $enLote = 0;
    $loteSize = 500;

    $pdo->beginTransaction();

    while (($fila = fgetcsv($fh, 0, $delimitador)) !== false) {
        if ($fila === [null] || $fila === false) {
            continue;
        }
        $leidas++;

        $valores = [];
        foreach ($columnasRequeridas as $col) {
            $pos = $indice[$col] ?? null;
            $crudo = ($pos !== null && isset($fila[$pos])) ? $fila[$pos] : null;
            $valores[] = demandaLeConvertirValor($col, $crudo);
        }

        try {
            $stmt->execute($valores);
            if ($stmt->rowCount() > 0) {
                $nuevas++;
            } else {
                $duplicadas++;
            }
        } catch (PDOException $e) {
            $errores++;
        }

        $enLote++;
        if ($enLote >= $loteSize) {
            $pdo->commit();
            $pdo->beginTransaction();
            $enLote = 0;
        }
    }

    $pdo->commit();
    fclose($fh);

    return [
        'leidas'     => $leidas,
        'nuevas'     => $nuevas,
        'duplicadas' => $duplicadas,
        'errores'    => $errores,
    ];
}

layoutHeader('Demanda LE - Cargar CSV', $user, 'demanda_le');
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">📥 Demanda Listas de Espera</h2>
    <p class="text-slate-600 dark:text-slate-400">Datos importados desde CNE, IQ y PROC (MINSAL)</p>
</div>

<!-- Tabs -->
<div class="flex gap-2 mb-6 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
    <?php foreach (DEMANDA_LE_TABLAS as $codigo => $t): ?>
        <a href="/SIGLECH/index.php?tipo=<?= htmlspecialchars($codigo) ?>" class="px-4 py-3 text-sm font-medium whitespace-nowrap text-slate-600 dark:text-slate-400 hover:text-brand-600">
            <?= $t['icono'] ?> <?= htmlspecialchars($t['label']) ?>
        </a>
    <?php endforeach; ?>
    <a href="cargar.php" class="px-4 py-3 text-sm font-medium whitespace-nowrap text-brand-600 border-b-2 border-brand-600">
        📤 Cargar CSV
    </a>
</div>

<?php if ($error): ?>
<div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg px-4 py-3 mb-6">
    ❌ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($resultado): ?>
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 mb-8">
    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">✅ Carga completada</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Filas leídas</p>
            <p class="text-2xl font-bold text-slate-900 dark:text-white"><?= number_format($resultado['leidas'], 0, ',', '.') ?></p>
        </div>
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Nuevas insertadas</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400"><?= number_format($resultado['nuevas'], 0, ',', '.') ?></p>
        </div>
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Ya existentes (omitidas)</p>
            <p class="text-2xl font-bold text-slate-500 dark:text-slate-400"><?= number_format($resultado['duplicadas'], 0, ',', '.') ?></p>
        </div>
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Errores</p>
            <p class="text-2xl font-bold <?= $resultado['errores'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400' ?>"><?= number_format($resultado['errores'], 0, ',', '.') ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Instrucciones -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">📋 Cargar CSV incremental</h3>

        <div class="mb-6">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">¿Cómo funciona?</h4>
            <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-2">
                <li class="flex gap-2">
                    <span class="text-brand-600 font-bold min-w-5">1.</span>
                    <span>Descarga la plantilla CSV para el tipo de lista que necesitas (CNE, IQ o PROC)</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-brand-600 font-bold min-w-5">2.</span>
                    <span>Completa los datos. La columna <code class="bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded text-xs">_id</code> es el identificador único</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-brand-600 font-bold min-w-5">3.</span>
                    <span>Sube el archivo aquí. Solo se insertan filas con <code class="bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded text-xs">_id</code> nuevas</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-brand-600 font-bold min-w-5">4.</span>
                    <span>Las filas duplicadas se omiten automáticamente (sin generar errores)</span>
                </li>
            </ul>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
            <p class="text-sm text-blue-900 dark:text-blue-100">
                <strong>💡 Consejo:</strong> Puedes subir el mismo archivo varias veces sin riesgo de duplicados. Solo se procesarán los registros nuevos.
            </p>
        </div>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Tipo de lista *</label>
                <select name="tipo" required id="tipoSelect" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                    <?php foreach (DEMANDA_LE_TABLAS as $codigo => $t): ?>
                        <option value="<?= htmlspecialchars($codigo) ?>"><?= $t['icono'] ?> <?= htmlspecialchars($t['label']) ?> — <?= htmlspecialchars($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Archivo CSV *</label>
                <input type="file" name="csv" accept=".csv" required
                       class="w-full text-sm text-slate-600 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-600 file:text-white hover:file:bg-brand-700">
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                    Formato: CSV con separador por coma o punto y coma. Máximo 100 MB.
                </p>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-medium transition">
                    📤 Procesar carga
                </button>
            </div>
        </form>
    </div>

    <!-- Panel de Descargas -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">📥 Descargar Plantillas</h3>

        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">
            Descarga la plantilla para tu tipo de lista. Incluye dos ejemplos para referencia.
        </p>

        <div class="space-y-3">
            <?php foreach (DEMANDA_LE_TABLAS as $codigo => $t): ?>
                <a href="descargar-template.php?tipo=<?= htmlspecialchars($codigo) ?>"
                   class="flex items-center gap-3 px-4 py-3 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-lg transition border border-slate-200 dark:border-slate-600">
                    <span class="text-xl"><?= $t['icono'] ?></span>
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white text-sm"><?= htmlspecialchars($t['label']) ?></p>
                        <p class="text-xs text-slate-600 dark:text-slate-400"><?= htmlspecialchars($t['nombre']) ?></p>
                    </div>
                    <span class="ml-auto text-slate-400 dark:text-slate-500">⬇️</span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">📊 Columnas Requeridas</h4>
            <p class="text-xs text-slate-600 dark:text-slate-400 mb-3">
                Total de columnas: <strong><?= count(DEMANDA_LE_COLUMNAS) ?></strong>
            </p>
            <div class="bg-slate-50 dark:bg-slate-900/30 rounded-lg p-3 max-h-40 overflow-y-auto">
                <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <?php foreach (DEMANDA_LE_COLUMNAS as $col): ?>
                        <li class="font-mono">• <?= htmlspecialchars($col) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                <a href="README_TEMPLATE.md" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-lg text-xs font-medium text-slate-900 dark:text-white transition">
                    📖 Ver documentación completa
                    <span class="text-slate-400">↗</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php layoutFooter(); ?>

