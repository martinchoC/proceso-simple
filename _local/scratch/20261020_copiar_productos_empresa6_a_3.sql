-- Copia los productos de la empresa 6 (Developsam SAS) a la empresa 3, solo base local.
-- La empresa 3 no tenia tipos de producto, categorias ni alicuotas de IVA: se copian de la 6.
-- Cuentas contables remapeadas por codigo al plan de la empresa 3 (0 / NULL se conservan).
-- Ids por empresa: nada se copia por id, todo por clave natural.

SET @orig := 6;
SET @dest := 3;

START TRANSACTION;

-- Funcion inline de remapeo de cuenta: (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s
--   JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = X)

-- 1) Tipos de producto (por producto_tipo_codigo)
INSERT INTO gestion__productos_tipos
  (empresa_id, producto_tipo, producto_tipo_codigo, maneja_stock, es_compuesto, afecta_facturacion,
   tabla_estado_registro_id, cont_cuenta_compra_default_id, cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id)
SELECT @dest, t.producto_tipo, t.producto_tipo_codigo, t.maneja_stock, t.es_compuesto, t.afecta_facturacion,
       t.tabla_estado_registro_id,
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = t.cont_cuenta_compra_default_id),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = t.cont_cuenta_venta_default_id),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = t.cont_cuenta_cmv_default_id)
FROM gestion__productos_tipos t
WHERE t.empresa_id = @orig
  AND NOT EXISTS (SELECT 1 FROM gestion__productos_tipos x WHERE x.empresa_id = @dest AND x.producto_tipo_codigo = t.producto_tipo_codigo);

DROP TEMPORARY TABLE IF EXISTS tmp_map_tipo;
CREATE TEMPORARY TABLE tmp_map_tipo AS
SELECT o.producto_tipo_id AS orig_id, d.producto_tipo_id AS dest_id
FROM gestion__productos_tipos o
JOIN gestion__productos_tipos d ON d.empresa_id = @dest AND d.producto_tipo_codigo = o.producto_tipo_codigo
WHERE o.empresa_id = @orig;

-- 2) Categorias (por nombre; sin padres en la 6)
INSERT INTO gestion__productos_categorias
  (empresa_id, producto_categoria_nombre, producto_categoria_padre_id, tabla_estado_registro_id,
   producto_tipo_id, cont_cuenta_ingreso_id, cont_cuenta_cmv_id)
SELECT @dest, c.producto_categoria_nombre, NULL, c.tabla_estado_registro_id,
       (SELECT m.dest_id FROM tmp_map_tipo m WHERE m.orig_id = c.producto_tipo_id),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = c.cont_cuenta_ingreso_id),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = c.cont_cuenta_cmv_id)
FROM gestion__productos_categorias c
WHERE c.empresa_id = @orig
  AND NOT EXISTS (SELECT 1 FROM gestion__productos_categorias x WHERE x.empresa_id = @dest AND x.producto_categoria_nombre = c.producto_categoria_nombre);

DROP TEMPORARY TABLE IF EXISTS tmp_map_cat;
CREATE TEMPORARY TABLE tmp_map_cat AS
SELECT o.producto_categoria_id AS orig_id, d.producto_categoria_id AS dest_id
FROM gestion__productos_categorias o
JOIN gestion__productos_categorias d ON d.empresa_id = @dest AND d.producto_categoria_nombre = o.producto_categoria_nombre
WHERE o.empresa_id = @orig;

-- Categoria para productos de la 6 que apuntan a una categoria ajena (la 1 es de la empresa 2)
SET @cat_default := (SELECT d.producto_categoria_id FROM gestion__productos_categorias d
                     WHERE d.empresa_id = @dest AND d.producto_categoria_nombre = 'Desarrollo de Software a Medida');

-- 3) Alicuotas de IVA (por codigo)
INSERT INTO gestion__impuestos__iva_alicuotas
  (empresa_id, codigo, iva_alicuota, porcentaje, es_gravado, es_exento, es_no_gravado,
   cont_cuenta_compra_id, cont_cuenta_venta_id, tabla_estado_registro_id)
SELECT @dest, a.codigo, a.iva_alicuota, a.porcentaje, a.es_gravado, a.es_exento, a.es_no_gravado,
       IF(a.cont_cuenta_compra_id = 0, 0, (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = a.cont_cuenta_compra_id)),
       IF(a.cont_cuenta_venta_id = 0, 0, (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = a.cont_cuenta_venta_id)),
       a.tabla_estado_registro_id
FROM gestion__impuestos__iva_alicuotas a
WHERE a.empresa_id = @orig
  AND NOT EXISTS (SELECT 1 FROM gestion__impuestos__iva_alicuotas x WHERE x.empresa_id = @dest AND x.codigo = a.codigo);

DROP TEMPORARY TABLE IF EXISTS tmp_map_iva;
CREATE TEMPORARY TABLE tmp_map_iva AS
SELECT o.iva_alicuota_id AS orig_id, d.iva_alicuota_id AS dest_id
FROM gestion__impuestos__iva_alicuotas o
JOIN gestion__impuestos__iva_alicuotas d ON d.empresa_id = @dest AND d.codigo = o.codigo
WHERE o.empresa_id = @orig;

-- 4) Productos (por producto_codigo). unidad_medida 1 es global (empresa 0).
INSERT INTO gestion__productos
  (empresa_id, producto_codigo, producto_nombre, codigo_barras, producto_descripcion, producto_categoria_id,
   cont_cuenta_id, cont_cuenta_compra_id, cont_cuenta_venta_id, cont_cuenta_cmv_id, producto_tipo_id,
   lado, material, color, peso, dimensiones, garantia, unidad_medida_id, iva_alicuota_id,
   impuesto_interno_porcentaje, impuesto_interno_fijo, controla_stock, es_servicio, tabla_estado_registro_id,
   compatibilidad_texto, compatibilidad_busqueda, proveedores_busqueda, aplica_compra, aplica_venta)
SELECT @dest, p.producto_codigo, p.producto_nombre, p.codigo_barras, p.producto_descripcion,
       COALESCE((SELECT m.dest_id FROM tmp_map_cat m WHERE m.orig_id = p.producto_categoria_id), @cat_default),
       IF(p.cont_cuenta_id = 0, 0, (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = p.cont_cuenta_id)),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = p.cont_cuenta_compra_id),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = p.cont_cuenta_venta_id),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = p.cont_cuenta_cmv_id),
       (SELECT m.dest_id FROM tmp_map_tipo m WHERE m.orig_id = p.producto_tipo_id),
       p.lado, p.material, p.color, p.peso, p.dimensiones, p.garantia, p.unidad_medida_id,
       (SELECT m.dest_id FROM tmp_map_iva m WHERE m.orig_id = p.iva_alicuota_id),
       p.impuesto_interno_porcentaje, p.impuesto_interno_fijo, p.controla_stock, p.es_servicio, p.tabla_estado_registro_id,
       p.compatibilidad_texto, p.compatibilidad_busqueda, NULL, p.aplica_compra, p.aplica_venta
FROM gestion__productos p
WHERE p.empresa_id = @orig
  AND NOT EXISTS (SELECT 1 FROM gestion__productos x WHERE x.empresa_id = @dest AND x.producto_codigo = p.producto_codigo);

COMMIT;
