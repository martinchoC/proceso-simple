-- Venta por presentación, paso 1 (modelo de datos): indicador, precios por presentación, unidad suelta por lista, mínimo / múltiplo.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261114_venta_presentaciones_01.sql
-- Diseño en _local/docs/gestion/19_precios_presentaciones.md (decisiones aprobadas el 2026-11-14).
--
-- 1. conf__empresas.usa_venta_presentaciones (0/1, por defecto 0): la empresa vende por presentación. Exige además
--    usa_presentaciones. Apagado, nada cambia.
-- 2. gestion__listas_precios_presentaciones (tabla 463): precio de la presentación entera por lista, con vigencia.
--    Sin fila vigente, la presentación no se ofrece en esa lista. Al cambiar un precio, la fila anterior pasa a estado 2.
-- 3. gestion__listas_precios.vende_unidad_base (1 por defecto = como hoy): con 0 la lista no ofrece la unidad suelta.
-- 4. venta_minimo / venta_multiplo (NULL = sin restricción) en gestion__productos (unidad base) y en
--    gestion__productos_presentaciones (en cantidad de esa presentación).
--
-- Solo agrega (columnas con valor por defecto o NULL, tabla nueva). Reanudable e idempotente (MariaDB 10.4 /
-- MySQL 8.0). Va ANTES del código. Requiere 20261112_productos_presentaciones_01.sql: si la tabla de presentaciones
-- todavía no existe, el punto 4 de presentaciones se saltea con aviso y hay que volver a correr este script después.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_venta_presentaciones');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_venta_presentaciones TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = vende por presentación (precio propio por lista, mínimo y múltiplo); exige usa_presentaciones'' ',
    'SELECT ''conf__empresas.usa_venta_presentaciones ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Precios por presentación
CREATE TABLE IF NOT EXISTS `gestion__listas_precios_presentaciones` (
  `lista_precio_presentacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `lista_precio_id` int(11) NOT NULL,
  `presentacion_id` int(10) unsigned NOT NULL COMMENT 'gestion__productos_presentaciones',
  `producto_id` int(10) unsigned NOT NULL COMMENT 'Producto de la presentación (para filtrar sin JOIN)',
  `precio_final` decimal(18,4) NOT NULL COMMENT 'Precio de la presentación entera (la caja, no el unitario)',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Vigente / 2 Reemplazada (historial)',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`lista_precio_presentacion_id`),
  KEY `idx_lpre_lista` (`empresa_id`, `lista_precio_id`, `presentacion_id`, `tabla_estado_registro_id`),
  KEY `idx_lpre_producto` (`empresa_id`, `producto_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Precio de cada presentación (caja, pack) por lista de precios';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, 1, 1, 1
FROM (SELECT 463 AS id, 'gestion__listas_precios_presentaciones' AS nombre, 'Precio de cada presentación (caja, pack) por lista de precios' AS descr) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

-- 3. Unidad suelta por lista
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__listas_precios' AND COLUMN_NAME = 'vende_unidad_base');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__listas_precios ADD COLUMN vende_unidad_base TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''0 = la lista no ofrece la unidad suelta (solo presentaciones); solo con usa_venta_presentaciones'' ',
    'SELECT ''gestion__listas_precios.vende_unidad_base ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 4a. Mínimo y múltiplo de la unidad base
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND COLUMN_NAME = 'venta_minimo');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__productos ADD COLUMN venta_minimo DECIMAL(18,4) DEFAULT NULL COMMENT ''Cantidad mínima de venta en unidad base (NULL = sin mínimo)'' ',
    'SELECT ''gestion__productos.venta_minimo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND COLUMN_NAME = 'venta_multiplo');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__productos ADD COLUMN venta_multiplo DECIMAL(18,4) DEFAULT NULL COMMENT ''Múltiplo de venta en unidad base (NULL = cualquier cantidad)'' ',
    'SELECT ''gestion__productos.venta_multiplo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 4b. Mínimo y múltiplo por presentación (solo si la tabla de presentaciones ya existe)
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_presentaciones');

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_presentaciones' AND COLUMN_NAME = 'venta_minimo');
SET @sql := CASE
    WHEN @tabla = 0 THEN 'SELECT ''AVISO: falta gestion__productos_presentaciones (correr 20261112_productos_presentaciones_01.sql y volver a correr este script)'''
    WHEN @existe = 0 THEN 'ALTER TABLE gestion__productos_presentaciones ADD COLUMN venta_minimo DECIMAL(18,4) DEFAULT NULL COMMENT ''Cantidad mínima de venta, en esta presentación (NULL = sin mínimo)'' '
    ELSE 'SELECT ''gestion__productos_presentaciones.venta_minimo ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_presentaciones' AND COLUMN_NAME = 'venta_multiplo');
SET @sql := CASE
    WHEN @tabla = 0 THEN 'SELECT ''AVISO: falta gestion__productos_presentaciones'''
    WHEN @existe = 0 THEN 'ALTER TABLE gestion__productos_presentaciones ADD COLUMN venta_multiplo DECIMAL(18,4) DEFAULT NULL COMMENT ''Múltiplo de venta, en esta presentación (NULL = cualquier cantidad)'' '
    ELSE 'SELECT ''gestion__productos_presentaciones.venta_multiplo ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT empresa_id, empresa, usa_venta_presentaciones FROM conf__empresas ORDER BY empresa_id;
