-- =====================================================================
-- 20261008_tesoreria_dashboard_01_ddl.sql
-- Módulo de Tesorería - Dashboard Financiero Caja y Bancos
-- Sistema gestion_multipyme
--
-- Agrega flag dinámico `es_caja_banco` en gestion__cont_cuentas para
-- evitar hardcodear cuentas contables en el dashboard financiero,
-- permitiendo que descubra automáticamente cuentas de Caja y Bancos.
-- =====================================================================

-- 1) Agregar columna es_caja_banco si no existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'gestion__cont_cuentas' 
      AND COLUMN_NAME = 'es_caja_banco'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `gestion__cont_cuentas` ADD COLUMN `es_caja_banco` TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1 = Cuenta líquida/financiera (Caja, Bancos, Valores en cartera, Billeteras). Base dinámica del Dashboard de Tesorería\' AFTER `es_imputable`',
    'SELECT "Columna es_caja_banco ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Crear índice si no existe
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'gestion__cont_cuentas' 
      AND INDEX_NAME = 'ix_cont_cuentas_caja_banco'
);

SET @sql_idx = IF(@idx_exists = 0,
    'ALTER TABLE `gestion__cont_cuentas` ADD INDEX `ix_cont_cuentas_caja_banco` (`empresa_id`, `es_caja_banco`, `tabla_estado_registro_id`)',
    'SELECT "Indice ix_cont_cuentas_caja_banco ya existe"'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- 3) Marcar cuentas asignadas en gestion__tesoreria_cuentas como es_caja_banco = 1
UPDATE `gestion__cont_cuentas` cc
INNER JOIN `gestion__tesoreria_cuentas` tc ON tc.cont_cuenta_id = cc.cont_cuenta_id
SET cc.es_caja_banco = 1;

-- 4) Marcar cuentas típicas de disponibilidades (código 1.1.01.%) que sean imputables
UPDATE `gestion__cont_cuentas`
SET `es_caja_banco` = 1
WHERE (`codigo` LIKE '1.1.01.%' OR `codigo` LIKE '1.1.1.%')
  AND `es_imputable` = 1;
