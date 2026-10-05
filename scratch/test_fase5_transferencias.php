<?php
/**
 * scratch/test_fase5_transferencias.php
 * Test automatizado end-to-end para Movimientos Internos de Tesorería (Fase 5)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion/tesoreria_movimientos_model.php';

$empresa_id = 2;
$sucursal_id = 2;
$usuario_id = 1;
$conexion = $conn;

echo "=== INICIANDO TEST FASE 5: MOVIMIENTOS INTERNOS DE TESORERÍA ===\n";

// 1. Obtener cuentas y saldos iniciales
$cta_caja = $conexion->query("SELECT tesoreria_cuenta_id, tesoreria_cuenta, saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 1")->fetch_assoc();
$cta_banco = $conexion->query("SELECT tesoreria_cuenta_id, tesoreria_cuenta, saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 3")->fetch_assoc();

echo "1. Cuentas iniciales:\n";
echo "   Origen: {$cta_caja['tesoreria_cuenta']} (ID 1) - Saldo: $" . number_format($cta_caja['saldo_actual'], 2) . "\n";
echo "   Destino: {$cta_banco['tesoreria_cuenta']} (ID 3) - Saldo: $" . number_format($cta_banco['saldo_actual'], 2) . "\n";

$saldo_caja_inicial = floatval($cta_caja['saldo_actual']);
$saldo_banco_inicial = floatval($cta_banco['saldo_actual']);

// 2. Probar Regla RT14: Origen y destino iguales
echo "2. Probando validación Regla RT14 (Origen == Destino)...\n";
try {
    registrarTransferenciaInterna($conexion, [
        'sucursal_id'        => $sucursal_id,
        'f_movimiento'       => date('Y-m-d'),
        'cuenta_origen_id'   => 1,
        'cuenta_destino_id'  => 1,
        'importe'            => 100.0,
        'observaciones'      => 'Test RT14'
    ], $empresa_id, $usuario_id);
    die("ERROR: Se esperaba una excepción por RT14 y no ocurrió.\n");
} catch (Exception $e) {
    echo "   [OK] Excepción esperada capturada: " . $e->getMessage() . "\n";
}

// 3. Probar validación de saldo insuficiente
echo "3. Probando validación de saldo insuficiente...\n";
try {
    registrarTransferenciaInterna($conexion, [
        'sucursal_id'        => $sucursal_id,
        'f_movimiento'       => date('Y-m-d'),
        'cuenta_origen_id'   => 1,
        'cuenta_destino_id'  => 3,
        'importe'            => $saldo_caja_inicial + 999999.0,
        'observaciones'      => 'Test Saldo Excedido'
    ], $empresa_id, $usuario_id);
    die("ERROR: Se esperaba una excepción por saldo insuficiente y no ocurrió.\n");
} catch (Exception $e) {
    echo "   [OK] Excepción esperada capturada: " . $e->getMessage() . "\n";
}

// 4. Registrar transferencia exitosa de $500
$monto_trans = 500.00;
echo "4. Registrando transferencia real de $$monto_trans de Caja a Banco...\n";
$res_t = registrarTransferenciaInterna($conexion, [
    'sucursal_id'        => $sucursal_id,
    'f_movimiento'       => date('Y-m-d'),
    'cuenta_origen_id'   => 1,
    'cuenta_destino_id'  => 3,
    'importe'            => $monto_trans,
    'observaciones'      => 'Depósito de efectivo en banco test auto'
], $empresa_id, $usuario_id);

if (!$res_t['success']) {
    die("ERROR al registrar transferencia: " . $res_t['error'] . "\n");
}
$comp_id = $res_t['comprobante_id'];
$nro_comp = $res_t['numero'];
$asiento_id = $res_t['asiento_id'];
echo "   Transferencia registrada con éxito. Comprobante ID: $comp_id, N°: $nro_comp, Asiento ID: $asiento_id\n";

// Verificar saldos actualizados
$saldo_caja_post = floatval($conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 1")->fetch_assoc()['saldo_actual']);
$saldo_banco_post = floatval($conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 3")->fetch_assoc()['saldo_actual']);

echo "   Saldos post-transferencia:\n";
echo "   - Caja: $" . number_format($saldo_caja_post, 2) . " (esperado: $" . number_format($saldo_caja_inicial - $monto_trans, 2) . ")\n";
echo "   - Banco: $" . number_format($saldo_banco_post, 2) . " (esperado: $" . number_format($saldo_banco_inicial + $monto_trans, 2) . ")\n";

if (abs($saldo_caja_post - ($saldo_caja_inicial - $monto_trans)) > 0.01) {
    die("ERROR: El saldo de caja no disminuyó correctamente.\n");
}
if (abs($saldo_banco_post - ($saldo_banco_inicial + $monto_trans)) > 0.01) {
    die("ERROR: El saldo de banco no aumentó correctamente.\n");
}

// Verificar asiento contable balanceado
if ($asiento_id > 0) {
    $res_det = $conexion->query("SELECT SUM(importe_local) AS balance, SUM(ABS(importe_local)) AS volumen FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = $asiento_id");
    $r_b = $res_det->fetch_assoc();
    $balance = floatval($r_b['balance'] ?? 0);
    $volumen = floatval($r_b['volumen'] ?? 0);

    echo "   Asiento contable ID $asiento_id balance:\n";
    echo "   - Balance neto (Debe - Haber): $" . number_format($balance, 2) . "\n";
    echo "   - Volumen total (Debe + Haber): $" . number_format($volumen, 2) . "\n";
    if (abs($balance) > 0.01 || abs($volumen - (2 * $monto_trans)) > 0.01) {
        die("ERROR: El asiento contable está desbalanceado o no coincide con el importe.\n");
    }
}

// 5. Probar listado
echo "5. Verificando listado de transferencias...\n";
$listado = obtenerTransferenciasListado($conexion, $empresa_id);
echo "   Registros encontrados: " . count($listado['data']) . "\n";

// 6. Anular la transferencia y verificar reversión
echo "6. Anulando la transferencia comprobante ID $comp_id...\n";
$res_anul = anularTransferenciaInterna($conexion, $comp_id, $empresa_id, $usuario_id);
if (!$res_anul['success']) {
    die("ERROR al anular transferencia: " . $res_anul['error'] . "\n");
}
echo "   Anulación exitosa: " . $res_anul['mensaje'] . "\n";

$saldo_caja_final = floatval($conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 1")->fetch_assoc()['saldo_actual']);
$saldo_banco_final = floatval($conexion->query("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = 3")->fetch_assoc()['saldo_actual']);

echo "   Saldos post-anulación:\n";
echo "   - Caja: $" . number_format($saldo_caja_final, 2) . " (esperado retorno a: $" . number_format($saldo_caja_inicial, 2) . ")\n";
echo "   - Banco: $" . number_format($saldo_banco_final, 2) . " (esperado retorno a: $" . number_format($saldo_banco_inicial, 2) . ")\n";

if (abs($saldo_caja_final - $saldo_caja_inicial) > 0.01) {
    die("ERROR: El saldo de caja no retornó a su estado inicial.\n");
}
if (abs($saldo_banco_final - $saldo_banco_inicial) > 0.01) {
    die("ERROR: El saldo de banco no retornó a su estado inicial.\n");
}

echo "\n>>> TEST FASE 5 COMPLETADO CON 100% DE ÉXITO <<<\n";
