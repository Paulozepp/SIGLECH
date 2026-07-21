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

<div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-6 border border-slate-200 dark:border-slate-700 max-w-2xl">
    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Cargar CSV incremental</h3>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">
        Solo se insertan las filas nuevas (identificadas por su columna <code class="bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">_id</code>).
        Las filas ya cargadas anteriormente se omiten automáticamente, por lo que puedes volver a subir el mismo archivo o una versión actualizada sin generar duplicados.
    </p>

    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

        <div>
            <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Tipo de lista</label>
            <select name="tipo" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                <?php foreach (DEMANDA_LE_TABLAS as $codigo => $t): ?>
                    <option value="<?= htmlspecialchars($codigo) ?>"><?= $t['icono'] ?> <?= htmlspecialchars($t['label']) ?> — <?= htmlspecialchars($t['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Archivo CSV</label>
            <input type="file" name="csv" accept=".csv" required
                   class="w-full text-sm text-slate-600 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-600 file:text-white hover:file:bg-brand-700">
        </div>

        <button type="submit" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-medium transition">
            📤 Procesar carga
        </button>
    </form>
</div>

<?php layoutFooter(); ?>

