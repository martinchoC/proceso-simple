-- =====================================================================================================
-- Función "Imprimir" en Pedidos de Clientes (páginas 55 / 555) en todos los estados del pedido
-- -----------------------------------------------------------------------------------------------------
-- La impresión (ventas_pedidos_print.php, mismo formato que el remito) solo estaba en 5 Confirmado
-- (funciones 243 / 5243). Se agrega en 9 En preparación, 10 Entrega parcial y 11 Entregado, destino = origen
-- (imprimir no cambia el estado). Ids 3520-3522 (página 55) / 8520-8522 (página 555).
-- Permisos: los perfiles que ya tienen "Imprimir" en la página, en los perfiles base y en cada empresa.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

START TRANSACTION;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES (3520, 55, 36, 8, 1, 'Imprimir', 'imprimir', '', 9, 9, 48, 1),
       (3521, 55, 36, 8, 1, 'Imprimir', 'imprimir', '', 10, 10, 48, 1),
       (3522, 55, 36, 8, 1, 'Imprimir', 'imprimir', '', 11, 11, 48, 1),
       (8520, 555, 36, 8, 1, 'Imprimir', 'imprimir', '', 9, 9, 48, 1),
       (8521, 555, 36, 8, 1, 'Imprimir', 'imprimir', '', 10, 10, 48, 1),
       (8522, 555, 36, 8, 1, 'Imprimir', 'imprimir', '', 11, 11, 48, 1)
ON DUPLICATE KEY UPDATE pagina_id = VALUES(pagina_id), icono_id = VALUES(icono_id), nombre_funcion = VALUES(nombre_funcion),
                        accion_js = VALUES(accion_js),
                        tabla_estado_registro_origen_id = VALUES(tabla_estado_registro_origen_id),
                        tabla_estado_registro_destino_id = VALUES(tabla_estado_registro_destino_id),
                        orden = VALUES(orden), tabla_estado_registro_id = 1;

-- Perfiles base: los que tienen "Imprimir" de la misma página
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfa.perfil_id, pf.pagina_funcion_id, 1
FROM conf__perfiles_funciones pfa
JOIN conf__paginas_funciones im ON im.pagina_funcion_id = pfa.pagina_funcion_id AND im.accion_js = 'imprimir' AND im.pagina_id IN (55, 555)
JOIN conf__paginas_funciones pf ON pf.pagina_id = im.pagina_id AND pf.pagina_funcion_id IN (3520, 3521, 3522, 8520, 8521, 8522)
WHERE pfa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pfa.perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

-- Perfiles de cada empresa: los que tienen "Imprimir" de la misma página
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM conf__empresas_perfiles_funciones epa
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epa.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones im ON im.pagina_funcion_id = epa.pagina_funcion_id AND im.accion_js = 'imprimir' AND im.pagina_id IN (55, 555)
JOIN conf__paginas_funciones pf ON pf.pagina_id = im.pagina_id AND pf.pagina_funcion_id IN (3520, 3521, 3522, 8520, 8521, 8522)
WHERE epa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

COMMIT;

-- Control (solo lectura)
SELECT pf.pagina_funcion_id, pf.pagina_id, pf.tabla_estado_registro_origen_id AS origen, COUNT(epf.empresa_perfil_id) AS perfiles_con_permiso
FROM conf__paginas_funciones pf
LEFT JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = pf.pagina_funcion_id AND epf.asignado = 1
WHERE pf.pagina_id IN (55, 555) AND pf.accion_js = 'imprimir'
GROUP BY pf.pagina_funcion_id, pf.pagina_id, pf.tabla_estado_registro_origen_id;
