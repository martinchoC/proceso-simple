-- ==============================================================================
-- 20261012_sindicatos_15_pagina_importaciones.sql
-- Módulo 6 (Sindicatos): página 1021 "Importación F.931 / Planilla" (ddjj_importar.php).
--
-- La página estaba en el menú sin archivos y colgada de la página de DDJJ (1020) en lugar
-- del menú "Gestión de Aportes" (1000). Cada importación queda registrada en
-- sindi__ddjj_importaciones (tabla 525) con el archivo, las filas leídas/aceptadas/rechazadas,
-- el log de errores y la DDJJ borrador que generó.
--
-- 1. Página bajo el menú 1000.
-- 2. Estados de la tabla 525: 5 Procesada (generó la DDJJ), 27 Con errores (ninguna fila válida).
-- 3. Funciones: Importar archivo (agregar, 0 -> 5) y Ver detalle (5 y 27).
-- 4. Permisos: a los perfiles que ya tienen "Acceder" en la página (internos y externos).
--
-- Reanudable.
-- ==============================================================================

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE conf__paginas SET padre_id = 1000, orden = 15 WHERE pagina_id = 1021;

INSERT INTO conf__tablas_estados_registros (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, es_final, orden, aplica_stock)
SELECT t.id, 525, t.estado, t.nombre, t.color, t.inicial, t.final, t.orden, 0
FROM (SELECT 3101 AS id, 5 AS estado, 'Procesada' AS nombre, 3 AS color, 1 AS inicial, 1 AS final, 1 AS orden
      UNION ALL SELECT 3102, 27, 'Con errores', 4, 0, 1, 2) t
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros x WHERE x.tabla_id = 525 AND x.estado_registro_id = t.estado)
  AND NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros y WHERE y.tabla_estado_registro_id = t.id);

INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT t.id, 1021, (SELECT MIN(i.icono_id) FROM conf__iconos i WHERE i.icono_clase = t.icono), t.color, 1, t.nombre, t.accion, NULL,
       t.origen, t.destino, t.orden, 1
FROM (SELECT 16260 AS id, 'Importar archivo' AS nombre, 'agregar' AS accion, 0 AS origen, 5 AS destino, 10 AS orden, 1 AS color, 'bi bi-upload' AS icono
      UNION ALL SELECT 16261, 'Ver detalle', 'ver', 5, 5, 20, 9, 'bi bi-list-check'
      UNION ALL SELECT 16262, 'Ver detalle', 'ver', 27, 27, 21, 9, 'bi bi-list-check') t
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = t.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT epf.empresa_id, epf.empresa_perfil_id, f.pagina_funcion_id, 1
FROM conf__paginas_funciones f
INNER JOIN conf__paginas_funciones acc ON acc.pagina_id = 1021 AND acc.accion_js = 'consultar'
INNER JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = acc.pagina_funcion_id AND epf.asignado = 1
WHERE f.pagina_funcion_id IN (16260, 16261, 16262)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = f.pagina_funcion_id);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfu.perfil_id, f.pagina_funcion_id, 1
FROM conf__paginas_funciones f
INNER JOIN conf__paginas_funciones acc ON acc.pagina_id = 1021 AND acc.accion_js = 'consultar'
INNER JOIN conf__perfiles_funciones pfu ON pfu.pagina_funcion_id = acc.pagina_funcion_id
WHERE f.pagina_funcion_id IN (16260, 16261, 16262)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pfu.perfil_id AND x.pagina_funcion_id = f.pagina_funcion_id);

COMMIT;

SELECT p.pagina_id, p.padre_id, COUNT(DISTINCT pf.pagina_funcion_id) AS funciones, COUNT(DISTINCT epf.empresa_perfil_id) AS perfiles,
       (SELECT COUNT(*) FROM conf__tablas_estados_registros WHERE tabla_id = 525) AS estados
FROM conf__paginas p
LEFT JOIN conf__paginas_funciones pf ON pf.pagina_id = p.pagina_id
LEFT JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = pf.pagina_funcion_id
WHERE p.pagina_id = 1021 GROUP BY p.pagina_id, p.padre_id;
