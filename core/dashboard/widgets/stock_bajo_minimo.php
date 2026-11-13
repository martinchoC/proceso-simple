<?php
/**
 * Stock bajo el mínimo: lee las alertas de reposición abiertas, que ya mantiene materializadas
 * stockEvaluarNivel() al mover stock (gestion__stock_alertas). Cantidad con salContarAbiertas()
 * (filtra sucursales) y las 5 con más faltante.
 */
return function (mysqli $c, array $ctx, array $params): array {
    require_once __DIR__ . '/../../../modules/gestion/stock_alertas_model.php';
    require_once __DIR__ . '/../../formato.php';

    $total = salContarAbiertas($c, $ctx['empresa_id'], $ctx['usuario_id']);

    $sql = "SELECT p.producto_codigo, p.producto_nombre, b.boca_nombre, a.stock_actual, a.stock_minimo,
                   a.cantidad_sugerida, a.tabla_estado_registro_id
            FROM gestion__stock_alertas a
            JOIN gestion__productos p ON p.producto_id = a.producto_id
            JOIN gestion__bocas b ON b.boca_id = a.boca_id
            WHERE a.empresa_id = ? AND a.abierta = 1"
         . dash_filtro_sucursal($c, $ctx, 'b.sucursal_id') . "
            ORDER BY (a.stock_minimo - a.stock_actual) DESC, a.stock_alerta_id
            LIMIT 5";

    $filas = [];
    foreach (dash_filas($c, $sql, 'i', [$ctx['empresa_id']]) as $r) {
        $actual = (float) $r['stock_actual'];
        $filas[] = [
            'texto'   => trim($r['producto_codigo'] . ' ' . $r['producto_nombre']),
            'detalle' => $r['boca_nombre'] . ' · mínimo ' . rtrim(rtrim(fmt_numero((float) $r['stock_minimo'], 2), '0'), SEPARADORES_DECIMAL[fmt_actual()['decimal']])
                       . ((int) $r['tabla_estado_registro_id'] === 18 ? ' · en curso' : ''),
            'valor'   => $actual,
            'formato' => 'decimal',
            'alerta'  => $actual <= 0,
        ];
    }

    return [
        'tipo'  => 'lista',
        'filas' => $filas,
        'total' => ['etiqueta' => 'Productos bajo el mínimo', 'valor' => $total, 'formato' => 'entero'],
        'vacio' => 'Ningún producto está bajo el mínimo.',
    ];
};
