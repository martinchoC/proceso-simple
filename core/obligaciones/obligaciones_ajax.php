<?php
/**
 * Despachadores AJAX de Obligaciones de Pago, Tipos de Obligación y Reglas de Aprobación (gestion y
 * gestion_normal). El _ajax.php de cada módulo valida sesión, empresa y página con ajax_guard(), abre el buffer,
 * incluye su _model.php (tesorería del módulo + este servicio) y llama al despachador con su pagina_idx fijo.
 * Respuesta {success, data, message} (core/bancos/bancos_ajax.php); escrituras con CSRF + función de la página.
 */

require_once __DIR__ . '/obligaciones_importacion.php';
require_once __DIR__ . '/../bancos/bancos_ajax.php';

function obl_req(string $k, $defecto = null)
{
    return $_POST[$k] ?? $_GET[$k] ?? $defecto;
}

/** Url de pagos.php del mismo módulo (para el botón Pagar). */
function obl_url_pagos(mysqli $c, int $pagina_id): string
{
    $p = obl_fila($c, "SELECT h.pagina_id, h.url FROM conf__paginas p JOIN conf__paginas h ON h.modulo_id = p.modulo_id AND h.url = 'pagos.php'
                       WHERE p.pagina_id = ? LIMIT 1", 'i', $pagina_id);
    return $p ? 'pagos.php?pagina_id=' . intval($p['pagina_id']) : 'pagos.php';
}

function obl_ajax_obligaciones(mysqli $c, array $ctx, int $pagina_id): void
{
    $e = intval($ctx['empresa_id']);
    $u = intval($ctx['usuario_id']);
    bco_despachar('obligaciones_pago_ajax', function (string $accion) use ($c, $ctx, $e, $u, $pagina_id) {
        $id = intval(obl_req('obligacion_id', 0));
        switch ($accion) {
            case 'listar':
                bco_responder(true, obl_listar($c, $e, $u, $pagina_id, [
                    'vista' => obl_req('vista', 'pendientes'), 'obligacion_tipo_id' => obl_req('obligacion_tipo_id'),
                    'entidad_id' => obl_req('entidad_id'), 'vto_desde' => obl_req('vto_desde'), 'vto_hasta' => obl_req('vto_hasta'),
                    'importacion_id' => obl_req('importacion_id'),
                ]));
            case 'resumen':
                bco_responder(true, obl_resumen($c, $e));
            case 'obtener_boton_agregar':
                bco_responder(true, obl_funciones_pagina($c, $u, $e, $pagina_id));
            case 'opciones':
                bco_responder(true, [
                    'tipos' => obl_tipos($c, $e), 'entidades' => obl_entidades($c, $e), 'cuentas' => obl_cuentas($c, $e),
                    'sucursales' => obl_filas($c, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id", 'i', $e),
                    'roles' => OBL_ROLES, 'url_pagos' => obl_url_pagos($c, $pagina_id),
                ]);
            case 'obtener':
                bco_responder(true, obl_obtener($c, $e, $id));
            case 'agregar':
                bco_exigir($c, $ctx, $pagina_id, 'agregar');
                $d = $_POST;
                $d['origen'] = 'MANUAL';
                $d['confirmar'] = !empty($_POST['confirmar_ahora']) ? 'SI' : 'NO';
                if ($d['confirmar'] === 'SI') bco_exigir($c, $ctx, $pagina_id, 'confirmar');
                $r = obl_registrar($c, $d, $e, $u);
                bco_responder(true, $r, $r['mensaje']);
            case 'editar':
                bco_exigir($c, $ctx, $pagina_id, 'editar');
                obl_editar($c, $e, $u, $id, $_POST);
                bco_responder(true, null, 'Guardado.');
            case 'confirmar':
                bco_exigir($c, $ctx, $pagina_id, 'confirmar');
                $r = obl_confirmar($c, $e, $u, $id);
                bco_responder(true, $r, $r['mensaje']);
            case 'aprobar':
                bco_exigir($c, $ctx, $pagina_id, 'aprobar');
                $r = obl_aprobar($c, $e, $u, $pagina_id, $id);
                bco_responder(true, $r, $r['mensaje']);
            case 'rechazar':
                bco_exigir($c, $ctx, $pagina_id, 'rechazar');
                obl_rechazar($c, $e, $u, $pagina_id, $id, (string)obl_req('motivo', ''));
                bco_responder(true, null, 'Rechazada: volvió a Borrador.');
            case 'eliminar':
                bco_exigir($c, $ctx, $pagina_id, 'eliminar');
                obl_eliminar($c, $e, $u, $id);
                bco_responder(true, null, 'Eliminada.');
            case 'anular':
                bco_exigir($c, $ctx, $pagina_id, 'anular');
                obl_anular($c, $e, $u, $id, (string)obl_req('motivo', ''));
                bco_responder(true, null, 'Anulada.');
            // Importación
            case 'importar_previsualizar':
                bco_exigir($c, $ctx, $pagina_id, 'importar');
                bco_responder(true, obl_imp_previsualizar($c, $e, $u, $_FILES['archivo'] ?? null, intval(obl_req('obligacion_tipo_id', 0))));
            case 'importar_confirmar':
                bco_exigir($c, $ctx, $pagina_id, 'importar');
                $confirmar = !empty($_POST['confirmar_ahora']);
                if ($confirmar) bco_exigir($c, $ctx, $pagina_id, 'confirmar');
                $r = obl_imp_confirmar($c, $e, $u, intval(obl_req('importacion_id', 0)), $confirmar, !empty($_POST['asignar_roles']));
                bco_responder(true, $r, $r['mensaje']);
            case 'importar_deshacer':
                bco_exigir($c, $ctx, $pagina_id, 'importar');
                obl_imp_deshacer($c, $e, $u, intval(obl_req('importacion_id', 0)));
                bco_responder(true, null, 'Importación deshecha: sus obligaciones quedaron eliminadas.');
            case 'importaciones':
                bco_responder(true, obl_imp_listar($c, $e));
            case 'plantilla':
                while (ob_get_level() > 0) ob_end_clean();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="plantilla_obligaciones.csv"');
                echo obl_imp_plantilla();
                exit;
            // Anticipos: saldo a favor de OP aplicado a deudas de la misma entidad
            case 'anticipos':
                $ent = intval(obl_req('entidad_id', 0));
                $destinos = array_values(array_filter(tesoreriaObtenerComprobantesCancelables($c, $e, 'OP', $ent),
                    static fn($d) => ($d['comprobante_tipo'] ?? '') !== 'SOL_ANT'));
                bco_responder(true, ['anticipos' => obl_anticipos_disponibles($c, $e, $ent), 'destinos' => $destinos]);
            case 'aplicar_anticipo':
                bco_exigir($c, $ctx, $pagina_id, 'aplicar_anticipo');
                $imp = obl_aplicar_anticipo($c, $e, $u, intval(obl_req('op_comprobante_id', 0)), intval(obl_req('destino_comprobante_id', 0)),
                                            obl_importe(obl_req('importe', 0)));
                bco_responder(true, ['importe' => $imp], 'Anticipo aplicado por $ ' . number_format($imp, 2, ',', '.') . '.');
        }
    });
}

function obl_ajax_tipos(mysqli $c, array $ctx, int $pagina_id): void
{
    $e = intval($ctx['empresa_id']);
    $u = intval($ctx['usuario_id']);
    bco_despachar('obligaciones_tipos_ajax', function (string $accion) use ($c, $ctx, $e, $u, $pagina_id) {
        $id = intval(obl_req('obligacion_tipo_id', 0));
        switch ($accion) {
            case 'listar':
                $rows = obl_tipos($c, $e, false);
                foreach ($rows as &$r) $r['botones'] = obl_botones($c, $pagina_id, intval($r['tabla_estado_registro_id']));
                unset($r);
                bco_responder(true, $rows);
            case 'obtener_boton_agregar':
                bco_responder(true, obl_funciones_pagina($c, $u, $e, $pagina_id));
            case 'opciones':
                bco_responder(true, ['cuentas' => obl_cuentas($c, $e), 'roles' => OBL_ROLES, 'comprobantes' => OBL_COMPROBANTES]);
            case 'obtener':
                bco_responder(true, obl_tipo($c, $e, $id));
            case 'agregar':
            case 'editar':
                bco_exigir($c, $ctx, $pagina_id, $accion);
                $d = $_POST;
                if ($accion === 'agregar') $d['obligacion_tipo_id'] = 0;
                obl_tipo_guardar($c, $e, $u, $d);
                bco_responder(true, null, 'Guardado.');
            case 'ejecutar_accion':
                $acc = (string)obl_req('accion_js', '');
                if (!in_array($acc, ['inactivar', 'habilitar'], true)) bco_responder(false, null, 'Acción no válida.', 400);
                bco_exigir($c, $ctx, $pagina_id, $acc);
                obl_tipo_estado($c, $e, $u, $id, $acc === 'inactivar' ? 2 : 1);
                bco_responder(true, null, $acc === 'inactivar' ? 'Inactivado.' : 'Habilitado.');
        }
    });
}

function obl_ajax_reglas(mysqli $c, array $ctx, int $pagina_id): void
{
    $e = intval($ctx['empresa_id']);
    $u = intval($ctx['usuario_id']);
    bco_despachar('aprobaciones_reglas_ajax', function (string $accion) use ($c, $ctx, $e, $u, $pagina_id) {
        $que = obl_req('que', 'regla') === 'tope' ? 'tope' : 'regla';
        switch ($accion) {
            case 'listar':
                $rows = $que === 'tope' ? aprob_topes_listar($c, $e) : aprob_reglas_listar($c, $e);
                foreach ($rows as &$r) $r['botones'] = obl_botones($c, $pagina_id, intval($r['tabla_estado_registro_id']));
                unset($r);
                bco_responder(true, $rows);
            case 'obtener_boton_agregar':
                bco_responder(true, obl_funciones_pagina($c, $u, $e, $pagina_id));
            case 'opciones':
                bco_responder(true, [
                    'tipos' => obl_tipos($c, $e),
                    'perfiles' => obl_filas($c, "SELECT empresa_perfil_id, empresa_perfil_nombre FROM conf__empresas_perfiles
                                                 WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY empresa_perfil_nombre", 'i', $e),
                ]);
            case 'agregar':
            case 'editar':
                bco_exigir($c, $ctx, $pagina_id, $accion);
                $d = $_POST;
                if ($accion === 'agregar') { $d['regla_id'] = 0; $d['tope_id'] = 0; }
                $que === 'tope' ? aprob_tope_guardar($c, $e, $u, $d) : aprob_regla_guardar($c, $e, $u, $d);
                bco_responder(true, null, 'Guardado.');
            case 'ejecutar_accion':
                $acc = (string)obl_req('accion_js', '');
                if (!in_array($acc, ['inactivar', 'habilitar'], true)) bco_responder(false, null, 'Acción no válida.', 400);
                bco_exigir($c, $ctx, $pagina_id, $acc);
                aprob_cambiar_estado($c, $e, $u, $que, intval(obl_req('id', 0)), $acc === 'inactivar' ? 2 : 1);
                bco_responder(true, null, $acc === 'inactivar' ? 'Inactivado.' : 'Habilitado.');
        }
    });
}
