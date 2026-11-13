-- Estudio contable: sección del menú "Gestión de Clientes" (carpeta 421) con la nueva página "Gestión del Cliente" (422,
-- cliente_gestion.php): ficha del cliente con solapas según su servicio (Padrón / Fiscal / Contable / Gestión completa).
-- Pasan a la sección las páginas que trabajan sobre un cliente: Mini-Gestión (417), Importar Mis Comprobantes (419),
-- e Inspecciones (418). Solo cambia el menú (padre_id / orden), no los permisos.
-- Función 3559 "Acceder" (ver) de la 422: la heredan los perfiles que hoy ven el Padrón de Clientes (3291).
-- Ids libres verificados el 2026-11-07: páginas 421 / 422, función 3559 (siguiente libre después de 3558).
-- Reanudable: cada paso se saltea si ya está hecho.
SET NAMES utf8mb4;

-- 1) Carpeta del menú
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT 421, 4, 'Gestión de Clientes', '#', 57, 'Ficha de cada cliente y lo que el estudio hace para él: IVA, contabilidad, importación de comprobantes e inspecciones',
       0, 3, 0, 0, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 421);

-- Carpetas siguientes una posición más abajo (solo si siguen con su orden original)
UPDATE conf__paginas SET orden = 4 WHERE pagina_id = 420 AND orden = 3;
UPDATE conf__paginas SET orden = 5 WHERE pagina_id = 430 AND orden = 4;
UPDATE conf__paginas SET orden = 6 WHERE pagina_id = 440 AND orden = 5;

-- 2) Página
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT 422, 4, 'Gestión del Cliente', 'cliente_gestion.php', 87,
       'Ficha del cliente: vencimientos, tareas, honorarios, inspecciones y, según el servicio, IVA, contabilidad y operación de su empresa',
       421, 1, 0, 0, 1, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE url = 'cliente_gestion.php' AND modulo_id = 4);

-- 3) Páginas que pasan a la sección (solo si siguen en su carpeta original)
UPDATE conf__paginas SET padre_id = 421, orden = 2 WHERE pagina_id = 417 AND padre_id = 400;
UPDATE conf__paginas SET padre_id = 421, orden = 3 WHERE pagina_id = 419 AND padre_id = 400;
UPDATE conf__paginas SET padre_id = 421, orden = 4 WHERE pagina_id = 418 AND padre_id = 400;

-- 4) Función
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3559, 422, 0, 1, 1, 'Acceder', 'ver', 'Ver la ficha y la gestión del cliente', 0, 0, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = 422 AND accion_js = 'ver')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 3559);

-- 5) Permisos: quien ve el Padrón de Clientes (3291) ve la ficha. No se pisan asignaciones existentes.
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, 3559, 1, NOW()
FROM conf__perfiles_funciones pf
WHERE pf.pagina_funcion_id = 3291 AND pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3559 AND f.pagina_id = 422)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = 3559);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, 3559, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
WHERE epf.pagina_funcion_id = 3291 AND epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3559 AND f.pagina_id = 422)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = 3559);
