-- Facturación de clientes: condición comercial y asociación de la página.
-- Ejecutar una sola vez sobre bases existentes.
ALTER TABLE gestion__ventas_facturas
    ADD COLUMN condicion_pago_id INT(11) NULL AFTER entidad_sucursal_id,
    ADD KEY condicion_pago_id (condicion_pago_id);

UPDATE conf__paginas
SET tabla_id = 80
WHERE pagina_id = 56 AND url = 'ventas_facturas.php';