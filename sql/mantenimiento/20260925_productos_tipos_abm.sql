-- ABM "Productos - Tipos": página nueva para administrar gestion__productos_tipos
-- (Producto, Servicio, etc.) con sus flags (maneja_stock, es_compuesto,
-- afecta_facturacion) y las cuentas contables default que productos_model.php
-- copia al producto en el alta (obtenerCuentasDefaultTipoProducto).
--
-- Archivos: modules/gestion/productos_tipos.php / _ajax.php / _model.php / .js
--
-- REQUISITO PREVIO: las columnas cont_cuenta_compra_default_id,
-- cont_cuenta_venta_default_id y cont_cuenta_cmv_default_id se agregan en
-- actualizar_sql.txt (ALTER TABLE gestion__productos_tipos). Correr eso antes
-- de usar la página; acá no se vuelven a crear.
--
-- gestion__productos_tipos NO estaba registrada en conf__tablas: se da de alta
-- como tabla tipo Diccionario (tabla_tipo_id = 1) con sus 2 estados.
--
-- IDs tomados del dump casalucho.sql del 2026-09-25 (ya incluye la página 95
-- de productos_proveedores): MAX(tabla_id) = 107,
-- MAX(tabla_estado_registro_id) = 217, MAX(pagina_id) = 95,
-- MAX(pagina_funcion_id) = 432. Verificar con la consulta 0) antes de correr:
-- si la base viva avanzó, ajustar los IDs (y $pagina_idx en productos_tipos.php).

-- 0) Verificación previa (deben dar 107 / 217 / 95 / 432 y 0 filas registradas)
SELECT (SELECT MAX(tabla_id) FROM conf__tablas) AS max_tabla_id,
       (SELECT MAX(tabla_estado_registro_id) FROM conf__tablas_estados_registros) AS max_tabla_estado_registro_id,
       (SELECT MAX(pagina_id) FROM conf__paginas) AS max_pagina_id,
       (SELECT MAX(pagina_funcion_id) FROM conf__paginas_funciones) AS max_pagina_funcion_id,
       (SELECT COUNT(*) FROM conf__tablas WHERE tabla_nombre = 'gestion__productos_tipos') AS ya_registrada;

START TRANSACTION;

-- ============================================================
-- 1) Tabla física — Diccionario (tabla_tipo_id = 1), módulo gestión (2).
--    Mismos valores que el resto de las tablas gestion__productos_*.
-- ============================================================
INSERT INTO `conf__tablas`
    (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
VALUES
    (108, 'gestion__productos_tipos', 'Tipos de producto (Producto, Servicio, etc.)', 2, 1, 1, 1);

-- ============================================================
-- 2) Estados de la tabla — Activo (inicial) / Inactivo, igual que
--    gestion__productos (210/211) y gestion__productos_proveedores (73/74).
--    estado_registro_id: 1 = Activo, 2 = Inactivo (conf__estados_registros).
-- ============================================================
INSERT INTO `conf__tablas_estados_registros`
    (`tabla_estado_registro_id`, `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `orden`)
VALUES
    (218, 108, 1, 'Activo',   1, 1, 1),
    (219, 108, 2, 'Inactivo', 1, 0, 10);

-- ============================================================
-- 3) Página: Productos - Tipos
--    Cuelga de "Gestion - Tablas" (padre_id = 32), junto a
--    Productos - Categorias (430), Marcas (431), Modelos (432) y
--    Submodelos (433) — orden 429 para quedar primera del grupo.
--    icono_id = 50 (mismo que "Productos - Categorias").
-- ============================================================
INSERT INTO `conf__paginas`
    (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`)
VALUES
    (96, 2, 'Productos - Tipos', 'productos_tipos.php', 50, 'Tipos de producto y sus cuentas contables por defecto', 32, 429, 108, 1, 1, 0, NULL);

-- ============================================================
-- 4) Funciones de la página (motor de botones) — mismo patrón que
--    Productos (pagina_id = 40, funciones 161-165):
--      Agregar     : origen 0 (sin registro) -> destino 1 (Activo)
--      Editar      : origen 1 (Activo)       -> destino 1 (Activo)
--      Inhabilitar : origen 1 (Activo)       -> destino 2 (Inactivo)  accion_js = 'inactivar'
--      Habilitar   : origen 2 (Inactivo)     -> destino 1 (Activo)
--    Se omite "Visualizar" (origen 0) porque el listado ya muestra todos
--    los campos del tipo.
-- ============================================================
INSERT INTO `conf__paginas_funciones`
    (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
VALUES
    (433, 96, 42, 3, 1, 'Agregar',     'agregar',   NULL, 0, 1, 0,   1),
    (434, 96, 40, 1, 1, 'Editar',      'editar',    NULL, 1, 1, 10,  1),
    (435, 96, 45, 4, 1, 'Inhabilitar', 'inactivar', NULL, 1, 2, 20,  1),
    (436, 96, 46, 3, 1, 'Habilitar',   'habilitar', NULL, 2, 1, 100, 1);

COMMIT;

-- ============================================================
-- 5) Permisos: los usuarios de USUARIOS_ACCESO_TOTAL ven la página
--    directamente. Para el resto, asignar las funciones 433-436 al perfil
--    que corresponda desde la pantalla de funciones por perfil
--    (conf__empresas_perfiles_funciones.asignado = 1); sin eso la página
--    no aparece en el menú.
-- ============================================================
