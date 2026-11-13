-- ============================================================================
-- Autorizaciones de copia entre empresas: permisos para copiar productos y
-- configuración de impuestos (gestion__empresas_autorizaciones_copia).
-- Las columnas ya las usa empresas_autorizaciones_copia_model.php y
-- empresa_inicio_model.php; existían en la base local sin script que las creara.
-- Fecha: 2026-11-12
-- Reanudable: cada columna se agrega solo si falta (information_schema). MariaDB 10.4 y MySQL 8.
-- ============================================================================

SET NAMES utf8mb4;

-- gestion__empresas_autorizaciones_copia.permite_impuestos_config
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_autorizaciones_copia' AND COLUMN_NAME = 'permite_impuestos_config');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__empresas_autorizaciones_copia ADD COLUMN permite_impuestos_config TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Autoriza copia de la configuración de impuestos'' AFTER permite_tesoreria',
    'SELECT ''gestion__empresas_autorizaciones_copia.permite_impuestos_config ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__empresas_autorizaciones_copia.permite_productos
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_autorizaciones_copia' AND COLUMN_NAME = 'permite_productos');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__empresas_autorizaciones_copia ADD COLUMN permite_productos TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Autoriza copia de productos'' AFTER permite_impuestos_config',
    'SELECT ''gestion__empresas_autorizaciones_copia.permite_productos ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
