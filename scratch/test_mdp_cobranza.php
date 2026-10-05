<?php
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion/cobranzas_model.php';

$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$empresa_id = 2;

echo "========================================================\n";
echo "TEST MAR DEL PLATA (SUCURSAL 2, BOCA 2, PV 2)\n";
echo "========================================================\n";

// Saldo antes caja Mar del Plata
$res_s0 = $conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 2");
$saldo_mdp_antes = floatval($res_s0->fetch_assoc()['saldo_actual']);
echo "Saldo antes Caja Mar del Plata: $saldo_mdp_antes\n";

$data_mdp = [
    'empresa_id'          => 2,
    'sucursal_id'         => 2, // Mar del Plata
    'comprobante_pv'      => 2, // PV 2 (Boca 2 - Comercio Mar del Plata)
    'entidad_id'          => 313,
    'f_emision'           => date('Y-m-d'),
    'f_contabilidad'      => date('Y-m-d'),
    'moneda_id'           => 1,
    'tipo_cambio'         => 1.0,
    'importe_total'       => 2300.00,
    'observaciones'       => 'Test cobro Mar del Plata Boca 2 -> Caja Mar del Plata',
    'usuario_id'          => 1,
    'medios'              => [
        [
            'medio_pago_id'      => 1, // Efectivo
            'medio_pago_tipo'    => 'EFECTIVO',
            'tesoreria_cuenta_id'=> null, // Auto-asigna Caja Mar del Plata (ID 2)
            'importe'            => 2300.00,
            'importe_comprobante'=> 2300.00,
            'tipo_cambio'        => 1.0,
            'moneda_id'          => 1
        ]
    ],
    'imputaciones'        => []
];

$cob_mdp_id = guardarCobranzaBorrador($conexion, $data_mdp);
echo "Cobranza MDP Borrador ID: $cob_mdp_id\n";

// Verificar caja asignada
$res_m = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__cobranzas_medios WHERE cobranza_id = $cob_mdp_id");
$cta_mdp = $res_m->fetch_assoc()['tesoreria_cuenta_id'];
echo "Caja asignada en cobranzas_medios: $cta_mdp (esperado: 2 - Caja Mar del Plata)\n";

$res_conf_mdp = confirmarCobranza($conexion, $cob_mdp_id, 2, 1);
echo "Resultado confirmarCobranza MDP: " . json_encode($res_conf_mdp, JSON_UNESCAPED_UNICODE) . "\n";

// Saldo actual caja Mar del Plata
$res_s1 = $conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 2");
$saldo_mdp_nuevo = floatval($res_s1->fetch_assoc()['saldo_actual']);
echo "Nuevo saldo Caja Mar del Plata: $saldo_mdp_nuevo (Incremento: " . ($saldo_mdp_nuevo - $saldo_mdp_antes) . ")\n";
