<?php
/**
 * Comprobantes de proveedores con saldo que vencen entre hoy y hoy + dias (parámetro, 1 a 60), los 5
 * más próximos, más el total y lo ya vencido. Mismo criterio de saldo que la cuenta corriente de
 * proveedores (ctacteSqlComprobantes: tipos de la matriz de cancelaciones, pendiente por imputaciones).
 */
return function (mysqli $c, array $ctx, array $params): array {
    require_once __DIR__ . '/../../../modules/gestion/proveedores_ctacte_model.php';
    require_once __DIR__ . '/../../formato.php';

    $dias = max(1, min(60, (int) ($params['dias'] ?? 7)));
    $hoy = $ctx['hoy'];
    $hasta = date('Y-m-d', strtotime("$hoy +$dias days"));

    $base = ctacteSqlComprobantes($c, $ctx['empresa_id'], null);
    if ($base === null) {
        return ['tipo' => 'lista', 'filas' => [], 'vacio' => 'Falta configurar qué comprobantes cancela el pago.'];
    }
    [$sql, $types, $params_sql] = $base;

    $sql_tot = "SELECT
                    SUM(CASE WHEN COALESCE(b.f_vto, b.f_emision) BETWEEN ? AND ? THEN b.pendiente_local ELSE 0 END) AS por_vencer,
                    SUM(CASE WHEN COALESCE(b.f_vto, b.f_emision) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS cant_por_vencer,
                    SUM(CASE WHEN COALESCE(b.f_vto, b.f_emision) < ? THEN b.pendiente_local ELSE 0 END) AS vencido
                FROM ($sql) b
                WHERE b.signo_ctacte = 1 AND b.pendiente > 0.009";
    $tot = dash_filas($c, $sql_tot, 'sssss' . $types, array_merge([$hoy, $hasta, $hoy, $hasta, $hoy], $params_sql))[0] ?? [];

    $sql_lista = "SELECT b.comprobante_id, b.comprobante_pv, b.comprobante_nro, COALESCE(b.f_vto, b.f_emision) AS vto,
                         b.pendiente_local, e.entidad_nombre, ct.codigo AS tipo_codigo
                  FROM ($sql) b
                  JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = b.comprobante_tipo_id
                  LEFT JOIN gestion__entidades e ON e.entidad_id = b.entidad_id
                  WHERE b.signo_ctacte = 1 AND b.pendiente > 0.009
                    AND COALESCE(b.f_vto, b.f_emision) BETWEEN ? AND ?
                  ORDER BY vto, b.comprobante_id
                  LIMIT 5";
    $filas = [];
    foreach (dash_filas($c, $sql_lista, $types . 'ss', array_merge($params_sql, [$hoy, $hasta])) as $r) {
        $nro = str_pad((string) (int) $r['comprobante_pv'], 5, '0', STR_PAD_LEFT) . '-'
             . str_pad((string) (int) $r['comprobante_nro'], 8, '0', STR_PAD_LEFT);
        $filas[] = [
            'texto'   => (string) ($r['entidad_nombre'] ?? ''),
            'detalle' => $r['tipo_codigo'] . ' ' . $nro . ' · vence ' . fmt_fecha($r['vto']),
            'valor'   => round((float) $r['pendiente_local'], 2),
            'formato' => 'moneda',
            'alerta'  => $r['vto'] === $hoy,
        ];
    }

    return [
        'tipo'  => 'lista',
        'filas' => $filas,
        'total' => ['etiqueta' => "Vence en $dias días", 'valor' => round((float) ($tot['por_vencer'] ?? 0), 2),
                    'formato' => 'moneda', 'cantidad' => (int) ($tot['cant_por_vencer'] ?? 0)],
        'sub'   => [['etiqueta' => 'Ya vencido', 'valor' => round((float) ($tot['vencido'] ?? 0), 2), 'formato' => 'moneda',
                     'alerta' => (float) ($tot['vencido'] ?? 0) > 0.009]],
        'vacio' => "Nada vence en los próximos $dias días.",
    ];
};
