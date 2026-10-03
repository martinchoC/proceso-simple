-- ventas_remitos.php: remitos a consumidor final (sin cliente dado de alta).
-- entidad_id pasa a admitir NULL: NULL = consumidor final, y en ese caso se guarda
-- el nombre tipeado en consumidor_final_nombre (para un remito a cliente queda NULL).
-- A estos remitos se les aplica el precio de la lista "Mostrador" de la empresa
-- (gestion__listas_precios.lista_precio_codigo = 'MOST'), sin descuento general.
-- En gestion__comprobantes (entidad_id NOT NULL) el remito a consumidor final se
-- sincroniza con entidad_id = 0.
ALTER TABLE gestion__ventas_remitos
    MODIFY COLUMN `entidad_id` int(11) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `consumidor_final_nombre` varchar(150) DEFAULT NULL AFTER `entidad_sucursal_id`;
