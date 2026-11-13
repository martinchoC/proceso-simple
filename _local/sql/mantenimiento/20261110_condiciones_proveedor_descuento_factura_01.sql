-- ============================================================================
-- Condiciones comerciales de proveedores: modalidad de descuento en facturas
-- y si el precio de compra (costo) contempla el descuento general del proveedor.
-- Fecha: 2026-11-10 (reescrito el 2026-11-11 sin ADD COLUMN IF NOT EXISTS, que MySQL 8 no acepta).
-- Reanudable: cada columna se agrega solo si falta (information_schema). MariaDB 10.4 y MySQL 8.
-- ============================================================================

SET NAMES utf8mb4;

-- gestion__entidades_condiciones_proveedores.modalidad_descuento_factura
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades_condiciones_proveedores' AND COLUMN_NAME = 'modalidad_descuento_factura');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__entidades_condiciones_proveedores ADD COLUMN modalidad_descuento_factura VARCHAR(30) NOT NULL DEFAULT ''EN_FACTURA_GLOBAL'' COMMENT ''EN_FACTURA_GLOBAL | EN_FACTURA_ITEM | NOTA_CREDITO | SIN_DESCUENTO'' AFTER proveedor_descuento_general',
    'SELECT ''gestion__entidades_condiciones_proveedores.modalidad_descuento_factura ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__entidades_condiciones_proveedores.precio_compra_contempla_descuento
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades_condiciones_proveedores' AND COLUMN_NAME = 'precio_compra_contempla_descuento');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__entidades_condiciones_proveedores ADD COLUMN precio_compra_contempla_descuento TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = El precio de compra/costo contempla el descuento general, 0 = No lo contempla'' AFTER modalidad_descuento_factura',
    'SELECT ''gestion__entidades_condiciones_proveedores.precio_compra_contempla_descuento ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores.modalidad_descuento_factura
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'modalidad_descuento_factura');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__facturas_proveedores ADD COLUMN modalidad_descuento_factura VARCHAR(30) DEFAULT ''EN_FACTURA_GLOBAL'' COMMENT ''EN_FACTURA_GLOBAL | EN_FACTURA_ITEM | NOTA_CREDITO | SIN_DESCUENTO'' AFTER descuento_general_pct',
    'SELECT ''gestion__facturas_proveedores.modalidad_descuento_factura ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores.precio_compra_contempla_descuento
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'precio_compra_contempla_descuento');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__facturas_proveedores ADD COLUMN precio_compra_contempla_descuento TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = El precio de compra/costo contempla el descuento general, 0 = No lo contempla'' AFTER modalidad_descuento_factura',
    'SELECT ''gestion__facturas_proveedores.precio_compra_contempla_descuento ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores.descuento_nc_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'descuento_nc_pct');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__facturas_proveedores ADD COLUMN descuento_nc_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT ''Porcentaje de descuento esperado por Nota de Crédito posterior cuando modalidad_descuento_factura = NOTA_CREDITO'' AFTER precio_compra_contempla_descuento',
    'SELECT ''gestion__facturas_proveedores.descuento_nc_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
