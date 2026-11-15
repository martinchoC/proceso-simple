-- Página "Escalas y promociones" (146 gestion / 646 gestion_normal, menú Ventas): funciones y permisos copiados de Listas de precios.
-- =====================================================================================================
-- 20261117_reglas_precio_venta_02_pagina.sql
-- Diseño en _local/docs/gestion/22_escalas_promociones.md (paso 4b). Requiere 20261117_reglas_precio_venta_01.sql.
--
-- precios_reglas_venta.php: escalas por cantidad y promociones (alta / edición con tramos, activar / inactivar,
-- vista previa del descuento) y la solapa "Topes por perfil" (descuento manual máximo de cada perfil). Sin motor de
-- estados (tabla_id 0): los botones llevan data-funcion y el _ajax.php exige la función con permisos_exigir_accion.
-- Lógica en core/precios_venta_abm.php, vista en templates/precios_reglas_venta/, JS en assets/js/.
--
--   1. Página 146 / 646 bajo Ventas (48 / 548).
--   2. Funciones 3595–3598 / 8595–8598: consultar, agregar, editar, inhabilitar.
--   3. Permisos: los perfiles con Listas de precios (página 53 / 553) reciben la función equivalente
--      (visualizar → consultar, agregar, editar, inactivar → inhabilitar), en conf__perfiles_funciones y en
--      conf__empresas_perfiles_funciones.
-- Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- 1. Páginas
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 146, 2, 'Escalas y promociones', 'precios_reglas_venta.php', 93,
       'Descuentos por cantidad y promociones con vigencia que se aplican al vender, y tope de descuento manual por perfil',
       48, 440, 0, 0, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 146)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 48);

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 646, 5, 'Escalas y promociones', 'precios_reglas_venta.php', 93,
       'Descuentos por cantidad y promociones con vigencia que se aplican al vender, y tope de descuento manual por perfil',
       548, 440, 0, 0, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 646)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 548);

-- 2. Funciones
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina, 93, 1, 1, v.nombre, v.accion, '', 0, 0, v.orden, 1
FROM (SELECT 3595 AS id, 146 AS pagina, 'Consultar' AS nombre, 'consultar' AS accion, 1 AS orden
      UNION ALL SELECT 3596, 146, 'Agregar', 'agregar', 2
      UNION ALL SELECT 3597, 146, 'Editar', 'editar', 3
      UNION ALL SELECT 3598, 146, 'Inhabilitar', 'inhabilitar', 4
      UNION ALL SELECT 8595, 646, 'Consultar', 'consultar', 1
      UNION ALL SELECT 8596, 646, 'Agregar', 'agregar', 2
      UNION ALL SELECT 8597, 646, 'Editar', 'editar', 3
      UNION ALL SELECT 8598, 646, 'Inhabilitar', 'inhabilitar', 4) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

-- 3. Permisos: equivalencia con Listas de precios (313 agregar, 314 visualizar, 315 editar, 316 inactivar; módulo 5 + 5000)
DROP TEMPORARY TABLE IF EXISTS tmp_prv_mapa;
CREATE TEMPORARY TABLE tmp_prv_mapa (origen INT NOT NULL, destino INT NOT NULL);
INSERT INTO tmp_prv_mapa VALUES (314, 3595), (313, 3596), (315, 3597), (316, 3598),
                                (5314, 8595), (5313, 8596), (5315, 8597), (5316, 8598);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pf.perfil_id, m.destino, 1
FROM conf__perfiles_funciones pf
JOIN tmp_prv_mapa m ON m.origen = pf.pagina_funcion_id
WHERE pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.destino)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = m.destino);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, m.destino, 1
FROM conf__empresas_perfiles_funciones epf
JOIN tmp_prv_mapa m ON m.origen = epf.pagina_funcion_id
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
WHERE epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.destino)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = m.destino);

DROP TEMPORARY TABLE IF EXISTS tmp_prv_mapa;
COMMIT;

-- Control (solo lectura)
SELECT p.pagina_id, p.pagina, p.padre_id, f.pagina_funcion_id, f.accion_js,
       (SELECT COUNT(*) FROM conf__perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_base,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_empresa
FROM conf__paginas p
LEFT JOIN conf__paginas_funciones f ON f.pagina_id = p.pagina_id
WHERE p.pagina_id IN (146, 646)
ORDER BY p.pagina_id, f.orden;
