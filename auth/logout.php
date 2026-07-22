<?php
/**
 * logout.php - Cierre de sesión SIGLECH
 */

require_once __DIR__ . '/../auth/guard.php';

// Destruir sesión
cerrarSesion();

// Redirigir a login
header('Location: /SIGLECH/login.php?loggedout=1');
exit;
?>
