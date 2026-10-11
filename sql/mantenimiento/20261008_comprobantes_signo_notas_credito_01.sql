-- =====================================================================
-- Comprobantes: signo de las notas de crédito (ventas y proveedores)
-- 2026-10-08
--
-- Problema: syncComprobante() (ventas_facturas_model.php y
-- facturas_proveedores_model.php) no grababa gestion__comprobantes.signo, que
-- quedaba en su default 1 también para las notas de crédito (tipo con signo
-- '-'). tesoreriaObtenerComprobantesCancelables() usa importe_pendiente * signo.
-- Los asientos de NC además salían con el mismo sentido que una factura.
-- El código ya está corregido (signo desde el tipo; asientos invertidos para
-- NC). Esto repara el signo de los comprobantes existentes; los asientos de NC
-- ya generados hay que regenerarlos (botón "Generar asiento" / acción
-- generar_asiento_contable), el script los lista.
-- Encontrado en la simulación de notas de crédito/débito del 2026-10-08.
--
-- Solo facturas de venta (tabla 80) y de proveedor (tabla 84): los remitos
-- tienen otra sincronización y no impactan cuenta corriente.
-- Reanudable.
-- =====================================================================

UPDATE gestion__comprobantes c
JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
SET c.signo = IF(ct.signo = '-', -1, 1)
WHERE c.tabla_origen_id IN (SELECT t.tabla_id FROM conf__tablas t
                            WHERE t.tabla_nombre IN ('gestion__ventas_facturas', 'gestion__facturas_proveedores'))
  AND c.signo <> IF(ct.signo = '-', -1, 1);

-- Notas de crédito con asiento ya generado (revisar / regenerar)
SELECT c.comprobante_id, ct.comprobante_tipo, c.comprobante_pv, c.comprobante_nro, a.cont_asiento_id
FROM gestion__comprobantes c
JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
JOIN gestion__cont_asientos a ON a.comprobante_id = c.comprobante_id
WHERE ct.signo = '-'
  AND c.tabla_origen_id IN (SELECT t.tabla_id FROM conf__tablas t
                            WHERE t.tabla_nombre IN ('gestion__ventas_facturas', 'gestion__facturas_proveedores'));
