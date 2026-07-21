<?php
/**
 * Middleware de Autenticación por Token API
 * Valida Bearer tokens contra tabla api_clientes
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

/**
 * Verifica token API Bearer y retorna cliente autenticado
 * Formato: Authorization: Bearer {token}
 */
function verificarTokenAPI(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!preg_match('/Bearer\s+([a-f0-9]+)/', $header, $matches)) {
        http_response_code(401);
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'error' => 'Token requerido',
            'mensaje' => 'Incluir header: Authorization: Bearer {token}'
        ]));
    }

    $token = $matches[1];
    $hash = hash('sha256', $token);

    try {
        $pdo = getConexion();
        $stmt = $pdo->prepare("
            SELECT id, nombre, permisos, activo
            FROM api_clientes
            WHERE token_hash = ? AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            http_response_code(401);
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'error' => 'Token inválido o inactivo'
            ]));
        }

        // Registrar acceso
        $update = $pdo->prepare("UPDATE api_clientes SET ultimo_acceso = NOW() WHERE id = ?");
        $update->execute([$cliente['id']]);

        return $cliente;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'error' => 'Error de conexión'
        ]));
    }
}

/**
 * Verifica que el cliente tenga permiso requerido
 */
function verificarPermiso(array $cliente, string $permiso_requerido): void {
    $permisos = array_map('trim', explode(',', $cliente['permisos']));

    if (!in_array($permiso_requerido, $permisos) && !in_array('lectura,escritura', $permisos)) {
        http_response_code(403);
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'error' => 'Permiso denegado',
            'requerido' => $permiso_requerido,
            'actual' => $cliente['permisos']
        ]));
    }
}

/**
 * Registra acceso API en auditoría
 */
function registrarAccesoAPI(array $cliente, string $endpoint, string $metodo, string $estado): void {
    try {
        $pdo = getConexion();
        $stmt = $pdo->prepare("
            INSERT INTO api_auditar (cliente_id, endpoint, metodo, estado, ip_origen, fecha)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $cliente['id'],
            $endpoint,
            $metodo,
            $estado,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        // Log silenciosamente, no interrumpir respuesta
        error_log("Error registrando acceso API: " . $e->getMessage());
    }
}
?>
