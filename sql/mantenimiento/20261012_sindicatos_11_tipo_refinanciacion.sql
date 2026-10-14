-- ==============================================================================
-- 20261012_sindicatos_11_tipo_refinanciacion.sql
-- Módulo 6 (Sindicatos): tipo de comprobante RPL "Refinanciación por plan de pagos".
--
-- El plan de pagos cancela las deudas de origen con un crédito RPL (signo -) por el capital
-- refinanciado y emite las cuotas PLC (signo +) como deudas nuevas (sindi_planes_core_model.php).
-- Antes imputaba la primera cuota (una deuda) contra las deudas de origen.
--
-- Crea RPL en cada empresa que ya tiene el tipo PLC, en el mismo grupo y subgrupo. Reanudable.
-- ==============================================================================

SET NAMES utf8mb4;

INSERT INTO gestion__comprobantes_tipos (
    empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id,
    impacta_stock, stock_signo, impacta_contabilidad, impacta_ctacte, requiere_debito_cuenta,
    comprobante_tipo, codigo, letra, signo, comentario, orden, tabla_estado_registro_id
)
SELECT p.empresa_id, p.comprobante_grupo_id, p.comprobante_subgrupo_id, 0,
       0, NULL, 1, 1, 0,
       'Refinanciación por Plan de Pagos', 'RPL', NULL, '-',
       'Crédito que cancela las deudas refinanciadas por un plan de pagos sindical', p.orden + 1, 1
FROM gestion__comprobantes_tipos p
WHERE p.codigo = 'PLC'
  AND NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos x WHERE x.empresa_id = p.empresa_id AND x.codigo = 'RPL');

SELECT empresa_id, comprobante_tipo_id, codigo, comprobante_tipo, signo
FROM gestion__comprobantes_tipos WHERE codigo IN ('PLC', 'RPL') ORDER BY empresa_id, codigo;
