<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 628;
$pagina_conceptos = 632;   // Conceptos Bancarios: el alta rápida de un concepto exige su función "agregar"
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/bancos_conciliacion_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_ajax.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);

bco_despachar('bancos_conciliacion_ajax', function (string $accion) use ($conexion, $ctx, $empresa_id, $usuario_id, $pagina_idx, $pagina_conceptos) {
    $cuenta_id = intval($_REQUEST['cuenta_id'] ?? 0);
    $desde = bco_fecha_valida($_REQUEST['desde'] ?? null);
    $hasta = bco_fecha_valida($_REQUEST['hasta'] ?? null);
    // Recibos desde el extracto: funciones de la página de Cobranzas del módulo
    $pagina_cobranzas = static fn() => bco_pagina_hermana($conexion, $pagina_idx, 'cobranzas.php');
    $puede_recibo = static fn() => usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_cobranzas(), 'agregar');
    switch ($accion) {
        case 'opciones':
            $alta = usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_conceptos, 'agregar');
            $recibo = $puede_recibo();
            bco_responder(true, ['cuentas' => bco_cuentas_banco($conexion, $empresa_id),
                                 'conceptos' => bco_conceptos_listar($conexion, $empresa_id, 0, true),
                                 'funciones' => array_keys(bco_funciones_pagina($conexion, $usuario_id, $empresa_id, $pagina_idx)),
                                 'alta_concepto' => $alta,
                                 'recibo' => $recibo,
                                 'recibo_confirmar' => $recibo && usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_cobranzas(), 'confirmar'),
                                 'productos' => $alta ? bco_productos_servicio($conexion, $empresa_id) : [],
                                 'jurisdicciones' => $alta ? bco_jurisdicciones_iibb($conexion, $empresa_id) : []]);
        case 'cobranza_preparar':
            bco_exigir_funciones($conexion, $ctx, $pagina_cobranzas(), ['agregar'], 'generar el recibo', 'Cobranzas');
            bco_responder(true, bco_cobranza_preparar($conexion, $empresa_id, intval($_REQUEST['extracto_id'] ?? 0), $pagina_cobranzas()));
        case 'cobranza_tipos':
            bco_exigir_funciones($conexion, $ctx, $pagina_cobranzas(), ['agregar'], 'generar el recibo', 'Cobranzas');
            bco_responder(true, bco_cobranza_tipos($conexion, $empresa_id, intval($_REQUEST['punto_venta_id'] ?? 0), $pagina_cobranzas()));
        case 'cobranza_generar':
            $confirmar = !empty($_POST['confirmar']);
            // Confirmado, además concilia la línea: función "agregar" (Conciliar) de esta página
            if ($confirmar) bco_exigir($conexion, $ctx, $pagina_idx, 'agregar');
            else csrf_exigir();
            bco_exigir_funciones($conexion, $ctx, $pagina_cobranzas(), $confirmar ? ['agregar', 'confirmar'] : ['agregar'], 'generar el recibo', 'Cobranzas');
            $r = bco_cobranza_generar($conexion, $empresa_id, $usuario_id, intval($_POST['extracto_id'] ?? 0), [
                'punto_venta_id' => $_POST['punto_venta_id'] ?? 0, 'comprobante_tipo_id' => $_POST['comprobante_tipo_id'] ?? 0,
                'medio_pago_id' => $_POST['medio_pago_id'] ?? 0, 'observaciones' => $_POST['observaciones'] ?? '',
                'imputaciones' => (array)($_POST['imputaciones'] ?? []), 'confirmar' => $confirmar], $pagina_cobranzas());
            bco_responder(true, $r, $r['confirmado'] ? "Recibo {$r['numero']} confirmado y línea conciliada."
                                                     : "Recibo guardado en Borrador (cobranza {$r['cobranza_id']}): confirmalo en Cobranzas y después conciliá la línea.");
        case 'agregar_concepto':
            bco_exigir($conexion, $ctx, $pagina_conceptos, 'agregar');
            $d = $_POST;
            $d['concepto_id'] = 0;
            $id = bco_concepto_guardar($conexion, $empresa_id, $usuario_id, $d);
            bco_responder(true, bco_concepto_obtener($conexion, $empresa_id, $id), 'Concepto creado.');
        case 'datos':
            $datos = bco_conciliacion_datos($conexion, $empresa_id, $cuenta_id, $desde, $hasta, !empty($_REQUEST['ignoradas']));
            if ($puede_recibo()) {
                $cob = bco_cobranzas_marcar($conexion, $empresa_id, $cuenta_id, $datos['lineas']);
                foreach ($datos['lineas'] as &$l) $l['cobranza'] = $cob[intval($l['extracto_id'])] ?? null;
                unset($l);
            }
            bco_responder(true, $datos);
        case 'periodos':
            // Años desde / hasta (por defecto el año en curso), a lo sumo 10 años
            $anio_hasta = intval($_REQUEST['anio_hasta'] ?? 0) ?: intval(date('Y'));
            $anio_desde = intval($_REQUEST['anio_desde'] ?? 0) ?: $anio_hasta;
            if ($anio_desde > $anio_hasta) bco_responder(false, null, 'El año "desde" es posterior al año "hasta".', 400);
            $anio_desde = max($anio_desde, $anio_hasta - 9);
            bco_responder(true, bco_conciliacion_resumen($conexion, $empresa_id, !empty($_REQUEST['solo_cuenta']) ? $cuenta_id : 0, $anio_desde, $anio_hasta));
        case 'sugerir':
            bco_responder(true, bco_conciliacion_sugerir($conexion, $empresa_id, $cuenta_id, $desde, $hasta));
        case 'agregar':
            bco_exigir($conexion, $ctx, $pagina_idx, 'agregar');
            $id = bco_conciliar($conexion, $empresa_id, $usuario_id, $cuenta_id, (array)($_POST['extracto_ids'] ?? []), (array)($_POST['movimiento_ids'] ?? []),
                                (string)($_POST['observaciones'] ?? ''));
            bco_responder(true, ['conciliacion_id' => $id], 'Conciliado.');
        case 'conciliar_auto':
            bco_exigir($conexion, $ctx, $pagina_idx, 'conciliar_auto');
            $r = bco_conciliar_auto($conexion, $empresa_id, $usuario_id, $cuenta_id, $desde, $hasta);
            bco_responder(true, $r, $r['conciliadas'] ? "Se conciliaron {$r['conciliadas']} movimientos por importe y referencia." : 'No hay coincidencias seguras para conciliar solas.');
        case 'clasificar':
            bco_exigir($conexion, $ctx, $pagina_idx, 'clasificar');
            $r = bco_lineas_clasificar($conexion, $empresa_id, $usuario_id, (array)($_POST['extracto_ids'] ?? []), (string)($_POST['operacion'] ?? ''),
                                       intval($_POST['concepto_id'] ?? 0));
            $msg = "Se actualizaron {$r['lineas']} líneas.";
            if ($r['mismas']) $msg .= " Además, {$r['mismas']} líneas sin concepto con la misma descripción.";
            if ($r['reglas']) $msg .= ' Los próximos extractos con esa descripción se clasifican solos (Reglas Bancarias, "aprendida").';
            bco_responder(true, $r, $msg);
        case 'listar':
            bco_responder(true, bco_conciliaciones_listar($conexion, $empresa_id, $pagina_idx, $cuenta_id, null, $desde, $hasta, true));
        case 'obtener':
            bco_responder(true, bco_conciliacion_detalle($conexion, $empresa_id, intval($_REQUEST['conciliacion_id'] ?? 0)));
        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            bco_exigir($conexion, $ctx, $pagina_idx, $accion_js);
            if ($accion_js !== 'deshacer') bco_responder(false, null, 'Acción no válida.', 400);
            bco_conciliacion_deshacer($conexion, $empresa_id, $usuario_id, intval($_POST['conciliacion_id'] ?? 0), (string)($_POST['motivo'] ?? ''));
            bco_responder(true, null, 'Conciliación deshecha: las líneas y los movimientos vuelven a estar pendientes.');
    }
});
