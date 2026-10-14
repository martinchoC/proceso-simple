-- =====================================================================
-- Ajuste de Puntos de Venta: Modalidad de Sistema de Emisión (ARCA / ex AFIP)
-- Fecha: 2026-10-13
--
-- Reglas de negocio (ARCA):
--   1. En ARCA, cada punto de venta queda estrictamente vinculado al sistema de emisión
--      con el que fue dado de alta ("Administración de Puntos de Venta y Domicilios").
--   2. Modalidades oficiales:
--      - WSFE: Web Service (Factura Electrónica) - ERPs / Sistemas de gestión (CAE por API).
--      - RCEL: Comprobantes en Línea (RCEL) - Facturación manual desde la web con clave fiscal.
--      - FISCAL: Controlador Fiscal - Equipos homologados (Nueva Tecnología).
--      - MOVIL: Facturador Móvil - App oficial de ARCA para celulares/tablets.
--      - TALONARIO: Comprobantes Manuales - Talonarios preimpresos con CAI (contingencia).
--      - NO_FISCAL: Comprobantes Internos - Remitos, presupuestos, recibos X, sin valor fiscal.
--   3. Exclusividad: No se puede emitir vía Web Service con un punto de venta dado de alta
--      en RCEL, Controlador Fiscal o Talonario.
--   4. Numeración independiente: Cada punto de venta numera de forma correlativa e independiente
--      sus comprobantes.
-- =====================================================================

START TRANSACTION;

-- Agregar columna sistema_emision si no existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'gestion__puntos_venta' 
      AND COLUMN_NAME = 'sistema_emision'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `gestion__puntos_venta` 
     ADD COLUMN `sistema_emision` VARCHAR(30) NOT NULL DEFAULT \'WSFE\' 
     COMMENT \'Modalidad ARCA: WSFE, RCEL, FISCAL, MOVIL, TALONARIO, NO_FISCAL\' 
     AFTER `codigo_fiscal`', 
    'SELECT "Columna sistema_emision ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Actualizar registros existentes
UPDATE `gestion__puntos_venta` 
SET `sistema_emision` = 'NO_FISCAL' 
WHERE (`codigo_fiscal` IS NULL OR `codigo_fiscal` >= 10000 OR `nombre` LIKE '%Interno%' OR `nombre` LIKE '%Info%');

UPDATE `gestion__puntos_venta` 
SET `sistema_emision` = 'WSFE', `es_web` = 1 
WHERE `sistema_emision` = 'WSFE';

COMMIT;
