<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['logueado'] = true;
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Pablo Passoni';
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 4;
$_GET['empresa_id'] = 2;
$_GET['modulo_id'] = 4;
$_GET['pagina_id'] = 401;

require_once __DIR__ . '/../modules/estudio_contable/dashboard_estudio.php';
