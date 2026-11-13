-- Estudio contable: nivel de servicio del cliente y origen EXTERNO (software de terceros).
--   PADRON   = solo padrón, vencimientos, tareas y honorarios (sin empresa en el ERP)
--   FISCAL   = comprobantes, IVA, IIBB, percepciones (empresa SIMPLIFICADA, sin perfiles)
--   CONTABLE = además contabilidad y balances (empresa SIMPLIFICADA con perfiles)
--   GESTION  = gestión completa (empresa COMPLETA)
-- Requiere 20261105_estudio_clientes_origen_datos_01.sql. Reanudable.
-- Compatible con MySQL 8 (no tiene ADD COLUMN IF NOT EXISTS).
SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'nivel_servicio');
SET @sql := IF(@existe = 0,
    'ALTER TABLE estudio__clientes ADD COLUMN nivel_servicio ENUM(''PADRON'',''FISCAL'',''CONTABLE'',''GESTION'') NOT NULL DEFAULT ''PADRON'' COMMENT ''Qué servicio recibe el cliente; define qué datos necesita el estudio'' AFTER cliente_tipo_id',
    'SELECT ''estudio__clientes.nivel_servicio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

ALTER TABLE `estudio__clientes`
  MODIFY COLUMN `origen_datos` enum('LOCAL','REMOTO','EXTERNO') NOT NULL DEFAULT 'LOCAL'
  COMMENT 'LOCAL = empresa de este ERP; REMOTO = servidor propio del cliente; EXTERNO = software de terceros (importación de archivos)';

-- Clientes existentes: con empresa del ERP vinculada = CONTABLE; con comprobantes manuales cargados = FISCAL; el resto, PADRON.
UPDATE `estudio__clientes` SET `nivel_servicio` = 'CONTABLE'
 WHERE `empresa_vinculada_id` IS NOT NULL AND `empresa_vinculada_id` > 0 AND `nivel_servicio` = 'PADRON';
UPDATE `estudio__clientes` c SET c.`nivel_servicio` = 'FISCAL'
 WHERE c.`nivel_servicio` = 'PADRON'
   AND EXISTS (SELECT 1 FROM `estudio__minigestion_comprobantes` m WHERE m.`empresa_id` = c.`empresa_id` AND m.`cliente_id` = c.`cliente_id`);
