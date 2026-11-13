<?php

require_once 'config.php';
require_once __DIR__ . '/core/auditoria.php';

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$usuario_nombre = (string)($_SESSION['usuario_nombre'] ?? 'Usuario');

if ($usuario_id > 0) {
    auditoria_logout($conexion, $usuario_id, $usuario_nombre);
}

$_SESSION = [];

session_destroy();

header("Location: " . url('index.php'));
exit;
?>