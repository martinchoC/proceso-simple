-- ==============================================================================
-- Formato de fechas y números por empresa (2026-10-31)
-- Paso 1: columnas en conf__empresas (se editan en Empresas, página 27, solapa "Formato").
--   formato_fecha       d/m/Y (default) / m/d/Y / Y-m-d / d-m-Y / d.m.Y
--   separador_miles     PUNTO (default) / COMA / ESPACIO / NINGUNO
--   separador_decimal   COMA (default) / PUNTO
-- La lista blanca está en core/formato.php (FORMATOS_FECHA, SEPARADORES_MILES, SEPARADORES_DECIMAL).
-- Los separadores se guardan por código (no el carácter) para que el espacio no se pierda.
-- Reanudable: cada ALTER se salta si la columna ya existe. Corre en MariaDB 10.4 y MySQL 8.
-- ==============================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'formato_fecha');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN formato_fecha VARCHAR(10) NOT NULL DEFAULT ''d/m/Y'' COMMENT ''Formato de fecha en pantallas: d/m/Y, m/d/Y, Y-m-d, d-m-Y, d.m.Y'' AFTER tipo_operacion',
    'SELECT ''conf__empresas.formato_fecha ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'separador_miles');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN separador_miles VARCHAR(10) NOT NULL DEFAULT ''PUNTO'' COMMENT ''Separador de miles: PUNTO, COMA, ESPACIO, NINGUNO'' AFTER formato_fecha',
    'SELECT ''conf__empresas.separador_miles ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'separador_decimal');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN separador_decimal VARCHAR(10) NOT NULL DEFAULT ''COMA'' COMMENT ''Separador decimal: COMA, PUNTO'' AFTER separador_miles',
    'SELECT ''conf__empresas.separador_decimal ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT empresa_id, empresa, formato_fecha, separador_miles, separador_decimal FROM conf__empresas ORDER BY empresa_id;
