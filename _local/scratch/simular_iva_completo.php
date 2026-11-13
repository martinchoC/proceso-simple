<?php
/**
 * Simulación End-to-End: Obligación Repetitiva de Liquidación Mensual de IVA
 * Desde Normativa hasta Evidencias Probatorias
 */

require_once __DIR__ . '/../../db.php';

echo "=== INICIANDO SIMULACION: LIQUIDACION MENSUAL DE IVA ===\n\n";

$empresa_id = 2; // Casa Lucho S.A.
$entidad_id = 12; // Casa Lucho S.A. (Sede Central)

// -----------------------------------------------------------------------------
// 1. ORGANISMO: ARCA / AFIP
// -----------------------------------------------------------------------------
$q_org = mysqli_query($conexion, "SELECT organismo_id FROM ml__organismos WHERE nombre LIKE '%AFIP%' OR nombre LIKE '%ARCA%' LIMIT 1");
if ($r = mysqli_fetch_assoc($q_org)) {
    $organismo_id = (int)$r['organismo_id'];
    echo "1. Organismo encontrado: ID $organismo_id\n";
} else {
    mysqli_query($conexion, "INSERT INTO ml__organismos (nombre, tabla_estado_registro_id, creado_en) VALUES ('ARCA / AFIP - Agencia de Recaudación y Control Aduanero', 1, NOW())");
    $organismo_id = mysqli_insert_id($conexion);
    echo "1. Organismo creado: ARCA / AFIP (ID $organismo_id)\n";
}

// -----------------------------------------------------------------------------
// 2. NORMA: LEY 23.349 / DECRETO 280/97 (IVA)
// -----------------------------------------------------------------------------
$q_norma = mysqli_query($conexion, "SELECT norma_id FROM ml__normas WHERE codigo = 'LEY-23349-IVA' LIMIT 1");
if ($r = mysqli_fetch_assoc($q_norma)) {
    $norma_id = (int)$r['norma_id'];
    echo "2. Norma existente: LEY-23349-IVA (ID $norma_id)\n";
} else {
    $sql_norma = "INSERT INTO ml__normas 
        (norma_tipo_id, norma_jerarquia_id, organismo_id, codigo, titulo, descripcion, 
         ambito_tipo_id, empresa_id, territorio_id, requiere_norma_local, confidencial, 
         vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            1, -- Ley Nacional
            1, -- Nacional
            $organismo_id,
            'LEY-23349-IVA',
            'Ley de Impuesto al Valor Agregado (T.O. Dec. 280/97 y Resoluciones Generales AFIP/ARCA)',
            'Régimen tributario sobre ventas, locaciones y prestaciones de servicios. Exige determinación mensual del saldo de Débito Fiscal contra Crédito Fiscal computable, Libro IVA Digital y calendario fiscal.',
            3, -- Empresa específica
            $empresa_id,
            NULL,
            0,
            0,
            '1997-01-01',
            1,
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_norma) or die("Error al crear norma: " . mysqli_error($conexion));
    $norma_id = mysqli_insert_id($conexion);
    echo "2. Norma creada: LEY-23349-IVA (ID $norma_id)\n";
}

// -----------------------------------------------------------------------------
// 3. OBLIGACION: OBL-IVA-MENSUAL
// -----------------------------------------------------------------------------
$q_obl = mysqli_query($conexion, "SELECT obligacion_id FROM ml__obligaciones WHERE codigo = 'OBL-IVA-MENSUAL' LIMIT 1");
if ($r = mysqli_fetch_assoc($q_obl)) {
    $obligacion_id = (int)$r['obligacion_id'];
    echo "3. Obligación existente: OBL-IVA-MENSUAL (ID $obligacion_id)\n";
} else {
    $sql_obl = "INSERT INTO ml__obligaciones 
        (norma_id, empresa_id, articulo_referencia, codigo, descripcion, periodicidad_id, 
         vencimiento_regla_id, criticidad_id, requiere_revision, requiere_evidencia, 
         retencion_evidencias_meses, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $norma_id,
            $empresa_id,
            'Art. 28 a 37 Ley 23.349 / RG AFIP 715/99 y RG 2000',
            'OBL-IVA-MENSUAL',
            'Determinación y Liquidación Mensual de IVA, Presentación de DDJJ Formulario F. 2002 y Pago del Saldo Resultante (VEP) ante ARCA/AFIP',
            1, -- Mensual
            3, -- CUIT_TERMINACION
            4, -- Crítica / Mandatoria (peso 10)
            1, -- Requiere revisión técnica contable
            1, -- Requiere evidencias (DDJJ y VEP)
            120, -- 10 años (Ley 11.683)
            '2024-01-01',
            1,
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_obl) or die("Error al crear obligacion: " . mysqli_error($conexion));
    $obligacion_id = mysqli_insert_id($conexion);
    echo "3. Obligación creada: OBL-IVA-MENSUAL (ID $obligacion_id)\n";
}

// -----------------------------------------------------------------------------
// 4. ASIGNACION DE OBLIGACION A ENTIDAD: Casa Lucho S.A. (Sede Central)
// -----------------------------------------------------------------------------
$q_oa = mysqli_query($conexion, "SELECT obligacion_asignada_id FROM ml__obligaciones_asignadas WHERE obligacion_prevaleciente_id = $obligacion_id AND entidad_id = $entidad_id LIMIT 1");
if ($r = mysqli_fetch_assoc($q_oa)) {
    $obligacion_asignada_id = (int)$r['obligacion_asignada_id'];
    echo "4. Asignación existente: ID $obligacion_asignada_id\n";
} else {
    $sql_oa = "INSERT INTO ml__obligaciones_asignadas 
        (obligacion_prevaleciente_id, entidad_id, empresa_id, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES ($obligacion_id, $entidad_id, $empresa_id, '2024-01-01', 1, NOW(), 1)";
    mysqli_query($conexion, $sql_oa) or die("Error al asignar obligacion: " . mysqli_error($conexion));
    $obligacion_asignada_id = mysqli_insert_id($conexion);
    echo "4. Asignación creada: ID $obligacion_asignada_id a Casa Lucho Sede Central\n";
}

// -----------------------------------------------------------------------------
// 5. PROCEDIMIENTO E INSTRUCTIVO OPERATIVO
// -----------------------------------------------------------------------------
$q_proc = mysqli_query($conexion, "SELECT procedimiento_id FROM ml__procedimientos WHERE codigo = 'PROC-IMP-IVA-01' LIMIT 1");
if ($r = mysqli_fetch_assoc($q_proc)) {
    $procedimiento_id = (int)$r['procedimiento_id'];
    echo "5. Procedimiento existente: PROC-IMP-IVA-01 (ID $procedimiento_id)\n";
} else {
    $sql_proc = "INSERT INTO ml__procedimientos 
        (empresa_id, norma_id, codigo, nombre, descripcion, confidencial, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $empresa_id,
            $norma_id,
            'PROC-IMP-IVA-01',
            'Procedimiento Estándar de Determinación, Conciliación y Liquidación Mensual de IVA',
            'Instructivo de control tributario: Cierre de Libro IVA Compras/Ventas, conciliación de retenciones/percepciones sufridas, confección del F. 2002 en Portal IVA, revisión de saldos y emisión de VEP.',
            0,
            1,
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_proc) or die("Error al crear procedimiento: " . mysqli_error($conexion));
    $procedimiento_id = mysqli_insert_id($conexion);
    echo "5. Procedimiento creado: PROC-IMP-IVA-01 (ID $procedimiento_id)\n";
}

// Versión del Procedimiento
$q_pv = mysqli_query($conexion, "SELECT procedimiento_version_id FROM ml__procedimientos_versiones WHERE procedimiento_id = $procedimiento_id AND version = 'v2.4' LIMIT 1");
if ($r = mysqli_fetch_assoc($q_pv)) {
    $procedimiento_version_id = (int)$r['procedimiento_version_id'];
    echo "   -> Versión existente: v2.4 (ID $procedimiento_version_id)\n";
} else {
    $sql_pv = "INSERT INTO ml__procedimientos_versiones 
        (procedimiento_id, version, archivo_ref, hash, resumen_cambios, aprobado_por, aprobado_en, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $procedimiento_id,
            'v2.4',
            'uploads/ml_procedimientos/PROC_IMP_IVA_01_v2.4.pdf',
            '98f12a3b4c5d6e7f8091a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3',
            'Adecuación integral al Libro de IVA Digital y nuevo Portal IVA unificado de ARCA/AFIP.',
            3, -- Martín Legal
            '2026-01-10 10:00:00',
            '2026-01-15',
            1,
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_pv) or die("Error al crear version proc: " . mysqli_error($conexion));
    $procedimiento_version_id = mysqli_insert_id($conexion);
    echo "   -> Versión creada: v2.4 (ID $procedimiento_version_id)\n";

    // Pasos / Instrucciones operativas
    $instrucciones = [
        [1, 'Cierre y exportación de subdiarios de IVA Compras e IVA Ventas', 'Verificar que todos los comprobantes del mes estén imputados, con CAE válido y sin duplicados en el ERP.', 1],
        [2, 'Descarga y conciliación de certificados de retención y percepción en SIRE / Mis Retenciones', 'Importar el archivo mensual de ARCA y cruzar contra cuentas contables 1.1.05.01 y 1.1.05.02.', 1],
        [3, 'Carga de operaciones y confección del borrador F. 2002 en Portal IVA', 'Ingresar con Clave Fiscal Nivel 3, validar alícuotas diferenciadas (10.5%, 21%, 27%) y aplicar saldos de libre disponibilidad.', 1],
        [4, 'Revisión técnica contable por Gerencia de Impuestos', 'Emitir informe de revisión tributaria y prestar conformidad previa a la presentación oficial.', 1],
        [5, 'Transmisión electrónica de la Declaración Jurada y emisión de VEP', 'Presentar formalmente el F. 2002, descargar el Acuse de Recibo con código QR y generar el Volante Electrónico de Pago.', 1],
        [6, 'Cancelación bancaria del VEP y resguardo de la constancia de pago', 'Remitir a Tesorería para cancelación en Interbanking antes de las 18:00 hs y archivar en la Bandeja de Evidencias.', 1]
    ];
    foreach ($instrucciones as $ins) {
        $sql_ins = "INSERT INTO ml__procedimientos_instrucciones 
            (procedimiento_version_id, orden, titulo, descripcion, es_obligatoria, creado_en, creado_por)
            VALUES ($procedimiento_version_id, {$ins[0]}, '{$ins[1]}', '{$ins[2]}', {$ins[3]}, NOW(), 1)";
        mysqli_query($conexion, $sql_ins);
    }
    echo "   -> 6 pasos operativos e instructivos agregados.\n";
}

// -----------------------------------------------------------------------------
// 6. CICLO DE CUMPLIMIENTO: PERIODO FISCAL SEPTIEMBRE 2026
// -----------------------------------------------------------------------------
$q_ciclo = mysqli_query($conexion, "SELECT ciclo_id FROM ml__ciclos WHERE obligacion_asignada_id = $obligacion_asignada_id AND periodo_desde = '2026-09-01' LIMIT 1");
if ($r = mysqli_fetch_assoc($q_ciclo)) {
    $ciclo_id = (int)$r['ciclo_id'];
    echo "6. Ciclo existente: ID $ciclo_id (Período 09/2026)\n";
} else {
    $sql_ciclo = "INSERT INTO ml__ciclos 
        (obligacion_asignada_id, empresa_id, entidad_id, periodo_desde, periodo_hasta, 
         fecha_inicio, fecha_vencimiento, observaciones, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $obligacion_asignada_id,
            $empresa_id,
            $entidad_id,
            '2026-09-01',
            '2026-09-30',
            '2026-10-01 08:00:00',
            '2026-10-20 20:00:00',
            'Ciclo mensual regular correspondiente al período fiscal Septiembre 2026. Terminación CUIT 8 (Vto: 20/10/2026).',
            2070, -- En curso
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_ciclo) or die("Error al crear ciclo: " . mysqli_error($conexion));
    $ciclo_id = mysqli_insert_id($conexion);
    echo "6. Ciclo creado: ID $ciclo_id (Período Fiscal 09/2026 - Vto: 20/10/2026)\n";
}

// -----------------------------------------------------------------------------
// 7. TAREAS OPERATIVAS, DEPENDENCIAS Y ESTADOS (MICROSOFT PLANNER)
// -----------------------------------------------------------------------------
echo "7. Creando tareas operativas del ciclo...\n";

// Definición de las 6 tareas del ciclo
$tareas_def = [
    [
        'clave' => 'T1_CIERRE_LIBROS',
        'titulo' => 'Cierre de Libro IVA Compras e IVA Ventas del período Septiembre 2026',
        'descripcion' => 'Cierre contable de facturación, notas de crédito/débito y cotejo con comprobantes validados en ARCA/AFIP.',
        'vence' => '2026-10-12 18:00:00',
        'cierre' => '2026-10-12 16:30:00',
        'estado' => 2081, // Cumplida
        'req_rev' => 0,
        'req_evi' => 1,
        'predecesora_clave' => null,
        'raci' => [
            ['rol' => 1, 'user' => 2], // R: Daniela Passoni
            ['rol' => 2, 'user' => 3], // A: Martín Legal
            ['rol' => 4, 'user' => 5], // I: Walter Abejer
        ]
    ],
    [
        'clave' => 'T2_CONCILIA_RETENCIONES',
        'titulo' => 'Conciliación de certificados de retenciones y percepciones (SIRE / Mis Retenciones)',
        'descripcion' => 'Importación de retenciones de clientes y bancos en Portal ARCA y conciliación de saldos computables.',
        'vence' => '2026-10-14 18:00:00',
        'cierre' => '2026-10-14 17:15:00',
        'estado' => 2081, // Cumplida
        'req_rev' => 0,
        'req_evi' => 1,
        'predecesora_clave' => 'T1_CIERRE_LIBROS',
        'raci' => [
            ['rol' => 1, 'user' => 2], // R: Daniela Passoni
            ['rol' => 2, 'user' => 3], // A: Martín Legal
            ['rol' => 3, 'user' => 1], // C: Pablo Passoni
        ]
    ],
    [
        'clave' => 'T3_BORRADOR_F2002',
        'titulo' => 'Confección del borrador de Declaración Jurada F. 2002 en Portal IVA AFIP',
        'descripcion' => 'Carga de alícuotas diferenciadas por actividad, cómputo de crédito fiscal y determinación del saldo a ingresar.',
        'vence' => '2026-10-16 18:00:00',
        'cierre' => '2026-10-16 18:10:00',
        'estado' => 2081, // Cumplida
        'req_rev' => 1,
        'req_evi' => 0,
        'predecesora_clave' => 'T2_CONCILIA_RETENCIONES',
        'raci' => [
            ['rol' => 1, 'user' => 2], // R: Daniela Passoni
            ['rol' => 2, 'user' => 3], // A: Martín Legal
        ]
    ],
    [
        'clave' => 'T4_REVISION_CONTABLE',
        'titulo' => 'Revisión técnica contable y aprobación del F. 2002 por Gerencia de Impuestos',
        'descripcion' => 'Control de saldos de libre disponibilidad del período anterior y validación de razonabilidad tributaria.',
        'vence' => '2026-10-18 18:00:00',
        'cierre' => '2026-10-18 17:00:00',
        'estado' => 2081, // Cumplida
        'req_rev' => 1,
        'req_evi' => 0,
        'predecesora_clave' => 'T3_BORRADOR_F2002',
        'raci' => [
            ['rol' => 1, 'user' => 3], // R: Martín Legal
            ['rol' => 2, 'user' => 3], // A: Martín Legal
            ['rol' => 3, 'user' => 1], // C: Pablo Passoni
            ['rol' => 4, 'user' => 2], // I: Daniela Passoni
        ]
    ],
    [
        'clave' => 'T5_PRESENTA_DDJJ',
        'titulo' => 'Presentación oficial de Declaración Jurada F. 2002 y generación de VEP en AFIP/ARCA',
        'descripcion' => 'Transmisión telemática con Clave Fiscal Nivel 3, obtención de acuse de presentación y generación de VEP para pago.',
        'vence' => '2026-10-20 20:00:00',
        'cierre' => '2026-10-20 18:45:00',
        'estado' => 2081, // Cumplida
        'req_rev' => 0,
        'req_evi' => 1,
        'predecesora_clave' => 'T4_REVISION_CONTABLE',
        'raci' => [
            ['rol' => 1, 'user' => 2], // R: Daniela Passoni
            ['rol' => 2, 'user' => 3], // A: Martín Legal
            ['rol' => 4, 'user' => 1], // I: Pablo Passoni (Tesorería)
            ['rol' => 4, 'user' => 5], // I: Walter Abejer (Auditoría)
        ]
    ],
    [
        'clave' => 'T6_PAGO_VEP',
        'titulo' => 'Cancelación y pago del VEP en plataforma bancaria Interbanking y resguardo',
        'descripcion' => 'Ejecución del pago bancario por débito en cuenta corriente del Banco Galicia y descarga del comprobante definitivo.',
        'vence' => '2026-10-21 18:00:00',
        'cierre' => null,
        'estado' => 2079, // En curso (en gestión de pago bancario)
        'req_rev' => 0,
        'req_evi' => 1,
        'predecesora_clave' => 'T5_PRESENTA_DDJJ',
        'raci' => [
            ['rol' => 1, 'user' => 1], // R: Pablo Passoni (Tesorería / Pagos)
            ['rol' => 2, 'user' => 1], // A: Pablo Passoni
            ['rol' => 3, 'user' => 3], // C: Martín Legal
            ['rol' => 4, 'user' => 2], // I: Daniela Passoni
            ['rol' => 4, 'user' => 5], // I: Walter Abejer
        ]
    ]
];

$tareas_ids = [];

foreach ($tareas_def as $t) {
    $q_chk = mysqli_query($conexion, "SELECT ciclo_tarea_id FROM ml__ciclos_tareas WHERE ciclo_id = $ciclo_id AND titulo = '" . mysqli_real_escape_string($conexion, $t['titulo']) . "' LIMIT 1");
    if ($r = mysqli_fetch_assoc($q_chk)) {
        $tid = (int)$r['ciclo_tarea_id'];
        echo "   * Tarea existente: ID $tid - {$t['titulo']}\n";
    } else {
        $cierre_sql = $t['cierre'] ? "'{$t['cierre']}'" : "NULL";
        $sql_t = "INSERT INTO ml__ciclos_tareas 
            (ciclo_id, titulo, descripcion, procedimiento_version_id, requiere_revision, requiere_evidencia, 
             fecha_activacion, fecha_inicio, fecha_vencimiento, fecha_cierre, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
            VALUES (
                $ciclo_id,
                '" . mysqli_real_escape_string($conexion, $t['titulo']) . "',
                '" . mysqli_real_escape_string($conexion, $t['descripcion']) . "',
                $procedimiento_version_id,
                {$t['req_rev']},
                {$t['req_evi']},
                '2026-10-01 08:00:00',
                '2026-10-01 09:00:00',
                '{$t['vence']}',
                $cierre_sql,
                {$t['estado']},
                $empresa_id,
                NOW(),
                1
            )";
        mysqli_query($conexion, $sql_t) or die("Error al crear tarea: " . mysqli_error($conexion));
        $tid = mysqli_insert_id($conexion);
        echo "   * Tarea creada: ID $tid - {$t['titulo']} (Estado: {$t['estado']})\n";
    }
    $tareas_ids[$t['clave']] = $tid;

    // Asignar Matriz RACI
    foreach ($t['raci'] as $rc) {
        $chk_raci = mysqli_query($conexion, "SELECT ciclo_tarea_raci_id FROM ml__ciclos_tareas_raci WHERE ciclo_tarea_id = $tid AND raci_rol_id = {$rc['rol']} AND usuario_id = {$rc['user']} LIMIT 1");
        if (!mysqli_fetch_assoc($chk_raci)) {
            mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_raci (ciclo_tarea_id, raci_rol_id, usuario_id, vigente_desde, empresa_id, creado_en) VALUES ($tid, {$rc['rol']}, {$rc['user']}, '2026-10-01', $empresa_id, NOW())");
        }
    }
}

// -----------------------------------------------------------------------------
// 8. DEPENDENCIAS SECUENCIALES OBLIGATORIAS
// -----------------------------------------------------------------------------
echo "\n8. Vinculando dependencias predecesoras obligatorias...\n";
foreach ($tareas_def as $t) {
    if (!empty($t['predecesora_clave']) && isset($tareas_ids[$t['predecesora_clave']])) {
        $tarea_id = $tareas_ids[$t['clave']];
        $pred_id = $tareas_ids[$t['predecesora_clave']];

        $chk_dep = mysqli_query($conexion, "SELECT ciclo_tarea_dependencia_id FROM ml__ciclos_tareas_dependencias WHERE ciclo_tarea_id = $tarea_id AND ciclo_tarea_predecesora_id = $pred_id LIMIT 1");
        if (!mysqli_fetch_assoc($chk_dep)) {
            mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_dependencias 
                (ciclo_tarea_id, ciclo_tarea_predecesora_id, estado_activador_id, obligatoria, empresa_id, creado_en)
                VALUES ($tarea_id, $pred_id, 2081, 1, $empresa_id, NOW())");
            echo "   -> Tarea $tarea_id bloqueada hasta que Tarea $pred_id esté Cumplida (2081)\n";
        }
    }
}

// -----------------------------------------------------------------------------
// 9. EVIDENCIAS PROBATORIAS CON FIRMA CRIPTOGRAFICA SHA-256
// -----------------------------------------------------------------------------
echo "\n9. Guardando archivos físicos probatorios y registrando evidencias...\n";

$upload_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ml_evidencias' . DIRECTORY_SEPARATOR . '2';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$evidencias_iva = [
    [
        'tarea_clave' => 'T1_CIERRE_LIBROS',
        'titulo' => 'Libro de IVA Digital - Período Septiembre 2026 (Compras y Ventas)',
        'descripcion' => 'Resumen consolidado emitido por el sistema ERP con el listado completo de facturación electrónica y compras imputadas.',
        'filename' => 'Libro_IVA_Digital_Septiembre_2026.pdf',
        'content' => "%PDF-1.4\nLIBRO DE IVA DIGITAL - PERIODO FISCAL 09/2026 - CASALUCHO S.A. CUIT 30-71234567-8\nTOTAL NETO GRAVADO: $ 12.450.000,00\nDEBITO FISCAL (21%): $ 2.614.500,00\nTOTAL COMPRAS: $ 5.541.000,00\nCREDITO FISCAL (21%): $ 1.163.610,00\n%%EOF",
        'es_comprobante' => 0,
        'valida_hasta' => '2027-10-31',
        'retener_hasta' => '2036-10-31',
        'confidencial' => 0
    ],
    [
        'tarea_clave' => 'T2_CONCILIA_RETENCIONES',
        'titulo' => 'Papeles de Trabajo - Conciliación de Retenciones y Percepciones de IVA Sufridas',
        'descripcion' => 'Planilla de control y cotejo contra aplicativo SIRE / Mis Retenciones de ARCA con número de certificado y CUIT agente retenedor.',
        'filename' => 'Papeles_Trabajo_Retenciones_IVA_09_2026.xlsx',
        'content' => "CONCILIACION RETENCIONES SUFRIDAS IVA 09/2026\nAGENTE_CUIT;RAZON_SOCIAL;CERTIFICADO_NRO;IMPORTE_RET\n30-50000319-3;BANCO DE GALICIA Y BS AS;RET-2026-99124;$ 185.400,00\n30-61234567-1;YPF S.A.;RET-2026-88123;$ 420.000,00",
        'es_comprobante' => 0,
        'valida_hasta' => '2027-10-31',
        'retener_hasta' => '2036-10-31',
        'confidencial' => 0
    ],
    [
        'tarea_clave' => 'T5_PRESENTA_DDJJ',
        'titulo' => 'Formulario Oficial F. 2002 y Acuse de Recibo AFIP/ARCA - IVA Septiembre 2026',
        'descripcion' => 'Comprobante oficial de Declaración Jurada presentada. Transacción N° 20260900145290. Saldo a favor de AFIP: $ 1.450.800,00.',
        'filename' => 'Acuse_Presentacion_AFIP_F2002_IVA_09_2026.pdf',
        'content' => "%PDF-1.4\nAGENCIA DE RECAUDACION Y CONTROL ADUANERO - ARCA / AFIP\nFORMULARIO 2002 - DECLARACION JURADA IMPUESTO AL VALOR AGREGADO\nCONTRIBUYENTE: CASALUCHO S.A. | CUIT: 30-71234567-8\nPERIODO FISCAL: 09/2026\nTRANSACCION NRO: 20260900145290\nSALDO DE LA DECLARACION JURADA A FAVOR DE ARCA: $ 1.450.800,00\nESTADO: PRESENTADO - ACEPTADO CONFORME\n%%EOF",
        'es_comprobante' => 1,
        'valida_hasta' => null, // Permanente
        'retener_hasta' => '2036-10-31', // 10 años
        'confidencial' => 0
    ],
    [
        'tarea_clave' => 'T6_PAGO_VEP',
        'titulo' => 'Volante Electrónico de Pago (VEP) N° 88129031 - Saldo F. 2002 IVA 09/2026',
        'descripcion' => 'VEP generado por el importe de $ 1.450.800,00 con código de pago Red Link / Interbanking para cancelación antes del vencimiento.',
        'filename' => 'Volante_Electronico_Pago_VEP_IVA_09_2026.pdf',
        'content' => "%PDF-1.4\nVOLANTE ELECTRONICO DE PAGO - VEP NRO: 88129031\nIMPUESTO: 30 - IVA | CONCEPTO: 019 - DECLARACION JURADA | SUBCONCEPTO: 019\nIMPORTE: $ 1.450.800,00 | VENCIMIENTO: 20/10/2026 23:59\nSISTEMA RECAUDADOR: ARCA / RED INTERBANKING\n%%EOF",
        'es_comprobante' => 1,
        'valida_hasta' => '2026-10-21',
        'retener_hasta' => '2036-10-31',
        'confidencial' => 0
    ]
];

foreach ($evidencias_iva as $evi) {
    $tarea_id = $tareas_ids[$evi['tarea_clave']];
    $filepath = $upload_dir . DIRECTORY_SEPARATOR . $evi['filename'];
    file_put_contents($filepath, $evi['content']);
    $hash_sha256 = hash_file('sha256', $filepath);
    $archivo_ref = 'uploads/ml_evidencias/2/' . $evi['filename'];

    $q_chk = mysqli_query($conexion, "SELECT evidencia_id FROM ml__evidencias WHERE titulo = '" . mysqli_real_escape_string($conexion, $evi['titulo']) . "' LIMIT 1");
    if ($r = mysqli_fetch_assoc($q_chk)) {
        echo "   * Evidencia ya existente: ID {$r['evidencia_id']}\n";
    } else {
        $valida_sql = $evi['valida_hasta'] ? "'{$evi['valida_hasta']}'" : "NULL";
        $retener_sql = $evi['retener_hasta'] ? "'{$evi['retener_hasta']}'" : "NULL";

        $sql_evi = "INSERT INTO ml__evidencias 
            (ciclo_tarea_id, titulo, descripcion, archivo_ref, hash, es_comprobante, 
             valida_hasta, retener_hasta, confidencial, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
            VALUES (
                $tarea_id,
                '" . mysqli_real_escape_string($conexion, $evi['titulo']) . "',
                '" . mysqli_real_escape_string($conexion, $evi['descripcion']) . "',
                '" . mysqli_real_escape_string($conexion, $archivo_ref) . "',
                '{$hash_sha256}',
                {$evi['es_comprobante']},
                $valida_sql,
                $retener_sql,
                {$evi['confidencial']},
                2039,
                $empresa_id,
                NOW(),
                2 -- Daniela Passoni
            )";
        mysqli_query($conexion, $sql_evi) or die("Error al crear evidencia: " . mysqli_error($conexion));
        $eid = mysqli_insert_id($conexion);
        echo "   * Evidencia creada: ID $eid - {$evi['titulo']} (Hash: " . substr($hash_sha256, 0, 12) . "...)\n";
    }
}

echo "\n=== SIMULACION COMPLETADA EXITOSAMENTE ===\n";
