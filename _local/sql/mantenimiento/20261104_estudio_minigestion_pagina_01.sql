-- Mini-Gestión del estudio contable como página propia del menú (módulo 4).
-- Hasta ahora minigestion.php no estaba en conf__paginas (tomaba prestada la 412, Parámetros Contables),
-- así que no aparecía en el menú para nadie, tampoco para el superadmin.
--   Página 417 "Mini-Gestión de Clientes" bajo "Clientes y Vencimientos" (400), antes de Parámetros Contables.
--   Funciones 3448 Acceder (consultar) y 3449 Cargar comprobantes (agregar: alta, edición y anulación).
--   (El bloque del estudio 3291–3334 está completo; 3448 / 3449 quedaron libres en el de gestión porque
--   sus espejos 8448 / 8449 del módulo 5 no se usan en ese esquema.)
-- Permisos: los mismos perfiles que hoy tienen Ver / Agregar en la 412, que eran los que entraban a Mini-Gestión.
-- Va junto con el cambio de código: minigestion.php y minigestion_ajax.php pasan de 412 a 417.
-- Reanudable.

SET NAMES utf8mb4;

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT 417, 4, 'Mini-Gestión de Clientes', 'minigestion.php', 83,
       'Compras, ventas e IVA de los clientes del estudio: carga manual o lectura de la empresa vinculada', 400, 11,
       0, 0, 1, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 417);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, 417, 0, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (
    SELECT 3448 AS id, 'Acceder' AS nombre, 'consultar' AS accion, 'Acceso a la página' AS descr, 1 AS orden
    UNION ALL SELECT 3449, 'Cargar comprobantes', 'agregar', 'Cargar, editar y anular comprobantes manuales de un cliente', 2
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

-- Perfiles base: 3331 (Ver de la 412) → 3448, 3332 (Agregar de la 412) → 3449
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, m.nueva, 1, NOW()
FROM conf__perfiles_funciones pf
JOIN (SELECT 3331 AS vieja, 3448 AS nueva UNION ALL SELECT 3332, 3449) m ON m.vieja = pf.pagina_funcion_id
WHERE pf.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = m.nueva);

-- Perfiles por empresa (la fuente que lee core/permisos.php)
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, m.nueva, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
JOIN (SELECT 3331 AS vieja, 3448 AS nueva UNION ALL SELECT 3332, 3449) m ON m.vieja = epf.pagina_funcion_id
WHERE epf.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = m.nueva);
