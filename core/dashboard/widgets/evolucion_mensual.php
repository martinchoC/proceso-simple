<?php
/**
 * Evolución mensual de ventas y compras de los últimos `meses` meses (parámetro, 3 a 24; incluye el
 * mes en curso hasta hoy). Mismo criterio que los demás indicadores: neto sin IVA ni otros impuestos,
 * Confirmado / Cancelado parcial / Cancelado total, NC restando por el signo del tipo, moneda local,
 * por fecha de emisión y sucursales del usuario.
 *
 * El permiso del widget es el de ventas (su pagina_id). La serie de compras se agrega solo si el
 * usuario también tiene la página de facturas de proveedores del módulo.
 */
return function (mysqli $c, array $ctx, array $params): array {
    $meses = max(3, min(24, (int) ($params['meses'] ?? 12)));
    $ts_hoy = strtotime($ctx['hoy']);
    $desde = date('Y-m-01', strtotime('-' . ($meses - 1) . ' months', strtotime(date('Y-m-01', $ts_hoy))));
    $hasta = $ctx['hoy'];

    $tabla_ventas = dash_tabla_id($c, 'gestion__ventas_facturas');
    $tabla_compras = dash_tabla_id($c, 'gestion__facturas_proveedores');
    $ve_compras = false;
    foreach ($ctx['paginas'] as $p) {
        if ($p['url'] === 'facturas_proveedores.php') {
            $ve_compras = true;
            break;
        }
    }
    $tablas = $ve_compras ? [$tabla_ventas, $tabla_compras] : [$tabla_ventas];

    $sql = "SELECT c.tabla_origen_id, DATE_FORMAT(c.f_emision, '%Y-%m') AS mes,
                   SUM(IF(ct.signo = '-', -1, 1) * (c.importe_total - c.importe_iva - c.importe_otros_impuestos) * c.tipo_cambio) AS neto
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ? AND c.tabla_origen_id IN (" . dash_marcadores(count($tablas)) . ")
              AND c.f_emision BETWEEN ? AND ?
              AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL', 'CANC_TOTAL')"
         . dash_filtro_sucursal($c, $ctx, 'c.sucursal_id') . "
            GROUP BY c.tabla_origen_id, DATE_FORMAT(c.f_emision, '%Y-%m')";
    $tipos = 'i' . str_repeat('i', count($tablas)) . 'ss';
    $filas = dash_filas($c, $sql, $tipos, array_merge([$ctx['empresa_id']], $tablas, [$desde, $hasta]));

    $por = [];
    foreach ($filas as $r) {
        $por[(int) $r['tabla_origen_id']][$r['mes']] = (float) $r['neto'];
    }

    $nombres = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $categorias = [];
    $claves = [];
    for ($i = 0; $i < $meses; $i++) {
        $ts = strtotime("+$i months", strtotime($desde));
        $claves[] = date('Y-m', $ts);
        $categorias[] = $nombres[(int) date('n', $ts) - 1] . ' ' . date('y', $ts);
    }
    $serie = function (int $tabla) use ($por, $claves) {
        return array_map(function ($k) use ($por, $tabla) {
            return round($por[$tabla][$k] ?? 0.0, 2);
        }, $claves);
    };

    $series = [['etiqueta' => 'Ventas', 'valores' => $serie($tabla_ventas)]];
    if ($ve_compras) {
        $series[] = ['etiqueta' => 'Compras', 'valores' => $serie($tabla_compras)];
    }
    $sub = array_map(function ($s) use ($meses) {
        return ['etiqueta' => $s['etiqueta'] . " ($meses meses)", 'valor' => round(array_sum($s['valores']), 2), 'formato' => 'moneda'];
    }, $series);

    return [
        'tipo'       => 'minichart',
        'grafico'    => 'columnas',
        'formato'    => 'moneda',
        'categorias' => $categorias,
        'series'     => $series,
        'sub'        => $sub,
        'nota'       => 'El último mes va hasta hoy.',
    ];
};
