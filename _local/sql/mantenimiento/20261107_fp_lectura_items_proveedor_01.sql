-- Carga de facturas de proveedores desde archivo: si se cargan los ítems del comprobante producto por producto.
-- gestion__entidades.compra_lectura_items: 1 = sí (como hasta ahora); 0 = no (teléfono, gas, luz, servicios: una
-- línea por alícuota con el producto de imputación del proveedor, aunque la lectura traiga ítems).
-- Se edita en Proveedores (Observaciones Generales) y en el panel de lectura del formulario de la factura.
-- Reanudable y compatible con MySQL 8 (que no tiene ADD COLUMN IF NOT EXISTS).
SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND COLUMN_NAME = 'compra_lectura_items');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__entidades ADD COLUMN compra_lectura_items TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Lectura de facturas de compra desde archivo: 1 = cargar los ítems producto por producto, 0 = una línea por alícuota'' AFTER observaciones',
    'SELECT ''gestion__entidades.compra_lectura_items ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
