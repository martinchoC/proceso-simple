-- =====================================================================
-- Matriz Legal - M4: PÁGINAS, FUNCIONES, PERMISOS Y SEMILLAS DE OBLIGACIONES
-- Archivo: sql/mantenimiento/20261003_ml_m4_01_paginas_y_permisos.sql
-- =====================================================================

START TRANSACTION;

-- 1. PÁGINAS DEL MÓDULO 3 (Agrupador 321: Obligaciones y Tareas)
INSERT INTO conf__paginas 
(pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
VALUES
(321, 3, 'Obligaciones y Exigencias', '#', 13, 'Transformación de normas en obligaciones operativas', 0, 35, 241, 1, 1, 0),
(322, 3, 'Reglas y Periodicidades', 'obligaciones_reglas.php', 9, 'Criticidades, cadencias y reglas de vencimiento', 321, 10, 239, 1, 1, 0),
(323, 3, 'Matriz de Obligaciones', 'obligaciones.php', 13, 'Catálogo de obligaciones, aplicabilidad y dependencias', 321, 20, 241, 2, 1, 0),
(324, 3, 'Plantillas de Tareas', 'tareas_plantillas.php', 26, 'Plantillas de tareas operativas y secuencias', 321, 30, 246, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    pagina = VALUES(pagina),
    url = VALUES(url),
    icono_id = VALUES(icono_id),
    pagina_descripcion = VALUES(pagina_descripcion),
    padre_id = VALUES(padre_id),
    orden = VALUES(orden),
    tabla_id = VALUES(tabla_id);

-- 2. FUNCIONES DE PÁGINA (conf__paginas_funciones: Bloque 3120-3150)
INSERT INTO conf__paginas_funciones 
(pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES
-- Página 322: Reglas y Periodicidades
(3121, 322, 1, 1, 1, 'Guardar Criticidad', 'guardar_criticidad', 'Crear o modificar nivel de criticidad y peso', 0, 1, 10, 1),
(3122, 322, 1, 1, 1, 'Guardar Periodicidad', 'guardar_periodicidad', 'Crear o modificar cadencia de repetición', 0, 1, 20, 1),
(3123, 322, 1, 1, 1, 'Guardar Regla Vencimiento', 'guardar_regla_vencimiento', 'Crear o modificar fórmula de vencimiento', 0, 1, 30, 1),

-- Página 323: Matriz de Obligaciones (Proceso Corto 2061-2064)
(3130, 323, 1, 1, 1, 'Crear Obligación', 'crear_obligacion', 'Registrar nueva obligación en borrador', 0, 2061, 10, 1),
(3131, 323, 1, 2, 2, 'Enviar a Revisión', 'enviar_revision_obligacion', 'Enviar obligación a control técnico', 2061, 2062, 20, 1),
(3132, 323, 1, 3, 2, 'Aprobar Obligación', 'aprobar_obligacion', 'Confirmar y activar obligación', 2062, 2063, 30, 1),
(3133, 323, 1, 4, 3, 'Suspender Obligación', 'suspender_obligacion', 'Dar de baja o suspender exigencia', 2063, 2064, 40, 1),
(3135, 323, 1, 1, 1, 'Asignar Tipo Entidad', 'asignar_tipo_entidad', 'Vincular a tipo de entidad y regla condicional', 0, 1, 50, 1),
(3136, 323, 1, 1, 1, 'Vincular Dependencia', 'vincular_dependencia_obligacion', 'Establecer condición previa obligatoria', 0, 1, 60, 1),
(3137, 323, 1, 1, 1, 'Configurar Alerta', 'configurar_alerta', 'Definir aviso previo por canal y días', 0, 1, 70, 1),

-- Página 324: Plantillas de Tareas
(3140, 324, 1, 1, 1, 'Guardar Plantilla Tarea', 'guardar_tarea_plantilla', 'Crear o modificar plantilla de tarea', 0, 1, 10, 1),
(3141, 324, 1, 1, 1, 'Vincular Dependencia Tarea', 'vincular_dependencia_tarea', 'Secuenciar predecesora y sucesora', 0, 1, 20, 1)
ON DUPLICATE KEY UPDATE 
    nombre_funcion = VALUES(nombre_funcion),
    accion_js = VALUES(accion_js),
    descripcion = VALUES(descripcion);

-- 3. PERMISOS EN PERFILES BASE
-- 301 (Admin Matriz): Total
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3121 AND 3141
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 302 (Gestor Normativo): Configuración y carga
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(302, 3121, 1), (302, 3122, 1), (302, 3123, 1), (302, 3130, 1), (302, 3131, 1),
(302, 3135, 1), (302, 3136, 1), (302, 3137, 1), (302, 3140, 1), (302, 3141, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 303 (Área Legal): Aprobación de obligaciones
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(303, 3130, 1), (303, 3131, 1), (303, 3132, 1), (303, 3133, 1), (303, 3136, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 4. PERMISOS EN PERFILES DE EMPRESA 2
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3121 AND 3141
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 302, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 302 AND pf.pagina_funcion_id BETWEEN 3121 AND 3141
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 303, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 303 AND pf.pagina_funcion_id BETWEEN 3121 AND 3141
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 5. SEMILLAS DE CATÁLOGOS (M4)

-- 5.1 ml__criticidades
INSERT INTO ml__criticidades (criticidad_id, nombre, peso, tabla_estado_registro_id, creado_en)
VALUES
(1, 'Baja', 1, 1, NOW()),
(2, 'Media', 2, 1, NOW()),
(3, 'Alta', 5, 1, NOW()),
(4, 'Crítica / Mandatoria', 10, 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), peso = VALUES(peso);

-- 5.2 ml__periodicidades (Casos C07, C26)
INSERT INTO ml__periodicidades (periodicidad_id, codigo, nombre, intervalo_cantidad, intervalo_unidad, es_evento, fecha_fija_autoridad, tabla_estado_registro_id, creado_en)
VALUES
(1, 'MENSUAL', 'Mensual (cada mes)', 1, 'MONTH', 0, 0, 1, NOW()),
(2, 'TRIMESTRAL', 'Trimestral (cada 3 meses)', 3, 'MONTH', 0, 0, 1, NOW()),
(3, 'SEMESTRAL', 'Semestral (cada 6 meses)', 6, 'MONTH', 0, 0, 1, NOW()),
(4, 'ANUAL', 'Anual (cada 12 meses)', 1, 'YEAR', 0, 0, 1, NOW()),
(5, 'QUINQUENAL', 'Plurianual - Cada 5 años', 5, 'YEAR', 0, 0, 1, NOW()),
(6, 'EVENTO', 'Por Evento / Incidente (medido en horas)', NULL, 'HOUR', 1, 0, 1, NOW()),
(7, 'AUTORIDAD', 'Aviso o Inspección de Autoridad', NULL, NULL, 0, 1, 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), intervalo_cantidad = VALUES(intervalo_cantidad), intervalo_unidad = VALUES(intervalo_unidad), es_evento = VALUES(es_evento), fecha_fija_autoridad = VALUES(fecha_fija_autoridad);

-- 5.3 ml__vencimientos_reglas (Casos C07, C22, C23)
INSERT INTO ml__vencimientos_reglas (vencimiento_regla_id, codigo, nombre, tipo, desplazamiento_dias, tabla_estado_registro_id, creado_en)
VALUES
(1, 'DIA_FIJO_MES', 'Día fijo del período (ej: día 10 o 15)', 'CALENDARIO', 0, 1, NOW()),
(2, 'FIN_MES', 'Último día hábil del período', 'CALENDARIO', 0, 1, NOW()),
(3, 'CUIT_TERMINACION', 'Según último dígito del CUIT/NIF de la empresa', 'DATO_EMPRESA', 0, 1, NOW()),
(4, 'FECHA_PROPIA_PERMISO', 'Fecha de vencimiento de permiso o certificado propio', 'DATO_ENTIDAD', -30, 1, NOW()),
(5, 'HORAS_DESDE_EVENTO', 'Horas corridas contadas desde el registro del evento', 'EVENTO', 0, 1, NOW()),
(6, 'DIAS_CORRIDOS_PUBLICACION', 'Días corridos contados desde la vigencia inicial', 'OFFSET', 30, 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), tipo = VALUES(tipo), desplazamiento_dias = VALUES(desplazamiento_dias);

COMMIT;
