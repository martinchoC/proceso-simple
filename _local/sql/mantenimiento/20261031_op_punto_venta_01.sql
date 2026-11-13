-- =====================================================================================================
-- 20261031_op_punto_venta_01.sql
-- Punto de venta de la Orden de Pago automática de una factura de proveedor (débito en cuenta / contado).
-- Lo elige Gastos Bancarios al generar el Resumen bancario; NULL = lo resuelve pagoResolverPuntoVenta()
-- (un PV con la OP habilitada en gestion__puntos_venta_comprobantes o, si no hay ninguno, el de tesorería).
-- Correr ANTES de subir el código (fpGenerarOrdenPagoAutomatica lee la columna). Se puede reanudar.
-- =====================================================================================================

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'op_punto_venta_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__facturas_proveedores ADD COLUMN op_punto_venta_id INT NULL DEFAULT NULL COMMENT ''PV de la OP automática (NULL = automático)'' AFTER tesoreria_cuenta_debito_id',
    'SELECT ''op_punto_venta_id ya existe''');
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;
