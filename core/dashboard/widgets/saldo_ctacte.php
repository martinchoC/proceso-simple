<?php
/**
 * Saldo de cuenta corriente de toda la empresa (lado = clientes | proveedores), con el mismo cálculo
 * que las páginas de cuenta corriente: ctacteResumen() sin entidad. No se filtra por sucursal, como
 * en esas páginas (un cliente debe lo mismo lo mire quien lo mire).
 *
 * clientes_ctacte_model.php y proveedores_ctacte_model.php declaran las mismas funciones: se incluye
 * uno solo por proceso. Cada widget va en su propio request, así que no se cruzan.
 */
return function (mysqli $c, array $ctx, array $params): array {
    $lado = ($params['lado'] ?? '') === 'proveedores' ? 'proveedores' : 'clientes';
    $modelo = __DIR__ . '/../../../modules/gestion/' . $lado . '_ctacte_model.php';
    if (function_exists('ctacteResumen') && !function_exists($lado === 'clientes' ? 'ctacteTiposCliente' : 'ctacteTiposProveedor')) {
        throw new RuntimeException('Ya está cargado el modelo de cuenta corriente del otro lado');
    }
    require_once $modelo;

    $r = ctacteResumen($c, $ctx['empresa_id'], null);
    if (empty($r['configurado'])) {
        return ['tipo' => 'kpi', 'valor' => null, 'formato' => 'moneda',
                'aviso' => 'Falta configurar qué comprobantes cancela el ' . ($lado === 'clientes' ? 'recibo' : 'pago') . '.'];
    }

    return [
        'tipo'    => 'kpi',
        'valor'   => $r['deuda'],
        'formato' => 'moneda',
        'sub'     => [
            ['etiqueta' => 'Vencido', 'valor' => $r['vencido'], 'formato' => 'moneda', 'alerta' => $r['vencido'] > 0.009],
            ['etiqueta' => 'Comprobantes pendientes', 'valor' => $r['comprobantes_pendientes'], 'formato' => 'entero'],
            ['etiqueta' => $lado === 'clientes' ? 'A favor de clientes' : 'A favor nuestro', 'valor' => $r['a_favor'], 'formato' => 'moneda'],
        ],
    ];
};
