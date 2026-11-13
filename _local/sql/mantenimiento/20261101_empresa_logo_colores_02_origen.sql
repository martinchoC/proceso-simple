-- ==============================================================================
-- Logo de la empresa y colores del encabezado, paso 2 (2026-11-01): de dónde salieron los colores.
--   color_logo_origen   IA      los propuso la IA de la empresa (o la del servidor) al cambiar el logo
--                       AUTO    los calculó el servidor (GD): sin IA, la IA falló, o cálculo inicial
--                       MANUAL  los ajustó un usuario en Empresas (conf); un logo nuevo los vuelve a calcular
-- core/empresa_logo.php: emp_logo_calcular() / emp_logo_fijar_manual() / emp_logo_recalcular().
-- Requiere el paso 1 (20261101_empresa_logo_colores_01.sql).
-- Reanudable. Corre en MariaDB 10.4 y MySQL 8.
-- ==============================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'color_logo_origen');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN color_logo_origen VARCHAR(10) NULL COMMENT ''Origen de los colores del logo: IA, AUTO, MANUAL'' AFTER color_logo_claro',
    'SELECT ''conf__empresas.color_logo_origen ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Los colores que ya estaban los calculó el servidor
UPDATE conf__empresas SET color_logo_origen = 'AUTO'
WHERE color_logo_origen IS NULL AND color_logo_oscuro IS NOT NULL AND color_logo_oscuro <> '';

SELECT empresa_id, empresa, logo_archivo, color_logo_oscuro, color_logo_claro, color_logo_origen FROM conf__empresas ORDER BY empresa_id;
