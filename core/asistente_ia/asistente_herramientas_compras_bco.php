<?php
/**
 * Asistente IA — Módulo de Compras, Cobranzas Comunes y Conciliación Bancaria:
 *  - Cobranzas: listar_medios_cobro + proponer_cobranza (COBRANZA)
 *  - Conciliación bancaria: consultar_pendientes_conciliacion + proponer_conciliacion_bancaria (CONCILIAR_BANCO)
 *    + proponer_recibo_extracto (RECIBO_EXTRACTO)
 *  - Compras: consultar_compras_proveedor + proponer_factura_proveedor (FACTURA_PROVEEDOR)
 *    + proponer_orden_compra (ORDEN_COMPRA)
 *
 * Todas filtradas por permisos del perfil y revalidadas al confirmar.
 */

function asis_herramientas_compras_bco(array $ctx): array
{
    $out = [];

    // --- Cobranzas comunes ---
    if (asis_puede($ctx, 'cobranzas', 'agregar')) {
        $out[] = [
            'name' => 'listar_medios_cobro',
            'description' => 'Lista los medios de cobro activos (efectivo, transferencia, cheques, etc.) y cuentas de tesorería para armar una cobranza a clientes.',
            'input_schema' => ['type' => 'object', 'properties' => (object)[]],
        ];
        $out[] = [
            'name' => 'proponer_cobranza',
            'description' => 'Propone un recibo de cobranza (RCX) en Borrador con medios de cobro e imputaciones opcionales a facturas pendientes del cliente. No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id'    => ['type' => 'integer', 'description' => 'entidad_id del cliente (buscar_entidades)'],
                'f_emision'     => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = hoy)'],
                'observaciones' => ['type' => 'string'],
                'medios'        => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'medio_pago_id'       => ['type' => 'integer', 'description' => 'De listar_medios_cobro'],
                    'tesoreria_cuenta_id' => ['type' => 'integer', 'description' => 'Cuenta bancaria/caja destino'],
                    'importe'             => ['type' => 'number'],
                    'nro_operacion'       => ['type' => 'string'],
                ], 'required' => ['medio_pago_id', 'importe']]],
                'imputaciones'  => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'comprobante_destino_id' => ['type' => 'integer', 'description' => 'comprobante_id de consultar_ctacte_cliente'],
                    'importe_imputado'       => ['type' => 'number'],
                ], 'required' => ['comprobante_destino_id', 'importe_imputado']]],
            ], 'required' => ['entidad_id', 'medios']],
        ];
    }

    // --- Conciliación bancaria ---
    if (asis_tiene_acceso_pagina($ctx, 'bancos_conciliacion')) {
        $out[] = [
            'name' => 'consultar_pendientes_conciliacion',
            'description' => 'Consulta para una cuenta bancaria las líneas del extracto sin conciliar y los movimientos de tesorería sin conciliar, para detectar coincidencias.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cuenta_id'   => ['type' => 'integer', 'description' => 'tesoreria_cuenta_id del banco (listar_cuentas_tesoreria)'],
                'fecha_desde' => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
                'fecha_hasta' => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
            ], 'required' => ['cuenta_id']],
        ];
    }

    if (asis_puede($ctx, 'bancos_conciliacion', 'agregar')) {
        $out[] = [
            'name' => 'proponer_conciliacion_bancaria',
            'description' => 'Propone conciliar una o más líneas del extracto bancario contra uno o más movimientos de tesorería de la misma cuenta (la suma con signo debe coincidir). No graba hasta confirmar.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cuenta_id'      => ['type' => 'integer', 'description' => 'tesoreria_cuenta_id del banco'],
                'extracto_ids'   => ['type' => 'array', 'items' => ['type' => 'integer']],
                'movimiento_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'observaciones'  => ['type' => 'string'],
            ], 'required' => ['cuenta_id', 'extracto_ids', 'movimiento_ids']],
        ];
    }

    if (asis_puede($ctx, 'bancos_conciliacion', 'agregar') && asis_puede($ctx, 'cobranzas', 'agregar')) {
        $out[] = [
            'name' => 'proponer_recibo_extracto',
            'description' => 'Propone generar un recibo de cobranza desde una línea de crédito del extracto bancario que tenga el CUIT de un cliente. Puede quedar en Borrador o confirmarse y conciliarse en el acto.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'extracto_id'   => ['type' => 'integer', 'description' => 'extracto_id de buscar_extracto_bancario o consultar_pendientes_conciliacion'],
                'confirmar'     => ['type' => 'boolean', 'description' => 'true = confirma el recibo y concilia la línea; false = deja en Borrador'],
                'observaciones' => ['type' => 'string'],
            ], 'required' => ['extracto_id']],
        ];
    }

    // --- Compras (Facturas de Proveedores y Órdenes de Compra) ---
    $ver_compras = asis_tiene_acceso_pagina($ctx, 'facturas_proveedores') || asis_tiene_acceso_pagina($ctx, 'ordenes_compra');
    if ($ver_compras) {
        $out[] = [
            'name' => 'consultar_compras_proveedor',
            'description' => 'Consulta facturas de proveedores recientes, órdenes de compra pendientes y tipos de comprobante de compra habilitados para un proveedor o para la empresa.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id'  => ['type' => 'integer', 'description' => 'entidad_id del proveedor (opcional)'],
                'fecha_desde' => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
                'fecha_hasta' => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
            ]],
        ];
    }

    if (asis_puede($ctx, 'facturas_proveedores', 'agregar')) {
        $out[] = [
            'name' => 'proponer_factura_proveedor',
            'description' => 'Propone cargar una Factura de Proveedor en Borrador. Primero consultá buscar_entidades (proveedor), buscar_productos y consultar_compras_proveedor (para comprobante_tipo_id). No graba hasta confirmar.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id'          => ['type' => 'integer', 'description' => 'entidad_id del proveedor'],
                'comprobante_tipo_id' => ['type' => 'integer', 'description' => 'Id de tipo de comprobante de compra (ej. C_FA, C_FB, C_FC)'],
                'comprobante_pv'      => ['type' => 'integer', 'description' => 'Punto de venta del comprobante del proveedor'],
                'comprobante_nro'     => ['type' => 'integer', 'description' => 'Número del comprobante del proveedor'],
                'f_emision'           => ['type' => 'string', 'description' => 'AAAA-MM-DD'],
                'observaciones'       => ['type' => 'string'],
                'items'               => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'producto_id'     => ['type' => 'integer'],
                    'cantidad'        => ['type' => 'number'],
                    'precio_unitario' => ['type' => 'number', 'description' => 'Sin IVA'],
                    'iva_porcentaje'  => ['type' => 'number', 'description' => 'Opcional (por defecto el del producto o 21)'],
                ], 'required' => ['producto_id', 'cantidad', 'precio_unitario']]],
            ], 'required' => ['entidad_id', 'comprobante_tipo_id', 'comprobante_nro', 'f_emision', 'items']],
        ];
    }

    if (asis_puede($ctx, 'ordenes_compra', 'agregar')) {
        $out[] = [
            'name' => 'proponer_orden_compra',
            'description' => 'Propone armar una Orden de Compra en Borrador para un proveedor. Primero consultá buscar_entidades (proveedor) y buscar_productos. No graba hasta confirmar.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id'         => ['type' => 'integer', 'description' => 'entidad_id del proveedor'],
                'f_emision'          => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = hoy)'],
                'f_entrega_estimada' => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
                'observaciones'      => ['type' => 'string'],
                'items'              => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'producto_id'     => ['type' => 'integer'],
                    'cantidad'        => ['type' => 'number'],
                    'precio_unitario' => ['type' => 'number', 'description' => 'Sin IVA'],
                    'iva_porcentaje'  => ['type' => 'number'],
                ], 'required' => ['producto_id', 'cantidad', 'precio_unitario']]],
            ], 'required' => ['entidad_id', 'items']],
        ];
    }

    return $out;
}

function asis_ejecutar_herramienta_compras_bco(array $ctx, string $nombre, array $e): ?array
{
    switch ($nombre) {
        case 'listar_medios_cobro':               return asis_h_listar_medios_cobro($ctx);
        case 'proponer_cobranza':                 return asis_h_proponer_cobranza($ctx, $e);
        case 'consultar_pendientes_conciliacion': return asis_h_consultar_pendientes_conciliacion($ctx, $e);
        case 'proponer_conciliacion_bancaria':    return asis_h_proponer_conciliacion_bancaria($ctx, $e);
        case 'proponer_recibo_extracto':          return asis_h_proponer_recibo_extracto($ctx, $e);
        case 'consultar_compras_proveedor':       return asis_h_consultar_compras_proveedor($ctx, $e);
        case 'proponer_factura_proveedor':        return asis_h_proponer_factura_proveedor($ctx, $e);
        case 'proponer_orden_compra':             return asis_h_proponer_orden_compra($ctx, $e);
        default: return null;
    }
}

// ===================================================================================================
// 1. COBRANZAS COMUNES
// ===================================================================================================

function asis_h_listar_medios_cobro(array $ctx): array
{
    $medios = asis_filas($ctx['c'], "SELECT medio_pago_id, codigo, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo
                                     FROM gestion__medios_pago
                                     WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND aplica_cobro = 1
                                     ORDER BY orden, medio_pago", 'i', $ctx['empresa_id']);
    return ['medios_cobro' => $medios, 'cuentas_tesoreria' => asis_h_listar_cuentas_tesoreria($ctx)['cuentas']];
}

function asis_validar_cobranza(array $ctx, array $e): array
{
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $ent_id = intval($e['entidad_id'] ?? 0);
    $ent = asis_fila($c, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $ent_id, $emp);
    if (!$ent) throw new RuntimeException("Cliente ID $ent_id no encontrado.");

    $f_em = trim((string)($e['f_emision'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_em)) $f_em = date('Y-m-d');

    $medios_in = (array)($e['medios'] ?? []);
    if (!$medios_in) throw new RuntimeException('Agregá al menos un medio de cobro.');
    $medios = [];
    $tot_medios = 0.0;
    foreach ($medios_in as $m) {
        $mp_id = intval($m['medio_pago_id'] ?? 0);
        $mp = asis_fila($c, "SELECT medio_pago_id, medio_pago FROM gestion__medios_pago WHERE medio_pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 AND aplica_cobro = 1", 'ii', $mp_id, $emp);
        if (!$mp) throw new RuntimeException("Medio de cobro ID $mp_id inválido.");
        $imp = round(floatval($m['importe'] ?? 0), 2);
        if ($imp <= 0) throw new RuntimeException('Cada medio de cobro debe tener importe mayor a 0.');
        $tc_id = intval($m['tesoreria_cuenta_id'] ?? 0);
        $tc_nom = '';
        if ($tc_id > 0) {
            $tc = asis_fila($c, "SELECT tesoreria_cuenta FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $tc_id, $emp);
            if (!$tc) throw new RuntimeException("Cuenta de tesorería ID $tc_id no encontrada.");
            $tc_nom = $tc['tesoreria_cuenta'];
        }
        $medios[] = [
            'medio_pago_id'       => $mp_id,
            'medio_pago_nombre'   => $mp['medio_pago'],
            'tesoreria_cuenta_id' => $tc_id ?: null,
            'cuenta_nombre'       => $tc_nom,
            'importe'             => $imp,
            'importe_comprobante' => $imp,
            'nro_operacion'       => mb_substr(trim((string)($m['nro_operacion'] ?? '')), 0, 50),
            'f_valor'             => $f_em,
        ];
        $tot_medios = round($tot_medios + $imp, 2);
    }

    $cancelables = [];
    foreach (tesoreriaObtenerComprobantesCancelables($c, $emp, 'RCX', $ent_id) as $p) {
        $cancelables[intval($p['comprobante_id'])] = $p;
    }
    $imps = [];
    $tot_imp = 0.0;
    foreach ((array)($e['imputaciones'] ?? []) as $i) {
        $did = intval($i['comprobante_destino_id'] ?? 0);
        $monto = round(floatval($i['importe_imputado'] ?? 0), 2);
        if ($monto <= 0) continue;
        if (!isset($cancelables[$did])) throw new RuntimeException("El comprobante ID $did no está pendiente para este cliente.");
        $pend = floatval($cancelables[$did]['importe_pendiente']);
        if ($monto > $pend + 0.001) throw new RuntimeException("Lo imputado al comprobante $did supera su saldo ($pend).");
        $p = $cancelables[$did];
        $imps[] = [
            'comprobante_destino_id' => $did,
            'numero'                 => trim(($p['comprobante_codigo'] ?? ($p['codigo'] ?? '')) . ' ' . emp_numero_comprobante($c, $emp, intval($p['comprobante_pv'] ?? 0), intval($p['comprobante_nro'] ?? 0))),
            'importe_imputado'       => $monto,
        ];
        $tot_imp = round($tot_imp + $monto, 2);
    }
    if ($tot_imp > $tot_medios + 0.001) throw new RuntimeException('Lo imputado supera el total cobrado.');

    return [
        'entidad_id'     => $ent_id,
        'cliente_nombre' => $ent['entidad_nombre'],
        'f_emision'      => $f_em,
        'observaciones'  => mb_substr(trim((string)($e['observaciones'] ?? '')), 0, 255),
        'medios'         => $medios,
        'imputaciones'   => $imps,
        'total_medios'   => $tot_medios,
        'total_imputado' => $tot_imp,
        'a_cuenta'       => round(max(0.0, $tot_medios - $tot_imp), 2),
    ];
}

function asis_h_proponer_cobranza(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'cobranzas', 'crear cobranzas');
    $v = asis_validar_cobranza($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'COBRANZA', $v);
    return ['propuesta_id' => $pid, 'total' => $v['total_medios'], 'imputaciones' => count($v['imputaciones'])];
}

function asis_grabar_cobranza(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'cobranzas', 'crear cobranzas');
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once $ctx['modulo_dir'] . '/cobranzas_model.php';
    $v = asis_validar_cobranza($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $pvs = obtenerPuntosVentaCobranza($c, $emp, $ctx['paginas']['cobranzas'] ?: 105);
    $suc_id = !empty($pvs) ? intval($pvs[0]['sucursal_id']) : 0;
    $pv_id  = !empty($pvs) ? intval($pvs[0]['punto_venta_id']) : 0;
    if ($suc_id <= 0) {
        $s = asis_fila($c, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id ASC LIMIT 1", 'i', $emp);
        $suc_id = intval($s['sucursal_id'] ?? 1);
    }
    try {
        $cob_id = guardarCobranzaBorrador($c, [
            'empresa_id'     => $emp,
            'sucursal_id'    => $suc_id,
            'comprobante_pv' => $pv_id,
            'entidad_id'     => $v['entidad_id'],
            'f_emision'      => $v['f_emision'],
            'observaciones'  => $v['observaciones'],
            'usuario_id'     => $ctx['usuario_id'],
            'medios'         => $v['medios'],
            'imputaciones'   => $v['imputaciones'],
        ]);
    } catch (Exception $ex) {
        throw new RuntimeException($ex->getMessage());
    }
    return [intval($cob_id), "Cobranza de {$v['cliente_nombre']} guardada en Borrador por " . fmt_moneda($v['total_medios']) . '.'];
}

// ===================================================================================================
// 2. CONCILIACIÓN BANCARIA ASISTIDA Y RECIBO DESDE EXTRACTO
// ===================================================================================================

function asis_h_consultar_pendientes_conciliacion(array $ctx, array $e): array
{
    if (!asis_tiene_acceso_pagina($ctx, 'bancos_conciliacion')) {
        throw new RuntimeException('No tenés acceso a Conciliación Bancaria.');
    }
    require_once __DIR__ . '/../bancos/bancos_conciliacion_service.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $cta_id = intval($e['cuenta_id'] ?? 0);
    if ($cta_id <= 0) throw new RuntimeException('Indicá cuenta_id del banco.');
    $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_desde'] ?? '')) ? $e['fecha_desde'] : null;
    $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_hasta'] ?? '')) ? $e['fecha_hasta'] : null;

    $movs = bco_tesoreria_pendientes($c, $emp, $cta_id, $desde, $hasta);
    $where = ["empresa_id = ?", "tesoreria_cuenta_id = ?", "estado IN ('PENDIENTE', 'CLASIFICADA')"];
    $t = 'ii'; $p = [$emp, $cta_id];
    if ($desde) { $where[] = "f_movimiento >= ?"; $t .= 's'; $p[] = $desde; }
    if ($hasta) { $where[] = "f_movimiento <= ?"; $t .= 's'; $p[] = $hasta; }
    $ext = asis_filas($c, "SELECT extracto_id, f_movimiento, descripcion, referencia, importe, estado
                           FROM gestion__bancos_extracto WHERE " . implode(' AND ', $where) . "
                           ORDER BY f_movimiento ASC, extracto_id ASC LIMIT 40", $t, ...$p);
    return ['extracto_pendiente' => $ext, 'tesoreria_pendiente' => array_slice($movs, 0, 40)];
}

function asis_validar_conciliacion_bancaria(array $ctx, array $e): array
{
    require_once __DIR__ . '/../bancos/bancos_conciliacion_service.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $cta_id = intval($e['cuenta_id'] ?? 0);
    $ext_ids = array_values(array_unique(array_filter(array_map('intval', (array)($e['extracto_ids'] ?? [])), fn($x) => $x > 0)));
    $mov_ids = array_values(array_unique(array_filter(array_map('intval', (array)($e['movimiento_ids'] ?? [])), fn($x) => $x > 0)));
    if ($cta_id <= 0 || !$ext_ids || !$mov_ids) {
        throw new RuntimeException('Indicá cuenta_id, extracto_ids y movimiento_ids para conciliar.');
    }
    $tc = asis_fila($c, "SELECT tesoreria_cuenta FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = ? AND empresa_id = ?", 'ii', $cta_id, $emp);
    if (!$tc) throw new RuntimeException('Cuenta bancaria no encontrada.');

    $phE = implode(',', array_fill(0, count($ext_ids), '?'));
    $ext = asis_filas($c, "SELECT extracto_id, f_movimiento, descripcion, importe, estado
                           FROM gestion__bancos_extracto
                           WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND extracto_id IN ($phE)",
                      'ii' . str_repeat('i', count($ext_ids)), $emp, $cta_id, ...$ext_ids);
    if (count($ext) !== count($ext_ids)) throw new RuntimeException('Alguna línea del extracto no existe o es de otra cuenta.');
    $sum_ext = 0.0;
    foreach ($ext as $l) {
        if (!in_array($l['estado'], ['PENDIENTE', 'CLASIFICADA'], true)) {
            throw new RuntimeException("La línea {$l['extracto_id']} ya está conciliada o ignorada.");
        }
        $sum_ext = round($sum_ext + floatval($l['importe']), 2);
    }

    $movs = bco_tesoreria_pendientes($c, $emp, $cta_id, null, null, $mov_ids);
    if (count($movs) !== count($mov_ids)) throw new RuntimeException('Algún movimiento de tesorería no existe o es de otra cuenta.');
    $sum_mov = 0.0;
    foreach ($movs as $m) {
        if (intval($m['conciliado'] ?? 0) === 1) throw new RuntimeException("El movimiento {$m['tesoreria_movimiento_id']} ya está conciliado.");
        $sig = ($m['tipo_movimiento'] ?? '') === 'EGRESO' ? -1 : 1;
        $sum_mov = round($sum_mov + $sig * abs(floatval($m['importe'])), 2);
    }
    if (abs($sum_ext - $sum_mov) >= 0.01) {
        throw new RuntimeException("Los importes no coinciden: banco ($sum_ext) ≠ tesorería ($sum_mov).");
    }

    return [
        'cuenta_id'      => $cta_id,
        'cuenta_nombre'  => $tc['tesoreria_cuenta'],
        'extracto_ids'   => $ext_ids,
        'movimiento_ids' => $mov_ids,
        'extracto'       => $ext,
        'movimientos'    => array_map(fn($m) => [
            'id'       => intval($m['tesoreria_movimiento_id']),
            'fecha'    => $m['f_movimiento'],
            'tipo'     => $m['tipo_movimiento'],
            'concepto' => $m['concepto'] ?? '',
            'importe'  => floatval($m['importe']),
        ], $movs),
        'importe_neto'   => $sum_ext,
        'observaciones'  => mb_substr(trim((string)($e['observaciones'] ?? 'Conciliado desde Asistente IA')), 0, 300),
    ];
}

function asis_h_proponer_conciliacion_bancaria(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'bancos_conciliacion', 'conciliar movimientos bancarios');
    $v = asis_validar_conciliacion_bancaria($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'CONCILIAR_BANCO', $v);
    return ['propuesta_id' => $pid, 'importe' => $v['importe_neto']];
}

function asis_grabar_conciliacion_bancaria(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'bancos_conciliacion', 'conciliar movimientos bancarios');
    require_once __DIR__ . '/../bancos/bancos_conciliacion_service.php';
    $v = asis_validar_conciliacion_bancaria($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $uid = $ctx['usuario_id'];

    // Si alguna línea está en PENDIENTE, clasificarla como MOVIMIENTO_PROPIO antes de conciliar
    $concepto = asis_fila($c, "SELECT concepto_id FROM gestion__bancos_conceptos WHERE empresa_id = ? AND naturaleza = 'MOVIMIENTO_PROPIO' AND tabla_estado_registro_id = 1 ORDER BY es_cobranza DESC, concepto_id ASC LIMIT 1", 'i', $emp);
    if ($concepto) {
        $cid = intval($concepto['concepto_id']);
        foreach ($v['extracto'] as $l) {
            if ($l['estado'] === 'PENDIENTE') {
                $eid = intval($l['extracto_id']);
                $st = mysqli_prepare($c, "UPDATE gestion__bancos_extracto SET concepto_id = ?, estado = 'CLASIFICADA', concepto_origen = 'MANUAL', usuario_modificacion_id = ? WHERE extracto_id = ? AND empresa_id = ? AND estado = 'PENDIENTE'");
                mysqli_stmt_bind_param($st, 'iiii', $cid, $uid, $eid, $emp);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
            }
        }
    }

    $conc_id = bco_conciliar($c, $emp, $uid, $v['cuenta_id'], $v['extracto_ids'], $v['movimiento_ids'], $v['observaciones']);
    return [intval($conc_id), "Conciliación #$conc_id registrada en {$v['cuenta_nombre']} por " . fmt_moneda(abs($v['importe_neto'])) . '.'];
}

function asis_h_proponer_recibo_extracto(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'bancos_conciliacion', 'generar recibos desde el extracto');
    asis_exigir_permiso($ctx, 'cobranzas', 'crear cobranzas');
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once $ctx['modulo_dir'] . '/cobranzas_model.php';
    require_once __DIR__ . '/../bancos/bancos_cobranzas_service.php';

    $ext_id = intval($e['extracto_id'] ?? 0);
    $pag_cob = intval($ctx['paginas']['cobranzas'] ?: 105);
    $prep = bco_cobranza_preparar($ctx['c'], $ctx['empresa_id'], $ext_id, $pag_cob);
    if (empty($prep['puntos_venta']) || empty($prep['medios'])) {
        throw new RuntimeException('Falta configurar punto de venta o medio de cobro bancario para generar recibos desde el extracto.');
    }
    $pv = $prep['puntos_venta'][0];
    $tipos = bco_cobranza_tipos($ctx['c'], $ctx['empresa_id'], intval($pv['punto_venta_id']), $pag_cob);
    if (empty($tipos)) throw new RuntimeException('No hay tipo de recibo habilitado en el punto de venta.');

    $datos = [
        'extracto_id'         => $ext_id,
        'fecha'               => $prep['linea']['f_movimiento'],
        'descripcion'         => $prep['linea']['descripcion'],
        'importe'             => floatval($prep['linea']['importe']),
        'cliente_nombre'      => $prep['cliente']['entidad_nombre'],
        'punto_venta_id'      => intval($pv['punto_venta_id']),
        'comprobante_tipo_id' => intval($tipos[0]['comprobante_tipo_id']),
        'medio_pago_id'       => intval($prep['medios'][0]['medio_pago_id']),
        'medio_nombre'        => $prep['medios'][0]['medio_pago'],
        'confirmar'           => !empty($e['confirmar']),
        'observaciones'       => mb_substr(trim((string)($e['observaciones'] ?? '')), 0, 500),
        'imputaciones'        => $prep['propuesta'],
    ];
    $pid = asis_guardar_propuesta($ctx, 'RECIBO_EXTRACTO', $datos);
    return ['propuesta_id' => $pid, 'cliente' => $datos['cliente_nombre'], 'importe' => $datos['importe'], 'imputaciones_propuestas' => count($datos['imputaciones'])];
}

function asis_grabar_recibo_extracto(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'bancos_conciliacion', 'generar recibos desde el extracto');
    asis_exigir_permiso($ctx, 'cobranzas', 'crear cobranzas');
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once $ctx['modulo_dir'] . '/cobranzas_model.php';
    require_once __DIR__ . '/../bancos/bancos_cobranzas_service.php';

    $pag_cob = intval($ctx['paginas']['cobranzas'] ?: 105);
    $r = bco_cobranza_generar($ctx['c'], $ctx['empresa_id'], $ctx['usuario_id'], intval($d['extracto_id']), $d, $pag_cob);
    $msg = !empty($r['confirmado'])
        ? "Recibo {$r['numero']} confirmado y conciliado con el banco (Conciliación #{$r['conciliacion_id']})."
        : "Recibo generado en Borrador (cobranza #{$r['cobranza_id']}).";
    return [intval($r['cobranza_id']), $msg];
}

// ===================================================================================================
// 3. COMPRAS: FACTURAS DE PROVEEDORES Y ÓRDENES DE COMPRA
// ===================================================================================================

function asis_h_consultar_compras_proveedor(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $ent_id = intval($e['entidad_id'] ?? 0);
    $w = ["fp.empresa_id = ?", "fp.tabla_estado_registro_id NOT IN (6, 38)"];
    $t = 'i'; $p = [$emp];
    if ($ent_id > 0) { $w[] = "fp.entidad_id = ?"; $t .= 'i'; $p[] = $ent_id; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_desde'] ?? ''))) { $w[] = "fp.f_emision >= ?"; $t .= 's'; $p[] = $e['fecha_desde']; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_hasta'] ?? ''))) { $w[] = "fp.f_emision <= ?"; $t .= 's'; $p[] = $e['fecha_hasta']; }

    $facturas = asis_filas($c, "SELECT fp.factura_proveedor_id, fp.f_emision, fp.comprobante_pv, fp.comprobante_nro,
                                       e.entidad_nombre AS proveedor, ct.codigo AS tipo_codigo, fp.total
                                FROM gestion__facturas_proveedores fp
                                JOIN gestion__entidades e ON e.entidad_id = fp.entidad_id
                                JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id
                                WHERE " . implode(' AND ', $w) . "
                                ORDER BY fp.f_emision DESC, fp.factura_proveedor_id DESC LIMIT 20", $t, ...$p);

    $tipos_compra = asis_filas($c, "SELECT comprobante_tipo_id, codigo, comprobante_tipo, letra
                                    FROM gestion__comprobantes_tipos
                                    WHERE (empresa_id = ? OR empresa_id = 0) AND tabla_estado_registro_id = 1
                                      AND codigo LIKE 'C_%'
                                    ORDER BY codigo", 'i', $emp);
    return ['facturas_recientes' => $facturas, 'tipos_comprobante_compra' => $tipos_compra];
}

function asis_validar_items_compra(array $ctx, array $items_in): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    if (!$items_in) throw new RuntimeException('Agregá al menos un ítem.');
    $alicuotas = [];
    foreach (asis_filas($c, "SELECT iva_alicuota_id, porcentaje FROM gestion__iva_alicuotas WHERE tabla_estado_registro_id = 1") as $a) {
        $alicuotas[number_format(floatval($a['porcentaje']), 2, '.', '')] = intval($a['iva_alicuota_id']);
    }
    $lineas = [];
    $sub = 0.0; $iva_tot = 0.0;
    foreach ($items_in as $it) {
        $pid = intval($it['producto_id'] ?? 0);
        $cant = floatval($it['cantidad'] ?? 0);
        $pu = round(floatval($it['precio_unitario'] ?? 0), 4);
        if ($pid <= 0 || $cant <= 0 || $pu <= 0) throw new RuntimeException('Cada ítem requiere producto_id, cantidad > 0 y precio_unitario > 0.');
        $prod = asis_fila($c, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.iva_alicuota_id, ia.porcentaje
                               FROM gestion__productos p
                               LEFT JOIN gestion__iva_alicuotas ia ON ia.iva_alicuota_id = p.iva_alicuota_id
                               WHERE p.producto_id = ? AND p.empresa_id = ? AND p.tabla_estado_registro_id = 1", 'ii', $pid, $emp);
        if (!$prod) throw new RuntimeException("Producto ID $pid no encontrado.");
        $iva_pct = isset($it['iva_porcentaje']) ? floatval($it['iva_porcentaje']) : floatval($prod['porcentaje'] ?? 21.0);
        $iva_key = number_format($iva_pct, 2, '.', '');
        $iva_id = $alicuotas[$iva_key] ?? intval($prod['iva_alicuota_id'] ?: 1);
        $neto = round($cant * $pu, 2);
        $iva_imp = round($neto * ($iva_pct / 100), 2);
        $tot = round($neto + $iva_imp, 2);
        $lineas[] = [
            'producto_id'     => $pid,
            'producto_codigo' => $prod['producto_codigo'] ?? '',
            'producto_nombre' => $prod['producto_nombre'],
            'cantidad'        => $cant,
            'precio_unitario' => $pu,
            'iva_alicuota_id' => $iva_id,
            'iva_porcentaje'  => $iva_pct,
            'neto_gravado'    => $neto,
            'iva_importe'     => $iva_imp,
            'total_linea'     => $tot,
        ];
        $sub = round($sub + $neto, 2);
        $iva_tot = round($iva_tot + $iva_imp, 2);
    }
    return ['lineas' => $lineas, 'subtotal' => $sub, 'impuestos' => $iva_tot, 'total' => round($sub + $iva_tot, 2)];
}

function asis_validar_factura_proveedor(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $ent_id = intval($e['entidad_id'] ?? 0);
    $ent = asis_fila($c, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $ent_id, $emp);
    if (!$ent) throw new RuntimeException("Proveedor ID $ent_id no encontrado.");

    $tipo_id = intval($e['comprobante_tipo_id'] ?? 0);
    $ct = asis_fila($c, "SELECT comprobante_tipo_id, codigo, comprobante_tipo FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND tabla_estado_registro_id = 1", 'i', $tipo_id);
    if (!$ct) throw new RuntimeException("Tipo de comprobante ID $tipo_id no encontrado.");

    $pv = intval($e['comprobante_pv'] ?? 0);
    $nro = intval($e['comprobante_nro'] ?? 0);
    if ($nro <= 0) throw new RuntimeException('Indicá el número del comprobante del proveedor.');
    $f_em = trim((string)($e['f_emision'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_em)) throw new RuntimeException('Fecha de emisión inválida (AAAA-MM-DD).');

    $dup = asis_fila($c, "SELECT factura_proveedor_id FROM gestion__facturas_proveedores WHERE empresa_id = ? AND entidad_id = ? AND comprobante_tipo_id = ? AND comprobante_pv = ? AND comprobante_nro = ? AND tabla_estado_registro_id NOT IN (6, 38) LIMIT 1",
                     'iiiii', $emp, $ent_id, $tipo_id, $pv, $nro);
    if ($dup) throw new RuntimeException("Ya existe ese comprobante cargado para este proveedor (ID {$dup['factura_proveedor_id']}).");

    $calc = asis_validar_items_compra($ctx, (array)($e['items'] ?? []));
    return [
        'entidad_id'          => $ent_id,
        'proveedor_nombre'    => $ent['entidad_nombre'],
        'comprobante_tipo_id' => $tipo_id,
        'comprobante_tipo'    => $ct['codigo'] . ' - ' . $ct['comprobante_tipo'],
        'comprobante_pv'      => $pv,
        'comprobante_nro'     => $nro,
        'f_emision'           => $f_em,
        'observaciones'       => mb_substr(trim((string)($e['observaciones'] ?? '')), 0, 255),
        'lineas'              => $calc['lineas'],
        'subtotal'            => $calc['subtotal'],
        'impuestos'           => $calc['impuestos'],
        'total'               => $calc['total'],
    ];
}

function asis_h_proponer_factura_proveedor(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'facturas_proveedores', 'cargar facturas de proveedores');
    $v = asis_validar_factura_proveedor($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'FACTURA_PROVEEDOR', $v);
    return ['propuesta_id' => $pid, 'total' => $v['total'], 'lineas' => count($v['lineas'])];
}

function asis_grabar_factura_proveedor(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'facturas_proveedores', 'cargar facturas de proveedores');
    $v = asis_validar_factura_proveedor($ctx, $d);
    require_once $ctx['modulo_dir'] . '/facturas_proveedores_model.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $suc = asis_fila($c, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id ASC LIMIT 1", 'i', $emp);
    $r = agregarFacturaProveedor($c, [
        'empresa_idx'         => $emp,
        'pagina_idx'          => intval($ctx['paginas']['facturas_proveedores'] ?: 557),
        'entidad_id'          => $v['entidad_id'],
        'comprobante_tipo_id' => $v['comprobante_tipo_id'],
        'comprobante_pv'      => $v['comprobante_pv'],
        'comprobante_nro'     => $v['comprobante_nro'],
        'f_emision'           => $v['f_emision'],
        'moneda_id'           => emp_moneda_id($c, $emp, 0),
        'sucursal_id'         => intval($suc['sucursal_id'] ?? 1),
        'subtotal'            => $v['subtotal'],
        'impuestos'           => $v['impuestos'],
        'observaciones'       => $v['observaciones'],
        'detalles'            => $v['lineas'],
    ]);
    if (empty($r['resultado'])) throw new RuntimeException($r['error'] ?? 'No se pudo guardar la factura de proveedor.');
    return [intval($r['factura_proveedor_id']), "Factura de proveedor {$v['proveedor_nombre']} guardada en Borrador por " . fmt_moneda($v['total']) . '.'];
}

function asis_validar_orden_compra(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $ent_id = intval($e['entidad_id'] ?? 0);
    $ent = asis_fila($c, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $ent_id, $emp);
    if (!$ent) throw new RuntimeException("Proveedor ID $ent_id no encontrado.");

    $f_em = trim((string)($e['f_emision'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_em)) $f_em = date('Y-m-d');
    $f_ent = trim((string)($e['f_entrega_estimada'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_ent)) $f_ent = $f_em;

    $calc = asis_validar_items_compra($ctx, (array)($e['items'] ?? []));
    return [
        'entidad_id'         => $ent_id,
        'proveedor_nombre'   => $ent['entidad_nombre'],
        'f_emision'          => $f_em,
        'f_entrega_estimada' => $f_ent,
        'observaciones'      => mb_substr(trim((string)($e['observaciones'] ?? '')), 0, 255),
        'lineas'             => $calc['lineas'],
        'subtotal'           => $calc['subtotal'],
        'impuestos'          => $calc['impuestos'],
        'total'              => $calc['total'],
    ];
}

function asis_h_proponer_orden_compra(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'ordenes_compra', 'crear órdenes de compra');
    $v = asis_validar_orden_compra($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'ORDEN_COMPRA', $v);
    return ['propuesta_id' => $pid, 'total' => $v['total'], 'lineas' => count($v['lineas'])];
}

function asis_grabar_orden_compra(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'ordenes_compra', 'crear órdenes de compra');
    $v = asis_validar_orden_compra($ctx, $d);
    require_once $ctx['modulo_dir'] . '/ordenes_compra_model.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $pv = asis_fila($c, "SELECT punto_venta_id, sucursal_id FROM gestion__puntos_venta WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY (sistema_emision = 'NO_FISCAL') DESC, punto_venta_id ASC LIMIT 1", 'i', $emp);
    $ct = asis_fila($c, "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE codigo IN ('OC', 'C_OC') AND (empresa_id = ? OR empresa_id = 0) AND tabla_estado_registro_id = 1 ORDER BY empresa_id DESC LIMIT 1", 'i', $emp);
    if (!$pv || !$ct) throw new RuntimeException('Falta configurar punto de venta o tipo de comprobante OC para la empresa.');

    $r = agregarOrdenCompra($c, [
        'empresa_idx'         => $emp,
        'pagina_idx'          => intval($ctx['paginas']['ordenes_compra'] ?: 551),
        'entidad_id'          => $v['entidad_id'],
        'comprobante_tipo_id' => intval($ct['comprobante_tipo_id']),
        'moneda_id'           => emp_moneda_id($c, $emp, 0),
        'sucursal_id'         => intval($pv['sucursal_id'] ?: 1),
        'punto_venta_id'      => intval($pv['punto_venta_id']),
        'f_emision'           => $v['f_emision'],
        'f_entrega_estimada'  => $v['f_entrega_estimada'],
        'subtotal'            => $v['subtotal'],
        'impuestos'           => $v['impuestos'],
        'total'               => $v['total'],
        'observaciones'       => $v['observaciones'],
        'detalles'            => $v['lineas'],
    ]);
    if (empty($r['resultado'])) throw new RuntimeException($r['error'] ?? 'No se pudo guardar la orden de compra.');
    return [intval($r['orden_compra_id']), "Orden de Compra a {$v['proveedor_nombre']} guardada en Borrador por " . fmt_moneda($v['total']) . '.'];
}
