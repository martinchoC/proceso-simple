<?php
session_start();
$_SESSION['logueado'] = true;
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Pablo';
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 2;
$_GET['empresa_id'] = 2;
$_GET['modulo_id'] = 2;

ob_start();
require_once __DIR__ . '/../modules/gestion/index.php';
$html = ob_get_clean();

file_put_contents(__DIR__ . '/output_gestion_index.html', $html);
echo "Rendered length: " . strlen($html) . "\n";
