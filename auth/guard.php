<?php
/**
 * guard.php - SIGLECH
 * Autenticación y autorización
 */

require_once __DIR__ . '/../db.php';

/**
 * Token anti-CSRF
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica token CSRF
 */
function verificarCsrf(): void {
    $enviado = (string)($_POST['csrf_token'] ?? '');
    $esperado = $_SESSION['csrf_token'] ?? '';

    if ($esperado === '' || !hash_equals($esperado, $enviado)) {
        http_response_code(403);
        die('Token de seguridad inválido o expirado. Recarga la página e intenta nuevamente.');
    }
}

/**
 * Inicia sesión
 */
function iniciarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerar ID de sesión cada hora (seguridad)
    if (!isset($_SESSION['ultimo_regenero'])) {
        $_SESSION['ultimo_regenero'] = time();
        session_regenerate_id(true);
    } elseif (time() - $_SESSION['ultimo_regenero'] > 3600) {
        $_SESSION['ultimo_regenero'] = time();
        session_regenerate_id(true);
    }
}

/**
 * Cierra sesión
 */
function cerrarSesion(): void {
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

/**
 * Requiere login (redirige a login si no autenticado)
 */
function requiereLogin(): array {
    iniciarSesion();

    if (empty($_SESSION['usuario_id'])) {
        header('Location: /SIGLECH//SIGLECH/login.php');
        exit;
    }

    $pdo = getConexion();

    // Obtener datos del usuario
    $stmt = $pdo->prepare("
        SELECT id, usuario, nombre, email, rol, activo
        FROM usuarios
        WHERE id = ? AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        cerrarSesion();
        header('Location: /SIGLECH//SIGLECH/login.php');
        exit;
    }

    return $usuario;
}

/**
 * Valida permisos de rol
 */
function validarRol(string $rol_requerido, array $usuario = null): bool {
    iniciarSesion();

    if ($usuario === null) {
        $usuario = requiereLogin();
    }

    return $usuario['rol'] === $rol_requerido || $usuario['rol'] === 'admin';
}

/**
 * Redirige si no tiene permisos
 */
function requiereRol(string $rol_requerido): void {
    $usuario = requiereLogin();

    if (!validarRol($rol_requerido, $usuario)) {
        http_response_code(403);
        die('No tienes permisos para acceder a este recurso.');
    }
}

/**
 * Obtiene usuario actual
 */
function usuarioActual(): ?array {
    iniciarSesion();

    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    $pdo = getConexion();
    $stmt = $pdo->prepare("
        SELECT id, usuario, nombre, email, rol
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Login de usuario (retorna array [success, usuario])
 */
function login(string $usuario, string $password): array {
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
        return [false, null, 'Usuario o contraseña incorrectos'];
    }

    iniciarSesion();
    $_SESSION['usuario_id'] = $user['id'];

    // Registrar último acceso
    $updateStmt = $pdo->prepare("
        UPDATE usuarios
        SET ultimo_acceso = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$user['id']]);

    return [true, $user, 'Login exitoso'];
}

/**
 * Hash de contraseña (crear nueva)
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 2048,
        'time_cost' => 4,
        'threads' => 3,
    ]);
}

