-- =====================================================================
-- Matriz legal - M0 (instalación del módulo 3), script 03: ESTADOS Y TABLAS
-- 2026-10-03
-- =====================================================================
-- Qué hace (docs/matriz-legal/08-diseno-m0.md, secciones 3 y 6;
-- docs/matriz-legal/07-clasificacion-de-tablas.md):
--   1) DDL idempotente: agrega la columna `es_final` TINYINT(1) NOT NULL DEFAULT 0
--      en `conf__tablas_estados_registros` (AFTER `es_inicial`) si no existe.
--   2) DML en transacción:
--      A) Controles previos: verifica que los bloques de IDs reservados para el
--         módulo 3 (estados 16-27, tablas 201-269, estados de tabla 2001-2200)
--         estén libres o contengan solo las filas de una corrida previa.
--      B) Alta de los 12 estados genéricos nuevos en `conf__estados_registros`
--         (IDs 16 a 27), con valor_estandar >= 200 para preservar el estado
--         inicial de gestión (Activo = 1).
--      C) Alta de las 69 tablas `ml__` en `conf__tablas` (IDs 201 a 269), con
--         modulo_id = 3 y clasificada según 07 (53 Diccionario, 7 Proceso corto,
--         9 Proceso largo).
--      D) Alta de los 110 estados de tabla en `conf__tablas_estados_registros`
--         (IDs 2001 a 2110) con sus marcas `es_inicial` y `es_final`.
--   3) Verificación final con asserts y limpieza de helpers.
--
-- ---------------------------------------------------------------------
-- CÓMO SE CORRE
-- ---------------------------------------------------------------------
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261003_ml_m0_03_estados_y_tablas.sql
-- =====================================================================

SET NAMES utf8mb4;
SET @m0_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------------------
-- HELPER: Assert
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;
DELIMITER $$
CREATE PROCEDURE `_m0_assert`(IN p_cond BOOLEAN, IN p_msg VARCHAR(128))
BEGIN
  IF NOT (p_cond IS TRUE) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- HELPER: Agregar columna si no existe (idempotente)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_add_column_if_not_exists`;
DELIMITER $$
CREATE PROCEDURE `_m0_add_column_if_not_exists`(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_def TEXT
)
BEGIN
    DECLARE v_exists INT;
    SELECT COUNT(*) INTO v_exists
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = p_table_name
       AND column_name = p_column_name;

    IF v_exists = 0 THEN
        SET @sql_add_col = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_def);
        PREPARE stmt_add_col FROM @sql_add_col;
        EXECUTE stmt_add_col;
        DEALLOCATE PREPARE stmt_add_col;
    END IF;
END$$
DELIMITER ;

-- =====================================================================
-- FASE 1: DDL — Agregar columna `es_final` en `conf__tablas_estados_registros`
-- =====================================================================
CALL `_m0_add_column_if_not_exists`(
    'conf__tablas_estados_registros',
    'es_final',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER `es_inicial`'
);

-- Verificar que la columna fue creada
SET @v_has_es_final = (
    SELECT COUNT(*)
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'conf__tablas_estados_registros'
       AND column_name = 'es_final'
);
CALL `_m0_assert`(@v_has_es_final = 1, 'M0-03: no se pudo crear la columna es_final en conf__tablas_estados_registros');

-- =====================================================================
-- FASE 2: DML — Estados genéricos, tablas y estados de tabla
-- =====================================================================
START TRANSACTION;

-- ---------------------------------------------------------------------
-- 2.1) Control de bloques reservados
-- ---------------------------------------------------------------------
-- Verificar que no haya colisiones con IDs ajenos al módulo 3
SET @colision_estados = (
    SELECT COUNT(*) FROM conf__estados_registros
     WHERE estado_registro_id BETWEEN 16 AND 27
       AND codigo_estandar NOT IN (
           'EN_ESPERA','PENDIENTE','EN_CURSO','EN_REVISION','VENCIDO','CUMPLIDO',
           'CUMPLIDO_FUERA_PLAZO','INCUMPLIDO','EXIMIDO','RECHAZADO','CANCELADO','CON_ERROR'
       )
);
CALL `_m0_assert`(@colision_estados = 0, 'M0-03: conflicto en conf__estados_registros (16-27 con otros códigos)');

SET @colision_tablas = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE tabla_id BETWEEN 201 AND 269
       AND (modulo_id <> 3 OR tabla_nombre NOT LIKE 'ml\_\_%')
);
CALL `_m0_assert`(@colision_tablas = 0, 'M0-03: conflicto en conf__tablas (201-269 ocupado por tablas no ml__)');

SET @colision_estados_tabla = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros
     WHERE tabla_estado_registro_id BETWEEN 2001 AND 2200
       AND tabla_id NOT BETWEEN 201 AND 269
);
CALL `_m0_assert`(@colision_estados_tabla = 0, 'M0-03: conflicto en conf__tablas_estados_registros (2001-2200 con tabla_id fuera de 201-269)');

-- ---------------------------------------------------------------------
-- 2.2) Alta de los 12 estados genéricos en `conf__estados_registros`
-- ---------------------------------------------------------------------
INSERT INTO `conf__estados_registros`
    (`estado_registro_id`, `estado_registro`, `codigo_estandar`, `valor_estandar`, `color_id`, `orden_estandar`)
VALUES
    (16, 'En espera',               'EN_ESPERA',             200, 2, 200),
    (17, 'Pendiente',               'PENDIENTE',             210, 6, 210),
    (18, 'En curso',                 'EN_CURSO',              220, 1, 220),
    (19, 'En revisión',             'EN_REVISION',           230, 5, 230),
    (20, 'Vencido',                 'VENCIDO',               240, 4, 240),
    (21, 'Cumplido',                'CUMPLIDO',              250, 3, 250),
    (22, 'Cumplido fuera de plazo', 'CUMPLIDO_FUERA_PLAZO',  260, 5, 260),
    (23, 'Incumplido',              'INCUMPLIDO',            270, 4, 270),
    (24, 'Eximido',                 'EXIMIDO',               280, 2, 280),
    (25, 'Rechazado',               'RECHAZADO',             290, 4, 290),
    (26, 'Cancelado',               'CANCELADO',             300, 8, 300),
    (27, 'Con error',               'CON_ERROR',             310, 4, 310)
ON DUPLICATE KEY UPDATE
    `estado_registro` = VALUES(`estado_registro`),
    `codigo_estandar` = VALUES(`codigo_estandar`),
    `valor_estandar`  = VALUES(`valor_estandar`),
    `color_id`        = VALUES(`color_id`),
    `orden_estandar`  = VALUES(`orden_estandar`);

-- ---------------------------------------------------------------------
-- 2.3) Alta de las 69 tablas en `conf__tablas` (IDs 201 a 269)
-- ---------------------------------------------------------------------
-- modulo_id = 3 (Matriz Legal)
-- tabla_tipo_id: 1 Diccionario (53), 2 Proceso corto (7), 3 Proceso largo (9)
INSERT INTO `conf__tablas`
    (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
VALUES
    -- M1: Territorio y Grupo
    (201, 'ml__grupos',                          'Grupos corporativos o matrices',                                 3, 1, 1, 1),
    (202, 'ml__regiones_grupo',                  'Regiones geográficas de agrupamiento corporativo',               3, 1, 1, 1),
    (203, 'ml__empresas_regiones',               'Pertenencia de empresas a regiones del grupo',                   3, 1, 1, 1),
    (204, 'ml__empresas_nombres',                'Historial de razones sociales y nombres de fantasía',            3, 1, 1, 1),
    (205, 'ml__empresas_eventos_tipos',          'Catálogo de tipos de eventos societarios',                       3, 1, 1, 1),
    (206, 'ml__empresas_eventos',                'Registro de eventos societarios que impactan en normas',         3, 2, 2, 1),
    (207, 'ml__territorios_tipos',               'Tipos de territorios (País, Provincia, Municipio, etc.)',       3, 1, 1, 1),
    (208, 'ml__territorios',                     'Árbol territorial y husos horarios',                             3, 1, 1, 1),
    (209, 'ml__territorios_pertenencias',        'Relación jerárquica histórica entre territorios',                3, 1, 1, 1),
    (210, 'ml__feriados',                        'Calendario de feriados por territorio para cálculo de plazos',   3, 1, 1, 1),
    (211, 'ml__entidades_tipos',                 'Tipos de entidades operativas (Sucursal, Planta, etc.)',         3, 1, 1, 1),
    (212, 'ml__entidades',                       'Entidades físicas, lógicas o móviles sujetas a control',         3, 1, 1, 1),
    (213, 'ml__empresas_grupo',                  'Empresas vinculadas al grupo corporativo',                       3, 1, 1, 1),
    (214, 'ml__entidades_empresas',              'Vigencia y titularidad de entidades en empresas',                3, 1, 1, 1),
    (215, 'ml__entidades_ubicaciones',           'Ubicación territorial histórica de entidades',                   3, 1, 1, 1),
    (216, 'ml__datos_tipos',                     'Definición de campos de datos complementarios',                  3, 1, 1, 1),
    (217, 'ml__entidades_datos',                 'Valores de datos complementarios por entidad',                   3, 1, 1, 1),

    -- M2: Catálogos, Estados, Perfiles y Configuración
    (218, 'ml__ambitos_tipos',                   'Tipos de ámbito organizacional (Mundial, Regional, etc.)',       3, 1, 1, 1),
    (219, 'ml__usuarios_ambitos',                'Asignación de ámbitos territoriales/organizacionales a usuarios',3, 1, 1, 1),
    (220, 'ml__delegaciones',                    'Delegaciones temporales de autoridad entre usuarios',            3, 1, 1, 1),
    (221, 'ml__opciones',                        'Catálogo de opciones configurables del módulo matriz legal',     3, 1, 1, 1),
    (222, 'ml__opciones_valores',                'Valores de opciones por empresa o grupo',                        3, 1, 1, 1),
    (223, 'ml__configuracion_grupo',             'Parámetros generales de configuración a nivel grupo',            3, 1, 1, 1),
    (224, 'ml__raci_roles',                      'Roles de matriz de responsabilidades RACI',                      3, 1, 1, 1),

    -- M3: Normas y Soporte
    (225, 'ml__organismos',                      'Organismos emisores de normas y autoridades de aplicación',      3, 1, 1, 1),
    (226, 'ml__normas_tipos',                    'Tipos de normas (Leyes, Decretos, Resoluciones, etc.)',          3, 1, 1, 1),
    (227, 'ml__normas_jerarquias',               'Jerarquías normativas para resolución de conflictos',            3, 1, 1, 1),
    (228, 'ml__normas',                          'Registro principal de normas aplicables',                        3, 2, 2, 1),
    (229, 'ml__normas_versiones',                'Versiones y textos históricos de normas',                        3, 2, 2, 1),
    (230, 'ml__normas_relaciones_tipos',         'Tipos de relaciones normativas (Deroga, Modifica, etc.)',        3, 1, 1, 1),
    (231, 'ml__normas_relaciones',               'Vínculos entre normas con vigencias',                            3, 1, 1, 1),
    (232, 'ml__normas_conflictos',               'Conflictos detectados entre normas y dictámenes legales',        3, 3, 3, 1),
    (233, 'ml__procedimientos',                  'Procedimientos e instrucciones operativas de cumplimiento',      3, 2, 2, 1),
    (234, 'ml__procedimientos_versiones',        'Versiones de procedimientos internos',                           3, 2, 2, 1),
    (235, 'ml__procedimientos_instrucciones',    'Pasos e instrucciones detalladas de un procedimiento',           3, 1, 1, 1),
    (236, 'ml__accesos_confidenciales',          'Permisos especiales sobre normas confidenciales',                3, 1, 1, 1),
    (237, 'ml__auditoria_accesos',               'Log de accesos a normas y documentos confidenciales',            3, 1, 1, 1),

    -- M4: Obligaciones y Aplicabilidad
    (238, 'ml__criticidades',                    'Niveles de criticidad e impacto de incumplimiento',              3, 1, 1, 1),
    (239, 'ml__periodicidades',                  'Periodicidades de vencimiento (Mensual, Anual, etc.)',           3, 1, 1, 1),
    (240, 'ml__vencimientos_reglas',             'Reglas de cálculo de vencimientos por periodicidad',             3, 1, 1, 1),
    (241, 'ml__obligaciones',                    'Definición de obligaciones de cumplimiento derivadas de normas', 3, 2, 2, 1),
    (242, 'ml__obligaciones_tipos_entidades',    'Tipos de entidades a las que aplica una obligación',             3, 1, 1, 1),
    (243, 'ml__obligaciones_jurisdicciones',     'Jurisdicciones territoriales donde aplica la obligación',        3, 1, 1, 1),
    (244, 'ml__obligaciones_dependencias',       'Dependencias y correlatividades entre obligaciones',             3, 1, 1, 1),
    (245, 'ml__obligaciones_alertas',            'Parámetros de alertas y recordatorios previos al vencimiento',   3, 1, 1, 1),
    (246, 'ml__tareas_plantillas',               'Plantillas de tareas estándar para cumplir la obligación',       3, 1, 1, 1),
    (247, 'ml__tareas_plantillas_dependencias',  'Orden y dependencias entre plantillas de tareas',                3, 1, 1, 1),

    -- M5: Motor de Herencia y Asignaciones
    (248, 'ml__obligaciones_asignadas',          'Obligaciones asignadas efectivamente a cada entidad',            3, 1, 1, 1),
    (249, 'ml__obligaciones_asignadas_origenes', 'Trazabilidad del origen de herencia de cada asignación',         3, 1, 1, 1),
    (250, 'ml__excepciones',                     'Solicitudes y dictámenes de excepciones a obligaciones',         3, 3, 3, 1),
    (251, 'ml__recalculos_pendientes',           'Cola de recálculos de herencia pendientes de procesamiento',     3, 3, 3, 1),

    -- M6: Ciclos y Tareas
    (252, 'ml__ciclos_vinculos_tipos',           'Tipos de vínculos entre ciclos (Complementario, etc.)',          3, 1, 1, 1),
    (253, 'ml__ciclos',                          'Ciclos de cumplimiento (instancias periódicas auditables)',      3, 3, 3, 1),
    (254, 'ml__ciclos_tareas',                   'Tareas operativas generadas para cada ciclo de cumplimiento',    3, 3, 3, 1),
    (255, 'ml__ciclos_tareas_dependencias',      'Dependencias dinámicas entre tareas instanciadas en un ciclo',   3, 1, 1, 1),
    (256, 'ml__obligaciones_asignadas_raci',     'Matriz RACI configurada a nivel de obligación asignada',         3, 1, 1, 1),
    (257, 'ml__ciclos_raci',                     'Responsables RACI instanciados para el ciclo',                   3, 1, 1, 1),
    (258, 'ml__ciclos_tareas_raci',              'Responsables RACI asignados a cada tarea del ciclo',             3, 1, 1, 1),
    (259, 'ml__reasignaciones',                  'Historial de reasignaciones de responsables sobre tareas',       3, 2, 2, 1),

    -- M7: Ejecución y Seguimiento
    (260, 'ml__evidencias',                      'Documentos y respaldos probatorios de cumplimiento',             3, 1, 1, 1),
    (261, 'ml__alertas',                         'Notificaciones y alertas disparadas por vencimientos',           3, 3, 3, 1),
    (262, 'ml__incidencias',                     'Desvíos e incumplimientos detectados con plan de acción',        3, 3, 3, 1),
    (263, 'ml__indicadores_cierres',             'Historial de cálculo de indicadores de cumplimiento mensual',    3, 1, 1, 1),

    -- M8: Cargas y Cambios Masivos
    (264, 'ml__cargas_tipos',                    'Tipos de plantillas para cargas masivas por Excel/CSV',          3, 1, 1, 1),
    (265, 'ml__cargas',                          'Lotes de carga masiva importados al sistema',                    3, 3, 3, 1),
    (266, 'ml__cargas_filas',                    'Detalle fila a fila de cada lote de importación',                3, 1, 1, 1),
    (267, 'ml__cambios_masivos',                 'Solicitudes de cambios normativos masivos',                      3, 3, 3, 1),
    (268, 'ml__cambios_masivos_detalles',        'Entidades y obligaciones alcanzadas por un cambio masivo',       3, 1, 1, 1),

    -- Transversal
    (269, 'ml__log_cambios',                     'Registro de auditoría de cambios en configuraciones y normas',   3, 1, 1, 1)
ON DUPLICATE KEY UPDATE
    `tabla_nombre`              = VALUES(`tabla_nombre`),
    `tabla_descripcion`         = VALUES(`tabla_descripcion`),
    `modulo_id`                 = VALUES(`modulo_id`),
    `tabla_tipo_id`             = VALUES(`tabla_tipo_id`),
    `tabla_patron_id`           = VALUES(`tabla_patron_id`),
    `tabla_estado_registro_id`  = VALUES(`tabla_estado_registro_id`);

-- ---------------------------------------------------------------------
-- 2.4) Alta de los 110 estados de tabla en `conf__tablas_estados_registros`
-- ---------------------------------------------------------------------
-- Columnas:
--   tabla_estado_registro_id (PK smallint 2001..2110)
--   tabla_id (201..269)
--   estado_registro_id (id genérico de conf__estados_registros)
--   tabla_estado_registro (nombre propio para la pantalla)
--   color_id (1: primario, 2: secundario, 3: éxito, 4: peligro, 5: advertencia, 6: info, 8: oscuro)
--   es_inicial (1 si es el estado inicial, 0 si no)
--   es_final (1 si es un estado terminal, 0 si no)
--   orden (orden de visualización en la interfaz)

INSERT INTO `conf__tablas_estados_registros`
    (`tabla_estado_registro_id`, `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`)
VALUES
    -- =================================================================
    -- A) 20 Diccionarios con estado (Activo / Inactivo) -> 40 filas
    -- =================================================================
    -- 201: ml__grupos
    (2001, 201, 1, 'Activo',   1, 1, 0, 1),
    (2002, 201, 2, 'Inactivo', 1, 0, 0, 10),

    -- 202: ml__regiones_grupo
    (2003, 202, 1, 'Activo',   1, 1, 0, 1),
    (2004, 202, 2, 'Inactivo', 1, 0, 0, 10),

    -- 205: ml__empresas_eventos_tipos
    (2005, 205, 1, 'Activo',   1, 1, 0, 1),
    (2006, 205, 2, 'Inactivo', 1, 0, 0, 10),

    -- 207: ml__territorios_tipos
    (2007, 207, 1, 'Activo',   1, 1, 0, 1),
    (2008, 207, 2, 'Inactivo', 1, 0, 0, 10),

    -- 208: ml__territorios
    (2009, 208, 1, 'Activo',   1, 1, 0, 1),
    (2010, 208, 2, 'Inactivo', 1, 0, 0, 10),

    -- 210: ml__feriados
    (2011, 210, 1, 'Activo',   1, 1, 0, 1),
    (2012, 210, 2, 'Inactivo', 1, 0, 0, 10),

    -- 211: ml__entidades_tipos
    (2013, 211, 1, 'Activo',   1, 1, 0, 1),
    (2014, 211, 2, 'Inactivo', 1, 0, 0, 10),

    -- 212: ml__entidades
    (2015, 212, 1, 'Activo',   1, 1, 0, 1),
    (2016, 212, 2, 'Inactivo', 1, 0, 0, 10),

    -- 213: ml__empresas_grupo
    (2017, 213, 1, 'Activo',   1, 1, 0, 1),
    (2018, 213, 2, 'Inactivo', 1, 0, 0, 10),

    -- 216: ml__datos_tipos
    (2019, 216, 1, 'Activo',   1, 1, 0, 1),
    (2020, 216, 2, 'Inactivo', 1, 0, 0, 10),

    -- 225: ml__organismos
    (2021, 225, 1, 'Activo',   1, 1, 0, 1),
    (2022, 225, 2, 'Inactivo', 1, 0, 0, 10),

    -- 226: ml__normas_tipos
    (2023, 226, 1, 'Activo',   1, 1, 0, 1),
    (2024, 226, 2, 'Inactivo', 1, 0, 0, 10),

    -- 227: ml__normas_jerarquias
    (2025, 227, 1, 'Activo',   1, 1, 0, 1),
    (2026, 227, 2, 'Inactivo', 1, 0, 0, 10),

    -- 230: ml__normas_relaciones_tipos
    (2027, 230, 1, 'Activo',   1, 1, 0, 1),
    (2028, 230, 2, 'Inactivo', 1, 0, 0, 10),

    -- 238: ml__criticidades
    (2029, 238, 1, 'Activo',   1, 1, 0, 1),
    (2030, 238, 2, 'Inactivo', 1, 0, 0, 10),

    -- 239: ml__periodicidades
    (2031, 239, 1, 'Activo',   1, 1, 0, 1),
    (2032, 239, 2, 'Inactivo', 1, 0, 0, 10),

    -- 240: ml__vencimientos_reglas
    (2033, 240, 1, 'Activo',   1, 1, 0, 1),
    (2034, 240, 2, 'Inactivo', 1, 0, 0, 10),

    -- 246: ml__tareas_plantillas
    (2035, 246, 1, 'Activo',   1, 1, 0, 1),
    (2036, 246, 2, 'Inactivo', 1, 0, 0, 10),

    -- 248: ml__obligaciones_asignadas
    (2037, 248, 1, 'Activo',   1, 1, 0, 1),
    (2038, 248, 2, 'Inactivo', 1, 0, 0, 10),

    -- 260: ml__evidencias
    (2039, 260, 1, 'Activo',   1, 1, 0, 1),
    (2040, 260, 2, 'Inactivo', 1, 0, 0, 10),

    -- =================================================================
    -- B) 7 Procesos Cortos (4 estados cada uno) -> 28 filas
    --    3: Borrador (I) · 4: Pendiente de Aprobación · 5: Confirmado · 6: Eliminado (F)
    -- =================================================================
    -- 206: ml__empresas_eventos
    (2041, 206, 3, 'Borrador',                 2, 1, 0, 1),
    (2042, 206, 4, 'Pendiente de Aprobación',  5, 0, 0, 10),
    (2043, 206, 5, 'Confirmado',              3, 0, 0, 20),
    (2044, 206, 6, 'Eliminado',               4, 0, 1, 90),

    -- 228: ml__normas
    (2045, 228, 3, 'Borrador',                 2, 1, 0, 1),
    (2046, 228, 4, 'Pendiente de Aprobación',  5, 0, 0, 10),
    (2047, 228, 5, 'Confirmada',              3, 0, 0, 20),
    (2048, 228, 6, 'Eliminada',                4, 0, 1, 90),

    -- 229: ml__normas_versiones
    (2049, 229, 3, 'Borrador',                 2, 1, 0, 1),
    (2050, 229, 4, 'Pendiente de Aprobación',  5, 0, 0, 10),
    (2051, 229, 5, 'Confirmada',              3, 0, 0, 20),
    (2052, 229, 6, 'Eliminada',                4, 0, 1, 90),

    -- 233: ml__procedimientos
    (2053, 233, 3, 'Borrador',                 2, 1, 0, 1),
    (2054, 233, 4, 'Pendiente de Aprobación',  5, 0, 0, 10),
    (2055, 233, 5, 'Confirmado',              3, 0, 0, 20),
    (2056, 233, 6, 'Eliminado',               4, 0, 1, 90),

    -- 234: ml__procedimientos_versiones
    (2057, 234, 3, 'Borrador',                 2, 1, 0, 1),
    (2058, 234, 4, 'Pendiente de Aprobación',  5, 0, 0, 10),
    (2059, 234, 5, 'Confirmado',              3, 0, 0, 20),
    (2060, 234, 6, 'Eliminado',               4, 0, 1, 90),

    -- 241: ml__obligaciones
    (2061, 241, 3, 'Borrador',                 2, 1, 0, 1),
    (2062, 241, 4, 'Pendiente de Aprobación',  5, 0, 0, 10),
    (2063, 241, 5, 'Confirmada',              3, 0, 0, 20),
    (2064, 241, 6, 'Eliminada',                4, 0, 1, 90),

    -- 259: ml__reasignaciones
    (2065, 259, 3, 'Borrador',                 2, 1, 0, 1),
    (2066, 259, 4, 'Pendiente de Aprobación',  5, 0, 0, 10),
    (2067, 259, 5, 'Confirmada',              3, 0, 0, 20),
    (2068, 259, 6, 'Eliminada',                4, 0, 1, 90),

    -- =================================================================
    -- C) 9 Procesos Largos (42 filas en total)
    -- =================================================================
    -- 253: ml__ciclos (8 estados)
    (2069, 253, 17, 'Pendiente',                6, 1, 0, 10),
    (2070, 253, 18, 'En curso',                 1, 0, 0, 20),
    (2071, 253, 20, 'Vencido',                  4, 0, 0, 30),
    (2072, 253, 21, 'Cumplido',                 3, 0, 1, 40),
    (2073, 253, 22, 'Cumplido fuera de plazo',  5, 0, 1, 50),
    (2074, 253, 23, 'Incumplido',               4, 0, 1, 60),
    (2075, 253, 24, 'Eximido',                  2, 0, 1, 70),
    (2076, 253, 26, 'Cancelado',                8, 0, 1, 80),

    -- 254: ml__ciclos_tareas (7 estados)
    (2077, 254, 16, 'Inactiva',                 2, 1, 0, 5),
    (2078, 254, 17, 'Pendiente',                6, 0, 0, 10),
    (2079, 254, 18, 'En curso',                 1, 0, 0, 20),
    (2080, 254, 19, 'En revisión',              5, 0, 0, 30),
    (2081, 254, 21, 'Cumplida',                 3, 0, 1, 40),
    (2082, 254, 25, 'Rechazada',                4, 0, 0, 50),
    (2083, 254, 26, 'Cancelada',                8, 0, 1, 60),

    -- 232: ml__normas_conflictos (3 estados)
    (2084, 232, 17, 'Abierto',                  6, 1, 0, 10),
    (2085, 232, 19, 'Escalado al área legal',   5, 0, 0, 20),
    (2086, 232,  5, 'Resuelto',                 3, 0, 1, 30),

    -- 250: ml__excepciones (5 estados)
    (2087, 250,  4, 'Solicitada',               5, 1, 0, 10),
    (2088, 250, 19, 'En aprobación legal',      5, 0, 0, 20),
    (2089, 250,  5, 'Aprobada',                 3, 0, 0, 30),
    (2090, 250, 25, 'Rechazada',                4, 0, 1, 40),
    (2091, 250, 20, 'Vencida',                  4, 0, 1, 50),

    -- 251: ml__recalculos_pendientes (3 estados)
    (2092, 251, 17, 'Pendiente',                6, 1, 0, 10),
    (2093, 251, 21, 'Procesado',                3, 0, 1, 20),
    (2094, 251, 27, 'Con error',                4, 0, 0, 30),

    -- 261: ml__alertas (4 estados)
    (2095, 261, 17, 'Programada',               6, 1, 0, 10),
    (2096, 261, 21, 'Enviada',                  3, 0, 1, 20),
    (2097, 261, 27, 'Con error',                4, 0, 0, 30),
    (2098, 261, 26, 'Cancelada',                8, 0, 1, 40),

    -- 262: ml__incidencias (3 estados)
    (2099, 262, 17, 'Abierta',                  6, 1, 0, 10),
    (2100, 262, 18, 'En seguimiento',           1, 0, 0, 20),
    (2101, 262, 21, 'Cerrada',                  3, 0, 1, 30),

    -- 265: ml__cargas (4 estados)
    (2102, 265,  3, 'Cargada',                  2, 1, 0, 10),
    (2103, 265, 27, 'Validada con errores',     4, 0, 0, 20),
    (2104, 265,  4, 'Validada',                 5, 0, 0, 30),
    (2105, 265,  5, 'Confirmada',               3, 0, 1, 40),

    -- 267: ml__cambios_masivos (5 estados)
    (2106, 267,  3, 'Borrador',                 2, 1, 0, 10),
    (2107, 267,  4, 'Pendiente de aprobación',  5, 0, 0, 20),
    (2108, 267,  5, 'Aprobado',                 3, 0, 0, 30),
    (2109, 267, 21, 'Aplicado',                 3, 0, 1, 40),
    (2110, 267, 25, 'Rechazado',                4, 0, 1, 50)
ON DUPLICATE KEY UPDATE
    `tabla_id`              = VALUES(`tabla_id`),
    `estado_registro_id`    = VALUES(`estado_registro_id`),
    `tabla_estado_registro` = VALUES(`tabla_estado_registro`),
    `color_id`              = VALUES(`color_id`),
    `es_inicial`            = VALUES(`es_inicial`),
    `es_final`              = VALUES(`es_final`),
    `orden`                 = VALUES(`orden`);

-- ---------------------------------------------------------------------
-- 2.5) Verificación dentro de la transacción
-- ---------------------------------------------------------------------
SET @cnt_estados_nuevos = (
    SELECT COUNT(*) FROM conf__estados_registros
     WHERE estado_registro_id BETWEEN 16 AND 27
);
CALL `_m0_assert`(@cnt_estados_nuevos = 12, 'M0-03: no se insertaron los 12 estados genéricos');

SET @min_valor_estandar = (
    SELECT valor_estandar FROM conf__estados_registros
     WHERE valor_estandar IS NOT NULL
     ORDER BY valor_estandar ASC LIMIT 1
);
CALL `_m0_assert`(@min_valor_estandar = 1, 'M0-03: se alteró el estado inicial de gestión (el menor valor_estandar debe seguir siendo 1)');

SET @cnt_tablas_ml = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE modulo_id = 3 AND tabla_id BETWEEN 201 AND 269
);
CALL `_m0_assert`(@cnt_tablas_ml = 69, 'M0-03: no se insertaron las 69 tablas en conf__tablas');

SET @cnt_estados_tabla = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros
     WHERE tabla_estado_registro_id BETWEEN 2001 AND 2110
);
CALL `_m0_assert`(@cnt_estados_tabla = 110, 'M0-03: no se insertaron los 110 estados en conf__tablas_estados_registros');

-- Cada una de las 36 tablas con estados debe tener EXACTAMENTE un estado inicial (es_inicial = 1)
SET @tablas_sin_inicial_unico = (
    SELECT COUNT(*) FROM (
        SELECT tabla_id, SUM(es_inicial) AS iniciales
          FROM conf__tablas_estados_registros
         WHERE tabla_id BETWEEN 201 AND 269
         GROUP BY tabla_id
        HAVING iniciales <> 1
    ) sub
);
CALL `_m0_assert`(@tablas_sin_inicial_unico = 0, 'M0-03: hay tablas con estados que no tienen exactamente un es_inicial = 1');

COMMIT;

-- ---------------------------------------------------------------------
-- Limpieza
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;
DROP PROCEDURE IF EXISTS `_m0_add_column_if_not_exists`;

SET SESSION sql_mode = @m0_old_sql_mode;

SELECT 'M0-03 terminado: columna es_final creada, 12 estados genéricos dados de alta, 69 tablas registradas y 110 estados de tabla asignados. Seguir con 04' AS resultado;
