-- =====================================================================
-- Tipos de comprobante de proveedores: ND A y NC X igual que sus pares
-- 2026-10-09
--
-- Decisión (Pablo, 2026-10-09), a partir de la simulación de notas de
-- crédito/débito del 2026-10-08:
--   * 36 "Nota de Débito A" (proveedores) no impactaba contabilidad: su
--     asiento no se generaba. Queda como la Factura A (34) y la NC A (35).
--   * 39 "Nota de Crédito X" (proveedores) no impactaba stock, cuenta
--     corriente ni contabilidad, mientras que la ND X (38) sí. Queda como la
--     NC A (35): devuelve mercadería (stock '-'), resta de la cuenta corriente
--     y genera asiento (invertido, por signo '-').
--
-- Reanudable.
-- =====================================================================

UPDATE gestion__comprobantes_tipos
SET impacta_contabilidad = 1
WHERE comprobante_tipo_id = 36;

UPDATE gestion__comprobantes_tipos
SET impacta_stock = 1,
    stock_signo = '-',
    impacta_ctacte = 1,
    impacta_contabilidad = 1
WHERE comprobante_tipo_id = 39;

-- Verificación: 34/35/36 y 38/39 deben coincidir en ctacte y contabilidad; 35 y 39 en stock '-'
SELECT comprobante_tipo_id, comprobante_tipo, signo, impacta_stock, stock_signo, impacta_ctacte, impacta_contabilidad
FROM gestion__comprobantes_tipos
WHERE comprobante_tipo_id IN (34, 35, 36, 38, 39)
ORDER BY comprobante_tipo_id;
