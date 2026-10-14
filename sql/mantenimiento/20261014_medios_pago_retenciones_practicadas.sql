-- Migración: Alta de Medios de Pago para Retenciones Practicadas (aplica_pago = 1)
-- Permite imputar retenciones de IVA, Ganancias, IIBB y SUSS en Órdenes de Pago a Proveedores.

INSERT INTO `gestion__medios_pago` 
  (`empresa_id`, `codigo`, `medio_pago`, `medio_pago_tipo`, `impuesto_tipo_id`, `requiere_detalle`, `aplica_cobro`, `aplica_pago`, `orden`, `tabla_estado_registro_id`)
SELECT e.empresa_id, m.codigo, m.medio_pago, 'RETENCION', m.impuesto_tipo_id, 1, 0, 1, m.orden, 1
FROM (SELECT DISTINCT empresa_id FROM `gestion__medios_pago`) e
CROSS JOIN (
    SELECT 'RET_IVA_P' AS codigo, 'Retención IVA' AS medio_pago, 15 AS impuesto_tipo_id, 20 AS orden
    UNION ALL SELECT 'RET_GAN_P', 'Retención Ganancias', 14, 21
    UNION ALL SELECT 'RET_IIBB_P', 'Retención IIBB', 16, 22
    UNION ALL SELECT 'RET_SUSS_P', 'Retención SUSS', 17, 23
) m
WHERE NOT EXISTS (
    SELECT 1 FROM `gestion__medios_pago` mp 
    WHERE mp.empresa_id = e.empresa_id AND mp.codigo = m.codigo
);
