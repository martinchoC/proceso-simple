-- Mostrador minorista (doc 29, paso 10-1): indicador por empresa, página 150 / 650 bajo Ventas, funciones y permisos.
-- =====================================================================================================
-- 20261124_mostrador_01.sql
-- Diseño en _local/docs/gestion/29_mostrador_minorista.md (aprobado el 2026-11-16).
--
--   1. conf__empresas.usa_mostrador (0/1, por defecto 0). Apagado, la página no se puede usar.
--   2. Página 150 (gestion) / 650 (gestion_normal) "Mostrador", url mostrador.php, bajo Ventas (48 / 548), sin motor
--      de estados (tabla_id 0: la factura que emite es una factura de venta común de la página 56 / 556), requiere_bienes.
--   3. Funciones 3620 / 3621 y 8620 / 8621: consultar, facturar.
--   4. Permisos: los perfiles base Administrador 201 / 501 y los perfiles de cada empresa que tienen "agregar" en
--      Facturas de venta (56 / 556) de ese módulo.
-- Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_mostrador');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_mostrador TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = pantalla de mostrador minorista (doc 29)''',
    'SELECT ''conf__empresas.usa_mostrador ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

START TRANSACTION;

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 150, 2, 'Mostrador', 'mostrador.php', 75, 'Venta rápida de mostrador: lector de códigos, cajas y unidades, cobro y factura en el momento',
       48, 460, 0, 1, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 150)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 48);

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 650, 5, 'Mostrador', 'mostrador.php', 75, 'Venta rápida de mostrador: lector de códigos, cajas y unidades, cobro y factura en el momento',
       548, 460, 0, 1, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 650)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 548);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina, 75, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (SELECT 3620 AS id, 150 AS pagina, 'Consultar' AS nombre, 'consultar' AS accion, '' AS descr, 1 AS orden
      UNION ALL SELECT 3621, 150, 'Facturar', 'facturar', 'Cobrar y emitir la factura de mostrador', 2
      UNION ALL SELECT 8620, 650, 'Consultar', 'consultar', '', 1
      UNION ALL SELECT 8621, 650, 'Facturar', 'facturar', 'Cobrar y emitir la factura de mostrador', 2) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

DROP TEMPORARY TABLE IF EXISTS tmp_vmos_func;
CREATE TEMPORARY TABLE tmp_vmos_func (modulo_id INT NOT NULL, funcion_id INT NOT NULL);
INSERT INTO tmp_vmos_func VALUES (2, 3620), (2, 3621), (5, 8620), (5, 8621);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT v.perfil_id, f.funcion_id, 1
FROM (SELECT 201 AS perfil_id, 2 AS modulo_id UNION ALL SELECT 501, 5) v
JOIN tmp_vmos_func f ON f.modulo_id = v.modulo_id
WHERE EXISTS (SELECT 1 FROM conf__perfiles p WHERE p.perfil_id = v.perfil_id)
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = v.perfil_id AND x.pagina_funcion_id = f.funcion_id);

-- Perfiles de cada empresa que pueden agregar facturas de venta en ese módulo
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, f.funcion_id, 1
FROM conf__empresas_perfiles ep
JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id AND epf.asignado = 1
JOIN conf__paginas_funciones vf ON vf.pagina_funcion_id = epf.pagina_funcion_id AND vf.pagina_id IN (56, 556) AND vf.accion_js = 'agregar'
JOIN conf__paginas pv ON pv.pagina_id = vf.pagina_id
JOIN tmp_vmos_func f ON f.modulo_id = pv.modulo_id
WHERE ep.tabla_estado_registro_id = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = f.funcion_id);

DROP TEMPORARY TABLE IF EXISTS tmp_vmos_func;
COMMIT;
