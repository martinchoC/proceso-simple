-- =====================================================================================================
-- Recibo en $0 de la aplicación de notas de crédito de venta: detalle de comprobantes
-- -----------------------------------------------------------------------------------------------------
-- La aplicación NC -> factura es una imputación directa (origen = NC, destino = factura,
-- tesoreriaAplicarNotaCredito) y el recibo en $0 que la documenta no tenía imputaciones propias: su detalle
-- salía vacío y anularlo no deshacía la aplicación.
-- gestion__comprobantes_imputaciones.documento_comprobante_id = comprobante del recibo que documenta la
-- imputación (NULL en las demás). Con ella:
--   - obtenerCobranzaDetalle() muestra la factura en positivo y la NC en negativo (recibo e impresión);
--   - anularCobranza() anula también esas imputaciones (la factura y la NC recuperan su saldo).
-- Las aplicaciones anteriores a este script no quedan ligadas a su recibo.
-- Reanudable (el DDL no es transaccional). MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_imputaciones' AND COLUMN_NAME = 'documento_comprobante_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__comprobantes_imputaciones ADD COLUMN documento_comprobante_id INT NULL DEFAULT NULL COMMENT ''Recibo en $0 que documenta la imputación (aplicación de NC)'' AFTER importe_origen',
    'SELECT ''documento_comprobante_id ya existe''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_imputaciones' AND INDEX_NAME = 'idx_imp_documento');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__comprobantes_imputaciones ADD INDEX idx_imp_documento (documento_comprobante_id)',
    'SELECT ''idx_imp_documento ya existe''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Control
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_imputaciones' AND COLUMN_NAME = 'documento_comprobante_id';
