<?php
session_start();
$_SESSION['usuario_id'] = 1;
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 3;

$_REQUEST['accion'] = 'drilldown';
$_REQUEST['empresa_idx'] = 2;
$_GET['nivel'] = 'global';

ob_start();
require 'c:/xampp/htdocs/casalucho/modules/ml/tablero_cumplimiento_ajax.php';
$out = ob_get_clean();

echo "Salida drilldown:\n" . $out . "\n";
