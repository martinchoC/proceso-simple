<?php
/**
 * Top 5 clientes (lado = ventas) o proveedores (lado = compras) por importe neto sin IVA ni otros
 * impuestos de los últimos `dias` días (por fecha de emisión), con el resto agrupado. Estados
 * Confirmado / Cancelado parcial / Cancelado total; la NC resta por el signo de su tipo. Ventas sin
 * cliente (consumidor final) se agrupan aparte. Sucursales: las del usuario.
 */
return function (mysqli $c, array $ctx, array $params): array {
    $compras = ($params['lado'] ?? '') === 'compras';
    $tabla_id = dash_tabla_id($c, $compras ? 'gestion__facturas_proveedores' : 'gestion__ventas_facturas');
    $dias = max(1, min(366, (int) ($params['dias'] ?? 30)));
    $hasta = $ctx['hoy'];
    $desde = date('Y-m-d', strtotime("$hasta -" . ($dias - 1) . " days"));

    $sql = "SELECT c.entidad_id, e.entidad_nombre,
                   SUM(IF(ct.signo = '-', -1, 1) * (c.importe_total - c.importe_iva - c.importe_otros_impuestos) * c.tipo_cambio) AS neto
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id AND e.empresa_id = c.empresa_id
            WHERE c.empresa_id = ? AND c.tabla_origen_id = ?
              AND c.f_emision BETWEEN ? AND ?
              AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL', 'CANC_TOTAL')"
         . dash_filtro_sucursal($c, $ctx, 'c.sucursal_id') . "
            GROUP BY c.entidad_id, e.entidad_nombre
            ORDER BY neto DESC";

    $series = [];
    $resto = 0.0;
    $total = 0.0;
    foreach (dash_filas($c, $sql, 'iiss', [$ctx['empresa_id'], $tabla_id, $desde, $hasta]) as $r) {
        $neto = (float) $r['neto'];
        $total += $neto;
        if (count($series) < 5 && $neto > 0.009) {
            $nombre = (int) $r['entidad_id'] > 0 && $r['entidad_nombre'] !== null ? (string) $r['entidad_nombre'] : 'Consumidor final';
            $series[] = ['etiqueta' => $nombre, 'valor' => round($neto, 2)];
        } else {
            $resto += $neto;
        }
    }
    if (abs($resto) > 0.009) {
        $series[] = ['etiqueta' => 'Resto', 'valor' => round($resto, 2), 'resto' => true];
    }

    return [
        'tipo'    => 'minichart',
        'grafico' => 'barras',
        'formato' => 'moneda',
        'series'  => $series,
        'total'   => ['etiqueta' => "Total $dias días", 'valor' => round($total, 2), 'formato' => 'moneda'],
        'vacio'   => $compras ? "Sin compras en los últimos $dias días." : "Sin ventas en los últimos $dias días.",
    ];
};
