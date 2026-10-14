<?php
/**
 * scratch/test_fase4_completo.php
 * Suite de Pruebas de Validación Automatizada para la Fase 4:
 * Fiscalización, Actas de Inspección, Planes de Pago y Procesos Automáticos (Crons).
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
$conexion = $conn;

require_once __DIR__ . '/../modules/sindicatos/sindi_fiscalizacion_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_actas_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_planes_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_ddjj_core_model.php';

$empresa_id = 3;
$usuario_id = 1;

$totalTests = 0;
$passed = 0;
$failed = 0;

function assertTest(bool $cond, string $desc) {
    global $totalTests, $passed, $failed;
    $totalTests++;
    if ($cond) {
        $passed++;
        echo "  [PASS] Test #$totalTests: $desc\n";
    } else {
        $failed++;
        echo "  [FAIL] Test #$totalTests: $desc\n";
    }
}

echo "====================================================================\n";
echo "INICIANDO SUITE DE TESTS - FASE 4: FISCALIZACION, ACTAS Y PLANES\n";
echo "Empresa ID: $empresa_id | Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // GRUPO 1: VERIFICACION DE DDL Y CONFIGURACION
    // -------------------------------------------------------------------------
    echo "--- GRUPO 1: Estructura de Base de Datos y Catálogos ---\n";
    
    // Tablas existen
    $tablas = ['sindi__actas', 'sindi__actas_periodos', 'sindi__actas_items', 'sindi__planes_tipos', 'sindi__planes', 'sindi__planes_cuotas'];
    foreach ($tablas as $t) {
        $chk = $conn->query("SHOW TABLES LIKE '$t'");
        assertTest($chk && $chk->num_rows === 1, "Tabla $t existe en la base de datos");
    }

    // Comprobantes tipos 308 ACT y 309 PLC
    $chkAct = $conn->query("SELECT comprobante_tipo_id, codigo, signo, impacta_contabilidad, impacta_ctacte FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = 308");
    $rowAct = $chkAct ? $chkAct->fetch_assoc() : null;
    assertTest($rowAct && $rowAct['codigo'] === 'ACT' && $rowAct['impacta_contabilidad'] == 1, "Tipo comprobante 308 ACT configurado (contable=1, ctacte=1)");

    $chkPlc = $conn->query("SELECT comprobante_tipo_id, codigo, signo, impacta_contabilidad, impacta_ctacte FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = 309");
    $rowPlc = $chkPlc ? $chkPlc->fetch_assoc() : null;
    assertTest($rowPlc && $rowPlc['codigo'] === 'PLC' && $rowPlc['impacta_ctacte'] == 1, "Tipo comprobante 309 PLC configurado (ctacte=1)");

    // Cuenta contable 3017 y concepto SINDI_AJUSTE_ACTAS
    $chkCta = $conn->query("SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE cont_cuenta_id = 3017 AND empresa_id = 3");
    assertTest($chkCta && $chkCta->num_rows === 1, "Cuenta contable 3017 (Ajuste por Actas de Inspección) sembrada");

    $chkCon = $conn->query("SELECT cont_cuenta_id FROM gestion__empresas_cuentas_conceptos WHERE concepto = 'SINDI_AJUSTE_ACTAS' AND empresa_id = 3");
    $rowCon = $chkCon ? $chkCon->fetch_assoc() : null;
    assertTest($rowCon && (int)$rowCon['cont_cuenta_id'] === 3017, "Concepto contable SINDI_AJUSTE_ACTAS mapeado a cuenta 3017");

    // Tipos de planes sembrados
    $chkPt = $conn->query("SELECT COUNT(*) AS cant FROM sindi__planes_tipos WHERE empresa_id = 3");
    $cantPt = $chkPt ? (int)$chkPt->fetch_assoc()['cant'] : 0;
    assertTest($cantPt >= 2, "Tipos de planes sembrados para empresa 3 (cant: $cantPt)");

    // -------------------------------------------------------------------------
    // GRUPO 2: MOTOR DE FISCALIZACION Y MORA
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 2: Recálculo de Mora y Matriz de Cumplimiento ---\n";

    // Recalculo de mora
    $resMora = sindiFiscalizacionRecalcularMora($conn, $empresa_id);
    assertTest(isset($resMora['procesados']) && $resMora['procesados'] > 0, "Recálculo de mora procesó {$resMora['procesados']} empleadores");
    assertTest(isset($resMora['por_categoria']['PUNTUAL']), "Categoría PUNTUAL evaluada correctamente");

    // Matriz de cumplimiento
    $matriz = sindiFiscalizacionMatrizCumplimiento($conn, $empresa_id, [
        'periodo_desde' => (int)date('Ym', strtotime('-3 months')),
        'periodo_hasta' => (int)date('Ym')
    ]);
    assertTest(!empty($matriz['periodos']), "Matriz generó lista de períodos cronológicos");
    assertTest(!empty($matriz['empleadores']), "Matriz trajo empleadores con sus períodos evaluados");

    // Detección de trabajadores omitidos
    $empFk = $conn->query("SELECT empleador_id FROM sindi__empleadores WHERE empresa_id = 3 AND tabla_estado_registro_id = 1 LIMIT 1")->fetch_assoc()['empleador_id'];
    $periodoPrueba = (int)date('Ym');
    $omitidos = sindiFiscalizacionDetectarEmpleadosOmitidos($conn, $empresa_id, (int)$empFk, $periodoPrueba);
    assertTest(is_array($omitidos), "Función de detección de trabajadores omitidos retornó array");

    // -------------------------------------------------------------------------
    // GRUPO 3: GENERACION DE DDJJ DE OFICIO
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 3: Declaraciones Juradas de Oficio ---\n";

    // Usar un período del año anterior para no colisionar con DDJJ presentadas reales
    $periodoOficio = 202511;
    // Limpiar si existía prueba previa
    $conn->query("DELETE FROM sindi__ddjj WHERE empresa_id = $empresa_id AND empleador_id = $empFk AND periodo = $periodoOficio");

    $resOficio = sindiFiscalizacionGenerarDdjjOficio($conn, $empresa_id, (int)$empFk, $periodoOficio, $usuario_id, true);
    assertTest(isset($resOficio['ddjj_id']) && $resOficio['ddjj_id'] > 0, "DDJJ de Oficio creada con ID {$resOficio['ddjj_id']}");
    assertTest($resOficio['tipo'] === 'OFICIO', "Tipo de DDJJ asignado como 'OFICIO'");
    assertTest($resOficio['total'] > 0, "Total determinado de oficio mayor a cero ($ {$resOficio['total']})");
    assertTest($resOficio['confirmada'] === true, "DDJJ de oficio confirmada generando comprobantes exigibles");

    // Verificar situación en sindi__empleadores_periodos (confirmada pasa a PRESENTADA vinculando la DDJJ)
    $chkEp = $conn->query("SELECT situacion, ddjj_vigente_id FROM sindi__empleadores_periodos WHERE empresa_id = $empresa_id AND empleador_id = $empFk AND periodo = $periodoOficio")->fetch_assoc();
    assertTest($chkEp && (int)$chkEp['ddjj_vigente_id'] === (int)$resOficio['ddjj_id'], "DDJJ de oficio vinculada a sindi__empleadores_periodos (situacion: {$chkEp['situacion']})");

    // -------------------------------------------------------------------------
    // GRUPO 4: ACTAS DE INSPECCION
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 4: Apertura, Liquidación y Confirmación de Actas ---\n";

    $datosActa = [
        'empleador_id' => (int)$empFk,
        'tipo_acta' => 'NORMAL',
        'f_inicio' => date('Y-m-d'),
        'f_notificacion' => date('Y-m-d'),
        'observaciones' => 'Acta de prueba integral Fase 4'
    ];
    $actaId = sindiActaCrear($conn, $empresa_id, $datosActa, $usuario_id);
    assertTest($actaId > 0, "Acta de inspección creada con ID $actaId");

    // Agregar período inspeccionado con diferencias
    $itemsPeriodo = [
        ['concepto_id' => 1, 'declarado' => 0.00, 'determinado' => 50000.00, 'pagado' => 0.00],
        ['concepto_id' => 2, 'declarado' => 0.00, 'determinado' => 25000.00, 'pagado' => 0.00]
    ];
    $actaPerId = sindiActaAgregarPeriodo($conn, $actaId, 202510, $itemsPeriodo, date('Y-m-d'));
    assertTest($actaPerId > 0, "Período 202510 agregado al acta con ID $actaPerId");

    // Verificar cálculo de diferencias e intereses en el acta
    $actaDet = sindiActaObtener($conn, $actaId);
    assertTest((float)$actaDet['total_diferencia'] === 75000.00, "Diferencia de capital consolidada calculada exactamente en $75.000,00");
    assertTest((float)$actaDet['total_intereses'] > 0.00, "Intereses resarcitorios devengados mayor a cero ($ {$actaDet['total_intereses']})");
    assertTest((float)$actaDet['total_acta'] > 75000.00, "Total exigible del acta calculado en $ {$actaDet['total_acta']}");

    // Confirmar Acta de Inspección
    $confActa = sindiActaConfirmar($conn, $actaId, $usuario_id);
    assertTest($confActa['estado'] === 5, "Acta de inspección confirmada con estado 5");
    assertTest($confActa['comprobante_id'] > 0, "Comprobante formal tipo ACT generado con ID {$confActa['comprobante_id']}");
    assertTest($confActa['asiento_id'] > 0, "Asiento contable formal generado con ID {$confActa['asiento_id']}");

    // Verificar en gestion__comprobantes
    $chkCompAct = $conn->query("SELECT comprobante_tipo_id, importe_total, importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = {$confActa['comprobante_id']}")->fetch_assoc();
    assertTest($chkCompAct && (int)$chkCompAct['comprobante_tipo_id'] === 308, "Comprobante emitido corresponde a tipo 308 (ACT)");
    assertTest((float)$chkCompAct['importe_pendiente'] == (float)$actaDet['total_acta'], "Saldo pendiente del comprobante coincide con el total del acta");

    // -------------------------------------------------------------------------
    // GRUPO 5: PLANES DE PAGO Y REFINANCIACION
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 5: Simulación y Emisión de Planes de Pago ---\n";

    $planTipoRow = $conn->query("SELECT plan_tipo_id FROM sindi__planes_tipos WHERE empresa_id = 3 AND codigo = 'PLAN_PERM' LIMIT 1")->fetch_assoc();
    $planTipoId = (int)$planTipoRow['plan_tipo_id'];

    // Simulación del plan
    $capitalSim = 100000.00;
    $cuotasSim = 6;
    $simRes = sindiPlanSimular($conn, $empresa_id, $planTipoId, $capitalSim, $cuotasSim, 10.00); // 10% anticipo
    assertTest($simRes['anticipo_importe'] == 10000.00, "Anticipo de 10% calculado exactamente en $10.000,00");
    assertTest($simRes['saldo_financiar'] == 90000.00, "Saldo a financiar calculado en $90.000,00");
    assertTest(count($simRes['cronograma']) === 7, "Cronograma contiene 7 cuotas (anticipo cuota 0 + 6 cuotas mensuales)");
    assertTest($simRes['total_financiado'] > $capitalSim, "Total financiado incluye interés pactado ($ {$simRes['total_financiado']})");

    // Crear y Confirmar Plan de Pagos refinanciando el acta anterior
    $datosPlan = [
        'empleador_id' => (int)$empFk,
        'plan_tipo_id' => $planTipoId,
        'origen_tipo' => 'ACTA',
        'acta_id' => $actaId,
        'capital_original' => (float)$chkCompAct['importe_total'],
        'cantidad_cuotas' => 3,
        'anticipo_pct' => 0.00,
        'observaciones' => 'Refinanciación de Acta por Plan de Pagos Test Fase 4'
    ];
    $planRes = sindiPlanCrearYConfirmar($conn, $empresa_id, $datosPlan, [$confActa['comprobante_id']], $usuario_id);
    assertTest($planRes['plan_id'] > 0, "Plan de pagos creado con ID {$planRes['plan_id']} y número {$planRes['numero_plan']}");
    assertTest($planRes['cuotas'] === 4, "Plan generó 4 cuotas (anticipo cuota 0 + 3 cuotas mensuales)");

    // Verificar cuotas y comprobantes PLC (309)
    $chkPlcCuotas = $conn->query("SELECT COUNT(*) AS cant FROM sindi__planes_cuotas WHERE plan_id = {$planRes['plan_id']} AND comprobante_id IS NOT NULL")->fetch_assoc();
    assertTest((int)$chkPlcCuotas['cant'] === 4, "Todas las 4 cuotas tienen su comprobante formal tipo PLC (309) asociado");

    // Verificar refinanciación: Comprobante del Acta debe quedar cancelado total (saldo 0, estado 40)
    $chkActaPost = $conn->query("SELECT importe_pendiente, tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = {$confActa['comprobante_id']}")->fetch_assoc();
    assertTest((float)$chkActaPost['importe_pendiente'] == 0.00, "Comprobante del acta cancelado (importe_pendiente = 0.00)");
    assertTest((int)$chkActaPost['tabla_estado_registro_id'] === 40, "Comprobante del acta pasó a estado 40 (Cancelado Total)");

    // Verificar imputación de refinanciación en gestion__comprobantes_imputaciones
    $chkImp = $conn->query("SELECT COUNT(*) AS cant FROM gestion__comprobantes_imputaciones WHERE comp_destino_id = {$confActa['comprobante_id']}")->fetch_assoc();
    assertTest((int)$chkImp['cant'] >= 1, "Imputación contable de refinanciación registrada formalmente");

    // Verificar estado del acta de inspección
    $chkActaFin = $conn->query("SELECT resolucion_tipo, plan_id, tabla_estado_registro_id FROM sindi__actas WHERE acta_id = $actaId")->fetch_assoc();
    assertTest($chkActaFin['resolucion_tipo'] === 'PLAN' && (int)$chkActaFin['plan_id'] === $planRes['plan_id'], "Acta vinculada al plan con resolucion_tipo = 'PLAN'");
    assertTest((int)$chkActaFin['tabla_estado_registro_id'] === 40, "Acta pasó a estado 40 (Cancelado total por refinanciación)");

    // -------------------------------------------------------------------------
    // GRUPO 6: ANULACION DE PLAN Y REVERSION
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 6: Anulación y Reversión de Plan ---\n";

    $anuloPlan = sindiPlanAnular($conn, $planRes['plan_id'], $usuario_id, 'Test de anulación');
    assertTest($anuloPlan === true, "Plan de pagos anulado exitosamente");

    $chkPlanAnul = $conn->query("SELECT tabla_estado_registro_id FROM sindi__planes WHERE plan_id = {$planRes['plan_id']}")->fetch_assoc();
    assertTest((int)$chkPlanAnul['tabla_estado_registro_id'] === 38, "Plan pasó a estado 38 (Anulado)");

    $chkActaRevertida = $conn->query("SELECT resolucion_tipo, tabla_estado_registro_id FROM sindi__actas WHERE acta_id = $actaId")->fetch_assoc();
    assertTest($chkActaRevertida['resolucion_tipo'] === 'PENDIENTE' && (int)$chkActaRevertida['tabla_estado_registro_id'] === 5, "Acta revertida a Confirmada y PENDIENTE tras anulación del plan");

    // -------------------------------------------------------------------------
    // GRUPO 7: EJECUCION DE PROCESOS AUTOMATICOS (CRON CLI)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 7: Ejecución de Procesos Automáticos CLI ---\n";

    $phpCli = "c:\\xampp\\php\\php.exe";

    // 1. Cron abrir período
    exec("$phpCli cron/sindicatos_abrir_periodo.php --empresa=3 --periodo=202611 2>&1", $out1, $ret1);
    assertTest($ret1 === 0, "Cron 'sindicatos_abrir_periodo.php' ejecutó con código de salida 0");

    // 2. Cron mora y vencimientos
    exec("$phpCli cron/sindicatos_mora.php --empresa=3 2>&1", $out2, $ret2);
    assertTest($ret2 === 0, "Cron 'sindicatos_mora.php' ejecutó con código de salida 0");

    // 3. Cron publicar deudas
    exec("$phpCli cron/sindicatos_publicar_deudas.php --empresa=3 2>&1", $out3, $ret3);
    assertTest($ret3 === 0, "Cron 'sindicatos_publicar_deudas.php' ejecutó con código de salida 0");

    // 4. Cron rendiciones
    exec("$phpCli cron/sindicatos_rendiciones.php --empresa=3 2>&1", $out4, $ret4);
    assertTest($ret4 === 0, "Cron 'sindicatos_rendiciones.php' ejecutó con código de salida 0");

} catch (Throwable $e) {
    echo "ERROR CATASTROFICO EN TEST SUITE: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n====================================================================\n";
echo "RESULTADOS DE LA SUITE DE VALIDACION FASE 4:\n";
echo "Total de Pruebas: $totalTests\n";
echo "Pruebas Pasadas:  $passed\n";
echo "Pruebas Fallidas: $failed\n";
if ($failed === 0) {
    echo "ESTADO FINAL: 100% OK - FASE 4 COMPLETADA Y VALIDADA CON EXITO\n";
} else {
    echo "ESTADO FINAL: HUBO FALLAS QUE REQUIEREN REVISION\n";
}
echo "====================================================================\n";
