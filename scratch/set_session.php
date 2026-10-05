<?php
session_start();
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario'] = 'pablo';
$_SESSION['usuario_nombre'] = 'Pablo Passoni';
$_SESSION['logueado'] = true;
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 4;
$target = $_GET['target'] ?? '/casalucho/modules/estudio_contable/dashboard_estudio.php?empresa_id=2&modulo_id=4';
header("Location: " . $target);
exit;
