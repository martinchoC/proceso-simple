<?php
/**
 * Ventas netas sin IVA ni otros impuestos (percepciones): facturas, ND y NC de venta en Confirmado /
 * Cancelado parcial / Cancelado total, incluidas las X. No entran las "Pendiente de ARCA" (todavía
 * no están emitidas). La NC resta por el signo de su tipo. Moneda local (importe * tipo_cambio).
 * Sucursales: las que el usuario tiene habilitadas.
 *
 * Parámetros: periodo = dia | mes. "mes" compara del 1 a hoy contra el mismo tramo del mes anterior
 * (contra el mes anterior completo, a mitad de mes siempre daría "bajando").
 */

/** Neto por día de ventas entre dos fechas: ['Y-m-d' => [neto, cantidad]]. */
function dash_ventas_por_dia(mysqli $c, array $ctx, int $tabla_id, string $desde, string $hasta): array
{
    $sql = "SELECT c.f_emision,
                   SUM(IF(ct.signo = '-', -1, 1) * (c.importe_total - c.importe_iva - c.importe_otros_impuestos) * c.tipo_cambio) AS neto,
                   COUNT(*) AS cantidad
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ? AND c.tabla_origen_id = ?
              AND c.f_emision BETWEEN ? AND ?
              AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL', 'CANC_TOTAL')";
    $sql .= dash_filtro_sucursal($c, $ctx, 'c.sucursal_id') . " GROUP BY c.f_emision";

    $out = [];
    foreach (dash_filas($c, $sql, 'iiss', [$ctx['empresa_id'], $tabla_id, $desde, $hasta]) as $r) {
        $out[$r['f_emision']] = [(float) $r['neto'], (int) $r['cantidad']];
    }
    return $out;
}

return function (mysqli $c, array $ctx, array $params): array {
    $tabla_id = dash_tabla_id($c, 'gestion__ventas_facturas');
    $hoy = $ctx['hoy'];

    if (($params['periodo'] ?? 'dia') !== 'mes') {
        $dias = dash_ventas_por_dia($c, $ctx, $tabla_id, $hoy, $hoy);
        [$neto, $cant] = $dias[$hoy] ?? [0.0, 0];
        return [
            'tipo'    => 'kpi',
            'valor'   => round($neto, 2),
            'formato' => 'moneda',
            'sub'     => [['etiqueta' => 'Comprobantes', 'valor' => $cant, 'formato' => 'entero']],
        ];
    }

    $ts_hoy = strtotime($hoy);
    $desde = date('Y-m-01', $ts_hoy);
    $dia = (int) date('j', $ts_hoy);
    $ant_desde = date('Y-m-01', strtotime('first day of previous month', $ts_hoy));
    // Mismo día del mes anterior, o su último día si es más corto (31 de marzo → 28/29 de febrero).
    $ant_ultimo = (int) date('t', strtotime($ant_desde));
    $ant_hasta = date('Y-m-', strtotime($ant_desde)) . str_pad((string) min($dia, $ant_ultimo), 2, '0', STR_PAD_LEFT);

    $actual = dash_ventas_por_dia($c, $ctx, $tabla_id, $desde, $hoy);
    $anterior = dash_ventas_por_dia($c, $ctx, $tabla_id, $ant_desde, $ant_hasta);

    $serie = [];
    $total = 0.0;
    $cant = 0;
    for ($d = 1; $d <= $dia; $d++) {
        $f = date('Y-m-', $ts_hoy) . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $v = $actual[$f][0] ?? 0.0;
        $total += $v;
        $cant += $actual[$f][1] ?? 0;
        $serie[] = round($total, 2);   // acumulado: la línea muestra el ritmo del mes
    }
    $total_ant = array_sum(array_column($anterior, 0));
    $variacion = abs($total_ant) > 0.009 ? round(($total - $total_ant) / abs($total_ant) * 100, 1) : null;

    return [
        'tipo'          => 'kpi',
        'valor'         => round($total, 2),
        'formato'       => 'moneda',
        'variacion_pct' => $variacion,
        'sub'           => [
            ['etiqueta' => 'Mismo tramo del mes anterior', 'valor' => round($total_ant, 2), 'formato' => 'moneda'],
            ['etiqueta' => 'Comprobantes', 'valor' => $cant, 'formato' => 'entero'],
        ],
        'serie'         => $serie,
    ];
};
