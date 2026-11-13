-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================
-- Sincronizacion LOCAL (gestion_multipyme) -> PRODUCCION multipyme (ps_gestion_developsam_produccion)
-- Script 01: ESTRUCTURA
-- 2026-10-31
-- =====================================================================
-- Surge de cargar _local/sql/developsam_produccion.sql (volcado del 2026-10-31, MySQL 8.0.46)
-- en una base local y compararla con gestion_multipyme por information_schema
-- (tablas, columnas, indices, FKs, rutinas) y fila por fila por clave primaria.
--
-- Diferencias de estructura encontradas (todo lo demas es igual):
--   1) 20261030_obligaciones_pago_01_estructura.sql quedo a medias: estan las tablas
--      nuevas (obligaciones_tipos / _importaciones, aprobaciones_*) pero NO el paso 4:
--      sigue gestion__obligaciones_impositivas (vacia) y no existe gestion__obligaciones_pago.
--      El 02 y el 03 de obligaciones SI se corrieron (conf__tablas 286 ya dice
--      gestion__obligaciones_pago, 33 tipos, paginas 135-137 / 635-637), asi que el codigo
--      subido falla en Obligaciones de Pago, OP, IVA, percepciones y retenciones.
--      Aca se repite ese paso 4 sin procedimiento ni DELIMITER (solo PREPARE), para que
--      corra igual desde el cliente mysql, HeidiSQL o phpMyAdmin.
--   2) Faltan conf__empresas.formato_fecha / separador_miles / separador_decimal
--      (= 20261031_formato_empresa_01_columnas.sql).
--
-- NO toca lo que existe solo en produccion: conf__usuarios_password_resets,
-- ecom__usuarios_entidades, gestion__depositos, gestion__ventas_cobranzas, *_copy y el
-- evento ev_refrescar_compatibilidad_anual. Los sp_* de compatibilidad solo difieren en
-- comentarios: no se tocan.
--
-- Reanudable: cada paso consulta information_schema (el DDL hace commit implicito).
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261031_sync_local_a_developsam_produccion_01_estructura.sql
-- Despues: 20261031_sync_local_a_developsam_produccion_02_datos.sql
-- Antes: respaldo de la base (mysqldump --set-gtid-purged=OFF --routines --triggers --events).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) gestion__obligaciones_impositivas -> gestion__obligaciones_pago
-- ---------------------------------------------------------------------

-- 1.a Renombrar (solo si existe la vieja y no la nueva)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__obligaciones_pago') = 0
           AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__obligaciones_impositivas') = 1,
    'RENAME TABLE gestion__obligaciones_impositivas TO gestion__obligaciones_pago',
    'SELECT ''1.a gestion__obligaciones_pago ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 1.b La rectificacion de DDJJ no se usa: sin la FK a si misma se puede renombrar la PK
SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'gestion__obligaciones_pago' AND CONSTRAINT_NAME = 'fk_obli_obligacion_rectificada_id') = 1,
    'ALTER TABLE gestion__obligaciones_pago DROP FOREIGN KEY fk_obli_obligacion_rectificada_id',
    'SELECT ''1.b sin fk_obli_obligacion_rectificada_id''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 1.c PK obligacion_impositiva_id -> obligacion_id; impuesto y periodo pasan a opcionales
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'gestion__obligaciones_pago' AND COLUMN_NAME = 'obligacion_impositiva_id') = 1,
    'ALTER TABLE gestion__obligaciones_pago
        CHANGE COLUMN obligacion_impositiva_id obligacion_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN entidad_id INT UNSIGNED NOT NULL COMMENT ''Beneficiario: empleado, organismo, proveedor (el rol lo fija el tipo)'',
        MODIFY COLUMN impuesto_tipo_id SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT ''Solo obligaciones impositivas'',
        MODIFY COLUMN periodo_fiscal VARCHAR(7) NULL DEFAULT NULL COMMENT ''YYYY-MM o YYYY (opcional)'',
        MODIFY COLUMN descripcion VARCHAR(200) NOT NULL COMMENT ''Sueldo octubre 2026, IVA marzo 2026...'',
        MODIFY COLUMN cont_cuenta_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Pasivo a pagar: lo cancela la OP (se fija al confirmar)'',
        MODIFY COLUMN importe_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT ''Importe a pagar''',
    'SELECT ''1.c PK ya renombrada''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 1.d Columnas nuevas
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'gestion__obligaciones_pago' AND COLUMN_NAME = 'obligacion_tipo_id') = 0,
    'ALTER TABLE gestion__obligaciones_pago
        ADD COLUMN obligacion_tipo_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''gestion__obligaciones_tipos'' AFTER empresa_id,
        ADD COLUMN origen VARCHAR(30) NOT NULL DEFAULT ''MANUAL'' COMMENT ''MANUAL / IMPORTACION / IVA_POSICION / PERCEPCIONES_IIBB / RETENCIONES_LIQ / SUELDOS'' AFTER obligacion_tipo_id,
        ADD COLUMN origen_registro_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Id en la tabla del generador'' AFTER origen,
        ADD COLUMN importacion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''gestion__obligaciones_importaciones'' AFTER origen_registro_id,
        ADD COLUMN referencia VARCHAR(100) NULL DEFAULT NULL COMMENT ''N° de recibo de sueldo, contrato, boleta...'' AFTER nro_vep,
        ADD COLUMN cont_cuenta_debe_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Cuenta del Debe del devengamiento (se fija al confirmar)'' AFTER cont_cuenta_id,
        ADD COLUMN aprobaciones_requeridas TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Las que fijo la regla al pedir la aprobacion'' AFTER cont_cuenta_debe_id',
    'SELECT ''1.d columnas ya agregadas''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 1.e Indices
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'gestion__obligaciones_pago' AND INDEX_NAME = 'ix_obli_obligacion_tipo') = 0,
    'ALTER TABLE gestion__obligaciones_pago
        ADD INDEX ix_obli_obligacion_tipo (obligacion_tipo_id),
        ADD INDEX ix_obli_importacion (importacion_id),
        ADD INDEX ix_obli_origen (empresa_id, origen, origen_registro_id),
        ADD INDEX ix_obli_cont_cuenta_debe (cont_cuenta_debe_id)',
    'SELECT ''1.e indices ya agregados''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 1.f FKs
SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'gestion__obligaciones_pago' AND CONSTRAINT_NAME = 'fk_obli_obligacion_tipo_id') = 0,
    'ALTER TABLE gestion__obligaciones_pago
        ADD CONSTRAINT fk_obli_obligacion_tipo_id FOREIGN KEY (obligacion_tipo_id) REFERENCES gestion__obligaciones_tipos (obligacion_tipo_id),
        ADD CONSTRAINT fk_obli_importacion_id FOREIGN KEY (importacion_id) REFERENCES gestion__obligaciones_importaciones (importacion_id),
        ADD CONSTRAINT fk_obli_cont_cuenta_debe_id FOREIGN KEY (cont_cuenta_debe_id) REFERENCES gestion__cont_cuentas (cont_cuenta_id)',
    'SELECT ''1.f FKs ya agregadas''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2) Formato de fechas y numeros por empresa (= 20261031_formato_empresa_01_columnas.sql)
-- ---------------------------------------------------------------------
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'formato_fecha') = 0,
    'ALTER TABLE conf__empresas ADD COLUMN formato_fecha VARCHAR(10) NOT NULL DEFAULT ''d/m/Y'' COMMENT ''Formato de fecha en pantallas: d/m/Y, m/d/Y, Y-m-d, d-m-Y, d.m.Y'' AFTER tipo_operacion',
    'SELECT ''2 conf__empresas.formato_fecha ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'separador_miles') = 0,
    'ALTER TABLE conf__empresas ADD COLUMN separador_miles VARCHAR(10) NOT NULL DEFAULT ''PUNTO'' COMMENT ''Separador de miles: PUNTO, COMA, ESPACIO, NINGUNO'' AFTER formato_fecha',
    'SELECT ''2 conf__empresas.separador_miles ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'separador_decimal') = 0,
    'ALTER TABLE conf__empresas ADD COLUMN separador_decimal VARCHAR(10) NOT NULL DEFAULT ''COMA'' COMMENT ''Separador decimal: COMA, PUNTO'' AFTER separador_miles',
    'SELECT ''2 conf__empresas.separador_decimal ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- Control (solo lectura): tiene que listar gestion__obligaciones_pago y no _impositivas
-- ---------------------------------------------------------------------
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND (TABLE_NAME LIKE 'gestion\_\_obligaciones\_%' OR TABLE_NAME LIKE 'gestion\_\_aprobaciones\_%')
ORDER BY TABLE_NAME;
SELECT empresa_id, empresa, formato_fecha, separador_miles, separador_decimal FROM conf__empresas ORDER BY empresa_id;
