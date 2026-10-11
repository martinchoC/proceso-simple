-- =====================================================================
-- Percepciones de IIBB practicadas - script 04: FUENTES DE ALÍCUOTA GENÉRICAS (etapa 2, paso 1)
-- 2026-10-11 — diseño en docs/gestion/09_percepciones_iibb.md §12
--
-- 1) gestion__padrones_iibb: depósito normalizado de alícuotas por CUIT, para cualquier fuente
--    (ARCHIVO / WS / MANUAL), con vigencia f_desde/f_hasta y situación ya traducida
--    (PADRON | INSCRIPTO_OTRA_JURISDICCION | NO_PERCIBIR). Migra lo importado con SIRCIP
--    (dígito del campo 7 -> situacion). periodo, letra_alicuota y situacion_jurisdiccion quedan
--    por compatibilidad (los sigue grabando el driver SIRCIP) hasta que ningún código los lea.
-- 2) gestion__padrones_iibb_importaciones: vigencia f_desde/f_hasta.
-- 3) gestion__empresas_impuestos_config: fuente_alicuota, sin_dato_accion, integracion_empresa_id,
--    ws_vigencia_dias (los usa el paso 2 en adelante; hoy sin efecto).
--
-- Reanudable (DDL no transaccional): cada paso se aplica solo si falta.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_pi04_col`;
DROP PROCEDURE IF EXISTS `_pi04_ddl_si`;
DROP PROCEDURE IF EXISTS `_pi04_estructura`;
DELIMITER $$
CREATE PROCEDURE `_pi04_col`(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_ddl TEXT)
BEGIN
    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna) = 0 THEN
        SET @_pi04_sql = p_ddl;
        PREPARE _pi04_stmt FROM @_pi04_sql;
        EXECUTE _pi04_stmt;
        DEALLOCATE PREPARE _pi04_stmt;
    END IF;
END$$

-- Ejecuta p_ddl si la condición (SELECT que devuelve 0/1) da 1
CREATE PROCEDURE `_pi04_ddl_si`(IN p_condicion TEXT, IN p_ddl TEXT)
BEGIN
    SET @_pi04_sql = CONCAT('SELECT (', p_condicion, ') INTO @_pi04_cond');
    PREPARE _pi04_stmt FROM @_pi04_sql;
    EXECUTE _pi04_stmt;
    DEALLOCATE PREPARE _pi04_stmt;
    IF @_pi04_cond = 1 THEN
        SET @_pi04_sql = p_ddl;
        PREPARE _pi04_stmt FROM @_pi04_sql;
        EXECUTE _pi04_stmt;
        DEALLOCATE PREPARE _pi04_stmt;
    END IF;
END$$

CREATE PROCEDURE `_pi04_estructura`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;

    -- 1) gestion__padrones_iibb
    CALL `_pi04_ddl_si`(
        'SELECT IS_NULLABLE = ''NO'' FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ''gestion__padrones_iibb'' AND COLUMN_NAME = ''padron_importacion_id''',
        'ALTER TABLE gestion__padrones_iibb MODIFY padron_importacion_id INT UNSIGNED NULL COMMENT ''NULL en WS y MANUAL''');
    CALL `_pi04_ddl_si`(
        'SELECT IS_NULLABLE = ''NO'' FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ''gestion__padrones_iibb'' AND COLUMN_NAME = ''periodo''',
        'ALTER TABLE gestion__padrones_iibb MODIFY periodo CHAR(6) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Obsoleto: usar f_desde/f_hasta''');
    CALL `_pi04_col`('gestion__padrones_iibb', 'fuente',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN fuente VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''ARCHIVO'' COMMENT ''ARCHIVO | WS | MANUAL''');
    CALL `_pi04_col`('gestion__padrones_iibb', 'f_desde',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN f_desde DATE NULL');
    CALL `_pi04_col`('gestion__padrones_iibb', 'f_hasta',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN f_hasta DATE NULL');
    CALL `_pi04_col`('gestion__padrones_iibb', 'situacion',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN situacion VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL COMMENT ''PADRON | INSCRIPTO_OTRA_JURISDICCION | NO_PERCIBIR''');
    CALL `_pi04_col`('gestion__padrones_iibb', 'codigo_origen',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN codigo_origen VARCHAR(10) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Código crudo de la fuente (letra, grupo)''');
    CALL `_pi04_col`('gestion__padrones_iibb', 'referencia',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN referencia VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT ''MANUAL: N° de constancia / archivo adjunto''');
    CALL `_pi04_col`('gestion__padrones_iibb', 'integracion_resultado_id',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN integracion_resultado_id BIGINT UNSIGNED NULL COMMENT ''WS: log en gestion__integraciones_resultados''');
    CALL `_pi04_col`('gestion__padrones_iibb', 'usuario_id',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN usuario_id INT NULL');
    CALL `_pi04_col`('gestion__padrones_iibb', 'created_at',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP');
    CALL `_pi04_col`('gestion__padrones_iibb', 'tabla_estado_registro_id',
        'ALTER TABLE gestion__padrones_iibb ADD COLUMN tabla_estado_registro_id SMALLINT NOT NULL DEFAULT 1');

    -- Migración de lo importado con SIRCIP (dígito del campo 7 -> situación)
    UPDATE gestion__padrones_iibb
       SET f_desde = STR_TO_DATE(CONCAT(periodo, '01'), '%Y%m%d'),
           f_hasta = LAST_DAY(STR_TO_DATE(CONCAT(periodo, '01'), '%Y%m%d')),
           situacion = CASE situacion_jurisdiccion WHEN 2 THEN 'INSCRIPTO_OTRA_JURISDICCION' WHEN 3 THEN 'NO_PERCIBIR' ELSE 'PADRON' END,
           codigo_origen = letra_alicuota
     WHERE f_desde IS NULL AND periodo IS NOT NULL;
    -- Filas sin período (no debería haber): sin vigencia no se pueden usar
    DELETE FROM gestion__padrones_iibb WHERE f_desde IS NULL;
    UPDATE gestion__padrones_iibb SET situacion = 'PADRON' WHERE situacion IS NULL;

    CALL `_pi04_ddl_si`(
        'SELECT IS_NULLABLE = ''YES'' FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ''gestion__padrones_iibb'' AND COLUMN_NAME = ''f_desde''',
        'ALTER TABLE gestion__padrones_iibb MODIFY f_desde DATE NOT NULL, MODIFY situacion VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''PADRON | INSCRIPTO_OTRA_JURISDICCION | NO_PERCIBIR''');
    CALL `_pi04_ddl_si`(
        'SELECT COUNT(*) > 0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ''gestion__padrones_iibb'' AND INDEX_NAME = ''uk_padron_cuit''',
        'ALTER TABLE gestion__padrones_iibb DROP INDEX uk_padron_cuit');
    CALL `_pi04_ddl_si`(
        'SELECT COUNT(*) = 0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ''gestion__padrones_iibb'' AND INDEX_NAME = ''uk_padron_cuit_vig''',
        'ALTER TABLE gestion__padrones_iibb ADD UNIQUE KEY uk_padron_cuit_vig (empresa_id, jurisdiccion_id, cuit, fuente, f_desde)');
    CALL `_pi04_ddl_si`(
        'SELECT COUNT(*) = 0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ''gestion__padrones_iibb'' AND INDEX_NAME = ''idx_padron_busqueda''',
        'ALTER TABLE gestion__padrones_iibb ADD KEY idx_padron_busqueda (empresa_id, jurisdiccion_id, cuit, f_desde)');

    -- 2) gestion__padrones_iibb_importaciones
    CALL `_pi04_ddl_si`(
        'SELECT IS_NULLABLE = ''NO'' FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ''gestion__padrones_iibb_importaciones'' AND COLUMN_NAME = ''periodo''',
        'ALTER TABLE gestion__padrones_iibb_importaciones MODIFY periodo CHAR(6) COLLATE utf8mb4_unicode_ci NULL COMMENT ''aaaamm (padrones mensuales)''');
    CALL `_pi04_col`('gestion__padrones_iibb_importaciones', 'f_desde',
        'ALTER TABLE gestion__padrones_iibb_importaciones ADD COLUMN f_desde DATE NULL');
    CALL `_pi04_col`('gestion__padrones_iibb_importaciones', 'f_hasta',
        'ALTER TABLE gestion__padrones_iibb_importaciones ADD COLUMN f_hasta DATE NULL');
    UPDATE gestion__padrones_iibb_importaciones
       SET f_desde = STR_TO_DATE(CONCAT(periodo, '01'), '%Y%m%d'),
           f_hasta = LAST_DAY(STR_TO_DATE(CONCAT(periodo, '01'), '%Y%m%d'))
     WHERE f_desde IS NULL AND periodo REGEXP '^[0-9]{6}$' AND periodo <> '000000';

    -- 3) gestion__empresas_impuestos_config
    CALL `_pi04_col`('gestion__empresas_impuestos_config', 'fuente_alicuota',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN fuente_alicuota VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL COMMENT ''NULL = sin padrón (condición del cliente) | ARCHIVO | WS | MANUAL''');
    CALL `_pi04_col`('gestion__empresas_impuestos_config', 'sin_dato_accion',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN sin_dato_accion VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''CONDICION'' COMMENT ''Si la fuente no responde: CONDICION | BLOQUEAR''');
    CALL `_pi04_col`('gestion__empresas_impuestos_config', 'integracion_empresa_id',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN integracion_empresa_id INT UNSIGNED NULL COMMENT ''WS: credenciales en gestion__integraciones_empresas''');
    CALL `_pi04_col`('gestion__empresas_impuestos_config', 'ws_vigencia_dias',
        'ALTER TABLE gestion__empresas_impuestos_config ADD COLUMN ws_vigencia_dias SMALLINT NULL COMMENT ''WS: vigencia del dato si el servicio no la informa''');
    -- Las configs con padrón de archivo (SIRCIP) quedan con fuente ARCHIVO
    UPDATE gestion__empresas_impuestos_config
       SET fuente_alicuota = 'ARCHIVO'
     WHERE impuesto_tipo_id = 6 AND fuente_alicuota IS NULL AND formato_padron IS NOT NULL AND formato_padron <> '';
END$$
DELIMITER ;

CALL `_pi04_estructura`();
DROP PROCEDURE IF EXISTS `_pi04_estructura`;
DROP PROCEDURE IF EXISTS `_pi04_ddl_si`;
DROP PROCEDURE IF EXISTS `_pi04_col`;

-- Resumen (solo lectura)
SELECT fuente, situacion, COUNT(*) AS filas, MIN(f_desde) AS desde, MAX(f_hasta) AS hasta
FROM gestion__padrones_iibb GROUP BY fuente, situacion;
SELECT empresa_impuesto_config_id, empresa_id, jurisdiccion_id, formato_padron, fuente_alicuota, sin_dato_accion, formato_ddjj
FROM gestion__empresas_impuestos_config WHERE impuesto_tipo_id = 6;
