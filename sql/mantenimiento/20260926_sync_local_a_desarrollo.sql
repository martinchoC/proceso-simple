-- =====================================================================
-- Sincronización de estructura: LOCAL (gestion_multipyme) -> DESARROLLO
-- (ps_gestion_casalucho_desarrollo, MySQL 8)
-- 2026-09-26
-- =====================================================================
-- Surge de comparar casalucho_local.sql vs casalucho_desarrollo.sql.
-- Agrega en desarrollo lo que existe en local y falta allá:
--   1) gestion__comprobantes_subgrupos.origen_asiento
--   2) gestion__productos: aplica_compra, aplica_venta,
--      cont_cuenta_compra_id, cont_cuenta_venta_id, cont_cuenta_cmv_id
--   3) gestion__productos_tipos: cont_cuenta_compra_default_id,
--      cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id
--   4) Tablas gestion__perfiles_sucursales y
--      gestion__usuarios_sucursales_excepciones
--   5) FKs fk_precios_actual_prod_prov y fk_historico_prod_prov
--   6) Datos semilla de actualizar_sql.txt (partes 2 y 3), que en
--      desarrollo no se aplicaron (la parte 1, IVA, ya estaba).
--
-- NO toca lo que existe solo en desarrollo (gestion__depositos,
-- ecom__usuarios_entidades, *_copy, SPs/evento de compatibilidad, FKs
-- FK_esc_* de gestion__entidades_sucursales_compra): eso queda como está.
--
-- Es idempotente: cada ALTER verifica information_schema antes de
-- ejecutarse, así que se puede volver a correr si falla a mitad de camino
-- (el DDL en MySQL hace commit implícito, no hay rollback posible).
-- Correr conectado a la base de desarrollo (USE abajo).
-- =====================================================================

SET NAMES utf8mb4;
USE `ps_gestion_casalucho_desarrollo`;

-- ---------------------------------------------------------------------
-- Helpers temporales (se eliminan al final)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_sync_add_column`;
DROP PROCEDURE IF EXISTS `_sync_add_fk`;

DELIMITER $$

CREATE PROCEDURE `_sync_add_column`(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna
    ) THEN
        SET @sync_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD COLUMN `', p_columna, '` ', p_definicion);
        PREPARE sync_stmt FROM @sync_sql;
        EXECUTE sync_stmt;
        DEALLOCATE PREPARE sync_stmt;
    END IF;
END$$

CREATE PROCEDURE `_sync_add_fk`(IN p_tabla VARCHAR(64), IN p_fk VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla
          AND CONSTRAINT_NAME = p_fk AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        SET @sync_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD CONSTRAINT `', p_fk, '` ', p_definicion);
        PREPARE sync_stmt FROM @sync_sql;
        EXECUTE sync_stmt;
        DEALLOCATE PREPARE sync_stmt;
    END IF;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 1) Origen de asiento por subgrupo de comprobante
-- ---------------------------------------------------------------------
CALL `_sync_add_column`('gestion__comprobantes_subgrupos', 'origen_asiento',
    'ENUM(''AUTO'',''MANU'',''NINGUNO'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''NINGUNO'' AFTER `tipo_asiento_id`');

-- ---------------------------------------------------------------------
-- 2) Productos: cuentas contables propias + flags compra/venta
--    (mismo orden de columnas que en local)
-- ---------------------------------------------------------------------
CALL `_sync_add_column`('gestion__productos', 'cont_cuenta_compra_id',
    'INT DEFAULT NULL COMMENT ''Cuenta de compra: 131 Mercaderias si es bien de cambio (con o sin control de stock), o cuenta de gasto si es servicio'' AFTER `cont_cuenta_id`');
CALL `_sync_add_column`('gestion__productos', 'cont_cuenta_venta_id',
    'INT DEFAULT NULL COMMENT ''Cuenta de ingreso: 600 Ventas Productos o 601 Ventas Servicios segun corresponda'' AFTER `cont_cuenta_compra_id`');
CALL `_sync_add_column`('gestion__productos', 'cont_cuenta_cmv_id',
    'INT DEFAULT NULL COMMENT ''Cuenta de costo de mercaderia vendida — solo se completa y solo se usa si controla_stock = 1. Provision: no usada por el motor de asientos en esta entrega'' AFTER `cont_cuenta_venta_id`');
CALL `_sync_add_column`('gestion__productos', 'aplica_compra',
    'TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = el producto puede comprarse'' AFTER `proveedores_busqueda`');
CALL `_sync_add_column`('gestion__productos', 'aplica_venta',
    'TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = el producto puede venderse'' AFTER `aplica_compra`');

-- ---------------------------------------------------------------------
-- 3) Tipos de producto: cuentas default que se materializan en el alta
-- ---------------------------------------------------------------------
CALL `_sync_add_column`('gestion__productos_tipos', 'cont_cuenta_compra_default_id',
    'INT DEFAULT NULL AFTER `tabla_estado_registro_id`');
CALL `_sync_add_column`('gestion__productos_tipos', 'cont_cuenta_venta_default_id',
    'INT DEFAULT NULL AFTER `cont_cuenta_compra_default_id`');
CALL `_sync_add_column`('gestion__productos_tipos', 'cont_cuenta_cmv_default_id',
    'INT DEFAULT NULL COMMENT ''Semilla de CMV — provisión, no usada por el motor de asientos en esta entrega'' AFTER `cont_cuenta_venta_default_id`');

-- ---------------------------------------------------------------------
-- 4) Accesos por sucursal / punto de venta / tipo de comprobante
--    (en local están con CHARSET=utf8; acá utf8mb4 como el resto de
--    desarrollo — solo afecta al ENUM tipo_excepcion)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gestion__perfiles_sucursales` (
  `perfil_sucursal_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_perfil_id` int unsigned NOT NULL,
  `sucursal_id` smallint unsigned NOT NULL,
  `punto_venta_id` smallint unsigned DEFAULT NULL,
  `comprobante_tipo_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`perfil_sucursal_id`),
  UNIQUE KEY `uk_grant` (`empresa_perfil_id`,`sucursal_id`,`punto_venta_id`,`comprobante_tipo_id`),
  KEY `idx_empresa_perfil` (`empresa_perfil_id`),
  KEY `fk_persuc_sucursal` (`sucursal_id`),
  KEY `fk_persuc_pv` (`punto_venta_id`),
  KEY `fk_persuc_tipo` (`comprobante_tipo_id`),
  CONSTRAINT `fk_persuc_empresa_perfil` FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`),
  CONSTRAINT `fk_persuc_pv` FOREIGN KEY (`punto_venta_id`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_persuc_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_persuc_tipo` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__usuarios_sucursales_excepciones` (
  `usuario_excepcion_id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `sucursal_id` int unsigned NOT NULL,
  `punto_venta_id` int unsigned DEFAULT NULL,
  `comprobante_tipo_id` int unsigned DEFAULT NULL,
  `tipo_excepcion` enum('PERMITE','DENIEGA') NOT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `usuario_alta_id` int unsigned NOT NULL,
  `f_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_excepcion_id`),
  KEY `idx_usuario_vigencia` (`usuario_id`,`fecha_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) FKs de precios de proveedor -> gestion__productos_proveedores
--    Verificación previa: ambas consultas deben dar 0. Si hay huérfanos
--    el ALTER falla (error 1452); limpiarlos antes de volver a correr.
-- ---------------------------------------------------------------------
SELECT 'precios huerfanos' AS control, COUNT(*) AS filas
FROM gestion__productos_proveedores_precios p
LEFT JOIN gestion__productos_proveedores pp ON pp.producto_proveedor_id = p.producto_proveedor_id
WHERE pp.producto_proveedor_id IS NULL
UNION ALL
SELECT 'historico huerfanos', COUNT(*)
FROM gestion__productos_proveedores_precios_historico h
LEFT JOIN gestion__productos_proveedores pp ON pp.producto_proveedor_id = h.producto_proveedor_id
WHERE pp.producto_proveedor_id IS NULL;

CALL `_sync_add_fk`('gestion__productos_proveedores_precios', 'fk_precios_actual_prod_prov',
    'FOREIGN KEY (`producto_proveedor_id`) REFERENCES `gestion__productos_proveedores` (`producto_proveedor_id`)');
CALL `_sync_add_fk`('gestion__productos_proveedores_precios_historico', 'fk_historico_prod_prov',
    'FOREIGN KEY (`producto_proveedor_id`) REFERENCES `gestion__productos_proveedores` (`producto_proveedor_id`)');

-- ---------------------------------------------------------------------
-- 6) Datos semilla (de actualizar_sql.txt, partes 2 y 3).
--    Solo completan valores vacíos: no pisan lo que ya se haya cargado.
-- ---------------------------------------------------------------------
START TRANSACTION;

-- Facturación (confirmar id real del subgrupo en desarrollo)
UPDATE gestion__comprobantes_subgrupos
SET origen_asiento = 'AUTO'
WHERE comprobante_subgrupo_id = 1
  AND origen_asiento = 'NINGUNO';

UPDATE gestion__productos_tipos
SET cont_cuenta_compra_default_id = COALESCE(cont_cuenta_compra_default_id, 131),  -- Mercaderias
    cont_cuenta_venta_default_id  = COALESCE(cont_cuenta_venta_default_id, 600)    -- Ventas Productos
WHERE maneja_stock = 1; -- tipo 'Producto'

UPDATE gestion__productos_tipos
SET cont_cuenta_venta_default_id = COALESCE(cont_cuenta_venta_default_id, 601)     -- Ventas Servicios
WHERE maneja_stock = 0; -- tipo 'Servicio'

COMMIT;

-- ---------------------------------------------------------------------
-- Limpieza de helpers
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_sync_add_column`;
DROP PROCEDURE IF EXISTS `_sync_add_fk`;

-- ---------------------------------------------------------------------
-- Verificación final: debe devolver 9 columnas, 2 tablas y 2 FKs (13 filas)
-- ---------------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME = 'gestion__comprobantes_subgrupos' AND COLUMN_NAME = 'origen_asiento')
    OR (TABLE_NAME = 'gestion__productos' AND COLUMN_NAME IN ('aplica_compra','aplica_venta','cont_cuenta_compra_id','cont_cuenta_venta_id','cont_cuenta_cmv_id'))
    OR (TABLE_NAME = 'gestion__productos_tipos' AND COLUMN_NAME IN ('cont_cuenta_compra_default_id','cont_cuenta_venta_default_id','cont_cuenta_cmv_default_id')))
UNION ALL
SELECT TABLE_NAME, '(tabla)', ''
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('gestion__perfiles_sucursales','gestion__usuarios_sucursales_excepciones')
UNION ALL
SELECT TABLE_NAME, CONSTRAINT_NAME, 'FK'
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND CONSTRAINT_NAME IN ('fk_precios_actual_prod_prov','fk_historico_prod_prov');
