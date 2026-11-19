-- 20261118_gestion_normal_pedidos_gestion_editar_01.sql
-- ventas_pedidos_gestion (módulo 5, página 591): faltaban las funciones "Editar" en estado
-- 9 (En preparación) y 10 (Entrega parcial) que sí existen en la página 91 del módulo 2
-- (pagina_funcion_id 450 y 452). Sin ellas el depósito no puede ajustar cantidades al preparar.
-- IDs explícitos (convención: mismo id del módulo 2 + 5000).

INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 5450, 591, 40, 1, 1, 'Editar', 'editar', '', 10, 10, 22, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 5450);

INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 5452, 591, 40, 1, 1, 'Editar', 'editar', '', 9, 9, 22, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 5452);

-- Perfiles base que ya tienen "Editar" de la 591 (5392) reciben también las nuevas.
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT pf.perfil_id, nf.id, 1
FROM conf__perfiles_funciones pf
JOIN (SELECT 5450 AS id UNION ALL SELECT 5452) nf
WHERE pf.pagina_funcion_id = 5392
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = nf.id);
