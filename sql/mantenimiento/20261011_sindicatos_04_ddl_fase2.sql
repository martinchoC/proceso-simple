-- ==============================================================================
-- Script: 20261011_sindicatos_04_ddl_fase2.sql
-- Propósito: DDL Fase 2 (Declaraciones Juradas, Cálculos, Renglones, Afiliaciones)
--            Especificación: docs/sindicatos/01_especificacion_modulo_sindicatos.md (§5.3, §5.6, §6)
-- ==============================================================================

USE gestion_multipyme;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. sindi__ddjj: Cabecera de Declaración Jurada Mensual
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__ddjj` (
  `ddjj_id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `empleador_id`               INT UNSIGNED NOT NULL,
  `periodo`                    INT NOT NULL COMMENT 'AAAAMM ej: 202610',
  `secuencia`                  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=original, 1..9=rectificativas',
  `ddjj_rectificada_id`        INT UNSIGNED NULL DEFAULT NULL COMMENT 'DDJJ anterior revertida',
  `media_jornada_piso`         ENUM('COMPLETO','PROPORCIONAL','SIN_PISO') NOT NULL DEFAULT 'COMPLETO',
  `tipo`                       ENUM('EMPLEADOR','OFICIO') NOT NULL DEFAULT 'EMPLEADOR',
  `origen_ingreso`             ENUM('MANUAL','F931','PLANILLA','LOTE','EMAIL','MIGRACION') NOT NULL DEFAULT 'MANUAL',
  `f_vto`                      DATE NOT NULL,
  `total`                      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `cantidad_empleados`         INT NOT NULL DEFAULT 0,
  `codigo_verificacion`        VARCHAR(32) NOT NULL COMMENT 'Código único para constancia oficial',
  `usuario_presenta_id`        INT NULL DEFAULT NULL,
  `estudio_id`                 INT UNSIGNED NULL DEFAULT NULL COMMENT 'Estudio contable que presentó',
  `f_presentacion`             DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id`   SMALLINT(4) NOT NULL DEFAULT 3 COMMENT '3=Borrador, 28=Presentado, 2=Rectificada, 38=Anulada',
  `usuario_creacion_id`        INT NULL DEFAULT NULL,
  `fecha_creacion`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`   INT NULL DEFAULT NULL,
  `fecha_actualizacion`        DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ddjj_id`),
  UNIQUE KEY `uk_ddjj_secuencia` (`empresa_id`, `empleador_id`, `periodo`, `secuencia`),
  KEY `idx_ddjj_empresa_periodo` (`empresa_id`, `periodo`),
  KEY `idx_ddjj_empleador` (`empleador_id`),
  KEY `idx_ddjj_estado` (`tabla_estado_registro_id`),
  KEY `idx_ddjj_codigo` (`codigo_verificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. sindi__ddjj_empleados: Renglón de nómina de empleados
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__ddjj_empleados` (
  `ddjj_empleado_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `ddjj_id`                    INT UNSIGNED NOT NULL,
  `persona_id`                 INT UNSIGNED NOT NULL,
  `categoria_id`               INT UNSIGNED NOT NULL,
  `rem_1`                      DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Remunerativo',
  `rem_2`                      DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'No remunerativo',
  `rem_3`                      DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Otros conceptos',
  `media_jornada`              TINYINT(1) NOT NULL DEFAULT 0,
  `jubilado`                   TINYINT(1) NOT NULL DEFAULT 0,
  `afiliado`                   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Marca declarada por empleador',
  `obra_social_id`             INT UNSIGNED NULL DEFAULT NULL,
  `novedad_id`                 INT UNSIGNED NULL DEFAULT NULL,
  `baja_motivo_id`             INT UNSIGNED NULL DEFAULT NULL,
  `f_baja`                     DATE NULL DEFAULT NULL,
  `localidad_id`               INT NULL DEFAULT NULL,
  `observaciones`              VARCHAR(255) NULL DEFAULT NULL,
  `tabla_estado_registro_id`   SMALLINT(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ddjj_empleado_id`),
  UNIQUE KEY `uk_ddjj_persona` (`ddjj_id`, `persona_id`),
  KEY `idx_ddjj_emp_persona` (`persona_id`),
  KEY `idx_ddjj_emp_cat` (`categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. sindi__ddjj_calculos: Renglón × concepto liquidado
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__ddjj_calculos` (
  `ddjj_calculo_id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `ddjj_id`                    INT UNSIGNED NOT NULL,
  `ddjj_empleado_id`           INT UNSIGNED NOT NULL,
  `concepto_id`                INT UNSIGNED NOT NULL,
  `base_declarada`             DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `piso_escala`                DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `base_aplicada`              DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `porcentaje`                 DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `valor_fijo`                 DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `importe`                    DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`ddjj_calculo_id`),
  UNIQUE KEY `uk_calc_renglon_concepto` (`ddjj_empleado_id`, `concepto_id`),
  KEY `idx_calc_ddjj` (`ddjj_id`),
  KEY `idx_calc_concepto` (`concepto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. sindi__ddjj_conceptos: Totales consolidados por concepto y comprobante espejo
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__ddjj_conceptos` (
  `ddjj_concepto_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `ddjj_id`                    INT UNSIGNED NOT NULL,
  `concepto_id`                INT UNSIGNED NOT NULL,
  `base_total`                 DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `cantidad_empleados`         INT NOT NULL DEFAULT 0,
  `importe_total`              DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `comprobante_id`             BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK gestion__comprobantes',
  PRIMARY KEY (`ddjj_concepto_id`),
  UNIQUE KEY `uk_ddjj_concepto` (`ddjj_id`, `concepto_id`),
  KEY `idx_ddjj_conc_comprobante` (`comprobante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. sindi__ddjj_importaciones: Lotes de importación F.931 o Excel/CSV
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__ddjj_importaciones` (
  `importacion_id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `empleador_id`               INT UNSIGNED NULL DEFAULT NULL,
  `periodo`                    INT NULL DEFAULT NULL,
  `formato`                    ENUM('F931_SICOSS','EXCEL_CSV','LOTE_MULTI') NOT NULL,
  `archivo_nombre`             VARCHAR(255) NOT NULL,
  `archivo_ruta`               VARCHAR(255) NOT NULL,
  `filas_total`                INT NOT NULL DEFAULT 0,
  `filas_aceptadas`            INT NOT NULL DEFAULT 0,
  `filas_rechazadas`           INT NOT NULL DEFAULT 0,
  `errores_detalle`            LONGTEXT NULL DEFAULT NULL COMMENT 'JSON con log de advertencias y errores',
  `ddjj_id`                    INT UNSIGNED NULL DEFAULT NULL,
  `usuario_id`                 INT NOT NULL,
  `tabla_estado_registro_id`   SMALLINT(4) NOT NULL DEFAULT 1,
  `fecha_creacion`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`importacion_id`),
  KEY `idx_imp_empresa_periodo` (`empresa_id`, `periodo`),
  KEY `idx_imp_empleador` (`empleador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. sindi__afiliaciones_tipos y sindi__afiliaciones (§5.6)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_tipos` (
  `afiliacion_tipo_id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `codigo`                     VARCHAR(30) NOT NULL COMMENT 'ACTIVIDAD, VOLUNTARIA, JUBILADO, FAMILIAR',
  `nombre`                     VARCHAR(100) NOT NULL,
  `grupo`                      ENUM('TITULAR','FAMILIAR') NOT NULL DEFAULT 'TITULAR',
  `alta`                       ENUM('AUTOMATICA','SOLICITUD') NOT NULL DEFAULT 'SOLICITUD',
  `requiere_empleador`         TINYINT(1) NOT NULL DEFAULT 0,
  `paga_cuota_directa`         TINYINT(1) NOT NULL DEFAULT 0,
  `importe_cuota`              DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `requiere_titular`           TINYINT(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id`   SMALLINT(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`afiliacion_tipo_id`),
  UNIQUE KEY `uk_afiltipo_codigo` (`empresa_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sindi__afiliaciones` (
  `afiliacion_id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `persona_id`                 INT UNSIGNED NOT NULL,
  `afiliacion_tipo_id`         INT UNSIGNED NOT NULL,
  `titular_afiliacion_id`      INT UNSIGNED NULL DEFAULT NULL,
  `parentesco_id`              INT UNSIGNED NULL DEFAULT NULL,
  `empleador_id`               INT UNSIGNED NULL DEFAULT NULL,
  `f_alta`                     DATE NOT NULL,
  `f_baja`                     DATE NULL DEFAULT NULL,
  `f_vigencia`                 DATE NULL DEFAULT NULL,
  `baja_motivo_id`             INT UNSIGNED NULL DEFAULT NULL,
  `origen`                     ENUM('DDJJ','SOLICITUD','MIGRACION') NOT NULL DEFAULT 'DDJJ',
  `origen_id`                  INT UNSIGNED NULL DEFAULT NULL COMMENT 'ddjj_id u origen',
  `numero_afiliado`            VARCHAR(30) NULL DEFAULT NULL,
  `tabla_estado_registro_id`   SMALLINT(4) NOT NULL DEFAULT 1 COMMENT '1=Activo, 2=Inactivo/Baja',
  `fecha_creacion`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`afiliacion_id`),
  KEY `idx_afil_persona` (`empresa_id`, `persona_id`),
  KEY `idx_afil_empleador` (`empleador_id`),
  KEY `idx_afil_vigencia` (`f_alta`, `f_baja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. Registro en conf__tablas (521 a 527)
-- ------------------------------------------------------------------------------
INSERT INTO `conf__tablas` (`tabla_id`, `modulo_id`, `tabla_nombre`, `tabla_descripcion`, `tabla_tipo_id`, `tabla_estado_registro_id`) VALUES
(521, 6, 'sindi__ddjj',               'Declaraciones juradas sindicales',        2, 1),
(522, 6, 'sindi__ddjj_empleados',     'Renglones de nómina de DDJJ',             2, 1),
(523, 6, 'sindi__ddjj_calculos',      'Cálculos de aporte por empleado',         2, 1),
(524, 6, 'sindi__ddjj_conceptos',     'Totales por concepto y comprobantes',     2, 1),
(525, 6, 'sindi__ddjj_importaciones', 'Lotes de importación F931 y planillas',   2, 1),
(526, 6, 'sindi__afiliaciones_tipos', 'Tipos de afiliación sindical',           1, 1),
(527, 6, 'sindi__afiliaciones',       'Padrón de afiliados y vigencias',         2, 1)
ON DUPLICATE KEY UPDATE
  `tabla_nombre` = VALUES(`tabla_nombre`),
  `tabla_descripcion` = VALUES(`tabla_descripcion`),
  `tabla_estado_registro_id` = 1;

-- ------------------------------------------------------------------------------
-- 8. Etiquetas de Estados para sindi__ddjj (tabla 521)
-- ------------------------------------------------------------------------------
INSERT INTO `conf__tablas_estados_registros` (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`) VALUES
(521, 3,  'Borrador',    3, 1, 0, 10),
(521, 28, 'Presentada',  12, 0, 0, 20),
(521, 2,  'Rectificada', 1, 0, 1, 30),
(521, 38, 'Anulada',     4, 0, 1, 40)
ON DUPLICATE KEY UPDATE
  `tabla_estado_registro` = VALUES(`tabla_estado_registro`),
  `color_id` = VALUES(`color_id`);

-- ------------------------------------------------------------------------------
-- 9. Páginas 1020, 1021, 1022 en conf__paginas
-- ------------------------------------------------------------------------------
INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`) VALUES
(1020, 6, 'Declaraciones Juradas', 'ddjj.php',             26, 'Presentación y consulta de DDJJ mensuales', 1000, 30, 521, 1, 1, 0, NULL),
(1021, 6, 'Importación F.931 / Planilla', 'ddjj_importar.php', 100, 'Importador de archivos F.931 y hojas de cálculo', 1020, 10, 525, 1, 1, 0, NULL),
(1022, 6, 'DDJJ de Oficio',         'ddjj_oficio.php',       9, 'Determinación de oficio para empleadores en mora', 1020, 20, 521, 1, 1, 0, NULL)
ON DUPLICATE KEY UPDATE
  `pagina` = VALUES(`pagina`),
  `url` = VALUES(`url`),
  `tabla_id` = VALUES(`tabla_id`);

-- ------------------------------------------------------------------------------
-- 10. Funciones de Página 1020 en conf__paginas_funciones
-- ------------------------------------------------------------------------------
INSERT INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `nombre_funcion`, `tabla_estado_registro_id`) VALUES
(9020, 1020, 'Ver DDJJ',               1),
(9021, 1020, 'Crear Borrador DDJJ',    1),
(9022, 1020, 'Editar Borrador DDJJ',   1),
(9023, 1020, 'Presentar DDJJ',         1),
(9024, 1020, 'Rectificar DDJJ',        1),
(9025, 1020, 'Anular Borrador',        1),
(9026, 1020, 'Importar Archivo',       1),
(9027, 1020, 'Descargar Constancia',   1)
ON DUPLICATE KEY UPDATE
  `nombre_funcion` = VALUES(`nombre_funcion`);

-- ------------------------------------------------------------------------------
-- 11. Asignar funciones a perfiles base (conf__perfiles_funciones)
-- ------------------------------------------------------------------------------
-- Admin (601) y Operador (602) tienen todas las funciones
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT p.perfil_id, f.pagina_funcion_id, 1
FROM `conf__perfiles` p
CROSS JOIN `conf__paginas_funciones` f
WHERE p.perfil_id IN (601, 602) AND f.pagina_funcion_id BETWEEN 9020 AND 9027;

-- Empleador (604) y Estudio (605) tienen todas salvo Anular si ya fue presentada
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT p.perfil_id, f.pagina_funcion_id, 1
FROM `conf__perfiles` p
CROSS JOIN `conf__paginas_funciones` f
WHERE p.perfil_id IN (604, 605) AND f.pagina_funcion_id IN (9020, 9021, 9022, 9023, 9024, 9025, 9026, 9027);

-- Inspector (603) solo puede Ver y Descargar Constancia
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT 603, f.pagina_funcion_id, 1
FROM `conf__paginas_funciones` f
WHERE f.pagina_funcion_id IN (9020, 9027);

-- Replicar a conf__empresas_perfiles_funciones para empresas con módulo 6 (ej. empresa 3)
INSERT INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, pf.asignado
FROM `conf__empresas_perfiles` ep
INNER JOIN `conf__perfiles_funciones` pf ON pf.perfil_id = ep.perfil_id_base
WHERE ep.modulo_id = 6 AND pf.pagina_funcion_id BETWEEN 9020 AND 9027
ON DUPLICATE KEY UPDATE `asignado` = VALUES(`asignado`);

SET FOREIGN_KEY_CHECKS = 1;
