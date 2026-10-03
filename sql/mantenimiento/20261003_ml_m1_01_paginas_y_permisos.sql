-- =====================================================================
-- Matriz legal - M1 (Territorio y Grupo), script 01: PÁGINAS Y PERMISOS
-- 2026-10-03
-- =====================================================================
-- Qué hace (docs/matriz-legal/09-diseno-m1-territorio-y-grupo.md):
--   1) Alta en `conf__paginas` de las pantallas de M1 (IDs 301 a 307)
--      bajo el Módulo 3 (`ml`).
--   2) Alta en `conf__paginas_funciones` de los botones de acción y transiciones
--      de estado (IDs 3001 a 3040).
--   3) Asignación en `conf__perfiles_funciones` para los perfiles base 301 a 308.
--   4) Propagación a `conf__empresas_perfiles_funciones` para las Empresas 2 y 3.
--
-- ---------------------------------------------------------------------
-- CÓMO SE CORRE
-- ---------------------------------------------------------------------
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261003_ml_m1_01_paginas_y_permisos.sql
-- =====================================================================

SET NAMES utf8mb4;
SET @m1_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

START TRANSACTION;

-- =====================================================================
-- 1) PÁGINAS Y MENÚS (`conf__paginas`)
-- =====================================================================
-- Reutilizamos páginas existentes:
--   padre_id = 0: Menús de primer nivel
--   padre_id = 63: Configuración y Tablas (existente)
--   padre_id = 301: Estructura Corporativa (nuevo agrupador)
INSERT INTO `conf__paginas`
    (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`)
VALUES
    -- Menú agrupador Estructura Corporativa
    (301, 3, 'Estructura Corporativa',  '#',                         1, 'Gestión de grupos, regiones y entidades',       0,   20,   0, 1, 1, 0),

    -- Páginas bajo Estructura Corporativa (padre_id = 301)
    (302, 3, 'Grupos y Regiones',       'grupos_regiones.php',        1, 'Grupos corporativos y regiones de gestión',   301,  10, 201, 1, 1, 0),
    (303, 3, 'Eventos Societarios',     'empresas_eventos.php',       1, 'Fusiones, escisiones y cambios de nombre',    301,  20, 206, 2, 1, 0),
    (304, 3, 'Entidades',               'entidades.php',              1, 'Activos y unidades operativas de control',     301,  30, 212, 1, 1, 0),

    -- Páginas bajo Configuración y Tablas (padre_id = 63)
    (305, 3, 'Territorios y Husos',     'territorios.php',            1, 'Árbol territorial y husos horarios',          63,   10, 208, 1, 1, 0),
    (306, 3, 'Feriados',                'feriados.php',               1, 'Calendario de feriados por territorio',       63,   20, 210, 1, 1, 0),
    (307, 3, 'Tipos de Entidad y Datos','entidades_tipos.php',        1, 'Catálogos de tipos de entidades y datos',     63,   30, 211, 1, 1, 0)
ON DUPLICATE KEY UPDATE
    `modulo_id`                = VALUES(`modulo_id`),
    `pagina`                   = VALUES(`pagina`),
    `url`                      = VALUES(`url`),
    `pagina_descripcion`       = VALUES(`pagina_descripcion`),
    `padre_id`                 = VALUES(`padre_id`),
    `orden`                    = VALUES(`orden`),
    `tabla_id`                 = VALUES(`tabla_id`),
    `pagina_patron_id`         = VALUES(`pagina_patron_id`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- =====================================================================
-- 2) FUNCIONES Y BOTONES (`conf__paginas_funciones`)
-- =====================================================================
-- Origen 0 = sin registro (alta)
-- Origen/Destino usan IDs genéricos de conf__estados_registros
INSERT INTO `conf__paginas_funciones`
    (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
VALUES
    -- -----------------------------------------------------------------
    -- Página 305: Territorios y Husos
    -- -----------------------------------------------------------------
    (3001, 305, 1, 1, 1, 'Agregar Territorio',  'agregar',             'Crear nuevo nodo territorial',           0, 1,  1, 1),
    (3002, 305, 1, 1, 2, 'Editar Territorio',   'editar',              'Modificar datos del territorio',          1, 1, 10, 1),
    (3003, 305, 1, 5, 2, 'Cambiar Pertenencia', 'cambiar_pertenencia', 'Asignar nuevo nodo superior con fecha',  1, 1, 20, 1),
    (3004, 305, 1, 4, 3, 'Inactivar',           'inactivar',           'Pasar territorio a estado inactivo',      1, 2, 30, 1),
    (3005, 305, 1, 3, 4, 'Activar',             'activar',             'Restablecer territorio a estado activo',  2, 1, 40, 1),

    -- -----------------------------------------------------------------
    -- Página 306: Feriados
    -- -----------------------------------------------------------------
    (3010, 306, 1, 1, 1, 'Agregar Feriado',     'agregar',             'Registrar nuevo día no laborable',        0, 1,  1, 1),
    (3011, 306, 1, 1, 2, 'Editar Feriado',      'editar',              'Modificar fecha o descripción',           1, 1, 10, 1),
    (3012, 306, 1, 4, 3, 'Eliminar Feriado',    'eliminar',            'Dar de baja un feriado',                  1, 2, 20, 1),

    -- -----------------------------------------------------------------
    -- Página 302: Grupos y Regiones
    -- -----------------------------------------------------------------
    (3015, 302, 1, 1, 1, 'Agregar Grupo',       'agregar_grupo',       'Crear grupo corporativo',                 0, 1,  1, 1),
    (3016, 302, 1, 1, 2, 'Editar Grupo',        'editar_grupo',        'Modificar datos del grupo',               1, 1, 10, 1),
    (3017, 302, 1, 1, 1, 'Agregar Región',      'agregar_region',      'Crear región de gestión del grupo',       0, 1, 20, 1),
    (3018, 302, 1, 1, 2, 'Editar Región',       'editar_region',       'Modificar región de gestión',             1, 1, 30, 1),
    (3019, 302, 1, 5, 2, 'Asignar Empresa',     'asignar_empresa',     'Vincular empresa a región de gestión',    1, 1, 40, 1),

    -- -----------------------------------------------------------------
    -- Página 303: Eventos Societarios (Proceso Corto: 3 Borrador -> 4 Pendiente -> 5 Confirmado)
    -- -----------------------------------------------------------------
    (3020, 303, 1, 1, 1, 'Registrar Evento',    'agregar',             'Cargar nuevo evento en borrador',         0, 3,  1, 1),
    (3021, 303, 1, 1, 2, 'Editar Evento',       'editar',              'Modificar datos del evento borrador',     3, 3, 10, 1),
    (3022, 303, 1, 5, 2, 'Enviar a Aprobación', 'enviar_aprobacion',   'Enviar evento para dictamen legal/matriz',3, 4, 20, 1),
    (3023, 303, 1, 3, 4, 'Confirmar Evento',    'confirmar',           'Aprobar y consolidar cambio societario',  4, 5, 30, 1),
    (3024, 303, 1, 4, 3, 'Desestimar / Eliminar','eliminar',           'Eliminar o desestimar evento',            3, 6, 40, 1),

    -- -----------------------------------------------------------------
    -- Página 304: Entidades
    -- -----------------------------------------------------------------
    (3030, 304, 1, 1, 1, 'Agregar Entidad',     'agregar',             'Dar de alta nueva unidad sujeta a control',0, 1,  1, 1),
    (3031, 304, 1, 1, 2, 'Editar Entidad',      'editar',              'Modificar datos de la entidad',           1, 1, 10, 1),
    (3032, 304, 1, 5, 2, 'Transferir Empresa',  'transferir',          'Transferir titularidad a otra empresa',   1, 1, 20, 1),
    (3033, 304, 1, 5, 2, 'Cambiar Ubicación',   'cambiar_ubicacion',   'Actualizar sede o países de operación',   1, 1, 30, 1),
    (3034, 304, 1, 4, 3, 'Inactivar Entidad',   'inactivar',           'Pasar entidad a estado inactivo',         1, 2, 40, 1),
    (3035, 304, 1, 3, 4, 'Activar Entidad',     'activar',             'Restablecer entidad a estado activo',     2, 1, 50, 1),

    -- -----------------------------------------------------------------
    -- Página 307: Tipos de Entidad y Datos
    -- -----------------------------------------------------------------
    (3038, 307, 1, 1, 1, 'Agregar Tipo Entidad','agregar_tipo',        'Crear nuevo tipo de entidad',             0, 1,  1, 1),
    (3039, 307, 1, 1, 2, 'Editar Tipo Entidad', 'editar_tipo',         'Modificar tipo de entidad',               1, 1, 10, 1),
    (3040, 307, 1, 1, 1, 'Agregar Tipo Dato',   'agregar_dato',         'Definir nuevo campo complementario',      0, 1, 20, 1),
    (3041, 307, 1, 1, 2, 'Editar Tipo Dato',    'editar_dato',          'Modificar campo complementario',          1, 1, 30, 1)
ON DUPLICATE KEY UPDATE
    `pagina_id`                        = VALUES(`pagina_id`),
    `nombre_funcion`                   = VALUES(`nombre_funcion`),
    `accion_js`                        = VALUES(`accion_js`),
    `descripcion`                      = VALUES(`descripcion`),
    `tabla_estado_registro_origen_id`  = VALUES(`tabla_estado_registro_origen_id`),
    `tabla_estado_registro_destino_id` = VALUES(`tabla_estado_registro_destino_id`),
    `orden`                            = VALUES(`orden`),
    `tabla_estado_registro_id`         = VALUES(`tabla_estado_registro_id`);

-- =====================================================================
-- 3) ASIGNACIÓN DE PERMISOS BASE (`conf__perfiles_funciones`)
-- =====================================================================
-- Perfiles base del Módulo 3:
--   301: Administrador de matriz (todas las funciones)
--   302: Gestor normativo (lectura de entidades y territorios)
--   303: Área legal (lectura + confirmación de eventos 3023)
--   304: Responsable de empresa (entidades 3030..3035, feriados 3010..3012, eventos 3020..3022)
--   307: Auditor (solo lectura)
--   308: Consulta (solo lectura)

-- 301 Administrador de Matriz: todas las funciones de M1
INSERT INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT 301, pf.pagina_funcion_id, 1
  FROM `conf__paginas_funciones` pf
 WHERE pf.pagina_funcion_id BETWEEN 3001 AND 3041
ON DUPLICATE KEY UPDATE `asignado` = 1;

-- 303 Área Legal: confirmación de eventos
INSERT INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
VALUES (303, 3023, 1)
ON DUPLICATE KEY UPDATE `asignado` = 1;

-- 304 Responsable de Empresa: feriados, eventos (borrador/envío) y gestión de sus entidades
INSERT INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT 304, pf.pagina_funcion_id, 1
  FROM `conf__paginas_funciones` pf
 WHERE pf.pagina_funcion_id IN (3010, 3011, 3012, 3020, 3021, 3022, 3030, 3031, 3033, 3034, 3035)
ON DUPLICATE KEY UPDATE `asignado` = 1;

-- =====================================================================
-- 4) PROPAGACIÓN A EMPRESAS (`conf__empresas_perfiles_funciones`)
-- =====================================================================
-- Empresa 2 (Casa Lucho - titular):
--   perfil 301 -> empresa_perfil_id 301 (todas)
--   perfil 303 -> empresa_perfil_id 303
--   perfil 304 -> empresa_perfil_id 304
INSERT INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
  FROM `conf__empresas_perfiles` ep
  JOIN `conf__perfiles_funciones` pf ON pf.perfil_id = ep.perfil_id_base
 WHERE ep.modulo_id = 3 AND pf.pagina_funcion_id BETWEEN 3001 AND 3041
ON DUPLICATE KEY UPDATE `asignado` = 1;

COMMIT;

SET SESSION sql_mode = @m1_old_sql_mode;

SELECT 'M1-01 terminado: páginas 301 a 307 creadas, 24 funciones registradas y permisos asignados a perfiles base y empresas.' AS resultado;
