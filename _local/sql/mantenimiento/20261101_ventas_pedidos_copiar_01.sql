-- =====================================================================================================
-- Función "Copiar" en Pedidos de Clientes (páginas 55 / 555)
-- -----------------------------------------------------------------------------------------------------
-- Crea un pedido nuevo en Borrador con la cabecera y las líneas activas de un pedido ya realizado (mismo cliente,
-- sucursal, punto de venta, tipo, condición de pago, moneda, dirección, observaciones, productos, cantidades y
-- precios), con fecha de emisión de hoy y sin fecha de entrega. El original no cambia de estado. Lógica:
-- copiarPedidoVenta() de ventas_pedidos_model.php (usa agregarPedidoVenta(): accesos por sucursal, espejo en
-- gestion__comprobantes, número recién al confirmar).
-- Una función por estado de origen (el motor de botones filtra por origen): 5 Confirmado, 9 En preparación,
-- 10 Entrega parcial, 11 Entregado. Ids 3516-3519 (página 55) / 8516-8519 (página 555), destino = origen.
-- Permisos: los perfiles que tienen "Agregar" en la página (241 / 5241), en los perfiles base y en cada empresa.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

START TRANSACTION;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES (3516, 55, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 5, 5, 46, 1),
       (3517, 55, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 9, 9, 46, 1),
       (3518, 55, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 10, 10, 46, 1),
       (3519, 55, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 11, 11, 46, 1),
       (8516, 555, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 5, 5, 46, 1),
       (8517, 555, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 9, 9, 46, 1),
       (8518, 555, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 10, 10, 46, 1),
       (8519, 555, 145, 1, 1, 'Copiar', 'copiar', 'Crea un pedido nuevo en Borrador a partir de este', 11, 11, 46, 1)
ON DUPLICATE KEY UPDATE pagina_id = VALUES(pagina_id), icono_id = VALUES(icono_id), nombre_funcion = VALUES(nombre_funcion),
                        accion_js = VALUES(accion_js), descripcion = VALUES(descripcion),
                        tabla_estado_registro_origen_id = VALUES(tabla_estado_registro_origen_id),
                        tabla_estado_registro_destino_id = VALUES(tabla_estado_registro_destino_id),
                        orden = VALUES(orden), tabla_estado_registro_id = 1;

-- Perfiles base: los que tienen "Agregar" de la misma página
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT pfa.perfil_id, pf.pagina_funcion_id, 1
FROM conf__perfiles_funciones pfa
JOIN conf__paginas_funciones ag ON ag.pagina_funcion_id = pfa.pagina_funcion_id AND ag.accion_js = 'agregar' AND ag.pagina_id IN (55, 555)
JOIN conf__paginas_funciones pf ON pf.pagina_id = ag.pagina_id AND pf.accion_js = 'copiar'
WHERE pfa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pfa.perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

-- Perfiles de cada empresa: los que tienen "Agregar" de la misma página
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM conf__empresas_perfiles_funciones epa
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epa.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones ag ON ag.pagina_funcion_id = epa.pagina_funcion_id AND ag.accion_js = 'agregar' AND ag.pagina_id IN (55, 555)
JOIN conf__paginas_funciones pf ON pf.pagina_id = ag.pagina_id AND pf.accion_js = 'copiar'
WHERE epa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

COMMIT;

-- Control (solo lectura)
SELECT pf.pagina_funcion_id, pf.pagina_id, pf.tabla_estado_registro_origen_id AS origen, COUNT(epf.empresa_perfil_id) AS perfiles_con_permiso
FROM conf__paginas_funciones pf
LEFT JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = pf.pagina_funcion_id AND epf.asignado = 1
WHERE pf.pagina_funcion_id IN (3516, 3517, 3518, 3519, 8516, 8517, 8518, 8519)
GROUP BY pf.pagina_funcion_id, pf.pagina_id, pf.tabla_estado_registro_origen_id;
