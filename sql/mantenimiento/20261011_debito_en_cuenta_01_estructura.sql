-- =====================================================================================================
-- Débito directo en cuenta bancaria para comprobantes de compra - script 01: ESTRUCTURA
-- -----------------------------------------------------------------------------------------------------
-- Gastos que el banco (u otro proveedor por débito automático) cobra directo de la cuenta: se cargan como
-- comprobante de compra (entran al Libro IVA Compras con su IVA y percepciones) y al confirmarlos se
-- cancelan solos con una OP automática que egresa de la cuenta bancaria indicada (medio "Débito en cuenta").
--
--   gestion__comprobantes_tipos.requiere_debito_cuenta   1 = el tipo exige la cuenta debitada (Resumen bancario)
--   gestion__facturas_proveedores.tesoreria_cuenta_debito_id   cuenta BANCO de tesorería debitada (NULL = pago normal)
--
-- Idempotente (columnas por INFORMATION_SCHEMA). MariaDB 10.4 y MySQL 8. Correr antes del código.
-- =====================================================================================================

DROP PROCEDURE IF EXISTS `_dc01_estructura`;
DELIMITER $$
CREATE PROCEDURE `_dc01_estructura`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;

    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'gestion__comprobantes_tipos' AND COLUMN_NAME = 'requiere_debito_cuenta') = 0 THEN
        ALTER TABLE gestion__comprobantes_tipos
            ADD COLUMN requiere_debito_cuenta TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '1 = comprobante de compra que se cancela por débito directo en una cuenta bancaria (obligatoria)'
            AFTER impacta_ctacte;
    END IF;

    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'tesoreria_cuenta_debito_id') = 0 THEN
        ALTER TABLE gestion__facturas_proveedores
            ADD COLUMN tesoreria_cuenta_debito_id INT UNSIGNED NULL DEFAULT NULL
            COMMENT 'Cuenta BANCO de tesorería debitada: al confirmar se emite la OP automática (NULL = pago normal)'
            AFTER condicion_pago_id;
    END IF;
END$$
DELIMITER ;

CALL `_dc01_estructura`();
DROP PROCEDURE IF EXISTS `_dc01_estructura`;
