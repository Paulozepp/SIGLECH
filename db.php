<?php
/**
 * db.php - SIGLECH
 * Gestión de conexión a BD (compartida con SICOCH)
 */

require_once __DIR__ . '/config.php';

/**
 * Obtiene conexión PDO a la BD compartida
 */
function getConexion(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Error conexión BD: ' . htmlspecialchars($e->getMessage()) .
                '<br><br>Ejecuta primero <a href="database/install.php">database/install.php</a>');
        }
    }

    return $pdo;
}

/**
 * Obtiene conexión PDO a la BD de demanda de listas de espera (CNE/IQ/PROC)
 */
function getConexionSiglech(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME_SIGLECH . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Error conexión BD siglech: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}

/**
 * Obtiene conexión sin BD (para crear/setup)
 */
function getConexionSinBD(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

/**
 * Ejecuta archivo SQL completo (con múltiples sentencias)
 */
function ejecutarSqlScript(string $rutaArchivo): void {
    $sql = file_get_contents($rutaArchivo);
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);

    $stmt = $pdo->query($sql);

    do {
        // Consume todos los result sets
    } while ($stmt->nextRowset());
}
