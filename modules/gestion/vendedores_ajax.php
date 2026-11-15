<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 147;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // un aviso al cargar el código no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/vendedores_abm.php';

$accion = (string) ($_GET['accion'] ?? $_POST['accion'] ?? '');
// Vendedores y carteras no tiene motor de estados: cada acción exige su función de la página (core/permisos.php)
permisos_exigir_accion($conn, $pagina_idx, $accion, [
    'listar' => 'consultar', 'cartera' => 'consultar', 'sin_vendedor' => 'consultar', 'historial' => 'consultar',
    'buscar_entidades' => 'consultar', 'usuarios' => 'consultar',
    'alta' => 'agregar', 'ligar_usuario' => 'editar', 'asignar' => 'editar', 'quitar' => 'editar', 'reasignar' => 'editar',
    'baja' => 'inhabilitar',
]);
$respuesta = vabm_despachar($conn, $ctx, $accion);
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
