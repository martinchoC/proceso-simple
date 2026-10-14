-- ==============================================================================
-- 20261012_sindicatos_16_sucursal_nomina.sql
-- Módulo 6 (Sindicatos): sucursal (establecimiento) del empleado en la nómina de la DDJJ.
--
-- - sindi__ddjj_empleados.sucursal_id y sindi__relaciones_laborales.sucursal_id
--   (gestion__entidades_sucursales del empleador, numeradas por el script 08).
-- - Los renglones y relaciones existentes quedan en la Casa Central (N° 1) de su empleador.
-- - El borrador de DDJJ valida que la sucursal sea del empleador; si no viene, usa la del último
--   renglón presentado de esa persona con ese empleador y, si no hay, la Casa Central.
--
-- Reanudable.
-- ==============================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sindi__ddjj_empleados' AND COLUMN_NAME = 'sucursal_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE sindi__ddjj_empleados ADD COLUMN sucursal_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Establecimiento del empleador donde trabaja (gestion__entidades_sucursales)'' AFTER persona_id, ADD KEY idx_ddjjemp_sucursal (sucursal_id)',
    'SELECT ''sindi__ddjj_empleados.sucursal_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sindi__relaciones_laborales' AND COLUMN_NAME = 'sucursal_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE sindi__relaciones_laborales ADD COLUMN sucursal_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Último establecimiento declarado en la DDJJ'' AFTER persona_id',
    'SELECT ''sindi__relaciones_laborales.sucursal_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Datos existentes: Casa Central del empleador
UPDATE sindi__ddjj_empleados de
INNER JOIN sindi__ddjj d ON d.ddjj_id = de.ddjj_id
INNER JOIN sindi__empleadores emp ON emp.empleador_id = d.empleador_id AND emp.empresa_id = d.empresa_id
INNER JOIN gestion__entidades_sucursales s ON s.entidad_id = emp.entidad_id AND s.sucursal_numero = 1
SET de.sucursal_id = s.sucursal_id
WHERE de.sucursal_id IS NULL;

UPDATE sindi__relaciones_laborales rl
INNER JOIN sindi__empleadores emp ON emp.empleador_id = rl.empleador_id AND emp.empresa_id = rl.empresa_id
INNER JOIN gestion__entidades_sucursales s ON s.entidad_id = emp.entidad_id AND s.sucursal_numero = 1
SET rl.sucursal_id = s.sucursal_id
WHERE rl.sucursal_id IS NULL;

SELECT 'renglones de DDJJ sin sucursal' AS control, COUNT(*) AS cantidad
FROM sindi__ddjj_empleados de INNER JOIN sindi__ddjj d ON d.ddjj_id = de.ddjj_id WHERE de.sucursal_id IS NULL
UNION ALL
SELECT 'renglones huérfanos (su DDJJ no existe)', COUNT(*)
FROM sindi__ddjj_empleados de LEFT JOIN sindi__ddjj d ON d.ddjj_id = de.ddjj_id WHERE d.ddjj_id IS NULL
UNION ALL
SELECT 'relaciones laborales sin sucursal', COUNT(*) FROM sindi__relaciones_laborales WHERE sucursal_id IS NULL;
