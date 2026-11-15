-- Ficha de producto completa en gestion_normal (doc 26): indicador por empresa para las solapas Proveedores, Precios, Ubicaciones e Imágenes.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261121_ficha_producto_01.sql
-- Diseño en _local/docs/gestion/26_ficha_producto_completa.md (aprobado el 2026-11-15).
--
-- conf__empresas.usa_ficha_producto_completa (0/1, por defecto 0). Apagado, la ficha del módulo 5 queda como estaba.
-- Las solapas usan tablas que ya existen (gestion__productos_proveedores, gestion__listas_precios_productos,
-- gestion__productos_ubicaciones, gestion__productos_imagenes): no hay estructura nueva.
--
-- Solo agrega. Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0). Va ANTES del código.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_ficha_producto_completa');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_ficha_producto_completa TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = solapas Proveedores, Precios, Ubicaciones e Imágenes en la ficha de productos del módulo 5'' ',
    'SELECT ''conf__empresas.usa_ficha_producto_completa ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
