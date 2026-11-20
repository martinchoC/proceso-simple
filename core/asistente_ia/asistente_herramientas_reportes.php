<?php
/**
 * Asistente IA — Módulo de Reportes Gerenciales, Gastos Bancarios y Stock:
 *  - Reportes (solo lectura):
 *      consultar_flujo_caja, consultar_mayor_contable, consultar_sumas_y_saldos, consultar_posicion_iva
 *  - Gastos Bancarios:
 *      consultar_gastos_bancarios + proponer_gastos_bancarios (GASTOS_BANCARIOS)
 *  - Stock:
 *      consultar_stock + proponer_ajuste_stock (AJUSTE_STOCK)
 *
 * Todas filtradas por permisos del perfil del usuario y revalidadas al confirmar.
 */

function asis_herramientas_reportes(array $ctx): array
{
    $out = [];

    $ver_flujo = asis_tiene_acceso_pagina($ctx, 'tesoreria_movimientos')
              || asis_tiene_acceso_pagina($ctx, 'tesoreria_cuentas')
              || asis_tiene_acceso_pagina($ctx, 'pagos')
              || asis_tiene_acceso_pagina($ctx, 'cobranzas');
    if ($ver_flujo) {
        $out[] = [
            'name' => 'consultar_flujo_caja',
            'description' => 'Consulta la posición financiera actual de la empresa: saldos de cajas, bancos y FCI, deuda a cobrar (total, vencida, 7 y 30 días) y deuda a pagar de proveedores y obligaciones (total, vencida, 7 y 30 días).',
            'input_schema' => ['type' => 'object', 'properties' => (object)[]],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'cont_asientos') || asis_tiene_acceso_pagina($ctx, 'plan_cuentas')) {
        $out[] = [
            'name' => 'consultar_mayor_contable',
            'description' => 'Consulta el libro mayor de una cuenta contable imputable: saldo anterior, movimientos con fecha, descripción, debe, haber y saldo acumulado.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cont_cuenta_id' => ['type' => 'integer', 'description' => 'Id de cuenta imputable (buscar_cuentas_contables)'],
                'fecha_desde'    => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
                'fecha_hasta'    => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
            ], 'required' => ['cont_cuenta_id']],
        ];
        $out[] = [
            'name' => 'consultar_sumas_y_saldos',
            'description' => 'Consulta el balance de sumas y saldos de la empresa (Debe, Haber y Saldo por cuenta), opcionalmente filtrado por período y prefijo de código (ej. "1.1.03" para Otros Créditos / Socios).',
            'input_schema' => ['type' => 'object', 'properties' => [
                'fecha_desde'    => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
                'fecha_hasta'    => ['type' => 'string', 'description' => 'AAAA-MM-DD (opcional)'],
                'codigo_prefijo' => ['type' => 'string', 'description' => 'Prefijo de código de cuenta (opcional, ej: 1.1.03)'],
            ]],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'iva_posiciones') || asis_tiene_acceso_pagina($ctx, 'cont_asientos')) {
        $out[] = [
            'name' => 'consultar_posicion_iva',
            'description' => 'Consulta la posición de IVA de un mes (AAAA-MM): débito fiscal de ventas confirmadas, crédito fiscal de compras confirmadas, saldo técnico estimado y si ya existe posición liquidada.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'periodo' => ['type' => 'string', 'description' => 'Mes en formato AAAA-MM (omitir = mes actual)'],
            ]],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'bancos_gastos')) {
        $out[] = [
            'name' => 'consultar_gastos_bancarios',
            'description' => 'Consulta meses y cuentas con gastos bancarios pendientes del extracto (comisiones, impuestos, percepciones). Si pasás cuenta_id y mes (AAAA-MM), devuelve el detalle exacto del resumen a generar y si hay bloqueos.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cuenta_id' => ['type' => 'integer', 'description' => 'tesoreria_cuenta_id del banco (opcional)'],
                'mes'       => ['type' => 'string', 'description' => 'AAAA-MM (opcional, requiere cuenta_id para ver el detalle)'],
            ]],
        ];
    }

    if (asis_puede($ctx, 'bancos_gastos', 'agregar')) {
        $out[] = [
            'name' => 'proponer_gastos_bancarios',
            'description' => 'Propone generar, confirmar y conciliar el Resumen Bancario (C_RB) de gastos de un banco y mes (AAAA-MM). No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cuenta_id' => ['type' => 'integer', 'description' => 'tesoreria_cuenta_id del banco'],
                'mes'       => ['type' => 'string', 'description' => 'Mes AAAA-MM'],
                'excluir'   => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'extracto_id a excluir (opcional)'],
            ], 'required' => ['cuenta_id', 'mes']],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'stock_ajustes') || asis_tiene_acceso_pagina($ctx, 'facturas') || asis_tiene_acceso_pagina($ctx, 'remitos')) {
        $out[] = [
            'name' => 'consultar_stock',
            'description' => 'Consulta el stock actual de productos por depósito (boca), costos promedio/última compra y depósitos activos de la empresa.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'texto'       => ['type' => 'string', 'description' => 'Código o parte del nombre del producto (opcional)'],
                'producto_id' => ['type' => 'integer', 'description' => 'producto_id exacto (opcional)'],
                'boca_id'     => ['type' => 'integer', 'description' => 'Depósito específico (opcional)'],
            ]],
        ];
    }

    if (asis_puede($ctx, 'stock_ajustes', 'agregar')) {
        $out[] = [
            'name' => 'proponer_ajuste_stock',
            'description' => 'Propone un Ajuste de Stock en Borrador para un depósito (boca_id). Cantidad positiva = ingreso/sobrante; negativa = egreso/faltante. No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'deposito_id' => ['type' => 'integer', 'description' => 'boca_id del depósito (de consultar_stock)'],
                'fecha'       => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = hoy)'],
                'descripcion' => ['type' => 'string', 'description' => 'Motivo del ajuste'],
                'items'       => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'producto_id'     => ['type' => 'integer'],
                    'cantidad_ajuste' => ['type' => 'number', 'description' => 'Positivo para sumar stock, negativo para restar'],
                    'observacion'     => ['type' => 'string'],
                ], 'required' => ['producto_id', 'cantidad_ajuste']]],
            ], 'required' => ['deposito_id', 'descripcion', 'items']],
        ];
    }

    return $out;
}

function asis_ejecutar_herramienta_reportes(array $ctx, string $nombre, array $e): ?array
{
    switch ($nombre) {
        case 'consultar_flujo_caja':       return asis_h_consultar_flujo_caja($ctx);
        case 'consultar_mayor_contable':   return asis_h_consultar_mayor_contable($ctx, $e);
        case 'consultar_sumas_y_saldos':   return asis_h_consultar_sumas_y_saldos($ctx, $e);
        case 'consultar_posicion_iva':     return asis_h_consultar_posicion_iva($ctx, $e);
        case 'consultar_gastos_bancarios': return asis_h_consultar_gastos_bancarios($ctx, $e);
        case 'proponer_gastos_bancarios':  return asis_h_proponer_gastos_bancarios($ctx, $e);
        case 'consultar_stock':            return asis_h_consultar_stock($ctx, $e);
        case 'proponer_ajuste_stock':      return asis_h_proponer_ajuste_stock($ctx, $e);
        default: return null;
    }
}

// ===================================================================================================
// 1. REPORTES GERENCIALES (SOLO LECTURA)
// ===================================================================================================

function asis_h_consultar_flujo_caja(array $ctx): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $hoy = date('Y-m-d');
    $d7  = date('Y-m-d', strtotime('+7 days'));
    $d30 = date('Y-m-d', strtotime('+30 days'));

    $cuentas = asis_filas($c, "SELECT tesoreria_cuenta_id, tesoreria_cuenta AS nombre, cuenta_tipo, saldo_actual
                               FROM gestion__tesoreria_cuentas
                               WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                               ORDER BY cuenta_tipo, orden, tesoreria_cuenta", 'i', $emp);
    $tot_disp = 0.0;
    foreach ($cuentas as &$ct) {
        $ct['saldo_actual'] = round(floatval($ct['saldo_actual']), 2);
        $tot_disp = round($tot_disp + $ct['saldo_actual'], 2);
    }
    unset($ct);

    // A cobrar (facturas/ND de clientes cancelables por RCX)
    $cobrar = asis_fila($c, "SELECT
            COALESCE(SUM(c.importe_pendiente * c.tipo_cambio), 0) AS total,
            COALESCE(SUM(CASE WHEN COALESCE(c.f_vto, c.f_emision) < ? THEN c.importe_pendiente * c.tipo_cambio ELSE 0 END), 0) AS vencido,
            COALESCE(SUM(CASE WHEN COALESCE(c.f_vto, c.f_emision) BETWEEN ? AND ? THEN c.importe_pendiente * c.tipo_cambio ELSE 0 END), 0) AS prox_7d,
            COALESCE(SUM(CASE WHEN COALESCE(c.f_vto, c.f_emision) BETWEEN ? AND ? THEN c.importe_pendiente * c.tipo_cambio ELSE 0 END), 0) AS prox_30d,
            COUNT(*) AS cantidad
        FROM gestion__comprobantes c
        JOIN gestion__comprobantes_tipos_cancelaciones tc ON tc.comprobante_tipo_destino_id = c.comprobante_tipo_id
             AND tc.empresa_id = c.empresa_id AND tc.tabla_estado_registro_id = 1
        JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = tc.comprobante_tipo_origen_id AND ct.codigo = 'RCX'
        WHERE c.empresa_id = ? AND c.tabla_estado_registro_id IN (5, 39) AND c.importe_pendiente > 0.009",
        'sssssi', $hoy, $hoy, $d7, $hoy, $d30, $emp);

    // A pagar (comprobantes cancelables por OP: compras + obligaciones)
    $pagar = asis_fila($c, "SELECT
            COALESCE(SUM(c.importe_pendiente * c.tipo_cambio), 0) AS total,
            COALESCE(SUM(CASE WHEN COALESCE(c.f_vto, c.f_emision) < ? THEN c.importe_pendiente * c.tipo_cambio ELSE 0 END), 0) AS vencido,
            COALESCE(SUM(CASE WHEN COALESCE(c.f_vto, c.f_emision) BETWEEN ? AND ? THEN c.importe_pendiente * c.tipo_cambio ELSE 0 END), 0) AS prox_7d,
            COALESCE(SUM(CASE WHEN COALESCE(c.f_vto, c.f_emision) BETWEEN ? AND ? THEN c.importe_pendiente * c.tipo_cambio ELSE 0 END), 0) AS prox_30d,
            COUNT(*) AS cantidad
        FROM gestion__comprobantes c
        JOIN gestion__comprobantes_tipos_cancelaciones tc ON tc.comprobante_tipo_destino_id = c.comprobante_tipo_id
             AND tc.empresa_id = c.empresa_id AND tc.tabla_estado_registro_id = 1
        JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = tc.comprobante_tipo_origen_id AND ct.codigo = 'OP'
        WHERE c.empresa_id = ? AND c.tabla_estado_registro_id IN (5, 39) AND c.importe_pendiente > 0.009",
        'sssssi', $hoy, $hoy, $d7, $hoy, $d30, $emp);

    $norm = fn($r) => [
        'total'    => round(floatval($r['total'] ?? 0), 2),
        'vencido'  => round(floatval($r['vencido'] ?? 0), 2),
        'prox_7d'  => round(floatval($r['prox_7d'] ?? 0), 2),
        'prox_30d' => round(floatval($r['prox_30d'] ?? 0), 2),
        'cantidad' => intval($r['cantidad'] ?? 0),
    ];

    return [
        'fecha'             => $hoy,
        'disponibilidades'  => ['total' => $tot_disp, 'cuentas' => $cuentas],
        'a_cobrar'          => $norm($cobrar),
        'a_pagar'           => $norm($pagar),
        'posicion_neta'     => round($tot_disp + floatval($cobrar['total'] ?? 0) - floatval($pagar['total'] ?? 0), 2),
    ];
}

function asis_h_consultar_mayor_contable(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $cid = intval($e['cont_cuenta_id'] ?? 0);
    $cta = asis_fila($c, "SELECT cont_cuenta_id, codigo, nombre, naturaleza, es_imputable
                          FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ?", 'ii', $cid, $emp);
    if (!$cta) throw new RuntimeException("Cuenta contable ID $cid no encontrada.");

    $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_desde'] ?? '')) ? $e['fecha_desde'] : null;
    $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_hasta'] ?? '')) ? $e['fecha_hasta'] : null;

    $saldo_ant = 0.0;
    if ($desde) {
        $ant = asis_fila($c, "SELECT COALESCE(SUM(d.importe_local), 0) AS s
                              FROM gestion__cont_asientos_detalles d
                              JOIN gestion__cont_asientos a ON a.cont_asiento_id = d.cont_asiento_id
                              WHERE a.empresa_id = ? AND d.cuenta_id = ? AND a.tabla_estado_registro_id IN (1, 5)
                                AND (d.estado = 'activo' OR d.estado IS NULL) AND a.f_asiento < ?", 'iis', $emp, $cid, $desde);
        $saldo_ant = round(floatval($ant['s'] ?? 0), 2);
    }

    $w = ["a.empresa_id = ?", "d.cuenta_id = ?", "a.tabla_estado_registro_id IN (1, 5)", "(d.estado = 'activo' OR d.estado IS NULL)"];
    $t = 'ii'; $p = [$emp, $cid];
    if ($desde) { $w[] = "a.f_asiento >= ?"; $t .= 's'; $p[] = $desde; }
    if ($hasta) { $w[] = "a.f_asiento <= ?"; $t .= 's'; $p[] = $hasta; }

    $rows = asis_filas($c, "SELECT a.cont_asiento_id, a.f_asiento, COALESCE(NULLIF(d.descripcion, ''), a.descripcion) AS descripcion,
                                   d.importe_local
                            FROM gestion__cont_asientos_detalles d
                            JOIN gestion__cont_asientos a ON a.cont_asiento_id = d.cont_asiento_id
                            WHERE " . implode(' AND ', $w) . "
                            ORDER BY a.f_asiento ASC, a.cont_asiento_id ASC LIMIT 100", $t, ...$p);

    $saldo = $saldo_ant;
    $tot_debe = 0.0;
    $tot_haber = 0.0;
    $movs = [];
    foreach ($rows as $r) {
        $imp = round(floatval($r['importe_local']), 2);
        $debe = $imp > 0 ? $imp : 0.0;
        $haber = $imp < 0 ? abs($imp) : 0.0;
        $saldo = round($saldo + $imp, 2);
        $tot_debe = round($tot_debe + $debe, 2);
        $tot_haber = round($tot_haber + $haber, 2);
        $movs[] = [
            'asiento_id'  => intval($r['cont_asiento_id']),
            'fecha'       => $r['f_asiento'],
            'descripcion' => (string)$r['descripcion'],
            'debe'        => $debe,
            'haber'       => $haber,
            'saldo'       => $saldo,
        ];
    }

    return [
        'cuenta'         => $cta['codigo'] . ' - ' . $cta['nombre'],
        'naturaleza'     => $cta['naturaleza'],
        'saldo_anterior' => $saldo_ant,
        'total_debe'     => $tot_debe,
        'total_haber'    => $tot_haber,
        'saldo_final'    => $saldo,
        'movimientos'    => $movs,
    ];
}

function asis_h_consultar_sumas_y_saldos(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $w = ["a.empresa_id = ?", "a.tabla_estado_registro_id IN (1, 5)", "(d.estado = 'activo' OR d.estado IS NULL)"];
    $t = 'i'; $p = [$emp];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_desde'] ?? ''))) { $w[] = "a.f_asiento >= ?"; $t .= 's'; $p[] = $e['fecha_desde']; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha_hasta'] ?? ''))) { $w[] = "a.f_asiento <= ?"; $t .= 's'; $p[] = $e['fecha_hasta']; }
    $pref = trim((string)($e['codigo_prefijo'] ?? ''));
    if ($pref !== '') { $w[] = "cc.codigo LIKE ?"; $t .= 's'; $p[] = $pref . '%'; }

    $rows = asis_filas($c, "SELECT cc.cont_cuenta_id, cc.codigo, cc.nombre,
                                   ROUND(SUM(CASE WHEN d.importe_local > 0 THEN d.importe_local ELSE 0 END), 2) AS debe,
                                   ROUND(SUM(CASE WHEN d.importe_local < 0 THEN ABS(d.importe_local) ELSE 0 END), 2) AS haber,
                                   ROUND(SUM(d.importe_local), 2) AS saldo
                            FROM gestion__cont_asientos_detalles d
                            JOIN gestion__cont_asientos a ON a.cont_asiento_id = d.cont_asiento_id
                            JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id
                            WHERE " . implode(' AND ', $w) . "
                            GROUP BY cc.cont_cuenta_id, cc.codigo, cc.nombre
                            HAVING ABS(SUM(CASE WHEN d.importe_local > 0 THEN d.importe_local ELSE 0 END)) > 0.001
                                OR ABS(SUM(CASE WHEN d.importe_local < 0 THEN d.importe_local ELSE 0 END)) > 0.001
                            ORDER BY cc.codigo ASC LIMIT 100", $t, ...$p);

    $td = 0.0; $th = 0.0;
    foreach ($rows as &$r) {
        $r['cont_cuenta_id'] = intval($r['cont_cuenta_id']);
        $r['debe'] = round(floatval($r['debe']), 2);
        $r['haber'] = round(floatval($r['haber']), 2);
        $r['saldo'] = round(floatval($r['saldo']), 2);
        $td = round($td + $r['debe'], 2);
        $th = round($th + $r['haber'], 2);
    }
    return ['cuentas' => $rows, 'total_debe' => $td, 'total_haber' => $th];
}

function asis_h_consultar_posicion_iva(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $per = trim((string)($e['periodo'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $per)) $per = date('Y-m');
    $desde = $per . '-01';
    $hasta = date('Y-m-t', strtotime($desde));

    // Ventas confirmadas (facturas suman, NC restan según ct.signo)
    $vta = asis_fila($c, "SELECT COALESCE(SUM(CASE WHEN ct.signo = '-' THEN -c.importe_iva ELSE c.importe_iva END * c.tipo_cambio), 0) AS debito,
                                 COUNT(*) AS cant
                          FROM gestion__comprobantes c
                          JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                          WHERE c.empresa_id = ? AND ct.codigo LIKE 'V_%' AND c.tabla_estado_registro_id IN (5, 39, 40)
                            AND c.f_emision BETWEEN ? AND ?", 'iss', $emp, $desde, $hasta);

    // Compras confirmadas
    $cmp = asis_fila($c, "SELECT COALESCE(SUM(CASE WHEN ct.signo = '-' THEN -c.importe_iva ELSE c.importe_iva END * c.tipo_cambio), 0) AS credito,
                                 COUNT(*) AS cant
                          FROM gestion__comprobantes c
                          JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                          WHERE c.empresa_id = ? AND ct.codigo LIKE 'C_%' AND c.tabla_estado_registro_id IN (5, 39, 40)
                            AND COALESCE(c.f_contabilidad, c.f_emision) BETWEEN ? AND ?", 'iss', $emp, $desde, $hasta);

    $pos = asis_fila($c, "SELECT iva_posicion_id, debito_fiscal, credito_fiscal, percepciones_iva, retenciones_iva,
                                 saldo_favor_anterior, saldo_a_pagar, saldo_a_favor, tabla_estado_registro_id
                          FROM gestion__iva_posiciones
                          WHERE empresa_id = ? AND periodo = ? AND tabla_estado_registro_id = 5 LIMIT 1", 'is', $emp, $per);

    $deb = round(floatval($vta['debito'] ?? 0), 2);
    $cred = round(floatval($cmp['credito'] ?? 0), 2);
    return [
        'periodo'               => $per,
        'debito_fiscal_est'     => $deb,
        'ventas_comprobantes'   => intval($vta['cant'] ?? 0),
        'credito_fiscal_est'    => $cred,
        'compras_comprobantes'  => intval($cmp['cant'] ?? 0),
        'saldo_tecnico_est'     => round($deb - $cred, 2),
        'posicion_liquidada'    => $pos ?: null,
    ];
}

// ===================================================================================================
// 2. GASTOS BANCARIOS (bancos_gastos.php)
// ===================================================================================================

function asis_h_consultar_gastos_bancarios(array $ctx, array $e): array
{
    if (!asis_tiene_acceso_pagina($ctx, 'bancos_gastos')) {
        throw new RuntimeException('No tenés acceso a Gastos Bancarios.');
    }
    require_once __DIR__ . '/../bancos/bancos_gastos_service.php';
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $cta_id = intval($e['cuenta_id'] ?? 0);
    $mes = trim((string)($e['mes'] ?? ''));

    if ($cta_id > 0 && preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $res = bco_gastos_resumen($c, $emp, $cta_id, $mes);
        return [
            'cuenta'       => $res['cuenta']['tesoreria_cuenta'] ?? '',
            'mes'          => $mes,
            'f_emision'    => $res['f_emision'],
            'lineas_incluidas' => count($res['incluidas']),
            'detalles'     => $res['detalles'],
            'impuestos'    => $res['impuestos'],
            'iva_extracto' => $res['iva_extracto'],
            'total'        => $res['total'],
            'bloqueos'     => $res['bloqueos'],
            'avisos'       => $res['avisos'],
        ];
    }
    return ['pendientes' => bco_gastos_pendientes($c, $emp, $cta_id)];
}

function asis_h_proponer_gastos_bancarios(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'bancos_gastos', 'generar resúmenes de gastos bancarios');
    require_once __DIR__ . '/../bancos/bancos_gastos_service.php';
    $cta_id = intval($e['cuenta_id'] ?? 0);
    $mes = trim((string)($e['mes'] ?? ''));
    if ($cta_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $mes)) {
        throw new RuntimeException('Indicá cuenta_id del banco y mes (AAAA-MM).');
    }
    $excluir = array_values(array_filter(array_map('intval', (array)($e['excluir'] ?? [])), fn($x) => $x > 0));
    $res = bco_gastos_resumen($ctx['c'], $ctx['empresa_id'], $cta_id, $mes, $excluir);
    if (!empty($res['bloqueos'])) {
        throw new RuntimeException('No se puede generar todavía: ' . implode(' ', $res['bloqueos']));
    }
    if (empty($res['incluidas'])) {
        throw new RuntimeException('No hay líneas de gastos bancarios pendientes para esa cuenta y mes.');
    }
    $datos = [
        'cuenta_id'        => $cta_id,
        'cuenta_nombre'    => $res['cuenta']['tesoreria_cuenta'] ?? '',
        'mes'              => $mes,
        'f_emision'        => $res['f_emision'],
        'excluir'          => $excluir,
        'lineas_incluidas' => count($res['incluidas']),
        'iva_extracto'     => $res['iva_extracto'],
        'total'            => $res['total'],
        'avisos'           => $res['avisos'],
    ];
    $pid = asis_guardar_propuesta($ctx, 'GASTOS_BANCARIOS', $datos);
    return ['propuesta_id' => $pid, 'total' => $datos['total'], 'lineas' => $datos['lineas_incluidas']];
}

function asis_grabar_gastos_bancarios(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'bancos_gastos', 'generar resúmenes de gastos bancarios');
    require_once $ctx['modulo_dir'] . '/bancos_gastos_model.php';
    $fp_pag = intval($ctx['paginas']['facturas_proveedores'] ?: 557);
    $r = bco_gastos_generar($ctx['c'], $ctx['empresa_id'], $ctx['usuario_id'],
        intval($d['cuenta_id']), (string)$d['mes'], (array)($d['excluir'] ?? []), $fp_pag);
    return [intval($r['conciliacion_id']), "Resumen bancario {$r['comprobante']} generado y conciliado por " . fmt_moneda($r['total']) . '.'];
}

// ===================================================================================================
// 3. STOCK Y AJUSTES DE STOCK
// ===================================================================================================

function asis_h_consultar_stock(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $depositos = asis_filas($c, "SELECT b.boca_id, b.boca_nombre, b.sucursal_id, s.sucursal_nombre
                                 FROM gestion__bocas b
                                 LEFT JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id
                                 WHERE b.empresa_id = ? AND b.tabla_estado_registro_id = 1 AND b.es_deposito = 1
                                 ORDER BY b.es_principal DESC, b.boca_nombre", 'i', $emp);

    $w = ["p.empresa_id = ?", "p.tabla_estado_registro_id = 1"];
    $t = 'i'; $p = [$emp];
    $pid = intval($e['producto_id'] ?? 0);
    if ($pid > 0) { $w[] = "p.producto_id = ?"; $t .= 'i'; $p[] = $pid; }
    $txt = trim((string)($e['texto'] ?? ''));
    if ($txt !== '') {
        $like = '%' . $txt . '%';
        $w[] = "(p.producto_codigo LIKE ? OR p.producto_nombre LIKE ?)";
        $t .= 'ss'; $p[] = $like; $p[] = $like;
    }
    $bid = intval($e['boca_id'] ?? 0);
    $join_b = $bid > 0 ? " AND ss.boca_id = " . $bid : "";

    $stock = asis_filas($c, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                                    ss.boca_id, b.boca_nombre,
                                    COALESCE(ss.cantidad, 0) AS cantidad,
                                    COALESCE(ss.costo_promedio, 0) AS costo_promedio
                             FROM gestion__productos p
                             LEFT JOIN gestion__stock ss ON ss.producto_id = p.producto_id AND ss.empresa_id = p.empresa_id $join_b
                             LEFT JOIN gestion__bocas b ON b.boca_id = ss.boca_id
                             WHERE " . implode(' AND ', $w) . "
                             ORDER BY p.producto_nombre ASC, b.boca_nombre ASC LIMIT 40", $t, ...$p);
    return ['depositos' => $depositos, 'stock' => $stock];
}

function asis_validar_ajuste_stock(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $dep_id = intval($e['deposito_id'] ?? 0);
    $dep = asis_fila($c, "SELECT boca_id, boca_nombre, sucursal_id FROM gestion__bocas WHERE boca_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 AND es_deposito = 1", 'ii', $dep_id, $emp);
    if (!$dep) throw new RuntimeException("Depósito (boca_id) $dep_id no encontrado.");

    $fecha = trim((string)($e['fecha'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
    $desc = mb_substr(trim((string)($e['descripcion'] ?? 'Ajuste de stock desde Asistente IA')), 0, 255);

    $items_in = (array)($e['items'] ?? []);
    if (!$items_in) throw new RuntimeException('Indicá al menos un producto para ajustar.');

    $tiene_pos = false;
    $tiene_neg = false;
    $lineas = [];
    foreach ($items_in as $it) {
        $pid = intval($it['producto_id'] ?? 0);
        $cant = round(floatval($it['cantidad_ajuste'] ?? 0), 4);
        if ($pid <= 0 || abs($cant) < 0.0001) throw new RuntimeException('Cada ítem requiere producto_id y cantidad_ajuste distinta de 0.');
        $prod = asis_fila($c, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, COALESCE(ss.cantidad, 0) AS stock_sistema, COALESCE(ss.costo_promedio, 0) AS costo
                               FROM gestion__productos p
                               LEFT JOIN gestion__stock ss ON ss.producto_id = p.producto_id AND ss.empresa_id = p.empresa_id AND ss.boca_id = ?
                               WHERE p.producto_id = ? AND p.empresa_id = ? AND p.tabla_estado_registro_id = 1", 'iii', $dep_id, $pid, $emp);
        if (!$prod) throw new RuntimeException("Producto ID $pid no encontrado.");
        if ($cant > 0) $tiene_pos = true;
        if ($cant < 0) $tiene_neg = true;
        $ss = floatval($prod['stock_sistema']);
        $lineas[] = [
            'producto_id'     => $pid,
            'producto_codigo' => $prod['producto_codigo'] ?? '',
            'producto_nombre' => $prod['producto_nombre'],
            'deposito_id'     => $dep_id,
            'stock_sistema'   => $ss,
            'stock_fisico'    => round($ss + $cant, 4),
            'diferencia'      => $cant,
            'cantidad_ajuste' => $cant,
            'costo_unitario'  => floatval($prod['costo']),
            'observacion'     => mb_substr(trim((string)($it['observacion'] ?? '')), 0, 200),
        ];
    }

    // Resolver tipo de comprobante de ajuste (subgrupo CEF) compatible con los signos
    $tipos = asis_filas($c, "SELECT ct.comprobante_tipo_id, ct.codigo, ct.comprobante_tipo, ct.signo
                             FROM gestion__comprobantes_tipos ct
                             JOIN gestion__comprobantes_subgrupos sg ON sg.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
                             WHERE (ct.empresa_id = ? OR ct.empresa_id = 0) AND ct.tabla_estado_registro_id = 1 AND sg.codigo = 'CEF'
                             ORDER BY ct.empresa_id DESC, ct.comprobante_tipo_id ASC", 'i', $emp);
    $tipo_sel = null;
    foreach ($tipos as $t) {
        $s = trim((string)$t['signo']);
        if ($tiene_pos && $tiene_neg && ($s === '+/-' || $s === '')) { $tipo_sel = $t; break; }
        if ($tiene_pos && !$tiene_neg && in_array($s, ['+', '+/-', ''], true)) { $tipo_sel = $t; break; }
        if ($tiene_neg && !$tiene_pos && in_array($s, ['-', '+/-', ''], true)) { $tipo_sel = $t; break; }
    }
    if (!$tipo_sel && !empty($tipos)) $tipo_sel = $tipos[0];
    if (!$tipo_sel) throw new RuntimeException('No hay un tipo de comprobante de ajuste de stock (CEF) configurado en la empresa.');

    return [
        'deposito_id'         => $dep_id,
        'deposito_nombre'     => $dep['boca_nombre'],
        'sucursal_id'         => intval($dep['sucursal_id'] ?: 1),
        'comprobante_tipo_id' => intval($tipo_sel['comprobante_tipo_id']),
        'comprobante_tipo'    => $tipo_sel['comprobante_tipo'],
        'fecha'               => $fecha,
        'descripcion'         => $desc,
        'lineas'              => $lineas,
    ];
}

function asis_h_proponer_ajuste_stock(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'stock_ajustes', 'crear ajustes de stock');
    $v = asis_validar_ajuste_stock($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'AJUSTE_STOCK', $v);
    return ['propuesta_id' => $pid, 'lineas' => count($v['lineas']), 'deposito' => $v['deposito_nombre']];
}

function asis_grabar_ajuste_stock(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'stock_ajustes', 'crear ajustes de stock');
    $v = asis_validar_ajuste_stock($ctx, $d);
    require_once $ctx['modulo_dir'] . '/stock_ajustes_model.php';
    $r = agregarStockAjuste($ctx['c'], [
        'empresa_idx'         => $ctx['empresa_id'],
        'pagina_idx'          => intval($ctx['paginas']['stock_ajustes'] ?: 577),
        'sucursal_id'         => $v['sucursal_id'],
        'deposito_id'         => $v['deposito_id'],
        'comprobante_tipo_id' => $v['comprobante_tipo_id'],
        'fecha'               => $v['fecha'],
        'descripcion'         => $v['descripcion'],
        'detalles'            => $v['lineas'],
    ]);
    if (empty($r['resultado'])) throw new RuntimeException($r['error'] ?? 'No se pudo guardar el ajuste de stock.');
    return [intval($r['stock_ajuste_id']), "Ajuste de stock en {$v['deposito_nombre']} guardado en Borrador (" . count($v['lineas']) . " ítem/s)."];
}
