-- Mini-gestión (página 417): función propia para liquidar y anular la liquidación mensual de IVA de un cliente.
-- Hasta ahora alcanzaba con "Cargar comprobantes" (3449, agregar); liquidar fija el período y bloquea la edición de sus
-- comprobantes, así que se separa para poder darla solo a quien firma la posición.
-- Id 3555: siguiente libre después de 3554. Los perfiles que hoy tienen 3449 la heredan (nadie pierde lo que tenía);
-- después se puede quitar por perfil en Perfiles - Funciones. Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3555, 417, 0, 1, 1, 'Liquidar IVA', 'liquidar_iva',
       'Liquidar y anular la liquidación mensual de IVA de los clientes en modo manual', 0, 0, 3, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = 417 AND accion_js = 'liquidar_iva');

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, f.pagina_funcion_id, 1, NOW()
FROM conf__perfiles_funciones pf
JOIN conf__paginas_funciones f ON f.pagina_id = 417 AND f.accion_js = 'liquidar_iva'
WHERE pf.pagina_funcion_id = 3449 AND pf.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = f.pagina_funcion_id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, f.pagina_funcion_id, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
JOIN conf__paginas_funciones f ON f.pagina_id = 417 AND f.accion_js = 'liquidar_iva'
WHERE epf.pagina_funcion_id = 3449 AND epf.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = f.pagina_funcion_id);
