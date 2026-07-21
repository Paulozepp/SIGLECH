<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'mensaje' => 'API funcionando',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION
]);
?>
