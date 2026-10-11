-- =====================================================================
-- MÓDULO 6: SINDICATOS — FASE 1: CONFIGURACIÓN, TABLAS, PÁGINAS Y PERMISOS
-- Fecha: 2026-10-11
-- Especificación: docs/sindicatos/01_especificacion_modulo_sindicatos.md (§6, §9)
--
-- Bloques de IDs:
--   conf__modulos:                 6 (Sindicatos)
--   conf__tablas:                  501–520
--   conf__tablas_estados_registros: 3001–3040
--   conf__perfiles:                601–606
--   conf__paginas:                 1000 (menú), 1001–1006, 1009, 1010–1012, 1019
--   conf__paginas_funciones:       9001–9040
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Módulo 6 en conf__modulos
-- ---------------------------------------------------------------------
INSERT INTO `conf__modulos` (`modulo_id`, `modulo`, `base_datos`, `modulo_url`, `email_envio_modulo`, `layout_nombre`, `usuario_temp`, `session_temp`, `imagen_id`, `depende_id`, `tabla_estado_registro_id`)
VALUES (6, 'Sindicatos', 'sindi__', 'sindicatos', 'info@developsam.com', 'default', NULL, NULL, 23, 0, 1)
ON DUPLICATE KEY UPDATE
  `modulo` = VALUES(`modulo`),
  `modulo_url` = VALUES(`modulo_url`),
  `base_datos` = VALUES(`base_datos`),
  `tabla_estado_registro_id` = 1;

-- Habilitar módulo 6 en empresas sindicales existentes (ej. CEC Patagones empresa 3)
INSERT IGNORE INTO `conf__empresas_modulos` (`empresa_id`, `modulo_id`, `tabla_estado_registro_id`)
VALUES (3, 6, 1);

-- ---------------------------------------------------------------------
-- 2) Registro de tablas en conf__tablas (501–520)
-- ---------------------------------------------------------------------
INSERT INTO `conf__tablas` (`tabla_id`, `modulo_id`, `tabla_nombre`, `tabla_descripcion`, `tabla_tipo_id`, `tabla_estado_registro_id`) VALUES
(501, 6, 'sindi__parametros',           'Parámetros y credenciales del sindicato', 1, 1),
(502, 6, 'sindi__convenios',            'Convenios colectivos de trabajo',         1, 1),
(503, 6, 'sindi__conceptos',            'Conceptos de aporte sindical',            1, 1),
(504, 6, 'sindi__categorias',           'Categorías del CCT',                      1, 1),
(505, 6, 'sindi__categorias_escalas',   'Escalas salariales por período',          1, 1),
(506, 6, 'sindi__vencimientos',         'Vencimientos por terminación de CUIT',    1, 1),
(507, 6, 'sindi__tasas_interes',        'Tasas de interés mensual por tramos',     1, 1),
(508, 6, 'sindi__categorias_mora',      'Categorías de mora para fiscalización',   1, 1),
(509, 6, 'sindi__obras_sociales',       'Catálogo nacional de obras sociales RNOS',1, 1),
(510, 6, 'sindi__novedades',            'Novedades de personal',                   1, 1),
(511, 6, 'sindi__bajas_motivos',        'Motivos de baja de empleados',            1, 1),
(512, 6, 'sindi__medios_pago',          'Medios de cobro y credenciales bancarias',1, 1),
(513, 6, 'sindi__medios_pago_layout',   'Layouts de rendición bancaria',           1, 1),
(514, 6, 'sindi__empleadores',          'Padrón de empleadores',                   2, 1),
(515, 6, 'sindi__estudios',             'Padrón de estudios contables',            1, 1),
(516, 6, 'sindi__estudios_empleadores', 'Vinculaciones estudio-empleador',         2, 1),
(517, 6, 'sindi__personas',             'Padrón de trabajadores y afiliados',      1, 1),
(518, 6, 'sindi__relaciones_laborales', 'Relaciones laborales',                    1, 1),
(519, 6, 'sindi__usuarios_empleadores', 'Accesos de usuarios a empleadores',       1, 1),
(520, 6, 'sindi__empleadores_periodos', 'Cumplimiento mensual de DDJJ',           2, 1)
ON DUPLICATE KEY UPDATE
  `tabla_nombre` = VALUES(`tabla_nombre`),
  `tabla_descripcion` = VALUES(`tabla_descripcion`),
  `tabla_tipo_id` = VALUES(`tabla_tipo_id`),
  `tabla_estado_registro_id` = 1;

-- ---------------------------------------------------------------------
-- 3) Estados por tabla en conf__tablas_estados_registros (3001–3040)
-- ---------------------------------------------------------------------
INSERT INTO `conf__tablas_estados_registros` (`tabla_estado_registro_id`, `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `orden`, `color_id`, `es_inicial`, `es_final`) VALUES
-- 501 sindi__parametros
(3001, 501, 1, 'Activo', 1, 1, 1, 0),
(3002, 501, 2, 'Inactivo', 2, 2, 0, 1),
-- 502 sindi__convenios
(3003, 502, 1, 'Activo', 1, 1, 1, 0),
(3004, 502, 2, 'Inactivo', 2, 2, 0, 1),
-- 503 sindi__conceptos
(3005, 503, 1, 'Activo', 1, 1, 1, 0),
(3006, 503, 2, 'Inactivo', 2, 2, 0, 1),
-- 506 sindi__vencimientos
(3007, 506, 1, 'Activo', 1, 1, 1, 0),
(3008, 506, 2, 'Inactivo', 2, 2, 0, 1),
-- 507 sindi__tasas_interes
(3009, 507, 1, 'Activo', 1, 1, 1, 0),
(3010, 507, 2, 'Inactivo', 2, 2, 0, 1),
-- 512 sindi__medios_pago
(3011, 512, 1, 'Activo', 1, 1, 1, 0),
(3012, 512, 2, 'Inactivo', 2, 2, 0, 1),
-- 514 sindi__empleadores
(3013, 514, 1, 'Activo', 1, 1, 1, 0),
(3014, 514, 2, 'Inactivo', 2, 2, 0, 1),
-- 515 sindi__estudios
(3015, 515, 1, 'Activo', 1, 1, 1, 0),
(3016, 515, 2, 'Inactivo', 2, 2, 0, 1),
-- 516 sindi__estudios_empleadores
(3017, 516, 4,  'Pendiente de Aprobación', 1, 3, 1, 0),
(3018, 516, 1,  'Aprobado / Activo',       2, 1, 0, 0),
(3019, 516, 25, 'Rechazado',               3, 2, 0, 1),
(3020, 516, 2,  'Inactivo / Desvinculado', 4, 4, 0, 1),
-- 517 sindi__personas
(3021, 517, 1, 'Activo', 1, 1, 1, 0),
(3022, 517, 2, 'Inactivo', 2, 2, 0, 1)
ON DUPLICATE KEY UPDATE
  `tabla_estado_registro` = VALUES(`tabla_estado_registro`),
  `orden` = VALUES(`orden`),
  `color_id` = VALUES(`color_id`);

-- ---------------------------------------------------------------------
-- 4) Perfiles del módulo 6 en conf__perfiles
-- ---------------------------------------------------------------------
INSERT INTO `conf__perfiles` (`perfil_id`, `modulo_id`, `perfil_nombre`, `tabla_estado_registro_id`) VALUES
(601, 6, 'Administrador Sindicato', 1),
(602, 6, 'Operador Sindicato',      1),
(603, 6, 'Inspector Sindicato',     1),
(604, 6, 'Empleador',               1),
(605, 6, 'Estudio Contable',        1),
(606, 6, 'Delegado',                1)
ON DUPLICATE KEY UPDATE
  `perfil_nombre` = VALUES(`perfil_nombre`),
  `tabla_estado_registro_id` = 1;

-- ---------------------------------------------------------------------
-- 5) Páginas del módulo 6 en conf__paginas
-- ---------------------------------------------------------------------
INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`) VALUES
-- Menú raíz y secciones
(1000, 6, 'Sindicatos',            '#',                 118, 'Gestión de Sindicato y Aportes', 0, 260, 0, 1, 1, 0, NULL),
(1009, 6, 'Configuración Gremial', '#',                  48, 'Parámetros, convenios y medios de pago', 1000, 10, 0, 1, 1, 0, NULL),
(1019, 6, 'Padrón',                '#',                  19, 'Empleadores, estudios y trabajadores',   1000, 20, 0, 1, 1, 0, NULL),

-- Pantallas de configuración
(1001, 6, 'Parámetros del Sindicato', 'parametros.php',    139, 'Configuración general, correo y reglas', 1009, 10, 501, 1, 1, 0, NULL),
(1002, 6, 'Convenios y Escalas',     'convenios.php',      23, 'Convenios CCT, categorías y escalas salariales', 1009, 20, 502, 1, 1, 0, NULL),
(1003, 6, 'Conceptos de Aporte',     'conceptos.php',       9, 'Conceptos de aporte mensual (Fondo Social, Cuota)', 1009, 30, 503, 1, 1, 0, NULL),
(1004, 6, 'Vencimientos',            'vencimientos.php',   26, 'Cronograma de vencimientos por terminación de CUIT', 1009, 40, 506, 1, 1, 0, NULL),
(1005, 6, 'Tasas de Interés',        'tasas_interes.php',  26, 'Tramos de tasas resarcitorias mensuales', 1009, 50, 507, 1, 1, 0, NULL),
(1006, 6, 'Medios de Pago',          'medios_pago.php',   105, 'Configuración de canales y credenciales bancarias', 1009, 60, 512, 1, 1, 0, NULL),

-- Pantallas de padrón
(1010, 6, 'Empleadores',             'empleadores.php',     9, 'Padrón general de empleadores aportantes', 1019, 10, 514, 1, 1, 0, NULL),
(1011, 6, 'Estudios Contables',      'estudios.php',       19, 'Estudios contables y solicitudes de vinculación', 1019, 20, 515, 1, 1, 0, NULL),
(1012, 6, 'Personas / Trabajadores', 'personas.php',       68, 'Padrón de personas físicas y trabajadores', 1019, 30, 517, 1, 1, 0, NULL)
ON DUPLICATE KEY UPDATE
  `pagina` = VALUES(`pagina`),
  `url` = VALUES(`url`),
  `padre_id` = VALUES(`padre_id`),
  `orden` = VALUES(`orden`),
  `tabla_id` = VALUES(`tabla_id`),
  `tabla_estado_registro_id` = 1;

-- ---------------------------------------------------------------------
-- 6) Funciones de página en conf__paginas_funciones (9001–9040)
-- ---------------------------------------------------------------------
INSERT INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `nombre_funcion`, `accion_js`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`) VALUES
-- 1001 Parámetros
(9001, 1001, 'Agregar',     'agregar',     0, 1, 10, 1),
(9002, 1001, 'Editar',      'editar',      1, 1, 20, 1),
(9003, 1001, 'Inhabilitar', 'inactivar',   1, 2, 30, 1),
(9004, 1001, 'Habilitar',   'habilitar',   2, 1, 40, 1),

-- 1002 Convenios
(9005, 1002, 'Agregar',     'agregar',     0, 1, 10, 1),
(9006, 1002, 'Editar',      'editar',      1, 1, 20, 1),
(9007, 1002, 'Inhabilitar', 'inactivar',   1, 2, 30, 1),
(9008, 1002, 'Habilitar',   'habilitar',   2, 1, 40, 1),

-- 1003 Conceptos
(9009, 1003, 'Agregar',     'agregar',     0, 1, 10, 1),
(9010, 1003, 'Editar',      'editar',      1, 1, 20, 1),
(9011, 1003, 'Inhabilitar', 'inactivar',   1, 2, 30, 1),
(9012, 1003, 'Habilitar',   'habilitar',   2, 1, 40, 1),

-- 1004 Vencimientos
(9013, 1004, 'Agregar',     'agregar',     0, 1, 10, 1),
(9014, 1004, 'Editar',      'editar',      1, 1, 20, 1),
(9015, 1004, 'Inhabilitar', 'inactivar',   1, 2, 30, 1),
(9016, 1004, 'Habilitar',   'habilitar',   2, 1, 40, 1),

-- 1005 Tasas de Interés
(9017, 1005, 'Agregar',     'agregar',     0, 1, 10, 1),
(9018, 1005, 'Editar',      'editar',      1, 1, 20, 1),
(9019, 1005, 'Inhabilitar', 'inactivar',   1, 2, 30, 1),
(9020, 1005, 'Habilitar',   'habilitar',   2, 1, 40, 1),

-- 1006 Medios de Pago
(9021, 1006, 'Agregar',          'agregar',          0, 1, 10, 1),
(9022, 1006, 'Editar',           'editar',           1, 1, 20, 1),
(9023, 1006, 'Inhabilitar',      'inactivar',        1, 2, 30, 1),
(9024, 1006, 'Habilitar',        'habilitar',        2, 1, 40, 1),
(9025, 1006, 'Probar Conexión',  'probar_conexion',  1, 1, 50, 1),

-- 1010 Empleadores
(9026, 1010, 'Agregar',     'agregar',     0, 1, 10, 1),
(9027, 1010, 'Editar',      'editar',      1, 1, 20, 1),
(9028, 1010, 'Inhabilitar', 'inactivar',   1, 2, 30, 1),
(9029, 1010, 'Habilitar',   'habilitar',   2, 1, 40, 1),

-- 1011 Estudios
(9030, 1011, 'Agregar',              'agregar',              0, 1,  10, 1),
(9031, 1011, 'Editar',               'editar',               1, 1,  20, 1),
(9032, 1011, 'Inhabilitar',          'inactivar',            1, 2,  30, 1),
(9033, 1011, 'Habilitar',            'habilitar',            2, 1,  40, 1),
(9034, 1011, 'Solicitar Vínculo',    'solicitar_vinculacion',0, 4,  50, 1),
(9035, 1011, 'Aprobar Vínculo',      'aprobar',              4, 1,  60, 1),
(9036, 1011, 'Rechazar Vínculo',     'rechazar',             4, 25, 70, 1),

-- 1012 Personas
(9037, 1012, 'Agregar',     'agregar',     0, 1, 10, 1),
(9038, 1012, 'Editar',      'editar',      1, 1, 20, 1),
(9039, 1012, 'Inhabilitar', 'inactivar',   1, 2, 30, 1),
(9040, 1012, 'Habilitar',   'habilitar',   2, 1, 40, 1)
ON DUPLICATE KEY UPDATE
  `nombre_funcion` = VALUES(`nombre_funcion`),
  `accion_js` = VALUES(`accion_js`),
  `tabla_estado_registro_origen_id` = VALUES(`tabla_estado_registro_origen_id`),
  `tabla_estado_registro_destino_id` = VALUES(`tabla_estado_registro_destino_id`);

-- ---------------------------------------------------------------------
-- 7) Permisos base para perfiles en conf__perfiles_funciones
-- ---------------------------------------------------------------------
-- Perfil 601 (Administrador Sindicato) tiene todas las funciones 9001–9040
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT 601, pf.pagina_funcion_id, 1
FROM `conf__paginas_funciones` pf
WHERE pf.pagina_funcion_id BETWEEN 9001 AND 9040;

-- Perfil 602 (Operador Sindicato) tiene todas menos configuración sensible de medios de pago
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT 602, pf.pagina_funcion_id, 1
FROM `conf__paginas_funciones` pf
WHERE pf.pagina_funcion_id BETWEEN 9001 AND 9040
  AND pf.pagina_funcion_id NOT IN (9001, 9003, 9021, 9023);

-- Perfil 604 (Empleador) y 605 (Estudio) para vinculaciones
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
VALUES
(604, 9035, 1),
(604, 9036, 1),
(605, 9034, 1);

-- También asignar al perfil 1 (Superadmin)
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT 1, pf.pagina_funcion_id, 1
FROM `conf__paginas_funciones` pf
WHERE pf.pagina_funcion_id BETWEEN 9001 AND 9040;

-- ---------------------------------------------------------------------
-- 8) Siembra en conf__empresas_perfiles y conf__empresas_perfiles_funciones
--    (para que el menú y permisos respondan de inmediato a core/permisos.php)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `conf__empresas_perfiles` (`empresa_id`, `modulo_id`, `perfil_id_base`, `empresa_perfil_nombre`, `tabla_estado_registro_id`)
SELECT em.empresa_id, 6, p.perfil_id, p.perfil_nombre, 1
FROM `conf__empresas_modulos` em
CROSS JOIN `conf__perfiles` p
WHERE em.modulo_id = 6
  AND p.modulo_id = 6
  AND em.tabla_estado_registro_id = 1;

-- Asignar funciones a empresas_perfiles de esas empresas
INSERT IGNORE INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM `conf__empresas_perfiles` ep
INNER JOIN `conf__perfiles_funciones` pf ON pf.perfil_id = ep.perfil_id_base
WHERE ep.tabla_estado_registro_id = 1
  AND pf.pagina_funcion_id BETWEEN 9001 AND 9040;

COMMIT;
