-- =====================================================================================================
-- Página "Stock - Stock y Movimientos" (138 en gestion / 638 en gestion_normal), menú Stock
-- -----------------------------------------------------------------------------------------------------
-- stock_consulta.php: stock por producto (una columna por boca) y libro de movimientos, con filtros de sucursal,
-- boca, producto, categoría, período, situación, dirección y comprobante. Solo lectura, sin tabla propia
-- (como el Dashboard de stock 114 / 614, cuyas consultas usa). requiere_bienes = 1.
-- Función 3526 / 8526 "Ver Stock y Movimientos" (consultar, 0 -> 0).
-- Permisos: los perfiles (base y de cada empresa) que ven el Dashboard de stock (funciones 3366 / 8366).
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

START TRANSACTION;

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 138, 2, 'Stock - Stock y Movimientos', 'stock_consulta.php', 5,
       'Stock por producto y boca y movimientos, con filtros de sucursal, boca, producto y período', 44, 95, 0, 1, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 138)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 44);

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 638, 5, 'Stock - Stock y Movimientos', 'stock_consulta.php', 5,
       'Stock por producto y boca y movimientos, con filtros de sucursal, boca, producto y período', 544, 95, 0, 1, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 638)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 544);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3526, 138, 37, 1, 1, 'Ver Stock y Movimientos', 'consultar', '', 0, 0, 1, 1 FROM DUAL
WHERE EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 138)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 3526);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 8526, 638, 37, 1, 1, 'Ver Stock y Movimientos', 'consultar', '', 0, 0, 1, 1 FROM DUAL
WHERE EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 638)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 8526);

-- Perfiles base: los que tienen el Dashboard de stock
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfa.perfil_id, CASE pfa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END, 1
FROM conf__perfiles_funciones pfa
WHERE pfa.pagina_funcion_id IN (3366, 8366) AND pfa.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = CASE pfa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = pfa.perfil_id AND x.pagina_funcion_id = CASE pfa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END);

-- Perfiles de cada empresa: los que tienen el Dashboard de stock
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, CASE epa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END, 1
FROM conf__empresas_perfiles_funciones epa
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epa.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
WHERE epa.pagina_funcion_id IN (3366, 8366) AND epa.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = CASE epa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id
                    AND x.pagina_funcion_id = CASE epa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END);

COMMIT;

-- Control (solo lectura)
SELECT p.pagina_id, p.pagina, p.padre_id, f.pagina_funcion_id,
       (SELECT COUNT(*) FROM conf__perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_base,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_empresa
FROM conf__paginas p
LEFT JOIN conf__paginas_funciones f ON f.pagina_id = p.pagina_id
WHERE p.pagina_id IN (138, 638);
