-- =====================================================================================================
-- Débito directo en cuenta bancaria - script 02: RESUMEN BANCARIO (tipo, medio de pago y conceptos)
-- -----------------------------------------------------------------------------------------------------
-- Requiere 20261011_debito_en_cuenta_01_estructura.sql y 20261011_productos_codigo_por_empresa_01.sql
-- (código de producto único por empresa: los conceptos BCO_* se repiten en cada empresa).
--
-- 1) Activa el comprobante fiscal ARCA 039 "Otros comprobantes A que cumplen con la RG 1415" (el resumen de
--    cuenta del banco como respaldo del crédito fiscal; validar con el contador el código del Libro IVA Digital).
-- 2) Por cada empresa con subgrupo FC (Facturas de Compra): tipo "Resumen bancario" (código C_RB, letra A,
--    signo +, fiscal 039, sin stock, con contabilidad y cuenta corriente, requiere_debito_cuenta = 1),
--    en el mismo grupo que su Factura A (C_FA).
-- 3) Por cada empresa con medio TRANSFER: medio "Débito en cuenta" (DEB_CTA, tipo TRANSFERENCIA, cuenta BANCO,
--    solo pagos). Lo usa la OP automática del débito.
-- 4) Conceptos de servicio (sin stock) para cargar el resumen, por empresa que tenga las cuentas del plan
--    6.3.01 Intereses Bancarios y 6.3.02 Gastos Bancarios (Casa Lucho y la demo clonada): crea la cuenta
--    6.3.05 "Impuesto s/ Débitos y Créditos Bancarios" (ley 25.413) y los productos
--      BCO_COMISION  Comisiones y gastos bancarios        IVA 21 %      -> 6.3.02
--      BCO_INTERES   Intereses bancarios                  IVA 21 %      -> 6.3.01  (cambiar la alícuota en la línea si es 10,5 %)
--      BCO_LEY25413  Impuesto s/ débitos y créditos       No gravado    -> 6.3.05
--    Las percepciones de IVA / IIBB del banco van como impuestos adicionales del comprobante.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

-- 0) El código de producto tiene que ser único por empresa (script productos_codigo_por_empresa_01)
DROP PROCEDURE IF EXISTS `_dc02_requisitos`;
DELIMITER $$
CREATE PROCEDURE `_dc02_requisitos`()
BEGIN
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'gestion__productos' AND INDEX_NAME = 'producto_codigo') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Correr antes 20261011_productos_codigo_por_empresa_01.sql';
    END IF;
END$$
DELIMITER ;
CALL `_dc02_requisitos`();
DROP PROCEDURE IF EXISTS `_dc02_requisitos`;

-- 1) Comprobante fiscal 039
UPDATE gestion__comprobantes_fiscales SET tabla_estado_registro_id = 1 WHERE codigo = '039';

-- 2) Tipo "Resumen bancario"
INSERT INTO gestion__comprobantes_tipos
    (empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id, impacta_stock, stock_signo,
     impacta_contabilidad, impacta_ctacte, requiere_debito_cuenta, comprobante_tipo, codigo, letra, signo, comentario, orden,
     tabla_estado_registro_id)
SELECT sg.empresa_id, fa.comprobante_grupo_id, sg.comprobante_subgrupo_id, cf.comprobante_fiscal_id, 0, NULL,
       1, 1, 1, 'Resumen bancario', 'C_RB', 'A', '+',
       'Resumen de cuenta del banco (RG 1415, cód. 039): se cancela por débito en cuenta', 200, 1
FROM gestion__comprobantes_subgrupos sg
INNER JOIN gestion__comprobantes_tipos fa ON fa.empresa_id = sg.empresa_id AND fa.codigo = 'C_FA'
INNER JOIN gestion__comprobantes_fiscales cf ON cf.codigo = '039'
WHERE sg.codigo = 'FC' AND sg.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos x WHERE x.empresa_id = sg.empresa_id AND x.codigo = 'C_RB');

-- 3) Medio de pago "Débito en cuenta"
INSERT INTO gestion__medios_pago
    (empresa_id, codigo, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo, tesoreria_cuenta_id, cont_cuenta_id,
     impuesto_tipo_id, requiere_detalle, aplica_cobro, aplica_pago, orden, tabla_estado_registro_id)
SELECT t.empresa_id, 'DEB_CTA', 'Débito en cuenta', 'TRANSFERENCIA', 'BANCO', NULL, t.cont_cuenta_id,
       NULL, 0, 0, 1, 20, 1
FROM gestion__medios_pago t
WHERE t.codigo = 'TRANSFER'
  AND NOT EXISTS (SELECT 1 FROM gestion__medios_pago x WHERE x.empresa_id = t.empresa_id AND x.codigo = 'DEB_CTA');

-- 4a) Cuenta 6.3.05 Impuesto s/ Débitos y Créditos Bancarios (hermana de 6.3.02 Gastos Bancarios)
INSERT INTO gestion__cont_cuentas
    (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
SELECT gb.empresa_id, 0, '6.3.05', 'Impuesto s/ Débitos y Créditos Bancarios', 'D', gb.cuenta_padre_id, gb.nivel, 5, 1, 0, 1
FROM gestion__cont_cuentas gb
WHERE gb.codigo = '6.3.02' AND gb.tabla_estado_registro_id = 1
  AND EXISTS (SELECT 1 FROM gestion__cont_cuentas ib WHERE ib.empresa_id = gb.empresa_id AND ib.codigo = '6.3.01')
  AND NOT EXISTS (SELECT 1 FROM gestion__cont_cuentas x WHERE x.empresa_id = gb.empresa_id AND x.codigo = '6.3.05');

-- 4b) Productos de servicio (sin stock) de los conceptos del resumen
INSERT INTO gestion__productos
    (empresa_id, producto_codigo, producto_nombre, cont_cuenta_id, cont_cuenta_compra_id, producto_tipo_id,
     unidad_medida_id, iva_alicuota_id, controla_stock, es_servicio, aplica_compra, aplica_venta, tabla_estado_registro_id)
SELECT c.empresa_id, n.codigo, n.nombre, c.cont_cuenta_id, c.cont_cuenta_id,
       (SELECT pt.producto_tipo_id FROM gestion__productos_tipos pt
        WHERE pt.empresa_id = c.empresa_id AND pt.maneja_stock = 0 AND pt.tabla_estado_registro_id = 1
        ORDER BY pt.producto_tipo_id LIMIT 1),
       (SELECT p.unidad_medida_id FROM gestion__productos p
        WHERE p.empresa_id = c.empresa_id AND p.es_servicio = 1 AND p.unidad_medida_id IS NOT NULL LIMIT 1),
       iva.iva_alicuota_id, 0, 1, 1, 0, 1
FROM (          SELECT 'BCO_COMISION' AS codigo, 'Comisiones y gastos bancarios' AS nombre, '6.3.02' AS cuenta, 'GRAV21' AS iva
      UNION ALL SELECT 'BCO_INTERES',  'Intereses bancarios',                          '6.3.01', 'GRAV21'
      UNION ALL SELECT 'BCO_LEY25413', 'Impuesto s/ débitos y créditos (ley 25.413)',  '6.3.05', 'NOGRAV') n
INNER JOIN gestion__cont_cuentas c ON c.codigo = n.cuenta AND c.tabla_estado_registro_id = 1
INNER JOIN gestion__cont_cuentas req ON req.empresa_id = c.empresa_id AND req.codigo = '6.3.05'
INNER JOIN gestion__impuestos__iva_alicuotas iva ON iva.empresa_id = c.empresa_id AND iva.tabla_estado_registro_id = 1
       AND ((n.iva = 'GRAV21' AND iva.es_gravado = 1 AND iva.porcentaje = 21) OR (n.iva = 'NOGRAV' AND iva.es_no_gravado = 1))
WHERE NOT EXISTS (SELECT 1 FROM gestion__productos x WHERE x.empresa_id = c.empresa_id AND x.producto_codigo = n.codigo);

-- Control (solo lectura)
SELECT ct.empresa_id, ct.comprobante_tipo_id, ct.comprobante_tipo, cf.codigo AS fiscal, ct.requiere_debito_cuenta,
       (SELECT COUNT(*) FROM gestion__medios_pago m WHERE m.empresa_id = ct.empresa_id AND m.codigo = 'DEB_CTA') AS medio_deb_cta,
       (SELECT COUNT(*) FROM gestion__productos p WHERE p.empresa_id = ct.empresa_id AND p.producto_codigo IN ('BCO_COMISION', 'BCO_INTERES', 'BCO_LEY25413')) AS conceptos
FROM gestion__comprobantes_tipos ct
LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
WHERE ct.codigo = 'C_RB'
ORDER BY ct.empresa_id;
