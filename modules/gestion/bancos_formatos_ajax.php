<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 131;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/bancos_formatos_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_ajax.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);

bco_despachar('bancos_formatos_ajax', function (string $accion) use ($conexion, $ctx, $empresa_id, $usuario_id, $pagina_idx) {
    switch ($accion) {
        case 'listar':
            bco_responder(true, bco_formatos_listar($conexion, $empresa_id, $pagina_idx));
        case 'obtener_boton_agregar':
            bco_responder(true, bco_funciones_pagina($conexion, $usuario_id, $empresa_id, $pagina_idx)['agregar'] ?? null);
        case 'opciones':
            bco_responder(true, ['bancos' => bco_bancos($conexion)]);
        case 'obtener':
            $f = bco_formato_obtener($conexion, $empresa_id, intval($_REQUEST['formato_id'] ?? 0));
            $f ? bco_responder(true, $f) : bco_responder(false, null, 'Formato no encontrado.', 404);
        case 'agregar':
        case 'editar':
            bco_exigir($conexion, $ctx, $pagina_idx, $accion);
            $d = $_POST;
            $d['formato_id'] = $accion === 'agregar' ? 0 : intval($_POST['formato_id'] ?? 0);
            $id = bco_formato_guardar($conexion, $empresa_id, $usuario_id, $d);
            bco_responder(true, ['formato_id' => $id], $accion === 'agregar' ? 'Formato creado.' : 'Formato actualizado.');
        case 'probar':
            bco_exigir($conexion, $ctx, $pagina_idx, 'probar');
            [$tmp] = bco_archivo_subido($_FILES['archivo'] ?? null);
            bco_responder(true, bco_formato_probar($conexion, $empresa_id, $_POST, $tmp));
        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            bco_exigir($conexion, $ctx, $pagina_idx, $accion_js);
            $estado = bco_formato_accion($conexion, $empresa_id, $pagina_idx, intval($_POST['formato_id'] ?? 0), $accion_js);
            bco_responder(true, ['estado' => $estado], 'Estado actualizado.');
    }
});
