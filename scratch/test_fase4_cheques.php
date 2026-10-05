<?php
/**
 * scratch/test_fase4_cheques.php
 * Test automatizado del ciclo de vida de cheques y conciliación bancaria
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion/cheques_cartera_model.php';

$empresa_id = 2;
$sucursal_id = 2;
$entidad_id = 3;
$usuario_id = 1;
$comp_origen_id = 1;
$conexion = $conn;

echo "=== INICIANDO TEST FASE 4: CARTERA DE CHEQUES Y CONCILIACIÓN ===\n";

// 1. Obtener resumen de cartera actual
$resumen = obtenerResumenCartera($conexion, $empresa_id);
echo "1. Resumen inicial:\n";
print_r($resumen);

// Buscar cuenta de valores en cartera
$res_v = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'VALORES' LIMIT 1");
$cta_valores_id = intval($res_v->fetch_assoc()['tesoreria_cuenta_id'] ?? 6);

// Buscar cuenta bancaria propia
$cta_banco = $conexion->query("SELECT tesoreria_cuenta_id, tesoreria_cuenta, banco_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'BANCO' LIMIT 1")->fetch_assoc();
if (!$cta_banco) {
    die("ERROR: No se encontró cuenta bancaria para la empresa $empresa_id\n");
}
$banco_cuenta_id = intval($cta_banco['tesoreria_cuenta_id']);
$banco_id = intval($cta_banco['banco_id'] ?? 1);

// 2. Crear un cheque de tercero de prueba en cartera por $2.500 (usando saldo de $6000 existente en Valores en Cartera)
$num_chq_tercero = (string)rand(10000000, 99999999);
$sql_chq3 = "INSERT INTO gestion__cheques (
                empresa_id, sucursal_id, cheque_tipo, es_echeq,
                banco_id, tesoreria_cuenta_id, numero, f_emision,
                f_pago, moneda_id, importe,
                librador_nombre, librador_cuit, entidad_id, endosable,
                comp_origen_id, tabla_estado_registro_id, usuario_id
            ) VALUES (?, ?, 'TERCERO', 0, ?, ?, ?, ?, ?, 1, 2500.00, 'Juan Perez Test', 20301234567, ?, 1, ?, 2172, ?)";
$stmt = mysqli_prepare($conexion, $sql_chq3);
$f_hoy = date('Y-m-d');
$f_vto = date('Y-m-d', strtotime('+15 days'));
mysqli_stmt_bind_param($stmt, "iiiisssiii", $empresa_id, $sucursal_id, $banco_id, $cta_valores_id, $num_chq_tercero, $f_hoy, $f_vto, $entidad_id, $comp_origen_id, $usuario_id);
mysqli_stmt_execute($stmt);
$chq_tercero_id = mysqli_insert_id($conexion);
mysqli_stmt_close($stmt);

echo "2. Cheque tercero creado. ID: $chq_tercero_id, N°: $num_chq_tercero, Monto: $2.500,00\n";

// Verificar estado inicial
$r = $conexion->query("SELECT tabla_estado_registro_id FROM gestion__cheques WHERE cheque_id = $chq_tercero_id")->fetch_assoc();
echo "   Estado inicial cheque tercero: " . $r['tabla_estado_registro_id'] . " (esperado 2172 = EN_CARTERA)\n";
if (intval($r['tabla_estado_registro_id']) !== ESTADO_CHQ_EN_CARTERA) {
    die("ERROR: El cheque de tercero no está en estado EN_CARTERA\n");
}

// 3. Depositar cheque de tercero en la cuenta bancaria de la empresa
echo "3. Depositando cheque de tercero en cuenta: " . $cta_banco['tesoreria_cuenta'] . " (ID: $banco_cuenta_id)...\n";
$res_dep = depositarChequeTercero($conexion, $chq_tercero_id, $banco_cuenta_id, $f_hoy, $empresa_id, $usuario_id);
echo "   Depósito exitoso: " . ($res_dep['message'] ?? $res_dep['mensaje'] ?? 'OK') . "\n";

$r = $conexion->query("SELECT tabla_estado_registro_id, tesoreria_cuenta_id FROM gestion__cheques WHERE cheque_id = $chq_tercero_id")->fetch_assoc();
echo "   Estado post-depósito: " . $r['tabla_estado_registro_id'] . " (esperado 2174 = DEPOSITADO), cuenta asignada: " . $r['tesoreria_cuenta_id'] . "\n";
if (intval($r['tabla_estado_registro_id']) !== ESTADO_CHQ_DEPOSITADO) {
    die("ERROR: El cheque no quedó en estado DEPOSITADO\n");
}

// 4. Crear un cheque propio diferido emitido por $1.200 (estado 2173 = EMITIDO)
$num_chq_propio = (string)rand(10000000, 99999999);
$sql_chqp = "INSERT INTO gestion__cheques (
                empresa_id, sucursal_id, cheque_tipo, es_echeq,
                banco_id, tesoreria_cuenta_id, numero, f_emision,
                f_pago, moneda_id, importe,
                entidad_id, endosable, comp_origen_id,
                tabla_estado_registro_id, usuario_id
            ) VALUES (?, ?, 'PROPIO', 0, ?, ?, ?, ?, ?, 1, 1200.00, ?, 1, ?, 2173, ?)";
$stmt_p = mysqli_prepare($conexion, $sql_chqp);
$f_vto_p = date('Y-m-d', strtotime('+30 days'));
mysqli_stmt_bind_param($stmt_p, "iiiisssiii", $empresa_id, $sucursal_id, $banco_id, $banco_cuenta_id, $num_chq_propio, $f_hoy, $f_vto_p, $entidad_id, $comp_origen_id, $usuario_id);
mysqli_stmt_execute($stmt_p);
$chq_propio_id = mysqli_insert_id($conexion);
mysqli_stmt_close($stmt_p);

echo "4. Cheque propio creado. ID: $chq_propio_id, N°: $num_chq_propio, Monto: $1.200,00\n";

$r = $conexion->query("SELECT tabla_estado_registro_id FROM gestion__cheques WHERE cheque_id = $chq_propio_id")->fetch_assoc();
echo "   Estado inicial cheque propio: " . $r['tabla_estado_registro_id'] . " (esperado 2173 = EMITIDO)\n";
if (intval($r['tabla_estado_registro_id']) !== ESTADO_CHQ_EMITIDO) {
    die("ERROR: El cheque propio no está en estado EMITIDO\n");
}

// 5. Registrar Débito Bancario del cheque propio
echo "5. Debitando cheque propio en banco...\n";
$res_deb = debitarChequePropio($conexion, $chq_propio_id, $f_hoy, $empresa_id, $usuario_id);
echo "   Débito exitoso: " . ($res_deb['message'] ?? $res_deb['mensaje'] ?? 'OK') . "\n";

$r = $conexion->query("SELECT tabla_estado_registro_id FROM gestion__cheques WHERE cheque_id = $chq_propio_id")->fetch_assoc();
echo "   Estado post-débito: " . $r['tabla_estado_registro_id'] . " (esperado 2176 = DEBITADO)\n";
if (intval($r['tabla_estado_registro_id']) !== ESTADO_CHQ_DEBITADO) {
    die("ERROR: El cheque propio no quedó en estado DEBITADO\n");
}

// 6. Probar Rechazo de un cheque (creamos otro de tercero para rechazar)
$num_chq_rechazo = (string)rand(10000000, 99999999);
$sql_chqr = "INSERT INTO gestion__cheques (
                empresa_id, sucursal_id, cheque_tipo, es_echeq,
                banco_id, tesoreria_cuenta_id, numero, f_emision,
                f_pago, moneda_id, importe,
                librador_nombre, librador_cuit, entidad_id, endosable,
                comp_origen_id, tabla_estado_registro_id, usuario_id
            ) VALUES (?, ?, 'TERCERO', 0, ?, ?, ?, ?, ?, 1, 500.00, 'Cliente Moroso', 20112233445, ?, 1, ?, 2172, ?)";
$stmt_r = mysqli_prepare($conexion, $sql_chqr);
mysqli_stmt_bind_param($stmt_r, "iiiisssiii", $empresa_id, $sucursal_id, $banco_id, $cta_valores_id, $num_chq_rechazo, $f_hoy, $f_hoy, $entidad_id, $comp_origen_id, $usuario_id);
mysqli_stmt_execute($stmt_r);
$chq_rechazo_id = mysqli_insert_id($conexion);
mysqli_stmt_close($stmt_r);

echo "6. Cheque para rechazo creado. ID: $chq_rechazo_id. Procediendo a rechazar...\n";
$res_rech = rechazarCheque($conexion, $chq_rechazo_id, "Sin fondos suficientes en cuenta libradora", $f_hoy, $empresa_id, $usuario_id);
echo "   Rechazo exitoso: " . ($res_rech['message'] ?? $res_rech['mensaje'] ?? 'OK') . "\n";

$r = $conexion->query("SELECT tabla_estado_registro_id, motivo_rechazo FROM gestion__cheques WHERE cheque_id = $chq_rechazo_id")->fetch_assoc();
echo "   Estado post-rechazo: " . $r['tabla_estado_registro_id'] . " (esperado 2178 = RECHAZADO)\n";
echo "   Motivo rechazo: " . $r['motivo_rechazo'] . "\n";
if (intval($r['tabla_estado_registro_id']) !== ESTADO_CHQ_RECHAZADO) {
    die("ERROR: El cheque no quedó en estado RECHAZADO\n");
}

// 7. Probar listado con filtros
$listado = obtenerChequesListado($conexion, $empresa_id, ['tipo' => 'TERCERO']);
echo "7. Listado cheques de terceros obtenido: " . count($listado['data']) . " registros encontrados.\n";

$resumen_final = obtenerResumenCartera($conexion, $empresa_id);
echo "8. Resumen final de cartera:\n";
print_r($resumen_final);

echo "\n>>> TEST FASE 4 COMPLETADO CON 100% DE ÉXITO <<<\n";
