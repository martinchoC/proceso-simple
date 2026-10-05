<?php
/**
 * scratch/test_fase6_obligaciones_rendiciones.php
 * Test automatizado end-to-end para Fase 6: Obligaciones Impositivas y Rendiciones
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion/tesoreria_movimientos_model.php';
require_once __DIR__ . '/../modules/gestion/obligaciones_rendiciones_model.php';

$empresa_id = 2;
$sucursal_id = 2;
$usuario_id = 1;
$entidad_id = 3;
$conexion = $conn;

// Asegurar saldo para idempotencia de pruebas
$conexion->query("UPDATE gestion__tesoreria_cuentas SET saldo_actual = GREATEST(saldo_actual, 10000.00) WHERE tesoreria_cuenta_id IN (1, 3)");

echo "=== INICIANDO TEST FASE 6: OBLIGACIONES IMPOSITIVAS Y RENDICIONES ===\n";

// =========================================================================
// 1. TEST FLUJO OBLIGACIÓN IMPOSITIVA (I_LIQ -> I_PAG -> D8)
// =========================================================================
echo "\n--- 1. FLUJO LIQUIDACIÓN IMPOSITIVA Y PAGO (I_LIQ / I_PAG) ---\n";

$nro_vep = "VEP-" . rand(100000, 999999);
$monto_impuesto = 1200.00;

echo "1.1 Registrando Obligación Impositiva I_LIQ por $$monto_impuesto (VEP: $nro_vep)...\n";
$res_liq = registrarObligacionImpositiva($conexion, [
    'entidad_id'     => $entidad_id,
    'periodo_fiscal' => '2026-10',
    'f_vto'          => date('Y-m-d', strtotime('+10 days')),
    'descripcion'    => 'IVA Posición Mensual Oct-2026 Test',
    'importe_total'  => $monto_impuesto,
    'nro_vep'        => $nro_vep,
    'nro_ddjj'       => 'F.2002',
    'sucursal_id'    => $sucursal_id
], $empresa_id, $usuario_id);

if (!$res_liq['success']) {
    die("ERROR al registrar obligación impositiva: " . $res_liq['error'] . "\n");
}
$ob_id = $res_liq['obligacion_id'];
$comp_liq_id = $res_liq['comprobante_id'];
echo "    Obligación registrada con éxito. ID: $ob_id, Comprobante ID: $comp_liq_id, N°: {$res_liq['numero']}\n";

// Verificar estado inicial y pendiente
$r_liq = $conexion->query("SELECT importe_total, importe_pendiente, tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = $comp_liq_id")->fetch_assoc();
echo "    Comprobante I_LIQ pendiente inicial: $" . number_format($r_liq['importe_pendiente'], 2) . "\n";
assert(abs(floatval($r_liq['importe_pendiente']) - $monto_impuesto) < 0.01, "El importe pendiente debe ser igual al total");

// 1.2 Pagar la obligación impositiva con I_PAG desde Banco Nación (cuenta 3)
echo "1.2 Pagando VEP $nro_vep con I_PAG desde Banco Nación (Cuenta 3)...\n";
$res_pag = pagarObligacionImpositiva($conexion, [
    'obligacion_id'       => $ob_id,
    'tesoreria_cuenta_id' => 3, // Banco Nación
    'f_pago'              => date('Y-m-d'),
    'importe_pago'        => $monto_impuesto,
    'nro_operacion'       => 'TRX-VEP-' . rand(1000, 9999)
], $empresa_id, $usuario_id);

if (!$res_pag['success']) {
    die("ERROR al pagar obligación impositiva: " . $res_pag['error'] . "\n");
}
$pago_id = $res_pag['pago_impuesto_id'];
$comp_pag_id = $res_pag['comprobante_pag_id'];
echo "    Pago registrado con éxito. ID: $pago_id, Comprobante I_PAG: {$res_pag['numero']}, Asiento ID: {$res_pag['asiento_id']}\n";

// 1.3 Verificar Recálculo D8 en la Obligación Original
$r_liq_post = $conexion->query("SELECT importe_total, importe_pendiente, tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = $comp_liq_id")->fetch_assoc();
echo "    Comprobante I_LIQ post-pago: Pendiente=$" . number_format($r_liq_post['importe_pendiente'], 2) . ", Estado=" . $r_liq_post['tabla_estado_registro_id'] . "\n";

if (floatval($r_liq_post['importe_pendiente']) > 0.01) {
    die("ERROR: El importe pendiente de la obligación debió ser 0.\n");
}
if (intval($r_liq_post['tabla_estado_registro_id']) !== ESTADO_CANCELADO_TOTAL) {
    die("ERROR: El estado de la obligación según D8 debía ser CANCELADO_TOTAL (2123).\n");
}
echo "    [OK] Regla D8 comprobada: La obligación I_LIQ quedó totalmente cancelada.\n";

// =========================================================================
// 2. TEST FLUJO RENDICIONES (RG_ANT -> RG_REND -> RG_REEM)
// =========================================================================
echo "\n--- 2. FLUJO RENDICIONES DE GASTOS (RG_ANT / RG_REND / RG_REEM) ---\n";

// 2.1 Entregar Anticipo a Rendir (RG_ANT) por $800 desde Caja Bahía Blanca (1)
$monto_ant = 800.00;
echo "2.1 Entregando Anticipo a Rendir por $$monto_ant desde Caja (1)...\n";
$res_ant = registrarAnticipoRendir($conexion, [
    'entidad_id'          => $entidad_id,
    'tesoreria_cuenta_id' => 1,
    'f_emision'           => date('Y-m-d'),
    'importe_total'       => $monto_ant,
    'descripcion'         => 'Anticipo para compras operativas test'
], $empresa_id, $usuario_id);

if (!$res_ant['success']) {
    die("ERROR al registrar anticipo: " . $res_ant['error'] . "\n");
}
$ant_id = $res_ant['anticipo_id'];
$comp_ant_id = $res_ant['comprobante_id'];
echo "    Anticipo registrado con éxito. ID: $ant_id, Comprobante N°: {$res_ant['numero']}\n";

// 2.2 Registrar Rendición de Gastos (RG_REND) por $1.100 aplicando el anticipo de $800
// Gastos ($1.100) > Anticipo ($800) => Saldo a Reembolsar = $300
$monto_gastos = 1100.00;
echo "2.2 Registrando Rendición de Gastos por $$monto_gastos absorbiendo anticipo de $$monto_ant...\n";
$res_rend = registrarRendicionGastos($conexion, [
    'entidad_id'   => $entidad_id,
    'f_emision'    => date('Y-m-d'),
    'anticipo_id'  => $ant_id,
    'descripcion'  => 'Rendición de compras varias e insumos',
    'items'        => [
        [
            'f_gasto'                 => date('Y-m-d'),
            'categoria_gasto_id'      => 1,
            'proveedor_nombre'        => 'Librería Central',
            'comprobante_externo_nro' => 'TK-001-445',
            'importe'                 => 600.00
        ],
        [
            'f_gasto'                 => date('Y-m-d'),
            'categoria_gasto_id'      => 1,
            'proveedor_nombre'        => 'Ferretería Industrial',
            'comprobante_externo_nro' => 'FC-004-9812',
            'importe'                 => 500.00
        ]
    ]
], $empresa_id, $usuario_id);

if (!$res_rend['success']) {
    die("ERROR al registrar rendición: " . $res_rend['error'] . "\n");
}
$rend_id = $res_rend['rendicion_id'];
$comp_rend_id = $res_rend['comprobante_id'];
$reembolso_esperado = $res_rend['monto_a_reembolsar'];
echo "    Rendición registrada con éxito. ID: $rend_id, Comprobante N°: {$res_rend['numero']}\n";
echo "    Saldo a reembolsar calculado: $$reembolso_esperado (esperado: $300.00)\n";

if (abs($reembolso_esperado - 300.00) > 0.01) {
    die("ERROR: El saldo a reembolsar debía ser exactamente $300.00\n");
}

// Verificar que el anticipo original haya quedado cancelado por D8
$r_ant_post = $conexion->query("SELECT importe_pendiente, tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = $comp_ant_id")->fetch_assoc();
echo "    Anticipo RG_ANT post-rendición: Pendiente=$" . number_format($r_ant_post['importe_pendiente'], 2) . ", Estado=" . $r_ant_post['tabla_estado_registro_id'] . "\n";
if (floatval($r_ant_post['importe_pendiente']) > 0.01) {
    die("ERROR: El anticipo debió quedar totalmente consumido (pendiente = 0).\n");
}
echo "    [OK] Regla D8 comprobada: El anticipo quedó saldado tras la rendición.\n";

// 2.3 Reembolsar el saldo a favor del responsable ($300) con RG_REEM desde Caja (1)
echo "2.3 Pagando Reembolso de $$reembolso_esperado con RG_REEM desde Caja (1)...\n";
$res_reem = reembolsarRendicion($conexion, [
    'rendicion_id'        => $rend_id,
    'tesoreria_cuenta_id' => 1,
    'f_pago'              => date('Y-m-d'),
    'importe_reembolso'   => $reembolso_esperado
], $empresa_id, $usuario_id);

if (!$res_reem['success']) {
    die("ERROR al reembolsar rendición: " . $res_reem['error'] . "\n");
}
echo "    Reembolso registrado con éxito. Comprobante N°: {$res_reem['numero']}, Asiento ID: {$res_reem['asiento_id']}\n";

// Verificar que la rendición haya quedado con pendiente 0 y cancelada
$r_rend_post = $conexion->query("SELECT importe_pendiente, tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = $comp_rend_id")->fetch_assoc();
echo "    Rendición RG_REND post-reembolso: Pendiente=$" . number_format($r_rend_post['importe_pendiente'], 2) . ", Estado=" . $r_rend_post['tabla_estado_registro_id'] . "\n";
if (floatval($r_rend_post['importe_pendiente']) > 0.01) {
    die("ERROR: La rendición debió quedar totalmente saldada tras el reembolso.\n");
}
echo "    [OK] Regla D8 comprobada: La rendición quedó totalmente cancelada.\n";

echo "\n>>> TEST FASE 6 COMPLETADO CON 100% DE ÉXITO <<<\n";
