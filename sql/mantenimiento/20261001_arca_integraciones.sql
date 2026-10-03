-- Framework de Integraciones + ARCA (WSAA / WSFEv1) — base para las pruebas en Homologación.
-- Especificación: Especificacion_Integraciones_ARCA_ERP.docx (raíz del repo).
--
-- Archivos:
--   arca/arca_lib.php                                    (WSAA + WSFEv1, sin URLs ni certificados hardcodeados)
--   modules/gestion/arca_integraciones.php / _ajax.php / _model.php / .js   (ABM + pruebas + historial)
--
-- Tablas de la especificación (sección 2), con dos agregados:
--   * conf__integraciones.codigo: identificador técnico estable ('ARCA') para que
--     el código PHP no dependa del integracion_id.
--   * gestion__integraciones_resultados.integracion_ambiente_id: separa el
--     historial de Homologación del de Producción (sección 10: "aislados por
--     empresa y ambiente").
--
-- Tabla NUEVA, no prevista en la especificación: gestion__integraciones_empresas.
--   La spec pide "vincular la empresa con el ambiente" (sección 11, paso 4) y
--   que el PHP obtenga "URLs, certificados y credenciales desde configuración"
--   (sección 1), pero ninguna de sus tablas guarda CUIT ni rutas de
--   certificado/clave por empresa + ambiente. Esta tabla es esa vinculación y es
--   la que administra el ABM (Diccionario: Activo / Inactivo).
--
-- IDs tomados de la base local del 2026-10-01: MAX(tabla_id) = 108,
-- MAX(tabla_estado_registro_id) = 219, MAX(pagina_id) = 98,
-- MAX(pagina_funcion_id) = 442. Verificar con la consulta 0) antes de correr:
-- si la base avanzó, ajustar los IDs (y $pagina_idx en arca_integraciones.php).

-- 0) Verificación previa (deben dar 108 / 219 / 98 / 442 y 0 tablas existentes)
SELECT (SELECT MAX(tabla_id) FROM conf__tablas) AS max_tabla_id,
       (SELECT MAX(tabla_estado_registro_id) FROM conf__tablas_estados_registros) AS max_tabla_estado_registro_id,
       (SELECT MAX(pagina_id) FROM conf__paginas) AS max_pagina_id,
       (SELECT MAX(pagina_funcion_id) FROM conf__paginas_funciones) AS max_pagina_funcion_id,
       (SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name LIKE '%integraciones%') AS tablas_existentes;

-- ============================================================
-- 1) Configuración global de integraciones (sin empresa_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS `conf__integraciones` (
    `integracion_id`  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo`          VARCHAR(30)  NOT NULL COMMENT 'Identificador técnico estable (ARCA, MERCADOPAGO, ...)',
    `nombre`          VARCHAR(100) NOT NULL,
    `descripcion`     VARCHAR(255) NULL,
    `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`integracion_id`),
    UNIQUE KEY `uk_integraciones_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `conf__integraciones_ambientes` (
    `integracion_ambiente_id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integracion_id`          SMALLINT UNSIGNED NOT NULL,
    `codigo`                  VARCHAR(30)  NOT NULL COMMENT 'homologacion / produccion / sandbox',
    `nombre`                  VARCHAR(100) NOT NULL,
    `url_wsaa`                VARCHAR(255) NULL COMMENT 'URL de autenticación, si aplica',
    `url_servicio`            VARCHAR(255) NULL COMMENT 'URL del servicio, si aplica',
    `activo`                  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`integracion_ambiente_id`),
    UNIQUE KEY `uk_integraciones_ambientes` (`integracion_id`, `codigo`),
    CONSTRAINT `fk_integraciones_ambientes_integracion`
        FOREIGN KEY (`integracion_id`) REFERENCES `conf__integraciones` (`integracion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `conf__integraciones_operaciones` (
    `integracion_operacion_id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integracion_id`           SMALLINT UNSIGNED NOT NULL,
    `codigo`                   VARCHAR(50)  NOT NULL COMMENT 'Identificador técnico de la operación',
    `nombre`                   VARCHAR(100) NOT NULL,
    `descripcion`              VARCHAR(255) NULL,
    `activo`                   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`integracion_operacion_id`),
    UNIQUE KEY `uk_integraciones_operaciones` (`integracion_id`, `codigo`),
    CONSTRAINT `fk_integraciones_operaciones_integracion`
        FOREIGN KEY (`integracion_id`) REFERENCES `conf__integraciones` (`integracion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 2) Vinculación empresa + ambiente (tabla nueva, ver encabezado).
--    Las rutas son relativas a la raíz del proyecto y deben quedar dentro
--    de arca/ (arca_lib.php rechaza cualquier otra ubicación).
-- ============================================================
CREATE TABLE IF NOT EXISTS `gestion__integraciones_empresas` (
    `integracion_empresa_id`   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `empresa_id`               SMALLINT UNSIGNED NOT NULL,
    `integracion_ambiente_id`  SMALLINT UNSIGNED NOT NULL,
    `servicio`                 VARCHAR(30)  NOT NULL DEFAULT 'wsfe',
    `cuit`                     CHAR(11)     NOT NULL,
    `certificado_ruta`         VARCHAR(255) NOT NULL COMMENT 'Relativa a la raíz del proyecto, dentro de arca/',
    `clave_privada_ruta`       VARCHAR(255) NOT NULL COMMENT 'Relativa a la raíz del proyecto, dentro de arca/',
    `observaciones`            VARCHAR(255) NULL,
    `fecha_creacion`           DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
    `usuario_creacion_id`      BIGINT UNSIGNED NULL,
    `tabla_estado_registro_id` SMALLINT(4)  NOT NULL DEFAULT 1,
    PRIMARY KEY (`integracion_empresa_id`),
    UNIQUE KEY `uk_integraciones_empresas` (`empresa_id`, `integracion_ambiente_id`, `servicio`),
    CONSTRAINT `fk_integraciones_empresas_ambiente`
        FOREIGN KEY (`integracion_ambiente_id`) REFERENCES `conf__integraciones_ambientes` (`integracion_ambiente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 3) Ticket de Acceso vigente por empresa + ambiente + servicio (sección 5).
--    Una sola fila por combinación: se pisa al renovar.
-- ============================================================
CREATE TABLE IF NOT EXISTS `gestion__integraciones_autenticaciones` (
    `integracion_autenticacion_id` INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `empresa_id`                   SMALLINT UNSIGNED NOT NULL,
    `integracion_ambiente_id`      SMALLINT UNSIGNED NOT NULL,
    `servicio`                     VARCHAR(30)  NOT NULL,
    `tipo_autenticacion`           VARCHAR(30)  NOT NULL DEFAULT 'token_sign',
    `token`                        TEXT         NOT NULL,
    `sign`                         TEXT         NOT NULL,
    `fecha_hora_generacion`        DATETIME     NOT NULL,
    `fecha_hora_vencimiento`       DATETIME     NOT NULL,
    `activo`                       TINYINT(1)   NOT NULL DEFAULT 1,
    `fecha_hora_actualizacion`     DATETIME     NOT NULL,
    PRIMARY KEY (`integracion_autenticacion_id`),
    UNIQUE KEY `uk_integraciones_autenticaciones` (`empresa_id`, `integracion_ambiente_id`, `servicio`),
    CONSTRAINT `fk_integraciones_autenticaciones_ambiente`
        FOREIGN KEY (`integracion_ambiente_id`) REFERENCES `conf__integraciones_ambientes` (`integracion_ambiente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 4) Histórico de toda llamada externa (sección 10). solicitud/respuesta se
--    guardan con Token, Sign y CMS enmascarados (arca_ocultar_credenciales).
-- ============================================================
CREATE TABLE IF NOT EXISTS `gestion__integraciones_resultados` (
    `integracion_resultado_id` BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `empresa_id`               SMALLINT UNSIGNED NOT NULL,
    `integracion_id`           SMALLINT UNSIGNED NOT NULL,
    `integracion_ambiente_id`  SMALLINT UNSIGNED NULL,
    `integracion_operacion_id` SMALLINT UNSIGNED NULL,
    `resultado_integracion_id` INT UNSIGNED NULL COMMENT 'Resultado/categoría si corresponde',
    `codigo`                   VARCHAR(100) NULL COMMENT 'Código técnico (error ARCA, Resultado A/R, ...)',
    `resultado`                VARCHAR(20)  NOT NULL COMMENT 'OK / ERROR / RECHAZO / TIMEOUT',
    `descripcion`              VARCHAR(500) NULL,
    `referencia_tipo`          VARCHAR(60)  NULL,
    `referencia_id`            BIGINT UNSIGNED NULL,
    `intento`                  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `fecha_hora_solicitud`     DATETIME     NOT NULL,
    `fecha_hora_respuesta`     DATETIME     NULL,
    `solicitud`                MEDIUMTEXT   NULL,
    `respuesta`                MEDIUMTEXT   NULL,
    PRIMARY KEY (`integracion_resultado_id`),
    KEY `ix_integraciones_resultados_empresa_fecha` (`empresa_id`, `fecha_hora_solicitud`),
    KEY `ix_integraciones_resultados_referencia` (`referencia_tipo`, `referencia_id`),
    CONSTRAINT `fk_integraciones_resultados_integracion`
        FOREIGN KEY (`integracion_id`) REFERENCES `conf__integraciones` (`integracion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

START TRANSACTION;

-- ============================================================
-- 5) Datos de ARCA (sección 11, pasos 2 y 3).
--    Producción queda inactiva: la spec pide probar todo en Homologación
--    antes de habilitarla (activo = 0 => no se puede elegir en el ABM).
-- ============================================================
INSERT INTO `conf__integraciones` (`integracion_id`, `codigo`, `nombre`, `descripcion`, `activo`)
VALUES (1, 'ARCA', 'ARCA', 'Agencia de Recaudación y Control Aduanero (ex AFIP) - Factura electrónica', 1);

INSERT INTO `conf__integraciones_ambientes`
    (`integracion_ambiente_id`, `integracion_id`, `codigo`, `nombre`, `url_wsaa`, `url_servicio`, `activo`)
VALUES
    (1, 1, 'homologacion', 'Homologación',
        'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
        'https://wswhomo.afip.gov.ar/wsfev1/service.asmx', 1),
    (2, 1, 'produccion', 'Producción',
        'https://wsaa.afip.gov.ar/ws/services/LoginCms',
        'https://servicios1.afip.gov.ar/wsfev1/service.asmx', 0);

INSERT INTO `conf__integraciones_operaciones`
    (`integracion_id`, `codigo`, `nombre`, `descripcion`, `activo`)
VALUES
    (1, 'wsaa_login_cms',          'WSAA - LoginCms',                 'Solicitud de Ticket de Acceso (Token/Sign)', 1),
    (1, 'wsaa_importar_ta',        'WSAA - Importar TA',              'Carga de un TA vigente desde una respuesta loginCms guardada en arca/', 1),
    (1, 'wsfe_dummy',              'WSFE - FEDummy',                  'Estado de los servidores de ARCA', 1),
    (1, 'wsfe_ptos_venta',         'WSFE - FEParamGetPtosVenta',      'Puntos de venta habilitados para web services', 1),
    (1, 'wsfe_ultimo_autorizado',  'WSFE - FECompUltimoAutorizado',   'Último número autorizado por punto de venta y tipo', 1),
    (1, 'wsfe_comp_consultar',     'WSFE - FECompConsultar',          'Consulta de un comprobante (conciliación)', 1),
    (1, 'wsfe_cae_solicitar',      'WSFE - FECAESolicitar',           'Solicitud de CAE', 1);

-- ============================================================
-- 6) Motor de estados: tabla Diccionario (tabla_tipo_id = 1), módulo gestión (2)
--    estado_registro_id: 1 = Activo, 2 = Inactivo (conf__estados_registros).
-- ============================================================
INSERT INTO `conf__tablas`
    (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
VALUES
    (109, 'gestion__integraciones_empresas', 'Integraciones externas por empresa y ambiente (ARCA, etc.)', 2, 1, 1, 1);

INSERT INTO `conf__tablas_estados_registros`
    (`tabla_estado_registro_id`, `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `orden`)
VALUES
    (220, 109, 1, 'Activo',   1, 1, 1),
    (221, 109, 2, 'Inactivo', 1, 0, 10);

-- ============================================================
-- 7) Página: "Integraciones - ARCA" bajo "Seteo Empresas" (padre_id = 37),
--    a continuación de "Sucursales - Puntos de Venta" (orden 416).
--    icono_id = 10 (bi bi-shield-fill).
-- ============================================================
INSERT INTO `conf__paginas`
    (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`)
VALUES
    (99, 2, 'Integraciones - ARCA', 'arca_integraciones.php', 10, 'Configuración de ARCA por empresa/ambiente y pruebas de WSAA/WSFE', 37, 417, 109, 1, 1, 0, NULL);

-- ============================================================
-- 8) Funciones (motor de botones) — patrón Diccionario + "Pruebas":
--      Agregar     : origen 0 -> 1
--      Editar      : origen 1 -> 1
--      Pruebas     : origen 1 -> 1  (abre el modal de pruebas; no cambia estado)
--      Inhabilitar : origen 1 -> 2  accion_js = 'inactivar'
--      Habilitar   : origen 2 -> 1
--    icono 60 = fas fa-wrench, color 6 = btn-info.
-- ============================================================
INSERT INTO `conf__paginas_funciones`
    (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
VALUES
    (443, 99, 42, 3, 1, 'Agregar',     'agregar',   NULL, 0, 1, 0,   1),
    (444, 99, 40, 1, 1, 'Editar',      'editar',    NULL, 1, 1, 10,  1),
    (445, 99, 60, 6, 1, 'Pruebas',     'pruebas',   'Pruebas de WSAA / WSFE contra ARCA', 1, 1, 15, 1),
    (446, 99, 45, 4, 1, 'Inhabilitar', 'inactivar', NULL, 1, 2, 20,  1),
    (447, 99, 46, 3, 1, 'Habilitar',   'habilitar', NULL, 2, 1, 100, 1);

COMMIT;

-- ============================================================
-- 9) Permisos: los usuarios de USUARIOS_ACCESO_TOTAL ven la página
--    directamente. Para el resto, asignar las funciones 443-447 al perfil
--    que corresponda desde la pantalla de funciones por perfil
--    (conf__empresas_perfiles_funciones.asignado = 1); sin eso la página
--    no aparece en el menú.
-- ============================================================
