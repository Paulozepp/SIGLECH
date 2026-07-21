<?php
/**
 * logout.php - Cerrar sesión
 */

session_start();
session_destroy();

header('Location: /SIGLECH/login.php');
exit;
?>

