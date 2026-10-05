<?php
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Pablo Passoni';
$_SESSION['logueado'] = true;
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 4;
$_GET['empresa_id'] = 2;
$_GET['modulo_id'] = 4;
$_SERVER['REQUEST_URI'] = '/casalucho/modules/estudio_contable/index.php?empresa_id=2&modulo_id=4';
$_SERVER['HTTP_HOST'] = 'localhost';

$modudo_idx = 4;
$pagina_idx = 401;

ob_start();
require_once __DIR__ . '/../templates/adminlte/header1.php';
$html = ob_get_clean();

// Buscar en $html la sección <ul class="nav sidebar-menu
$pos = strpos($html, 'id="navigation"');
if ($pos !== false) {
    echo "=== SECCION SIDEBAR GENERADA ===\n";
    echo substr($html, $pos, 2500);
} else {
    echo "NO SE ENCONTRO navigation en el HTML generado\n";
    echo substr($html, 0, 1000);
}
