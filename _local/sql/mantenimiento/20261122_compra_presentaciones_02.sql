-- Compra por presentación (doc 27, paso 8-4): presentación en la que el proveedor vende el producto (gestion__productos_proveedores.presentacion_id).
-- -----------------------------------------------------------------------------------------------------------------
-- 20261122_compra_presentaciones_02.sql
-- Diseño en _local/docs/gestion/27_presentaciones_en_compras.md (decisión 4, aprobada el 2026-11-15).
--
-- gestion__productos_proveedores.presentacion_id INT UNSIGNED NULL (NULL = el proveedor vende por unidad, como hasta
-- ahora). Con usa_compra_presentaciones, la orden de compra y la factura de proveedor proponen esa presentación al
-- elegir el producto, y la carga de facturas desde archivo toma la cantidad y el precio leídos con ese código de
-- proveedor como cantidad y precio de la presentación.
--
-- Solo agrega una columna NULL. Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0). Va ANTES del código. Requiere
-- 20261122_compra_presentaciones_01.sql.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores' AND COLUMN_NAME = 'presentacion_id');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__productos_proveedores''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__productos_proveedores ADD COLUMN presentacion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Presentación en la que vende el proveedor (gestion__productos_presentaciones); NULL = por unidad''' ELSE 'SELECT ''gestion__productos_proveedores.presentacion_id ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
