-- Compra por presentación (doc 27): indicador por empresa y foto de la presentación comprada en las líneas de órdenes de compra y facturas de proveedor.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261122_compra_presentaciones_01.sql
-- Diseño en _local/docs/gestion/27_presentaciones_en_compras.md (aprobado el 2026-11-15).
--
-- 1. conf__empresas.usa_compra_presentaciones (0/1, por defecto 0). Apagado, nada cambia. Necesita también
--    usa_presentaciones (las presentaciones del producto).
-- 2. En gestion__ordenes_compra_detalle y gestion__facturas_proveedores_detalle, cinco columnas NULL (NULL en toda
--    línea por unidad). La línea sigue en unidad base (cantidad y precio unitario de siempre):
--      presentacion_id, presentacion_nombre, presentacion_factor, presentacion_cantidad, presentacion_precio
--    con cantidad = presentacion_cantidad × presentacion_factor.
--
-- Solo agrega columnas NULL / con valor por defecto. Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0). Va ANTES
-- del código.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_compra_presentaciones');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_compra_presentaciones TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = órdenes de compra y facturas de proveedor por presentación (caja, pack)'' ',
    'SELECT ''conf__empresas.usa_compra_presentaciones ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- 2a. Órdenes de compra
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_id');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ordenes_compra_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ordenes_compra_detalle ADD COLUMN presentacion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Presentación comprada (gestion__productos_presentaciones); NULL = por unidad''' ELSE 'SELECT ''gestion__ordenes_compra_detalle.presentacion_id ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_nombre');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ordenes_compra_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ordenes_compra_detalle ADD COLUMN presentacion_nombre VARCHAR(60) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación''' ELSE 'SELECT ''gestion__ordenes_compra_detalle.presentacion_nombre ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_factor');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ordenes_compra_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ordenes_compra_detalle ADD COLUMN presentacion_factor DECIMAL(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)''' ELSE 'SELECT ''gestion__ordenes_compra_detalle.presentacion_factor ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_cantidad');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ordenes_compra_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ordenes_compra_detalle ADD COLUMN presentacion_cantidad DECIMAL(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)''' ELSE 'SELECT ''gestion__ordenes_compra_detalle.presentacion_cantidad ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_precio');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ordenes_compra_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ordenes_compra_detalle ADD COLUMN presentacion_precio DECIMAL(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera del proveedor, antes de descuentos''' ELSE 'SELECT ''gestion__ordenes_compra_detalle.presentacion_precio ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2b. Facturas de proveedor
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_id');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__facturas_proveedores_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__facturas_proveedores_detalle ADD COLUMN presentacion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Presentación comprada (gestion__productos_presentaciones); NULL = por unidad''' ELSE 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_id ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_nombre');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__facturas_proveedores_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__facturas_proveedores_detalle ADD COLUMN presentacion_nombre VARCHAR(60) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación''' ELSE 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_nombre ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_factor');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__facturas_proveedores_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__facturas_proveedores_detalle ADD COLUMN presentacion_factor DECIMAL(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)''' ELSE 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_factor ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_cantidad');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__facturas_proveedores_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__facturas_proveedores_detalle ADD COLUMN presentacion_cantidad DECIMAL(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)''' ELSE 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_cantidad ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_precio');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__facturas_proveedores_detalle''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__facturas_proveedores_detalle ADD COLUMN presentacion_precio DECIMAL(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera del proveedor, antes de descuentos''' ELSE 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_precio ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
