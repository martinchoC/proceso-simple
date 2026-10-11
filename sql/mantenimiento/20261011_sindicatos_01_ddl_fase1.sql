-- =====================================================================
-- MÓDULO 6: SINDICATOS — FASE 1: DDL BASE, CATÁLOGOS Y PADRÓN
-- Fecha: 2026-10-11
-- Especificación: docs/sindicatos/01_especificacion_modulo_sindicatos.md (§5.1, §5.2)
--
-- Tablas creadas:
--   §5.1 Configuración y catálogos:
--     1.  sindi__parametros
--     2.  sindi__convenios
--     3.  sindi__conceptos
--     4.  sindi__categorias
--     5.  sindi__categorias_escalas
--     6.  sindi__vencimientos
--     7.  sindi__tasas_interes
--     8.  sindi__categorias_mora
--     9.  sindi__obras_sociales
--     10. sindi__novedades
--     11. sindi__bajas_motivos
--     12. sindi__medios_pago
--     13. sindi__medios_pago_layout
--   §5.2 Padrón:
--     14. sindi__empleadores
--     15. sindi__estudios
--     16. sindi__estudios_empleadores
--     17. sindi__personas
--     18. sindi__relaciones_laborales
--     19. sindi__usuarios_empleadores
--     20. sindi__empleadores_periodos
--
-- Roles de entidades:
--     EMPL (Empleador) y EST (Estudio Contable) en gestion__roles_entidades
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0) Roles nuevos de entidad para empleadores y estudios contables
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `gestion__roles_entidades` (`rol_entidad_id`, `rol_entidad_codigo`, `rol_entidad_nombre`, `rol_entidad_descripcion`) VALUES
(5, 'EMPL', 'Empleador', 'Empleador obligado a presentar DDJJ y aportar al sindicato'),
(6, 'EST',  'Estudio contable', 'Estudio contable que representa y gestiona empleadores');

-- ---------------------------------------------------------------------
-- 1) sindi__parametros: Configuración general y credenciales del gremio
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__parametros` (
  `parametro_id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `f_desde`                     DATE NOT NULL,
  `f_hasta`                     DATE NULL DEFAULT NULL,
  `convenio_id_default`         INT UNSIGNED NULL DEFAULT NULL,
  `portal_slug`                 VARCHAR(50) NOT NULL DEFAULT 'sindicato',
  `dias_gracia`                 INT NOT NULL DEFAULT 0,
  `aprobacion_autoalta`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=alta directa, 1=requiere aprobacion administrativa',
  `email_avisos`                VARCHAR(255) NULL DEFAULT NULL,
  `media_jornada_piso`          ENUM('COMPLETO','PROPORCIONAL','SIN_PISO') NOT NULL DEFAULT 'COMPLETO',
  `media_jornada_factor`        DECIMAL(5,2) NOT NULL DEFAULT 0.50,
  `smtp_host`                   VARCHAR(150) NULL DEFAULT NULL,
  `smtp_puerto`                 INT NULL DEFAULT 587,
  `smtp_seguridad`              VARCHAR(20) NULL DEFAULT 'tls',
  `smtp_usuario`                VARCHAR(150) NULL DEFAULT NULL,
  `smtp_clave`                  TEXT NULL DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `correo_ingreso_host`         VARCHAR(150) NULL DEFAULT NULL,
  `correo_ingreso_puerto`       INT NULL DEFAULT 993,
  `correo_ingreso_protocolo`    VARCHAR(20) NULL DEFAULT 'imap',
  `correo_ingreso_seguridad`    VARCHAR(20) NULL DEFAULT 'ssl',
  `correo_ingreso_usuario`      VARCHAR(150) NULL DEFAULT NULL,
  `correo_ingreso_clave`        TEXT NULL DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `gdrive_service_account_path` VARCHAR(255) NULL DEFAULT NULL,
  `gdrive_folder_id`            VARCHAR(150) NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`parametro_id`),
  UNIQUE KEY `uk_sp_vigencia` (`empresa_id`, `f_desde`),
  KEY `idx_sp_empresa` (`empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parámetros generales de cada sindicato con vigencia temporal';

-- ---------------------------------------------------------------------
-- 2) sindi__convenios: Convenios colectivos de trabajo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__convenios` (
  `convenio_id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `codigo`                      VARCHAR(30) NOT NULL,
  `nombre`                      VARCHAR(150) NOT NULL,
  `descripcion`                 TEXT NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`convenio_id`),
  UNIQUE KEY `uk_conv_codigo` (`empresa_id`, `codigo`),
  KEY `idx_conv_empresa` (`empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Convenios colectivos de trabajo administrados por el sindicato';

-- ---------------------------------------------------------------------
-- 3) sindi__conceptos: Conceptos de aporte sindical
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__conceptos` (
  `concepto_id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `convenio_id`                 INT UNSIGNED NOT NULL,
  `codigo`                      VARCHAR(30) NOT NULL,
  `descripcion`                 VARCHAR(150) NOT NULL,
  `base_calculo`                ENUM('REM_TOTAL','REMUNERATIVO','FIJO') NOT NULL DEFAULT 'REM_TOTAL',
  `porcentaje`                  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `valor_fijo`                  DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `solo_afiliados`              TINYINT(1) NOT NULL DEFAULT 0,
  `aplica_piso_escala`          TINYINT(1) NOT NULL DEFAULT 1,
  `incluye_sac`                 TINYINT(1) NOT NULL DEFAULT 1,
  `comprobante_tipo_id`         INT UNSIGNED NULL DEFAULT NULL,
  `cuenta_concepto_codigo`      VARCHAR(50) NOT NULL DEFAULT 'SINDI_DEUDORES_APORTES',
  `f_desde`                     DATE NOT NULL,
  `f_hasta`                     DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`concepto_id`),
  UNIQUE KEY `uk_conc_codigo` (`empresa_id`, `codigo`, `f_desde`),
  KEY `idx_conc_convenio` (`empresa_id`, `convenio_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Conceptos de liquidación y aporte mensual de DDJJ';

-- ---------------------------------------------------------------------
-- 4) sindi__categorias: Categorías profesionales por convenio
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__categorias` (
  `categoria_id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `convenio_id`                 INT UNSIGNED NOT NULL,
  `codigo`                      VARCHAR(30) NOT NULL,
  `nombre`                      VARCHAR(150) NOT NULL,
  `descripcion`                 TEXT NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`categoria_id`),
  UNIQUE KEY `uk_cat_codigo` (`empresa_id`, `convenio_id`, `codigo`),
  KEY `idx_cat_empresa` (`empresa_id`, `convenio_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías salariales del CCT';

-- ---------------------------------------------------------------------
-- 5) sindi__categorias_escalas: Escalas salariales históricas por período
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__categorias_escalas` (
  `escala_id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `categoria_id`                INT UNSIGNED NOT NULL,
  `periodo`                     INT NOT NULL COMMENT 'AAAAMM',
  `basico`                      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `rem_1`                       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `rem_2`                       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`escala_id`),
  UNIQUE KEY `uk_esc_periodo` (`empresa_id`, `categoria_id`, `periodo`),
  KEY `idx_esc_cat` (`categoria_id`, `periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Escala salarial para cálculo del piso de aportes por categoría y período';

-- ---------------------------------------------------------------------
-- 6) sindi__vencimientos: Vencimientos por terminación de CUIT
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__vencimientos` (
  `vencimiento_id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `periodo`                     INT NOT NULL COMMENT 'AAAAMM',
  `terminacion_cuit`            TINYINT NOT NULL COMMENT '0 al 9',
  `tipo_vencimiento`            VARCHAR(20) NOT NULL DEFAULT 'DDJJ',
  `f_vto`                       DATE NOT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`vencimiento_id`),
  UNIQUE KEY `uk_vto` (`empresa_id`, `periodo`, `terminacion_cuit`, `tipo_vencimiento`),
  KEY `idx_vto_busq` (`empresa_id`, `periodo`, `terminacion_cuit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cronograma de vencimientos por período y dígito de CUIT';

-- ---------------------------------------------------------------------
-- 7) sindi__tasas_interes: Tramos de tasa resarcitoria mensual
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__tasas_interes` (
  `tasa_interes_id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `clase`                       INT NOT NULL DEFAULT 1 COMMENT '1 = Resarcitorio',
  `tasa_mensual`                DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  `f_desde`                     DATE NOT NULL,
  `f_hasta`                     DATE NOT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `legacy_id`                   INT NULL DEFAULT NULL,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tasa_interes_id`),
  UNIQUE KEY `uk_tasa_vigencia` (`empresa_id`, `clase`, `f_desde`),
  KEY `idx_tasa_tramo` (`empresa_id`, `clase`, `f_desde`, `f_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tasas de interés mensual por tramos sin superposición';

-- ---------------------------------------------------------------------
-- 8) sindi__categorias_mora: Categorización de mora de empleadores
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__categorias_mora` (
  `categoria_mora_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `codigo`                      VARCHAR(30) NOT NULL,
  `nombre`                      VARCHAR(100) NOT NULL,
  `dias_desde`                  INT NOT NULL DEFAULT 0,
  `dias_hasta`                  INT NOT NULL DEFAULT 0,
  `color`                       VARCHAR(20) NOT NULL DEFAULT '#6c757d',
  `orden`                       INT NOT NULL DEFAULT 0,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`categoria_mora_id`),
  UNIQUE KEY `uk_cat_mora` (`empresa_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías de mora para semáforo de fiscalización (Puntual a Crónica)';

-- ---------------------------------------------------------------------
-- 9) sindi__obras_sociales: Catálogo nacional RNOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__obras_sociales` (
  `obra_social_id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo_rnos`                 VARCHAR(20) NOT NULL,
  `nombre`                      VARCHAR(200) NOT NULL,
  `sigla`                       VARCHAR(50) NULL DEFAULT NULL,
  `f_alta`                      DATE NULL DEFAULT NULL,
  `f_baja`                      DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`obra_social_id`),
  UNIQUE KEY `uk_rnos` (`codigo_rnos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de obras sociales de la Superintendencia (código RNOS F.931)';

-- ---------------------------------------------------------------------
-- 10) sindi__novedades: Tipos de novedad en la nómina
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__novedades` (
  `novedad_id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `codigo`                      VARCHAR(20) NOT NULL,
  `descripcion`                 VARCHAR(150) NOT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`novedad_id`),
  UNIQUE KEY `uk_nov_codigo` (`empresa_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Novedades de personal (licencias, maternidad, etc.)';

-- ---------------------------------------------------------------------
-- 11) sindi__bajas_motivos: Motivos de baja de empleados
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__bajas_motivos` (
  `baja_motivo_id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `codigo`                      VARCHAR(20) NOT NULL,
  `descripcion`                 VARCHAR(150) NOT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`baja_motivo_id`),
  UNIQUE KEY `uk_bm_codigo` (`empresa_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Motivos de desvinculación laboral o baja sindical';

-- ---------------------------------------------------------------------
-- 12) sindi__medios_pago: Canales de cobranza y configuración bancaria
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__medios_pago` (
  `medio_pago_id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `codigo`                      VARCHAR(30) NOT NULL,
  `nombre`                      VARCHAR(150) NOT NULL,
  `driver`                      ENUM('BNA_BARRAS','RED_LINK','INTERBANKING','BAPRO','MANUAL') NOT NULL DEFAULT 'MANUAL',
  `ambiente`                    ENUM('PRUEBA','PRODUCCION') NOT NULL DEFAULT 'PRUEBA',
  `orden`                       INT NOT NULL DEFAULT 0,
  `tesoreria_cuenta_codigo`     VARCHAR(50) NULL DEFAULT NULL,
  `medio_pago_codigo`           VARCHAR(50) NULL DEFAULT NULL,
  `entidad_bancaria_id`         INT NULL DEFAULT NULL,
  `url_servicio`                VARCHAR(255) NULL DEFAULT NULL,
  `url_autenticacion`           VARCHAR(255) NULL DEFAULT NULL,
  `ente_acuerdo`                VARCHAR(50) NULL DEFAULT NULL,
  `client_id`                   VARCHAR(150) NULL DEFAULT NULL,
  `client_secret`               TEXT NULL DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `usuario`                     VARCHAR(150) NULL DEFAULT NULL,
  `clave`                       TEXT NULL DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `certificado_path`            VARCHAR(255) NULL DEFAULT NULL,
  `clave_privada_path`          VARCHAR(255) NULL DEFAULT NULL,
  `verificar_ssl`               TINYINT(1) NOT NULL DEFAULT 1,
  `publica_deuda`               TINYINT(1) NOT NULL DEFAULT 0,
  `concepto_defecto`            VARCHAR(100) NULL DEFAULT NULL,
  `rendicion_modo`              ENUM('API','SFTP','ARCHIVO_MANUAL') NOT NULL DEFAULT 'ARCHIVO_MANUAL',
  `sftp_host`                   VARCHAR(150) NULL DEFAULT NULL,
  `sftp_puerto`                 INT NULL DEFAULT 22,
  `sftp_usuario`                VARCHAR(150) NULL DEFAULT NULL,
  `sftp_clave`                  TEXT NULL DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `sftp_carpeta_remota`         VARCHAR(255) NULL DEFAULT NULL,
  `sftp_patron_archivo`         VARCHAR(150) NULL DEFAULT NULL,
  `barras_prefijo`              VARCHAR(20) NULL DEFAULT NULL,
  `barras_simbologia`           VARCHAR(20) NOT NULL DEFAULT 'Code128',
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`medio_pago_id`),
  UNIQUE KEY `uk_mp_codigo` (`empresa_id`, `codigo`),
  KEY `idx_mp_empresa` (`empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Medios de cobro del sindicato con credenciales cifradas';

-- ---------------------------------------------------------------------
-- 13) sindi__medios_pago_layout: Mapeo de archivos posicionales
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__medios_pago_layout` (
  `layout_id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `medio_pago_id`               INT UNSIGNED NOT NULL,
  `campo_nombre`                VARCHAR(50) NOT NULL,
  `posicion_inicio`             INT NOT NULL,
  `longitud`                    INT NOT NULL,
  `formato`                     VARCHAR(30) NOT NULL DEFAULT 'STRING',
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`layout_id`),
  KEY `idx_mpl_mp` (`medio_pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Definición posicional de los archivos de rendición bancaria';

-- ---------------------------------------------------------------------
-- 14) sindi__empleadores: Extensión de gestion__entidades para empleadores
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__empleadores` (
  `empleador_id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `entidad_id`                  INT UNSIGNED NOT NULL,
  `rubro`                       VARCHAR(150) NULL DEFAULT NULL,
  `convenio_id`                 INT UNSIGNED NULL DEFAULT NULL,
  `categoria_mora_id`           INT UNSIGNED NULL DEFAULT NULL,
  `f_alta_padron`               DATE NOT NULL,
  `origen`                      ENUM('AUTOALTA','SINDICATO','OFICIO','MIGRACION') NOT NULL DEFAULT 'SINDICATO',
  `legacy_id`                   INT NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`empleador_id`),
  UNIQUE KEY `uk_emp_entidad` (`empresa_id`, `entidad_id`),
  KEY `idx_emp_mora` (`empresa_id`, `categoria_mora_id`),
  KEY `idx_emp_estado` (`empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Extensión del padrón de empleadores vinculada a gestion__entidades';

-- ---------------------------------------------------------------------
-- 15) sindi__estudios: Extensión de gestion__entidades para estudios
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__estudios` (
  `estudio_id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `entidad_id`                  INT UNSIGNED NOT NULL,
  `matricula`                   VARCHAR(50) NULL DEFAULT NULL,
  `contacto`                    VARCHAR(150) NULL DEFAULT NULL,
  `legacy_id`                   INT NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`estudio_id`),
  UNIQUE KEY `uk_est_entidad` (`empresa_id`, `entidad_id`),
  KEY `idx_est_estado` (`empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estudios contables vinculados a gestion__entidades';

-- ---------------------------------------------------------------------
-- 16) sindi__estudios_empleadores: Vínculo estudio-empleador con workflow
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__estudios_empleadores` (
  `estudio_empleador_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `estudio_id`                  INT UNSIGNED NOT NULL,
  `empleador_id`                INT UNSIGNED NOT NULL,
  `iniciado_por`                ENUM('ESTUDIO','EMPLEADOR','SINDICATO') NOT NULL DEFAULT 'ESTUDIO',
  `f_solicitud`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_resolucion`                DATETIME NULL DEFAULT NULL,
  `f_baja`                      DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 4 COMMENT '4=Pendiente aprobación, 1=Activo/Aprobado, 25=Rechazado, 2=Inactivo/Baja',
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`estudio_empleador_id`),
  KEY `idx_ee_rel` (`empresa_id`, `estudio_id`, `empleador_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Solicitudes y vinculaciones formales entre estudios y empleadores';

-- ---------------------------------------------------------------------
-- 17) sindi__personas: Trabajadores y personas físicas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__personas` (
  `persona_id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `cuil`                        VARCHAR(11) NOT NULL,
  `apellido`                    VARCHAR(100) NOT NULL,
  `nombre`                      VARCHAR(100) NOT NULL,
  `documento_tipo_id`           SMALLINT NULL DEFAULT NULL,
  `documento_nro`               VARCHAR(20) NULL DEFAULT NULL,
  `sexo`                        CHAR(1) NULL DEFAULT 'M',
  `f_nacimiento`                DATE NULL DEFAULT NULL,
  `email`                       VARCHAR(150) NULL DEFAULT NULL,
  `telefono`                    VARCHAR(50) NULL DEFAULT NULL,
  `direccion`                   VARCHAR(200) NULL DEFAULT NULL,
  `localidad_id`                SMALLINT NULL DEFAULT NULL,
  `entidad_id`                  INT UNSIGNED NULL DEFAULT NULL COMMENT 'Opcional, si paga cuota directa',
  `legacy_id`                   INT NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`persona_id`),
  UNIQUE KEY `uk_persona_cuil` (`empresa_id`, `cuil`),
  KEY `idx_per_ape_nom` (`empresa_id`, `apellido`, `nombre`),
  KEY `idx_per_estado` (`empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Padrón de trabajadores y afiliados';

-- ---------------------------------------------------------------------
-- 18) sindi__relaciones_laborales: Empleador × Trabajador
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__relaciones_laborales` (
  `relacion_laboral_id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `empleador_id`                INT UNSIGNED NOT NULL,
  `persona_id`                  INT UNSIGNED NOT NULL,
  `f_alta`                      DATE NOT NULL,
  `f_baja`                      DATE NULL DEFAULT NULL,
  `ddjj_alta_id`                INT NULL DEFAULT NULL,
  `ddjj_baja_id`                INT NULL DEFAULT NULL,
  `baja_motivo_id`              INT UNSIGNED NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`relacion_laboral_id`),
  KEY `idx_rl_emp_per` (`empresa_id`, `empleador_id`, `persona_id`, `tabla_estado_registro_id`),
  KEY `idx_rl_per` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Relaciones laborales declaradas en DDJJ o dadas de alta por empleador';

-- ---------------------------------------------------------------------
-- 19) sindi__usuarios_empleadores: Aislamiento y acceso por empleador
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__usuarios_empleadores` (
  `usuario_empleador_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`                  INT(11) NOT NULL,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `empleador_id`                INT UNSIGNED NOT NULL,
  `rol`                         ENUM('TITULAR','ESTUDIO','DELEGADO') NOT NULL DEFAULT 'TITULAR',
  `estudio_empleador_id`        INT UNSIGNED NULL DEFAULT NULL,
  `f_desde`                     DATE NOT NULL,
  `f_hasta`                     DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_empleador_id`),
  UNIQUE KEY `uk_ue` (`usuario_id`, `empresa_id`, `empleador_id`, `rol`),
  KEY `idx_ue_busq` (`usuario_id`, `empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Asignación de empleadores a usuarios para control de acceso estricto';

-- ---------------------------------------------------------------------
-- 20) sindi__empleadores_periodos: Matriz empleador × período de DDJJ
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__empleadores_periodos` (
  `empleador_periodo_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `empleador_id`                INT UNSIGNED NOT NULL,
  `periodo`                     INT NOT NULL COMMENT 'AAAAMM',
  `f_vto`                       DATE NOT NULL,
  `ddjj_vigente_id`             INT NULL DEFAULT NULL,
  `situacion`                   ENUM('PENDIENTE','PRESENTADA','FUERA_DE_TERMINO','OFICIO','NO_COMPUTA') NOT NULL DEFAULT 'PENDIENTE',
  `dias_atraso`                 INT NOT NULL DEFAULT 0,
  `tabla_estado_registro_id`    SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`         INT NULL DEFAULT NULL,
  `fecha_creacion`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`    INT NULL DEFAULT NULL,
  `fecha_actualizacion`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`empleador_periodo_id`),
  UNIQUE KEY `uk_ep_periodo` (`empresa_id`, `empleador_id`, `periodo`),
  KEY `idx_ep_situacion` (`empresa_id`, `periodo`, `situacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Seguimiento y estado de cumplimiento mensual de DDJJ por empleador';
