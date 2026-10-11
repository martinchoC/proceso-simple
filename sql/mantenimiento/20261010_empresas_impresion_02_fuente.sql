-- =====================================================================
-- Configuración de impresión de comprobantes por empresa - script 02: TIPOGRAFÍA
-- 2026-10-10
--
-- gestion__empresas_impresion.fuente: clave del catálogo IMPRESION_FUENTES de core/impresion.php
-- (roboto, open_sans, lato, source_sans, inter, nunito_sans, roboto_condensed, arial).
-- NULL = la de por defecto (roboto). Las fuentes se cargan de Google Fonts al imprimir.
--
-- Reanudable (DDL no transaccional): agrega la columna solo si falta.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción). Requiere el script 01.
-- =====================================================================

DROP PROCEDURE IF EXISTS `_eimp02_columna`;
DELIMITER $$
CREATE PROCEDURE `_eimp02_columna`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;
    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_impresion'
          AND COLUMN_NAME = 'fuente') = 0 THEN
        ALTER TABLE gestion__empresas_impresion
            ADD COLUMN fuente VARCHAR(30) NULL DEFAULT NULL
            COMMENT 'Tipografía (clave de IMPRESION_FUENTES en core/impresion.php); NULL = roboto'
            AFTER color_acento;
    END IF;
END$$
DELIMITER ;

CALL `_eimp02_columna`();
DROP PROCEDURE IF EXISTS `_eimp02_columna`;

-- Resumen (solo lectura)
SELECT empresa_id, comprobante_subgrupo_id, formato, fuente, tabla_estado_registro_id
FROM gestion__empresas_impresion
ORDER BY empresa_id, comprobante_subgrupo_id;
