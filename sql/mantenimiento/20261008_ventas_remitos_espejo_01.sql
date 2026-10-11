-- =====================================================================
-- Remitos de venta: sincronizar el espejo en gestion__comprobantes
-- 2026-10-08
--
-- Problema: ejecutarTransicionEstadoRemito() (ventas_remitos_model.php) y
-- vfRecalcularEstadoRemito() (ventas_facturas_model.php) cambiaban el estado
-- (y al confirmar, el número) de gestion__ventas_remitos sin actualizar su fila
-- en gestion__comprobantes: el comprobante del remito quedaba en Borrador (3)
-- con número 0. El código ya está corregido; esto repara los existentes.
-- Encontrado en la simulación de circuitos del 2026-10-08.
--
-- Reanudable: solo actualiza filas desincronizadas.
-- =====================================================================

UPDATE gestion__comprobantes c
JOIN gestion__ventas_remitos r ON r.venta_remito_id = c.registro_origen_id
SET c.tabla_estado_registro_id = r.tabla_estado_registro_id,
    c.comprobante_nro = r.comprobante_nro
WHERE c.tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__ventas_remitos')
  AND (c.tabla_estado_registro_id <> r.tabla_estado_registro_id OR c.comprobante_nro <> r.comprobante_nro);

-- Verificación: debe dar 0
SELECT COUNT(*) AS remitos_desincronizados
FROM gestion__comprobantes c
JOIN gestion__ventas_remitos r ON r.venta_remito_id = c.registro_origen_id
WHERE c.tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__ventas_remitos')
  AND (c.tabla_estado_registro_id <> r.tabla_estado_registro_id OR c.comprobante_nro <> r.comprobante_nro);
