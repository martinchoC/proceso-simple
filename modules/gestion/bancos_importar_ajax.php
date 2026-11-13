<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 129;
$pagina_conciliacion = 128;   // la solapa Movimientos clasifica las líneas con la función "clasificar" de Conciliación Bancaria
$pagina_conceptos = 132;      // y da de alta conceptos con la función "agregar" de Conceptos Bancarios
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/bancos_importar_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_conciliacion_service.php';
require_once __DIR__ . '/../../core/bancos/bancos_ajax.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);

bco_despachar('bancos_importar_ajax', function (string $accion) use ($conexion, $ctx, $empresa_id, $usuario_id, $pagina_idx, $pagina_conciliacion, $pagina_conceptos) {
    switch ($accion) {
        case 'listar':
            bco_responder(true, bco_importaciones_listar($conexion, $empresa_id, $pagina_idx, intval($_REQUEST['cuenta_id'] ?? 0)));
        case 'opciones':
            $alta = usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_conceptos, 'agregar');
            bco_responder(true, ['cuentas' => bco_cuentas_banco($conexion, $empresa_id),
                                 'conceptos' => bco_conceptos_listar($conexion, $empresa_id),
                                 'puede_importar' => usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, 'agregar'),
                                 'puede_clasificar' => usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_conciliacion, 'clasificar'),
                                 'alta_concepto' => $alta,
                                 'productos' => $alta ? bco_productos_servicio($conexion, $empresa_id) : [],
                                 'jurisdicciones' => $alta ? bco_jurisdicciones_iibb($conexion, $empresa_id) : []]);
        case 'clasificar':
            bco_exigir($conexion, $ctx, $pagina_conciliacion, 'clasificar');
            $r = bco_lineas_clasificar($conexion, $empresa_id, $usuario_id, (array)($_POST['extracto_ids'] ?? []), (string)($_POST['operacion'] ?? ''),
                                       intval($_POST['concepto_id'] ?? 0));
            $msg = "Se actualizaron {$r['lineas']} líneas.";
            if ($r['mismas']) $msg .= " Además, {$r['mismas']} líneas sin concepto con la misma descripción.";
            if ($r['reglas']) $msg .= ' Los próximos extractos con esa descripción se clasifican solos (Reglas Bancarias, "aprendida").';
            bco_responder(true, $r, $msg);
        case 'agregar_concepto':
            bco_exigir($conexion, $ctx, $pagina_conceptos, 'agregar');
            $d = $_POST;
            $d['concepto_id'] = 0;
            $id = bco_concepto_guardar($conexion, $empresa_id, $usuario_id, $d);
            bco_responder(true, bco_concepto_obtener($conexion, $empresa_id, $id), 'Concepto creado.');
        case 'movimientos':
        case 'resumen':
            $filtro = ['cuenta_id' => intval($_REQUEST['cuenta_id'] ?? 0), 'desde' => (string)($_REQUEST['desde'] ?? ''), 'hasta' => (string)($_REQUEST['hasta'] ?? ''),
                       'concepto_id' => intval($_REQUEST['concepto_id'] ?? 0), 'estado' => (string)($_REQUEST['estado'] ?? ''),
                       'sentido' => (string)($_REQUEST['sentido'] ?? ''), 'texto' => (string)($_REQUEST['texto'] ?? '')];
            bco_responder(true, $accion === 'resumen' ? bco_extracto_resumen_mensual($conexion, $empresa_id, $filtro)
                                                      : bco_extracto_movimientos($conexion, $empresa_id, $filtro));
        case 'formatos':
            bco_responder(true, bco_formatos_para_cuenta($conexion, $empresa_id, intval($_REQUEST['cuenta_id'] ?? 0)));
        case 'previsualizar':
            bco_exigir($conexion, $ctx, $pagina_idx, 'agregar');
            [$tmp] = bco_archivo_subido($_FILES['archivo'] ?? null);
            bco_responder(true, bco_importar_previsualizar($conexion, $empresa_id, intval($_POST['cuenta_id'] ?? 0), 0, $tmp));
        case 'agregar':
            bco_exigir($conexion, $ctx, $pagina_idx, 'agregar');
            [$tmp, $nombre] = bco_archivo_subido($_FILES['archivo'] ?? null);
            $r = bco_importar_confirmar($conexion, $empresa_id, $usuario_id, intval($_POST['cuenta_id'] ?? 0), 0, $tmp, $nombre);
            bco_responder(true, $r, "Se importaron {$r['lineas']} movimientos" . ($r['sin_concepto'] ? " ({$r['sin_concepto']} sin concepto: asignalo en Conciliación Bancaria)." : '.'));
        case 'obtener':
            $id = intval($_REQUEST['importacion_id'] ?? 0);
            if (!bco_fila($conexion, "SELECT 1 FROM gestion__bancos_importaciones WHERE importacion_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id)) {
                bco_responder(false, null, 'Lote no encontrado.', 404);
            }
            bco_responder(true, bco_extracto_lineas($conexion, $empresa_id, ['importacion_id' => $id]));
        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            bco_exigir($conexion, $ctx, $pagina_idx, $accion_js);
            $estado = bco_importacion_anular($conexion, $empresa_id, $usuario_id, $pagina_idx, intval($_POST['importacion_id'] ?? 0), $accion_js);
            bco_responder(true, ['estado' => $estado], 'Lote anulado: sus movimientos se quitaron y el archivo se puede volver a importar.');
    }
});
