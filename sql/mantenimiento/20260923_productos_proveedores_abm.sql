-- ABM "Productos - Proveedores": página nueva e independiente que lista TODOS
-- los productos junto con sus códigos de proveedor vinculados
-- (gestion__productos_proveedores), con conteo de correlación, filtros y
-- altas/bajas rápidas de vínculo. Complementa (no reemplaza) el tab
-- "Proveedores del Producto" que ya existe dentro del modal de productos.php.
--
-- La tabla física gestion__productos_proveedores YA está registrada en
-- conf__tablas (tabla_id = 30, tabla_tipo_id = 1 Diccionario) y ya tiene sus
-- 2 estados en conf__tablas_estados_registros (73 = Activo, 74 = Inactivo),
-- porque ya se usa desde el tab embebido de productos.php. Por eso acá NO se
-- inserta nada en conf__tablas ni en conf__tablas_estados_registros: solo
-- falta la página propia y sus funciones/botones.
--
-- IDs verificados contra la base viva (gestion_multipyme) el 2026-09-23:
-- MAX(pagina_id) = 94, MAX(pagina_funcion_id) = 428. El dump versionado
-- casalucho.sql está desactualizado respecto a la base real (ver nota en
-- CLAUDE.md / memoria de sesión) y no debe usarse para calcular estos IDs.

-- ============================================================
-- Página: Productos - Proveedores
-- Cuelga de "Compras" (padre_id = 47), junto a "Proveedores" (93) y
-- "Lista Precios Proveedores" (92, orden 200) — orden 210, siguiente lugar libre.
-- icono_id = 52 (mismo ícono que ya usa "Lista Precios Proveedores" en ese submenú).
-- ============================================================
INSERT INTO `conf__paginas`
    (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`)
VALUES
    (95, 2, 'Productos - Proveedores', 'productos_proveedores.php', 52, 'Correlación de productos con códigos de proveedor', 47, 210, 30, 1, 1, 0, NULL);

-- ============================================================
-- Funciones de la página (motor de botones) — mismo patrón que
-- "Lista Precios Proveedores" (pagina_id = 92, funciones 409-413):
--   Agregar   : origen 0 (sin registro previo) -> destino 1 (Activo)
--   Editar    : origen 1 (Activo) -> destino 1 (Activo)
--   Inhabilitar (accion_js = 'inactivar') : origen 1 (Activo) -> destino 2 (Inactivo)
--   Habilitar : origen 2 (Inactivo) -> destino 1 (Activo)  [reactivación de vínculo]
-- Los estados 1/2 acá son conf__estados_registros.estado_registro_id
-- (1 = Activo, 2 = Inactivo), igual que en el resto de las páginas tipo
-- Diccionario del proyecto.
-- ============================================================
INSERT INTO `conf__paginas_funciones`
    (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
VALUES
    (429, 95, 42, 3, 1, 'Agregar',     'agregar',    NULL, 0, 1, 0,   1),
    (430, 95, 40, 1, 1, 'Editar',      'editar',     NULL, 1, 1, 10,  1),
    (431, 95, 45, 4, 1, 'Inhabilitar', 'inactivar',  NULL, 1, 2, 20,  1),
    (432, 95, 46, 3, 1, 'Habilitar',   'habilitar',  NULL, 2, 1, 100, 1);
