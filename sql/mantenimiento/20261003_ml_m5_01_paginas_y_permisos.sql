-- =====================================================================
-- Matriz Legal - M5 (Motor de Herencia y Solapamientos)
-- Script 01: Páginas, Funciones y Permisos
-- 2026-10-03
-- =====================================================================

SET NAMES utf8mb4;

-- 1. PÁGINAS DEL MÓDULO 3 (Agrupador 331: Motor y Asignaciones)
INSERT INTO conf__paginas 
(pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
VALUES
-- Agrupador en menú
(331, 3, 'Motor y Asignaciones', '#', 15, 'Motor de herencia y asignación de obligaciones', 0, 40, 248, 1, 1, 0),
-- Páginas operativas
(332, 3, 'Obligaciones Asignadas', 'obligaciones_asignadas.php', 26, 'Matriz de obligaciones heredadas y orígenes', 331, 10, 248, 1, 1, 0),
(333, 3, 'Excepciones de Cumplimiento', 'excepciones.php', 25, 'Solicitudes y aprobaciones de excepción', 331, 20, 250, 3, 1, 0),
(334, 3, 'Motor de Herencia y Recálculos', 'recalculos_motor.php', 30, 'Simulación y ejecución del motor de cálculo', 331, 30, 251, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    pagina = VALUES(pagina),
    url = VALUES(url),
    icono_id = VALUES(icono_id),
    pagina_descripcion = VALUES(pagina_descripcion),
    padre_id = VALUES(padre_id),
    orden = VALUES(orden),
    tabla_id = VALUES(tabla_id);

-- 2. FUNCIONES DE PÁGINA (conf__paginas_funciones: Bloque 3151 a 3180)
INSERT INTO conf__paginas_funciones
(pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES
-- Página 332: Obligaciones Asignadas
(3151, 332, 1, 1, 1, 'Consultar Obligaciones Asignadas', 'consultar_obligaciones_asignadas', 'Ver matriz de obligaciones asignadas', 0, 1, 10, 1),
(3152, 332, 1, 1, 1, 'Desactivar Asignación', 'desactivar_obligacion_asignada', 'Suspender o dar de baja asignación', 1, 2, 20, 1),

-- Página 333: Excepciones de Cumplimiento (Proceso largo 2087 -> 2088 -> 2089 -> 2090/2091)
(3161, 333, 1, 1, 1, 'Solicitar Excepción', 'solicitar_excepcion', 'Registrar solicitud de excepción temporal', 0, 2087, 10, 1),
(3162, 333, 1, 2, 2, 'Escalar a Aprobación Legal', 'enviar_aprobacion_legal_excepcion', 'Enviar excepción a dictamen del área legal', 2087, 2088, 20, 1),
(3163, 333, 1, 3, 2, 'Aprobar Excepción', 'aprobar_excepcion', 'Confirmar y otorgar excepción temporal', 2088, 2089, 30, 1),
(3164, 333, 1, 4, 3, 'Rechazar Excepción', 'rechazar_excepcion', 'Denegar solicitud de excepción', 2088, 2090, 40, 1),
(3165, 333, 1, 5, 3, 'Revocar Excepción', 'revocar_excepcion', 'Finalizar anticipadamente una excepción vigente', 2089, 2091, 50, 1),

-- Página 334: Motor de Herencia y Recálculos
(3171, 334, 1, 1, 1, 'Ejecutar Recálculo Motor', 'ejecutar_recalculo_motor', 'Ejecutar herencia y asignación de obligaciones', 0, 1, 10, 1),
(3172, 334, 1, 1, 1, 'Simular Impacto Motor', 'simular_impacto_motor', 'Vista previa de entidades y tareas afectadas (C35)', 0, 1, 20, 1),
(3173, 334, 1, 1, 1, 'Procesar Cola Recálculos', 'procesar_cola_recalculos', 'Procesar lote de cola asíncrona', 2092, 2093, 30, 1)
ON DUPLICATE KEY UPDATE 
    nombre_funcion = VALUES(nombre_funcion),
    accion_js = VALUES(accion_js),
    descripcion = VALUES(descripcion);

-- 3. PERMISOS EN PERFILES BASE
-- 301 (Admin Matriz): Total
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 302 (Gestor Normativo): Consulta, excepciones y ejecución de motor
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(302, 3151, 1), (302, 3161, 1), (302, 3162, 1), (302, 3171, 1), (302, 3172, 1), (302, 3173, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 303 (Área Legal): Excepciones (aprobación/rechazo C09) y consulta
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(303, 3151, 1), (303, 3161, 1), (303, 3162, 1), (303, 3163, 1), (303, 3164, 1), (303, 3165, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 304 (Responsable Operativo / Planta): Consulta de obligaciones asignadas y solicitar excepción
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(304, 3151, 1), (304, 3161, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 305 (Responsable de Tareas): Consulta de obligaciones asignadas
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(305, 3151, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 4. PERMISOS EN PERFILES DE EMPRESA 2
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 302, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 302 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 303, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 303 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 304, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 304 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 305, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 305 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 5. PERMISOS EN PERFILES DE EMPRESA 3 (Mismo mapeo para empresa 3: IDs 306 a 310)
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 306, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 307, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 302 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 308, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 303 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 309, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 304 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 310, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 305 AND pf.pagina_funcion_id BETWEEN 3151 AND 3173
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);
