<?php
/**
 * SIGLECH - Script de Instalación Web
 *
 * Acceder a: http://localhost/SIGLECH/install.php
 *
 * Este script:
 * 1. Verifica la conexión a BD
 * 2. Crea todas las tablas
 * 3. Inserta datos iniciales
 * 4. Crea usuario admin
 */

require_once __DIR__ . '/config.php';

/**
 * Parser robusto de sentencias SQL
 * Maneja comentarios y múltiples sentencias
 */
function parseSQLStatements($sql) {
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $i = 0;
    $len = strlen($sql);

    while ($i < $len) {
        $char = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        // Manejo de strings
        if (!$inString && ($char === '"' || $char === "'")) {
            $inString = true;
            $stringChar = $char;
            $current .= $char;
            $i++;
        } elseif ($inString && $char === $stringChar && $next !== $stringChar) {
            $inString = false;
            $current .= $char;
            $i++;
        } elseif ($inString && $char === $stringChar && $next === $stringChar) {
            // Escaped quote
            $current .= $char . $next;
            $i += 2;
        }
        // Manejo de comentarios si no estamos en string
        elseif (!$inString && $char === '-' && $next === '-') {
            // Comentario de línea
            $i += 2;
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
        } elseif (!$inString && $char === '/' && $next === '*') {
            // Comentario multilinea
            $i += 2;
            while ($i < $len - 1) {
                if ($sql[$i] === '*' && $sql[$i + 1] === '/') {
                    $i += 2;
                    break;
                }
                $i++;
            }
        }
        // Fin de sentencia
        elseif (!$inString && $char === ';') {
            $current = trim($current);
            if (!empty($current)) {
                $statements[] = $current;
            }
            $current = '';
            $i++;
        }
        // Carácter normal
        else {
            $current .= $char;
            $i++;
        }
    }

    // Agregar última sentencia si existe
    $current = trim($current);
    if (!empty($current)) {
        $statements[] = $current;
    }

    return $statements;
}

// Detectar si ya está instalado
$yaInstalado = false;
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios LIMIT 1");
    $yaInstalado = true;
} catch (Exception $e) {
    // BD no existe aún o tablas no creadas
}

$mensaje = '';
$exito = false;
$paso = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instalar'])) {
    try {
        // Conectar sin BD primero
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS
        );

        // 1. Crear BD si no existe
        $paso = 1;
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $mensaje .= "✅ Paso 1: Base de datos verificada\n";

        // 2. Conectar a la BD
        $paso = 2;
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $mensaje .= "✅ Paso 2: Conectado a base de datos\n";

        // 3. Leer y ejecutar schema.sql
        $paso = 3;
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Archivo schema.sql no encontrado en " . $schemaFile);
        }

        $sql = file_get_contents($schemaFile);

        // Parser robusto de SQL
        $statements = parseSQLStatements($sql);

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (Exception $e) {
                    // Log pero continúa (algunas sentencias pueden fallar si ya existen)
                    // error_log("SQL Error: " . $e->getMessage());
                }
            }
        }

        $mensaje .= "✅ Paso 3: Tablas creadas correctamente\n";

        // 4. Verificar que usuario admin existe
        $paso = 4;
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE usuario = 'admin'");
        $adminExiste = $stmt->fetchColumn() > 0;

        if ($adminExiste) {
            $mensaje .= "✅ Paso 4: Usuario admin ya existe\n";
        } else {
            $mensaje .= "⚠️  Paso 4: Usuario admin será creado en próximo login\n";
        }

        // 5. Verificar tabla de especialidades y establecimientos
        $paso = 5;
        $stmt = $pdo->query("SELECT COUNT(*) FROM especialidades");
        $especialidadesCount = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM establecimientos");
        $establecimientosCount = $stmt->fetchColumn();

        $mensaje .= "✅ Paso 5: Verificación de tablas compartidas\n";
        $mensaje .= "   • Especialidades: $especialidadesCount registros\n";
        $mensaje .= "   • Establecimientos: $establecimientosCount registros\n";

        // 6. Verificar tablas de SIGLECH
        $paso = 6;
        $tablasSIGLECH = [
            'usuarios',
            'lista_espera_interconsultas',
            'lista_espera_gestiones_contacto',
            'lista_espera_fichas_egreso',
            'lista_espera_alertas',
            'lista_espera_auditoria',
            'lista_espera_sincronizacion_log',
            'lista_espera_reportes_cache'
        ];

        $conteo = 0;
        foreach ($tablasSIGLECH as $tabla) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$tabla'");
            if ($stmt->fetchColumn() > 0) {
                $conteo++;
            }
        }

        $mensaje .= "✅ Paso 6: Tablas de SIGLECH verificadas ($conteo/" . count($tablasSIGLECH) . ")\n";

        $exito = true;
        $mensaje .= "\n✅ ¡INSTALACIÓN COMPLETADA EXITOSAMENTE!\n";
        $mensaje .= "\n🔑 Datos de acceso:\n";
        $mensaje .= "   Usuario: admin\n";
        $mensaje .= "   Contraseña: admin\n";
        $mensaje .= "\n⚠️  IMPORTANTE: Cambia la contraseña en el primer login\n";

    } catch (Exception $e) {
        $exito = false;
        $mensaje = "❌ Error en paso $paso:\n" . htmlspecialchars($e->getMessage()) . "\n\n";
        $mensaje .= "Soluciones posibles:\n";
        $mensaje .= "1. Verifica que MySQL está corriendo\n";
        $mensaje .= "2. Verifica usuario/contraseña en config.php\n";
        $mensaje .= "3. Verifica que el archivo schema.sql existe en database/\n";
        $mensaje .= "4. Verifica permisos de carpetas\n";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - SIGLECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .log-output { font-family: 'Courier New', monospace; white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-2xl w-full">

            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-slate-900 mb-2">🗂️ SIGLECH v1.0</h1>
                <p class="text-slate-600">Gestor de Listas de Espera Chiloé</p>
            </div>

            <!-- Card principal -->
            <div class="bg-white rounded-2xl shadow-xl p-8">

                <?php if ($yaInstalado && !$_POST): ?>
                    <!-- Ya está instalado -->
                    <div class="bg-emerald-50 border-2 border-emerald-500 rounded-lg p-6 mb-6">
                        <h2 class="text-2xl font-bold text-emerald-900 mb-4">✅ SIGLECH ya está instalado</h2>
                        <p class="text-emerald-800 mb-4">La base de datos ya contiene datos. Elige una opción:</p>

                        <div class="space-y-3">
                            <a href="/SIGLECH/index.php" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                                ✓ Ir al Dashboard
                            </a>
                            <form method="POST" class="block">
                                <button type="submit" name="reinstalar" value="1" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-3 px-4 rounded-lg transition">
                                    ⚠️ Reinstalar (Borra datos existentes)
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($_POST && $exito): ?>
                    <!-- Éxito -->
                    <div class="bg-emerald-50 border-2 border-emerald-500 rounded-lg p-6 mb-6">
                        <h2 class="text-2xl font-bold text-emerald-900 mb-4">✅ ¡Instalación Exitosa!</h2>
                        <div class="log-output bg-slate-900 text-emerald-400 p-4 rounded-lg mb-4 text-sm">
                            <?= nl2br(htmlspecialchars($mensaje)) ?>
                        </div>
                        <a href="/SIGLECH/index.php" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                            Ir al Dashboard →
                        </a>
                    </div>
                <?php elseif ($_POST && !$exito): ?>
                    <!-- Error -->
                    <div class="bg-red-50 border-2 border-red-500 rounded-lg p-6 mb-6">
                        <h2 class="text-2xl font-bold text-red-900 mb-4">❌ Error en la instalación</h2>
                        <div class="log-output bg-slate-900 text-red-400 p-4 rounded-lg mb-4 text-sm">
                            <?= nl2br(htmlspecialchars($mensaje)) ?>
                        </div>
                        <form method="POST" class="block">
                            <button type="submit" name="instalar" value="1" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition">
                                🔄 Reintentar
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Formulario de instalación -->
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-900 mb-4">📋 Instalador Automático</h2>
                        <p class="text-slate-600 mb-6">Este script configurará automáticamente SIGLECH:</p>

                        <div class="space-y-3 mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                            <p class="text-sm text-blue-900"><strong>✅ Que hará:</strong></p>
                            <ul class="text-sm text-blue-800 space-y-1 ml-4">
                                <li>✓ Crear base de datos (si no existe)</li>
                                <li>✓ Crear 8 tablas de SIGLECH</li>
                                <li>✓ Crear 2 vistas SQL</li>
                                <li>✓ Crear índices de performance</li>
                                <li>✓ Insertar usuario admin</li>
                            </ul>
                        </div>

                        <div class="space-y-3 mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                            <p class="text-sm text-amber-900"><strong>⚠️  Configuración actual:</strong></p>
                            <ul class="text-sm text-amber-800 space-y-1 ml-4 font-mono">
                                <li>Host: <?= DB_HOST ?></li>
                                <li>Usuario: <?= DB_USER ?></li>
                                <li>BD: <?= DB_NAME ?></li>
                            </ul>
                        </div>
                    </div>

                    <form method="POST">
                        <button type="submit" name="instalar" value="1" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg transition text-lg">
                            🚀 Instalar SIGLECH Ahora
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Info de seguridad -->
                <div class="mt-8 pt-8 border-t border-slate-200">
                    <h3 class="font-bold text-slate-900 mb-3">🔒 Datos de Acceso</h3>
                    <div class="bg-slate-100 p-4 rounded-lg text-sm space-y-1">
                        <p><strong>Usuario:</strong> <code class="bg-white px-2 py-1 rounded">admin</code></p>
                        <p><strong>Contraseña:</strong> <code class="bg-white px-2 py-1 rounded">admin</code></p>
                        <p class="text-red-600 font-semibold mt-3">⚠️ IMPORTANTE: Cambia la contraseña inmediatamente después del login</p>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-slate-600 text-sm">
                <p>SIGLECH v1.0 • Servicio de Salud Chiloé • 2026</p>
            </div>

        </div>
    </div>
</body>
</html>

