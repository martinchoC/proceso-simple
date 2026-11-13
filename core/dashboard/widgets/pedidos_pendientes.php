<?php
/**
 * Pedidos de clientes a preparar (estado 5 "Confirmado - Pend. Revisión") de las sucursales del
 * usuario, y como dato aparte los que están En preparación (9). Se calcula al vuelo: un COUNT sobre
 * ix_vped_emp_estado_suc. También alimenta el badge del acceso directo (usa 'valor').
 */
return function (mysqli $c, array $ctx, array $params): array {
    $sql = "SELECT vp.tabla_estado_registro_id AS estado, vp.sucursal_id, s.sucursal_nombre, COUNT(*) AS n
            FROM gestion__ventas_pedidos vp
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = vp.sucursal_id
            WHERE vp.empresa_id = ? AND vp.tabla_estado_registro_id IN (5, 9)"
         . dash_filtro_sucursal($c, $ctx, 'vp.sucursal_id') . "
            GROUP BY vp.tabla_estado_registro_id, vp.sucursal_id, s.sucursal_nombre
            ORDER BY s.sucursal_nombre";

    $a_preparar = 0;
    $en_preparacion = 0;
    $por_sucursal = [];
    foreach (dash_filas($c, $sql, 'i', [$ctx['empresa_id']]) as $r) {
        $n = (int) $r['n'];
        if ((int) $r['estado'] === 5) {
            $a_preparar += $n;
            $por_sucursal[] = ['etiqueta' => (string) ($r['sucursal_nombre'] ?? ('Sucursal ' . $r['sucursal_id'])),
                               'valor' => $n, 'formato' => 'entero'];
        } else {
            $en_preparacion += $n;
        }
    }

    $sub = [['etiqueta' => 'En preparación', 'valor' => $en_preparacion, 'formato' => 'entero']];
    if (count($por_sucursal) > 1) {
        $sub = array_merge($sub, $por_sucursal);
    }
    return ['tipo' => 'kpi', 'valor' => $a_preparar, 'formato' => 'entero', 'sub' => $sub];
};
