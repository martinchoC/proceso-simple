-- =====================================================================
-- Matriz Legal - M2: PÁGINAS, FUNCIONES, PERMISOS Y SEMILLAS DE CONFIGURACIÓN
-- Archivo: sql/mantenimiento/20261003_ml_m2_01_paginas_y_permisos.sql
-- =====================================================================

START TRANSACTION;

-- 1. PÁGINAS DEL MÓDULO 3 (Colgadas del contenedor 63: Configuración y Tablas)
INSERT INTO conf__paginas 
(pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
VALUES
(308, 3, 'Configuración y Opciones', 'configuracion.php', 9, 'Las 10 opciones configurables del grupo y RACI', 63, 40, 223, 1, 1, 0),
(309, 3, 'Ámbitos de Usuarios', 'usuarios_ambitos.php', 30, 'Asignación de alcance territorial y corporativo', 63, 50, 219, 1, 1, 0),
(310, 3, 'Delegaciones y Suplencias', 'delegaciones.php', 7, 'Gestión de ausencias temporales y reemplazos', 63, 60, 220, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    pagina = VALUES(pagina),
    url = VALUES(url),
    icono_id = VALUES(icono_id),
    pagina_descripcion = VALUES(pagina_descripcion),
    padre_id = VALUES(padre_id),
    orden = VALUES(orden),
    tabla_id = VALUES(tabla_id);

-- 2. FUNCIONES DE PÁGINA (conf__paginas_funciones: Bloque 3050-3070)
INSERT INTO conf__paginas_funciones 
(pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES
-- Página 308: Configuración
(3050, 308, 1, 1, 2, 'Modificar Opción', 'modificar_opcion', 'Actualizar valor de opción corporativa', 1, 1, 10, 1),

-- Página 309: Ámbitos de Usuarios
(3055, 309, 1, 1, 1, 'Asignar Ámbito', 'agregar_ambito', 'Vincular usuario a alcance territorial/corporativo', 0, 1, 1, 1),
(3056, 309, 1, 4, 3, 'Cerrar Ámbito', 'cerrar_ambito', 'Finalizar vigencia del ámbito asignado', 1, 1, 10, 1),

-- Página 310: Delegaciones
(3060, 310, 1, 1, 1, 'Registrar Suplencia', 'agregar_delegacion', 'Crear reemplazo temporal por ausencia', 0, 1, 1, 1),
(3061, 310, 1, 4, 3, 'Cancelar Suplencia', 'cancelar_delegacion', 'Dar de baja suplencia anticipadamente', 1, 2, 10, 1)
ON DUPLICATE KEY UPDATE 
    nombre_funcion = VALUES(nombre_funcion),
    accion_js = VALUES(accion_js),
    descripcion = VALUES(descripcion);

-- 3. PERMISOS EN PERFILES BASE (conf__perfiles_funciones)
-- 301 (Admin Matriz): Todas las funciones de M2
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(301, 3050, 1),
(301, 3055, 1),
(301, 3056, 1),
(301, 3060, 1),
(301, 3061, 1),
-- 304 (Resp. Empresa) y 306 (Aprobador): Pueden registrar y cancelar suplencias de su equipo
(304, 3060, 1),
(304, 3061, 1),
(306, 3060, 1),
(306, 3061, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 4. PERMISOS EN PERFILES DE EMPRESA 2 (Casa Lucho - Cabecera)
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
VALUES
(301, 3050, 1),
(301, 3055, 1),
(301, 3056, 1),
(301, 3060, 1),
(301, 3061, 1),
(304, 3060, 1),
(304, 3061, 1),
(306, 3060, 1),
(306, 3061, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 5. PERMISOS EN PERFILES DE EMPRESA 3
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
VALUES
(309, 3060, 1), -- Resp. Empresa
(309, 3061, 1),
(311, 3060, 1), -- Aprobador
(311, 3061, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- =====================================================================
-- 6. SEMILLAS DE CATÁLOGOS Y OPCIONES (M2)
-- =====================================================================

-- 6.1 ml__ambitos_tipos
INSERT INTO ml__ambitos_tipos (ambito_tipo_id, codigo, nombre, creado_en)
VALUES
(1, 'GRUPO', 'Todo el Grupo Corporativo', NOW()),
(2, 'REGION_GRUPO', 'Región de Gestión Corporativa', NOW()),
(3, 'EMPRESA', 'Empresa Específica', NOW()),
(4, 'TERRITORIO', 'Jurisdicción / Territorio Geográfico', NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- 6.2 ml__raci_roles
INSERT INTO ml__raci_roles (raci_rol_id, codigo, nombre, creado_en)
VALUES
(1, 'R', 'Responsable (Quien ejecuta la tarea)', NOW()),
(2, 'A', 'Accountable / Aprobador (Quien responde y aprueba)', NOW()),
(3, 'C', 'Consultado (Aporte técnico o legal previo)', NOW()),
(4, 'I', 'Informado (Notificado del resultado)', NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- 6.3 ml__opciones (Las 10 opciones de la Sección 16)
INSERT INTO ml__opciones (opcion_id, codigo, nombre, nivel, creado_en)
VALUES
(1, 'SOLAPAMIENTOS', 'Criterio ante Solapamiento de Normas', 'GRUPO', NOW()),
(2, 'CAMBIO_MITAD_PERIODO', 'Tratamiento de Cambio Normativo a Mitad de Período', 'GRUPO', NOW()),
(3, 'DEROGACION_CICLOS_ABIERTOS', 'Tratamiento de Derogación con Ciclos Abiertos', 'GRUPO', NOW()),
(4, 'APROBACION_EXCEPCIONES', 'Reparto de Aprobación de Excepciones Legales', 'GRUPO', NOW()),
(5, 'REVISION_TAREA', 'Exigencia de Revisión Técnica de Tareas', 'GRUPO', NOW()),
(6, 'EVIDENCIA_OBLIGATORIA', 'Exigencia de Evidencia Documental Adjunta', 'GRUPO', NOW()),
(7, 'APROBACION_CAMBIOS_MASIVOS', 'Aprobación Previa de Cambios Masivos', 'GRUPO', NOW()),
(8, 'PONDERACION_INDICADOR', 'Ponderación del Indicador de Cumplimiento', 'GRUPO', NOW()),
(9, 'AUDITORIA_CONFIDENCIALES', 'Acceso Central de Auditoría a Documentos Confidenciales', 'GRUPO', NOW()),
(10, 'COMPUTO_PLAZOS_CORTOS', 'Cómputo de Plazos Menores a 15 Días', 'GRUPO', NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- 6.4 ml__opciones_valores
INSERT INTO ml__opciones_valores (opcion_valor_id, opcion_id, codigo, nombre, es_defecto, creado_en)
VALUES
-- Opción 1: Solapamientos
(101, 1, 'MAS_ESTRICTA', 'La exigencia más estricta prevalece', 1, NOW()),
(102, 1, 'MAYOR_JERARQUIA', 'La norma de mayor jerarquía formal prevalece', 0, NOW()),

-- Opción 2: Cambio a mitad de período
(201, 2, 'CERRAR_Y_ABRIR', 'Cerrar ciclo actual y abrir nuevo con reglas actualizadas', 1, NOW()),
(202, 2, 'COMPLETAR_VIEJAS', 'Completar con reglas vigentes y aplicar nuevas al próximo ciclo', 0, NOW()),

-- Opción 3: Derogación con ciclos abiertos
(301, 3, 'EXIMIR_ABIERTOS', 'Cerrar automáticamente los ciclos abiertos como Eximidos', 1, NOW()),
(302, 3, 'COMPLETAR_OBLIGACION', 'Exigir completar ciclos abiertos antes de finalizar la obligación', 0, NOW()),

-- Opción 4: Aprobación de excepciones
(401, 4, 'MATRIZ_Y_LEGAL', 'Políticas: Matriz; Normas legales: Fundamento técnico y Legal', 1, NOW()),
(402, 4, 'DESCENTRALIZADO', 'Reparto descentralizado según país y tipo de activo', 0, NOW()),

-- Opción 5: Revisión de tareas
(501, 5, 'OBLIGATORIA', 'Revisión por supervisor obligatoria para dar por cumplida', 1, NOW()),
(502, 5, 'OPCIONAL', 'Revisión opcional (pasa directo de En curso a Cumplida)', 0, NOW()),

-- Opción 6: Evidencias
(601, 6, 'OBLIGATORIA', 'Carga de evidencia documental adjunta obligatoria', 1, NOW()),
(602, 6, 'OPCIONAL', 'Evidencia opcional según criterio del responsable', 0, NOW()),

-- Opción 7: Cambios masivos
(701, 7, 'APROBACION_PREVIA', 'Aprobación previa obligatoria antes de impactar el cálculo', 1, NOW()),
(702, 7, 'INFORMATIVA', 'Aplicación directa con vista previa informativa', 0, NOW()),

-- Opción 8: Ponderación de indicador
(801, 8, 'SUMA_LINEAL', 'Conteo lineal de ciclos cumplidos sobre totales', 1, NOW()),
(802, 8, 'POR_CRITICIDAD', 'Ponderación matemática según peso de criticidad de la obligación', 0, NOW()),

-- Opción 9: Auditoría confidenciales
(901, 9, 'AUDITORIA_REGISTRADA', 'Acceso para matriz regional con registro en bitácora de auditoría', 1, NOW()),
(902, 9, 'SIN_ACCESO_CENTRAL', 'Sin acceso central (restringido estrictamente al ámbito local)', 0, NOW()),

-- Opción 10: Cómputo de plazos cortos
(1001, 10, 'DIAS_HABILES', 'Días hábiles según calendario de feriados del territorio', 1, NOW()),
(1002, 10, 'DIAS_CORRIDOS', 'Días corridos calendario continuo', 0, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), es_defecto = VALUES(es_defecto);

-- 6.5 ml__configuracion_grupo (Valores por defecto para Grupo 1)
INSERT INTO ml__configuracion_grupo (grupo_id, opcion_id, opcion_valor_id, vigente_desde, creado_en)
VALUES
(1, 1, 101, '2020-01-01', NOW()),
(1, 2, 201, '2020-01-01', NOW()),
(1, 3, 301, '2020-01-01', NOW()),
(1, 4, 401, '2020-01-01', NOW()),
(1, 5, 501, '2020-01-01', NOW()),
(1, 6, 601, '2020-01-01', NOW()),
(1, 7, 701, '2020-01-01', NOW()),
(1, 8, 801, '2020-01-01', NOW()),
(1, 9, 901, '2020-01-01', NOW()),
(1, 10, 1001, '2020-01-01', NOW())
ON DUPLICATE KEY UPDATE opcion_valor_id = VALUES(opcion_valor_id);

COMMIT;
