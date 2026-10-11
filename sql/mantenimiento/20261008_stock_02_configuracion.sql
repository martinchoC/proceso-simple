-- =====================================================================
-- Movimientos de stock por boca — ETAPA 2: configuración
-- Fecha: 2026-10-08
-- Requiere: 20261008_stock_01_ddl.sql
--
-- Todo lo que sigue es configuración editable (ABM de Tipos de Comprobante y catálogo de estados
-- por tabla): el código no conoce ningún tipo ni estado concreto.
-- Idempotente: solo UPDATEs a valores fijos. Cada UPDATE se acota por id + código para no tocar
-- un tipo distinto si en otro ambiente el id no coincide.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Sentido en stock por tipo de comprobante
-- ---------------------------------------------------------------------
-- Restan: remitos de salida, facturas de venta, devolución a proveedor, faltante físico, NC de compra.
-- Las facturas de venta que salen de un remito NO vuelven a descontar: la línea con
-- venta_remito_detalle_id solo mueve lo que el remito no movió (regla de antecesor, stock_core_model.php).
UPDATE gestion__comprobantes_tipos SET impacta_stock = 1, stock_signo = '-'
 WHERE (comprobante_tipo_id, codigo) IN ((3,'S_RCPe'), (33,'RSX'), (40,'S_RSA'), (41,'S_RSX'),
                                         (4,'V_FA'), (9,'V_FX'), (12,'V_FB'),
                                         (23,'DP'), (22,'S_AN'), (35,'C_NCA'));

-- Suman: NC de venta (devolución del cliente), remito de entrada interno, sobrante físico, factura de compra.
-- Una NC cargada solo con conceptos (controla_stock = 0) no mueve nada.
UPDATE gestion__comprobantes_tipos SET impacta_stock = 1, stock_signo = '+'
 WHERE (comprobante_tipo_id, codigo) IN ((7,'V_NCA'), (10,'V_NCX'), (13,'V_NCA_B'),
                                         (20,'S_RE'), (21,'S_AP'), (34,'C_FA'));

-- No mueven: notas de débito (son ajustes de precio) y el remito del proveedor
-- (el que ingresa la mercadería es el remito interno que se genera a partir de él).
UPDATE gestion__comprobantes_tipos SET impacta_stock = 0, stock_signo = NULL
 WHERE (comprobante_tipo_id, codigo) IN ((8,'V_NDA'), (11,'V_NDX'), (14,'V_NDA_B'),
                                         (19,'S_RP'), (38,'C_NDX'));

-- Cualquier otro tipo sin stock queda coherente.
UPDATE gestion__comprobantes_tipos SET stock_signo = NULL WHERE impacta_stock = 0 AND stock_signo IS NOT NULL;

-- ---------------------------------------------------------------------
-- 2) Estados en que cada documento tiene el stock aplicado
-- ---------------------------------------------------------------------
UPDATE conf__tablas_estados_registros SET aplica_stock = 0 WHERE tabla_id IN (80, 81, 84, 97);

-- 80 ventas_facturas: Confirmado y los derivados del saldo (Cancelación parcial / total).
--    Con ARCA, "Pendiente de ARCA" (4) todavía no descuenta: recién al tener CAE (5).
UPDATE conf__tablas_estados_registros SET aplica_stock = 1 WHERE tabla_id = 80 AND estado_registro_id IN (5, 39, 40);

-- 81 ventas_remitos: el remito aprobado es el que sale de Borrador a Pend. de Facturación.
UPDATE conf__tablas_estados_registros SET aplica_stock = 1 WHERE tabla_id = 81 AND estado_registro_id IN (13, 15, 12);

-- 84 facturas_proveedores: Confirmada.
UPDATE conf__tablas_estados_registros SET aplica_stock = 1 WHERE tabla_id = 84 AND estado_registro_id = 5;

-- 97 stock_ajustes: Confirmado.
UPDATE conf__tablas_estados_registros SET aplica_stock = 1 WHERE tabla_id = 97 AND estado_registro_id = 5;

-- ---------------------------------------------------------------------
-- 3) Control: tipos que dicen impactar stock pero no tienen sentido (el servicio los ignora)
-- ---------------------------------------------------------------------
SELECT comprobante_tipo_id, empresa_id, codigo, comprobante_tipo
  FROM gestion__comprobantes_tipos
 WHERE impacta_stock = 1 AND stock_signo IS NULL;
