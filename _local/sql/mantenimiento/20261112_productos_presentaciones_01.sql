-- -----------------------------------------------------------------------------------------------------------------
-- 20261112_productos_presentaciones_01.sql
-- Presentaciones de productos (etapa 1: alta en el ABM). Diseño en _local/docs/gestion/17_productos_presentaciones.md.
-- Código: core/productos_presentaciones.php (prpr_*), templates/productos/presentaciones_seccion.php,
-- assets/js/productos-presentaciones.js y productos* de gestion / gestion_normal.
--
-- 1. conf__empresas.usa_presentaciones (0/1, por defecto 0): la empresa carga presentaciones (caja, pack, pallet...)
--    en sus productos. Se edita en Configuración > Empresas. Apagado, el ABM de productos no muestra la sección.
-- 2. gestion__productos_presentaciones (tabla 462): presentaciones de cada producto, con su factor en unidades de la
--    unidad de medida del producto (Caja x 12 = 12), código de barras propio (EAN / DUN-14), peso y medidas del bulto
--    y si es la presentación habitual de compra / de venta. Nunca se borran: la baja es estado 2 (los comprobantes de
--    la etapa 2 van a apuntar a presentacion_id).
--
-- Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0). Va ANTES del código (sin la tabla, el código no muestra
-- la sección; sin la columna, ninguna empresa la usa).
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_presentaciones');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_presentaciones TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = los productos tienen presentaciones (caja, pack, pallet) con factor y código de barras propio'' AFTER decimales_stock',
    'SELECT ''conf__empresas.usa_presentaciones ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Presentaciones
CREATE TABLE IF NOT EXISTS `gestion__productos_presentaciones` (
  `presentacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `producto_id` int(10) unsigned NOT NULL,
  `presentacion_nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: Caja x 12, Pack x 6, Pallet',
  `factor` decimal(18,4) NOT NULL COMMENT 'Unidades de la unidad de medida del producto que contiene (> 0)',
  `codigo_barras` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'EAN-13 / DUN-14 del bulto; único por empresa entre productos y presentaciones activas',
  `peso_bruto_kg` decimal(10,3) DEFAULT NULL,
  `largo_cm` decimal(8,2) DEFAULT NULL,
  `ancho_cm` decimal(8,2) DEFAULT NULL,
  `alto_cm` decimal(8,2) DEFAULT NULL,
  `aplica_compra` tinyint(1) NOT NULL DEFAULT 1,
  `aplica_venta` tinyint(1) NOT NULL DEFAULT 1,
  `es_compra_defecto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Presentación habitual de compra (una por producto)',
  `es_venta_defecto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Presentación habitual de venta (una por producto)',
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Inactiva (no se borran)',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `f_alta` datetime NOT NULL DEFAULT current_timestamp(),
  `f_modificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`presentacion_id`),
  KEY `idx_prpr_producto` (`empresa_id`, `producto_id`, `tabla_estado_registro_id`),
  KEY `idx_prpr_barras` (`empresa_id`, `codigo_barras`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Presentaciones de un producto (caja, pack, pallet) con factor de conversión a la unidad del producto';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, 1, 1, 1
FROM (SELECT 462 AS id, 'gestion__productos_presentaciones' AS nombre, 'Presentaciones de productos (caja, pack, pallet) con factor y código de barras' AS descr) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

SELECT empresa_id, empresa, usa_presentaciones FROM conf__empresas ORDER BY empresa_id;
