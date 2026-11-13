-- Asientos Contables (páginas 84 / 584): un asiento se edita hasta el cierre de su período contable, no solo en Borrador.
-- Función "Editar" (editar) también desde 1 Activo (los automáticos de comprobantes) y 5 Confirmado, sin cambio de estado.
-- Las reglas están en core/cont_asientos_edicion.php: manual = fecha, descripción y renglones (si cambian los renglones
-- de uno confirmado vuelve a Borrador); automático = solo la cuenta de los renglones de productos. Cerrado el período,
-- el listado no muestra Editar.
-- Ids libres verificados el 2026-11-11: 3592 / 3593 (página 84) y 8592 / 8593 (página 584).
-- Permisos: los mismos perfiles que ya tienen "Editar" desde Borrador (324 / 5324). Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT n.id, f.pagina_id, f.icono_id, f.color_id, f.funcion_estandar_id, f.nombre_funcion, f.accion_js,
       'Editar el asiento con el período contable abierto', n.estado, n.estado, f.orden, 1
FROM (SELECT 3592 AS id, 324 AS base, 1 AS estado UNION ALL SELECT 3593, 324, 5
      UNION ALL SELECT 8592, 5324, 1 UNION ALL SELECT 8593, 5324, 5) n
JOIN conf__paginas_funciones f ON f.pagina_funcion_id = n.base AND f.accion_js = 'editar'
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x
                  WHERE x.pagina_id = f.pagina_id AND x.accion_js = 'editar' AND x.tabla_estado_registro_origen_id = n.estado);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, n.id, 1, NOW()
FROM (SELECT 3592 AS id, 324 AS base UNION ALL SELECT 3593, 324 UNION ALL SELECT 8592, 5324 UNION ALL SELECT 8593, 5324) n
JOIN conf__perfiles_funciones pf ON pf.pagina_funcion_id = n.base AND pf.asignado = 1
WHERE EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, n.id, 1, NOW()
FROM (SELECT 3592 AS id, 324 AS base UNION ALL SELECT 3593, 324 UNION ALL SELECT 8592, 5324 UNION ALL SELECT 8593, 5324) n
JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = n.base AND epf.asignado = 1
WHERE EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = n.id);
