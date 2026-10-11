-- =====================================================================
-- Parámetros de Empresa: LOGO para comprobantes impresos - script 01: COLUMNA
-- 2026-10-09
--
-- gestion__empresas_parametros.logo_archivo (Parámetros de Empresa, página 111):
--   ruta relativa a la raíz del proyecto (uploads/empresas/logo_<empresa>_<id>.<ext>) del
--   logo que se imprime arriba a la izquierda en las facturas de venta
--   (modules/gestion/ventas_facturas_print.php). NULL = sin logo.
-- Se lee de la vigencia abierta; si esa no tiene logo, del último registro activo que lo tenga.
-- Una vigencia nueva hereda el logo de la anterior si no se sube otro.
--
-- Reanudable (DDL no transaccional): agrega la columna solo si falta.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_epl01_columna`;
DELIMITER $$
CREATE PROCEDURE `_epl01_columna`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;
    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_parametros'
          AND COLUMN_NAME = 'logo_archivo') = 0 THEN
        ALTER TABLE gestion__empresas_parametros
            ADD COLUMN logo_archivo VARCHAR(255) NULL DEFAULT NULL
            COMMENT 'Logo para comprobantes impresos (ruta relativa a la raíz, uploads/empresas/)'
            AFTER arca_habilitado;
    END IF;
END$$
DELIMITER ;

CALL `_epl01_columna`();
DROP PROCEDURE IF EXISTS `_epl01_columna`;

-- Resumen (solo lectura)
SELECT empresa_id, empresa_parametro_id, f_desde, f_hasta, arca_habilitado, logo_archivo
FROM gestion__empresas_parametros
WHERE tabla_estado_registro_id = 1
ORDER BY empresa_id, f_desde DESC;
