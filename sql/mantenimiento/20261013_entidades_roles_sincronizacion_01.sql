-- ==============================================================================
-- Migración: Sincronización y formalización de roles de entidades
-- Fecha: 2026-10-13
-- Módulos: Gestión (mod 2) y Gestión Normal (mod 5)
-- Objetivo:
--   1. Completar catálogo de roles en gestion__roles_entidades (CLI, PRO, EMP, ORG, BAN, EMPL, EST, AFIL).
--   2. Optimizar índices en gestion__entidades_roles y gestion__roles_entidades.
--   3. Sincronizar hacia gestion__entidades_roles todas las entidades existentes con es_cliente = 1 y es_proveedor = 1.
-- ==============================================================================

-- 1. Actualizar y asegurar roles base en gestion__roles_entidades
INSERT INTO `gestion__roles_entidades` (`rol_entidad_id`, `rol_entidad_codigo`, `rol_entidad_nombre`, `rol_entidad_descripcion`) VALUES
  (1, 'CLI', 'Cliente', 'Entidad a la que se le venden bienes o servicios'),
  (2, 'PRO', 'Proveedor', 'Entidad que provee bienes o servicios'),
  (3, 'EMP', 'Empleado', 'Empleado o colaborador (anticipos, sueldos, rendiciones de gastos)'),
  (4, 'ORG', 'Organismo público / fiscal', 'Organismo público o fiscal (Estado nacional, provincial, municipal, ARCA, ARBA, etc.)'),
  (5, 'EMPL', 'Empleador', 'Empleador obligado a presentar DDJJ y aportar al sindicato'),
  (6, 'EST', 'Estudio contable', 'Estudio contable que representa y gestiona empleadores'),
  (7, 'AFIL', 'Afiliado', 'Afiliado al sindicato que paga su cuota en forma directa'),
  (8, 'BAN', 'Entidad bancaria / financiera', 'Entidad bancaria o financiera para cuentas, valores y créditos')
ON DUPLICATE KEY UPDATE 
  `rol_entidad_nombre` = VALUES(`rol_entidad_nombre`),
  `rol_entidad_descripcion` = VALUES(`rol_entidad_descripcion`);

-- 2. Asegurar índices en gestion__entidades_roles
SET @existe_idx_entidad_rol = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'gestion__entidades_roles' 
      AND INDEX_NAME = 'idx_entidad_rol'
);
SET @sql_idx1 = IF(@existe_idx_entidad_rol = 0, 
    'ALTER TABLE `gestion__entidades_roles` ADD INDEX `idx_entidad_rol` (`entidad_id`, `rol_entidad_id`, `f_baja`)', 
    'SELECT "Index idx_entidad_rol already exists"'
);
PREPARE stmt1 FROM @sql_idx1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @existe_idx_rol_entidad = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'gestion__entidades_roles' 
      AND INDEX_NAME = 'idx_rol_entidad'
);
SET @sql_idx2 = IF(@existe_idx_rol_entidad = 0, 
    'ALTER TABLE `gestion__entidades_roles` ADD INDEX `idx_rol_entidad` (`rol_entidad_id`, `entidad_id`)', 
    'SELECT "Index idx_rol_entidad already exists"'
);
PREPARE stmt2 FROM @sql_idx2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 3. Poblar gestion__entidades_roles para clientes existentes (es_cliente = 1)
INSERT INTO `gestion__entidades_roles` (`entidad_id`, `rol_entidad_id`, `f_alta`, `f_baja`, `tabla_estado_registro_id`)
SELECT 
    e.`entidad_id`,
    1 AS `rol_entidad_id`,
    IFNULL(DATE(e.`fecha_alta`), CURDATE()) AS `f_alta`,
    NULL AS `f_baja`,
    1 AS `tabla_estado_registro_id`
FROM `gestion__entidades` e
WHERE e.`es_cliente` = 1
  AND NOT EXISTS (
      SELECT 1 FROM `gestion__entidades_roles` er
      WHERE er.`entidad_id` = e.`entidad_id`
        AND er.`rol_entidad_id` = 1
        AND er.`f_baja` IS NULL
  );

-- 4. Poblar gestion__entidades_roles para proveedores existentes (es_proveedor = 1)
INSERT INTO `gestion__entidades_roles` (`entidad_id`, `rol_entidad_id`, `f_alta`, `f_baja`, `tabla_estado_registro_id`)
SELECT 
    e.`entidad_id`,
    2 AS `rol_entidad_id`,
    IFNULL(DATE(e.`fecha_alta`), CURDATE()) AS `f_alta`,
    NULL AS `f_baja`,
    1 AS `tabla_estado_registro_id`
FROM `gestion__entidades` e
WHERE e.`es_proveedor` = 1
  AND NOT EXISTS (
      SELECT 1 FROM `gestion__entidades_roles` er
      WHERE er.`entidad_id` = e.`entidad_id`
        AND er.`rol_entidad_id` = 2
        AND er.`f_baja` IS NULL
  );
