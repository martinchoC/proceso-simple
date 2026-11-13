-- =====================================================================================================
-- Botones por perfil en Estudio Contable (módulo 4): funciones que faltaban en las páginas 413–416
-- -----------------------------------------------------------------------------------------------------
-- Las páginas 401–412 ya tienen las funciones de página ver / agregar / editar / inhabilitar (0 -> 0) y desde el
-- 2026-11-05 cada botón y cada acción del _ajax.php se valida contra ellas (alta y procesos = agregar, modificar y
-- cambiar estado = editar, baja / anular / revertir = inhabilitar; botones marcados data-funcion, que header1.php
-- oculta si el perfil no la tiene, y permisos_exigir_accion() en el _ajax.php). Las 413–416 solo tenían "consultar":
--   413 Cierre de ejercicio     3541 agregar (Ejecutar cierre)
--   414 Conceptos sin stock     3542 agregar, 3543 editar, 3544 inhabilitar
--   415 Recibos                 3545 agregar (Emitir recibo)
--   416 Plan de cuentas         3546 agregar, 3547 editar, 3548 inhabilitar
-- (El bloque propio del módulo, 3291–3334 / 3398–3399 / 3448–3449, está completo: se usan los siguientes libres del
-- bloque compartido, a continuación de 3528–3540 de 20261105_botones_perfil_01_funciones_tesoreria.sql.)
-- La 417 (Mini-gestión) ya tiene "agregar" (3449).
-- Permisos: para no cambiar lo que ve nadie hoy, cada función nueva se asigna a todo perfil (base y de empresa) que
-- tiene el "consultar" de su página (3397–3400). Después se quitan desde Perfiles > Funciones a quien no corresponda.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

DROP TEMPORARY TABLE IF EXISTS tmp_fn_estudio;
CREATE TEMPORARY TABLE tmp_fn_estudio (
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

INSERT INTO tmp_fn_estudio VALUES
    (3541, 413, 38, 3, 'Ejecutar cierre',  'agregar',     'Refundición y cierre patrimonial del ejercicio', 10, 3397),
    (3542, 414, 42, 3, 'Nuevo concepto',   'agregar',     'Dar de alta un concepto sin stock',              10, 3398),
    (3543, 414, 40, 1, 'Editar',           'editar',      'Editar un concepto',                             20, 3398),
    (3544, 414, 45, 4, 'Anular',           'inhabilitar', 'Dar de baja un concepto',                        30, 3398),
    (3545, 415, 42, 3, 'Emitir recibo',    'agregar',     'Emitir un recibo con retenciones sufridas',      10, 3399),
    (3546, 416, 42, 3, 'Nueva cuenta',     'agregar',     'Dar de alta una cuenta del plan',                10, 3400),
    (3547, 416, 40, 1, 'Editar',           'editar',      'Editar una cuenta del plan',                     20, 3400),
    (3548, 416, 45, 4, 'Eliminar',         'inhabilitar', 'Dar de baja una cuenta del plan',                30, 3400);

START TRANSACTION;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT t.pagina_funcion_id, t.pagina_id, t.icono_id, t.color_id, 1, t.nombre_funcion, t.accion_js, t.descripcion, 0, 0, t.orden, 1
FROM tmp_fn_estudio t
JOIN conf__paginas p ON p.pagina_id = t.pagina_id
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = t.pagina_funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_id = t.pagina_id AND x.accion_js = t.accion_js);

-- Perfiles base que tienen el acceso a la página
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT b.perfil_id, t.pagina_funcion_id, 1
FROM tmp_fn_estudio t
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = t.pagina_funcion_id AND pf.pagina_id = t.pagina_id
JOIN conf__perfiles_funciones b ON b.pagina_funcion_id = t.base_funcion_id AND b.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = b.perfil_id AND x.pagina_funcion_id = t.pagina_funcion_id);

-- Perfiles de cada empresa que tienen el acceso a la página (la fuente que lee core/permisos.php)
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT b.empresa_id, b.empresa_perfil_id, t.pagina_funcion_id, 1
FROM tmp_fn_estudio t
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = t.pagina_funcion_id AND pf.pagina_id = t.pagina_id
JOIN conf__empresas_perfiles_funciones b ON b.pagina_funcion_id = t.base_funcion_id AND b.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = b.empresa_perfil_id AND x.pagina_funcion_id = t.pagina_funcion_id);

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_fn_estudio;
