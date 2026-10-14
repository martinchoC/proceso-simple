<?php
declare(strict_types=1);

/**
 * scratch/test_fase2_completo.php
 * Suite de validación integral y smoke test de Fase 2 (DDJJ, Cálculos, Comprobantes, Reversión).
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/accesos_empleadores.php';
require_once __DIR__ . '/../core/empresa_config.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_ddjj_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_importadores.php';

$conexion = $conn;
$empresa_id = 3; // CEC Patagones
$usuario_id = 1; // Superadmin

echo "=== INICIO SMOKE TEST FASE 2 - DECLARACIONES JURADAS Y NÓMINA ===\n\n";

$errores = 0;
$pruebas = 0;

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
// 1. Verificación del Motor de Cálculo de Renglón (§2.2, CCT 130/75)
// ------------------------------------------------------------------------------
echo "1. Motor de Cálculo de Nómina (sindi_calcular_renglon_empleado):\n";

$conceptos = sindi_obtener_conceptos_activos($conexion, $empresa_id);
verificar("Conceptos activos obtenidos para empresa 3", count($conceptos) >= 2);

// Caso A: Empleado no afiliado, jornada completa, remuneración normal
// Categoría 1 (Maestranza A). Período 202610
$calcA = sindi_calcular_renglon_empleado(
    $conexion, $empresa_id, 202610,
    ['categoria_id' => 1, 'rem_1' => 150000.00, 'rem_2' => 20000.00, 'media_jornada' => 0, 'afiliado' => 0],
    $conceptos, 'COMPLETO', 0.50
);

// Art. 100 (Fondo Social 2%) debe aplicar a todos
$cArt100 = $calcA['calculos'][1] ?? null;
verificar("Art. 100 calculado para no afiliado", $cArt100 !== null && $cArt100['importe'] > 0);

// Art. 101 (Cuota Sindical 2%) NO debe aplicar a no afiliados
$cArt101 = $calcA['calculos'][2] ?? null;
verificar("Art. 101 es 0 para no afiliado", $cArt101 !== null && $cArt101['importe'] == 0.0);

// Caso B: Empleado afiliado
$calcB = sindi_calcular_renglon_empleado(
    $conexion, $empresa_id, 202610,
    ['categoria_id' => 1, 'rem_1' => 150000.00, 'rem_2' => 20000.00, 'media_jornada' => 0, 'afiliado' => 1],
    $conceptos, 'COMPLETO', 0.50
);
$cArt101B = $calcB['calculos'][2] ?? null;
verificar("Art. 101 calculado para afiliado", $cArt101B !== null && $cArt101B['importe'] > 0);

// Caso C: Media jornada con piso PROPORCIONAL vs COMPLETO
$calcProp = sindi_calcular_renglon_empleado(
    $conexion, $empresa_id, 202610,
    ['categoria_id' => 1, 'rem_1' => 5000.00, 'rem_2' => 0.00, 'media_jornada' => 1, 'afiliado' => 1],
    $conceptos, 'PROPORCIONAL', 0.50
);
$calcComp = sindi_calcular_renglon_empleado(
    $conexion, $empresa_id, 202610,
    ['categoria_id' => 1, 'rem_1' => 5000.00, 'rem_2' => 0.00, 'media_jornada' => 1, 'afiliado' => 1],
    $conceptos, 'COMPLETO', 0.50
);
verificar("Piso proporcional es exactamente la mitad del completo", abs($calcProp['piso_calculado'] - ($calcComp['piso_calculado'] * 0.50)) < 0.05);

// Caso D: SAC en período de Junio (202606)
$escalaJunio = sindi_obtener_escala_categoria($conexion, $empresa_id, 1, 202606)['escala_total'];
$calcSac = sindi_calcular_renglon_empleado(
    $conexion, $empresa_id, 202606,
    ['categoria_id' => 1, 'rem_1' => 5000.00, 'rem_2' => 0.00, 'media_jornada' => 0, 'afiliado' => 1],
    $conceptos, 'COMPLETO', 0.50
);
verificar("Período con SAC (mes 6) adiciona 50% al piso de escala", $escalaJunio > 0 && abs($calcSac['piso_calculado'] - ($escalaJunio * 1.50)) < 0.05);

// ------------------------------------------------------------------------------
// 2. Parseadores de Archivo F.931 y CSV (sindi_importadores.php)
// ------------------------------------------------------------------------------
echo "\n2. Parseadores e Importadores de Nómina (sindi_importadores.php):\n";

// Línea simulada F.931 (ancho 420 caracteres)
// Posiciones: 0-11 CUIL (20301234563), 11-41 Nombre, 46-48 Jubilado (02), 61-67 Obra Social, 212 Bruto (0000015000.00), 405 No Rem (0000002000.00)
$cuilTest1 = "20301234563";
$lineaF931 = str_pad($cuilTest1 . "PEREZ JUAN                    ", 46)
           . "02" // Jubilado
           . str_pad("", 13)
           . "303703" // Obra social RNOS
           . str_pad("", 145)
           . "0000015000.00" // Posición 212 (bruto)
           . str_pad("", 180)
           . "0000002000.00" // Posición 405 (no rem)
           . "\n";

$resF931 = sindi_parsear_f931($lineaF931, $conexion, $empresa_id);
verificar("Parseador F.931 procesa fila exitosamente", $resF931['success'] === true && $resF931['aceptadas'] === 1);
$empF931 = $resF931['empleados'][0] ?? [];
verificar("F.931 detecta CUIL correctamente", ($empF931['cuil'] ?? '') === $cuilTest1);
verificar("F.931 calcula rem_1 neta (bruto - no rem)", ($empF931['rem_1'] ?? 0.0) == 13000.00);
verificar("F.931 detecta jubilado", ($empF931['jubilado'] ?? 0) === 1);

// Prueba CSV
$csvData = "CUIL;Nombre;Categoria;Remunerativo;NoRemunerativo;MediaJornada;Afiliado;Jubilado\n"
         . "20301234563;Perez Juan;1;150000,00;20000,00;0;1;0\n";
$resCsv = sindi_parsear_csv($csvData, $conexion, $empresa_id);
verificar("Parseador CSV procesa fila exitosamente", $resCsv['success'] === true && $resCsv['aceptadas'] === 1);
$empCsv = $resCsv['empleados'][0] ?? [];
verificar("CSV detecta afiliado y montos", ($empCsv['afiliado'] ?? 0) === 1 && ($empCsv['rem_1'] ?? 0) == 150000.00);

// ------------------------------------------------------------------------------
// 3. Flujo Completo de Declaración Jurada Original
// ------------------------------------------------------------------------------
echo "\n3. Creación, Guardado de Borrador y Presentación de DDJJ Original:\n";

// Empleador de prueba
$resEmpObj = mysqli_query($conexion, "SELECT empleador_id FROM sindi__empleadores WHERE empresa_id = {$empresa_id} LIMIT 1");
$rowEmpObj = mysqli_fetch_assoc($resEmpObj);
$empleador_id = (int)($rowEmpObj['empleador_id'] ?? 1);

$periodoTest = 202610;

// Limpiar DDJJ previas del período para la prueba
$resPrevDDJJ = mysqli_query($conexion, "SELECT ddjj_id FROM sindi__ddjj WHERE empresa_id = {$empresa_id} AND empleador_id = {$empleador_id} AND periodo = {$periodoTest}");
while ($rPD = mysqli_fetch_assoc($resPrevDDJJ)) {
    $pId = (int)$rPD['ddjj_id'];
    mysqli_query($conexion, "DELETE FROM sindi__ddjj_calculos WHERE ddjj_id = {$pId}");
    mysqli_query($conexion, "DELETE FROM sindi__ddjj_empleados WHERE ddjj_id = {$pId}");
    mysqli_query($conexion, "DELETE FROM sindi__ddjj_conceptos WHERE ddjj_id = {$pId}");
    mysqli_query($conexion, "DELETE FROM sindi__ddjj WHERE ddjj_id = {$pId}");
}

// 3.1 Guardar Borrador
$nominaTest = [
    [
        'cuil' => '20301234563',
        'nombre' => 'Gómez Martín',
        'categoria_id' => 1,
        'rem_1' => 180000.00,
        'rem_2' => 20000.00,
        'media_jornada' => 0,
        'afiliado' => 1,
        'jubilado' => 0
    ],
    [
        'cuil' => '27359876543', // 27-35987654-3
        'nombre' => 'López Mariana',
        'categoria_id' => 2,
        'rem_1' => 190000.00,
        'rem_2' => 10000.00,
        'media_jornada' => 0,
        'afiliado' => 0,
        'jubilado' => 0
    ]
];

$resBorrador = sindi_guardar_borrador_ddjj(
    $conexion, $empresa_id, $usuario_id,
    [
        'empleador_id' => $empleador_id,
        'periodo' => $periodoTest,
        'ddjj_id' => 0,
        'tipo' => 'EMPLEADOR',
        'origen_ingreso' => 'MANUAL'
    ],
    $nominaTest
);

verificar("Borrador guardado exitosamente", ($resBorrador['success'] ?? false) === true);
$ddjj_id_orig = (int)($resBorrador['ddjj_id'] ?? 0);
verificar("ID de DDJJ generado", $ddjj_id_orig > 0);
verificar("Total liquidado mayor a cero", ($resBorrador['total'] ?? 0.0) > 0);

// Verificar registro en sindi__ddjj
$ddjjCargada = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_orig);
verificar("DDJJ cargada en estado Borrador (3)", (int)($ddjjCargada['tabla_estado_registro_id'] ?? 0) === 3);
verificar("Cantidad de empleados es 2", count($ddjjCargada['nomina'] ?? []) === 2);
verificar("Conceptos consolidados presentes", count($ddjjCargada['conceptos'] ?? []) >= 2);

// 3.2 Confirmar / Presentar DDJJ
$resConfirma = sindi_confirmar_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_orig);
verificar("Confirmación de DDJJ exitosa", ($resConfirma['success'] ?? false) === true);
verificar("Comprobantes generados en confirmación", count($resConfirma['comprobantes'] ?? []) >= 2);
verificar("Código de verificación generado", !empty($resConfirma['codigo_verificacion']));

// Verificar estado 28 (Presentada)
$ddjjPres = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_orig);
verificar("DDJJ pasa a estado Presentada (28)", (int)($ddjjPres['tabla_estado_registro_id'] ?? 0) === 28);
verificar("Fecha de presentación asignada", !empty($ddjjPres['f_presentacion']));

// Verificar comprobantes en gestion__comprobantes
$compIds = $resConfirma['comprobantes'];
$inComp = implode(',', array_map('intval', $compIds));
$resComps = mysqli_query($conexion, "SELECT * FROM gestion__comprobantes WHERE comprobante_id IN ({$inComp})");
verificar("Comprobantes emitidos en gestion__comprobantes", mysqli_num_rows($resComps) === count($compIds));

// Verificar asientos contables en gestion__cont_asientos
$resAsientos = mysqli_query($conexion, "SELECT * FROM gestion__cont_asientos WHERE comprobante_id IN ({$inComp})");
verificar("Asientos contables automáticos generados", mysqli_num_rows($resAsientos) >= 1);

// Verificar matriz de períodos en sindi__empleadores_periodos
$resMatriz = mysqli_query($conexion, "SELECT * FROM sindi__empleadores_periodos WHERE empresa_id = {$empresa_id} AND empleador_id = {$empleador_id} AND periodo = {$periodoTest}");
$rowMatriz = mysqli_fetch_assoc($resMatriz);
verificar("Matriz empleador×período actualizada a PRESENTADA", ($rowMatriz['situacion'] ?? '') === 'PRESENTADA' && (int)$rowMatriz['ddjj_vigente_id'] === $ddjj_id_orig);

// ------------------------------------------------------------------------------
// 4. Flujo de Rectificativa con Reversión Total (§7.1)
// ------------------------------------------------------------------------------
echo "\n4. Rectificativa y Reversión Total de DDJJ Anterior:\n";

// Iniciar rectificativa
$resRect = sindi_iniciar_rectificativa($conexion, $empresa_id, $usuario_id, $ddjj_id_orig);
verificar("Rectificativa iniciada correctamente", ($resRect['success'] ?? false) === true);
$ddjj_id_rect = (int)($resRect['ddjj_id'] ?? 0);
verificar("Nuevo ID de rectificativa generado", $ddjj_id_rect > 0 && $ddjj_id_rect !== $ddjj_id_orig);

$ddjjRectObj = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_rect);
verificar("Secuencia de rectificativa es 1", (int)($ddjjRectObj['secuencia'] ?? 0) === 1);
verificar("Referencia a DDJJ anterior establecida", (int)($ddjjRectObj['ddjj_rectificada_id'] ?? 0) === $ddjj_id_orig);

// Modificar nómina en la rectificativa (aumentar sueldo del empleado)
$nominaRect = $nominaTest;
$nominaRect[0]['rem_1'] = 220000.00;
$resGuardarRect = sindi_guardar_borrador_ddjj(
    $conexion, $empresa_id, $usuario_id,
    [
        'empleador_id' => $empleador_id,
        'periodo' => $periodoTest,
        'ddjj_id' => $ddjj_id_rect,
        'tipo' => 'EMPLEADOR',
        'origen_ingreso' => 'MANUAL'
    ],
    $nominaRect
);
verificar("Borrador de rectificativa actualizado", ($resGuardarRect['success'] ?? false) === true);

$resConfirmaRect = sindi_confirmar_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_rect);
verificar("Confirmación de rectificativa exitosa", ($resConfirmaRect['success'] ?? false) === true);

// 4.1 Comprobar que la DDJJ original pasó a estado 2 (Rectificada)
$ddjjOrigPost = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_orig);
verificar("DDJJ original pasó a estado Rectificada (2)", (int)($ddjjOrigPost['tabla_estado_registro_id'] ?? 0) === 2);

// 4.2 Comprobar que los comprobantes anteriores quedaron con importe_pendiente = 0
$resCompOrig = mysqli_query($conexion, "SELECT COUNT(*) as c FROM gestion__comprobantes WHERE comprobante_id IN ({$inComp}) AND importe_pendiente = 0");
$cOrig = (int)mysqli_fetch_assoc($resCompOrig)['c'];
verificar("Comprobantes anteriores saldados por reversión", $cOrig === count($compIds));

// 4.3 Comprobar que se emitieron comprobantes de reversión (signo -1)
$resCompRev = mysqli_query($conexion, "SELECT * FROM gestion__comprobantes WHERE empresa_id = {$empresa_id} AND signo = -1 AND observaciones LIKE '%Reversión%'");
verificar("Comprobantes de reversión (signo -1) generados", mysqli_num_rows($resCompRev) >= 2);

// 4.4 Comprobar que la matriz de períodos apunta ahora a la rectificativa
$resMatrizPost = mysqli_query($conexion, "SELECT ddjj_vigente_id FROM sindi__empleadores_periodos WHERE empresa_id = {$empresa_id} AND empleador_id = {$empleador_id} AND periodo = {$periodoTest}");
$matrizPost = mysqli_fetch_assoc($resMatrizPost);
verificar("Matriz de períodos apunta ahora a la DDJJ rectificativa", (int)$matrizPost['ddjj_vigente_id'] === $ddjj_id_rect);

// ------------------------------------------------------------------------------
// 5. Verificación de Seguridad y Aislamiento Multi-empleador
// ------------------------------------------------------------------------------
echo "\n5. Seguridad y Aislamiento en Consultas de DDJJ:\n";
// Usuario ficticio sin acceso
$listaVacia = sindi_listar_ddjj($conexion, $empresa_id, 99999, 1020);
verificar("Usuario sin permisos no puede listar DDJJ de otros empleadores", count($listaVacia) === 0);

echo "\n============================================\n";
echo "RESULTADOS SMOKE TEST FASE 2:\n";
echo "Pruebas ejecutadas: {$pruebas}\n";
echo "Errores detectados: {$errores}\n";
echo "============================================\n";

if ($errores === 0) {
    echo "\n>>> ¡TODAS LAS PRUEBAS DE FASE 2 PASARON CON ÉXITO! <<<\n";
    exit(0);
} else {
    echo "\n>>> SE ENCONTRARON ERRORES EN LA EJECUCIÓN <<<\n";
    exit(1);
}
