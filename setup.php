<?php
/**
 * SIGLECH - Setup Avanzado
 *
 * Script de configuración completo con:
 * - Verificación de requisitos
 * - Test de conexión
 * - Instalación paso a paso
 * - Restauración de backups
 *
 * Acceder a: http://localhost/SIGLECH/setup.php
 */

header('Content-Type: text/html; charset=utf-8');

$paso = isset($_GET['paso']) ? $_GET['paso'] : 'bienvenida';
$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - SIGLECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .code { font-family: 'Courier New', monospace; }
        .step { position: relative; }
        .step.completed { opacity: 0.6; }
        .step.active { border-left: 4px solid #3b82f6; }
    </style>
</head>
<body class="bg-slate-900">
    <div class="min-h-screen py-12 px-4">
        <div class="max-w-4xl mx-auto">

            <!-- Header -->
            <div class="mb-12">
                <h1 class="text-4xl font-bold text-white mb-2">🗂️ SIGLECH Setup</h1>
                <p class="text-slate-400">Configuración y instalación de SIGLECH</p>
            </div>

            <!-- Progress -->
            <div class="mb-12 bg-slate-800 rounded-lg p-6">
                <h2 class="text-white font-bold mb-4">Pasos de instalación</h2>
                <div class="space-y-2">
                    <div class="step <?= $paso === 'requisitos' ? 'active' : 'completed' ?> flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center mr-3">1</div>
                        <a href="setup.php?paso=requisitos" class="text-slate-300 hover:text-white">
                            Verificar Requisitos
                        </a>
                    </div>
                    <div class="step <?= $paso === 'conexion' ? 'active' : 'completed' ?> flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center mr-3">2</div>
                        <a href="setup.php?paso=conexion" class="text-slate-300 hover:text-white">
                            Probar Conexión a BD
                        </a>
                    </div>
                    <div class="step <?= $paso === 'instalacion' ? 'active' : 'completed' ?> flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center mr-3">3</div>
                        <a href="setup.php?paso=instalacion" class="text-slate-300 hover:text-white">
                            Ejecutar Instalación
                        </a>
                    </div>
                    <div class="step <?= $paso === 'finalizacion' ? 'active' : 'completed' ?> flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center mr-3">4</div>
                        <a href="setup.php?paso=finalizacion" class="text-slate-300 hover:text-white">
                            Finalización
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contenido por paso -->
            <div class="bg-slate-800 rounded-lg p-8 text-white">

                <?php
                // ============================================================================
                // PASO 1: Verificar Requisitos
                // ============================================================================
                if ($paso === 'requisitos'):
                ?>
                    <h2 class="text-2xl font-bold mb-6">✓ Verificar Requisitos</h2>

                    <div class="space-y-4">
                        <!-- PHP Version -->
                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-2">PHP Version</h3>
                            <?php
                            $phpVersion = phpversion();
                            $ok = version_compare($phpVersion, '7.4', '>=');
                            ?>
                            <p class="text-sm">
                                <span class="<?= $ok ? 'text-green-400' : 'text-red-400' ?>">
                                    <?= $ok ? '✓' : '✗' ?>
                                </span>
                                <?= $phpVersion ?>
                                <span class="text-slate-400">
                                    (Requerido: 7.4+)
                                </span>
                            </p>
                        </div>

                        <!-- Extensions -->
                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-2">Extensiones PHP</h3>
                            <?php
                            $extensions = [
                                'PDO' => extension_loaded('pdo'),
                                'PDO MySQL' => extension_loaded('pdo_mysql'),
                                'JSON' => extension_loaded('json'),
                                'Fileinfo' => extension_loaded('fileinfo'),
                            ];
                            ?>
                            <div class="space-y-1 text-sm">
                                <?php foreach ($extensions as $name => $loaded): ?>
                                    <p>
                                        <span class="<?= $loaded ? 'text-green-400' : 'text-red-400' ?>">
                                            <?= $loaded ? '✓' : '✗' ?>
                                        </span>
                                        <?= $name ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Permisos -->
                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-2">Permisos de Archivos</h3>
                            <?php
                            $dirs = [
                                'logs' => __DIR__ . '/logs',
                                'cache' => __DIR__ . '/cache',
                                'database' => __DIR__ . '/database',
                            ];
                            $allWritable = true;
                            ?>
                            <div class="space-y-1 text-sm">
                                <?php foreach ($dirs as $name => $path): ?>
                                    <?php
                                    $writable = is_writable($path);
                                    $allWritable = $allWritable && $writable;
                                    ?>
                                    <p>
                                        <span class="<?= $writable ? 'text-green-400' : 'text-red-400' ?>">
                                            <?= $writable ? '✓' : '✗' ?>
                                        </span>
                                        <?= $name ?> (<?= substr($path, -20) ?>)
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- config.php -->
                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-2">Configuración</h3>
                            <?php
                            $configOk = file_exists(__DIR__ . '/config.php');
                            ?>
                            <p class="text-sm">
                                <span class="<?= $configOk ? 'text-green-400' : 'text-red-400' ?>">
                                    <?= $configOk ? '✓' : '✗' ?>
                                </span>
                                config.php
                            </p>
                            <?php if ($configOk): ?>
                                <p class="text-xs text-slate-400 mt-2">
                                    ✓ Archivo de configuración encontrado
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="mt-6 flex gap-2">
                            <a href="setup.php?paso=conexion" class="flex-1 bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-center transition">
                                Siguiente →
                            </a>
                        </div>
                    </div>

                <?php
                // ============================================================================
                // PASO 2: Probar Conexión
                // ============================================================================
                elseif ($paso === 'conexion'):
                    require_once __DIR__ . '/config.php';
                ?>
                    <h2 class="text-2xl font-bold mb-6">🔗 Probar Conexión a BD</h2>

                    <div class="space-y-4">
                        <?php
                        $conectado = false;
                        $error = null;

                        try {
                            $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
                            $pdo = new PDO($dsn, DB_USER, DB_PASS);
                            $conectado = true;
                        } catch (PDOException $e) {
                            $error = $e->getMessage();
                        }
                        ?>

                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-2">Configuración Actual</h3>
                            <div class="space-y-1 text-sm code text-slate-300">
                                <p>Host: <span class="text-blue-400"><?= DB_HOST ?></span></p>
                                <p>Usuario: <span class="text-blue-400"><?= DB_USER ?></span></p>
                                <p>BD: <span class="text-blue-400"><?= DB_NAME ?></span></p>
                            </div>
                        </div>

                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-2">Estado de Conexión</h3>
                            <?php if ($conectado): ?>
                                <p class="text-green-400 flex items-center">
                                    <span class="mr-2">✓</span>
                                    Conectado a MySQL exitosamente
                                </p>
                            <?php else: ?>
                                <p class="text-red-400 flex items-center">
                                    <span class="mr-2">✗</span>
                                    No se pudo conectar a MySQL
                                </p>
                                <p class="text-sm text-red-300 mt-2">
                                    <?= htmlspecialchars($error) ?>
                                </p>
                                <p class="text-sm text-slate-400 mt-2">
                                    Verifica que:
                                    <br>• MySQL está corriendo (XAMPP)
                                    <br>• Usuario/contraseña son correctos
                                    <br>• Host es accesible
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="mt-6 flex gap-2">
                            <a href="setup.php?paso=requisitos" class="flex-1 bg-slate-600 hover:bg-slate-700 px-4 py-2 rounded text-center transition">
                                ← Atrás
                            </a>
                            <a href="setup.php?paso=instalacion" class="flex-1 bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-center transition <?= !$conectado ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$conectado ? 'onclick="return false"' : '' ?>>
                                Siguiente →
                            </a>
                        </div>
                    </div>

                <?php
                // ============================================================================
                // PASO 3: Instalación
                // ============================================================================
                elseif ($paso === 'instalacion'):
                    require_once __DIR__ . '/config.php';
                ?>
                    <h2 class="text-2xl font-bold mb-6">⚙️ Ejecutar Instalación</h2>

                    <div class="space-y-4">
                        <div class="bg-blue-900 border border-blue-700 p-4 rounded">
                            <p class="text-blue-200">
                                <span class="text-blue-400">ℹ️</span>
                                Este paso creará todas las tablas necesarias para SIGLECH.
                                Los datos existentes serán preservados.
                            </p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="accion" value="instalar">
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 px-4 py-3 rounded font-bold transition">
                                🚀 Instalar Ahora
                            </button>
                        </form>

                        <?php
                        if ($accion === 'instalar'):
                            try {
                                $pdo = new PDO(
                                    'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
                                    DB_USER,
                                    DB_PASS
                                );

                                // Crear BD
                                $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                                // Conectar a la BD
                                $pdo = new PDO(
                                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                                    DB_USER,
                                    DB_PASS
                                );
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                // Ejecutar schema
                                $schema = file_get_contents(__DIR__ . '/database/schema.sql');
                                $statements = array_filter(
                                    array_map('trim', explode(';', $schema)),
                                    function($s) { return !empty($s) && !preg_match('/^--/', $s); }
                                );

                                foreach ($statements as $statement) {
                                    $pdo->exec($statement);
                                }

                                ?>
                                <div class="bg-green-900 border border-green-700 p-4 rounded mt-4">
                                    <p class="text-green-200">
                                        <span class="text-green-400">✓</span>
                                        Instalación completada exitosamente
                                    </p>
                                </div>

                                <div class="mt-4">
                                    <a href="setup.php?paso=finalizacion" class="block w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-center transition">
                                        Siguiente →
                                    </a>
                                </div>
                                <?php
                            } catch (Exception $e) {
                                ?>
                                <div class="bg-red-900 border border-red-700 p-4 rounded mt-4">
                                    <p class="text-red-200">
                                        <span class="text-red-400">✗</span>
                                        Error: <?= htmlspecialchars($e->getMessage()) ?>
                                    </p>
                                </div>
                                <?php
                            }
                        endif;
                        ?>

                        <div class="mt-6 flex gap-2">
                            <a href="setup.php?paso=conexion" class="flex-1 bg-slate-600 hover:bg-slate-700 px-4 py-2 rounded text-center transition">
                                ← Atrás
                            </a>
                        </div>
                    </div>

                <?php
                // ============================================================================
                // PASO 4: Finalización
                // ============================================================================
                elseif ($paso === 'finalizacion'):
                ?>
                    <h2 class="text-2xl font-bold mb-6">✅ Finalización</h2>

                    <div class="space-y-4">
                        <div class="bg-green-900 border border-green-700 p-6 rounded">
                            <h3 class="text-green-200 font-bold text-lg mb-3">¡Instalación Completada!</h3>
                            <p class="text-green-200 text-sm">
                                SIGLECH se ha configurado correctamente y está listo para usar.
                            </p>
                        </div>

                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-3">📝 Datos de Acceso</h3>
                            <div class="space-y-2 text-sm">
                                <p>
                                    <span class="text-slate-400">Usuario:</span>
                                    <span class="code text-blue-400">admin</span>
                                </p>
                                <p>
                                    <span class="text-slate-400">Contraseña:</span>
                                    <span class="code text-blue-400">admin</span>
                                </p>
                                <p class="text-red-400 mt-3">
                                    ⚠️ Cambia la contraseña inmediatamente
                                </p>
                            </div>
                        </div>

                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-3">🚀 Próximos Pasos</h3>
                            <ol class="text-sm space-y-2 list-decimal list-inside">
                                <li>Accede a SIGLECH</li>
                                <li>Cambia la contraseña del usuario admin</li>
                                <li>Verifica la conexión con SICOCH</li>
                                <li>Crea tu primer usuario</li>
                            </ol>
                        </div>

                        <div class="mt-6">
                            <a href="/SIGLECH/index.php" class="block w-full bg-green-600 hover:bg-green-700 px-4 py-3 rounded text-center font-bold transition">
                                → Ir al Dashboard
                            </a>
                        </div>
                    </div>

                <?php
                // ============================================================================
                // Página de bienvenida
                // ============================================================================
                else:
                ?>
                    <h2 class="text-2xl font-bold mb-6">👋 Bienvenido a SIGLECH Setup</h2>

                    <div class="space-y-4">
                        <div class="bg-blue-900 border border-blue-700 p-6 rounded">
                            <h3 class="text-blue-200 font-bold mb-2">¿Qué es esto?</h3>
                            <p class="text-blue-200 text-sm">
                                Este asistente te guiará a través de la instalación y configuración de SIGLECH.
                                Solo necesitas seguir los pasos y hacer clic en los botones.
                            </p>
                        </div>

                        <div class="bg-slate-700 p-4 rounded">
                            <h3 class="font-bold mb-3">📋 Requerimientos</h3>
                            <ul class="text-sm space-y-2 text-slate-300">
                                <li>✓ PHP 7.4 o superior</li>
                                <li>✓ MySQL 5.7 o superior</li>
                                <li>✓ Extensiones: PDO, PDO-MySQL, JSON</li>
                                <li>✓ Permisos de escritura en carpetas</li>
                            </ul>
                        </div>

                        <div class="mt-6">
                            <a href="setup.php?paso=requisitos" class="block w-full bg-blue-600 hover:bg-blue-700 px-4 py-3 rounded text-center font-bold transition">
                                Comenzar →
                            </a>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-slate-500 text-sm">
                <p>SIGLECH v1.0 • Servicio de Salud Chiloé • 2026</p>
            </div>

        </div>
    </div>
</body>
</html>

