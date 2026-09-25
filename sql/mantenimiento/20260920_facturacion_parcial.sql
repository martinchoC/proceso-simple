-- Permite facturar parcialmente un detalle de remito.
ALTER TABLE gestion__ventas_remitos_detalles
    ADD COLUMN cantidad_facturada DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER facturado;

-- Los remitos históricos marcados como facturados ya estaban completos.
UPDATE gestion__ventas_remitos_detalles
SET cantidad_facturada = cantidad
WHERE facturado <> 0 AND cantidad_facturada = 0;