<?php
session_start();
$_SESSION['logueado'] = true;
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Pablo';
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 4;
$_GET['empresa_id'] = 2;
$_GET['modulo_id'] = 4;
$_GET['pagina_id'] = 401;
$modudo_idx = 4;
$pagina_idx = 401;

require_once __DIR__ . '/../../config.php';

ob_start();
require_once __DIR__ . '/../../templates/adminlte/header1.php';
$header = ob_get_clean();

$start = strpos($header, '<ul class="nav sidebar-menu');
if ($start !== false) {
    $end = strpos($header, '</ul>', $start);
    echo substr($header, $start, 3500) . "\n";
} else {
    echo "NO SIDEBAR FOUND\n";
}
