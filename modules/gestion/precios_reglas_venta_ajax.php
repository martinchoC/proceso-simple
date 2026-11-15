<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 146;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // un aviso al cargar el código no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/precios_venta_abm.php';

$accion = (string) ($_GET['accion'] ?? $_POST['accion'] ?? '');
// Escalas y promociones no tiene motor de estados: cada acción exige su función de la página (core/permisos.php)
permisos_exigir_accion($conn, $pagina_idx, $accion, [
    'listar' => 'consultar', 'obtener' => 'consultar', 'catalogos' => 'consultar', 'buscar_productos' => 'consultar',
    'topes_listar' => 'consultar', 'vista_previa' => 'consultar',
    'agregar' => 'agregar', 'editar' => 'editar', 'tope_guardar' => 'editar', 'lista_tope_guardar' => 'editar',
    'estado' => 'inhabilitar',
]);
$respuesta = prva_despachar($conn, $ctx, 2, $accion);
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
