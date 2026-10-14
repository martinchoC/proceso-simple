<?php
declare(strict_types=1);

/**
 * scratch/test_fase3_completo.php
 * Suite de validación integral y smoke test de Fase 3:
 * - Cálculo de intereses resarcitorios a dos fechas por tramos.
 * - Armado y emisión de volantes con código de barras BNA Code128.
 * - Publicación en canales de pago.
 * - Procesamiento de rendiciones, conciliación y cobro.
 * - Emisión de ND de intereses, cobranzas, imputaciones y asientos.
 * - Seguridad y aislamiento multi-empleador.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/accesos_empleadores.php';
require_once __DIR__ . '/../core/empresa_config.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_ddjj_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_intereses_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_volantes_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_pagos_drivers.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_cobros_core_model.php';

$conexion = $conn;
$empresa_id = 3; // CEC Patagones
$usuario_id = 1; // Superadmin

echo "=== INICIO SMOKE TEST FASE 3 - COBRANZAS Y VOLANTES DE PAGO ===\n\n";

$pruebas = 0;
$errores = 0;

function verificar(string $nombre, bool $condicion, string $detalle = ''): void {
    global $pruebas, $errores;
    $pruebas++;
    if ($condicion) {
        echo "  [OK] {$nombre}\n";
    } else {
        $errores++;
        echo "  [FAIL] {$nombre}: {$detalle}\n";
    }
}

// ------------------------------------------------------------------------------
// 1. Motor de Cálculo de Intereses Resarcitorios (§2.3, §7.3.2)
// ------------------------------------------------------------------------------
echo "1. Motor de Cálculo de Intereses Resarcitorios (sindi_intereses_core_model.php):\n";

$tasas = sindi_obtener_tasas_interes($conexion, $empresa_id, 1);
verificar("Tramos de tasa activa obtenidos para empresa 3", count($tasas) >= 30);

// Caso 1.1: Pago antes o el mismo día del vencimiento (Interés = 0)
$calcSinMora = sindi_calcular_interes_deuda(10000.00, '2026-10-15', '2026-10-10', $tasas);
verificar("Pago en término o anticipado da interés 0", $calcSinMora['interes_total'] == 0.00 && empty($calcSinMora['tramos']));

// Caso 1.2: Mora dentro del tramo vigente actual (Julio 2025 - Julio 2030, tasa 2.75% mensual)
// 60 días de atraso sobre $100.000 -> 100000 * 0.0275 * (60/30) = $5.500
$calcMoraSimple = sindi_calcular_interes_deuda(100000.00, '2026-01-01', '2026-03-02', $tasas);
verificar("Mora en tramo simple calcula interés correctamente", $calcMoraSimple['interes_total'] > 5000.00 && count($calcMoraSimple['tramos']) >= 1);

// Caso 1.3: Cálculo a dos vencimientos
$deudasSim = [
    [
        'comprobante_id' => 9999,
        'saldo_deuda' => 50000.00,
        'f_vto' => '2026-08-15',
        'periodo' => 202607,
        'concepto_id' => 1
    ]
];
$volCalc = sindi_calcular_intereses_volante_completo($deudasSim, '2026-10-15', '2026-10-30', $tasas);
verificar("Cálculo a dos vencimientos generado", !empty($volCalc['detalles']));
verificar("Segundo vencimiento tiene mayor interés que el primero", $volCalc['importe_interes_2'] > $volCalc['importe_interes_1']);
verificar("Importe total 2 es mayor a importe total 1", $volCalc['importe_total_2'] > $volCalc['importe_total_1']);

// ------------------------------------------------------------------------------
// 2. Generación de Código de Barras BNA Code128
// ------------------------------------------------------------------------------
echo "\n2. Código de Barras Banco Nación (sindi_volantes_core_model.php):\n";

$cb = sindi_generar_codigo_barras_bna(42, 105, '2026-10-25', 12345.67, '869955555');
verificar("Código de barras generado con longitud estándar (47 caracteres)", strlen($cb) === 47);
verificar("Prefijo BNA correcto en código de barras", substr($cb, 0, 9) === '869955555');
verificar("ID empleador rellenado a 10 ceros", substr($cb, 9, 10) === '0000000042');
verificar("ID volante rellenado a 10 ceros", substr($cb, 19, 10) === '0000000105');
verificar("Fecha codificada en formato YYYYMMDD", substr($cb, 29, 8) === '20261025');
verificar("Importe en centavos rellenado a 10 ceros", substr($cb, 37, 10) === '0001234567');

// ------------------------------------------------------------------------------
// 3. Emisión de Volante de Pago con Deudas Reales de DDJJ
// ------------------------------------------------------------------------------
echo "\n3. Emisión de Volante de Pago con Deudas de DDJJ:\n";

// Obtener empleador de prueba y crearle una DDJJ presentada con deuda
$resEmp = mysqli_query($conexion, "SELECT empleador_id FROM sindi__empleadores WHERE empresa_id = {$empresa_id} LIMIT 1");
$empleador_id = (int)mysqli_fetch_assoc($resEmp)['empleador_id'];

// Período de prueba
$periodoDDJJ = 202604;
$nominaTest = [
    [
        'cuil' => '20301234563',
        'nombre' => 'Perez Roberto',
        'categoria_id' => 1,
        'rem_1' => 200000.00,
        'rem_2' => 0.00,
        'media_jornada' => 0,
        'afiliado' => 1,
        'jubilado' => 0
    ]
];

// Limpiar pruebas anteriores del período
$resPrev = mysqli_query($conexion, "SELECT ddjj_id FROM sindi__ddjj WHERE empresa_id = {$empresa_id} AND empleador_id = {$empleador_id} AND periodo = {$periodoDDJJ}");
while ($rp = mysqli_fetch_assoc($resPrev)) {
    $pId = (int)$rp['ddjj_id'];
    mysqli_query($conexion, "DELETE FROM sindi__ddjj_calculos WHERE ddjj_id = {$pId}");
    mysqli_query($conexion, "DELETE FROM sindi__ddjj_empleados WHERE ddjj_id = {$pId}");
    mysqli_query($conexion, "DELETE FROM sindi__ddjj_conceptos WHERE ddjj_id = {$pId}");
    mysqli_query($conexion, "DELETE FROM sindi__ddjj WHERE ddjj_id = {$pId}");
}

// Guardar y presentar DDJJ para generar comprobantes pendientes
$resBor = sindi_guardar_borrador_ddjj($conexion, $empresa_id, $usuario_id, [
    'empleador_id' => $empleador_id,
    'periodo' => $periodoDDJJ,
    'ddjj_id' => 0,
    'tipo' => 'EMPLEADOR',
    'origen_ingreso' => 'MANUAL'
], $nominaTest);
$ddjj_id_test = (int)($resBor['ddjj_id'] ?? 0);
$resPres = sindi_confirmar_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_test);
verificar("DDJJ confirmada para generar deudas", ($resPres['success'] ?? false) === true);

// Consultar deudas pendientes disponibles para volante
$deudasDisponibles = sindi_obtener_deudas_pendientes_empleador($conexion, $empresa_id, $empleador_id);
verificar("Deudas pendientes encontradas para el empleador", count($deudasDisponibles) >= 2);

$compIdsAIncluir = array_column($deudasDisponibles, 'comprobante_id');

// Emitir volante a dos vencimientos
$f_vto_1 = date('Y-m-d', strtotime('+10 days'));
$f_vto_2 = date('Y-m-d', strtotime('+20 days'));
$resEmitir = sindi_emitir_volante($conexion, $empresa_id, $usuario_id, [
    'empleador_id' => $empleador_id,
    'f_pago_1' => $f_vto_1,
    'f_pago_2' => $f_vto_2,
    'observaciones' => 'Volante test Fase 3'
], $compIdsAIncluir);

verificar("Volante emitido exitosamente", ($resEmitir['success'] ?? false) === true);
$volante_id = (int)($resEmitir['volante_id'] ?? 0);
verificar("ID de volante generado", $volante_id > 0);
verificar("Código de barras asignado al volante", !empty($resEmitir['codigo_barras']));

// Verificar cabecera en sindi__volantes
$volanteObj = sindi_obtener_volante($conexion, $empresa_id, $usuario_id, $volante_id);
verificar("Volante en estado Emitido (31)", (int)($volanteObj['tabla_estado_registro_id'] ?? 0) === 31);
verificar("Detalles de deudas guardados en el volante", count($volanteObj['detalles'] ?? []) >= 2);

// Verificar auditoría de intereses en sindi__volantes_detalles_intereses
$resAud = mysqli_query($conexion, "SELECT COUNT(*) as c FROM sindi__volantes_detalles_intereses WHERE volante_detalle_id IN (SELECT volante_detalle_id FROM sindi__volantes_detalles WHERE volante_id = {$volante_id})");
$cAud = (int)mysqli_fetch_assoc($resAud)['c'];
verificar("Auditoría de tramos de interés guardada", $cAud >= 2);

// Verificar publicación en canales
$resCan = mysqli_query($conexion, "SELECT * FROM sindi__volantes_canales WHERE volante_id = {$volante_id}");
verificar("Volante publicado en canales de pago configurados", mysqli_num_rows($resCan) >= 1);

// ------------------------------------------------------------------------------
// 4. Drivers de Recaudación y Parseo de Rendición
// ------------------------------------------------------------------------------
echo "\n4. Drivers de Recaudación y Parseo de Rendición (sindi_pagos_drivers.php):\n";

$driverBNA = SindiPagosFactory::obtenerDriver('BNA_BARRAS');
$cbVol = $volanteObj['codigo_barras'];
$impCobrado1 = (float)$volanteObj['importe_1'];

// Línea simulada de rendición BNA (delimitada o posicional)
$lineaRendicion = "{$cbVol};{$f_vto_1};" . number_format($impCobrado1, 2, ',', '') . ";SUC_PATAGONES;BNA_VENTANILLA\n";
$resParseBNA = $driverBNA->parsearArchivo($lineaRendicion, []);
verificar("Driver BNA parsea archivo de rendición exitosamente", ($resParseBNA['success'] ?? false) === true && count($resParseBNA['lineas']) === 1);
$linP = $resParseBNA['lineas'][0];
verificar("Línea detecta identificador de código de barras", $linP['identificador_externo'] === $cbVol);
verificar("Línea detecta importe exacto", abs($linP['importe_rendido'] - $impCobrado1) < 0.01);

// ------------------------------------------------------------------------------
// 5. Conciliación y Procesamiento de Cobranza (§7.3.2)
// ------------------------------------------------------------------------------
echo "\n5. Conciliación, Cobranza, ND Intereses e Imputaciones (sindi_cobros_core_model.php):\n";

// Registrar rendición en sindi__rendiciones
$resRegRend = sindi_registrar_rendicion(
    $conexion, $empresa_id, $usuario_id, 1, // Medio 1: BNA
    date('Y-m-d'), 'rendicion_bna_test.txt', $lineaRendicion
);
verificar("Rendición registrada en estado Recibido (8)", ($resRegRend['success'] ?? false) === true);
$rendicion_id = (int)($resRegRend['rendicion_id'] ?? 0);

// Procesar cobranza de la rendición
$resProcCobranza = sindi_procesar_cobranza_rendicion($conexion, $empresa_id, $usuario_id, $rendicion_id);
verificar("Cobranza procesada exitosamente", ($resProcCobranza['success'] ?? false) === true);
verificar("Línea conciliada sin diferencias", ($resProcCobranza['procesadas_ok'] ?? 0) === 1);

// Verificar comprobantes emitidos: Recibo REC (307) y ND Intereses NDI (306)
$resLineasPost = mysqli_query($conexion, "SELECT * FROM sindi__rendiciones_lineas WHERE rendicion_id = {$rendicion_id}");
$linPost = mysqli_fetch_assoc($resLineasPost);
$compCobroId = (int)($linPost['comprobante_cobro_id'] ?? 0);
verificar("Comprobante de cobranza (REC) emitido", $compCobroId > 0);

// Verificar que las deudas originales del volante quedaron con saldo 0
$resDeudasSaldadas = mysqli_query($conexion, "SELECT COUNT(*) as c FROM gestion__comprobantes WHERE comprobante_id IN (" . implode(',', $compIdsAIncluir) . ") AND importe_pendiente = 0");
$cSaldadas = (int)mysqli_fetch_assoc($resDeudasSaldadas)['c'];
verificar("Todas las deudas del volante quedaron saldadas (pendiente = 0)", $cSaldadas === count($compIdsAIncluir));

// Verificar imputaciones en gestion__comprobantes_imputaciones
$resImputaciones = mysqli_query($conexion, "SELECT * FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = {$compCobroId}");
verificar("Imputaciones contables registradas para la cobranza", mysqli_num_rows($resImputaciones) >= count($compIdsAIncluir));

// Verificar asientos contables de la cobranza
$resAsientosCobro = mysqli_query($conexion, "SELECT * FROM gestion__cont_asientos WHERE comprobante_id = {$compCobroId}");
verificar("Asiento contable automático generado para la cobranza", mysqli_num_rows($resAsientosCobro) >= 1);

// Verificar estado final del volante: 40 (Cancelado Total)
$volantePost = sindi_obtener_volante($conexion, $empresa_id, $usuario_id, $volante_id);
verificar("Volante pasa a estado Cancelado Total (40)", (int)($volantePost['tabla_estado_registro_id'] ?? 0) === 40);

// ------------------------------------------------------------------------------
// 6. Seguridad y Aislamiento Multi-empleador en Volantes
// ------------------------------------------------------------------------------
echo "\n6. Seguridad y Aislamiento en Volantes de Pago:\n";

$listaVacia = sindi_listar_volantes($conexion, $empresa_id, 99999, 1030);
verificar("Usuario sin permisos no puede ver volantes de otros empleadores", count($listaVacia) === 0);

echo "\n============================================\n";
echo "RESULTADOS SMOKE TEST FASE 3:\n";
echo "Pruebas ejecutadas: {$pruebas}\n";
echo "Errores detectados: {$errores}\n";
echo "============================================\n";

if ($errores === 0) {
    echo "\n>>> ¡TODAS LAS PRUEBAS DE FASE 3 PASARON CON ÉXITO! <<<\n";
    exit(0);
} else {
    echo "\n>>> SE ENCONTRARON ERRORES EN LA EJECUCIÓN <<<\n";
    exit(1);
}
