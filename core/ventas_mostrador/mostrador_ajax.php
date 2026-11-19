<?php
/**
 * Mostrador minorista (doc 29): acciones AJAX comunes a los módulos 2 y 5. El mostrador_ajax.php de cada módulo
 * fija su página e incluye antes el ventas_facturas_model.php de su carpeta: la factura que emite es una factura de
 * venta común (página Facturas de venta del módulo), con las mismas funciones que la pantalla (guardarVentaFactura,
 * vfEjecutarTransicionEstado con los medios de contado, vfTiposPorPuntoVenta). Precios, presentaciones, mínimos,
 * escalas y promociones: core/pedidos_venta/externo.php (pven_*; consumidor final = lista Mostrador).
 * Respuesta {success, data, message}. Escrituras: POST + CSRF + función "facturar" de la página del mostrador.
 *
 * GET  inicio                         puntos de venta del usuario con factura a consumidor final, medios y cuentas
 * GET  buscar      q, entidad_id, punto_venta_id     productos con sus ofertas (código de barras: la presentación)
 * GET  clientes    q                  clientes para facturar a nombre de uno (opcional)
 * POST valorizar   items, entidad_id, punto_venta_id
 * POST facturar    items, entidad_id, punto_venta_id, medios (JSON), consumidor_final_nombre, observaciones
 */

require_once __DIR__ . '/../pedidos_venta/externo.php';
require_once __DIR__ . '/../pedidos_venta/app_vendedor.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../permisos.php';

if (!function_exists('vmos_activo')) {
    /** Indicador usa_mostrador. Sin la columna: apagado. */
    function vmos_activo(mysqli $conexion, int $empresa_id): bool
    {
        $col = mysqli_query($conexion, "SHOW COLUMNS FROM conf__empresas LIKE 'usa_mostrador'");
        if (!$col || mysqli_num_rows($col) === 0) {
            return false;
        }
        $r = pven_filas($conexion, "SELECT usa_mostrador FROM conf__empresas WHERE empresa_id = ?", 'i', [$empresa_id]);
        return intval($r[0]['usa_mostrador'] ?? 0) === 1;
    }
}

if (!function_exists('vmos_puntos_venta')) {
    /**
     * Puntos de venta de las sucursales del usuario que tienen un tipo de factura para el destinatario (consumidor
     * final con $entidad_id 0): [punto_venta_id, nombre, sucursal_id, sucursal, comprobante_tipo_id, comprobante_tipo].
     */
    function vmos_puntos_venta(mysqli $conexion, int $empresa_id, int $pagina_facturas, int $entidad_id = 0): array
    {
        $sucursales = pven_filas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                             ORDER BY sucursal_nombre", 'i', [$empresa_id]);
        if (file_exists(__DIR__ . '/../accesos_sucursales.php')) {
            require_once __DIR__ . '/../accesos_sucursales.php';
            $sucursales = AccesosSucursalesHelper::filtrarSucursales($conexion, $empresa_id, $sucursales);
        }
        $out = [];
        foreach ($sucursales as $s) {
            foreach (pven_filas($conexion, "SELECT punto_venta_id, nombre FROM gestion__puntos_venta WHERE empresa_id = ? AND sucursal_id = ? AND tabla_estado_registro_id = 1
                                            ORDER BY punto_venta_id", 'ii', [$empresa_id, (int) $s['sucursal_id']]) as $pv) {
                $tipos = vfTiposPorPuntoVenta($conexion, $empresa_id, (int) $pv['punto_venta_id'], $pagina_facturas, $entidad_id, true);
                $tipos = is_array($tipos) ? ($tipos['data'] ?? $tipos) : [];
                if (!$tipos) {
                    continue;
                }
                $t = reset($tipos);
                $out[] = ['punto_venta_id' => (int) $pv['punto_venta_id'], 'nombre' => $pv['nombre'], 'sucursal_id' => (int) $s['sucursal_id'],
                          'sucursal' => $s['sucursal_nombre'], 'comprobante_tipo_id' => (int) $t['comprobante_tipo_id'],
                          'comprobante_tipo' => (string) ($t['comprobante_tipo'] ?? $t['nombre'] ?? '')];
            }
        }
        return $out;
    }
}

if (!function_exists('vmos_items')) {
    /** Ítems del request: solo producto, presentación y cantidad (precios y descuentos los pone el servidor). */
    function vmos_items(): array
    {
        $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
        if (!is_array($items)) {
            throw new InvalidArgumentException('Ítems inválidos.');
        }
        return array_map(fn($i) => ['producto_id' => intval($i['producto_id'] ?? 0), 'presentacion_id' => intval($i['presentacion_id'] ?? 0),
                                    'cantidad' => (float) ($i['cantidad'] ?? 0)], array_values($items));
    }
}

if (!function_exists('vmos_lineas_factura')) {
    /** Líneas valorizadas (pven_valorizar) → detalle de la factura de venta (el modelo calcula los importes). */
    function vmos_lineas_factura(array $valorizado): array
    {
        return array_map(function (array $d): array {
            $l = ['producto_id' => $d['producto_id'], 'cantidad' => $d['cantidad'], 'precio_unitario' => $d['precio_unitario'],
                  'descuento_general_pct' => $d['descuento_general_pct'], 'iva_alicuota_id' => $d['iva_alicuota_id'], 'iva_porcentaje' => $d['iva_porcentaje']];
            if (!empty($d['presentacion_id'])) {
                $l += ['presentacion_id' => $d['presentacion_id'], 'presentacion_cantidad' => $d['presentacion_cantidad'], 'presentacion_precio' => $d['presentacion_precio']];
            }
            return $l;
        }, $valorizado['detalles']);
    }
}

if (!function_exists('vmos_despachar')) {
    function vmos_despachar(mysqli $conexion, array $ctx, int $pagina_mostrador, int $pagina_facturas): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $empresa_id = (int) $ctx['empresa_id'];
        $usuario_id = (int) $ctx['usuario_id'];
        $responder = function (bool $ok, $data = null, string $msg = '', int $status = 200): void {
            if (ob_get_length()) {
                ob_clean();
            }
            http_response_code($status);
            echo json_encode(['success' => $ok, 'data' => $data, 'message' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        };
        if (!vmos_activo($conexion, $empresa_id)) {
            $responder(false, null, 'El mostrador no está habilitado para la empresa (Configuración > Empresas).', 403);
        }
        $accion = (string) ($_GET['accion'] ?? $_POST['accion'] ?? '');
        $pv_pedido = function (int $entidad_id) use ($conexion, $empresa_id, $pagina_facturas): array {
            $pvs = vmos_puntos_venta($conexion, $empresa_id, $pagina_facturas, $entidad_id);
            $pedido = intval($_REQUEST['punto_venta_id'] ?? 0);
            foreach ($pvs as $pv) {
                if ($pv['punto_venta_id'] === $pedido) {
                    return $pv;
                }
            }
            if (!$pvs) {
                throw new InvalidArgumentException('No tenés un punto de venta habilitado para facturar en el mostrador.');
            }
            return $pvs[0];
        };
        try {
            switch ($accion) {
                case 'inicio':
                    $m = vfMaestrosCobro($conexion, $empresa_id);
                    $medios = array_values(array_filter($m['medios_pago'] ?? [], fn($x) => in_array($x['medio_pago_tipo'] ?? '', ['EFECTIVO', 'TRANSFERENCIA', 'CHEQUE_TERCERO', 'TARJETA', 'TARJETA_DEBITO', 'TARJETA_CREDITO'], true)));
                    $lista = pven_cliente_o_cf($conexion, $empresa_id, 0);
                    $responder(true, [
                        'puntos_venta' => vmos_puntos_venta($conexion, $empresa_id, $pagina_facturas, 0),
                        'medios' => array_map(fn($x) => ['medio_pago_id' => (int) $x['medio_pago_id'], 'nombre' => $x['medio_pago'], 'tipo' => $x['medio_pago_tipo']], $medios),
                        'cuentas' => array_map(fn($x) => ['tesoreria_cuenta_id' => (int) $x['tesoreria_cuenta_id'], 'nombre' => $x['tesoreria_cuenta'] ?? '',
                                                           'tipo' => $x['cuenta_tipo'] ?? ''], $m['cuentas_tesoreria'] ?? []),
                        'lista_mostrador' => $lista['lista_precio_id'],
                        'puede_facturar' => permisos_puede($conexion, $pagina_mostrador, 'facturar'),
                    ]);

                case 'clientes':
                    $q = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
                    $responder(true, pven_filas($conexion,
                        "SELECT e.entidad_id, e.entidad_nombre AS nombre, e.cuit FROM gestion__entidades e
                          WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI') . "
                            AND (e.entidad_nombre LIKE ? OR e.entidad_fantasia LIKE ? OR e.cuit LIKE ?) ORDER BY e.entidad_nombre LIMIT 20",
                        'isss', [$empresa_id, $q, $q, $q]));

                case 'buscar':
                    $entidad = intval($_GET['entidad_id'] ?? 0);
                    $pv = $pv_pedido($entidad);
                    $enc = pven_buscar_productos($conexion, $empresa_id, (string) ($_GET['q'] ?? ''));
                    $of = pven_ofertas($conexion, $empresa_id, $entidad, array_column($enc, 'producto_id'), $pv['sucursal_id']);
                    $out = [];
                    foreach ($enc as $e) {
                        if (isset($of['productos'][$e['producto_id']])) {
                            $out[] = $of['productos'][$e['producto_id']] + ['presentacion_escaneada' => $e['presentacion_id']];
                        }
                    }
                    $responder(true, ['productos' => $out]);

                case 'valorizar':
                    csrf_exigir();
                    $entidad = intval($_POST['entidad_id'] ?? 0);
                    $items = vmos_items();
                    if (!$items) {
                        $responder(true, ['lineas' => [], 'cabecera' => ['subtotal' => 0, 'impuestos' => 0, 'total' => 0]]);
                    }
                    $pv = $pv_pedido($entidad);
                    $v = pven_valorizar($conexion, $empresa_id, $entidad, $items, ['sucursal_id' => $pv['sucursal_id'], 'usuario_id' => $usuario_id]);
                    // Comprobante no fiscal (Factura X, comprobante_fiscal_id = 0): no lleva IVA, igual que guardarVentaFactura()
                    $cf = pven_filas($conexion, "SELECT comprobante_fiscal_id FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? LIMIT 1", 'i', [(int) $pv['comprobante_tipo_id']]);
                    $comprobante_fiscal_id = intval($cf[0]['comprobante_fiscal_id'] ?? 0);
                    if ($comprobante_fiscal_id === 0) {
                        foreach ($v['detalles'] as $idx => $det) {
                            $neto = round((float) ($det['neto_gravado'] ?? 0), 2);
                            $v['detalles'][$idx]['iva_porcentaje'] = 0;
                            $v['detalles'][$idx]['iva_importe'] = 0;
                            $v['detalles'][$idx]['total_linea'] = $neto;
                        }
                        $sub = round(array_sum(array_column($v['detalles'], 'neto_gravado')), 2);
                        $v['cabecera']['subtotal'] = $sub;
                        $v['cabecera']['impuestos'] = 0.0;
                        $v['cabecera']['total'] = $sub;
                    }
                    $responder(true, ['lineas' => array_map(fn($d, $l) => $l + ['total_linea' => $d['total_linea']], $v['detalles'], $v['lineas']),
                                      'cabecera' => $v['cabecera'],
                                      'comprobante_tipo' => $pv['comprobante_tipo']]);

                case 'facturar':
                    csrf_exigir();
                    permisos_exigir_funcion($conexion, $pagina_mostrador, 'facturar');
                    $entidad = intval($_POST['entidad_id'] ?? 0);
                    $pv = $pv_pedido($entidad);
                    $v = pven_valorizar($conexion, $empresa_id, $entidad, vmos_items(), ['sucursal_id' => $pv['sucursal_id'], 'usuario_id' => $usuario_id]);
                    $medios = json_decode((string) ($_POST['medios'] ?? '[]'), true);
                    if (!is_array($medios)) {
                        throw new InvalidArgumentException('Medios de pago inválidos.');
                    }
                    $r = guardarVentaFactura($conexion, $empresa_id, $pagina_facturas, [
                        'punto_venta_id' => $pv['punto_venta_id'], 'sucursal_id' => $pv['sucursal_id'], 'comprobante_tipo_id' => $pv['comprobante_tipo_id'],
                        'entidad_id' => $entidad, 'consumidor_final_nombre' => $entidad === 0 ? (mb_substr(trim((string) ($_POST['consumidor_final_nombre'] ?? '')), 0, 100) ?: 'Consumidor final') : '',
                        'f_emision' => date('Y-m-d'), 'f_vto' => date('Y-m-d'), 'moneda_id' => emp_moneda_base_id($conexion, $empresa_id), 'tipo_cambio' => 1,
                        'condicion_pago_id' => '', 'descuento_general_pct' => 0,
                        'observaciones' => mb_substr(trim((string) ($_POST['observaciones'] ?? 'Mostrador')), 0, 500) ?: 'Mostrador',
                        'detalles' => vmos_lineas_factura($v),
                    ]);
                    $fid = (int) ($r['venta_factura_id'] ?? 0);
                    if (empty($r['success']) || $fid <= 0) {
                        $responder(false, null, (string) ($r['error'] ?? $r['message'] ?? 'No se pudo guardar la factura.'));
                    }
                    $t1 = vfEjecutarTransicionEstado($conexion, $fid, 'confirma', $empresa_id, $pagina_facturas, null);
                    if (empty($t1['success'])) {
                        $responder(false, ['venta_factura_id' => $fid], 'La factura quedó en Borrador: ' . ($t1['error'] ?? $t1['message'] ?? 'no se pudo confirmar.'));
                    }
                    // Consumidor final: cobro de contado con los medios elegidos; con cliente, a su cuenta corriente
                    $t2 = vfEjecutarTransicionEstado($conexion, $fid, 'aprobada', $empresa_id, $pagina_facturas, $entidad === 0 ? $medios : null);
                    $f = pven_filas($conexion, "SELECT comprobante_nro, importe_total AS total, tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = ?", 'i', [$fid]);
                    $res = ['venta_factura_id' => $fid, 'comprobante_nro' => (int) ($f[0]['comprobante_nro'] ?? 0), 'total' => (float) ($f[0]['total'] ?? 0),
                            'estado' => (int) ($f[0]['tabla_estado_registro_id'] ?? 0), 'aprobada' => !empty($t2['success'])];
                    $responder(true, $res, !empty($t2['success']) ? 'Factura emitida.'
                        : 'La factura quedó confirmada sin aprobar: ' . ($t2['error'] ?? $t2['message'] ?? 'revisala en Facturas de venta.'));

                default:
                    $responder(false, null, 'Acción desconocida.', 400);
            }
        } catch (InvalidArgumentException $e) {
            $responder(false, null, $e->getMessage(), 422);
        }
    }
}
