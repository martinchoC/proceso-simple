<?php
/**
 * test_pagos_e2e.php
 *
 * Prueba End-to-End de Órdenes de Pago (OP):
 * Borrador -> Confirmación Atómica -> Asiento D27/D36 + Movimientos Tesorería + Cheques -> Anulación
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion/pagos_model.php';

echo "=== INICIO TEST END-TO-END DE ORDENES DE PAGO OP ===\n\n";

$empresa_id = 2; // Casa Lucho
$usuario_id = 1;

// 1. Obtener proveedor real
$res_prov = $conn->query("SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE empresa_id = $empresa_id AND es_proveedor = 1 LIMIT 1");
$prov = $res_prov->fetch_assoc();
if (!$prov) {
    die("ERROR: No se encontró ningún proveedor para empresa $empresa_id\n");
}
echo "1. Proveedor seleccionado: ID {$prov['entidad_id']} - {$prov['entidad_nombre']} (CUIT: {$prov['cuit']})\n";

$nro_chq_test = substr(strval(time()), -8);

// Limpiar pruebas previas interrumpidas
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("DELETE FROM gestion__cheques WHERE numero = '10020030'");
$conn->query("DELETE FROM gestion__pagos WHERE observaciones LIKE 'Test E2E Automatizado Fase 3%'");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// 2. Armar payload de pago con 2 medios: Transferencia y Cheque Propio Diferido
$payload = [
    'empresa_id'     => $empresa_id,
    'sucursal_id'    => 1, // Bahia Blanca
    'entidad_id'     => intval($prov['entidad_id']),
    'f_emision'      => date('Y-m-d'),
    'f_contabilidad' => date('Y-m-d'),
    'observaciones'  => 'Test E2E Automatizado Fase 3 Tesorería (OP)',
    'usuario_id'     => $usuario_id,
    'medios'         => [
        [
            'medio_pago_id'       => 4, // Transferencia
            'importe'             => 4000.00,
            'importe_comprobante' => 4000.00,
            'tesoreria_cuenta_id' => 3, // Banco Nacion CC $
            'numero_operacion'    => 'TRF-PROV-7711'
        ],
        [
            'medio_pago_id'       => 3, // Cheque Propio
            'importe'             => 6000.00,
            'importe_comprobante' => 6000.00,
            'tesoreria_cuenta_id' => 3, // Banco Nacion
            'cheque_propio'       => [
                'tesoreria_cuenta_id' => 3,
                'banco_id'            => 2, // Banco Nacion
                'numero'              => $nro_chq_test,
                'f_pago'              => date('Y-m-d', strtotime('+45 days')) // Diferido
            ]
        ]
    ],
    'imputaciones'   => [] // Pago a cuenta / anticipo para no alterar facturas
];

echo "2. Guardando borrador de Orden de Pago (Chq N° $nro_chq_test)...\n";
try {
    $pago_id = guardarPagoBorrador($conn, $payload);
    echo "   -> Pago ID creado: $pago_id\n";
} catch (Throwable $e) {
    echo "ERROR en guardar borrador de pago: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Verificar en BD
$res_p = $conn->query("SELECT pago_id, comprobante_id, comprobante_pv, comprobante_nro, importe_total, tabla_estado_registro_id FROM gestion__pagos WHERE pago_id = $pago_id");
$p = $res_p->fetch_assoc();
echo "   -> Estado pago: {$p['tabla_estado_registro_id']} (Esperado 2125 BORRADOR)\n";
echo "   -> Comprobante ID asociado: {$p['comprobante_id']}\n";
echo "   -> Total pagado: \${$p['importe_total']} (Esperado 10000.00)\n";

if (intval($p['tabla_estado_registro_id']) !== ESTADO_PAGO_BORRADOR || floatval($p['importe_total']) != 10000.00) {
    die("ERROR en guardado de borrador!\n");
}

// 3. Confirmar la Orden de Pago (§2.5)
echo "\n3. Ejecutando confirmación atómica de OP (§2.5 / RT01-RT21)...\n";
try {
    $res_conf = confirmarPago($conn, $pago_id, $empresa_id, $usuario_id);
    echo "   -> Resultado: {$res_conf['message']}\n";
    echo "   -> Número de comprobante asignado: {$res_conf['comprobante_nro']}\n";
} catch (Throwable $e) {
    echo "ERROR en confirmación de pago: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Verificar estado confirmado
$res_p2 = $conn->query("SELECT p.*, comp.importe_pendiente AS saldo_anticipo 
                        FROM gestion__pagos p 
                        JOIN gestion__comprobantes comp ON comp.comprobante_id = p.comprobante_id 
                        WHERE p.pago_id = $pago_id");
$p2 = $res_p2->fetch_assoc();
echo "   -> Nuevo estado pago: {$p2['tabla_estado_registro_id']} (Esperado 2126 CONFIRMADO)\n";
echo "   -> OP oficial: PV {$p2['comprobante_pv']} - N° {$p2['comprobante_nro']}\n";
echo "   -> Saldo anticipo registrado: \${$p2['saldo_anticipo']} (Regla D6 / RT02)\n";

if (intval($p2['tabla_estado_registro_id']) !== ESTADO_PAGO_CONFIRMADO || intval($p2['comprobante_nro']) <= 0) {
    die("ERROR en confirmación de OP!\n");
}

// 4. Verificar movimiento de egreso en libro de tesorería (D4/D15)
$comp_id = intval($p2['comprobante_id']);
$res_movs = $conn->query("SELECT tesoreria_movimiento_id, tesoreria_cuenta_id, tipo_movimiento, importe, concepto 
                          FROM gestion__tesoreria_movimientos 
                          WHERE comprobante_id = $comp_id");
echo "\n4. Verificando movimientos en libro de tesorería:\n";
$count_movs = 0;
while ($m = $res_movs->fetch_assoc()) {
    $count_movs++;
    echo "   -> Mov ID {$m['tesoreria_movimiento_id']} | Cuenta {$m['tesoreria_cuenta_id']} | Tipo {$m['tipo_movimiento']} | Importe \${$m['importe']} | {$m['concepto']}\n";
}
echo "   -> Total movimientos registrados: $count_movs (Esperado 1: Egreso de Banco por Transferencia, el diferido no impacta de inmediato conforme D28/C2)\n";

// 5. Verificar cheque propio emitido
$res_ch = $conn->query("SELECT cheque_id, cheque_tipo, numero, importe, es_diferido, tabla_estado_registro_id 
                        FROM gestion__cheques 
                        WHERE comp_origen_id = $comp_id");
$ch = $res_ch->fetch_assoc();
echo "\n5. Verificando cheque propio emitido:\n";
echo "   -> Cheque ID {$ch['cheque_id']} | Tipo {$ch['cheque_tipo']} | N° {$ch['numero']} | \${$ch['importe']} | Diferido: {$ch['es_diferido']} | Estado {$ch['tabla_estado_registro_id']} (Esperado 2173 EMITIDO)\n";
if (!$ch || intval($ch['tabla_estado_registro_id']) !== 2173 || intval($ch['es_diferido']) !== 1) {
    die("ERROR en cheque propio emitido!\n");
}

// 6. Verificar asiento contable balanceado (D27/D36 / Anexo D)
echo "\n6. Verificando asiento contable generado:\n";
$res_as = $conn->query("SELECT cont_asiento_id, descripcion, f_asiento 
                        FROM gestion__cont_asientos 
                        WHERE comprobante_id = $comp_id");
$as = $res_as->fetch_assoc();
if (!$as) {
    die("ERROR: No se encontró asiento contable para comprobante_id = $comp_id\n");
}
echo "   -> Asiento ID {$as['cont_asiento_id']} | '{$as['descripcion']}' | Fecha: {$as['f_asiento']}\n";

$res_lin = $conn->query("SELECT d.*, c.codigo, c.nombre 
                         FROM gestion__cont_asientos_detalles d 
                         JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = d.cuenta_id 
                         WHERE d.cont_asiento_id = {$as['cont_asiento_id']}");
$total_debe = 0.0;
$total_haber = 0.0;
while ($l = $res_lin->fetch_assoc()) {
    $imp = floatval($l['importe_local']);
    $debe = ($imp > 0) ? $imp : 0.0;
    $haber = ($imp < 0) ? abs($imp) : 0.0;
    $total_debe += $debe;
    $total_haber += $haber;
    echo "      * {$l['codigo']} - {$l['nombre']} | Debe: \$$debe | Haber: \$$haber | {$l['descripcion']}\n";
}

echo "   -> Total Debe: \$$total_debe | Total Haber: \$$total_haber\n";
if (abs($total_debe - $total_haber) > 0.01 || abs($total_debe - 10000.00) > 0.01) {
    die("ERROR en asiento contable desbalanceado!\n");
}

// 7. Probar Anulación (§2.6 / RT08)
echo "\n7. Probando anulación de OP con motivo obligatorio (§2.6)...\n";
$res_anul = anularPago($conn, $pago_id, $empresa_id, $usuario_id, "Test de anulación OP automático");
echo "   -> Resultado anulación: {$res_anul['message']}\n";

$res_check_anul = $conn->query("SELECT tabla_estado_registro_id FROM gestion__pagos WHERE pago_id = $pago_id");
$est_anul = intval($res_check_anul->fetch_assoc()['tabla_estado_registro_id']);
echo "   -> Estado pago post-anulación: $est_anul (Esperado 2127 ANULADO)\n";

$res_ch_anul = $conn->query("SELECT tabla_estado_registro_id FROM gestion__cheques WHERE cheque_id = {$ch['cheque_id']}");
$est_ch_anul = intval($res_ch_anul->fetch_assoc()['tabla_estado_registro_id']);
echo "   -> Estado cheque propio post-anulación: $est_ch_anul (Esperado 2179 ANULADO)\n";

$res_rev = $conn->query("SELECT COUNT(*) AS total_rev FROM gestion__tesoreria_movimientos WHERE comprobante_id = $comp_id AND reversa_de_movimiento_id IS NOT NULL");
$total_rev = intval($res_rev->fetch_assoc()['total_rev']);
echo "   -> Movimientos de reversa generados: $total_rev (Esperado 1 contra-movimiento de ingreso)\n";

if ($est_anul !== ESTADO_PAGO_ANULADO || $total_rev !== 1 || $est_ch_anul !== 2179) {
    die("ERROR en anulación de OP!\n");
}

// 8. Limpiar datos del test
echo "\n8. Limpiando registros de prueba del test...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = {$as['cont_asiento_id']}");
$conn->query("DELETE FROM gestion__cont_asientos WHERE cont_asiento_id = {$as['cont_asiento_id']}");
$conn->query("DELETE FROM gestion__tesoreria_movimientos WHERE comprobante_id = $comp_id");
$conn->query("DELETE FROM gestion__cheques WHERE comp_origen_id = $comp_id");
$conn->query("DELETE FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = $comp_id");
$conn->query("DELETE FROM gestion__pagos_medios WHERE pago_id = $pago_id");
$conn->query("DELETE FROM gestion__comprobantes WHERE comprobante_id = $comp_id");
$conn->query("DELETE FROM gestion__pagos WHERE pago_id = $pago_id");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "   -> Registros de prueba eliminados exitosamente.\n";

echo "\n=== TODOS LOS TESTS END-TO-END DE ORDENES DE PAGO PASARON CON ÉXITO (100%) ===\n";
