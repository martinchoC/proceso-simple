-- =====================================================================
-- Matriz Legal - M8 (Cargas y Cambios Masivos)
-- Script 01: Semillas de Tipos, Páginas, Funciones y Permisos
-- 2026-10-03
-- =====================================================================

SET NAMES utf8mb4;

-- 1. TIPOS DE CARGAS MASIVAS (ml__cargas_tipos)
INSERT INTO ml__cargas_tipos (carga_tipo_id, codigo, nombre)
VALUES
(1, 'NORMAS', 'Normas Legales y Corporativas'),
(2, 'OBLIGACIONES', 'Obligaciones y Requisitos de Cumplimiento'),
(3, 'ENTIDADES', 'Entidades Sujetas a Control (Sedes / Establecimientos)')
ON DUPLICATE KEY UPDATE 
    codigo = VALUES(codigo),
    nombre = VALUES(nombre);

-- 2. PÁGINAS DEL MÓDULO 3 (Agrupador 361: Cargas y Cambios Masivos)
INSERT INTO conf__paginas 
(pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
VALUES
-- Agrupador en menú
(361, 3, 'Cargas y Cambios Masivos', '#', 17, 'Herramientas de importación masiva y gestión controlada de impacto normativo', 0, 60, 1, 1, 1, 0),
-- Páginas operativas
(362, 3, 'Cargas Masivas', 'cargas_masivas.php', 25, 'Importador de normas, obligaciones y entidades con validación y rollback (C36)', 361, 10, 1, 1, 1, 0),
(363, 3, 'Cambios Masivos e Impacto', 'cambios_masivos.php', 26, 'Simulación de impacto, aprobación previa y recálculo en cascada (C35)', 361, 20, 1, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    pagina = VALUES(pagina),
    url = VALUES(url),
    icono_id = VALUES(icono_id),
    pagina_descripcion = VALUES(pagina_descripcion),
    padre_id = VALUES(padre_id),
    orden = VALUES(orden),
    tabla_id = VALUES(tabla_id);

-- 3. FUNCIONES DE PÁGINA (Bloque 3261 a 3290)
INSERT INTO conf__paginas_funciones
(pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES
-- Página 362: Cargas Masivas
(3261, 362, 1, 1, 1, 'Consultar Cargas', 'consultar_cargas', 'Ver historial de lotes de importación', 0, 1, 10, 1),
(3262, 362, 1, 1, 1, 'Subir y Validar Carga', 'subir_validar_carga', 'Parsear planilla CSV y validar registros', 0, 1, 20, 1),
(3263, 362, 1, 1, 1, 'Confirmar Carga', 'confirmar_carga', 'Confirmar inserción transaccional atómica', 1, 2, 30, 1),
(3264, 362, 1, 1, 1, 'Descargar Plantilla', 'descargar_plantilla', 'Descargar archivo modelo de datos', 0, 1, 40, 1),

-- Página 363: Cambios Masivos e Impacto
(3271, 363, 1, 1, 1, 'Consultar Cambios Masivos', 'consultar_cambios_masivos', 'Ver solicitudes de cambio normativo masivo', 0, 1, 10, 1),
(3272, 363, 1, 1, 1, 'Simular Impacto', 'simular_impacto', 'Calcular entidades, ciclos y tareas impactados', 0, 1, 20, 1),
(3273, 363, 1, 1, 1, 'Aprobar Cambio Masivo', 'aprobar_cambio', 'Aprobar formalmente el cambio propuesto (C35)', 1, 2, 30, 1),
(3274, 363, 1, 1, 1, 'Aplicar Cambio Masivo', 'aplicar_cambio', 'Ejecutar recálculo y propagación en cascada', 2, 3, 40, 1)
ON DUPLICATE KEY UPDATE 
    nombre_funcion = VALUES(nombre_funcion),
    accion_js = VALUES(accion_js),
    descripcion = VALUES(descripcion),
    orden = VALUES(orden);

-- 4. PERMISOS PARA PERFILES BASE (301 a 305)
-- 301 Administrador de Matriz (acceso total a funciones de M8)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 301, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3261 AND 3290;

-- 302 Gestor Normativo (cargas masivas y simulación de impacto)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 302, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3261, 3262, 3263, 3264, 3271, 3272);

-- 303 Área Legal (aprobación de cambios normativos y consulta)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 303, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3261, 3264, 3271, 3272, 3273, 3274);

-- 304 Responsable de Empresa (acceso a cargas masivas de su empresa)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 304, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3261, 3262, 3263, 3264, 3271);

-- 5. COPIAR PERMISOS A EMPRESAS 2 Y 3 (conf__empresas_perfiles_funciones)
INSERT IGNORE INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id
FROM conf__empresas_perfiles ep
INNER JOIN conf__perfiles_funciones pf ON pf.perfil_id = ep.perfil_id_base
WHERE ep.empresa_id IN (2, 3) 
  AND pf.pagina_funcion_id BETWEEN 3261 AND 3290;
