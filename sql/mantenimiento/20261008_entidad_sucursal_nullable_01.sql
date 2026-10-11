-- =====================================================================
-- entidad_sucursal_id / condicion_pago_id opcionales en pedidos de venta y asientos
-- 2026-10-08
--
-- Problema: la pantalla de pedidos (ventas_pedidos.js) permite elegir el
-- cliente "principal" (sin sucursal) y agregarPedidoVenta() /
-- editarPedidoVenta() guardan entidad_sucursal_id = NULL, pero la columna era
-- NOT NULL: el pedido fallaba con "Column 'entidad_sucursal_id' cannot be
-- null". Encontrado en la simulación de circuitos del 2026-10-08.
-- gestion__cont_asientos tenía la misma restricción. En el resto de las
-- tablas de documentos (facturas, remitos, cobranzas, pagos, comprobantes,
-- OC) la columna ya es NULL.
-- Igual con gestion__ventas_pedidos.condicion_pago_id: el combo no es
-- obligatorio, el modelo guarda NULL si viene vacío y la columna era NOT NULL
-- (en facturas, OC y facturas de proveedor ya es NULL).
--
-- Reanudable: MODIFY a la misma definición no cambia nada si ya se aplicó.
-- MariaDB 10.4 / MySQL 8.0.
-- =====================================================================

ALTER TABLE `gestion__ventas_pedidos`
    MODIFY `entidad_sucursal_id` INT(11) NULL DEFAULT NULL,
    MODIFY `condicion_pago_id` SMALLINT(6) NULL DEFAULT NULL;

ALTER TABLE `gestion__cont_asientos`
    MODIFY `entidad_sucursal_id` INT(11) NULL DEFAULT NULL;

-- Verificación: las tres deben quedar en YES
SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((COLUMN_NAME = 'entidad_sucursal_id' AND TABLE_NAME IN ('gestion__ventas_pedidos', 'gestion__cont_asientos'))
    OR (COLUMN_NAME = 'condicion_pago_id' AND TABLE_NAME = 'gestion__ventas_pedidos'));
