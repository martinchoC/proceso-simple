<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../db.php';

echo "=== INICIANDO SIMULACIÓN DE CASOS Y TAREAS OPERATIVAS ML ===\n";

// 1. Asegurar Países y Territorios
mysqli_query($conexion, "INSERT IGNORE INTO ml__territorios (territorio_id, territorio_tipo_id, codigo, nombre, huso_horario, tabla_estado_registro_id) 
VALUES 
(4, 3, 'CHL', 'Chile', 'America/Santiago', 1),
(5, 3, 'BRA', 'Brasil', 'America/Sao_Paulo', 1),
(6, 3, 'PRY', 'Paraguay', 'America/Asuncion', 1)");

// Pertenencias territoriales a MUNDO / Cono Sur
mysqli_query($conexion, "INSERT IGNORE INTO ml__territorios_pertenencias (territorio_id, territorio_superior_id, es_principal, vigente_desde)
VALUES 
(2, 1, 1, '2020-01-01'), 
(3, 1, 1, '2020-01-01'), 
(4, 1, 1, '2020-01-01'), 
(5, 1, 0, '2020-01-01'), 
(6, 1, 0, '2020-01-01')");

// 2. Entidades clave
mysqli_query($conexion, "INSERT INTO ml__entidades (entidad_id, entidad_tipo_id, entidad_padre_id, codigo, nombre, descripcion, es_raiz, vigente_desde, tabla_estado_registro_id)
VALUES 
(11, 4, NULL, 'BUQUE-RIO-PARANA', 'Buque Tanque Río Paraná (IMO 9123456)', 'Buque de transporte fluvial y de cabotaje', 0, '2024-01-01', 1),
(12, 1, NULL, 'CORP-RAIZ', 'Casa Lucho S.A. (Sede Central)', 'Entidad lógica corporativa de la empresa', 1, '2020-01-01', 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), codigo = VALUES(codigo)");

// Vincular a empresas y ubicaciones
mysqli_query($conexion, "INSERT IGNORE INTO ml__entidades_empresas (entidad_id, empresa_id, es_titular, vigente_desde)
VALUES (8, 2, 1, '2024-01-01'), (9, 2, 1, '2024-01-01'), (10, 2, 1, '2024-01-01'), (11, 2, 1, '2024-01-01'), (12, 2, 1, '2024-01-01'),
(8, 1, 0, '2024-01-01'), (9, 1, 0, '2024-01-01'), (10, 1, 0, '2024-01-01'), (11, 1, 0, '2024-01-01'), (12, 1, 0, '2024-01-01')");

mysqli_query($conexion, "INSERT IGNORE INTO ml__entidades_ubicaciones (entidad_id, territorio_id, es_base, vigente_desde)
VALUES (8, 2, 1, '2024-01-01'), (9, 2, 1, '2024-01-01'), (10, 2, 1, '2024-01-01'), (11, 2, 1, '2024-01-01'), (12, 2, 1, '2024-01-01')");

// 3. Crear Norma ISO 14001 con ambito_tipo_id = 4 y territorio_id = 1 (Mundo)
mysqli_query($conexion, "INSERT INTO ml__normas (norma_id, norma_tipo_id, norma_jerarquia_id, codigo, titulo, descripcion, idioma_original, ambito_tipo_id, territorio_id, vigente_desde, tabla_estado_registro_id)
VALUES (5, 5, 1, 'ISO-14001-2015', 'ISO 14001:2015 - Sistema de Gestión Ambiental', 'Estándar internacional de gestión ambiental corporativa', 'es', 4, 1, '2020-01-01', 2047)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)");

// Obligación ISO 14001
mysqli_query($conexion, "INSERT INTO ml__obligaciones (obligacion_id, norma_id, empresa_id, articulo_referencia, codigo, descripcion, periodicidad_id, vencimiento_regla_id, criticidad_id, requiere_revision, requiere_evidencia, retencion_evidencias_meses, vigente_desde, tabla_estado_registro_id)
VALUES (5, 5, 2, 'Cláusula 9.2', 'OBL-ISO14-ANUAL', 'Auditoría Anual de Conformidad Ambiental, Aspectos e Impactos y Emisión de Informe de Cierre', 4, 2, 3, 1, 1, 60, '2024-01-01', 2063)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");

// Obligación por Evento Derrame
mysqli_query($conexion, "INSERT INTO ml__obligaciones (obligacion_id, norma_id, empresa_id, articulo_referencia, codigo, descripcion, periodicidad_id, vencimiento_regla_id, criticidad_id, requiere_revision, requiere_evidencia, retencion_evidencias_meses, vigente_desde, tabla_estado_registro_id)
VALUES (6, 2, 2, 'Art. 8 Ord. 02/98', 'OBL-MA-EVT-DERRAME', 'Activación del PLANACON, Contención de Derrame y Notificación Inmediata en menos de 24 horas', 1, 4, 3, 1, 1, 120, '2024-01-01', 2063)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");

// Obligaciones asignadas correspondientes
mysqli_query($conexion, "INSERT INTO ml__obligaciones_asignadas (obligacion_asignada_id, obligacion_prevaleciente_id, entidad_id, empresa_id, vigente_desde, tabla_estado_registro_id)
VALUES 
(5, 5, 9, 2, '2024-01-01', 1),
(6, 6, 11, 2, '2024-01-01', 1)
ON DUPLICATE KEY UPDATE obligacion_prevaleciente_id = VALUES(obligacion_prevaleciente_id)");

// 4. Crear Ciclos
// Ciclo 5: ISO 14001 en Dock Sud (Vence en 15 días)
mysqli_query($conexion, "INSERT INTO ml__ciclos (ciclo_id, obligacion_asignada_id, empresa_id, entidad_id, periodo_desde, periodo_hasta, fecha_inicio, fecha_vencimiento, tabla_estado_registro_id)
VALUES (5, 5, 2, 9, '2026-01-01', '2026-12-31', '2026-01-01 08:00:00', '2026-11-15 18:00:00', 2070)
ON DUPLICATE KEY UPDATE fecha_vencimiento = VALUES(fecha_vencimiento)");

// Ciclo 6: Evento Derrame en Buque (Vence en 24 horas - ALERTA)
mysqli_query($conexion, "INSERT INTO ml__ciclos (ciclo_id, obligacion_asignada_id, empresa_id, entidad_id, periodo_desde, periodo_hasta, fecha_inicio, fecha_vencimiento, tabla_estado_registro_id)
VALUES (6, 6, 2, 11, '2026-10-22', '2026-10-23', '2026-10-22 14:30:00', '2026-10-23 14:30:00', 2070)
ON DUPLICATE KEY UPDATE fecha_vencimiento = VALUES(fecha_vencimiento)");

// Actualizar fechas del Ciclo 4 (TK-101) para que tenga vencimiento pasado (CRÍTICO)
mysqli_query($conexion, "UPDATE ml__ciclos SET fecha_vencimiento = '2026-10-15 18:00:00', tabla_estado_registro_id = 2071 WHERE ciclo_id = 4");

// 5. Poblar Tareas Operativas Realistas con variedad de estados y plazos
$tareas = [
    // CASO 1: ISO 14001 (Ciclo 5)
    [
        'id' => 11, 'ciclo_id' => 5, 'empresa_id' => 2,
        'titulo' => 'Relevamiento de métricas de consumos, efluentes y residuos especiales del año',
        'desc' => 'Recopilación documental de balances de masa y manifiestos de transporte para auditoría.',
        'estado' => 2081, // Cumplida
        'vence' => '2026-10-18 18:00:00',
        'resp_id' => 5, // Walter Abejer
        'aprob_id' => 1 // Pablo Passoni
    ],
    [
        'id' => 12, 'ciclo_id' => 5, 'empresa_id' => 2,
        'titulo' => 'Coordinación y ejecución de auditoría in situ con auditor líder IRAM',
        'desc' => 'Recorrido de campo por muelles, tanques y plantas de tratamiento.',
        'estado' => 2080, // En revisión
        'vence' => '2026-10-28 18:00:00',
        'resp_id' => 5,
        'aprob_id' => 1
    ],
    [
        'id' => 13, 'ciclo_id' => 5, 'empresa_id' => 2,
        'titulo' => 'Aprobación de la memoria ambiental anual y plan de acción de no conformidades',
        'desc' => 'Firma digital de informe final de cierre y remisión a Dirección Corporativa.',
        'estado' => 2077, // Inactiva (espera a T12)
        'vence' => '2026-11-15 18:00:00',
        'resp_id' => 3, // Martin Legal
        'aprob_id' => 1
    ],

    // CASO 2: Seguridad e Higiene Ley 19.587 (Ciclo 1)
    [
        'id' => 1, 'ciclo_id' => 1, 'empresa_id' => 2,
        'titulo' => 'Inspección técnica semestral de extintores, rociadores y salidas de emergencia',
        'desc' => 'Prueba de carga y verificación de manómetros en planta.',
        'estado' => 2081, // Cumplida
        'vence' => '2026-10-15 18:00:00',
        'resp_id' => 5,
        'aprob_id' => 1
    ],
    [
        'id' => 2, 'ciclo_id' => 1, 'empresa_id' => 2,
        'titulo' => 'Medición de resistencia de puesta a tierra y continuidad de masas (Res. SRT 900/15)',
        'desc' => 'Medición de 48 jabalinas con telurímetro homologado INTI.',
        'estado' => 2079, // En curso
        'vence' => '2026-10-26 18:00:00', // Próxima (≤ 4 días)
        'resp_id' => 6, // Eduardo Zamudio
        'aprob_id' => 5
    ],
    [
        'id' => 3, 'ciclo_id' => 1, 'empresa_id' => 2,
        'titulo' => 'Subsanación técnica y reemplazo de jabalina J-14 con bentonita y dispersor',
        'desc' => 'Corrección obligatoria tras dar >10 Ohms en medición preliminar.',
        'estado' => 2082, // Rechazada / Observada
        'vence' => '2026-10-27 18:00:00',
        'resp_id' => 6,
        'aprob_id' => 5
    ],

    // CASO 3: Evento Crítico Derrame en Buque (Ciclo 6 - Urgente)
    [
        'id' => 14, 'ciclo_id' => 6, 'empresa_id' => 2,
        'titulo' => 'Despliegue de barreras absorbentes marinas y contención física de hidrocarburo',
        'desc' => 'Cierre de imbornales y confinamiento perimetral en muelle.',
        'estado' => 2081, // Cumplida
        'vence' => '2026-10-22 16:30:00',
        'resp_id' => 7, // Eduardo Archilla
        'aprob_id' => 1
    ],
    [
        'id' => 15, 'ciclo_id' => 6, 'empresa_id' => 2,
        'titulo' => 'Confección y remisión de Alerta Temprana a Prefectura Naval y DPMA (<6 hs)',
        'desc' => 'Formulario oficial de notificación según ordenanza marítima vigente.',
        'estado' => 2079, // En curso
        'vence' => date('Y-m-d H:i:s', strtotime('+6 hours')), // Vence hoy!
        'resp_id' => 3,
        'aprob_id' => 1
    ],
    [
        'id' => 16, 'ciclo_id' => 6, 'empresa_id' => 2,
        'titulo' => 'Informe técnico pericial de causa raíz y acta notarial de cierre de evento',
        'desc' => 'Inspección de brida y ensayo hidrostático de manguera de trasvase.',
        'estado' => 2078, // Pendiente
        'vence' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        'resp_id' => 7,
        'aprob_id' => 3
    ],

    // CASO 4: Auditoría de Tanques SEN 785 / TK-101 (Ciclo 4 - Vencida Crítica)
    [
        'id' => 8, 'ciclo_id' => 4, 'empresa_id' => 2,
        'titulo' => 'Medición ultrasonográfica de espesores en virolas y fondo TK-101',
        'desc' => 'Ensayo no destructivo exigido por Secretaría de Energía.',
        'estado' => 2079, // En curso pero VENCIDA
        'vence' => '2026-10-10 18:00:00', // Atrasada!
        'resp_id' => 6,
        'aprob_id' => 1
    ],
    [
        'id' => 9, 'ciclo_id' => 4, 'empresa_id' => 2,
        'titulo' => 'Inspección técnica de válvulas de presión/vacío, arrestallamas y cubeto de retención',
        'desc' => 'Chequeo de estanqueidad de membrana flotante interna.',
        'estado' => 2078, // Pendiente pero VENCIDA
        'vence' => '2026-10-12 18:00:00', // Atrasada!
        'resp_id' => 6,
        'aprob_id' => 1
    ],
    [
        'id' => 10, 'ciclo_id' => 4, 'empresa_id' => 2,
        'titulo' => 'Emisión y suscripción del Certificado de Auditoría Anual ante la Secretaría de Energía',
        'desc' => 'Trámite digital ante la plataforma de hidrocarburos de Nación.',
        'estado' => 2077, // Inactiva (espera a T8 y T9)
        'vence' => '2026-10-31 18:00:00',
        'resp_id' => 3,
        'aprob_id' => 1
    ],

    // CASO 5: Gobierno Societario Ley 19.550 (Ciclo 3)
    [
        'id' => 6, 'ciclo_id' => 3, 'empresa_id' => 2,
        'titulo' => 'Redacción de memoria del directorio y aprobación de estados contables',
        'desc' => 'Dictamen contable de auditoría externa y proyecto de distribución de utilidades.',
        'estado' => 2081, // Cumplida
        'vence' => '2026-10-15 18:00:00',
        'resp_id' => 2, // Daniela Passoni
        'aprob_id' => 1
    ],
    [
        'id' => 7, 'ciclo_id' => 3, 'empresa_id' => 2,
        'titulo' => 'Publicación de edictos de convocatoria a Asamblea Ordinaria en Boletín Oficial',
        'desc' => 'Cumplimiento del plazo de publicación con 20 días de anticipación.',
        'estado' => 2079, // En curso
        'vence' => '2026-10-29 18:00:00', // Próxima
        'resp_id' => 3,
        'aprob_id' => 1
    ],
    [
        'id' => 17, 'ciclo_id' => 3, 'empresa_id' => 2,
        'titulo' => 'Celebración de Asamblea General Ordinaria y protocolización notarial del Acta',
        'desc' => 'Firma presencial en libro societario rubricado y presentación telemática ante IGJ.',
        'estado' => 2078, // Pendiente
        'vence' => '2026-11-20 18:00:00',
        'resp_id' => 3,
        'aprob_id' => 1
    ]
];

// Reinsertamos tareas
foreach ($tareas as $t) {
    $sql = "INSERT INTO ml__ciclos_tareas (ciclo_tarea_id, ciclo_id, empresa_id, titulo, descripcion, requiere_revision, requiere_evidencia, fecha_vencimiento, tabla_estado_registro_id)
            VALUES ({$t['id']}, {$t['ciclo_id']}, {$t['empresa_id']}, '{$t['titulo']}', '{$t['desc']}', 1, 1, '{$t['vence']}', {$t['estado']})
            ON DUPLICATE KEY UPDATE 
                titulo = VALUES(titulo),
                descripcion = VALUES(descripcion),
                fecha_vencimiento = VALUES(fecha_vencimiento),
                tabla_estado_registro_id = VALUES(tabla_estado_registro_id)";
    mysqli_query($conexion, $sql);

    // Asignar RACI a la tarea (R = 1, A = 2)
    if (!empty($t['resp_id'])) {
        mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_raci (ciclo_tarea_id, raci_rol_id, usuario_id, vigente_desde, empresa_id)
                                VALUES ({$t['id']}, 1, {$t['resp_id']}, '2026-01-01', {$t['empresa_id']})
                                ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id)");
    }
    if (!empty($t['aprob_id'])) {
        mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_raci (ciclo_tarea_id, raci_rol_id, usuario_id, vigente_desde, empresa_id)
                                VALUES ({$t['id']}, 2, {$t['aprob_id']}, '2026-01-01', {$t['empresa_id']})
                                ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id)");
    }
}

// 6. Dependencias entre tareas en ml__ciclos_tareas_dependencias
mysqli_query($conexion, "TRUNCATE TABLE ml__ciclos_tareas_dependencias");
$deps = [
    ['tarea' => 12, 'predecesora' => 11], // ISO: T12 depende de T11
    ['tarea' => 13, 'predecesora' => 12], // ISO: T13 depende de T12
    ['tarea' => 3,  'predecesora' => 2],  // PAT: Reemplazo jabalina depende de medición
    ['tarea' => 16, 'predecesora' => 15], // Derrame: Informe pericial depende de alerta
    ['tarea' => 10, 'predecesora' => 8],  // SEN 785: Certificado depende de ultrasonido T8
    ['tarea' => 10, 'predecesora' => 9],  // SEN 785: Certificado depende de inspección T9
    ['tarea' => 17, 'predecesora' => 7],  // Asamblea: Acta depende de publicación de edictos
];
foreach ($deps as $d) {
    mysqli_query($conexion, "INSERT INTO ml__ciclos_tareas_dependencias (ciclo_tarea_id, ciclo_tarea_predecesora_id, estado_activador_id, obligatoria, empresa_id)
                            VALUES ({$d['tarea']}, {$d['predecesora']}, 2081, 1, 2)");
}

echo "=== SIMULACIÓN COMPLETADA CON ÉXITO ===\n";
echo "Total tareas operativas cargadas: " . count($tareas) . "\n";
echo "Total dependencias creadas: " . count($deps) . "\n";
