-- ventas_facturas.php: facturas a consumidor final (sin cliente dado de alta), mismo
-- criterio que 20261003_ventas_remitos_consumidor_final.sql.
-- entidad_id pasa a admitir NULL: NULL = consumidor final, con el nombre tipeado en
-- consumidor_final_nombre (para una factura a cliente queda NULL). Se les aplica la
-- lista "Mostrador" (lista_precio_codigo = 'MOST') sin descuento y condición de pago
-- de contado (gestion__condiciones_pago.tipo = 'CONTADO'). En gestion__comprobantes
-- (entidad_id NOT NULL) se sincronizan con entidad_id = 0.
ALTER TABLE gestion__ventas_facturas
    MODIFY COLUMN `entidad_id` int(11) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `consumidor_final_nombre` varchar(150) DEFAULT NULL AFTER `entidad_sucursal_id`;
