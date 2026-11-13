-- -----------------------------------------------------------------------------------------------------------------
-- 20261111_contratos_y_certificaciones_02_ids_estados.sql
-- Contratos y certificaciones de avance (doc _local/docs/gestion/16_contratos_y_certificaciones_avance.md), paso 2.
--
-- 1. Las bases que corrieron la primera versión del _01 registraron la tabla como 545 (bloque de sindicatos, el mismo
--    choque que corrigió 20261014_ids_bloques_01_renumerar.sql): pasa a 458, la siguiente libre del bloque de gestión.
-- 2. Estado inicial = 3 Borrador (id genérico; la primera versión usaba 1). Facturado = 12 (usaba 40, Cancelado total).
-- 3. Catálogo de estados de la tabla 458 en conf__tablas_estados_registros (ids 262–267, bloque < 2000).
--
-- Correr después del _01. Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0).
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Renumerar 545 -> 458 (solo si la 545 es la de certificaciones y la 458 está libre)
UPDATE `conf__tablas` t
LEFT JOIN `conf__tablas` libre ON libre.`tabla_id` = 458
SET t.`tabla_id` = 458
WHERE t.`tabla_id` = 545
  AND t.`tabla_nombre` = 'gestion__ordenes_compra_certificaciones'
  AND libre.`tabla_id` IS NULL;

UPDATE `conf__tablas_estados_registros` SET `tabla_id` = 458
WHERE `tabla_id` = 545
  AND EXISTS (SELECT 1 FROM (SELECT `tabla_id` FROM `conf__tablas` WHERE `tabla_id` = 458
                             AND `tabla_nombre` = 'gestion__ordenes_compra_certificaciones') x)
  AND NOT EXISTS (SELECT 1 FROM (SELECT `tabla_id` FROM `conf__tablas` WHERE `tabla_id` = 545) y);

-- 2. Estado por defecto y datos con los ids viejos
ALTER TABLE `gestion__ordenes_compra_certificaciones`
  MODIFY `tabla_estado_registro_id` INT(11) NOT NULL DEFAULT 3
  COMMENT 'Id genérico: 3 Borrador, 4 En revisión, 5 Aprobado (habilita factura), 12 Facturado, 6 Eliminado, 38 Anulado';

UPDATE `gestion__ordenes_compra_certificaciones` SET `tabla_estado_registro_id` = 3  WHERE `tabla_estado_registro_id` = 1;
UPDATE `gestion__ordenes_compra_certificaciones` SET `tabla_estado_registro_id` = 12 WHERE `tabla_estado_registro_id` = 40;

-- 3. Catálogo de estados (etiqueta propia de la tabla 458)
INSERT INTO `conf__tablas_estados_registros`
  (`tabla_estado_registro_id`, `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`, `aplica_stock`)
SELECT v.id, 458, v.estado, v.etiqueta, 1, v.inicial, v.final, v.orden, 0
FROM (
          SELECT 262 AS id,  3 AS estado, 'Borrador'    AS etiqueta, 1 AS inicial, 0 AS final, 10 AS orden
UNION ALL SELECT 263,        4,           'En revisión',              0,           0,          20
UNION ALL SELECT 264,        5,           'Aprobado',                 0,           0,          30
UNION ALL SELECT 265,       12,           'Facturado',                0,           1,          40
UNION ALL SELECT 266,       38,           'Anulado',                  0,           1,          90
UNION ALL SELECT 267,        6,           'Eliminado',                0,           1,         100
) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas_estados_registros` t
                  WHERE t.`tabla_estado_registro_id` = v.id
                     OR (t.`tabla_id` = 458 AND t.`estado_registro_id` = v.estado));

SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE tabla_nombre = 'gestion__ordenes_compra_certificaciones';
SELECT tabla_estado_registro_id, estado_registro_id, tabla_estado_registro FROM conf__tablas_estados_registros WHERE tabla_id = 458 ORDER BY orden;
