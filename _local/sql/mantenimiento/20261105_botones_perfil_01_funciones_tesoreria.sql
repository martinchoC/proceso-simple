-- =====================================================================================================
-- Botones por perfil en Tesorería: funciones que faltaban en Pagos, Cartera de Cheques, Movimientos internos
-- y Rendiciones de Gastos (páginas 106-109 / 606-609)
-- -----------------------------------------------------------------------------------------------------
-- Estas pantallas armaban sus botones a mano (por estado del registro) y solo tenían la función "consultar"
-- (acceso a la página), así que no había forma de quitarle a un perfil, por ejemplo, "Anular" sin quitarle la
-- página. Desde el 2026-11-05 cada botón se muestra solo si el perfil del usuario tiene asignada su función
-- (core/permisos.php: permisos_puede / usuario_puede_funcion) y la acción se valida también en el _ajax.php.
--
-- Funciones nuevas (módulo 5 = id + 5000):
--   106 Pagos        3528 agregar 0->3, 3529 editar 3->3, 3530 confirmar 3->5, 3531 eliminar 3->6, 3532 anular 5->38
--   108 Movimientos  3533 agregar 0->5, 3534 anular 5->38
--   107 Cheques      3535 depositar, 3536 debitar, 3537 rechazar (acciones sobre el cheque: 0->0)
--   109 Rendiciones  3538 agregar_anticipo, 3539 agregar_rendicion, 3540 reembolsar (0->0)
-- Permisos: para no cambiar lo que ve nadie hoy, cada función nueva se asigna a todo perfil (base y de empresa)
-- que tiene asignado el "consultar" de su página (3393-3396 / 8377-8380). Después se quitan desde
-- Perfiles > Funciones a quien no corresponda.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

DROP TEMPORARY TABLE IF EXISTS tmp_fn_tesoreria;
CREATE TEMPORARY TABLE tmp_fn_tesoreria (
    pagina_funcion_id INT NOT NULL PRIMARY KEY,
    pagina_id INT NOT NULL,
    icono_id INT NULL,
    color_id INT NULL,
    nombre_funcion VARCHAR(100) NOT NULL,
    accion_js VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    origen INT NOT NULL,
    destino INT NOT NULL,
    orden INT NOT NULL,
    base_funcion_id INT NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_fn_tesoreria VALUES
    (3528, 106, 42, 3, 'Nueva Orden de Pago', 'agregar',   'Crear una orden de pago en borrador',            0, 3,  10, 3393),
    (3529, 106, 40, 1, 'Editar',              'editar',    'Editar la orden de pago en borrador',            3, 3,  30, 3393),
    (3530, 106, 38, 3, 'Confirmar Pago',      'confirmar', 'Emitir la OP (o pedir su aprobación)',           3, 5,  40, 3393),
    (3531, 106, 47, 4, 'Eliminar Borrador',   'eliminar',  'Eliminar la orden de pago en borrador',          3, 6,  60, 3393),
    (3532, 106, 45, 4, 'Anular OP',           'anular',    'Anular la orden de pago y revertir movimientos', 5, 38, 50, 3393),
    (3533, 108, 42, 3, 'Nueva Transferencia', 'agregar',   'Registrar una transferencia entre cuentas',      0, 5,  10, 3395),
    (3534, 108, 45, 4, 'Anular',              'anular',    'Anular la transferencia',                        5, 38, 50, 3395),
    (3535, 107, 38, 3, 'Depositar',           'depositar', 'Depositar cheques de terceros en un banco',      0, 0,  20, 3394),
    (3536, 107, 38, 1, 'Debitar',             'debitar',   'Registrar el débito de un cheque propio',        0, 0,  30, 3394),
    (3537, 107, 45, 4, 'Rechazar',            'rechazar',  'Registrar el rechazo de un cheque',              0, 0,  40, 3394),
    (3538, 109, 42, 1, 'Nuevo Anticipo',      'agregar_anticipo',  'Entregar un anticipo de gastos a rendir', 0, 0, 10, 3396),
    (3539, 109, 42, 3, 'Nueva Rendición',     'agregar_rendicion', 'Rendir comprobantes de gastos',           0, 0, 20, 3396),
    (3540, 109, 38, 1, 'Reembolsar',          'reembolsar',        'Reembolsar el saldo a favor del empleado', 0, 0, 30, 3396);

-- Módulo 5 (Gestión Normal): mismas funciones, id + 5000, página + 500, base = "consultar" de 606..609 (8377..8380)
INSERT INTO tmp_fn_tesoreria VALUES
    (8528, 606, 42, 3, 'Nueva Orden de Pago', 'agregar',   'Crear una orden de pago en borrador',            0, 3,  10, 8377),
    (8529, 606, 40, 1, 'Editar',              'editar',    'Editar la orden de pago en borrador',            3, 3,  30, 8377),
    (8530, 606, 38, 3, 'Confirmar Pago',      'confirmar', 'Emitir la OP (o pedir su aprobación)',           3, 5,  40, 8377),
    (8531, 606, 47, 4, 'Eliminar Borrador',   'eliminar',  'Eliminar la orden de pago en borrador',          3, 6,  60, 8377),
    (8532, 606, 45, 4, 'Anular OP',           'anular',    'Anular la orden de pago y revertir movimientos', 5, 38, 50, 8377),
    (8533, 608, 42, 3, 'Nueva Transferencia', 'agregar',   'Registrar una transferencia entre cuentas',      0, 5,  10, 8379),
    (8534, 608, 45, 4, 'Anular',              'anular',    'Anular la transferencia',                        5, 38, 50, 8379),
    (8535, 607, 38, 3, 'Depositar',           'depositar', 'Depositar cheques de terceros en un banco',      0, 0,  20, 8378),
    (8536, 607, 38, 1, 'Debitar',             'debitar',   'Registrar el débito de un cheque propio',        0, 0,  30, 8378),
    (8537, 607, 45, 4, 'Rechazar',            'rechazar',  'Registrar el rechazo de un cheque',              0, 0,  40, 8378),
    (8538, 609, 42, 1, 'Nuevo Anticipo',      'agregar_anticipo',  'Entregar un anticipo de gastos a rendir', 0, 0, 10, 8380),
    (8539, 609, 42, 3, 'Nueva Rendición',     'agregar_rendicion', 'Rendir comprobantes de gastos',           0, 0, 20, 8380),
    (8540, 609, 38, 1, 'Reembolsar',          'reembolsar',        'Reembolsar el saldo a favor del empleado', 0, 0, 30, 8380);

START TRANSACTION;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT t.pagina_funcion_id, t.pagina_id, t.icono_id, t.color_id, 1, t.nombre_funcion, t.accion_js, t.descripcion,
       t.origen, t.destino, t.orden, 1
FROM tmp_fn_tesoreria t
JOIN conf__paginas p ON p.pagina_id = t.pagina_id
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = t.pagina_funcion_id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_id = t.pagina_id AND x.accion_js = t.accion_js);

-- Perfiles base que tienen el acceso a la página
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT b.perfil_id, t.pagina_funcion_id, 1
FROM tmp_fn_tesoreria t
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = t.pagina_funcion_id AND pf.pagina_id = t.pagina_id
JOIN conf__perfiles_funciones b ON b.pagina_funcion_id = t.base_funcion_id AND b.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = b.perfil_id AND x.pagina_funcion_id = t.pagina_funcion_id);

-- Perfiles de cada empresa que tienen el acceso a la página (la fuente que lee core/permisos.php)
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT b.empresa_id, b.empresa_perfil_id, t.pagina_funcion_id, 1
FROM tmp_fn_tesoreria t
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = t.pagina_funcion_id AND pf.pagina_id = t.pagina_id
JOIN conf__empresas_perfiles_funciones b ON b.pagina_funcion_id = t.base_funcion_id AND b.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = b.empresa_perfil_id AND x.pagina_funcion_id = t.pagina_funcion_id);

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_fn_tesoreria;
