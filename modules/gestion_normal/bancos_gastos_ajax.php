<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 630;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/bancos_gastos_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_ajax.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);

bco_despachar('bancos_gastos_ajax', function (string $accion) use ($conexion, $ctx, $empresa_id, $usuario_id, $pagina_idx) {
    $cuenta_id = intval($_REQUEST['cuenta_id'] ?? 0);
    $mes = (string)($_REQUEST['mes'] ?? '');
    // Período de los listados (solapas A generar y Generados): meses AAAA-MM, vacío = sin límite
    [$desde, $hasta] = in_array($accion, ['pendientes', 'listar'], true)
        ? bco_rango_meses((string)($_REQUEST['desde'] ?? ''), (string)($_REQUEST['hasta'] ?? '')) : [null, null];
    switch ($accion) {
        case 'opciones':
            bco_responder(true, ['cuentas' => bco_cuentas_banco($conexion, $empresa_id),
                                 'puntos_venta' => pagoPuntosVentaOp($conexion, $empresa_id),
                                 'funciones' => array_keys(bco_funciones_pagina($conexion, $usuario_id, $empresa_id, $pagina_idx))]);
        case 'pendientes':
            bco_responder(true, bco_gastos_pendientes($conexion, $empresa_id, $cuenta_id, $desde, $hasta));
        case 'resumen':
            bco_responder(true, bco_gastos_resumen($conexion, $empresa_id, $cuenta_id, $mes, (array)($_REQUEST['excluir'] ?? [])));
        case 'proveedores':
            bco_responder(true, bco_proveedores_buscar($conexion, $empresa_id, (string)($_REQUEST['q'] ?? '')));
        case 'proveedor':
            bco_exigir($conexion, $ctx, $pagina_idx, 'proveedor');
            bco_gastos_proveedor_guardar($conexion, $empresa_id, $cuenta_id, intval($_POST['entidad_id'] ?? 0));
            bco_responder(true, null, 'Proveedor del banco guardado.');
        case 'agregar':
            bco_exigir($conexion, $ctx, $pagina_idx, 'agregar');
            // El resumen bancario se emite por Facturas de Proveedores de este módulo: exige también sus funciones
            $fp_pagina_id = bco_pagina_hermana($conexion, $pagina_idx, 'facturas_proveedores.php');
            bco_exigir_funciones($conexion, $ctx, $fp_pagina_id, ['agregar', 'aprobar', 'confirmar'], 'generar el resumen bancario');
            $r = bco_gastos_generar($conexion, $empresa_id, $usuario_id, $cuenta_id, $mes, (array)($_POST['excluir'] ?? []), $fp_pagina_id,
                                   intval($_POST['op_punto_venta_id'] ?? 0));
            bco_responder(true, $r, "Se generó el {$r['comprobante']} por $ " . bco_fmt($r['total']) . ' y se concilió con el débito del banco.');
        case 'listar':
            bco_responder(true, bco_conciliaciones_listar($conexion, $empresa_id, $pagina_idx, $cuenta_id, 'GASTOS', $desde, $hasta));
        case 'obtener':
            bco_responder(true, bco_conciliacion_detalle($conexion, $empresa_id, intval($_REQUEST['conciliacion_id'] ?? 0)));
        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            bco_exigir($conexion, $ctx, $pagina_idx, $accion_js);
            if ($accion_js !== 'deshacer') bco_responder(false, null, 'Acción no válida.', 400);
            $fp_pagina_id = bco_pagina_hermana($conexion, $pagina_idx, 'facturas_proveedores.php');
            bco_exigir_funciones($conexion, $ctx, $fp_pagina_id, ['anular'], 'anular el resumen bancario');
            $msg = bco_gastos_deshacer($conexion, $empresa_id, $usuario_id, intval($_POST['conciliacion_id'] ?? 0), (string)($_POST['motivo'] ?? ''), $fp_pagina_id);
            bco_responder(true, null, 'Se anuló el resumen bancario y las líneas quedaron disponibles. ' . $msg);
    }
});
