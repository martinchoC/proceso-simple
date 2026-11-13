<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 632;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/bancos_conceptos_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_ajax.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);

bco_despachar('bancos_conceptos_ajax', function (string $accion) use ($conexion, $ctx, $empresa_id, $usuario_id, $pagina_idx) {
    switch ($accion) {
        case 'listar':
            bco_responder(true, bco_conceptos_listar($conexion, $empresa_id, $pagina_idx));
        case 'obtener_boton_agregar':
            bco_responder(true, bco_funciones_pagina($conexion, $usuario_id, $empresa_id, $pagina_idx)['agregar'] ?? null);
        case 'opciones':
            bco_responder(true, ['productos' => bco_productos_servicio($conexion, $empresa_id), 'jurisdicciones' => bco_jurisdicciones_iibb($conexion, $empresa_id)]);
        case 'obtener':
            $k = bco_concepto_obtener($conexion, $empresa_id, intval($_REQUEST['concepto_id'] ?? 0));
            $k ? bco_responder(true, $k) : bco_responder(false, null, 'Concepto no encontrado.', 404);
        case 'agregar':
        case 'editar':
            bco_exigir($conexion, $ctx, $pagina_idx, $accion);
            $d = $_POST;
            $d['concepto_id'] = $accion === 'agregar' ? 0 : intval($_POST['concepto_id'] ?? 0);
            $id = bco_concepto_guardar($conexion, $empresa_id, $usuario_id, $d);
            bco_responder(true, ['concepto_id' => $id], $accion === 'agregar' ? 'Concepto creado.' : 'Concepto actualizado.');
        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            bco_exigir($conexion, $ctx, $pagina_idx, $accion_js);
            $estado = bco_concepto_accion($conexion, $empresa_id, $pagina_idx, intval($_POST['concepto_id'] ?? 0), $accion_js);
            bco_responder(true, ['estado' => $estado], 'Estado actualizado.');
    }
});
