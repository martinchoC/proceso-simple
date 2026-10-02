-- Página "Productos - Consulta": misma pantalla que Productos (productos.php)
-- pero SOLO LECTURA — sin alta, edición, cambios de estado ni ABM de
-- compatibilidad, imágenes, ubicaciones, proveedores o precios.
--
-- Archivos: modules/gestion/productos_consulta.php / productos_consulta_ajax.php
-- (el _ajax solo acepta acciones de lectura y delega en productos_ajax.php;
-- el modelo es productos_model.php, no hay _model propio).
--
-- No crea tabla ni estados nuevos: reutiliza gestion__productos (tabla_id = 25).
-- Solo agrega la página y su única función, "Visualizar", disponible en los
-- dos estados del producto (Activo e Inactivo) sin cambiar el estado.
--
-- IDs tomados de la base viva del 2026-09-26 (ya incluye la página 97 de
-- productos_generales): MAX(pagina_id) = 97, MAX(pagina_funcion_id) = 440.
-- Verificar con la consulta 0) antes de correr: si la base avanzó, ajustar los
-- IDs (y $pagina_idx en productos_consulta.php).

-- 0) Verificación previa (deben dar 97 / 440 / 25 y 0 páginas registradas)
SELECT (SELECT MAX(pagina_id) FROM conf__paginas) AS max_pagina_id,
       (SELECT MAX(pagina_funcion_id) FROM conf__paginas_funciones) AS max_pagina_funcion_id,
       (SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__productos') AS tabla_id_productos,
       (SELECT COUNT(*) FROM conf__paginas WHERE url = 'productos_consulta.php') AS ya_registrada;

START TRANSACTION;

-- ============================================================
-- 1) Página: Productos - Consulta
--    Mismo padre que Productos (padre_id = 37), después de Productos (420)
--    y Productos - Generales (421). icono_id = 37 (bi bi-search).
-- ============================================================
INSERT INTO `conf__paginas`
    (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`)
VALUES
    (98, 2, 'Productos - Consulta', 'productos_consulta.php', 37, 'Consulta de productos, solo lectura', 37, 422, 25, 1, 1, 0, NULL);

-- ============================================================
-- 2) Funciones de la página (motor de botones): solo "Visualizar", sin
--    transición de estado (origen = destino), para Activo (1) e Inactivo (2).
--    Mismo icono (37) y color (1) que la función Visualizar de Productos (162).
--    No hay función con origen 0, así que la página no muestra botón Agregar.
-- ============================================================
INSERT INTO `conf__paginas_funciones`
    (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
VALUES
    (441, 98, 37, 1, 1, 'Visualizar', 'visualizar', NULL, 1, 1, 1, 1),
    (442, 98, 37, 1, 1, 'Visualizar', 'visualizar', NULL, 2, 2, 1, 1);

COMMIT;

-- ============================================================
-- 3) Permisos: los usuarios de USUARIOS_ACCESO_TOTAL ven la página
--    directamente. Para el resto, asignar las funciones 441-442 al perfil
--    que corresponda desde la pantalla de funciones por perfil
--    (conf__empresas_perfiles_funciones.asignado = 1); sin eso la página
--    no aparece en el menú.
-- ============================================================
