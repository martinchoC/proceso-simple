-- Canales de venta (paso 3): indicador, catálogo de canales por empresa, canal de cada lista y canales que vende cada sucursal.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261116_canales_venta_01.sql
-- Diseño en _local/docs/gestion/21_canal_cliente_sucursal.md (decisiones aprobadas el 2026-11-15).
--
-- 1. conf__empresas.usa_canales_venta (0/1, por defecto 0). Apagado, nada cambia.
-- 2. gestion__canales_venta (tabla 464): canales de cada empresa (Minorista, Mayorista...). Sin datos: los carga la
--    empresa desde Listas de precios.
-- 3. gestion__listas_precios.canal_venta_id (NULL = sin canal: la lista se vende en todas las sucursales). El canal
--    del cliente es el de su lista vigente.
-- 4. gestion__sucursales_canales_venta (tabla 465): canales que vende cada sucursal. Sucursal sin filas activas =
--    vende todos los canales (como hoy).
--
-- Solo agrega (columna NULL / con valor por defecto, tablas nuevas). Reanudable e idempotente (MariaDB 10.4 /
-- MySQL 8.0). Va ANTES del código.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_canales_venta');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_canales_venta TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = canales de venta por lista, sucursales que restringen canales y lista del cliente unificada'' ',
    'SELECT ''conf__empresas.usa_canales_venta ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Catálogo de canales
CREATE TABLE IF NOT EXISTS `gestion__canales_venta` (
  `canal_venta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`canal_venta_id`),
  UNIQUE KEY `uk_canales_venta_codigo` (`empresa_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Canales de venta de la empresa (minorista, mayorista); el canal del cliente sale de su lista';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, 1, 1, 1
FROM (SELECT 464 AS id, 'gestion__canales_venta' AS nombre, 'Canales de venta de la empresa (minorista, mayorista)' AS descr) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

-- 3. Canal de cada lista
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__listas_precios' AND COLUMN_NAME = 'canal_venta_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__listas_precios ADD COLUMN canal_venta_id INT(10) UNSIGNED DEFAULT NULL COMMENT ''gestion__canales_venta (NULL = sin canal); solo con usa_canales_venta'' ',
    'SELECT ''gestion__listas_precios.canal_venta_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 4. Canales que vende cada sucursal
CREATE TABLE IF NOT EXISTS `gestion__sucursales_canales_venta` (
  `sucursal_canal_venta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` int(10) unsigned NOT NULL,
  `canal_venta_id` int(10) unsigned NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Vende / 2 Ya no vende (historial)',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`sucursal_canal_venta_id`),
  UNIQUE KEY `uk_sucursales_canales_venta` (`sucursal_id`, `canal_venta_id`),
  KEY `idx_sucursales_canales_venta_empresa` (`empresa_id`, `sucursal_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Canales que vende cada sucursal (sin filas activas = todos)';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, 1, 1, 1
FROM (SELECT 465 AS id, 'gestion__sucursales_canales_venta' AS nombre, 'Canales que vende cada sucursal' AS descr) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

SELECT empresa_id, empresa, usa_canales_venta FROM conf__empresas ORDER BY empresa_id;
