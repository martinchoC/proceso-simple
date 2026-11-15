-- Página "Vendedores y carteras" (147 gestion / 647 gestion_normal, menú Ventas): funciones y permisos para los perfiles de administración.
-- =====================================================================================================
-- 20261118_vendedores_carteras_02_pagina.sql
-- Diseño en _local/docs/gestion/23_vendedores_carteras.md (paso 5a-2). Requiere 20261118_vendedores_carteras_01.sql.
--
-- vendedores.php: vendedores (alta desde una entidad o alta mínima, usuario ligado, baja) y cartera de clientes con
-- vigencia (asignar, quitar, pasar la cartera, clientes sin vendedor). Sin motor de estados (tabla_id 0): los
-- botones llevan data-funcion y el _ajax.php exige la función con permisos_exigir_accion. Lógica en
-- core/vendedores.php + core/vendedores_abm.php, vista en templates/vendedores/, JS en assets/js/vendedores.js.
--
--   1. Página 147 / 647 bajo Ventas (48 / 548).
--   2. Funciones 3599–3604 / 8599–8604: consultar, agregar, editar, inhabilitar, ver_todas_carteras ("Ver todas las
--      carteras": un usuario vendedor con esta función no queda limitado a su cartera) y asignar_vendedor ("Asignar
--      vendedor": elegir a mano el vendedor de un pedido).
--   3. Permisos: solo los perfiles de administración (empresa_perfil_nombre LIKE 'Administrador%' o 'Acceso total')
--      que tienen Listas de precios (páginas 52 / 53 / 552 / 553), y los perfiles base
--      Administrador 201 / 501.
-- Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- 1. Páginas
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 147, 2, 'Vendedores y carteras', 'vendedores.php', 75,
       'Vendedores de la empresa, el usuario de cada uno y su cartera de clientes con vigencia',
       48, 450, 0, 0, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 147)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 48);

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 647, 5, 'Vendedores y carteras', 'vendedores.php', 75,
       'Vendedores de la empresa, el usuario de cada uno y su cartera de clientes con vigencia',
       548, 450, 0, 0, 1, 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 647)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 548);

-- 2. Funciones
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina, 75, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (SELECT 3599 AS id, 147 AS pagina, 'Consultar' AS nombre, 'consultar' AS accion, '' AS descr, 1 AS orden
      UNION ALL SELECT 3600, 147, 'Agregar', 'agregar', '', 2
      UNION ALL SELECT 3601, 147, 'Editar', 'editar', '', 3
      UNION ALL SELECT 3602, 147, 'Inhabilitar', 'inhabilitar', '', 4
      UNION ALL SELECT 3603, 147, 'Ver todas las carteras', 'ver_todas_carteras', 'Un usuario vendedor con esta función ve todos los pedidos, no solo los de su cartera', 5
      UNION ALL SELECT 3604, 147, 'Asignar vendedor', 'asignar_vendedor', 'Elegir a mano el vendedor de un pedido', 6
      UNION ALL SELECT 8599, 647, 'Consultar', 'consultar', '', 1
      UNION ALL SELECT 8600, 647, 'Agregar', 'agregar', '', 2
      UNION ALL SELECT 8601, 647, 'Editar', 'editar', '', 3
      UNION ALL SELECT 8602, 647, 'Inhabilitar', 'inhabilitar', '', 4
      UNION ALL SELECT 8603, 647, 'Ver todas las carteras', 'ver_todas_carteras', 'Un usuario vendedor con esta función ve todos los pedidos, no solo los de su cartera', 5
      UNION ALL SELECT 8604, 647, 'Asignar vendedor', 'asignar_vendedor', 'Elegir a mano el vendedor de un pedido', 6) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

-- 3. Permisos
DROP TEMPORARY TABLE IF EXISTS tmp_vcar_func;
CREATE TEMPORARY TABLE tmp_vcar_func (modulo_id INT NOT NULL, funcion_id INT NOT NULL);
INSERT INTO tmp_vcar_func VALUES (2, 3599), (2, 3600), (2, 3601), (2, 3602), (2, 3603), (2, 3604),
                                 (5, 8599), (5, 8600), (5, 8601), (5, 8602), (5, 8603), (5, 8604);

-- Perfiles base Administrador
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT v.perfil_id, f.funcion_id, 1
FROM (SELECT 201 AS perfil_id, 2 AS modulo_id UNION ALL SELECT 501, 5) v
JOIN tmp_vcar_func f ON f.modulo_id = v.modulo_id
WHERE EXISTS (SELECT 1 FROM conf__perfiles p WHERE p.perfil_id = v.perfil_id)
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = v.perfil_id AND x.pagina_funcion_id = f.funcion_id);

-- Perfiles de administración de cada empresa que manejan Listas de precios en ese módulo
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, f.funcion_id, 1
FROM conf__empresas_perfiles ep
JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id AND epf.asignado = 1
JOIN conf__paginas_funciones lp ON lp.pagina_funcion_id = epf.pagina_funcion_id AND lp.pagina_id IN (52, 53, 552, 553)
JOIN conf__paginas pl ON pl.pagina_id = lp.pagina_id
JOIN tmp_vcar_func f ON f.modulo_id = pl.modulo_id
WHERE ep.tabla_estado_registro_id = 1
  AND (ep.empresa_perfil_nombre LIKE 'Administrador%' OR ep.empresa_perfil_nombre = 'Acceso total')
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = f.funcion_id);

DROP TEMPORARY TABLE IF EXISTS tmp_vcar_func;
COMMIT;

-- Control (solo lectura)
SELECT p.pagina_id, p.pagina, p.padre_id, f.pagina_funcion_id, f.accion_js,
       (SELECT COUNT(*) FROM conf__perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_base,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_empresa
FROM conf__paginas p
LEFT JOIN conf__paginas_funciones f ON f.pagina_id = p.pagina_id
WHERE p.pagina_id IN (147, 647)
ORDER BY p.pagina_id, f.orden;
