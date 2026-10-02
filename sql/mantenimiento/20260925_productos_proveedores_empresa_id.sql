-- Corrige vínculos de gestion__productos_proveedores grabados con
-- empresa_id = 7 (empresa inexistente en conf__empresas) cuando tanto el
-- producto como el proveedor pertenecen a la empresa 2 (Casa Lucho).
--
-- Síntoma: en productos_proveedores.php, filtrando por el proveedor
-- entidad_id = 5748 (INDULOCK), los productos aparecían "con vínculo" pero sin
-- el código de proveedor, porque el conteo/filtro no exigía empresa y el
-- detalle de chips sí (pp.empresa_id = p.empresa_id).
--
-- En el dump casalucho.sql (2026-09-25): 272 filas, producto_proveedor_id
-- 6763..7447, proveedores 5726, 5727, 5729, 5733, 5740, 5741, 5748 (117),
-- 5749, 5752, 5758, 5763. Ninguna choca con un vínculo ya existente en la
-- empresa 2 para el mismo producto + proveedor.
--
-- Se toma la empresa real desde el producto (no se hardcodea 2) y solo se
-- tocan filas cuyo proveedor es de esa misma empresa.

-- 1) Diagnóstico previo (debería dar ~272 filas)
SELECT pp.empresa_id AS empresa_vinculo, p.empresa_id AS empresa_producto,
       e.empresa_id AS empresa_proveedor, pp.entidad_id, COUNT(*) AS cantidad
FROM gestion__productos_proveedores pp
INNER JOIN gestion__productos p ON p.producto_id = pp.producto_id
INNER JOIN gestion__entidades e ON e.entidad_id = pp.entidad_id
WHERE pp.empresa_id <> p.empresa_id
GROUP BY pp.empresa_id, p.empresa_id, e.empresa_id, pp.entidad_id;

-- 2) Corrección
START TRANSACTION;

UPDATE gestion__productos_proveedores pp
INNER JOIN gestion__productos p ON p.producto_id = pp.producto_id
INNER JOIN gestion__entidades e ON e.entidad_id = pp.entidad_id
SET pp.empresa_id = p.empresa_id
WHERE pp.empresa_id <> p.empresa_id
  AND e.empresa_id = p.empresa_id
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT producto_id, entidad_id, empresa_id FROM gestion__productos_proveedores) dup
      WHERE dup.producto_id = pp.producto_id
        AND dup.entidad_id = pp.entidad_id
        AND dup.empresa_id = p.empresa_id
  );

-- 3) Recalcular el campo materializado de búsqueda de los productos afectados
UPDATE gestion__productos p
SET p.proveedores_busqueda = (
    SELECT GROUP_CONCAT(DISTINCT pp.codigo_proveedor SEPARATOR ' ')
    FROM gestion__productos_proveedores pp
    WHERE pp.producto_id = p.producto_id
      AND pp.tabla_estado_registro_id = 1
      AND pp.codigo_proveedor IS NOT NULL
      AND pp.codigo_proveedor <> ''
)
WHERE p.producto_id IN (
    SELECT producto_id FROM (
        SELECT DISTINCT producto_id FROM gestion__productos_proveedores
        WHERE producto_proveedor_id BETWEEN 6763 AND 7447
    ) x
);

-- Verificar con la consulta 1) (debe quedar vacía) antes de confirmar
COMMIT;
