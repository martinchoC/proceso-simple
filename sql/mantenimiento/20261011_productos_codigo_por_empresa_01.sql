-- =====================================================================================================
-- Código de producto único POR EMPRESA (no global)
-- -----------------------------------------------------------------------------------------------------
-- gestion__productos tenía UNIQUE `producto_codigo` (producto_codigo) global: dos empresas no podían usar el
-- mismo código (los conceptos bancarios se habían creado como BCO_COMISION_E2 / _E6). Pasa a
-- UNIQUE `uk_productos_empresa_codigo` (empresa_id, producto_codigo).
--
-- Además renombra los conceptos bancarios creados con sufijo de empresa (BCO_xxx_E<empresa> -> BCO_xxx).
-- Correr ANTES de 20261011_debito_en_cuenta_02_resumen_bancario.sql si todavía no se corrió (ese script ya usa
-- los códigos sin sufijo y verifica que el índice global no exista).
-- Idempotente. MariaDB 10.4 y MySQL 8 (DDL no transaccional: se puede reanudar).
-- =====================================================================================================

DROP PROCEDURE IF EXISTS `_pc01_codigo_empresa`;
DELIMITER $$
CREATE PROCEDURE `_pc01_codigo_empresa`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;

    -- Duplicados dentro de una misma empresa impedirían el índice nuevo (con el global no puede haberlos)
    IF EXISTS (SELECT 1 FROM gestion__productos GROUP BY empresa_id, producto_codigo HAVING COUNT(*) > 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay códigos de producto repetidos dentro de una empresa: corregirlos antes';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'gestion__productos' AND INDEX_NAME = 'uk_productos_empresa_codigo') THEN
        ALTER TABLE gestion__productos ADD UNIQUE KEY uk_productos_empresa_codigo (empresa_id, producto_codigo);
    END IF;

    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'gestion__productos' AND INDEX_NAME = 'producto_codigo') THEN
        ALTER TABLE gestion__productos DROP INDEX producto_codigo;
    END IF;
END$$
DELIMITER ;

CALL `_pc01_codigo_empresa`();
DROP PROCEDURE IF EXISTS `_pc01_codigo_empresa`;

-- Conceptos bancarios con sufijo de empresa -> código limpio (si la empresa no tiene ya ese código)
UPDATE gestion__productos p
SET p.producto_codigo = LEFT(p.producto_codigo, CHAR_LENGTH(p.producto_codigo) - CHAR_LENGTH(CONCAT('_E', p.empresa_id)))
WHERE p.producto_codigo IN (CONCAT('BCO_COMISION_E', p.empresa_id), CONCAT('BCO_INTERES_E', p.empresa_id), CONCAT('BCO_LEY25413_E', p.empresa_id))
  AND NOT EXISTS (SELECT 1 FROM (SELECT empresa_id, producto_codigo FROM gestion__productos) x
                  WHERE x.empresa_id = p.empresa_id
                    AND x.producto_codigo = LEFT(p.producto_codigo, CHAR_LENGTH(p.producto_codigo) - CHAR_LENGTH(CONCAT('_E', p.empresa_id))));

-- Control (solo lectura)
SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas, MAX(NON_UNIQUE) AS non_unique
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND INDEX_NAME IN ('producto_codigo', 'uk_productos_empresa_codigo')
GROUP BY INDEX_NAME;
