-- Reglas de precio de venta (paso 4): indicador, escalas y promociones con tramos, topes de descuento por perfil, tope por lista y la regla aplicada en cada línea.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261117_reglas_precio_venta_01.sql
-- Diseño en _local/docs/gestion/22_escalas_promociones.md (decisiones aprobadas el 2026-11-15).
--
-- 1. conf__empresas.usa_reglas_precio_venta (0/1, por defecto 0). Apagado, nada cambia.
-- 2. gestion__precios_reglas_venta (tabla 466): escalas por cantidad y promociones, con vigencia y alcance
--    (producto / presentación / categoría / lista / canal / sucursal; NULL = todos). Se evalúan al vender.
-- 3. gestion__precios_reglas_venta_tramos (tabla 467): tramos de una escala (desde N unidades base, X %).
-- 4. gestion__ventas_descuentos_topes (tabla 468): % máximo de descuento manual por perfil, con vigencia.
-- 5. gestion__listas_precios.descuento_tope_pct (NULL = sin tope): techo del descuento automático de la lista.
-- 6. En las líneas de pedidos, remitos y facturas de venta: la regla aplicada (precio_regla_tipo / _id), el
--    automático, el manual y quién lo aprobó. Todas NULL por defecto; el descuento que se calcula sigue en
--    descuento_general_pct como hoy.
--
-- Solo agrega (columnas NULL / con valor por defecto, tablas nuevas). Reanudable e idempotente (MariaDB 10.4 /
-- MySQL 8.0). Va ANTES del código. La página "Escalas y promociones" va en 20261117_reglas_precio_venta_02_pagina.sql.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_reglas_precio_venta');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_reglas_precio_venta TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = escalas por cantidad, promociones y tope del descuento manual al vender'' ',
    'SELECT ''conf__empresas.usa_reglas_precio_venta ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Reglas
CREATE TABLE IF NOT EXISTS `gestion__precios_reglas_venta` (
  `regla_venta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `tipo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ESCALA (por cantidad, con tramos) / PROMOCION (descuento_pct)',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridad` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Desempate entre reglas del mismo tipo con el mismo %: mayor gana',
  `producto_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = todos los productos (si tampoco hay categoría)',
  `presentacion_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = cualquier presentación (y la unidad)',
  `producto_categoria_id` int(10) unsigned DEFAULT NULL,
  `lista_precio_id` int(10) unsigned DEFAULT NULL,
  `canal_venta_id` int(10) unsigned DEFAULT NULL,
  `sucursal_id` int(10) unsigned DEFAULT NULL,
  `descuento_pct` decimal(5,2) DEFAULT NULL COMMENT 'PROMOCION: descuento sobre el precio de lista',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Inactiva',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`regla_venta_id`),
  KEY `idx_prv_empresa` (`empresa_id`, `tabla_estado_registro_id`, `f_desde`),
  KEY `idx_prv_producto` (`empresa_id`, `producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Escalas por cantidad y promociones de venta, evaluadas al vender';

CREATE TABLE IF NOT EXISTS `gestion__precios_reglas_venta_tramos` (
  `tramo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `regla_venta_id` int(10) unsigned NOT NULL,
  `cantidad_desde` decimal(18,4) NOT NULL COMMENT 'En unidad base del producto, sumando todo el comprobante',
  `descuento_pct` decimal(5,2) NOT NULL,
  PRIMARY KEY (`tramo_id`),
  KEY `idx_prvt_regla` (`regla_venta_id`, `cantidad_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tramos de una escala por cantidad';

CREATE TABLE IF NOT EXISTS `gestion__ventas_descuentos_topes` (
  `descuento_tope_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `empresa_perfil_id` int(11) NOT NULL COMMENT 'conf__empresas_perfiles',
  `tope_pct` decimal(5,2) NOT NULL COMMENT '% máximo de descuento manual que puede dar o aprobar el perfil',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`descuento_tope_id`),
  KEY `idx_vdt_perfil` (`empresa_id`, `empresa_perfil_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tope de descuento manual en ventas por perfil (sin fila = 0 %)';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, 1, 1, 1
FROM (SELECT 466 AS id, 'gestion__precios_reglas_venta' AS nombre, 'Escalas por cantidad y promociones de venta' AS descr
      UNION ALL SELECT 467, 'gestion__precios_reglas_venta_tramos', 'Tramos de una escala por cantidad'
      UNION ALL SELECT 468, 'gestion__ventas_descuentos_topes', 'Tope de descuento manual en ventas por perfil') v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

-- 5. Tope del descuento automático por lista
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__listas_precios' AND COLUMN_NAME = 'descuento_tope_pct');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__listas_precios ADD COLUMN descuento_tope_pct DECIMAL(5,2) DEFAULT NULL COMMENT ''Tope del descuento automático (cliente / escala / promoción) en esta lista; NULL = sin tope'' ',
    'SELECT ''gestion__listas_precios.descuento_tope_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 6. Regla aplicada en las líneas de venta
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'precio_regla_tipo');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN precio_regla_tipo VARCHAR(10) DEFAULT NULL COMMENT ''Regla del descuento automático: CLIENTE / ESCALA / PROMOCION / NINGUNA (NULL = sin reglas de precio)''', 'SELECT ''gestion__ventas_pedidos_detalles.precio_regla_tipo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'precio_regla_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN precio_regla_id INT(10) UNSIGNED DEFAULT NULL COMMENT ''gestion__precios_reglas_venta (ESCALA / PROMOCION)''', 'SELECT ''gestion__ventas_pedidos_detalles.precio_regla_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_auto_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN descuento_auto_pct DECIMAL(5,2) DEFAULT NULL COMMENT ''Descuento automático (cliente, escala o promoción), ya con el tope de la lista''', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_auto_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_manual_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN descuento_manual_pct DECIMAL(5,2) DEFAULT NULL COMMENT ''Descuento manual del vendedor, sobre el neto del automático''', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_manual_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_aprobado_por');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN descuento_aprobado_por INT(10) UNSIGNED DEFAULT NULL COMMENT ''Usuario cuyo tope cubrió el descuento manual''', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_aprobado_por ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_aprobado_en');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN descuento_aprobado_en DATETIME DEFAULT NULL', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_aprobado_en ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'precio_regla_tipo');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN precio_regla_tipo VARCHAR(10) DEFAULT NULL COMMENT ''Regla del descuento automático: CLIENTE / ESCALA / PROMOCION / NINGUNA (NULL = sin reglas de precio)''', 'SELECT ''gestion__ventas_remitos_detalles.precio_regla_tipo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'precio_regla_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN precio_regla_id INT(10) UNSIGNED DEFAULT NULL COMMENT ''gestion__precios_reglas_venta (ESCALA / PROMOCION)''', 'SELECT ''gestion__ventas_remitos_detalles.precio_regla_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_auto_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN descuento_auto_pct DECIMAL(5,2) DEFAULT NULL COMMENT ''Descuento automático (cliente, escala o promoción), ya con el tope de la lista''', 'SELECT ''gestion__ventas_remitos_detalles.descuento_auto_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_manual_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN descuento_manual_pct DECIMAL(5,2) DEFAULT NULL COMMENT ''Descuento manual del vendedor, sobre el neto del automático''', 'SELECT ''gestion__ventas_remitos_detalles.descuento_manual_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_aprobado_por');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN descuento_aprobado_por INT(10) UNSIGNED DEFAULT NULL COMMENT ''Usuario cuyo tope cubrió el descuento manual''', 'SELECT ''gestion__ventas_remitos_detalles.descuento_aprobado_por ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_aprobado_en');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN descuento_aprobado_en DATETIME DEFAULT NULL', 'SELECT ''gestion__ventas_remitos_detalles.descuento_aprobado_en ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'precio_regla_tipo');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN precio_regla_tipo VARCHAR(10) DEFAULT NULL COMMENT ''Regla del descuento automático: CLIENTE / ESCALA / PROMOCION / NINGUNA (NULL = sin reglas de precio)''', 'SELECT ''gestion__ventas_facturas_detalles.precio_regla_tipo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'precio_regla_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN precio_regla_id INT(10) UNSIGNED DEFAULT NULL COMMENT ''gestion__precios_reglas_venta (ESCALA / PROMOCION)''', 'SELECT ''gestion__ventas_facturas_detalles.precio_regla_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_auto_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN descuento_auto_pct DECIMAL(5,2) DEFAULT NULL COMMENT ''Descuento automático (cliente, escala o promoción), ya con el tope de la lista''', 'SELECT ''gestion__ventas_facturas_detalles.descuento_auto_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_manual_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN descuento_manual_pct DECIMAL(5,2) DEFAULT NULL COMMENT ''Descuento manual del vendedor, sobre el neto del automático''', 'SELECT ''gestion__ventas_facturas_detalles.descuento_manual_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_aprobado_por');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN descuento_aprobado_por INT(10) UNSIGNED DEFAULT NULL COMMENT ''Usuario cuyo tope cubrió el descuento manual''', 'SELECT ''gestion__ventas_facturas_detalles.descuento_aprobado_por ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_aprobado_en');
SET @sql := IF(@existe = 0, 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN descuento_aprobado_en DATETIME DEFAULT NULL', 'SELECT ''gestion__ventas_facturas_detalles.descuento_aprobado_en ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT empresa_id, empresa, usa_reglas_precio_venta FROM conf__empresas ORDER BY empresa_id;
