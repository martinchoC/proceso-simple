-- ventas_facturas.php: el combo de tipos por punto de venta filtra por
-- conf__paginas.tabla_id -> gestion__comprobantes_subgrupos.tabla_id (mismo criterio
-- que pedidos/remitos). El subgrupo "Facturación" tenía tabla_id = 0, así que hay que
-- vincularlo a gestion__ventas_facturas para que sus tipos (V_FA, V_FB, V_NCA...) aparezcan.
UPDATE gestion__comprobantes_subgrupos cs
JOIN conf__tablas t ON t.tabla_nombre = 'gestion__ventas_facturas'
SET cs.tabla_id = t.tabla_id
WHERE cs.comprobante_subgrupo = 'Facturación'
  AND cs.empresa_id = 2
  AND cs.tabla_id = 0;
