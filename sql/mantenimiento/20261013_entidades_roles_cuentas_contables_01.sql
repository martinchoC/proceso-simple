-- ==============================================================================
-- Migración: Cuentas contables por rol de entidad y configuración por empresa
-- Fecha: 2026-10-13
-- Módulos: Gestión (mod 2) y Gestión Normal (mod 5)
-- Objetivo:
--   1. Agregar cont_cuenta_id a gestion__entidades_roles.
--   2. Migrar cont_cuenta_id_cliente y cont_cuenta_id_proveedor existentes a gestion__entidades_roles.
--   3. Crear tabla gestion__empresas_roles_cuentas para cuentas por defecto por empresa y rol.
--   4. Sembrar cuentas por defecto para las empresas activas (empresa 2, 6, etc.).
-- ==============================================================================

-- 1. Agregar columna cont_cuenta_id a gestion__entidades_roles si no existe
SET @existe_col_cont_cuenta = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'gestion__entidades_roles' 
      AND COLUMN_NAME = 'cont_cuenta_id'
);
SET @sql_col1 = IF(@existe_col_cont_cuenta = 0, 
    'ALTER TABLE `gestion__entidades_roles` ADD COLUMN `cont_cuenta_id` INT(10) UNSIGNED DEFAULT NULL AFTER `rol_entidad_id`', 
    'SELECT "Columna cont_cuenta_id ya existe"'
);
PREPARE stmt1 FROM @sql_col1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- Índice para cont_cuenta_id en gestion__entidades_roles
SET @existe_idx_cuenta = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'gestion__entidades_roles' 
      AND INDEX_NAME = 'idx_er_cuenta'
);
SET @sql_idx1 = IF(@existe_idx_cuenta = 0, 
    'ALTER TABLE `gestion__entidades_roles` ADD INDEX `idx_er_cuenta` (`cont_cuenta_id`)', 
    'SELECT "Indice idx_er_cuenta ya existe"'
);
PREPARE stmt_idx1 FROM @sql_idx1;
EXECUTE stmt_idx1;
DEALLOCATE PREPARE stmt_idx1;

-- 2. Migrar cuentas existentes de gestion__entidades a gestion__entidades_roles
-- Para clientes (rol 1):
UPDATE `gestion__entidades_roles` er
JOIN `gestion__entidades` e ON er.`entidad_id` = e.`entidad_id`
SET er.`cont_cuenta_id` = e.`cont_cuenta_id_cliente`
WHERE er.`rol_entidad_id` = 1 
  AND e.`cont_cuenta_id_cliente` IS NOT NULL
  AND er.`cont_cuenta_id` IS NULL;

-- Para proveedores (rol 2):
UPDATE `gestion__entidades_roles` er
JOIN `gestion__entidades` e ON er.`entidad_id` = e.`entidad_id`
SET er.`cont_cuenta_id` = e.`cont_cuenta_id_proveedor`
WHERE er.`rol_entidad_id` = 2 
  AND e.`cont_cuenta_id_proveedor` IS NOT NULL
  AND er.`cont_cuenta_id` IS NULL;

-- 3. Crear tabla gestion__empresas_roles_cuentas
CREATE TABLE IF NOT EXISTS `gestion__empresas_roles_cuentas` (
  `empresa_rol_cuenta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `rol_entidad_id` tinyint(3) unsigned NOT NULL,
  `cont_cuenta_id` int(10) unsigned NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`empresa_rol_cuenta_id`),
  UNIQUE KEY `uk_empresa_rol` (`empresa_id`, `rol_entidad_id`),
  KEY `ix_erc_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_erc_rol` FOREIGN KEY (`rol_entidad_id`) REFERENCES `gestion__roles_entidades` (`rol_entidad_id`),
  CONSTRAINT `fk_erc_cuenta` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas contables por defecto de la empresa para cada rol de entidad';

-- 4. Sembrar cuentas por defecto para Empresa 2 (Casa Lucho)
-- Rol 1 (CLI): 111 (1.1.02.01 - Deudores por Ventas)
-- Rol 2 (PRO): 301 (2.1.01.01 - Proveedores Nacionales)
-- Rol 3 (EMP): 856 (1.1.03.09 - Empleados - Anticipos y Rendiciones)
-- Rol 4 (ORG): 332 (2.1.04.02 - IVA a Pagar / Organismos)
-- Rol 8 (BAN): 851 (1.1.01.03 - Valores a Depositar)
INSERT INTO `gestion__empresas_roles_cuentas` (`empresa_id`, `rol_entidad_id`, `cont_cuenta_id`, `tabla_estado_registro_id`)
VALUES 
  (2, 1, 111, 1),
  (2, 2, 301, 1),
  (2, 3, 856, 1),
  (2, 4, 332, 1),
  (2, 8, 851, 1)
ON DUPLICATE KEY UPDATE `cont_cuenta_id` = VALUES(`cont_cuenta_id`);

-- Sembrar cuentas por defecto para Empresa 6 (Developsam) desde gestion__empresas_cuentas_conceptos si existen
INSERT INTO `gestion__empresas_roles_cuentas` (`empresa_id`, `rol_entidad_id`, `cont_cuenta_id`, `tabla_estado_registro_id`)
SELECT 6, 1, cont_cuenta_id, 1 
FROM gestion__empresas_cuentas_conceptos 
WHERE empresa_id = 6 AND concepto = 'DEUDORES_VENTAS' AND cont_cuenta_id IS NOT NULL
ON DUPLICATE KEY UPDATE `cont_cuenta_id` = VALUES(`cont_cuenta_id`);

INSERT INTO `gestion__empresas_roles_cuentas` (`empresa_id`, `rol_entidad_id`, `cont_cuenta_id`, `tabla_estado_registro_id`)
SELECT 6, 2, cont_cuenta_id, 1 
FROM gestion__empresas_cuentas_conceptos 
WHERE empresa_id = 6 AND concepto = 'VALORES_A_PAGAR' AND cont_cuenta_id IS NOT NULL
ON DUPLICATE KEY UPDATE `cont_cuenta_id` = VALUES(`cont_cuenta_id`);

INSERT INTO `gestion__empresas_roles_cuentas` (`empresa_id`, `rol_entidad_id`, `cont_cuenta_id`, `tabla_estado_registro_id`)
SELECT 6, 3, cont_cuenta_id, 1 
FROM gestion__empresas_cuentas_conceptos 
WHERE empresa_id = 6 AND concepto = 'EMPLEADOS' AND cont_cuenta_id IS NOT NULL
ON DUPLICATE KEY UPDATE `cont_cuenta_id` = VALUES(`cont_cuenta_id`);
