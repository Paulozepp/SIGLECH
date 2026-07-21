<?php
/**
 * /SIGLECH/login.php - Página de Login
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Si ya está autenticado, redirigir al dashboard
if (!empty($_SESSION['usuario_id'])) {
    header('Location: /SIGLECH//SIGLECH/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($usuario) || empty($password)) {
        $error = 'Usuario y contraseña son requeridos';
    } else {
        try {
            $pdo = getConexion();

            $stmt = $pdo->prepare("
                SELECT id, usuario, nombre, email, rol, password_hash, activo
                FROM usuarios
                WHERE usuario = ? AND activo = 1
                LIMIT 1
            ");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Usuario o contraseña incorrectos';
            } else {
                // Login exitoso
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario'] = $user['usuario'];

                // Registrar último acceso
                $updateStmt = $pdo->prepare("
                    UPDATE usuarios
                    SET ultimo_acceso = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$user['id']]);

                header('Location: /SIGLECH/index.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Error de conexión: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIGLECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full">

            <!-- Card de Login -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-8">

                <!-- Logo -->
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-slate-900 dark:text-white mb-2">
                        🗂️ SIGLECH
                    </h1>
                    <p class="text-slate-600 dark:text-slate-400">
                        Gestión de Listas de Espera
                    </p>
                </div>

                <!-- Formulario -->
                <form method="POST" class="space-y-4">
                    <!-- Usuario -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Usuario
                        </label>
                        <input
                            type="text"
                            name="usuario"
                            required
                            placeholder="admin"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none"
                        >
                    </div>

                    <!-- Contraseña -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Contraseña
                        </label>
                        <input
                            type="password"
                            name="password"
                            required
                            placeholder="••••••••"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none"
                        >
                    </div>

                    <!-- Error -->
                    <?php if ($error): ?>
                        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg text-sm">
                            ❌ <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Botón de Login -->
                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-bold py-2 px-4 rounded-lg transition"
                    >
                        Iniciar Sesión
                    </button>
                </form>

                <!-- Info de Demo -->
                <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                    <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-3">
                        Credenciales de demostración:
                    </p>
                    <div class="bg-slate-100 dark:bg-slate-700 p-3 rounded-lg text-xs text-slate-700 dark:text-slate-300 font-mono">
                        <p><strong>Usuario:</strong> admin</p>
                        <p><strong>Contraseña:</strong> admin</p>
                    </div>
                    <p class="text-xs text-red-600 dark:text-red-400 mt-3 text-center">
                        ⚠️ Cambia la contraseña después del primer login
                    </p>
                </div>

            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-sm text-slate-600 dark:text-slate-400">
                <p>SIGLECH v1.0 • © 2026 Servicio de Salud Chiloé</p>
            </div>

        </div>
    </div>
</body>
</html>

