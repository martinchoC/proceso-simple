-- @migracion: particular
-- Pasa a es_manual = 0 los precios vigentes de gestion__listas_precios_productos
-- de los productos que tienen costo registrado o precio de lista de proveedor activo,
-- para que sigan las actualizaciones por facturas de compra o listas de proveedor.
-- ---------------------------------------------------------------------------------

SET NAMES utf8mb4;

DROP TEMPORARY TABLE IF EXISTS tmp_prods_con_costo;
CREATE TEMPORARY TABLE tmp_prods_con_costo (PRIMARY KEY (empresa_id, producto_id))
SELECT DISTINCT empresa_id, producto_id FROM (
    SELECT empresa_id, producto_id
    FROM gestion__productos_costos
    WHERE costo_actual > 0
    UNION
    SELECT ppp.empresa_id, pp.producto_id
    FROM gestion__productos_proveedores_precios ppp
    JOIN gestion__productos_proveedores pp ON pp.producto_proveedor_id = ppp.producto_proveedor_id
    WHERE ppp.tabla_estado_registro_id = 1
      AND pp.tabla_estado_registro_id = 1
      AND ppp.precio_lista > 0
) u;

UPDATE gestion__listas_precios_productos lpp
JOIN tmp_prods_con_costo t ON t.empresa_id = lpp.empresa_id AND t.producto_id = lpp.producto_id
SET lpp.es_manual = 0
WHERE lpp.tabla_estado_registro_id = 1
  AND lpp.es_manual = 1;

DROP TEMPORARY TABLE IF EXISTS tmp_prods_con_costo;
