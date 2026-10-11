-- =====================================================================
-- Precios de compra: lista del proveedor y listas de venta - script 01: ESTRUCTURA
-- 2026-10-10
--
-- 1) gestion__empresas_parametros (Parámetros de Empresa, página 111), qué hace
--    la confirmación de una factura de proveedor (facturas_proveedores_model.php,
--    fpActualizarPreciosPorCompra) además de actualizar el costo, que ya hacía:
--    - compra_actualiza_lista_proveedor:
--        0 = no toca la lista del proveedor (comportamiento anterior)
--        1 = carga el precio de la factura solo en los productos que no tienen
--            precio en la lista de ese proveedor
--        2 = siempre: también pisa el precio existente con el de la factura
--      (precio de lista = precio unitario de la línea, antes de descuentos)
--    - compra_actualiza_listas_venta:
--        0 = no recalcula las listas de venta (comportamiento anterior)
--        1 = recalcula las listas de venta con regla aplicable usando el costo
--            nuevo de la compra (mismo motor que "Actualizar Precios de Venta"
--            de Lista Precios Proveedores)
--    Se leen de la vigencia abierta. Default 0 = sin cambios de comportamiento.
--
-- 2) origen_carga de gestion__productos_proveedores_precios (+ _historico) suma:
--    - ESTIMADO: precio deducido del precio de venta o del costo con el botón
--      "Armar lista desde precios" (proveedor_precios.php)
--    - COMPRA:   precio tomado de una factura de proveedor confirmada
--
-- Reanudable (DDL no transaccional): cada paso se aplica solo si falta.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_pc01_estructura`;
DELIMITER $$
CREATE PROCEDURE `_pc01_estructura`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;

    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_parametros'
          AND COLUMN_NAME = 'compra_actualiza_lista_proveedor') = 0 THEN
        ALTER TABLE gestion__empresas_parametros
            ADD COLUMN compra_actualiza_lista_proveedor TINYINT(1) NOT NULL DEFAULT 0
            COMMENT 'Factura de proveedor confirmada: 0 = no toca la lista del proveedor, 1 = solo carga los que no tienen precio, 2 = siempre (pisa)'
            AFTER arca_habilitado;
    END IF;

    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_parametros'
          AND COLUMN_NAME = 'compra_actualiza_listas_venta') = 0 THEN
        ALTER TABLE gestion__empresas_parametros
            ADD COLUMN compra_actualiza_listas_venta TINYINT(1) NOT NULL DEFAULT 0
            COMMENT 'Factura de proveedor confirmada: 1 = recalcula las listas de venta por regla con el costo nuevo'
            AFTER compra_actualiza_lista_proveedor;
    END IF;

    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores_precios'
          AND COLUMN_NAME = 'origen_carga' AND COLUMN_TYPE LIKE '%ESTIMADO%') = 0 THEN
        ALTER TABLE gestion__productos_proveedores_precios
            MODIFY COLUMN origen_carga ENUM('MANUAL','IMPORTACION','ESTIMADO','COMPRA')
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL';
    END IF;

    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores_precios_historico'
          AND COLUMN_NAME = 'origen_carga' AND COLUMN_TYPE LIKE '%ESTIMADO%') = 0 THEN
        ALTER TABLE gestion__productos_proveedores_precios_historico
            MODIFY COLUMN origen_carga ENUM('MANUAL','IMPORTACION','ESTIMADO','COMPRA')
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
    END IF;
END$$
DELIMITER ;

CALL `_pc01_estructura`();
DROP PROCEDURE IF EXISTS `_pc01_estructura`;

-- Resumen (solo lectura)
SELECT empresa_id, empresa_parametro_id, f_desde, f_hasta,
       compra_actualiza_lista_proveedor, compra_actualiza_listas_venta
FROM gestion__empresas_parametros
WHERE tabla_estado_registro_id = 1
ORDER BY empresa_id, f_desde DESC;

SELECT TABLE_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'origen_carga'
  AND TABLE_NAME IN ('gestion__productos_proveedores_precios', 'gestion__productos_proveedores_precios_historico');
