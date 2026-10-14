-- ==============================================================================
-- 20261012_sindicatos_08_sucursales_empleadores.sql
-- Módulo 6 (Sindicatos): sucursales de empleadores numeradas.
--
-- - gestion__entidades_sucursales.sucursal_numero (NULL para clientes/proveedores del
--   módulo 2/5, que no lo usan; 1..n para empleadores y estudios del módulo 6).
-- - La sucursal 1 es la Casa Central: la crea el alta de todo empleador y no se puede
--   inhabilitar (sindi_guardar_sucursal_central() en sindi_base_model.php).
-- - Numera las sucursales existentes de empleadores/estudios (Casa Central primero) y
--   crea la Casa Central de los empleadores que no tienen ninguna.
--
-- Corre en MariaDB 10.4 y MySQL 8. Reanudable: cada paso verifica su estado.
-- ==============================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------------------
-- 1. Columna sucursal_numero
-- ------------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'gestion__entidades_sucursales'
                  AND COLUMN_NAME = 'sucursal_numero');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__entidades_sucursales ADD COLUMN sucursal_numero SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT ''Número de sucursal dentro de la entidad (1 = Casa Central). Lo usa el módulo 6'' AFTER empresa_id',
    'SELECT ''sucursal_numero ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------------------------
-- 2. Numerar las sucursales existentes de empleadores y estudios
--    (Casa Central primero, después por sucursal_id). Solo las que no tienen número.
-- ------------------------------------------------------------------------------
UPDATE gestion__entidades_sucursales s
INNER JOIN (
    SELECT x.sucursal_id,
           ROW_NUMBER() OVER (PARTITION BY x.entidad_id
                              ORDER BY (x.sucursal_nombre = 'Casa Central') DESC, x.sucursal_id) AS nro
    FROM gestion__entidades_sucursales x
    WHERE x.entidad_id IN (SELECT entidad_id FROM sindi__empleadores
                           UNION SELECT entidad_id FROM sindi__estudios)
      AND NOT EXISTS (SELECT 1 FROM gestion__entidades_sucursales y
                      WHERE y.entidad_id = x.entidad_id AND y.sucursal_numero IS NOT NULL)
) n ON n.sucursal_id = s.sucursal_id
SET s.sucursal_numero = n.nro;

-- ------------------------------------------------------------------------------
-- 3. Casa Central (número 1) para los empleadores que no tienen sucursales
-- ------------------------------------------------------------------------------
INSERT INTO gestion__entidades_sucursales
    (entidad_id, empresa_id, sucursal_numero, sucursal_nombre, sucursal_direccion, localidad_id, tabla_estado_registro_id)
SELECT e.entidad_id, emp.empresa_id, 1, 'Casa Central', e.domicilio_legal, e.localidad_id, 1
FROM sindi__empleadores emp
INNER JOIN gestion__entidades e ON e.entidad_id = emp.entidad_id
WHERE NOT EXISTS (SELECT 1 FROM gestion__entidades_sucursales s
                  WHERE s.entidad_id = emp.entidad_id AND s.sucursal_numero = 1);

-- ------------------------------------------------------------------------------
-- 4. Unicidad del número dentro de la entidad (los NULL de clientes/proveedores no chocan)
-- ------------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'gestion__entidades_sucursales'
                  AND INDEX_NAME = 'uk_ent_suc_numero');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__entidades_sucursales ADD UNIQUE KEY uk_ent_suc_numero (entidad_id, sucursal_numero)',
    'SELECT ''uk_ent_suc_numero ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------------------------
-- Verificación: todo empleador tiene su sucursal 1 y no hay números repetidos
-- ------------------------------------------------------------------------------
SELECT 'empleadores sin Casa Central' AS control, COUNT(*) AS cantidad
FROM sindi__empleadores emp
WHERE NOT EXISTS (SELECT 1 FROM gestion__entidades_sucursales s
                  WHERE s.entidad_id = emp.entidad_id AND s.sucursal_numero = 1)
UNION ALL
SELECT 'sucursales de empleadores sin número', COUNT(*)
FROM gestion__entidades_sucursales s
INNER JOIN sindi__empleadores emp ON emp.entidad_id = s.entidad_id
WHERE s.sucursal_numero IS NULL;
