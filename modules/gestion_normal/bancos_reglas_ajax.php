<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 633;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/bancos_reglas_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_ajax.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);

bco_despachar('bancos_reglas_ajax', function (string $accion) use ($conexion, $ctx, $empresa_id, $usuario_id, $pagina_idx) {
    switch ($accion) {
        case 'listar':
            bco_responder(true, bco_reglas_listar($conexion, $empresa_id, $pagina_idx, intval($_REQUEST['banco_id'] ?? 0)));
        case 'obtener_boton_agregar':
            bco_responder(true, array_values(bco_funciones_pagina($conexion, $usuario_id, $empresa_id, $pagina_idx)));
        case 'opciones':
            bco_responder(true, ['bancos' => bco_bancos($conexion), 'conceptos' => bco_conceptos_listar($conexion, $empresa_id, 0, true)]);
        case 'obtener':
            $r = bco_regla_obtener($conexion, $empresa_id, intval($_REQUEST['regla_id'] ?? 0));
            $r ? bco_responder(true, $r) : bco_responder(false, null, 'Regla no encontrada.', 404);
        case 'probar_texto':
            // Qué regla clasificaría una descripción (no escribe)
            $reglas = bco_reglas_activas($conexion, $empresa_id, intval($_REQUEST['banco_id'] ?? 0));
            $r = bco_regla_para(['descripcion' => (string)($_REQUEST['descripcion'] ?? ''), 'importe' => floatval($_REQUEST['importe'] ?? -1)], $reglas);
            bco_responder(true, $r);
        case 'agregar':
        case 'editar':
            bco_exigir($conexion, $ctx, $pagina_idx, $accion);
            $d = $_POST;
            $d['regla_id'] = $accion === 'agregar' ? 0 : intval($_POST['regla_id'] ?? 0);
            $id = bco_regla_guardar($conexion, $empresa_id, $usuario_id, $d);
            bco_responder(true, ['regla_id' => $id], $accion === 'agregar' ? 'Regla creada.' : 'Regla actualizada.');
        case 'aplicar':
            bco_exigir($conexion, $ctx, $pagina_idx, 'aplicar');
            $r = bco_reglas_aplicar($conexion, $empresa_id, $usuario_id, intval($_POST['banco_id'] ?? 0));
            bco_responder(true, $r, "Se revisaron {$r['revisadas']} líneas sin conciliar: cambiaron {$r['cambiadas']}, quedan {$r['sin_concepto']} sin concepto.");
        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            bco_exigir($conexion, $ctx, $pagina_idx, $accion_js);
            $estado = bco_regla_accion($conexion, $empresa_id, $pagina_idx, intval($_POST['regla_id'] ?? 0), $accion_js);
            bco_responder(true, ['estado' => $estado], 'Estado actualizado.');
    }
});
