<?php
require_once __DIR__ . '/../../core/empresa_config.php';
/**
 * tesoreria_dashboard_model.php
 *
 * Módulo de Tesorería - Dashboard Financiero Combinado de Caja y Bancos
 * Sistema gestion_multipyme
 *
 * Implementa las decisiones de diseño D4, D15, D35/D36 y soporte dinámico de cuentas
 * mediante el flag `es_caja_banco` en `gestion__cont_cuentas`:
 * 1. Saldo por cuenta individual y total consolidado (lectura O(1) de saldo_actual materializado).
 * 2. Auditoría/Reconciliación automática entre saldo_actual y suma del libro de tesorería.
 * 3. Movimientos del período con agregados analíticos y desglose temporal.
 * 4. Flujo de fondos proyectado a 30 días (cobros, pagos, cheques diferidos).
 * 5. Cartera de valores a depositar (cheques de terceros en cartera por tramos de vencimiento).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';

if (!defined('ESTADO_CHQ_EN_CARTERA')) define('ESTADO_CHQ_EN_CARTERA', 29);
if (!defined('ESTADO_CHQ_EMITIDO'))    define('ESTADO_CHQ_EMITIDO', 31);
if (!defined('ESTADO_TESORERIA_MOV_ACTIVO')) define('ESTADO_TESORERIA_MOV_ACTIVO', 1);

/**
 * 1. OBTENER SALDOS CONSOLIDADOS Y POR CUENTA
 *
 * Consulta cuentas operativas de tesorería asociadas a cuentas contables con `es_caja_banco = 1`.
 * Utiliza el `saldo_actual` materializado en `gestion__tesoreria_cuentas` para respuesta instantánea.
 * Opcionalmente audita contra el libro de movimientos para detectar cualquier desfasaje.
 */
function obtenerSaldosCuentasConsolidados($conexion, $empresa_id, $filtros = [])
{
    $empresa_id = intval($empresa_id);
    if ($empresa_id <= 0) return ['cuentas' => [], 'totales' => []];

    $sql = "SELECT 
                tc.tesoreria_cuenta_id,
                tc.codigo AS codigo_cuenta,
                tc.tesoreria_cuenta,
                tc.cuenta_tipo,
                tc.banco_cuenta_tipo,
                tc.numero AS cuenta_numero,
                tc.cbu,
                tc.alias,
                b.banco,
                s.sucursal_nombre,
                tc.sucursal_id,
                tc.moneda_id,
                m.simbolo AS moneda_simbolo,
                m.codigo AS moneda_codigo,
                tc.saldo_actual,
                tc.permite_saldo_negativo,
                cc.cont_cuenta_id,
                cc.codigo AS cont_codigo,
                cc.nombre AS cont_nombre,
                -- Auditoría al vuelo del libro de movimientos para verificar integridad
                COALESCE((
                    SELECT SUM(CASE WHEN tm.tipo_movimiento = 'INGRESO' THEN tm.importe ELSE -tm.importe END)
                    FROM gestion__tesoreria_movimientos tm
                    WHERE tm.tesoreria_cuenta_id = tc.tesoreria_cuenta_id
                      AND tm.empresa_id = tc.empresa_id
                      AND tm.tabla_estado_registro_id = 1
                ), 0.00) AS saldo_calculado_libro
            FROM gestion__tesoreria_cuentas tc
            INNER JOIN gestion__cont_cuentas cc 
                ON cc.cont_cuenta_id = tc.cont_cuenta_id 
                AND cc.es_caja_banco = 1
                AND cc.tabla_estado_registro_id = 1
            INNER JOIN gestion__monedas m ON m.moneda_id = tc.moneda_id
            LEFT JOIN conf__bancos b ON b.banco_id = tc.banco_id
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = tc.sucursal_id
            WHERE tc.empresa_id = ?
              AND tc.tabla_estado_registro_id = 1 ";

    $params = [$empresa_id];
    $types  = "i";

    if (!empty($filtros['sucursal_id'])) {
        $sql .= " AND (tc.sucursal_id = ? OR tc.sucursal_id IS NULL) ";
        $params[] = intval($filtros['sucursal_id']);
        $types   .= "i";
    }

    if (!empty($filtros['cuenta_tipo']) && in_array($filtros['cuenta_tipo'], ['CAJA', 'BANCO', 'VALORES', 'CUPONES'])) {
        $sql .= " AND tc.cuenta_tipo = ? ";
        $params[] = $filtros['cuenta_tipo'];
        $types   .= "s";
    }

    $sql .= " ORDER BY 
                CASE tc.cuenta_tipo 
                    WHEN 'CAJA' THEN 1 
                    WHEN 'BANCO' THEN 2 
                    WHEN 'VALORES' THEN 3 
                    ELSE 4 
                END, 
                tc.orden ASC, 
                tc.tesoreria_cuenta ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['cuentas' => [], 'totales' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $cuentas = [];
    $total_ars = 0.0;
    $total_usd = 0.0;
    $total_cajas_ars = 0.0;
    $total_bancos_ars = 0.0;
    $total_valores_ars = 0.0;
    $desfasajes_detectados = 0;

    while ($row = mysqli_fetch_assoc($res)) {
        $saldo_materializado = round(floatval($row['saldo_actual']), 2);
        $saldo_libro         = round(floatval($row['saldo_calculado_libro']), 2);
        $diferencia          = round($saldo_materializado - $saldo_libro, 2);
        $tiene_desfasaje     = abs($diferencia) > 0.01;

        if ($tiene_desfasaje) {
            $desfasajes_detectados++;
        }

        $row['saldo_actual']          = $saldo_materializado;
        $row['saldo_calculado_libro'] = $saldo_libro;
        $row['diferencia_auditoria']  = $diferencia;
        $row['tiene_desfasaje']       = $tiene_desfasaje;

        // Acumular por moneda y tipo
        if (intval($row['moneda_id']) === 1) { // ARS
            $total_ars += $saldo_materializado;
            if ($row['cuenta_tipo'] === 'CAJA') {
                $total_cajas_ars += $saldo_materializado;
            } elseif ($row['cuenta_tipo'] === 'BANCO') {
                $total_bancos_ars += $saldo_materializado;
            } elseif ($row['cuenta_tipo'] === 'VALORES') {
                $total_valores_ars += $saldo_materializado;
            }
        } elseif (intval($row['moneda_id']) === 2) { // USD
            $total_usd += $saldo_materializado;
        }

        $cuentas[] = $row;
    }
    mysqli_stmt_close($stmt);

    // Obtener cotización de referencia para el total general estimado
    $tipo_cambio_usd = 1.0;
    $res_cot = mysqli_query($conexion, "SELECT cotizacion_actual FROM gestion__monedas WHERE empresa_id = " . intval($empresa_id) . " AND codigo = 'USD' AND tabla_estado_registro_id = 1 LIMIT 1");
    if ($res_cot && ($row_cot = mysqli_fetch_assoc($res_cot))) {
        $tipo_cambio_usd = floatval($row_cot['cotizacion_actual'] ?? 1.0);
    }
    if ($tipo_cambio_usd <= 0) $tipo_cambio_usd = 1.0;

    $total_consolidado_ars = round($total_ars + ($total_usd * $tipo_cambio_usd), 2);

    return [
        'cuentas' => $cuentas,
        'totales' => [
            'total_ars'              => $total_ars,
            'total_usd'              => $total_usd,
            'total_cajas_ars'        => $total_cajas_ars,
            'total_bancos_ars'       => $total_bancos_ars,
            'total_valores_ars'      => $total_valores_ars,
            'tipo_cambio_usd'        => $tipo_cambio_usd,
            'total_consolidado_ars'  => $total_consolidado_ars,
            'desfasajes_detectados'  => $desfasajes_detectados
        ]
    ];
}

/**
 * 2. OBTENER MOVIMIENTOS DEL PERÍODO (SELECTOR DE FECHAS)
 *
 * Agrupa ingresos y egresos dentro del rango seleccionado,
 * desglosa la serie diaria para el gráfico de barras/líneas
 * y lista los movimientos del subdiario de tesorería.
 */
function obtenerMovimientosPeriodo($conexion, $empresa_id, $filtros = [])
{
    $empresa_id = intval($empresa_id);
    $fecha_desde = !empty($filtros['desde']) ? $filtros['desde'] : date('Y-m-01');
    $fecha_hasta = !empty($filtros['hasta']) ? $filtros['hasta'] : date('Y-m-t');

    // 1) Totales agregados por tipo
    $sql_tot = "SELECT 
                    tm.tipo_movimiento,
                    COUNT(tm.tesoreria_movimiento_id) AS cantidad,
                    COALESCE(SUM(tm.importe), 0.00) AS total_importe
                FROM gestion__tesoreria_movimientos tm
                INNER JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = tm.tesoreria_cuenta_id
                INNER JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id AND cc.es_caja_banco = 1
                WHERE tm.empresa_id = ?
                  AND tm.f_movimiento BETWEEN ? AND ?
                  AND tm.tabla_estado_registro_id = 1 ";

    $params = [$empresa_id, $fecha_desde, $fecha_hasta];
    $types  = "iss";

    if (!empty($filtros['cuenta_id'])) {
        $sql_tot .= " AND tm.tesoreria_cuenta_id = ? ";
        $params[] = intval($filtros['cuenta_id']);
        $types   .= "i";
    }

    $sql_tot .= " GROUP BY tm.tipo_movimiento";

    $stmt_tot = mysqli_prepare($conexion, $sql_tot);
    mysqli_stmt_bind_param($stmt_tot, $types, ...$params);
    mysqli_stmt_execute($stmt_tot);
    $res_tot = mysqli_stmt_get_result($stmt_tot);

    $total_ingresos = 0.0;
    $total_egresos  = 0.0;
    $cant_ingresos  = 0;
    $cant_egresos   = 0;

    while ($r = mysqli_fetch_assoc($res_tot)) {
        if ($r['tipo_movimiento'] === 'INGRESO') {
            $total_ingresos = floatval($r['total_importe']);
            $cant_ingresos  = intval($r['cantidad']);
        } elseif ($r['tipo_movimiento'] === 'EGRESO') {
            $total_egresos  = floatval($r['total_importe']);
            $cant_egresos   = intval($r['cantidad']);
        }
    }
    mysqli_stmt_close($stmt_tot);

    $flujo_neto = round($total_ingresos - $total_egresos, 2);

    // 2) Serie diaria para gráfico Chart.js
    $sql_serie = "SELECT 
                    tm.f_movimiento,
                    SUM(CASE WHEN tm.tipo_movimiento = 'INGRESO' THEN tm.importe ELSE 0 END) AS ingresos,
                    SUM(CASE WHEN tm.tipo_movimiento = 'EGRESO' THEN tm.importe ELSE 0 END) AS egresos
                  FROM gestion__tesoreria_movimientos tm
                  INNER JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = tm.tesoreria_cuenta_id
                  INNER JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id AND cc.es_caja_banco = 1
                  WHERE tm.empresa_id = ?
                    AND tm.f_movimiento BETWEEN ? AND ?
                    AND tm.tabla_estado_registro_id = 1 ";

    $params_serie = [$empresa_id, $fecha_desde, $fecha_hasta];
    $types_serie  = "iss";

    if (!empty($filtros['cuenta_id'])) {
        $sql_serie .= " AND tm.tesoreria_cuenta_id = ? ";
        $params_serie[] = intval($filtros['cuenta_id']);
        $types_serie   .= "i";
    }

    $sql_serie .= " GROUP BY tm.f_movimiento ORDER BY tm.f_movimiento ASC";

    $stmt_serie = mysqli_prepare($conexion, $sql_serie);
    mysqli_stmt_bind_param($stmt_serie, $types_serie, ...$params_serie);
    mysqli_stmt_execute($stmt_serie);
    $res_serie = mysqli_stmt_get_result($stmt_serie);

    $labels = [];
    $data_ingresos = [];
    $data_egresos  = [];
    $data_netos    = [];

    while ($s = mysqli_fetch_assoc($res_serie)) {
        $labels[]        = date('d/m', strtotime($s['f_movimiento']));
        $ing             = round(floatval($s['ingresos']), 2);
        $egr             = round(floatval($s['egresos']), 2);
        $data_ingresos[] = $ing;
        $data_egresos[]  = $egr;
        $data_netos[]    = round($ing - $egr, 2);
    }
    mysqli_stmt_close($stmt_serie);

    // 3) Listado detallado de movimientos recientes del período (hasta 100 registros)
    $sql_det = "SELECT 
                    tm.tesoreria_movimiento_id,
                    tm.f_movimiento,
                    tm.tipo_movimiento,
                    tm.importe,
                    tm.concepto,
                    tc.tesoreria_cuenta,
                    tc.cuenta_tipo,
                    m.simbolo AS moneda_simbolo,
                    mp.medio_pago,
                    c.comprobante_nro,
                    " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv,
                    c.comprobante_tipo_id
                FROM gestion__tesoreria_movimientos tm
                INNER JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = tm.tesoreria_cuenta_id
                INNER JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id AND cc.es_caja_banco = 1
                INNER JOIN gestion__monedas m ON m.moneda_id = tm.moneda_id
                LEFT JOIN gestion__medios_pago mp ON mp.medio_pago_id = tm.medio_pago_id
                LEFT JOIN gestion__comprobantes c ON c.comprobante_id = tm.comprobante_id
                WHERE tm.empresa_id = ?
                  AND tm.f_movimiento BETWEEN ? AND ?
                  AND tm.tabla_estado_registro_id = 1 ";

    $params_det = [$empresa_id, $fecha_desde, $fecha_hasta];
    $types_det  = "iss";

    if (!empty($filtros['cuenta_id'])) {
        $sql_det .= " AND tm.tesoreria_cuenta_id = ? ";
        $params_det[] = intval($filtros['cuenta_id']);
        $types_det   .= "i";
    }

    $sql_det .= " ORDER BY tm.f_movimiento DESC, tm.tesoreria_movimiento_id DESC LIMIT 100";

    $stmt_det = mysqli_prepare($conexion, $sql_det);
    mysqli_stmt_bind_param($stmt_det, $types_det, ...$params_det);
    mysqli_stmt_execute($stmt_det);
    $res_det = mysqli_stmt_get_result($stmt_det);

    $movimientos = [];
    while ($m = mysqli_fetch_assoc($res_det)) {
        $movimientos[] = $m;
    }
    mysqli_stmt_close($stmt_det);

    return [
        'rango' => [
            'desde' => $fecha_desde,
            'hasta' => $fecha_hasta
        ],
        'totales' => [
            'ingresos'      => $total_ingresos,
            'cant_ingresos' => $cant_ingresos,
            'egresos'       => $total_egresos,
            'cant_egresos'  => $cant_egresos,
            'flujo_neto'    => $flujo_neto
        ],
        'grafico' => [
            'labels'   => $labels,
            'ingresos' => $data_ingresos,
            'egresos'  => $data_egresos,
            'netos'    => $data_netos
        ],
        'movimientos' => $movimientos
    ];
}

/**
 * 3. OBTENER FLUJO DE FONDOS PROYECTADO (CASH FLOW HORIZONTE 30 DÍAS)
 *
 * Parte del saldo actual consolidado disponible y proyecta día a día:
 * (+) Cobros previstos (facturas con saldo pendiente e imputaciones sincronizadas bajo lock D2)
 * (+) Cheques de terceros en cartera por vencer
 * (-) Pagos previstos a proveedores (facturas con saldo pendiente)
 * (-) Cheques propios diferidos emitidos por debitar
 */
function obtenerFlujoFondosProyectado($conexion, $empresa_id, $dias_horizonte = 30)
{
    $empresa_id = intval($empresa_id);
    $dias_horizonte = max(7, min(90, intval($dias_horizonte)));
    $hoy = date('Y-m-d');
    $fecha_limite = date('Y-m-d', strtotime("+$dias_horizonte days"));

    // 1) Saldo inicial disponible hoy (Cajas + Bancos en moneda local)
    $sql_saldo = "SELECT COALESCE(SUM(tc.saldo_actual), 0.00) AS saldo_disponible
                  FROM gestion__tesoreria_cuentas tc
                  INNER JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id AND cc.es_caja_banco = 1
                  WHERE tc.empresa_id = ?
                    AND tc.tabla_estado_registro_id = 1
                    AND tc.moneda_id = " . emp_moneda_base_id($conexion, $empresa_id) . "
                    AND tc.cuenta_tipo IN ('CAJA', 'BANCO')";
    $stmt_s = mysqli_prepare($conexion, $sql_saldo);
    mysqli_stmt_bind_param($stmt_s, "i", $empresa_id);
    mysqli_stmt_execute($stmt_s);
    $row_s = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_s));
    mysqli_stmt_close($stmt_s);

    $saldo_inicial = floatval($row_s['saldo_disponible'] ?? 0);

    // 2) Cobros pendientes: los tipos que cancela el recibo de cliente (RCX) en la matriz de
    // cancelaciones, como la cuenta corriente de clientes. El signo no separa ventas de compras
    // (las facturas de proveedor también tienen signo 1) y sin filtrar el tipo entraban pedidos
    // y remitos, que también llevan importe_pendiente.
    $sql_cobros = "SELECT
                       COALESCE(c.f_vto, c.f_emision) AS fecha,
                       SUM(c.importe_pendiente * c.tipo_cambio) AS importe
                   FROM gestion__comprobantes c
                   WHERE c.empresa_id = ?
                     AND c.tabla_estado_registro_id IN (5, 39)
                     AND c.importe_pendiente > 0.001
                     AND c.comprobante_tipo_id IN (
                         SELECT tc.comprobante_tipo_destino_id
                         FROM gestion__comprobantes_tipos_cancelaciones tc
                         JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id
                         WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1 AND o.codigo = 'RCX')
                     AND COALESCE(c.f_vto, c.f_emision) <= ?
                   GROUP BY COALESCE(c.f_vto, c.f_emision)";
    $stmt_c = mysqli_prepare($conexion, $sql_cobros);
    mysqli_stmt_bind_param($stmt_c, "iis", $empresa_id, $empresa_id, $fecha_limite);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);

    $ingresos_por_fecha = [];
    $total_cobros_vencidos = 0.0;
    while ($r = mysqli_fetch_assoc($res_c)) {
        $f = $r['fecha'];
        $imp = floatval($r['importe']);
        if ($f < $hoy) {
            $total_cobros_vencidos += $imp;
            // Para la proyección, los vencidos se contemplan en el día de hoy
            $f = $hoy;
        }
        $ingresos_por_fecha[$f] = ($ingresos_por_fecha[$f] ?? 0.0) + $imp;
    }
    mysqli_stmt_close($stmt_c);

    // 3) Cheques de terceros en cartera a cobrar (f_pago)
    $sql_chq_cob = "SELECT 
                        chq.f_pago AS fecha,
                        SUM(chq.importe) AS importe
                    FROM gestion__cheques chq
                    WHERE chq.empresa_id = ?
                      AND chq.cheque_tipo = 'TERCERO'
                      AND chq.tabla_estado_registro_id = 29 -- EN_CARTERA
                      AND chq.f_pago <= ?
                    GROUP BY chq.f_pago";
    $stmt_cc = mysqli_prepare($conexion, $sql_chq_cob);
    mysqli_stmt_bind_param($stmt_cc, "is", $empresa_id, $fecha_limite);
    mysqli_stmt_execute($stmt_cc);
    $res_cc = mysqli_stmt_get_result($stmt_cc);

    while ($r = mysqli_fetch_assoc($res_cc)) {
        $f = $r['fecha'];
        $imp = floatval($r['importe']);
        if ($f < $hoy) $f = $hoy;
        $ingresos_por_fecha[$f] = ($ingresos_por_fecha[$f] ?? 0.0) + $imp;
    }
    mysqli_stmt_close($stmt_cc);

    // 4) Pagos pendientes a proveedores: los tipos que cancela la OP, sin los de ventas (mismo
    // criterio que la cuenta corriente de proveedores).
    $sql_pagos = "SELECT
                      COALESCE(c.f_vto, c.f_emision) AS fecha,
                      SUM(c.importe_pendiente * c.tipo_cambio) AS importe
                  FROM gestion__comprobantes c
                  WHERE c.empresa_id = ?
                    AND c.tabla_estado_registro_id IN (5, 39)
                    AND c.importe_pendiente > 0.001
                    AND c.comprobante_tipo_id IN (
                        SELECT tc.comprobante_tipo_destino_id
                        FROM gestion__comprobantes_tipos_cancelaciones tc
                        JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id
                        JOIN gestion__comprobantes_tipos d ON d.comprobante_tipo_id = tc.comprobante_tipo_destino_id
                        LEFT JOIN gestion__comprobantes_grupos cg ON cg.comprobante_grupo_id = d.comprobante_grupo_id
                        WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1 AND o.codigo = 'OP'
                          AND (cg.codigo IS NULL OR cg.codigo != 'VENTAS')
                          AND d.codigo NOT LIKE 'V_%')
                    AND COALESCE(c.f_vto, c.f_emision) <= ?
                  GROUP BY COALESCE(c.f_vto, c.f_emision)";
    $stmt_p = mysqli_prepare($conexion, $sql_pagos);
    mysqli_stmt_bind_param($stmt_p, "iis", $empresa_id, $empresa_id, $fecha_limite);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);

    $egresos_por_fecha = [];
    $total_pagos_vencidos = 0.0;
    while ($r = mysqli_fetch_assoc($res_p)) {
        $f = $r['fecha'];
        $imp = floatval($r['importe']);
        if ($f < $hoy) {
            $total_pagos_vencidos += $imp;
            $f = $hoy;
        }
        $egresos_por_fecha[$f] = ($egresos_por_fecha[$f] ?? 0.0) + $imp;
    }
    mysqli_stmt_close($stmt_p);

    // 5) Cheques propios diferidos emitidos pendientes de débito
    $sql_chq_pag = "SELECT 
                        chq.f_pago AS fecha,
                        SUM(chq.importe) AS importe
                    FROM gestion__cheques chq
                    WHERE chq.empresa_id = ?
                      AND chq.cheque_tipo = 'PROPIO'
                      AND chq.tabla_estado_registro_id = 31 -- EMITIDO
                      AND chq.f_pago <= ?
                    GROUP BY chq.f_pago";
    $stmt_cp = mysqli_prepare($conexion, $sql_chq_pag);
    mysqli_stmt_bind_param($stmt_cp, "is", $empresa_id, $fecha_limite);
    mysqli_stmt_execute($stmt_cp);
    $res_cp = mysqli_stmt_get_result($stmt_cp);

    while ($r = mysqli_fetch_assoc($res_cp)) {
        $f = $r['fecha'];
        $imp = floatval($r['importe']);
        if ($f < $hoy) $f = $hoy;
        $egresos_por_fecha[$f] = ($egresos_por_fecha[$f] ?? 0.0) + $imp;
    }
    mysqli_stmt_close($stmt_cp);

    // Construcción de la proyección cronológica día por día
    $timeline = [];
    $saldo_acumulado = $saldo_inicial;
    $total_ingresos_proy = 0.0;
    $total_egresos_proy  = 0.0;

    $dias_semana_esp = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    // Buckets por semanas/tramos
    $buckets = [
        'vencido'     => ['ingresos' => $total_cobros_vencidos, 'egresos' => $total_pagos_vencidos],
        'dias_1_7'    => ['ingresos' => 0.0, 'egresos' => 0.0],
        'dias_8_15'   => ['ingresos' => 0.0, 'egresos' => 0.0],
        'dias_16_30'  => ['ingresos' => 0.0, 'egresos' => 0.0],
    ];

    for ($i = 0; $i <= $dias_horizonte; $i++) {
        $dia_ts = strtotime("+$i days", strtotime($hoy));
        $dia_str = date('Y-m-d', $dia_ts);

        $ing = round($ingresos_por_fecha[$dia_str] ?? 0.0, 2);
        $egr = round($egresos_por_fecha[$dia_str] ?? 0.0, 2);

        $total_ingresos_proy += $ing;
        $total_egresos_proy  += $egr;

        $saldo_acumulado = round($saldo_acumulado + $ing - $egr, 2);

        if ($i >= 1 && $i <= 7) {
            $buckets['dias_1_7']['ingresos'] += $ing;
            $buckets['dias_1_7']['egresos']  += $egr;
        } elseif ($i >= 8 && $i <= 15) {
            $buckets['dias_8_15']['ingresos'] += $ing;
            $buckets['dias_8_15']['egresos']  += $egr;
        } elseif ($i >= 16 && $i <= 30) {
            $buckets['dias_16_30']['ingresos'] += $ing;
            $buckets['dias_16_30']['egresos']  += $egr;
        }

        $w = intval(date('w', $dia_ts));
        $timeline[] = [
            'dia_indice'      => $i,
            'fecha'           => $dia_str,
            'fecha_formato'   => date('d/m', $dia_ts),
            'dia_nombre'      => $dias_semana_esp[$w],
            'ingresos'        => $ing,
            'egresos'         => $egr,
            'saldo_proyectado'=> $saldo_acumulado
        ];
    }

    return [
        'saldo_inicial'       => $saldo_inicial,
        'saldo_final_estimado'=> $saldo_acumulado,
        'total_ingresos'      => $total_ingresos_proy,
        'total_egresos'       => $total_egresos_proy,
        'buckets'             => $buckets,
        'timeline'            => $timeline
    ];
}

/**
 * 4. OBTENER VALORES A DEPOSITAR (CARTERA DE CHEQUES DE TERCEROS)
 *
 * Cheques de terceros en estado EN_CARTERA (29),
 * con categorización por proximidad de cobro (Vencidos, Hoy, 7 días, Futuros).
 */
function obtenerValoresADepositar($conexion, $empresa_id, $filtros = [])
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT 
                chq.cheque_id,
                chq.numero AS cheque_numero,
                b.banco AS banco_nombre,
                chq.importe,
                m.simbolo AS moneda_simbolo,
                chq.f_emision,
                chq.f_pago,
                DATEDIFF(chq.f_pago, CURDATE()) AS dias_al_cobro,
                CASE 
                    WHEN chq.f_pago < CURDATE() THEN 'VENCIDO'
                    WHEN chq.f_pago = CURDATE() THEN 'HOY'
                    WHEN DATEDIFF(chq.f_pago, CURDATE()) <= 7 THEN 'PROX_7_DIAS'
                    ELSE 'FUTURO'
                END AS categoria_cobro,
                COALESCE(chq.librador_nombre, e.entidad_nombre, 'Desconocido') AS librador,
                COALESCE(chq.librador_cuit, e.cuit, '') AS cuit,
                tc.tesoreria_cuenta AS cuenta_custodia
            FROM gestion__cheques chq
            INNER JOIN conf__bancos b ON b.banco_id = chq.banco_id
            INNER JOIN gestion__monedas m ON m.moneda_id = chq.moneda_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = chq.entidad_id
            LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = chq.tesoreria_cuenta_id
            WHERE chq.empresa_id = ?
              AND chq.cheque_tipo = 'TERCERO'
              AND chq.tabla_estado_registro_id = 29 "; // ESTADO_CHQ_EN_CARTERA

    $params = [$empresa_id];
    $types  = "i";

    if (!empty($filtros['categoria'])) {
        if ($filtros['categoria'] === 'VENCIDO') {
            $sql .= " AND chq.f_pago < CURDATE() ";
        } elseif ($filtros['categoria'] === 'HOY') {
            $sql .= " AND chq.f_pago = CURDATE() ";
        } elseif ($filtros['categoria'] === 'PROX_7_DIAS') {
            $sql .= " AND chq.f_pago > CURDATE() AND DATEDIFF(chq.f_pago, CURDATE()) <= 7 ";
        }
    }

    $sql .= " ORDER BY chq.f_pago ASC, chq.importe DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resumen' => [], 'cheques' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $cheques = [];
    $total_cartera = 0.0;
    $cant_cartera = 0;
    $total_vencidos = 0.0;
    $cant_vencidos = 0;
    $total_hoy = 0.0;
    $cant_hoy = 0;
    $total_prox7 = 0.0;
    $cant_prox7 = 0;

    while ($chq = mysqli_fetch_assoc($res)) {
        $imp = floatval($chq['importe']);
        $total_cartera += $imp;
        $cant_cartera++;

        switch ($chq['categoria_cobro']) {
            case 'VENCIDO':
                $total_vencidos += $imp;
                $cant_vencidos++;
                break;
            case 'HOY':
                $total_hoy += $imp;
                $cant_hoy++;
                break;
            case 'PROX_7_DIAS':
                $total_prox7 += $imp;
                $cant_prox7++;
                break;
        }

        $cheques[] = $chq;
    }
    mysqli_stmt_close($stmt);

    return [
        'resumen' => [
            'total_cartera' => $total_cartera,
            'cant_cartera'  => $cant_cartera,
            'total_vencidos'=> $total_vencidos,
            'cant_vencidos' => $cant_vencidos,
            'total_hoy'     => $total_hoy,
            'cant_hoy'      => $cant_hoy,
            'total_prox7'   => $total_prox7,
            'cant_prox7'    => $cant_prox7
        ],
        'cheques' => $cheques
    ];
}

/**
 * 5. RECONCILIACIÓN Y CORRECCIÓN DE SALDO MATERIALIZADO
 *
 * Utilidad de auditoría/salud: recalcula el saldo_actual de la cuenta
 * a partir de la suma neta del libro inmutable de tesorería y lo sincroniza bajo lock.
 */
function reconciliarSaldoCuenta($conexion, $empresa_id, $tesoreria_cuenta_id, $usuario_id = 1)
{
    $empresa_id = intval($empresa_id);
    $tesoreria_cuenta_id = intval($tesoreria_cuenta_id);

    mysqli_begin_transaction($conexion);
    try {
        // Bloquear fila de la cuenta
        $sql_lock = "SELECT tesoreria_cuenta_id, saldo_actual 
                     FROM gestion__tesoreria_cuentas 
                     WHERE tesoreria_cuenta_id = ? AND empresa_id = ? 
                     FOR UPDATE";
        $stmt_l = mysqli_prepare($conexion, $sql_lock);
        mysqli_stmt_bind_param($stmt_l, "ii", $tesoreria_cuenta_id, $empresa_id);
        mysqli_stmt_execute($stmt_l);
        $res_l = mysqli_stmt_get_result($stmt_l);
        $cuenta = mysqli_fetch_assoc($res_l);
        mysqli_stmt_close($stmt_l);

        if (!$cuenta) {
            throw new Exception("Cuenta no encontrada o no pertenece a la empresa.");
        }

        // Calcular saldo del libro
        $sql_calc = "SELECT COALESCE(SUM(CASE WHEN tipo_movimiento = 'INGRESO' THEN importe ELSE -importe END), 0.00) AS saldo_libro
                     FROM gestion__tesoreria_movimientos
                     WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1";
        $stmt_c = mysqli_prepare($conexion, $sql_calc);
        mysqli_stmt_bind_param($stmt_c, "ii", $tesoreria_cuenta_id, $empresa_id);
        mysqli_stmt_execute($stmt_c);
        $res_c = mysqli_stmt_get_result($stmt_c);
        $row_c = mysqli_fetch_assoc($res_c);
        mysqli_stmt_close($stmt_c);

        $saldo_nuevo = round(floatval($row_c['saldo_libro'] ?? 0), 2);

        // Actualizar
        $sql_upd = "UPDATE gestion__tesoreria_cuentas 
                    SET saldo_actual = ?, fecha_modificacion = NOW(), usuario_modificacion_id = ? 
                    WHERE tesoreria_cuenta_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param($stmt_u, "dii", $saldo_nuevo, $usuario_id, $tesoreria_cuenta_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        mysqli_commit($conexion);
        return [
            'success'      => true,
            'saldo_anterior'=> floatval($cuenta['saldo_actual']),
            'saldo_nuevo'   => $saldo_nuevo,
            'mensaje'       => "Saldo reconciliado exitosamente."
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'error'   => $e->getMessage()
        ];
    }
}
