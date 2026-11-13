-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================
-- Migracion de PRODUCCION Casa Lucho al formato actual (servidor nuevo)
-- Script 05: COMPLEMENTO
-- 2026-10-31
-- =====================================================================
-- Requiere los scripts 01 a 04. Escrito a mano.
-- Surge de correr 01 -> 04 sobre un clon de ps_gestion_casalucho y comparar el
-- resultado con gestion_multipyme (estructura por information_schema y datos de
-- configuracion por clave). Cubre lo que quedo afuera:
--   1) gestion__bancos_importaciones.formato_id admite NULL (= Lectura IA).
--      Equivale a 20261031_bancos_importar_ia_01.sql, posterior a la
--      generacion del 01.
--   2) gestion__productos_tipos: se borra el UNIQUE global `producto_tipo_codigo`
--      de produccion. El 01 ya agrega uk_productos_tipos_empresa_codigo
--      (empresa_id, producto_tipo_codigo); con el global, el asistente de inicio
--      no puede copiar los tipos PROD / SERV a otra empresa.
--
-- Diferencias que quedan A PROPOSITO despues de 01 -> 05:
--   - FK fk_precios_actual_prod_prov: 57 precios de produccion apuntan a
--     relaciones producto-proveedor que ya no existen (ver el final del 01).
--   - conf__usuarios.duracion_sid_minutos: produccion tiene DEFAULT 1440 (local 60).
--   - Indice idx_producto_empresa_principal de gestion__productos_proveedores y
--     las tablas / columnas propias de produccion (ver el encabezado del 01).
--   - Tablas perf_* de local (herramienta de _local/perf, no van a produccion).
--
-- Reanudable: cada paso consulta information_schema antes de ejecutarse.
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261031_migracion_casalucho_05_complemento.sql
-- =====================================================================

SET NAMES utf8mb4;
-- Sin USE: la base se indica en la linea de comando.

DROP PROCEDURE IF EXISTS `_mig_complemento_05`;

DELIMITER $$

CREATE PROCEDURE `_mig_complemento_05`()
BEGIN
    -- 1) formato_id NULL = Lectura IA
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__bancos_importaciones'
                 AND COLUMN_NAME = 'formato_id' AND IS_NULLABLE = 'NO') THEN
        ALTER TABLE `gestion__bancos_importaciones`
            MODIFY COLUMN `formato_id` int(10) unsigned DEFAULT NULL COMMENT 'Formato usado (NULL = Lectura IA)';
    END IF;

    -- 2) Codigo de tipo de producto: unico por empresa, no global
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_tipos'
                     AND INDEX_NAME = 'uk_productos_tipos_empresa_codigo') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falta uk_productos_tipos_empresa_codigo: correr el script 01 antes';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_tipos'
                 AND INDEX_NAME = 'producto_tipo_codigo') THEN
        ALTER TABLE `gestion__productos_tipos` DROP INDEX `producto_tipo_codigo`;
    END IF;
END$$

DELIMITER ;

CALL `_mig_complemento_05`();
DROP PROCEDURE IF EXISTS `_mig_complemento_05`;
