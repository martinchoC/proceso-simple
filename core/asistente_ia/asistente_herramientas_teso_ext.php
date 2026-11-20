<?php
/**
 * Asistente IA — Módulo extendido de Tesorería, Cuenta Corriente y Órdenes de Pago:
 *  - Cuenta Corriente de Clientes: consultar_ctacte_cliente + proponer_imputacion_saldo_favor (IMPUTAR_SALDO_FAVOR)
 *  - Cuentas de Tesorería (Cajas/Bancos/FCI): listar_catalogos_tesoreria + proponer_cuenta_tesoreria (CUENTA_TESORERIA)
 *  - Órdenes de Pago: consultar_deudas_pagar + proponer_orden_pago (ORDEN_PAGO)
 *
 * Todas filtradas por los permisos del perfil del usuario y revalidadas al confirmar.
 */

function asis_herramientas_teso_ext(array $ctx): array
{
    $out = [];

    if (asis_tiene_acceso_pagina($ctx, 'clientes_ctacte')) {
        $out[] = [
            'name' => 'consultar_ctacte_cliente',
            'description' => 'Consulta la cuenta corriente de un cliente: saldo, deuda pendiente, vencido, saldo a favor sin aplicar, listado de facturas adeudadas y listado de recibos/NC sin aplicar (con comprobante_id).',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id' => ['type' => 'integer', 'description' => 'entidad_id del cliente (buscar_entidades)'],
            ], 'required' => ['entidad_id']],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'clientes_ctacte') && asis_puede($ctx, 'cobranzas', 'agregar')) {
        $out[] = [
            'name' => 'proponer_imputacion_saldo_favor',
            'description' => 'Propone imputar un saldo a favor de cliente (recibo RCX a cuenta o Nota de Crédito) contra una factura adeudada del mismo cliente. Al confirmar emite un recibo RCX en $0 y su asiento contable de compensación.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id'      => ['type' => 'integer', 'description' => 'entidad_id del cliente'],
                'comp_origen_id'  => ['type' => 'integer', 'description' => 'comprobante_id del recibo a cuenta o NC (de consultar_ctacte_cliente)'],
                'comp_destino_id' => ['type' => 'integer', 'description' => 'comprobante_id de la factura adeudada (de consultar_ctacte_cliente)'],
                'importe'         => ['type' => 'number',  'description' => 'Opcional: importe a imputar (omitir = máximo posible entre ambos saldos)'],
            ], 'required' => ['entidad_id', 'comp_origen_id', 'comp_destino_id']],
        ];
    }

    if (asis_puede($ctx, 'tesoreria_cuentas', 'agregar')) {
        $out[] = [
            'name' => 'listar_catalogos_tesoreria',
            'description' => 'Devuelve bancos (conf__bancos), monedas activas y sucursales activas de la empresa para dar de alta una cuenta de tesorería (caja, banco o FCI).',
            'input_schema' => ['type' => 'object', 'properties' => (object)[]],
        ];
        $out[] = [
            'name' => 'proponer_cuenta_tesoreria',
            'description' => 'Propone crear una cuenta de tesorería (CAJA, BANCO, VALORES o CUPONES; para un FCI usar cuenta_tipo=BANCO y banco_cuenta_tipo=VIRTUAL vinculada a la cuenta contable 1.1.01.21 Fondos Comunes Inversión). No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cuenta_tipo'            => ['type' => 'string', 'enum' => ['CAJA', 'BANCO', 'VALORES', 'CUPONES']],
                'tesoreria_cuenta'       => ['type' => 'string', 'description' => 'Nombre de la cuenta (ej: FCI Banco Provincia $)'],
                'cont_cuenta_id'         => ['type' => 'integer', 'description' => 'Cuenta contable imputable del Plan de Cuentas'],
                'moneda_id'              => ['type' => 'integer', 'description' => 'Opcional: id de moneda (omitir = moneda local)'],
                'sucursal_id'            => ['type' => 'integer', 'description' => 'Obligatorio para CAJA'],
                'banco_id'               => ['type' => 'integer', 'description' => 'Obligatorio para BANCO (de listar_catalogos_tesoreria)'],
                'banco_cuenta_tipo'      => ['type' => 'string', 'enum' => ['CC', 'CA', 'VIRTUAL'], 'description' => 'Obligatorio para BANCO (CC=Cuenta Corriente, CA=Caja de Ahorro, VIRTUAL=FCI/Billetera)'],
                'codigo'                 => ['type' => 'string', 'description' => 'Código corto opcional (letras/números/-/_)'],
                'numero'                 => ['type' => 'string'],
                'cbu'                    => ['type' => 'string', 'description' => '22 dígitos si corresponde'],
                'alias'                  => ['type' => 'string'],
                'permite_saldo_negativo' => ['type' => 'boolean'],
            ], 'required' => ['cuenta_tipo', 'tesoreria_cuenta', 'cont_cuenta_id']],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'pagos')) {
        $out[] = [
            'name' => 'consultar_deudas_pagar',
            'description' => 'Consulta los comprobantes pendientes de pago de un proveedor, empleado u organismo (facturas de compra y obligaciones OBL/I_LIQ/SOL_ANT cancelables por OP), más los medios de pago y cuentas de tesorería disponibles.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id' => ['type' => 'integer', 'description' => 'entidad_id del beneficiario (buscar_entidades)'],
            ], 'required' => ['entidad_id']],
        ];
    }

    if (asis_puede($ctx, 'pagos', 'agregar')) {
        $out[] = [
            'name' => 'proponer_orden_pago',
            'description' => 'Propone una Orden de Pago (OP) en Borrador para pagar facturas de compra u obligaciones de pago (sueldos, impuestos, anticipos/retiros de socios). No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'entidad_id'    => ['type' => 'integer', 'description' => 'entidad_id del beneficiario'],
                'f_emision'     => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = hoy)'],
                'observaciones' => ['type' => 'string'],
                'medios'        => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'medio_pago_id'       => ['type' => 'integer', 'description' => 'De consultar_deudas_pagar'],
                    'tesoreria_cuenta_id' => ['type' => 'integer', 'description' => 'Cuenta de banco o caja de donde sale el dinero'],
                    'importe'             => ['type' => 'number'],
                    'nro_operacion'       => ['type' => 'string'],
                ], 'required' => ['medio_pago_id', 'importe']]],
                'imputaciones'  => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'comprobante_destino_id' => ['type' => 'integer', 'description' => 'comprobante_id de consultar_deudas_pagar'],
                    'importe_imputado'       => ['type' => 'number'],
                ], 'required' => ['comprobante_destino_id', 'importe_imputado']]],
            ], 'required' => ['entidad_id', 'medios']],
        ];
    }

    return $out;
}

function asis_ejecutar_herramienta_teso_ext(array $ctx, string $nombre, array $e): ?array
{
    switch ($nombre) {
        case 'consultar_ctacte_cliente':        return asis_h_consultar_ctacte_cliente($ctx, $e);
        case 'proponer_imputacion_saldo_favor': return asis_h_proponer_imputacion_saldo_favor($ctx, $e);
        case 'listar_catalogos_tesoreria':      return asis_h_listar_catalogos_tesoreria($ctx);
        case 'proponer_cuenta_tesoreria':       return asis_h_proponer_cuenta_tesoreria($ctx, $e);
        case 'consultar_deudas_pagar':          return asis_h_consultar_deudas_pagar($ctx, $e);
        case 'proponer_orden_pago':             return asis_h_proponer_orden_pago($ctx, $e);
        default: return null;
    }
}

// ===================================================================================================
// 1. CUENTA CORRIENTE DE CLIENTES E IMPUTACIÓN DE SALDO A FAVOR
// ===================================================================================================

function asis_h_consultar_ctacte_cliente(array $ctx, array $e): array
{
    if (!asis_tiene_acceso_pagina($ctx, 'clientes_ctacte')) {
        throw new RuntimeException('No tenés acceso a Cuenta Corriente de Clientes.');
    }
    require_once $ctx['modulo_dir'] . '/clientes_ctacte_model.php';
    $ent_id = intval($e['entidad_id'] ?? 0);
    if ($ent_id <= 0) throw new RuntimeException('Indicá el entidad_id del cliente.');
    vcar_exigir_cliente($ctx['c'], $ctx['empresa_id'], $ctx['usuario_id'], $ent_id);

    $resumen = ctacteResumen($ctx['c'], $ctx['empresa_id'], $ent_id);
    $pend = ctactePendientes($ctx['c'], $ctx['empresa_id'], $ent_id);
    $fmtComp = fn($r) => [
        'comprobante_id' => $r['comprobante_id'],
        'numero'         => trim(($r['tipo_codigo'] ?? '') . ' ' . str_pad((string)$r['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$r['comprobante_nro'], 8, '0', STR_PAD_LEFT)),
        'f_emision'      => $r['f_emision'],
        'f_vto'          => $r['f_vto'],
        'moneda_id'      => $r['moneda_id'],
        'importe_total'  => $r['importe_total'],
        'pendiente'      => $r['pendiente'],
    ];
    return [
        'resumen' => $resumen,
        'deuda'   => array_map($fmtComp, array_slice($pend['deuda'], 0, 30)),
        'a_favor' => array_map($fmtComp, array_slice($pend['a_favor'], 0, 20)),
    ];
}

function asis_validar_imputacion_saldo_favor(array $ctx, array $e): array
{
    require_once $ctx['modulo_dir'] . '/clientes_ctacte_model.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $ent_id = intval($e['entidad_id'] ?? 0);
    $orig_id = intval($e['comp_origen_id'] ?? 0);
    $dest_id = intval($e['comp_destino_id'] ?? 0);
    if ($ent_id <= 0 || $orig_id <= 0 || $dest_id <= 0) {
        throw new RuntimeException('Indicá cliente, comprobante origen (saldo a favor) y comprobante destino (factura adeudada).');
    }
    vcar_exigir_cliente($c, $emp, $ctx['usuario_id'], $ent_id);

    $ent = asis_fila($c, "SELECT entidad_nombre FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', $ent_id, $emp);
    if (!$ent) throw new RuntimeException('Cliente no encontrado.');

    $sqlC = "SELECT c.comprobante_id, c.entidad_id, c.comprobante_tipo_id, c.comprobante_pv, c.comprobante_nro,
                    c.f_emision, c.moneda_id, c.importe_pendiente, c.tabla_estado_registro_id, ct.codigo AS tipo_codigo
             FROM gestion__comprobantes c
             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
             WHERE c.comprobante_id = ? AND c.empresa_id = ?";
    $orig = asis_fila($c, $sqlC, 'ii', $orig_id, $emp);
    $dest = asis_fila($c, $sqlC, 'ii', $dest_id, $emp);
    if (!$orig || intval($orig['entidad_id']) !== $ent_id || floatval($orig['importe_pendiente']) <= 0.001) {
        throw new RuntimeException('El comprobante a favor no pertenece al cliente o no tiene saldo disponible.');
    }
    if (!$dest || intval($dest['entidad_id']) !== $ent_id || floatval($dest['importe_pendiente']) <= 0.001) {
        throw new RuntimeException('El comprobante adeudado no pertenece al cliente o no tiene saldo pendiente.');
    }
    if (intval($orig['moneda_id']) !== intval($dest['moneda_id'])) {
        throw new RuntimeException('El saldo a favor y la factura están en monedas distintas.');
    }
    $permite = asis_fila($c, "SELECT 1 AS x FROM gestion__comprobantes_tipos_cancelaciones
                              WHERE empresa_id = ? AND comprobante_tipo_origen_id = ? AND comprobante_tipo_destino_id = ? AND tabla_estado_registro_id = 1 LIMIT 1",
                         'iii', $emp, intval($orig['comprobante_tipo_id']), intval($dest['comprobante_tipo_id']));
    if (!$permite) throw new RuntimeException('La matriz de cancelaciones no permite imputar esos tipos de comprobante.');

    $max = round(min(floatval($orig['importe_pendiente']), floatval($dest['importe_pendiente'])), 2);
    $imp = round(floatval($e['importe'] ?? 0), 2);
    if ($imp <= 0) $imp = $max;
    if ($imp > $max + 0.001) throw new RuntimeException('El importe supera el saldo disponible (' . fmt_moneda($max) . ').');

    return [
        'entidad_id'      => $ent_id,
        'cliente_nombre'  => $ent['entidad_nombre'],
        'comp_origen_id'  => $orig_id,
        'origen_numero'   => trim($orig['tipo_codigo'] . ' ' . emp_numero_comprobante($c, $emp, intval($orig['comprobante_pv']), intval($orig['comprobante_nro']))),
        'origen_fecha'    => $orig['f_emision'],
        'comp_destino_id' => $dest_id,
        'destino_numero'  => trim($dest['tipo_codigo'] . ' ' . emp_numero_comprobante($c, $emp, intval($dest['comprobante_pv']), intval($dest['comprobante_nro']))),
        'destino_fecha'   => $dest['f_emision'],
        'importe'         => $imp,
    ];
}

function asis_h_proponer_imputacion_saldo_favor(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'cobranzas', 'imputar saldos a favor');
    $v = asis_validar_imputacion_saldo_favor($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'IMPUTAR_SALDO_FAVOR', $v);
    return ['propuesta_id' => $pid, 'importe' => $v['importe'], 'origen' => $v['origen_numero'], 'destino' => $v['destino_numero']];
}

function asis_grabar_imputacion_saldo_favor(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'cobranzas', 'imputar saldos a favor');
    require_once $ctx['modulo_dir'] . '/clientes_ctacte_model.php';
    $v = asis_validar_imputacion_saldo_favor($ctx, $d);
    $res = ctacteAplicarSaldoFavor($ctx['c'], $ctx['empresa_id'], $ctx['usuario_id'],
        $v['entidad_id'], $v['comp_origen_id'], $v['comp_destino_id'], $v['importe']);
    $msg = "Imputado {$v['origen_numero']} contra {$v['destino_numero']} por " . fmt_moneda($res['importe_aplicado'])
         . " · Recibo en $0 N° {$res['recibo_numero']}"
         . (!empty($res['asiento_id']) ? " · Asiento #{$res['asiento_id']}" : '') . '.';
    return [intval($res['cobranza_id']), $msg];
}

// ===================================================================================================
// 2. ALTA DE CUENTAS DE TESORERÍA (CAJA / BANCO / FCI)
// ===================================================================================================

function asis_h_listar_catalogos_tesoreria(array $ctx): array
{
    require_once $ctx['modulo_dir'] . '/tesoreria_cuentas_model.php';
    return tcMaestros($ctx['c'], $ctx['empresa_id']);
}

function asis_validar_cuenta_tesoreria(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $tipo = strtoupper(trim((string)($e['cuenta_tipo'] ?? '')));
    if (!in_array($tipo, ['CAJA', 'BANCO', 'VALORES', 'CUPONES'], true)) {
        throw new RuntimeException('cuenta_tipo debe ser CAJA, BANCO, VALORES o CUPONES.');
    }
    $nombre = mb_substr(trim((string)($e['tesoreria_cuenta'] ?? '')), 0, 100);
    if ($nombre === '') throw new RuntimeException('Falta el nombre de la cuenta de tesorería.');

    $cta_id = intval($e['cont_cuenta_id'] ?? 0);
    $cta = asis_fila($c, "SELECT cont_cuenta_id, codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $cta_id, $emp);
    if (!$cta || intval($cta['es_imputable']) !== 1) throw new RuntimeException('La cuenta contable no existe o no es imputable.');

    $mon_id = intval($e['moneda_id'] ?? 0);
    if ($mon_id <= 0) $mon_id = emp_moneda_id($c, $emp, 0);
    $mon = asis_fila($c, "SELECT moneda_id, moneda, simbolo FROM gestion__monedas WHERE moneda_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $mon_id, $emp);
    if (!$mon) throw new RuntimeException('Moneda inválida para la empresa.');

    $suc_id = intval($e['sucursal_id'] ?? 0);
    $suc_nom = '';
    if ($tipo === 'CAJA' && $suc_id <= 0) {
        $s = asis_fila($c, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id ASC LIMIT 1", 'i', $emp);
        if ($s) { $suc_id = intval($s['sucursal_id']); $suc_nom = $s['sucursal_nombre']; }
    } elseif ($suc_id > 0) {
        $s = asis_fila($c, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", 'ii', $suc_id, $emp);
        if (!$s) throw new RuntimeException('La sucursal no pertenece a la empresa.');
        $suc_nom = $s['sucursal_nombre'];
    }

    $banco_id = 0;
    $banco_nom = '';
    $bct = '';
    if ($tipo === 'BANCO') {
        $banco_id = intval($e['banco_id'] ?? 0);
        $b = asis_fila($c, "SELECT banco_id, banco FROM conf__bancos WHERE banco_id = ?", 'i', $banco_id);
        if (!$b) throw new RuntimeException('Para cuenta tipo BANCO elegí un banco válido (listar_catalogos_tesoreria).');
        $banco_nom = $b['banco'];
        $bct = strtoupper(trim((string)($e['banco_cuenta_tipo'] ?? '')));
        if (!in_array($bct, ['CC', 'CA', 'VIRTUAL'], true)) {
            throw new RuntimeException('Para cuenta tipo BANCO indicá banco_cuenta_tipo: CC, CA o VIRTUAL (para FCI/billeteras).');
        }
    }

    $codigo = strtoupper(trim((string)($e['codigo'] ?? '')));
    if ($codigo !== '' && !preg_match('/^[A-Z0-9_\-]{1,20}$/', $codigo)) {
        throw new RuntimeException('Código inválido (solo letras, números, - y _, hasta 20 caracteres).');
    }
    $cbu = preg_replace('/\D/', '', (string)($e['cbu'] ?? ''));
    if ($tipo === 'BANCO' && $cbu !== '' && strlen($cbu) !== 22) {
        throw new RuntimeException('El CBU/CVU debe tener 22 dígitos.');
    }

    return [
        'cuenta_tipo'            => $tipo,
        'tesoreria_cuenta'       => $nombre,
        'codigo'                 => $codigo,
        'moneda_id'              => $mon_id,
        'moneda_texto'           => $mon['moneda'] . ' (' . $mon['simbolo'] . ')',
        'sucursal_id'            => $suc_id,
        'sucursal_nombre'        => $suc_nom,
        'cont_cuenta_id'         => $cta_id,
        'cont_cuenta_texto'      => $cta['codigo'] . ' - ' . $cta['nombre'],
        'banco_id'               => $banco_id,
        'banco_nombre'           => $banco_nom,
        'banco_cuenta_tipo'      => $bct,
        'numero'                 => mb_substr(trim((string)($e['numero'] ?? '')), 0, 50),
        'cbu'                    => $cbu,
        'alias'                  => mb_substr(trim((string)($e['alias'] ?? '')), 0, 50),
        'permite_saldo_negativo' => !empty($e['permite_saldo_negativo']),
    ];
}

function asis_h_proponer_cuenta_tesoreria(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'tesoreria_cuentas', 'crear cuentas de tesorería');
    $v = asis_validar_cuenta_tesoreria($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'CUENTA_TESORERIA', $v);
    return ['propuesta_id' => $pid, 'cuenta' => $v['tesoreria_cuenta'], 'tipo' => $v['cuenta_tipo']];
}

function asis_grabar_cuenta_tesoreria(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'tesoreria_cuentas', 'crear cuentas de tesorería');
    require_once $ctx['modulo_dir'] . '/tesoreria_cuentas_model.php';
    $v = asis_validar_cuenta_tesoreria($ctx, $d);
    try {
        $id = tcGuardar($ctx['c'], $ctx['empresa_id'], $ctx['usuario_id'], $v);
    } catch (Exception $ex) {
        throw new RuntimeException($ex->getMessage());
    }
    return [intval($id), "Cuenta de tesorería '{$v['tesoreria_cuenta']}' ({$v['cuenta_tipo']}) creada."];
}

// ===================================================================================================
// 3. ÓRDENES DE PAGO (pagos.php)
// ===================================================================================================

function asis_h_consultar_deudas_pagar(array $ctx, array $e): array
{
    if (!asis_tiene_acceso_pagina($ctx, 'pagos')) {
        throw new RuntimeException('No tenés acceso a Órdenes de Pago.');
    }
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    $ent_id = intval($e['entidad_id'] ?? 0);
    if ($ent_id <= 0) throw new RuntimeException('Indicá el entidad_id del beneficiario.');

    $pend = tesoreriaObtenerComprobantesCancelables($ctx['c'], $ctx['empresa_id'], 'OP', $ent_id);
    $medios = asis_filas($ctx['c'], "SELECT medio_pago_id, codigo, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo
                                     FROM gestion__medios_pago
                                     WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND aplica_pago = 1
                                     ORDER BY orden, medio_pago", 'i', $ctx['empresa_id']);
    $cuentas = asis_h_listar_cuentas_tesoreria($ctx)['cuentas'];

    $deudas = array_map(fn($p) => [
        'comprobante_id'    => intval($p['comprobante_id']),
        'tipo_codigo'       => $p['comprobante_codigo'] ?? ($p['codigo'] ?? ''),
        'numero'            => emp_numero_comprobante($ctx['c'], $ctx['empresa_id'], intval($p['comprobante_pv'] ?? 0), intval($p['comprobante_nro'] ?? 0)),
        'f_emision'         => $p['f_emision'] ?? '',
        'f_vto'             => $p['f_vto'] ?? '',
        'importe_total'     => floatval($p['importe_total'] ?? 0),
        'importe_pendiente' => floatval($p['importe_pendiente'] ?? 0),
    ], array_slice($pend, 0, 40));

    return ['deudas' => $deudas, 'medios_pago' => $medios, 'cuentas_tesoreria' => $cuentas];
}

function asis_validar_orden_pago(array $ctx, array $e): array
{
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $ent_id = intval($e['entidad_id'] ?? 0);
    $ent = asis_fila($c, "SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $ent_id, $emp);
    if (!$ent) throw new RuntimeException("Beneficiario ID $ent_id no encontrado.");

    $f_em = trim((string)($e['f_emision'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_em)) $f_em = date('Y-m-d');

    $medios_in = (array)($e['medios'] ?? []);
    if (!$medios_in) throw new RuntimeException('Agregá al menos un medio de pago.');
    $medios = [];
    $tot_medios = 0.0;
    foreach ($medios_in as $m) {
        $mp_id = intval($m['medio_pago_id'] ?? 0);
        $mp = asis_fila($c, "SELECT medio_pago_id, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo FROM gestion__medios_pago WHERE medio_pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 AND aplica_pago = 1", 'ii', $mp_id, $emp);
        if (!$mp) throw new RuntimeException("Medio de pago ID $mp_id inválido.");
        $imp = round(floatval($m['importe'] ?? 0), 2);
        if ($imp <= 0) throw new RuntimeException('Cada medio de pago debe tener importe mayor a 0.');
        $tc_id = intval($m['tesoreria_cuenta_id'] ?? 0);
        $tc_nom = '';
        if ($tc_id > 0) {
            $tc = asis_fila($c, "SELECT tesoreria_cuenta_id, tesoreria_cuenta FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $tc_id, $emp);
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
    foreach (tesoreriaObtenerComprobantesCancelables($c, $emp, 'OP', $ent_id) as $p) {
        $cancelables[intval($p['comprobante_id'])] = $p;
    }
    $imps = [];
    $tot_imp = 0.0;
    foreach ((array)($e['imputaciones'] ?? []) as $i) {
        $did = intval($i['comprobante_destino_id'] ?? 0);
        $monto = round(floatval($i['importe_imputado'] ?? 0), 2);
        if ($monto <= 0) continue;
        if (!isset($cancelables[$did])) throw new RuntimeException("El comprobante ID $did no está pendiente de pago para este beneficiario.");
        $pend = floatval($cancelables[$did]['importe_pendiente']);
        if ($monto > $pend + 0.001) throw new RuntimeException("El importe a imputar en comprobante $did supera su saldo pendiente ($pend).");
        $p = $cancelables[$did];
        $imps[] = [
            'comprobante_destino_id' => $did,
            'numero'                 => trim(($p['comprobante_codigo'] ?? ($p['codigo'] ?? '')) . ' ' . emp_numero_comprobante($c, $emp, intval($p['comprobante_pv'] ?? 0), intval($p['comprobante_nro'] ?? 0))),
            'importe_imputado'       => $monto,
        ];
        $tot_imp = round($tot_imp + $monto, 2);
    }
    if ($tot_imp > $tot_medios + 0.001) {
        throw new RuntimeException('Lo imputado supera el total de los medios de pago.');
    }

    // Deudas del beneficiario que la OP todavía no puede imputar porque no están confirmadas (Borrador / Pendiente)
    $avisos = [];
    $sin_confirmar = asis_filas($ctx['c'], "SELECT o.obligacion_id, o.descripcion, o.importe_total, er.estado_registro
            FROM gestion__obligaciones_pago o
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = o.tabla_estado_registro_id
            WHERE o.empresa_id = ? AND o.entidad_id = ? AND o.tabla_estado_registro_id IN (3, 4)
            ORDER BY o.obligacion_id DESC LIMIT 5", 'ii', $ctx['empresa_id'], $ent_id);
    foreach ($sin_confirmar as $o) {
        $avisos[] = "La obligación \"{$o['descripcion']}\" por " . fmt_moneda(floatval($o['importe_total'])) . " está en {$o['estado_registro']}: "
            . 'no se puede imputar hasta confirmarla.' . ($imps ? '' : ' Esta OP queda a cuenta.');
    }

    return [
        'avisos'         => $avisos,
        'entidad_id'     => $ent_id,
        'entidad_nombre' => $ent['entidad_nombre'],
        'f_emision'      => $f_em,
        'observaciones'  => mb_substr(trim((string)($e['observaciones'] ?? '')), 0, 255),
        'medios'         => $medios,
        'imputaciones'   => $imps,
        'total_medios'   => $tot_medios,
        'total_imputado' => $tot_imp,
        'a_cuenta'       => round(max(0.0, $tot_medios - $tot_imp), 2),
    ];
}

function asis_h_proponer_orden_pago(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'pagos', 'crear órdenes de pago');
    $v = asis_validar_orden_pago($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'ORDEN_PAGO', $v);
    return ['propuesta_id' => $pid, 'total' => $v['total_medios'], 'imputaciones' => count($v['imputaciones'])]
        + ($v['avisos'] ? ['avisos' => $v['avisos']] : []);
}

function asis_grabar_orden_pago(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'pagos', 'crear órdenes de pago');
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once $ctx['modulo_dir'] . '/pagos_model.php';

    $v = asis_validar_orden_pago($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $suc = asis_fila($c, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id ASC LIMIT 1", 'i', $emp);
    $suc_id = intval($suc['sucursal_id'] ?? 1);

    try {
        $pago_id = guardarPagoBorrador($c, [
            'empresa_id'    => $emp,
            'sucursal_id'   => $suc_id,
            'entidad_id'    => $v['entidad_id'],
            'f_emision'     => $v['f_emision'],
            'observaciones' => $v['observaciones'],
            'usuario_id'    => $ctx['usuario_id'],
            'medios'        => $v['medios'],
            'imputaciones'  => $v['imputaciones'],
        ]);
    } catch (Exception $ex) {
        throw new RuntimeException($ex->getMessage());
    }
    return [intval($pago_id), "Orden de Pago a {$v['entidad_nombre']} guardada en Borrador por " . fmt_moneda($v['total_medios']) . '.'];
}
