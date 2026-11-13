<?php
/**
 * Script de Blanqueo y Siembra Inicial de Matriz Legal (Módulo 3)
 * Escenario: Empresa argentina (Casa Lucho S.A. - Empresa ID 2)
 * Instalaciones:
 *   1) Terminal de Almacenaje de Petróleo en Puerto Rosales (Coronel Rosales, BsAs)
 *   2) Oficina Comercial en Martínez (San Isidro, BsAs)
 * Flujo: Impuesto al Valor Agregado (IVA - F. 2002 / Libro IVA Digital / VEP)
 * Archivo: _local/scratch/blanquear_y_sembrar_iva.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../db.php';

echo "====================================================================\n";
echo "   RESETEO Y BLANQUEO DE OBLIGACIONES - MATRIZ LEGAL (MODULO 3)     \n";
echo "====================================================================\n\n";

mysqli_begin_transaction($conexion);

try {
    $empresa_id = 2; // Casa Lucho S.A. (Argentina)

    // -------------------------------------------------------------------------
    // PASO 1: BLANQUEAR TABLAS OPERATIVAS Y DE OBLIGACIONES
    // -------------------------------------------------------------------------
    echo "1. Blanqueando datos operativos previos de Matriz Legal...\n";

    mysqli_query($conexion, "SET FOREIGN_KEY_CHECKS = 0");

    // Tablas operativas, incidencias y excepciones
    mysqli_query($conexion, "DELETE FROM ml__evidencias");
    mysqli_query($conexion, "DELETE FROM ml__ciclos_tareas_mensajes");
    mysqli_query($conexion, "DELETE FROM ml__ciclos_tareas_dependencias");
    mysqli_query($conexion, "DELETE FROM ml__ciclos_tareas_raci");
    mysqli_query($conexion, "DELETE FROM ml__ciclos_tareas");
    mysqli_query($conexion, "DELETE FROM ml__ciclos_raci");
    mysqli_query($conexion, "DELETE FROM ml__incidencias");
    mysqli_query($conexion, "DELETE FROM ml__excepciones");
    mysqli_query($conexion, "DELETE FROM ml__reasignaciones");
    mysqli_query($conexion, "DELETE FROM ml__ciclos");
    echo "   -> Ciclos, tareas, evidencias e incidencias limpiadas.\n";

    // Tablas dependientes de obligaciones
    mysqli_query($conexion, "DELETE FROM ml__obligaciones_alertas");
    mysqli_query($conexion, "DELETE FROM ml__obligaciones_asignadas_origenes");
    mysqli_query($conexion, "DELETE FROM ml__obligaciones_asignadas_raci");
    mysqli_query($conexion, "DELETE FROM ml__obligaciones_asignadas");
    mysqli_query($conexion, "DELETE FROM ml__obligaciones_dependencias");
    mysqli_query($conexion, "DELETE FROM ml__obligaciones_jurisdicciones");
    mysqli_query($conexion, "DELETE FROM ml__obligaciones_tipos_entidades");
    mysqli_query($conexion, "DELETE FROM ml__obligaciones");
    echo "   -> ml__obligaciones y tablas vinculadas limpiadas.\n";

    // Tablas de procedimientos e instructivos
    mysqli_query($conexion, "DELETE FROM ml__procedimientos_instrucciones");
    mysqli_query($conexion, "DELETE FROM ml__procedimientos_versiones");
    mysqli_query($conexion, "DELETE FROM ml__procedimientos");
    echo "   -> ml__procedimientos limpiada.\n";

    // Tablas de normas
    mysqli_query($conexion, "DELETE FROM ml__normas_conflictos");
    mysqli_query($conexion, "DELETE FROM ml__normas_relaciones");
    mysqli_query($conexion, "DELETE FROM ml__normas_versiones");
    mysqli_query($conexion, "DELETE FROM ml__normas");
    echo "   -> ml__normas limpiada.\n";

    mysqli_query($conexion, "SET FOREIGN_KEY_CHECKS = 1");

    // -------------------------------------------------------------------------
    // PASO 2: CONFIGURAR LAS 2 ENTIDADES EXACTAS DE LA EMPRESA
    // -------------------------------------------------------------------------
    echo "\n2. Configurando las 2 instalaciones / sedes solicitadas...\n";

    // Inactivar entidades antiguas
    mysqli_query($conexion, "UPDATE ml__entidades SET tabla_estado_registro_id = 2016 WHERE 1=1");

    // 2.1 Terminal de Almacenaje Puerto Rosales
    $q_ros = mysqli_query($conexion, "SELECT entidad_id FROM ml__entidades WHERE codigo = 'TERM-ROSALES' LIMIT 1");
    if ($r_ros = mysqli_fetch_assoc($q_ros)) {
        $id_rosales = (int)$r_ros['entidad_id'];
        mysqli_query($conexion, "UPDATE ml__entidades SET 
            nombre = 'Terminal de Almacenaje Puerto Rosales',
            descripcion = 'Terminal portuaria de almacenaje de petróleo crudo y derivados, boyas monoboya y parque de tanques en Puerto Rosales (Coronel Rosales, Pcia. de Buenos Aires).',
            entidad_tipo_id = 2, -- Terminal
            es_raiz = 0,
            tabla_estado_registro_id = 2015
            WHERE entidad_id = $id_rosales");
    } else {
        mysqli_query($conexion, "INSERT INTO ml__entidades 
            (entidad_tipo_id, codigo, nombre, descripcion, es_raiz, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (2, 'TERM-ROSALES', 'Terminal de Almacenaje Puerto Rosales', 
                    'Terminal portuaria de almacenaje de petróleo crudo y derivados, boyas monoboya y parque de tanques en Puerto Rosales (Coronel Rosales, Pcia. de Buenos Aires).', 
                    0, '2024-01-01', 2015, NOW(), 1)");
        $id_rosales = mysqli_insert_id($conexion);
    }
    echo "   -> [ID $id_rosales] Terminal de Almacenaje Puerto Rosales configurada.\n";

    // 2.2 Oficina Comercial Martínez (Sede Central)
    $q_mtz = mysqli_query($conexion, "SELECT entidad_id FROM ml__entidades WHERE codigo = 'SEDE-MARTINEZ' LIMIT 1");
    if ($r_mtz = mysqli_fetch_assoc($q_mtz)) {
        $id_martinez = (int)$r_mtz['entidad_id'];
        mysqli_query($conexion, "UPDATE ml__entidades SET 
            nombre = 'Oficina Comercial Martínez',
            descripcion = 'Sede corporativa, comercial y administrativa central en Martínez, San Isidro, Pcia. de Buenos Aires.',
            entidad_tipo_id = 1, -- Sede / Planta
            es_raiz = 1,
            tabla_estado_registro_id = 2015
            WHERE entidad_id = $id_martinez");
    } else {
        mysqli_query($conexion, "INSERT INTO ml__entidades 
            (entidad_tipo_id, codigo, nombre, descripcion, es_raiz, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (1, 'SEDE-MARTINEZ', 'Oficina Comercial Martínez', 
                    'Sede corporativa, comercial y administrativa central en Martínez, San Isidro, Pcia. de Buenos Aires.', 
                    1, '2024-01-01', 2015, NOW(), 1)");
        $id_martinez = mysqli_insert_id($conexion);
    }
    echo "   -> [ID $id_martinez] Oficina Comercial Martínez configurada.\n";

    // Asegurar vinculación territorial y empresarial para ambas entidades
    foreach ([$id_rosales, $id_martinez] as $eid) {
        // Empresa 2
        $q_ee = mysqli_query($conexion, "SELECT entidad_empresa_id FROM ml__entidades_empresas WHERE entidad_id = $eid AND empresa_id = $empresa_id LIMIT 1");
        if (!mysqli_fetch_assoc($q_ee)) {
            mysqli_query($conexion, "INSERT INTO ml__entidades_empresas (entidad_id, empresa_id, es_titular, vigente_desde, creado_en, creado_por) VALUES ($eid, $empresa_id, 1, '2024-01-01', NOW(), 1)");
        }
        // Territorio Argentina (ID 2)
        $q_eu = mysqli_query($conexion, "SELECT entidad_ubicacion_id FROM ml__entidades_ubicaciones WHERE entidad_id = $eid AND territorio_id = 2 LIMIT 1");
        if (!mysqli_fetch_assoc($q_eu)) {
            mysqli_query($conexion, "INSERT INTO ml__entidades_ubicaciones (entidad_id, territorio_id, es_base, vigente_desde, creado_en, creado_por) VALUES ($eid, 2, 1, '2024-01-01', NOW(), 1)");
        }
    }

    // -------------------------------------------------------------------------
    // PASO 3: ORGANISMO REGULADOR (ARCA / AFIP)
    // -------------------------------------------------------------------------
    echo "\n3. Creando Organismo Regulador (ARCA / AFIP)...\n";
    $q_org = mysqli_query($conexion, "SELECT organismo_id FROM ml__organismos WHERE nombre LIKE '%ARCA%' OR nombre LIKE '%AFIP%' LIMIT 1");
    if ($r = mysqli_fetch_assoc($q_org)) {
        $organismo_id = (int)$r['organismo_id'];
    } else {
        mysqli_query($conexion, "INSERT INTO ml__organismos (nombre, tabla_estado_registro_id, creado_en) VALUES ('ARCA / AFIP - Agencia de Recaudación y Control Aduanero', 2021, NOW())");
        $organismo_id = mysqli_insert_id($conexion);
    }
    echo "   -> Organismo ID: $organismo_id\n";

    // -------------------------------------------------------------------------
    // PASO 4: NORMA LEGAL (LEY 23.349 DE IVA)
    // -------------------------------------------------------------------------
    echo "\n4. Creando Norma Legal de IVA...\n";
    $sql_norma = "INSERT INTO ml__normas 
        (norma_tipo_id, norma_jerarquia_id, organismo_id, codigo, titulo, descripcion, 
         ambito_tipo_id, empresa_id, territorio_id, requiere_norma_local, confidencial, 
         vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            1, -- Ley Nacional
            1, -- Nacional
            $organismo_id,
            'LEY-23349-IVA',
            'Ley de Impuesto al Valor Agregado (Ley 23.349, T.O. 1997 y RG AFIP/ARCA)',
            'Marco tributario general sobre ventas, locaciones y prestaciones de servicios. Exige determinación mensual del saldo de Débito Fiscal contra Crédito Fiscal computable, Libro IVA Digital y pago del saldo de declaración jurada.',
            3, -- Empresa Específica (permite asociar empresa_id directamente cumpliendo ck_nor_ambito)
            $empresa_id,
            NULL, -- Debe ser NULL para cumplir restriccion de exactamente 1 ambito
            0,
            0,
            '1997-01-01',
            2047, -- Confirmada
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_norma);
    $norma_id = mysqli_insert_id($conexion);
    echo "   -> Norma creada con éxito: LEY-23349-IVA (ID $norma_id)\n";

    // -------------------------------------------------------------------------
    // PASO 5: OBLIGACION LEGAL (OBL-ARCA-IVA-F2002)
    // -------------------------------------------------------------------------
    echo "\n5. Creando Obligación de IVA con diseño y parámetros ClickUp...\n";
    $sql_obl = "INSERT INTO ml__obligaciones 
        (norma_id, empresa_id, articulo_referencia, codigo, sigla, icono, color_hex, descripcion, 
         periodicidad_id, vencimiento_regla_id, criticidad_id, requiere_revision, requiere_evidencia, 
         retencion_evidencias_meses, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $norma_id,
            $empresa_id,
            'Art. 28 a 37 Ley 23.349 / RG AFIP 715/99 y RG 4597/19',
            'OBL-ARCA-IVA-F2002',
            'IVA-F2002',
            'fas fa-file-invoice-dollar',
            '#7b68ee', -- ClickUp Purple
            'Determinación y Liquidación Mensual de IVA, Presentación de DDJJ Formulario F. 2002 / Libro IVA Digital y Pago del Saldo Resultante (VEP) ante ARCA/AFIP',
            1, -- Mensual
            3, -- CUIT_TERMINACION
            4, -- Crítica / Mandatoria (Peso 10)
            1, -- Requiere revisión técnica impositiva
            1, -- Requiere evidencias documentales (F. 2002 y VEP)
            120, -- 10 años de prescripción tributaria
            '2024-01-01',
            2063, -- Confirmada / Activa
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_obl);
    $obligacion_id = mysqli_insert_id($conexion);
    echo "   -> Obligación creada: OBL-ARCA-IVA-F2002 (ID $obligacion_id)\n";

    // -------------------------------------------------------------------------
    // PASO 6: ASIGNACION A LA OFICINA COMERCIAL MARTINEZ
    // -------------------------------------------------------------------------
    echo "\n6. Asignando Obligación a la Sede Central (Martínez)...\n";
    $sql_oa = "INSERT INTO ml__obligaciones_asignadas 
        (obligacion_prevaleciente_id, entidad_id, empresa_id, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES ($obligacion_id, $id_martinez, $empresa_id, '2024-01-01', 2037, NOW(), 1)";
    mysqli_query($conexion, $sql_oa);
    $obligacion_asignada_id = mysqli_insert_id($conexion);
    echo "   -> Asignación ID: $obligacion_asignada_id\n";

    // Asignaciones RACI de la Obligación (Catálogo de gobernanza)
    mysqli_query($conexion, "INSERT INTO ml__obligaciones_asignadas_raci (obligacion_asignada_id, raci_rol_id, usuario_id, vigente_desde, empresa_id, creado_en) VALUES 
        ($obligacion_asignada_id, 2, 3, '2024-01-01', $empresa_id, NOW()), -- A: Martín Legal (Jefe Contable)
        ($obligacion_asignada_id, 1, 2, '2024-01-01', $empresa_id, NOW()), -- R: Daniela Passoni (Analista Contable)
        ($obligacion_asignada_id, 1, 6, '2024-01-01', $empresa_id, NOW()), -- R: Eduardo Zamudio (Analista Contable)
        ($obligacion_asignada_id, 3, 1, '2024-01-01', $empresa_id, NOW()), -- C: Pablo Passoni (Tesorería)
        ($obligacion_asignada_id, 4, 5, '2024-01-01', $empresa_id, NOW())  -- I: Walter Abejer (Auditoría)");

    // -------------------------------------------------------------------------
    // PASO 7: PROCEDIMIENTO E INSTRUCTIVO OPERATIVO
    // -------------------------------------------------------------------------
    echo "\n7. Creando Procedimiento e Instructivo Operativo Estándar...\n";
    $sql_proc = "INSERT INTO ml__procedimientos 
        (empresa_id, norma_id, codigo, nombre, descripcion, confidencial, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $empresa_id,
            $norma_id,
            'PROC-IMP-IVA-01',
            'Procedimiento Operativo: Determinación, Presentación y Cancelación Mensual de IVA',
            'Instructivo de control tributario: Cierre de Libro IVA Compras/Ventas, conciliación de retenciones/percepciones sufridas, confección del F. 2002 en Portal IVA, revisión de saldos y emisión de VEP.',
            0,
            2055, -- Confirmado
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_proc);
    $procedimiento_id = mysqli_insert_id($conexion);

    // Versión 3.0
    $sql_pv = "INSERT INTO ml__procedimientos_versiones 
        (procedimiento_id, version, archivo_ref, hash, resumen_cambios, aprobado_por, aprobado_en, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $procedimiento_id,
            'v3.0',
            'uploads/ml_procedimientos/PROC_IMP_IVA_01_v3.0.pdf',
            '98f12a3b4c5d6e7f8091a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3',
            'Actualización integral al Libro de IVA Digital y nuevo Portal IVA unificado de ARCA.',
            3, -- Martín Legal (Jefe Contable)
            '2026-01-10 10:00:00',
            '2026-01-15',
            2059, -- Confirmada
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_pv);
    $procedimiento_version_id = mysqli_insert_id($conexion);

    // Instrucciones paso a paso
    $instrucciones = [
        [1, 'Cierre de facturación y generación del Libro IVA Digital (Compras y Ventas)', 'Verificar que todos los comprobantes de la terminal Puerto Rosales y sede Martínez tengan CAE válido y estén imputados en el ERP.', 1],
        [2, 'Descarga y conciliación de certificados de retención y percepción en SIRE / Mis Retenciones', 'Cruzar retenciones sufridas de clientes y bancos en ARCA contra mayores contables de impuestos.', 1],
        [3, 'Confección del borrador de Declaración Jurada F. 2002 en Portal IVA', 'Ingresar con Clave Fiscal Nivel 3, declarar débitos y créditos fiscales y aplicar saldos a favor del período anterior.', 1],
        [4, 'Revisión técnica contable por Gerencia de Impuestos', 'Verificar razonabilidad de la determinación, alícuotas diferenciadas y prestar conformidad formal para transmisión.', 1],
        [5, 'Transmisión electrónica de la Declaración Jurada F. 2002 y generación de VEP', 'Presentar formalmente ante ARCA, descargar acuse oficial con código QR y generar VEP consolidado.', 1],
        [6, 'Cancelación bancaria del VEP y resguardo de la constancia probatoria', 'Ejecutar pago en Interbanking antes de las 18:00 hs y archivar comprobante en la Bandeja de Evidencias.', 1]
    ];
    foreach ($instrucciones as $ins) {
        mysqli_query($conexion, "INSERT INTO ml__procedimientos_instrucciones 
            (procedimiento_version_id, orden, titulo, descripcion, es_obligatoria, creado_en, creado_por)
            VALUES ($procedimiento_version_id, {$ins[0]}, '{$ins[1]}', '{$ins[2]}', {$ins[3]}, NOW(), 1)");
    }
    echo "   -> Procedimiento ID $procedimiento_id con Versión $procedimiento_version_id y 6 instrucciones operativas creado.\n";

    // -------------------------------------------------------------------------
    // PASO 8: CICLO DE CUMPLIMIENTO (PERIODO FISCAL SEPTIEMBRE 2026)
    // -------------------------------------------------------------------------
    echo "\n8. Creando Ciclo de Cumplimiento Mensual (Período Fiscal 09/2026)...\n";
    $sql_ciclo = "INSERT INTO ml__ciclos 
        (obligacion_asignada_id, empresa_id, entidad_id, periodo_desde, periodo_hasta, 
         fecha_inicio, fecha_vencimiento, observaciones, tabla_estado_registro_id, creado_en, creado_por)
        VALUES (
            $obligacion_asignada_id,
            $empresa_id,
            $id_martinez,
            '2026-09-01',
            '2026-09-30',
            '2026-10-01 08:00:00',
            '2026-10-20 20:00:00',
            'Liquidación mensual de IVA consolidada de Casa Lucho S.A. (Terminal Puerto Rosales y Oficina Martínez). Terminación CUIT 8 (Vto: 20/10/2026).',
            2070, -- En curso
            NOW(),
            1
        )";
    mysqli_query($conexion, $sql_ciclo);
    $ciclo_id = mysqli_insert_id($conexion);
    echo "   -> Ciclo ID: $ciclo_id\n";

    // Asignaciones RACI del Ciclo
    mysqli_query($conexion, "INSERT INTO ml__ciclos_raci (ciclo_id, raci_rol_id, usuario_id, vigente_desde, empresa_id, creado_en) VALUES 
        ($ciclo_id, 2, 3, '2026-09-01', $empresa_id, NOW()), -- A: Martín Legal (Jefe Contable)
        ($ciclo_id, 1, 2, '2026-09-01', $empresa_id, NOW()), -- R: Daniela Passoni (Analista Contable)
        ($ciclo_id, 1, 6, '2026-09-01', $empresa_id, NOW()), -- R: Eduardo Zamudio (Analista Contable)
        ($ciclo_id, 3, 1, '2026-09-01', $empresa_id, NOW()), -- C: Pablo Passoni (Tesorería)
        ($ciclo_id, 4, 5, '2026-09-01', $empresa_id, NOW())  -- I: Walter Abejer (Auditoría)");

    // -------------------------------------------------------------------------
    // PASO 9: TAREAS OPERATIVAS CON ROLES RACI Y DEPENDENCIAS
    // -------------------------------------------------------------------------
    echo "\n9. Creando 6 Tareas Operativas con Matriz RACI y Dependencias...\n";

    $tareas_def = [
        [
            'clave' => 'T1_LIBRO_IVA',
            'titulo' => 'Cierre contable de facturación y generación del Libro IVA Digital (Compras y Ventas)',
            'descripcion' => 'Conciliación de ventas de la Terminal Puerto Rosales y administración Martínez con comprobantes emitidos en ARCA.',
            'vence' => '2026-10-12 18:00:00',
            'cierre' => '2026-10-12 16:30:00',
            'estado' => 2081, // Cumplida
            'req_rev' => 0,
            'req_evi' => 1,
            'predecesora' => null,
            'comentario' => 'Cierre completado. Todas las facturas de almacenaje y ventas de combustible tienen CAE validado.',
            'raci' => [
                ['rol' => 1, 'user' => 2], // R: Daniela Passoni (Analista Contable)
                ['rol' => 3, 'user' => 6], // C: Eduardo Zamudio (Analista Contable)
                ['rol' => 2, 'user' => 3], // A: Martín Legal (Jefe Contable)
                ['rol' => 4, 'user' => 5], // I: Walter Abejer (Auditoría)
            ]
        ],
        [
            'clave' => 'T2_RETENCIONES',
            'titulo' => 'Conciliación y cruce de retenciones/percepciones sufridas en ARCA (SIRE / Mis Retenciones)',
            'descripcion' => 'Descarga de retenciones bancarias y de clientes mayoristas e imputación en cuentas contables.',
            'vence' => '2026-10-14 18:00:00',
            'cierre' => '2026-10-14 17:15:00',
            'estado' => 2081, // Cumplida
            'req_rev' => 0,
            'req_evi' => 1,
            'predecesora' => 'T1_LIBRO_IVA',
            'comentario' => 'Descargados 24 certificados SIRE. Total retenciones computables: $ 605.400,00.',
            'raci' => [
                ['rol' => 1, 'user' => 6], // R: Eduardo Zamudio (Analista Contable)
                ['rol' => 3, 'user' => 2], // C: Daniela Passoni (Analista Contable)
                ['rol' => 2, 'user' => 3], // A: Martín Legal (Jefe Contable)
                ['rol' => 4, 'user' => 1], // I: Pablo Passoni (Tesorería)
            ]
        ],
        [
            'clave' => 'T3_BORRADOR_F2002',
            'titulo' => 'Confección del borrador de Declaración Jurada F. 2002 en Portal IVA ARCA',
            'descripcion' => 'Cálculo de alícuotas diferenciadas por almacenaje y comercialización, crédito computable y determinación del saldo.',
            'vence' => '2026-10-16 18:00:00',
            'cierre' => '2026-10-16 18:05:00',
            'estado' => 2081, // Cumplida
            'req_rev' => 1,
            'req_evi' => 0,
            'predecesora' => 'T2_RETENCIONES',
            'comentario' => 'Borrador cargado en Portal IVA. Saldo preliminar a ingresar a ARCA: $ 1.450.800,00.',
            'raci' => [
                ['rol' => 1, 'user' => 2], // R: Daniela Passoni (Analista Contable)
                ['rol' => 3, 'user' => 6], // C: Eduardo Zamudio (Analista Contable)
                ['rol' => 2, 'user' => 3], // A: Martín Legal (Jefe Contable)
            ]
        ],
        [
            'clave' => 'T4_REVISION_CONTABLE',
            'titulo' => 'Revisión técnica impositiva y control de razonabilidad por Gerencia de Impuestos',
            'descripcion' => 'Control de saldos técnicos y validación de retenciones computadas previo a la transmisión oficial.',
            'vence' => '2026-10-18 18:00:00',
            'cierre' => '2026-10-18 17:00:00',
            'estado' => 2081, // Cumplida
            'req_rev' => 1,
            'req_evi' => 0,
            'predecesora' => 'T3_BORRADOR_F2002',
            'comentario' => 'Revisión técnica aprobada sin observaciones. Se autoriza presentación telemática.',
            'raci' => [
                ['rol' => 1, 'user' => 3], // R: Martín Legal (Jefe Contable)
                ['rol' => 2, 'user' => 3], // A: Martín Legal (Jefe Contable)
                ['rol' => 3, 'user' => 2], // C: Daniela Passoni (Analista Contable)
                ['rol' => 3, 'user' => 6], // C: Eduardo Zamudio (Analista Contable)
                ['rol' => 4, 'user' => 1], // I: Pablo Passoni (Tesorería)
            ]
        ],
        [
            'clave' => 'T5_PRESENTACION_OFICIAL',
            'titulo' => 'Presentación telemática oficial de DDJJ F. 2002 y emisión de VEP en ARCA/AFIP',
            'descripcion' => 'Transmisión electrónica con Clave Fiscal Nivel 3, obtención de acuse de recibo y generación de VEP.',
            'vence' => '2026-10-20 20:00:00',
            'cierre' => '2026-10-20 18:45:00',
            'estado' => 2081, // Cumplida
            'req_rev' => 0,
            'req_evi' => 1,
            'predecesora' => 'T4_REVISION_CONTABLE',
            'comentario' => 'Presentación transmitida exitosamente. Acuse N° 20260900145290 generado. VEP N° 88129031 emitido.',
            'raci' => [
                ['rol' => 1, 'user' => 2], // R: Daniela Passoni (Analista Contable)
                ['rol' => 2, 'user' => 3], // A: Martín Legal (Jefe Contable)
                ['rol' => 4, 'user' => 6], // I: Eduardo Zamudio (Analista Contable)
                ['rol' => 4, 'user' => 1], // I: Pablo Passoni (Tesorería)
                ['rol' => 4, 'user' => 5], // I: Walter Abejer (Auditoría)
            ]
        ],
        [
            'clave' => 'T6_PAGO_VEP',
            'titulo' => 'Cancelación y pago del VEP N° 88129031 en Interbanking y resguardo probatorio',
            'descripcion' => 'Ejecución del pago bancario por débito en cuenta corriente del Banco Galicia y archivo de la constancia.',
            'vence' => '2026-10-21 18:00:00',
            'cierre' => null,
            'estado' => 2079, // En curso (en gestión de pago bancario)
            'req_rev' => 0,
            'req_evi' => 1,
            'predecesora' => 'T5_PRESENTACION_OFICIAL',
            'comentario' => 'VEP cargado en la plataforma Interbanking del Banco Galicia. Pendiente de firma de Tesorería.',
            'raci' => [
                ['rol' => 1, 'user' => 1], // R: Pablo Passoni (Tesorería)
                ['rol' => 2, 'user' => 1], // A: Pablo Passoni (Tesorería)
                ['rol' => 3, 'user' => 3], // C: Martín Legal (Jefe Contable)
                ['rol' => 4, 'user' => 2], // I: Daniela Passoni (Analista Contable)
                ['rol' => 4, 'user' => 6], // I: Eduardo Zamudio (Analista Contable)
                ['rol' => 4, 'user' => 5], // I: Walter Abejer (Auditoría)
            ]
        ]
    ];

    $map_tareas = [];

    foreach ($tareas_def as $t) {
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
        mysqli_query($conexion, $sql_t);
        $tid = mysqli_insert_id($conexion);
        $map_tareas[$t['clave']] = $tid;
        echo "   * Tarea ID $tid: {$t['titulo']} [Estado: {$t['estado']}]\n";

        // Asignaciones RACI
        foreach ($t['raci'] as $rc) {
            mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_raci 
                (ciclo_tarea_id, raci_rol_id, usuario_id, vigente_desde, empresa_id, creado_en) 
                VALUES ($tid, {$rc['rol']}, {$rc['user']}, '2026-10-01', $empresa_id, NOW())");
        }

        // Comentario de hilo
        if (!empty($t['comentario'])) {
            $user_id = $t['raci'][0]['user']; // Primer usuario (R)
            mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_mensajes 
                (ciclo_tarea_id, usuario_id, tipo, mensaje, creado_en) 
                VALUES ($tid, $user_id, 'USUARIO', '" . mysqli_real_escape_string($conexion, $t['comentario']) . "', NOW())");
        }
    }

    // Predecesoras
    echo "\n10. Vinculando dependencias predecesoras obligatorias...\n";
    foreach ($tareas_def as $t) {
        if (!empty($t['predecesora']) && isset($map_tareas[$t['predecesora']])) {
            $tid = $map_tareas[$t['clave']];
            $pid = $map_tareas[$t['predecesora']];
            mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_dependencias 
                (ciclo_tarea_id, ciclo_tarea_predecesora_id, estado_activador_id, obligatoria, empresa_id, creado_en) 
                VALUES ($tid, $pid, 2081, 1, $empresa_id, NOW())");
            echo "   -> Tarea $tid bloqueada hasta cumplir Tarea $pid.\n";
        }
    }

    // -------------------------------------------------------------------------
    // PASO 11: EVIDENCIAS PROBATORIAS REALES EN DISCO Y BD
    // -------------------------------------------------------------------------
    echo "\n11. Generando archivos probatorios y registrando evidencias...\n";

    $upload_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ml_evidencias' . DIRECTORY_SEPARATOR . '2';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $evidencias_data = [
        [
            'tarea_clave' => 'T1_LIBRO_IVA',
            'titulo' => 'Libro de IVA Digital - Período Septiembre 2026 (Terminal Puerto Rosales y Martínez)',
            'descripcion' => 'Resumen consolidado emitido por el sistema ERP con el listado completo de facturación electrónica y compras imputadas.',
            'filename' => 'Libro_IVA_Digital_Septiembre_2026.pdf',
            'content' => "%PDF-1.4\nLIBRO DE IVA DIGITAL - PERIODO FISCAL 09/2026 - CASALUCHO S.A. CUIT 30-71234567-8\nSEDE CENTRAL MARTINEZ Y TERMINAL PUERTO ROSALES\nTOTAL NETO GRAVADO: $ 12.450.000,00\nDEBITO FISCAL (21%): $ 2.614.500,00\nTOTAL COMPRAS COMPUTABLES: $ 5.541.000,00\nCREDITO FISCAL COMPUTABLE: $ 1.163.610,00\n%%EOF",
            'es_comprobante' => 0,
            'valida_hasta' => '2027-10-31',
            'retener_hasta' => '2036-10-31',
            'usuario_id' => 2 // Daniela Passoni
        ],
        [
            'tarea_clave' => 'T2_RETENCIONES',
            'titulo' => 'Papeles de Trabajo - Conciliación de Retenciones y Percepciones de IVA Sufridas',
            'descripcion' => 'Planilla de control y cotejo contra aplicativo SIRE / Mis Retenciones de ARCA con número de certificado y CUIT agente retenedor.',
            'filename' => 'Papeles_Trabajo_Retenciones_IVA_09_2026.xlsx',
            'content' => "CONCILIACION RETENCIONES SUFRIDAS IVA 09/2026\nAGENTE_CUIT;RAZON_SOCIAL;CERTIFICADO_NRO;IMPORTE_RET\n30-50000319-3;BANCO DE GALICIA Y BS AS;RET-2026-99124;$ 185.400,00\n30-61234567-1;YPF S.A.;RET-2026-88123;$ 420.000,00",
            'es_comprobante' => 0,
            'valida_hasta' => '2027-10-31',
            'retener_hasta' => '2036-10-31',
            'usuario_id' => 2
        ],
        [
            'tarea_clave' => 'T5_PRESENTACION_OFICIAL',
            'titulo' => 'Formulario Oficial F. 2002 y Acuse de Recibo AFIP/ARCA - IVA Septiembre 2026',
            'descripcion' => 'Comprobante oficial de Declaración Jurada presentada. Transacción N° 20260900145290. Saldo a favor de AFIP: $ 1.450.800,00.',
            'filename' => 'Acuse_Presentacion_AFIP_F2002_IVA_09_2026.pdf',
            'content' => "%PDF-1.4\nAGENCIA DE RECAUDACION Y CONTROL ADUANERO - ARCA / AFIP\nFORMULARIO 2002 - DECLARACION JURADA IMPUESTO AL VALOR AGREGADO\nCONTRIBUYENTE: CASALUCHO S.A. | CUIT: 30-71234567-8\nESTABLECIMIENTOS: MARTINEZ (BSAS) Y PUERTO ROSALES\nPERIODO FISCAL: 09/2026\nTRANSACCION NRO: 20260900145290\nSALDO DE LA DECLARACION JURADA A FAVOR DE ARCA: $ 1.450.800,00\nESTADO: PRESENTADO - ACEPTADO CONFORME\n%%EOF",
            'es_comprobante' => 1,
            'valida_hasta' => null, // Permanente
            'retener_hasta' => '2036-10-31', // 10 años
            'usuario_id' => 2
        ],
        [
            'tarea_clave' => 'T6_PAGO_VEP',
            'titulo' => 'Volante Electrónico de Pago (VEP) N° 88129031 - Saldo F. 2002 IVA 09/2026',
            'descripcion' => 'VEP generado por el importe de $ 1.450.800,00 con código de pago Red Link / Interbanking para cancelación antes del vencimiento.',
            'filename' => 'Volante_Electronico_Pago_VEP_IVA_09_2026.pdf',
            'content' => "%PDF-1.4\nVOLANTE ELECTRONICO DE PAGO - VEP NRO: 88129031\nCONTRIBUYENTE: CASALUCHO S.A. | CUIT: 30-71234567-8\nIMPUESTO: 30 - IVA | CONCEPTO: 019 - DECLARACION JURADA | SUBCONCEPTO: 019\nIMPORTE: $ 1.450.800,00 | VENCIMIENTO: 21/10/2026 18:00\nSISTEMA RECAUDADOR: ARCA / RED INTERBANKING\n%%EOF",
            'es_comprobante' => 1,
            'valida_hasta' => '2026-10-21',
            'retener_hasta' => '2036-10-31',
            'usuario_id' => 1 // Pablo Passoni
        ]
    ];

    foreach ($evidencias_data as $evi) {
        $tarea_id = $map_tareas[$evi['tarea_clave']];
        $filepath = $upload_dir . DIRECTORY_SEPARATOR . $evi['filename'];
        file_put_contents($filepath, $evi['content']);
        $hash_sha256 = hash_file('sha256', $filepath);
        $archivo_ref = 'uploads/ml_evidencias/2/' . $evi['filename'];

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
                0,
                2039,
                $empresa_id,
                NOW(),
                {$evi['usuario_id']}
            )";
        mysqli_query($conexion, $sql_evi);
        $eid = mysqli_insert_id($conexion);
        echo "   * Evidencia ID $eid creada: {$evi['filename']} (Hash: " . substr($hash_sha256, 0, 10) . "...)\n";
    }

    mysqli_commit($conexion);
    echo "\n====================================================================\n";
    echo "   ¡BLANQUEO Y SIEMBRA DE IVA COMPLETADOS EXITOSAMENTE!             \n";
    echo "====================================================================\n";

} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo "\n[ERROR CRITICO] " . $e->getMessage() . "\n";
}
