-- =====================================================================
-- Percepciones de IIBB practicadas (agente de percepción) - script 01: ESTRUCTURA
-- 2026-10-10 — diseño en docs/gestion/09_percepciones_iibb.md (v2)
--
-- 1) gestion__empresas_impuestos_config (página 73): datos del agente por
--    empresa × provincia × régimen (una fila por vigencia, impuesto_tipo_id = 6).
-- 2) gestion__empresas_impuestos_config_situaciones: alícuota por situación del
--    cliente (PADRON, INSCRIPTO_JURISDICCION, SIMPLIFICADO_JURISDICCION,
--    INSCRIPTO_OTRA_JURISDICCION, NO_INSCRIPTO, EXENTO, BIEN_DE_USO).
-- 3) gestion__entidades_condiciones_iibb_jurisdicciones: jurisdicciones de alta
--    de un cliente (CM) + régimen SIMPLIFICADO en gestion__regimenes_iibb.
-- 4) gestion__padrones_iibb + _importaciones: padrón por CUIT (SIRCIP).
-- 5) gestion__entidades_iibb_exclusiones: certificados de no percepción.
-- 6) gestion__ventas_facturas.destino_bien_uso.
-- 7) gestion__comprobantes_impuestos: jurisdicción, situación, CRC, liquidación.
-- 8) Corrige gestion__jurisdicciones.provincia_id (estaba mal salvo ARBA/AGIP).
--
-- Reanudable (DDL no transaccional): cada paso se aplica solo si falta.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_pi01_col`;
DROP PROCEDURE IF EXISTS `_pi01_estructura`;
DELIMITER $$
CREATE PROCEDURE `_pi01_col`(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_ddl TEXT)
BEGIN
    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna) = 0 THEN
        SET @_pi01_sql = p_ddl;
        PREPARE _pi01_stmt FROM @_pi01_sql;
        EXECUTE _pi01_stmt;
        DEALLOCATE PREPARE _pi01_stmt;
    END IF;
END$$

CREATE PROCEDURE `_pi01_estructura`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;

    -- 1) Datos del agente en la config de impuestos
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'numero_agente',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN numero_agente VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Nro de agente de recaudación en la jurisdicción''');
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'criterio_territorial',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN criterio_territorial VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL COMMENT ''ENTREGA (sucursal de la entidad) | ESTABLECIMIENTO (sucursal de la empresa)''');
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'importe_minimo_percepcion',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN importe_minimo_percepcion DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT ''Percepción menor a este importe = no se percibe''');
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'arca_tributo_id',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN arca_tributo_id SMALLINT NULL COMMENT ''Id de FEParamGetTiposTributos para <Tributos> de WSFE''');
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'formato_padron',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN formato_padron VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Parser del padrón: SIRCIP | NULL = sin padrón''');
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'formato_ddjj',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN formato_ddjj VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Exportador de DDJJ: SIRCAR_D1 | SIRCIP''');
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'codigo_jurisdiccion_cm',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN codigo_jurisdiccion_cm CHAR(3) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Código Convenio Multilateral (916 = Río Negro)''');
    CALL `_pi01_col`('gestion__empresas_impuestos_config', 'codigo_regimen_ddjj',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN codigo_regimen_ddjj VARCHAR(3) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Tipo de régimen de la DDJJ (SIRCAR campo 10 / SIRCIP campo 4)''');

    -- 3) Régimen Simplificado separado de "Local"
    IF (SELECT COUNT(*) FROM gestion__regimenes_iibb WHERE regimen_iibb_codigo = 'SIMPLIFICADO') = 0 THEN
        INSERT INTO gestion__regimenes_iibb
            (regimen_iibb, regimen_iibb_codigo, regimen_iibb_descripcion, requiere_jurisdiccion_sede, requiere_numero_inscripcion, tabla_estado_registro_id)
        VALUES ('Régimen Simplificado', 'SIMPLIFICADO', 'Inscripto en el régimen simplificado de IIBB de la jurisdicción sede.', 1, 1, 1);
    END IF;
    UPDATE gestion__regimenes_iibb SET regimen_iibb = 'Local (Directo)'
    WHERE regimen_iibb_codigo = 'LOCAL' AND regimen_iibb = 'Local / Simplificado';

    -- 6) Destino bien de uso declarado por el cliente (sin percepción)
    CALL `_pi01_col`('gestion__ventas_facturas', 'destino_bien_uso',
        'ALTER TABLE gestion__ventas_facturas ADD COLUMN destino_bien_uso TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = el cliente declara destino bien de uso / insumo: sin percepción IIBB'' AFTER observaciones');

    -- 7) Percepción por comprobante
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'impuesto_tipo_id',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN impuesto_tipo_id SMALLINT UNSIGNED NULL AFTER empresa_impuesto_config_id');
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'jurisdiccion_id',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN jurisdiccion_id INT NULL AFTER impuesto_tipo_id');
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'situacion',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN situacion VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Situación del cliente al calcular (ver _config_situaciones)'' AFTER detalle_origen');
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'tipo_registro_ddjj',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN tipo_registro_ddjj TINYINT NULL AFTER situacion');
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'padron_iibb_id',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN padron_iibb_id INT UNSIGNED NULL AFTER tipo_registro_ddjj');
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'padron_crc',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN padron_crc TINYINT UNSIGNED NULL COMMENT ''CRC del padrón SIRCIP aplicado'' AFTER padron_iibb_id');
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'leyenda',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN leyenda VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL AFTER padron_crc');
    CALL `_pi01_col`('gestion__comprobantes_impuestos', 'liquidacion_comprobante_id',
        'ALTER TABLE gestion__comprobantes_impuestos ADD COLUMN liquidacion_comprobante_id INT NULL COMMENT ''I_LIQ en la que se declaró (NULL = pendiente)'' AFTER leyenda');

    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_impuestos'
          AND INDEX_NAME = 'idx_ci_ddjj') = 0 THEN
        ALTER TABLE gestion__comprobantes_impuestos
            ADD INDEX idx_ci_ddjj (impuesto_tipo_id, jurisdiccion_id, liquidacion_comprobante_id);
    END IF;

    -- Filas viejas (percepciones recibidas en compras): completar tipo y jurisdicción desde la config
    UPDATE gestion__comprobantes_impuestos ci
    JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
    SET ci.impuesto_tipo_id = cfg.impuesto_tipo_id, ci.jurisdiccion_id = cfg.jurisdiccion_id
    WHERE ci.impuesto_tipo_id IS NULL;
END$$
DELIMITER ;

CALL `_pi01_estructura`();
DROP PROCEDURE IF EXISTS `_pi01_estructura`;
DROP PROCEDURE IF EXISTS `_pi01_col`;

-- 2) Alícuota por situación del cliente
CREATE TABLE IF NOT EXISTS `gestion__empresas_impuestos_config_situaciones` (
    `config_situacion_id`        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `empresa_impuesto_config_id` INT              NOT NULL,
    `situacion`                  VARCHAR(30)      NOT NULL COMMENT 'PADRON | INSCRIPTO_JURISDICCION | SIMPLIFICADO_JURISDICCION | INSCRIPTO_OTRA_JURISDICCION | NO_INSCRIPTO | EXENTO | BIEN_DE_USO',
    `requiere_territorio`        TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '1 = solo si la operación es en la jurisdicción',
    `alicuota`                   DECIMAL(10,4)    NOT NULL DEFAULT 0 COMMENT 'PADRON: se ignora (manda el padrón)',
    `tipo_registro_ddjj`         TINYINT          NULL COMMENT 'SIRCIP campo 5: 1 Percepción, 2 Informativo, 3 Excluido, 4 No inscripto, 5 Sobretasa',
    `declarar_si_cero`           TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = con alícuota 0 igual se registra y va a la DDJJ',
    `leyenda`                    VARCHAR(120)     NULL COMMENT 'Texto en la factura; NULL = "Percepción IIBB <jurisdicción>"',
    `tabla_estado_registro_id`   SMALLINT         NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
    PRIMARY KEY (`config_situacion_id`),
    UNIQUE KEY `uk_config_situacion` (`empresa_impuesto_config_id`, `situacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Percepciones: alícuota según la situación del cliente frente al impuesto';

-- 3) Jurisdicciones de alta del cliente (CM; para un local, la sede)
CREATE TABLE IF NOT EXISTS `gestion__entidades_condiciones_iibb_jurisdicciones` (
    `entidad_condicion_iibb_jurisdiccion_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entidad_condicion_iibb_id`              INT UNSIGNED NOT NULL,
    `jurisdiccion_sede_id`                   INT UNSIGNED NOT NULL,
    `f_desde`                                DATE         NULL,
    `f_hasta`                                DATE         NULL,
    `tabla_estado_registro_id`               SMALLINT     NOT NULL DEFAULT 1,
    PRIMARY KEY (`entidad_condicion_iibb_jurisdiccion_id`),
    UNIQUE KEY `uk_cond_iibb_jur` (`entidad_condicion_iibb_id`, `jurisdiccion_sede_id`),
    KEY `idx_cond_iibb_jur_sede` (`jurisdiccion_sede_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Jurisdicciones de alta en IIBB de un cliente (Convenio Multilateral)';

-- 4) Padrón por CUIT
CREATE TABLE IF NOT EXISTS `gestion__padrones_iibb_importaciones` (
    `padron_importacion_id`  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `empresa_id`             INT           NOT NULL,
    `jurisdiccion_id`        INT           NOT NULL,
    `formato`                VARCHAR(20)   NOT NULL,
    `periodo`                CHAR(6)       NOT NULL COMMENT 'aaaamm',
    `archivo_nombre`         VARCHAR(255)  NOT NULL,
    `archivo_hash`           CHAR(40)      NOT NULL COMMENT 'sha1',
    `filas_leidas`           INT           NOT NULL DEFAULT 0,
    `filas_cargadas`         INT           NOT NULL DEFAULT 0,
    `filas_rechazadas`       INT           NOT NULL DEFAULT 0,
    `observaciones`          VARCHAR(1000) NULL,
    `usuario_id`             INT           NULL,
    `created_at`             DATETIME      NULL DEFAULT CURRENT_TIMESTAMP,
    `tabla_estado_registro_id` SMALLINT    NOT NULL DEFAULT 1 COMMENT '1 Vigente / 2 Reemplazada',
    PRIMARY KEY (`padron_importacion_id`),
    KEY `idx_pi_imp_periodo` (`empresa_id`, `jurisdiccion_id`, `periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de importaciones de padrones de IIBB';

CREATE TABLE IF NOT EXISTS `gestion__padrones_iibb` (
    `padron_iibb_id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `empresa_id`             INT           NOT NULL,
    `jurisdiccion_id`        INT           NOT NULL,
    `padron_importacion_id`  INT UNSIGNED  NOT NULL,
    `periodo`                CHAR(6)       NOT NULL COMMENT 'aaaamm',
    `cuit`                   CHAR(11)      NOT NULL,
    `razon_social`           VARCHAR(70)   NULL,
    `alicuota_percepcion`    DECIMAL(10,4) NOT NULL,
    `letra_alicuota`         CHAR(1)       NULL,
    `crc`                    TINYINT UNSIGNED NULL,
    `situacion_jurisdiccion` TINYINT       NULL COMMENT 'Dígito del campo 7 de SIRCIP para esta jurisdicción',
    `jurisdiccion_sede_cm`   CHAR(3)       NULL,
    PRIMARY KEY (`padron_iibb_id`),
    UNIQUE KEY `uk_padron_cuit` (`empresa_id`, `jurisdiccion_id`, `periodo`, `cuit`),
    KEY `idx_padron_importacion` (`padron_importacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Padrón de alícuotas de percepción IIBB por CUIT y período';

-- 5) Certificados de no percepción / exclusión
CREATE TABLE IF NOT EXISTS `gestion__entidades_iibb_exclusiones` (
    `entidad_iibb_exclusion_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `empresa_id`                INT           NOT NULL,
    `entidad_id`                INT UNSIGNED  NOT NULL,
    `jurisdiccion_id`           INT           NOT NULL,
    `porcentaje_exclusion`      DECIMAL(5,2)  NOT NULL DEFAULT 100.00,
    `numero_certificado`        VARCHAR(50)   NULL,
    `f_desde`                   DATE          NOT NULL,
    `f_hasta`                   DATE          NULL,
    `observaciones`             VARCHAR(255)  NULL,
    `tabla_estado_registro_id`  SMALLINT      NOT NULL DEFAULT 1,
    `created_at`                DATETIME      NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`entidad_iibb_exclusion_id`),
    KEY `idx_excl_entidad` (`empresa_id`, `entidad_id`, `jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Certificados de exclusión / no percepción de IIBB de clientes';

-- 8) provincia_id de las jurisdicciones provinciales según conf__provincias
UPDATE gestion__jurisdicciones j
JOIN conf__provincias p ON p.provincia = CASE j.jurisdiccion_codigo
        WHEN 'ARBA'       THEN 'BUENOS AIRES'
        WHEN 'AGIP'       THEN 'CIUDAD DE BSAS'
        WHEN 'API_SF'     THEN 'SANTA FE'
        WHEN 'DGR_CBA'    THEN 'CORDOBA'
        WHEN 'DGR_MZA'    THEN 'MENDOZA'
        WHEN 'DGR_NQN'    THEN 'NEUQUEN'
        WHEN 'DGR_RN'     THEN 'RIO NEGRO'
        WHEN 'DGR_CHUBUT' THEN 'CHUBUT'
        WHEN 'DGR_LP'     THEN 'LA PAMPA'
     END
SET j.provincia_id = p.provincia_id
WHERE j.provincia_id IS NULL OR j.provincia_id <> p.provincia_id;

-- Resumen (solo lectura)
SELECT j.jurisdiccion_id, j.jurisdiccion_codigo, j.provincia_id, p.provincia
FROM gestion__jurisdicciones j LEFT JOIN conf__provincias p ON p.provincia_id = j.provincia_id
ORDER BY j.jurisdiccion_id;
SELECT regimen_iibb_id, regimen_iibb_codigo, regimen_iibb FROM gestion__regimenes_iibb;
