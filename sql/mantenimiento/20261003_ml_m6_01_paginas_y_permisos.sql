-- =====================================================================
-- Matriz Legal - M6 (Ciclos y Tareas)
-- Script 01: Semillas, Páginas, Funciones y Permisos
-- 2026-10-03
-- =====================================================================

SET NAMES utf8mb4;

-- 1. SEMILLAS DE TIPOS DE VÍNCULOS ENTRE CICLOS (ml__ciclos_vinculos_tipos)
INSERT INTO ml__ciclos_vinculos_tipos (ciclo_vinculo_tipo_id, codigo, nombre)
VALUES
(1, 'COMPLEMENTARIO', 'Ciclo complementario para subsanar error en ciclo cerrado (Caso C28)'),
(2, 'SEGUIMIENTO', 'Ciclo de seguimiento de plan de acción por hallazgo de auditoría (Caso C29)'),
(3, 'RENOVACION', 'Ciclo de renovación anticipada por vencimiento de evidencia/permiso (Caso C30)')
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre);

-- 2. PÁGINAS DEL MÓDULO 3 (Agrupador 341: Ciclos y Tareas)
INSERT INTO conf__paginas 
(pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
VALUES
-- Agrupador en menú
(341, 3, 'Ciclos y Tareas', '#', 17, 'Gestión de ciclos periódicos y tareas operativas', 0, 45, 253, 1, 1, 0),
-- Páginas operativas
(342, 3, 'Calendario y Ciclos', 'ciclos.php', 20, 'Tablero de períodos de cumplimiento y estados', 341, 10, 253, 1, 1, 0),
(343, 3, 'Tareas Operativas', 'ciclos_tareas.php', 26, 'Bandeja de trabajo, dependencias y revisiones', 341, 20, 254, 1, 1, 0),
(344, 3, 'Matriz RACI y Asignaciones', 'ciclos_raci.php', 25, 'Responsables RACI y reasignaciones en bloque', 341, 30, 258, 1, 1, 0),
(345, 3, 'Generador y Eventos', 'ciclos_generador.php', 30, 'Generador a 12 meses y disparador de eventos (C07)', 341, 40, 253, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    pagina = VALUES(pagina),
    url = VALUES(url),
    icono_id = VALUES(icono_id),
    pagina_descripcion = VALUES(pagina_descripcion),
    padre_id = VALUES(padre_id),
    orden = VALUES(orden),
    tabla_id = VALUES(tabla_id);

-- 3. FUNCIONES DE PÁGINA (Bloque 3181 a 3220)
INSERT INTO conf__paginas_funciones
(pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES
-- Página 342: Calendario y Ciclos
(3181, 342, 1, 1, 1, 'Consultar Ciclos', 'consultar_ciclos', 'Ver tablero y calendario de ciclos', 0, 1, 10, 1),
(3182, 342, 1, 1, 1, 'Cerrar Ciclo', 'cerrar_ciclo', 'Cerrar período como cumplido o con retraso', 2070, 2072, 20, 1),
(3183, 342, 1, 1, 1, 'Eximir Ciclo', 'eximir_ciclo', 'Eximir ciclo por excepción o derogación', 2069, 2075, 30, 1),
(3184, 342, 1, 1, 1, 'Cancelar Ciclo', 'cancelar_ciclo', 'Cancelar ciclo por baja o salida de empresa', 2069, 2076, 40, 1),
(3185, 342, 1, 1, 1, 'Crear Ciclo Complementario', 'crear_ciclo_complementario', 'Crear ciclo de subsanación de error (C28)', 2072, 2069, 50, 1),
(3186, 342, 1, 1, 1, 'Crear Ciclo Seguimiento', 'crear_ciclo_seguimiento', 'Crear ciclo de plan de acción de auditoría (C29)', 2072, 2069, 60, 1),

-- Página 343: Tareas Operativas
(3191, 343, 1, 1, 1, 'Iniciar Tarea', 'iniciar_tarea', 'Comenzar ejecución de la tarea', 2078, 2079, 10, 1),
(3192, 343, 1, 1, 1, 'Enviar Tarea a Revisión', 'enviar_revision_tarea', 'Enviar tarea finalizada a control técnico', 2079, 2080, 20, 1),
(3193, 343, 1, 1, 1, 'Cumplir Tarea', 'cumplir_tarea', 'Validar y dar por cumplida la tarea', 2080, 2081, 30, 1),
(3194, 343, 1, 1, 1, 'Rechazar Tarea', 'rechazar_tarea', 'Observar o solicitar corrección en tarea', 2080, 2082, 40, 1),
(3195, 343, 1, 1, 1, 'Cancelar Tarea', 'cancelar_tarea', 'Dar de baja tarea del ciclo', 2078, 2083, 50, 1),

-- Página 344: Matriz RACI y Asignaciones
(3201, 344, 1, 1, 1, 'Guardar Asignación RACI', 'guardar_raci', 'Asignar rol R, A, C o I', 0, 1, 10, 1),
(3202, 344, 1, 1, 1, 'Reasignar Responsable Bloque', 'reasignar_responsable_bloque', 'Traspaso de tareas por salida de usuario (C27)', 0, 1, 20, 1),

-- Página 345: Generador y Eventos
(3211, 345, 1, 1, 1, 'Generar Ciclos Período', 'generar_ciclos_periodo', 'Generar ciclos periódicos a 12 meses móvil', 0, 1, 10, 1),
(3212, 345, 1, 1, 1, 'Disparar Ciclo Evento', 'disparar_ciclo_evento', 'Registrar evento y crear ciclo con plazo (C07)', 0, 1, 20, 1)
ON DUPLICATE KEY UPDATE 
    nombre_funcion = VALUES(nombre_funcion),
    accion_js = VALUES(accion_js),
    descripcion = VALUES(descripcion);

-- 4. PERMISOS EN PERFILES BASE
-- 301 (Admin Matriz): Total
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 302 (Gestor Normativo): Gestión y supervisión
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT 302, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 303 (Área Legal): Cierre, excepciones y supervisión
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(303, 3181, 1), (303, 3182, 1), (303, 3183, 1), (303, 3184, 1), (303, 3185, 1), (303, 3186, 1),
(303, 3193, 1), (303, 3194, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 304 (Responsable Operativo / Planta): Ejecución y disparo de eventos
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(304, 3181, 1), (304, 3191, 1), (304, 3192, 1), (304, 3201, 1), (304, 3212, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 305 (Responsable de Tareas): Ejecución de tareas operativas
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(305, 3181, 1), (305, 3191, 1), (305, 3192, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 5. PERMISOS EN PERFILES DE EMPRESA 2
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 302, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 302 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 303, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 303 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 304, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 304 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 305, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 305 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 6. PERMISOS EN PERFILES DE EMPRESA 3 (IDs 306 a 310)
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 306, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 307, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 302 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 308, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 303 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 309, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 304 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 310, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 305 AND pf.pagina_funcion_id BETWEEN 3181 AND 3212
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);
