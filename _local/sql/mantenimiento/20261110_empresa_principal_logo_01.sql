-- ==============================================================================
-- Empresa principal del sistema y logo inicial (2026-11-10)
-- Agrega columna es_principal a conf__empresas:
--   1 = empresa principal del sistema (define el logo inicial de login y branding)
--   0 = empresa secundaria
-- Si ninguna empresa está marcada como principal, marca por defecto la empresa 2
-- (Casa Lucho, con logo configurado) o la primera empresa activa.
-- Reanudable: cada ALTER se salta si la columna ya existe.
-- ==============================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'es_principal');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN es_principal TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = empresa principal del sistema (define logo inicial y branding)'' AFTER logo_archivo',
    'SELECT ''conf__empresas.es_principal ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Si no hay ninguna empresa marcada como principal, marcar la empresa 2 si existe activa, o la primera activa con logo
SET @total_principales := (SELECT COUNT(*) FROM conf__empresas WHERE es_principal = 1);
SET @empresa_sugerida := (
    SELECT empresa_id FROM conf__empresas 
    WHERE tabla_estado_registro_id = 1
    ORDER BY (empresa_id = 2) DESC, (logo_archivo IS NOT NULL AND logo_archivo <> '') DESC, empresa_id ASC 
    LIMIT 1
);

SET @sql_upd := IF(@total_principales = 0 AND @empresa_sugerida IS NOT NULL,
    CONCAT('UPDATE conf__empresas SET es_principal = 1 WHERE empresa_id = ', @empresa_sugerida),
    'SELECT ''Ya existe empresa principal definida''');
PREPARE st_upd FROM @sql_upd; EXECUTE st_upd; DEALLOCATE PREPARE st_upd;

