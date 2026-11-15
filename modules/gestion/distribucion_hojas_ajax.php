<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 149;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // un aviso al cargar el código no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/distribucion/distribucion_hojas.php';

$accion = (string) ($_GET['accion'] ?? $_POST['accion'] ?? '');
// Sin motor de botones: cada acción exige su función de la página (core/permisos.php)
permisos_exigir_accion($conn, $pagina_idx, $accion, [
    'catalogos' => 'consultar', 'disponibles' => 'consultar', 'hojas' => 'consultar', 'hoja' => 'consultar', 'lista_carga' => 'consultar',
    'hoja_crear' => 'agregar',
    'hoja_editar' => 'editar', 'agregar_pedidos' => 'editar', 'quitar_pedido' => 'editar', 'ordenar' => 'editar', 'cambiar_deposito' => 'editar',
    'confirmar' => 'confirmar', 'anular' => 'inhabilitar',
    'rendir_pedido' => 'rendir', 'cerrar_rendicion' => 'rendir',
]);
$respuesta = vdhr_despachar($conn, $ctx, $accion);
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
