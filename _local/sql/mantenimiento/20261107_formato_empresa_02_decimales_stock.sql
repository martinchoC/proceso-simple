-- ==============================================================================
-- Formato por empresa, paso 2 (2026-11-07): decimales de las cantidades de stock.
--   conf__empresas.decimales_stock   0 a 4 (default 2)
-- Cuántos decimales se muestran y se cargan en las cantidades de stock (Ajustes de Stock y las pantallas
-- que usen Fmt.cantidad / fmt_cantidad). Se edita en Empresas (página 27), junto al formato de fechas y números.
-- Tope 4: el libro de stock (stock_core_model.php) redondea a 4 decimales.
-- Reanudable: el ALTER se salta si la columna ya existe. Corre en MariaDB 10.4 y MySQL 8.
-- Va ANTES del código (core/formato.php lee la columna; sin ella usa 2 y avisa en el log).
-- ==============================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'decimales_stock');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN decimales_stock TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT ''Decimales de las cantidades de stock en pantalla y en la carga (0 a 4)'' AFTER separador_decimal',
    'SELECT ''conf__empresas.decimales_stock ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT empresa_id, empresa, formato_fecha, separador_miles, separador_decimal, decimales_stock FROM conf__empresas ORDER BY empresa_id;
