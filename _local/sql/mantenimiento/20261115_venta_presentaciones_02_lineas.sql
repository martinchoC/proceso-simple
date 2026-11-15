-- Venta por presentación, paso 2 (líneas): foto de la presentación vendida en las líneas de pedidos, remitos y facturas.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261115_venta_presentaciones_02_lineas.sql
-- Diseño en _local/docs/gestion/20_presentaciones_en_ventas.md (aprobado el 2026-11-14).
--
-- La línea sigue en unidad base (cantidad y precio unitario de siempre); estas columnas, NULL en toda línea por
-- unidad, guardan qué presentación se vendió y cómo:
--   presentacion_id        gestion__productos_presentaciones
--   presentacion_nombre    foto del nombre ("Caja x 12")
--   presentacion_factor    foto del factor (unidades base por presentación)
--   presentacion_cantidad  cantidad en presentaciones (cantidad = presentacion_cantidad × presentacion_factor)
--   presentacion_precio    precio de la presentación entera, antes del descuento del cliente
-- en gestion__ventas_pedidos_detalles, gestion__ventas_remitos_detalles y gestion__ventas_facturas_detalles.
--
-- Solo agrega columnas NULL. Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0): cada columna con su chequeo.
-- Va ANTES del código. Requiere 20261114_venta_presentaciones_01.sql (el indicador) para que se usen.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- Pedidos
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_id');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_pedidos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN presentacion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Presentación vendida (gestion__productos_presentaciones); NULL = por unidad''' ELSE 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_id ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_nombre');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_pedidos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN presentacion_nombre VARCHAR(60) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación''' ELSE 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_nombre ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_factor');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_pedidos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN presentacion_factor DECIMAL(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)''' ELSE 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_factor ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_cantidad');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_pedidos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN presentacion_cantidad DECIMAL(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)''' ELSE 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_cantidad ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_precio');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_pedidos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_pedidos_detalles ADD COLUMN presentacion_precio DECIMAL(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera, antes del descuento''' ELSE 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_precio ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Remitos
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_id');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_remitos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN presentacion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Presentación vendida (gestion__productos_presentaciones); NULL = por unidad''' ELSE 'SELECT ''gestion__ventas_remitos_detalles.presentacion_id ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_nombre');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_remitos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN presentacion_nombre VARCHAR(60) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación''' ELSE 'SELECT ''gestion__ventas_remitos_detalles.presentacion_nombre ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_factor');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_remitos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN presentacion_factor DECIMAL(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)''' ELSE 'SELECT ''gestion__ventas_remitos_detalles.presentacion_factor ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_cantidad');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_remitos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN presentacion_cantidad DECIMAL(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)''' ELSE 'SELECT ''gestion__ventas_remitos_detalles.presentacion_cantidad ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_precio');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_remitos_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_remitos_detalles ADD COLUMN presentacion_precio DECIMAL(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera, antes del descuento''' ELSE 'SELECT ''gestion__ventas_remitos_detalles.presentacion_precio ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Facturas
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_id');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_facturas_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN presentacion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Presentación vendida (gestion__productos_presentaciones); NULL = por unidad''' ELSE 'SELECT ''gestion__ventas_facturas_detalles.presentacion_id ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_nombre');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_facturas_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN presentacion_nombre VARCHAR(60) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación''' ELSE 'SELECT ''gestion__ventas_facturas_detalles.presentacion_nombre ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_factor');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_facturas_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN presentacion_factor DECIMAL(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)''' ELSE 'SELECT ''gestion__ventas_facturas_detalles.presentacion_factor ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_cantidad');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_facturas_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN presentacion_cantidad DECIMAL(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)''' ELSE 'SELECT ''gestion__ventas_facturas_detalles.presentacion_cantidad ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_precio');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles');
SET @sql := CASE WHEN @tabla = 0 THEN 'SELECT ''AVISO: no existe gestion__ventas_facturas_detalles''' WHEN @existe = 0 THEN 'ALTER TABLE gestion__ventas_facturas_detalles ADD COLUMN presentacion_precio DECIMAL(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera, antes del descuento''' ELSE 'SELECT ''gestion__ventas_facturas_detalles.presentacion_precio ya existe''' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT TABLE_NAME, COUNT(*) AS columnas_presentacion
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE 'presentacion\_%'
  AND TABLE_NAME IN ('gestion__ventas_pedidos_detalles', 'gestion__ventas_remitos_detalles', 'gestion__ventas_facturas_detalles')
GROUP BY TABLE_NAME;
