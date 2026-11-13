-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================================================
-- Habilita la generación de remitos rápidos desde Productos - Consulta para el perfil Pedidos
-- de Casa Lucho (empresa 2, módulo Gestión; perfil de empresa 12: "Pedidos Bahia Blanca" en producción,
-- "Pedidos" en local, por eso el nombre se filtra con LIKE 'Pedidos%').
-- -----------------------------------------------------------------------------------------------------
-- Se habilita la consulta de productos (Visualizar) y, porque el endpoint del remito rápido exige acceso
-- a Remitos de venta, únicamente Agregar y Confirmar en esa página. Confirmar numera el remito y descuenta
-- stock.
-- No se amplían los permisos del perfil base ni los de otros perfiles/empresas.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

START TRANSACTION;

UPDATE conf__empresas_perfiles_funciones epf
JOIN conf__empresas_perfiles ep
  ON ep.empresa_perfil_id = epf.empresa_perfil_id
JOIN conf__paginas p
  ON p.modulo_id = 2
 AND p.url IN ('productos_consulta.php', 'ventas_remitos.php')
 AND p.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones pf
  ON pf.pagina_id = p.pagina_id
 AND (
      (p.url = 'productos_consulta.php' AND pf.accion_js = 'visualizar')
      OR (p.url = 'ventas_remitos.php' AND pf.accion_js IN ('agregar', 'confirmar'))
 )
 AND pf.tabla_estado_registro_id = 1
SET epf.asignado = 1
WHERE ep.empresa_id = 2
  AND ep.modulo_id = 2
  AND ep.empresa_perfil_id = 12
  AND ep.empresa_perfil_nombre LIKE 'Pedidos%'
  AND ep.tabla_estado_registro_id = 1
  AND epf.pagina_funcion_id = pf.pagina_funcion_id;

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM conf__empresas_perfiles ep
JOIN conf__paginas p
  ON p.modulo_id = 2
 AND p.url IN ('productos_consulta.php', 'ventas_remitos.php')
 AND p.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones pf
  ON pf.pagina_id = p.pagina_id
 AND (
      (p.url = 'productos_consulta.php' AND pf.accion_js = 'visualizar')
      OR (p.url = 'ventas_remitos.php' AND pf.accion_js IN ('agregar', 'confirmar'))
 )
 AND pf.tabla_estado_registro_id = 1
WHERE ep.empresa_id = 2
  AND ep.modulo_id = 2
  AND ep.empresa_perfil_id = 12
  AND ep.empresa_perfil_nombre LIKE 'Pedidos%'
  AND ep.tabla_estado_registro_id = 1
  AND NOT EXISTS (
      SELECT 1
      FROM conf__empresas_perfiles_funciones existing
      WHERE existing.empresa_perfil_id = ep.empresa_perfil_id
        AND existing.pagina_funcion_id = pf.pagina_funcion_id
  );

COMMIT;

-- Control (solo lectura)
SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, p.pagina_id, pf.pagina_funcion_id,
       pf.nombre_funcion, pf.accion_js, COALESCE(epf.asignado, 0) AS asignado
FROM conf__empresas_perfiles ep
JOIN conf__paginas p
  ON p.modulo_id = 2
 AND p.url IN ('productos_consulta.php', 'ventas_remitos.php')
 AND p.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones pf
  ON pf.pagina_id = p.pagina_id
 AND (
      (p.url = 'productos_consulta.php' AND pf.accion_js = 'visualizar')
      OR (p.url = 'ventas_remitos.php' AND pf.accion_js IN ('agregar', 'confirmar'))
 )
 AND pf.tabla_estado_registro_id = 1
LEFT JOIN conf__empresas_perfiles_funciones epf
  ON epf.empresa_perfil_id = ep.empresa_perfil_id
 AND epf.pagina_funcion_id = pf.pagina_funcion_id
WHERE ep.empresa_id = 2
  AND ep.modulo_id = 2
  AND ep.empresa_perfil_id = 12
  AND ep.empresa_perfil_nombre LIKE 'Pedidos%'
  AND ep.tabla_estado_registro_id = 1
ORDER BY pf.accion_js;
