-- -----------------------------------------------------------------------------------------------------------------
-- 20261111_ia_empresa_01.sql
-- Uso de la IA definido por empresa en Configuración > Empresas (modules/conf/empresas.php, core/ia_empresa.php).
--
-- conf__empresas.ia_habilitada: interruptor único para todos los módulos (lectura de facturas y de extractos,
--   asistente, constancia de CUIT, colores del logo). Por defecto habilitada.
-- conf__empresas.ia_api_key_cifrada: clave de la API (Gemini / Claude) cifrada con core/secretos.php. Vacía = se usa
--   la de la empresa principal (es_principal = 1) y, si tampoco tiene, la del servidor (FACTURA_LECTURA_CONFIG).
--
-- La clave que ya estaba en gestion__empresas_parametros (Parámetros de Empresa) se copia tal cual: está cifrada con
-- la misma SINDI_CLAVE_CIFRADO del sistema. La columna vieja queda (no se borra) pero el código ya no la lee.
-- Los topes diarios siguen en Parámetros de Empresa.
--
-- Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0).
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Columnas
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'ia_habilitada') = 0,
    "ALTER TABLE `conf__empresas` ADD COLUMN `ia_habilitada` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Usa la IA en todos los módulos (core/ia_empresa.php)'",
    "SELECT 'ia_habilitada ya existe'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'ia_api_key_cifrada') = 0,
    "ALTER TABLE `conf__empresas` ADD COLUMN `ia_api_key_cifrada` VARCHAR(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clave de la API de la IA, cifrada (core/secretos.php); vacía = la de la empresa principal' AFTER `ia_habilitada`",
    "SELECT 'ia_api_key_cifrada ya existe'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Copiar la clave de Parámetros de Empresa (la vigencia más reciente que tenga una), solo si la empresa no tiene
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'gestion__empresas_parametros' AND COLUMN_NAME = 'ia_api_key_cifrada') = 1,
    "UPDATE `conf__empresas` e
        JOIN `gestion__empresas_parametros` ep ON ep.`empresa_parametro_id` = (
            SELECT ep2.`empresa_parametro_id` FROM `gestion__empresas_parametros` ep2
             WHERE ep2.`empresa_id` = e.`empresa_id` AND ep2.`ia_api_key_cifrada` IS NOT NULL AND ep2.`ia_api_key_cifrada` <> ''
             ORDER BY ep2.`f_desde` DESC, ep2.`empresa_parametro_id` DESC LIMIT 1)
        SET e.`ia_api_key_cifrada` = ep.`ia_api_key_cifrada`
      WHERE e.`ia_api_key_cifrada` IS NULL OR e.`ia_api_key_cifrada` = ''",
    "SELECT 'sin clave en parámetros'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
