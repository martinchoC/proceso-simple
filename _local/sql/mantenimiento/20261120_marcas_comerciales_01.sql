-- Marca comercial del producto (doc 25): indicador, tabla de marcas por empresa, marca del producto y marca en el alcance de escalas y promociones.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261120_marcas_comerciales_01.sql
-- Diseño en _local/docs/gestion/25_marca_comercial.md (decisiones aprobadas el 2026-11-15).
--
-- 1. conf__empresas.usa_marcas_comerciales (0/1, por defecto 0). Apagado, nada cambia.
-- 2. gestion__productos_marcas (tabla 476): marcas comerciales por empresa. NO es gestion__marcas (marca del
--    vehículo de la compatibilidad de autopartes del módulo 2).
-- 3. gestion__productos.marca_comercial_id (NULL).
-- 4. gestion__precios_reglas_venta.marca_comercial_id (NULL = todas las marcas), solo si ya está la tabla de reglas
--    (20261117_reglas_precio_venta_01.sql).
--
-- Solo agrega (columnas NULL / con valor por defecto, tabla nueva). Reanudable e idempotente (MariaDB 10.4 /
-- MySQL 8.0). Va ANTES del código.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_marcas_comerciales');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_marcas_comerciales TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = marca comercial en los productos'' ',
    'SELECT ''conf__empresas.usa_marcas_comerciales ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Marcas
CREATE TABLE IF NOT EXISTS `gestion__productos_marcas` (
  `marca_comercial_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Inactiva',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`marca_comercial_id`),
  KEY `idx_pma_empresa` (`empresa_id`, `tabla_estado_registro_id`, `nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Marcas comerciales de los productos (no las de vehículos de gestion__marcas)';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT 476, 'gestion__productos_marcas', 'Marcas comerciales de los productos', 2, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` WHERE `tabla_id` = 476);

-- 3. Marca del producto
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND COLUMN_NAME = 'marca_comercial_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__productos ADD COLUMN marca_comercial_id INT(10) UNSIGNED DEFAULT NULL COMMENT ''Marca comercial (gestion__productos_marcas); NULL = sin marca'', ADD KEY idx_prod_marca_comercial (empresa_id, marca_comercial_id)',
    'SELECT ''gestion__productos.marca_comercial_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 4. Marca en el alcance de escalas y promociones (si la tabla existe)
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__precios_reglas_venta');
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__precios_reglas_venta' AND COLUMN_NAME = 'marca_comercial_id');
SET @sql := IF(@tabla = 1 AND @existe = 0,
    'ALTER TABLE gestion__precios_reglas_venta ADD COLUMN marca_comercial_id INT(10) UNSIGNED DEFAULT NULL COMMENT ''NULL = todas las marcas'' AFTER producto_categoria_id',
    'SELECT ''gestion__precios_reglas_venta.marca_comercial_id ya existe o no hay tabla de reglas''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
