-- ABM "Productos - Generales": página nueva para dar de alta y editar productos
-- Y servicios sobre gestion__productos con todos sus campos, sin la
-- compatibilidad (marca/modelo/submodelo) que maneja productos.php.
--
-- Archivos: modules/gestion/productos_generales.php / _ajax.php / _model.php / .js
--
-- No crea tabla ni estados nuevos: reutiliza gestion__productos, que ya está
-- registrada en conf__tablas (tabla_id = 25) con sus estados Activo (210) /
-- Inactivo (211). Solo agrega la página y sus funciones.
--
-- IDs tomados de la base viva del 2026-09-25 (ya incluye la página 96 de
-- productos_tipos): MAX(pagina_id) = 96, MAX(pagina_funcion_id) = 436.
-- Verificar con la consulta 0) antes de correr: si la base avanzó, ajustar los
-- IDs (y $pagina_idx en productos_generales.php / el default de productos_generales_ajax.php).

-- 0) Verificación previa (deben dar 96 / 436 / 25 y 0 páginas registradas)
SELECT (SELECT MAX(pagina_id) FROM conf__paginas) AS max_pagina_id,
       (SELECT MAX(pagina_funcion_id) FROM conf__paginas_funciones) AS max_pagina_funcion_id,
       (SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__productos') AS tabla_id_productos,
       (SELECT COUNT(*) FROM conf__paginas WHERE url = 'productos_generales.php') AS ya_registrada;

START TRANSACTION;

-- ============================================================
-- 1) Página: Productos - Generales
--    Cuelga del mismo padre que Productos (padre_id = 37), justo después
--    (orden 421). Mismo icono (68) y tabla (25) que Productos.
-- ============================================================
INSERT INTO `conf__paginas`
    (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`)
VALUES
    (97, 2, 'Productos - Generales', 'productos_generales.php', 68, 'Productos y servicios con todos sus campos, sin compatibilidad', 37, 421, 25, 1, 1, 0, NULL);

-- ============================================================
-- 2) Funciones de la página (motor de botones) — mismo patrón que
--    Productos (pagina_id = 40, funciones 161-165):
--      Agregar     : origen 0 (sin registro) -> destino 1 (Activo)
--      Editar      : origen 1 (Activo)       -> destino 1 (Activo)
--      Inhabilitar : origen 1 (Activo)       -> destino 2 (Inactivo)  accion_js = 'inactivar'
--      Habilitar   : origen 2 (Inactivo)     -> destino 1 (Activo)
--    Se omite "Visualizar" (origen 0), igual que en productos_tipos.
-- ============================================================
INSERT INTO `conf__paginas_funciones`
    (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
VALUES
    (437, 97, 42, 3, 1, 'Agregar',     'agregar',   NULL, 0, 1, 0,   1),
    (438, 97, 40, 1, 1, 'Editar',      'editar',    NULL, 1, 1, 10,  1),
    (439, 97, 45, 4, 1, 'Inhabilitar', 'inactivar', NULL, 1, 2, 20,  1),
    (440, 97, 46, 3, 1, 'Habilitar',   'habilitar', NULL, 2, 1, 100, 1);

COMMIT;

-- ============================================================
-- 3) Permisos: los usuarios de USUARIOS_ACCESO_TOTAL ven la página
--    directamente. Para el resto, asignar las funciones 437-440 al perfil
--    que corresponda desde la pantalla de funciones por perfil
--    (conf__empresas_perfiles_funciones.asignado = 1); sin eso la página
--    no aparece en el menú.
-- ============================================================
