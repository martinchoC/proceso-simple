-- Distribución (paso 5b): funciones del pedido para el estado 42 y páginas "Distribución - Configuración" (148 / 648) y "Armado y hojas de ruta" (149 / 649).
-- =====================================================================================================
-- 20261119_distribucion_02_paginas.sql
-- Diseño en _local/docs/gestion/24_distribucion.md. Requiere 20261119_distribucion_01.sql (estado 42).
--
--   1. Pedidos (55 / 555 "Pedidos Realizados", 91 / 591 "Nuevos Pedidos de Clientes"): 5 → 42 "Liberar para
--      distribución", 42 → 5 "Volver a revisión", 42 → 42 Visualizar / Imprimir y, en la preparación, 42 → 9 al
--      abrir (como hoy 5 → 9). Funciones 3605–3609 / 8605–8609. Sin usa_distribucion el modelo de pedidos no las
--      muestra ni las ejecuta. Permisos: los mismos perfiles que tienen Confirmar / Visualizar / Imprimir del
--      pedido.
--   2. Páginas 148 / 648 y 149 / 649 bajo Ventas, sin motor de estados (tabla_id 0): data-funcion +
--      permisos_exigir_accion. Funciones 3610–3613 / 8610–8613 (consultar, agregar, editar, inhabilitar) y
--      3614–3619 / 8614–8619 (lo mismo + confirmar y rendir).
--   3. Permisos de las páginas: configuración, solo perfiles de administración con Pedidos; armado, además los
--      perfiles que preparan pedidos (función 404 / 5404 de la página 91 / 591).
-- Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- 1. Funciones del pedido
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina, v.icono, v.color, 1, v.nombre, v.accion, v.descr, v.o, v.d, v.orden, 1
FROM (SELECT 3605 AS id, 55 AS pagina, 46 AS icono, 3 AS color, 'Liberar para distribución' AS nombre, 'liberar_distribucion' AS accion,
             'Revisado: pasa al armado de reparto' AS descr, 5 AS o, 42 AS d, 16 AS orden
      UNION ALL SELECT 3606, 55, 41, 4, 'Volver a revisión', 'volver_revision', 'Lo saca del armado de reparto', 42, 5, 17
      UNION ALL SELECT 3607, 55, 37, 7, 'Visualizar', 'visualizar', '', 42, 42, 18
      UNION ALL SELECT 3608, 55, 36, 8, 'Imprimir', 'imprimir', '', 42, 42, 19
      UNION ALL SELECT 3609, 91, 37, 7, 'Visualizar', 'visualizar', 'Al abrirlo pasa a En preparación', 42, 9, 18
      UNION ALL SELECT 8605, 555, 46, 3, 'Liberar para distribución', 'liberar_distribucion', 'Revisado: pasa al armado de reparto', 5, 42, 16
      UNION ALL SELECT 8606, 555, 41, 4, 'Volver a revisión', 'volver_revision', 'Lo saca del armado de reparto', 42, 5, 17
      UNION ALL SELECT 8607, 555, 37, 7, 'Visualizar', 'visualizar', '', 42, 42, 18
      UNION ALL SELECT 8608, 555, 36, 8, 'Imprimir', 'imprimir', '', 42, 42, 19
      UNION ALL SELECT 8609, 591, 37, 7, 'Visualizar', 'visualizar', 'Al abrirlo pasa a En preparación', 42, 9, 18) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina)
  AND EXISTS (SELECT 1 FROM conf__estados_registros e WHERE e.estado_registro_id = 42)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

DROP TEMPORARY TABLE IF EXISTS tmp_dis_mapa;
CREATE TEMPORARY TABLE tmp_dis_mapa (origen INT NOT NULL, destino INT NOT NULL);
INSERT INTO tmp_dis_mapa VALUES (250, 3605), (250, 3606), (378, 3607), (243, 3608), (404, 3609),
                                (5250, 8605), (5250, 8606), (5378, 8607), (5243, 8608), (5404, 8609);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pf.perfil_id, m.destino, 1
FROM conf__perfiles_funciones pf JOIN tmp_dis_mapa m ON m.origen = pf.pagina_funcion_id
WHERE pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.destino)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = m.destino);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, m.destino, 1
FROM conf__empresas_perfiles_funciones epf
JOIN tmp_dis_mapa m ON m.origen = epf.pagina_funcion_id
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
WHERE epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.destino)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = m.destino);

DROP TEMPORARY TABLE IF EXISTS tmp_dis_mapa;

-- 2. Páginas
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT v.id, v.modulo, v.nombre, v.url, 75, v.descr, v.padre, v.orden, 0, 1, 1, 1, 0, 0
FROM (SELECT 148 AS id, 2 AS modulo, 'Distribución - Configuración' AS nombre, 'distribucion_config.php' AS url,
             'Zonas de reparto, zona de cada cliente y camiones con su capacidad' AS descr, 48 AS padre, 460 AS orden
      UNION ALL SELECT 149, 2, 'Armado y hojas de ruta', 'distribucion_hojas.php',
             'Armado nocturno del reparto con control de capacidad, hojas de ruta, lista de carga y rendición', 48, 470
      UNION ALL SELECT 648, 5, 'Distribución - Configuración', 'distribucion_config.php',
             'Zonas de reparto, zona de cada cliente y camiones con su capacidad', 548, 460
      UNION ALL SELECT 649, 5, 'Armado y hojas de ruta', 'distribucion_hojas.php',
             'Armado nocturno del reparto con control de capacidad, hojas de ruta, lista de carga y rendición', 548, 470) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.padre)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas x WHERE x.pagina_id = v.id);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina, 75, 1, 1, v.nombre, v.accion, '', 0, 0, v.orden, 1
FROM (SELECT 3610 AS id, 148 AS pagina, 'Consultar' AS nombre, 'consultar' AS accion, 1 AS orden
      UNION ALL SELECT 3611, 148, 'Agregar', 'agregar', 2
      UNION ALL SELECT 3612, 148, 'Editar', 'editar', 3
      UNION ALL SELECT 3613, 148, 'Inhabilitar', 'inhabilitar', 4
      UNION ALL SELECT 3614, 149, 'Consultar', 'consultar', 1
      UNION ALL SELECT 3615, 149, 'Agregar', 'agregar', 2
      UNION ALL SELECT 3616, 149, 'Editar', 'editar', 3
      UNION ALL SELECT 3617, 149, 'Anular', 'inhabilitar', 4
      UNION ALL SELECT 3618, 149, 'Confirmar', 'confirmar', 5
      UNION ALL SELECT 3619, 149, 'Rendir', 'rendir', 6
      UNION ALL SELECT 8610, 648, 'Consultar', 'consultar', 1
      UNION ALL SELECT 8611, 648, 'Agregar', 'agregar', 2
      UNION ALL SELECT 8612, 648, 'Editar', 'editar', 3
      UNION ALL SELECT 8613, 648, 'Inhabilitar', 'inhabilitar', 4
      UNION ALL SELECT 8614, 649, 'Consultar', 'consultar', 1
      UNION ALL SELECT 8615, 649, 'Agregar', 'agregar', 2
      UNION ALL SELECT 8616, 649, 'Editar', 'editar', 3
      UNION ALL SELECT 8617, 649, 'Anular', 'inhabilitar', 4
      UNION ALL SELECT 8618, 649, 'Confirmar', 'confirmar', 5
      UNION ALL SELECT 8619, 649, 'Rendir', 'rendir', 6) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

-- 3. Permisos de las páginas
DROP TEMPORARY TABLE IF EXISTS tmp_dis_func;
CREATE TEMPORARY TABLE tmp_dis_func (modulo_id INT NOT NULL, pagina INT NOT NULL, funcion_id INT NOT NULL);
INSERT INTO tmp_dis_func VALUES (2, 148, 3610), (2, 148, 3611), (2, 148, 3612), (2, 148, 3613),
                                (2, 149, 3614), (2, 149, 3615), (2, 149, 3616), (2, 149, 3617), (2, 149, 3618), (2, 149, 3619),
                                (5, 648, 8610), (5, 648, 8611), (5, 648, 8612), (5, 648, 8613),
                                (5, 649, 8614), (5, 649, 8615), (5, 649, 8616), (5, 649, 8617), (5, 649, 8618), (5, 649, 8619);

-- Perfiles base Administrador: las dos páginas
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT v.perfil_id, f.funcion_id, 1
FROM (SELECT 201 AS perfil_id, 2 AS modulo_id UNION ALL SELECT 501, 5) v
JOIN tmp_dis_func f ON f.modulo_id = v.modulo_id
WHERE EXISTS (SELECT 1 FROM conf__perfiles p WHERE p.perfil_id = v.perfil_id)
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = v.perfil_id AND x.pagina_funcion_id = f.funcion_id);

-- Perfiles base que preparan pedidos (404 / 5404): la página de armado
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pf.perfil_id, f.funcion_id, 1
FROM conf__perfiles_funciones pf
JOIN tmp_dis_func f ON f.pagina IN (149, 649) AND f.modulo_id = IF(pf.pagina_funcion_id = 404, 2, 5)
WHERE pf.pagina_funcion_id IN (404, 5404) AND pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = f.funcion_id);

-- Perfiles de administración de cada empresa con Pedidos (55 / 555) en ese módulo: las dos páginas
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, f.funcion_id, 1
FROM conf__empresas_perfiles ep
JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id AND epf.asignado = 1
JOIN conf__paginas_funciones lp ON lp.pagina_funcion_id = epf.pagina_funcion_id AND lp.pagina_id IN (55, 555)
JOIN conf__paginas pl ON pl.pagina_id = lp.pagina_id
JOIN tmp_dis_func f ON f.modulo_id = pl.modulo_id
WHERE ep.tabla_estado_registro_id = 1
  AND (ep.empresa_perfil_nombre LIKE 'Administrador%' OR ep.empresa_perfil_nombre = 'Acceso total')
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = f.funcion_id);

-- Perfiles de cada empresa que preparan pedidos: la página de armado
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, f.funcion_id, 1
FROM conf__empresas_perfiles ep
JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id AND epf.asignado = 1 AND epf.pagina_funcion_id IN (404, 5404)
JOIN tmp_dis_func f ON f.pagina IN (149, 649) AND f.modulo_id = IF(epf.pagina_funcion_id = 404, 2, 5)
WHERE ep.tabla_estado_registro_id = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.pagina_funcion_id = f.funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = f.funcion_id);

DROP TEMPORARY TABLE IF EXISTS tmp_dis_func;
COMMIT;

-- Control (solo lectura)
SELECT f.pagina_funcion_id, f.pagina_id, f.accion_js, f.tabla_estado_registro_origen_id AS origen, f.tabla_estado_registro_destino_id AS destino,
       (SELECT COUNT(*) FROM conf__perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_base,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_empresa
FROM conf__paginas_funciones f
WHERE f.pagina_funcion_id BETWEEN 3605 AND 3619 OR f.pagina_funcion_id BETWEEN 8605 AND 8619
ORDER BY f.pagina_funcion_id;
