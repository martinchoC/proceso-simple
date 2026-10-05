<?php
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion/cobranzas_model.php';

$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$empresa_id = 2;

echo "========================================================\n";
echo "1. VERIFICACIÓN DE PUNTOS DE VENTA POR BOCA PARA RCX\n";
echo "========================================================\n";
$pvs = obtenerPuntosVentaCobranza($conexion, $empresa_id);
foreach ($pvs as $pv) {
    echo "PV #{$pv['punto_venta_id']}: {$pv['nombre']} | Sucursal: {$pv['sucursal_id']} ({$pv['sucursal_nombre']}) | Boca: {$pv['boca_id']} ({$pv['boca_nombre']})\n";
}

echo "\n========================================================\n";
echo "2. VERIFICACIÓN DE CAJAS POR SUCURSAL\n";
echo "========================================================\n";
$cuentas = obtenerCuentasTesoreriaEmpresa($conexion, $empresa_id);
foreach ($cuentas as $c) {
    if ($c['cuenta_tipo'] === 'CAJA') {
        echo "Caja #{$c['tesoreria_cuenta_id']}: {$c['tesoreria_cuenta']} | Sucursal ID: {$c['sucursal_id']}\n";
    }
}

echo "\n========================================================\n";
echo "3. TEST DE GUARDADO Y CONFIRMACIÓN CON PV DE BOCA (PV 1)\n";
echo "========================================================\n";

// Tomar numerador antes
$res_n = $conexion->query("SELECT ultimo_numero FROM gestion__comprobantes_numeradores WHERE empresa_id = 2 AND punto_venta_id = 1 AND comprobante_tipo_id = 24");
$num_antes = intval($res_n->fetch_assoc()['ultimo_numero'] ?? 0);
echo "Numerador actual para PV 1 (Recibo RCX): $num_antes\n";

// Saldo actual caja Bahía Blanca antes
$res_s0 = $conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 1");
$saldo_antes = floatval($res_s0->fetch_assoc()['saldo_actual']);
echo "Saldo antes Caja Bahía Blanca: $saldo_antes\n";

// Crear borrador
$data_test = [
    'empresa_id'          => 2,
    'sucursal_id'         => 1, // Bahía Blanca
    'comprobante_pv'      => 1, // PV 1 (Boca 1)
    'entidad_id'          => 313, // Walter Abejer
    'f_emision'           => date('Y-m-d'),
    'f_contabilidad'      => date('Y-m-d'),
    'moneda_id'           => 1,
    'tipo_cambio'         => 1.0,
    'importe_total'       => 1500.00,
    'observaciones'       => 'Test cobro PV Boca 1 -> Caja Bahía Blanca',
    'usuario_id'          => 1,
    'medios'              => [
        [
            'medio_pago_id'      => 1, // Efectivo
            'medio_pago_tipo'    => 'EFECTIVO',
            'tesoreria_cuenta_id'=> null, // Debería auto-asignar la Caja de Bahía Blanca (ID 1)
            'importe'            => 1500.00,
            'importe_comprobante'=> 1500.00,
            'tipo_cambio'        => 1.0,
            'moneda_id'          => 1
        ]
    ],
    'imputaciones'        => []
];

$cob_id = guardarCobranzaBorrador($conexion, $data_test);
echo "Resultado guardarCobranzaBorrador ID: $cob_id\n";
if ($cob_id > 0) {



    
    // Verificar qué caja asignó al medio
    $res_m = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__cobranzas_medios WHERE cobranza_id = $cob_id");
    $cta_asignada = $res_m->fetch_assoc()['tesoreria_cuenta_id'];
    echo "Caja asignada en cobranzas_medios: $cta_asignada (esperado: 1 - Caja Bahía Blanca)\n";
    
    // Confirmar cobranza
    $res_conf = confirmarCobranza($conexion, $cob_id, 2, 1);
    echo "Resultado confirmarCobranza: " . json_encode($res_conf, JSON_UNESCAPED_UNICODE) . "\n";
    
    // Verificar número emitido
    $res_c = $conexion->query("SELECT comprobante_pv, comprobante_nro FROM gestion__cobranzas WHERE cobranza_id = $cob_id");
    $cob_info = $res_c->fetch_assoc();
    echo "Recibo emitido: PV " . str_pad($cob_info['comprobante_pv'], 4, '0', STR_PAD_LEFT) . " N° " . str_pad($cob_info['comprobante_nro'], 8, '0', STR_PAD_LEFT) . "\n";
    
    // Verificar movimiento de tesorería
    $res_mov = $conexion->query("SELECT tesoreria_cuenta_id, tipo_movimiento, importe FROM gestion__tesoreria_movimientos WHERE comprobante_id = (SELECT comprobante_id FROM gestion__cobranzas WHERE cobranza_id = $cob_id)");
    $mov = $res_mov->fetch_assoc();
    echo "Movimiento en Tesorería -> Cuenta: {$mov['tesoreria_cuenta_id']} | Tipo: {$mov['tipo_movimiento']} | Importe: {$mov['importe']}\n";
    
    // Saldo actual de la caja
    $res_s = $conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 1");
    $saldo_nuevo = floatval($res_s->fetch_assoc()['saldo_actual']);
    echo "Nuevo saldo Caja Bahía Blanca: $saldo_nuevo (Incremento: " . ($saldo_nuevo - $saldo_antes) . ")\n";
    
    // Verificar numerador actualizado para PV 1
    $res_n2 = $conexion->query("SELECT ultimo_numero FROM gestion__comprobantes_numeradores WHERE empresa_id = 2 AND punto_venta_id = 1 AND comprobante_tipo_id = 24");
    $num_despues = intval($res_n2->fetch_assoc()['ultimo_numero'] ?? 0);
    echo "Numerador después para PV 1 (Recibo RCX): $num_despues\n";
}
