-- =====================================================================
-- Notas de crédito: comprobante asociado, aplicación y devolución
-- 2026-10-09
--
-- Decisiones (Pablo, 2026-10-09):
--   * La NC/ND guarda el comprobante al que se asocia
--     (comprobante_asociado_id -> gestion__comprobantes). Al confirmar la NC,
--     si ese comprobante tiene saldo se le aplica (imputación directa NC ->
--     comprobante, D7) y se emite un recibo (ventas) / OP (compras) en $0 que
--     documenta la aplicación. Si no tiene saldo, la NC queda con saldo.
--   * Al confirmar una factura, si la entidad tiene NC con saldo aplicables,
--     la pantalla ofrece imputarlas (mismo mecanismo, con recibo/OP en $0).
--   * Devolución de dinero de una NC con saldo: Órdenes de Pago al cliente
--     (OP -> NC de venta) y Cobranzas del proveedor (recibo -> NC de proveedor).
--     Se agregan esas reglas en gestion__comprobantes_tipos_cancelaciones.
--
--   * Venta de contado a consumidor final: al confirmarse se emite un recibo
--     automático en efectivo. gestion__cobranzas.entidad_id pasa a NULL para
--     ese recibo (la FK fk_cob_entidad_id admite NULL); en gestion__comprobantes
--     el consumidor final sigue con entidad_id = 0, como las facturas.
--
-- Reanudable. MariaDB 10.4 / MySQL 8.0.
-- =====================================================================

-- 1) Comprobante asociado (ventas y proveedores)
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas' AND COLUMN_NAME = 'comprobante_asociado_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ventas_facturas` ADD COLUMN `comprobante_asociado_id` INT(11) NULL DEFAULT NULL COMMENT ''NC/ND: gestion__comprobantes del comprobante asociado'' AFTER `comprobante_id`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'comprobante_asociado_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__facturas_proveedores` ADD COLUMN `comprobante_asociado_id` INT(11) NULL DEFAULT NULL COMMENT ''NC/ND: gestion__comprobantes del comprobante asociado'' AFTER `comprobante_id`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1b) Recibo de consumidor final sin entidad
ALTER TABLE `gestion__cobranzas` MODIFY `entidad_id` INT(10) UNSIGNED NULL DEFAULT NULL;

-- 2) Devoluciones: la OP cancela NC de venta y el recibo cancela NC de proveedor
--    (por código de tipo, para cada empresa que ya tenga las reglas de OP / RCX)
INSERT INTO gestion__comprobantes_tipos_cancelaciones
    (empresa_id, comprobante_tipo_origen_id, comprobante_tipo_destino_id, exige_misma_entidad, tabla_estado_registro_id)
SELECT DISTINCT tc.empresa_id, tc.comprobante_tipo_origen_id, nc.comprobante_tipo_id, 1, 1
FROM gestion__comprobantes_tipos_cancelaciones tc
JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id AND o.codigo = 'OP'
JOIN gestion__comprobantes_tipos nc ON nc.comprobante_subgrupo_id = 1 AND nc.signo = '-' AND nc.tabla_estado_registro_id = 1
WHERE NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos_cancelaciones x
                  WHERE x.empresa_id = tc.empresa_id AND x.comprobante_tipo_origen_id = tc.comprobante_tipo_origen_id
                    AND x.comprobante_tipo_destino_id = nc.comprobante_tipo_id);

INSERT INTO gestion__comprobantes_tipos_cancelaciones
    (empresa_id, comprobante_tipo_origen_id, comprobante_tipo_destino_id, exige_misma_entidad, tabla_estado_registro_id)
SELECT DISTINCT tc.empresa_id, tc.comprobante_tipo_origen_id, nc.comprobante_tipo_id, 1, 1
FROM gestion__comprobantes_tipos_cancelaciones tc
JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id AND o.codigo = 'RCX'
JOIN gestion__comprobantes_tipos nc ON nc.comprobante_subgrupo_id = 5 AND nc.signo = '-' AND nc.tabla_estado_registro_id = 1
WHERE NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos_cancelaciones x
                  WHERE x.empresa_id = tc.empresa_id AND x.comprobante_tipo_origen_id = tc.comprobante_tipo_origen_id
                    AND x.comprobante_tipo_destino_id = nc.comprobante_tipo_id);

-- Verificación
SELECT 'columnas' AS paso, COUNT(*) AS n FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'comprobante_asociado_id'
  AND TABLE_NAME IN ('gestion__ventas_facturas', 'gestion__facturas_proveedores')
UNION ALL
SELECT CONCAT(o.codigo, ' -> ', d.comprobante_tipo, ' (', d.comprobante_tipo_id, ')'), tc.empresa_id
FROM gestion__comprobantes_tipos_cancelaciones tc
JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id
JOIN gestion__comprobantes_tipos d ON d.comprobante_tipo_id = tc.comprobante_tipo_destino_id
WHERE o.codigo IN ('OP', 'RCX') AND d.signo = '-';
