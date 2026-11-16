-- Canales (doc 28, paso 9-1): indicadores por empresa del carrito integrado al ERP y de la aplicación del vendedor.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261123_canales_carrito_app_01.sql
-- Diseño en _local/docs/gestion/28_canales_carrito_app.md (aprobado el 2026-11-15).
--
-- conf__empresas.usa_carrito_integrado (0/1, por defecto 0): el carrito (casalucho-carrito) calcula y graba el pedido
--   con el código del ERP (core/). Apagado, el carrito sigue como hoy.
-- conf__empresas.usa_app_vendedor (0/1, por defecto 0): habilita la aplicación del vendedor (app_vendedor/).
--
-- Solo agrega columnas con valor por defecto. Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0). Va ANTES del código.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_carrito_integrado');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_carrito_integrado TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = el carrito del cliente calcula y graba el pedido con el código del ERP (doc 28)''',
    'SELECT ''conf__empresas.usa_carrito_integrado ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_app_vendedor');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_app_vendedor TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = aplicación del vendedor para cargar pedidos de su cartera desde el celular (doc 28)''',
    'SELECT ''conf__empresas.usa_app_vendedor ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
