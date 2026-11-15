<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 148;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // un aviso al cargar el código no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/distribucion/distribucion_config_abm.php';

$accion = (string) ($_GET['accion'] ?? $_POST['accion'] ?? '');
// Sin motor de estados: cada acción exige su función de la página (core/permisos.php)
permisos_exigir_accion($conn, $pagina_idx, $accion, [
    'catalogos' => 'consultar', 'zonas_listar' => 'consultar', 'zona_clientes' => 'consultar', 'sin_zona' => 'consultar',
    'buscar_clientes' => 'consultar', 'sucursales_cliente' => 'consultar', 'camiones_listar' => 'consultar',
    'zona_agregar' => 'agregar', 'camion_agregar' => 'agregar',
    'zona_editar' => 'editar', 'zona_asignar' => 'editar', 'zona_quitar' => 'editar', 'camion_editar' => 'editar',
    'zona_estado' => 'inhabilitar', 'camion_estado' => 'inhabilitar',
]);
$respuesta = vdcf_despachar($conn, $ctx, $accion);
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
