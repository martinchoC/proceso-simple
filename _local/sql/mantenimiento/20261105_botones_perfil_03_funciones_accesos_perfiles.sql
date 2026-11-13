-- =====================================================================================================
-- Botones por perfil en Accesos por Perfil (páginas 441 / 941): funciones que faltaban
-- -----------------------------------------------------------------------------------------------------
-- La página solo tenía "consultar" (3401 / 8381). Desde el 2026-11-05 dar y quitar accesos por sucursal se valida
-- contra su función (accesos_perfiles_ajax.php: permisos_exigir_accion; los botones llevan data-funcion y
-- header1.php oculta los que el perfil no tiene):
--   3549 / 8549  agregar    Dar acceso      (acción agregar_grant)
--   3550 / 8550  inactivar  Quitar acceso   (acción eliminar_grant)
-- Permisos: a todo perfil (base y de empresa) que hoy tiene el "consultar" de la página.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

DROP TEMPORARY TABLE IF EXISTS tmp_fn_accesos;
CREATE TEMPORARY TABLE tmp_fn_accesos (
    pagina_funcion_id INT NOT NULL PRIMARY KEY,
    pagina_id INT NOT NULL,
    icono_id INT NULL,
    color_id INT NULL,
    nombre_funcion VARCHAR(100) NOT NULL,
    accion_js VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    orden INT NOT NULL,
    base_funcion_id INT NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_fn_accesos VALUES
    (3549, 441, 42, 3, 'Dar acceso',    'agregar',   'Dar acceso a una sucursal, boca, punto de venta o tipo de comprobante', 10, 3401),
    (3550, 441, 45, 4, 'Quitar acceso', 'inactivar', 'Quitar un acceso dado al perfil',                                      20, 3401),
    (8549, 941, 42, 3, 'Dar acceso',    'agregar',   'Dar acceso a una sucursal, boca, punto de venta o tipo de comprobante', 10, 8381),
    (8550, 941, 45, 4, 'Quitar acceso', 'inactivar', 'Quitar un acceso dado al perfil',                                      20, 8381);

START TRANSACTION;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT t.pagina_funcion_id, t.pagina_id, t.icono_id, t.color_id, 1, t.nombre_funcion, t.accion_js, t.descripcion, 0, 0, t.orden, 1
FROM tmp_fn_accesos t
JOIN conf__paginas p ON p.pagina_id = t.pagina_id
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = t.pagina_funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_id = t.pagina_id AND x.accion_js = t.accion_js);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT b.perfil_id, t.pagina_funcion_id, 1
FROM tmp_fn_accesos t
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = t.pagina_funcion_id AND pf.pagina_id = t.pagina_id
JOIN conf__perfiles_funciones b ON b.pagina_funcion_id = t.base_funcion_id AND b.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = b.perfil_id AND x.pagina_funcion_id = t.pagina_funcion_id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT b.empresa_id, b.empresa_perfil_id, t.pagina_funcion_id, 1
FROM tmp_fn_accesos t
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = t.pagina_funcion_id AND pf.pagina_id = t.pagina_id
JOIN conf__empresas_perfiles_funciones b ON b.pagina_funcion_id = t.base_funcion_id AND b.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = b.empresa_perfil_id AND x.pagina_funcion_id = t.pagina_funcion_id);

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_fn_accesos;
