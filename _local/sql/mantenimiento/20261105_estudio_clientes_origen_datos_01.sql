-- Estudio contable: origen de los datos del cliente y plantillas de alta de cliente.
-- origen_datos: LOCAL = el cliente es una empresa del ERP de este servidor (empresa_vinculada_id);
--               REMOTO = el cliente tiene su propio servidor (se registra la URL; la sincronización llega en una etapa posterior).
-- Reanudable y compatible con MySQL 8 (que no tiene ADD COLUMN IF NOT EXISTS).
SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'origen_datos');
SET @sql := IF(@existe = 0,
    'ALTER TABLE estudio__clientes ADD COLUMN origen_datos ENUM(''LOCAL'',''REMOTO'') NOT NULL DEFAULT ''LOCAL'' COMMENT ''LOCAL = empresa de este ERP; REMOTO = servidor propio del cliente'' AFTER empresa_vinculada_id',
    'SELECT ''estudio__clientes.origen_datos ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'remoto_url');
SET @sql := IF(@existe = 0,
    'ALTER TABLE estudio__clientes ADD COLUMN remoto_url VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''URL base del ERP del cliente (solo REMOTO)'' AFTER origen_datos',
    'SELECT ''estudio__clientes.remoto_url ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Empresas del ERP que sirven de modelo para dar de alta clientes simplificados (copia de empresa autorizada).
CREATE TABLE IF NOT EXISTS `estudio__plantillas_cliente` (
  `plantilla_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant del estudio',
  `empresa_origen_id` int(10) unsigned NOT NULL COMMENT 'Empresa modelo que se copia',
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`plantilla_id`),
  UNIQUE KEY `uq_estudio_origen` (`empresa_id`,`empresa_origen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
