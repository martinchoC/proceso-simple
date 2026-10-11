-- =====================================================================
-- Facturas de Clientes: CAE de ARCA al aprobar - script 02: PARÁMETRO DE HABILITACIÓN
-- 2026-10-07
--
-- gestion__empresas_parametros.arca_habilitado (Parámetros de Empresa, página 111):
--   0 = no se consume ARCA (default): la factura fiscal se numera al confirmar con la
--       numeración interna y "Aprobada" (4 -> 5) es manual, como antes del script 01.
--   1 = la factura fiscal se confirma sin número y "Aprobada" exige el CAE de ARCA
--       (vfArcaAutorizarFactura en ventas_facturas_model.php).
-- Se lee de la vigencia abierta (f_hasta IS NULL, activa); sin vigencia = no habilitado.
--
-- Reanudable (DDL no transaccional): agrega la columna solo si falta.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_vfa02_columna`;
DELIMITER $$
CREATE PROCEDURE `_vfa02_columna`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;
    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_parametros'
          AND COLUMN_NAME = 'arca_habilitado') = 0 THEN
        ALTER TABLE gestion__empresas_parametros
            ADD COLUMN arca_habilitado TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '1 = las facturas fiscales se confirman recién con el CAE de ARCA'
            AFTER leyenda_fiscal;
    END IF;
END$$
DELIMITER ;

CALL `_vfa02_columna`();
DROP PROCEDURE IF EXISTS `_vfa02_columna`;

-- Resumen (solo lectura)
SELECT empresa_id, empresa_parametro_id, f_desde, f_hasta, arca_habilitado
FROM gestion__empresas_parametros
WHERE tabla_estado_registro_id = 1
ORDER BY empresa_id, f_desde DESC;
