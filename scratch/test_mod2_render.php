<?php
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Pablo Passoni';
$_SESSION['logueado'] = true;
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 2;
$_GET['empresa_id'] = 2;
$_GET['modulo_id'] = 2;
$_GET['pagina_id'] = 56; // Ventas Facturas
$_SERVER['REQUEST_URI'] = '/casalucho/modules/gestion/ventas_facturas.php?empresa_id=2&modulo_id=2&pagina_id=56';
$_SERVER['HTTP_HOST'] = 'localhost';

$modudo_idx = 2;
$pagina_idx = 56;

ob_start();
require_once __DIR__ . '/../templates/adminlte/header1.php';
$html = ob_get_clean();

$pos = strpos($html, 'id="navigation"');
if ($pos !== false) {
    echo "=== SIDEBAR MODULO 2 (GESTION) ===\n";
    echo substr($html, $pos, 3500);
}
