-- =====================================================================
-- Tesorería - script 01: ESTRUCTURA + DATOS GLOBALES (caja, bancos,
-- valores, cobranzas, pagos, impuestos y rendiciones)
-- 2026-10-03 · generado por disenador-modelo-datos · NO EJECUTADO
-- Revisión 6 (2026-10-04). Especificación funcional vigente:
--   docs/gestion/08_proc_tesoreria_v3_10.docx (decisiones D1-D36).
-- =====================================================================
-- ORDEN DE EJECUCIÓN: 01 -> 02 -> 02b -> 03 (prefijo 20261003_tesoreria_;
-- 02b_plan_cuentas.sql crea las cuentas contables de la empresa 2 y no es
-- parte de esta serie generada):
--   01_ddl.sql                 UNA VEZ por base. Estructura (tablas, columnas,
--                              índices, FK) y datos GLOBALES: estados
--                              genéricos, roles EMP/ORG, tipos de impuesto,
--                              conf__tablas + estados, catálogo de bancos.
--   02_siembra_empresa.sql     UNA VEZ POR EMPRESA (repetible): subgrupos,
--                              tipos de comprobante, reglas de cancelación y
--                              marca de agente de retención (inactiva).
--                              Parámetro: @tes_empresa_id.
--   02b_plan_cuentas.sql       Cuentas contables que usa tesorería (empresa 2;
--                              mantenido por el usuario, no se edita acá).
--   03_datos_iniciales.sql     POR EMPRESA, después del 02b: plantilla con los
--                              datos de configuración (medios de pago, cuentas
--                              de tesorería, categorías de gasto, numeradores
--                              de retenciones, puntos de venta internos D29).
--                              Todo comentado: se edita y descomenta a mano.
--
-- REQUISITOS DE ESTE SCRIPT
--   - Base seleccionada con USE antes de correr (no hay USE en el script: el
--     nombre difiere entre local, gestion_multipyme, y producción,
--     ps_gestion_casalucho).
--   - Producción: haber corrido antes 20260927_sync_local_a_produccion.sql
--     (crea gestion__comprobantes_subgrupos.origen_asiento, que usa el 02;
--     el volcado casalucho_produccion.sql es anterior y no la tiene).
--   - Privilegios: CREATE, ALTER, INDEX, REFERENCES, INSERT, UPDATE, SELECT,
--     CREATE ROUTINE y ALTER ROUTINE (crea y borra procedimientos helper
--     _tes_*). DROP solo si se descomenta el bloque 10. No hace falta
--     log_bin_trust_function_creators: no se crean funciones, solo
--     procedimientos.
--   - BACKUP COMPLETO ANTES. El DDL hace commit implícito: no hay rollback.
--   - Sin parámetros: este script no depende de ninguna empresa.
--
-- Historial:
-- Revisión 2 (2026-10-03): incorpora D19-D24 de
-- docs/matriz-legal/08_proc_tesoreria_v3_2.docx (D1-D24 confirmadas):
--   D20 devolución de endoso (operación DEVOLUCION_ENDOSO, comprobante DEV_END);
--   D21 retenciones practicadas en la OP (gestion__pagos_retenciones,
--       gestion__retenciones_numeradores, tipos de impuesto de retención y
--       marca de agente de retención por empresa);
--   D22 sin EN_CARTERA -> RECHAZADO; D19/D23/D24 solo verificadas
--   (comentarios ajustados). §R.3 con el lock de retenciones y numeradores.
-- Revisión 3 (2026-10-03): Word v3.3, D25 y D26:
--   D25 la OP que pagó con cheques de terceros nunca se anula: se quita la
--       anulación conjunta OP + DEV_END; OP -> DEV_END = OP futuras.
--   D26 sin CHECK en ninguna tabla (validaciones en PHP); se quita el helper
--       _tes_add_check.
--   §3c ALTER gestion__empresas_impuestos_config.cont_cuenta_id a INT
--       UNSIGNED (bloque marcado para revisión).
-- Revisión 4 (2026-10-03): Word v3.6, D27-D31:
--   D30 la siembra por empresa sale de este archivo: subgrupos, tipos,
--       reglas de cancelación y filas de agente de retención pasan al 02
--       (parametrizado por @tes_empresa_id, sin ids fijos); la configuración
--       inicial va en el 03.
--   D27 tipo_asiento_id = AUT en los subgrupos de tesorería (en el 02).
--   D28 rechazo de cheque propio: comprobante CHP_RECH (tipo y regla
--       OP -> CHP_RECH en el 02; comentarios de §7.2, §7.3, §7.8 y §R.3
--       ajustados acá). Sin cambio de estructura: la operación sigue siendo
--       RECHAZO y el tipo de comprobante (CHP_RECH / ND_CHR) la distingue.
--   D29 puntos de venta internos por sucursal: bloque comentado en el 03.
--   D31 semilla de bancos mínima: se quita HSBC (150).
--   §3c pasa a un helper genérico (_tes_modify_column) que consulta
--       information_schema; sigue marcado REVISAR.
--   sql_mode de sesión explícito (se restaura al final).
-- Revisión 5 (2026-10-04): Word v3.9, D32-D35:
--   D32 retenciones practicadas no se implementan en Casa Lucho:
--       gestion__pagos_retenciones y gestion__retenciones_numeradores se
--       conservan sin uso (sin cambio de estructura).
--   D33 se agrega el tipo de impuesto «Retención Ganancias» (retención
--       SUFRIDA, aplica_venta = 1): faltaba; IVA e IIBB ya existían (§3b').
--   D34/D35 sin cambios en este script (ver 02/03 y la propuesta de D35).
-- Revisión 6 (2026-10-04): Word v3.10, D35-D36:
--   D35 tabla nueva gestion__empresas_cuentas_conceptos (§6.6): cuenta
--       contable por defecto de la empresa por concepto (VALORES_A_PAGAR,
--       EMPLEADOS), registrada en conf__tablas como diccionario (20 tablas).
--       Los conceptos se siembran en el 02 y la cuenta se asigna en el 03.
--   D36 orden de resolución documentado en el COMMENT de la tabla.
--   Sobre una base que ya corrió la revisión 5 (local, 2026-10-04) se puede
--   volver a correr este script completo: todo es idempotente y lo único
--   nuevo que hace es crear la tabla de §6.6 y darla de alta en
--   conf__tablas + conf__tablas_estados_registros (Activo / Inactivo).
-- =====================================================================
-- Fuentes:
--   - docs/gestion/08_proc_tesoreria_v3_10.docx (D1-D36). Versiones
--     anteriores: docs/gestion/08_proc_tesoreria_v3_9 (D1-D35),
--     docs/matriz-legal/08_proc_tesoreria_v3_6 (D1-D31), _v3_3 (D1-D26) y
--     _v3_2 (D1-D24).
--   - docs/gestion/08_proc_tesoreria_revision.md (hallazgos C1-C9, A1-A9 y
--     decisiones D1-D9).
--   - Especificación 08_proc_tesoreria_1.docx v2.0.
--   - Esquema real: casalucho_local.sql (MariaDB 10.4) y
--     casalucho_produccion.sql (MySQL 8.0.46).
--   - Patrón de comprobante: syncComprobante() en
--     modules/gestion/ventas_facturas_model.php.
--
-- Qué hace:
--   0) Controles previos (solo lectura + asserts).
--   1) Estados genéricos faltantes en conf__estados_registros.
--   2) Roles de entidad EMP (Empleado) y ORG (Organismo fiscal).
--   3) gestion__impuestos_tipos: es_liquidable + frecuencia (+ semillas).
--   3b) Retenciones practicadas (D21): tipos de impuesto de retención
--      (globales). La marca de agente por empresa está en el 02.
--   3b') Retención sufrida de Ganancias (D33), la única que faltaba.
--   3c) ALTER de gestion__empresas_impuestos_config.cont_cuenta_id (REVISAR).
--   4) gestion__comprobantes_imputaciones: migración a bigint/utf8mb4,
--      estado propio, fecha, moneda/tipo de cambio, sin UNIQUE(origen,destino).
--   5) gestion__comprobantes: UNIQUE de numeración.
--   6) Diccionarios: conf__bancos, gestion__tesoreria_cuentas,
--      gestion__medios_pago, gestion__categorias_gasto,
--      gestion__comprobantes_tipos_cancelaciones,
--      gestion__empresas_cuentas_conceptos (D35/D36).
--   7) Tablas de proceso y sus detalles.
--   8) Alta en conf__tablas + conf__tablas_estados_registros.
--   9) Catálogo de bancos (global, semilla mínima D31).
--  10) Reemplazo de gestion__ventas_cobranzas (DROP condicionado, COMENTADO).
--   V) Verificación final y limpieza de helpers.
--   R) Consultas de referencia (comentadas).
-- NO genera conf__paginas ni conf__paginas_funciones (orquestador-dev).
--
-- ---------------------------------------------------------------------
-- PORTABILIDAD E IDEMPOTENCIA
-- ---------------------------------------------------------------------
-- Local es MariaDB 10.4 pero PRODUCCIÓN ES MySQL 8.0, que no tiene
-- ADD COLUMN IF NOT EXISTS / CREATE INDEX IF NOT EXISTS / DROP ... IF EXISTS
-- sobre índices y FKs. Por eso, en vez de la sintaxis IF NOT EXISTS de
-- MariaDB, se usa el mismo patrón que 20260927_sync_local_a_produccion.sql:
-- procedimientos helper (_tes_*) que consultan information_schema antes de
-- cada ALTER. Funciona igual en ambos motores y se puede volver a correr si
-- se corta a mitad de camino (el DDL hace commit implícito: no hay rollback).
--   - Tablas nuevas: CREATE TABLE IF NOT EXISTS (con PK, UNIQUE, índices
--     y FK inline). Sin CHECK (D26): las validaciones van en PHP.
--   - Datos: INSERT ... WHERE NOT EXISTS; los ids de otras filas se
--     resuelven por código/nombre (codigo_estandar, tabla_nombre, codigo).
--     Quedan literales solo ids de catálogos de configuración global
--     (modulo_id 1/2, tabla_tipo_id 1/2/3, tabla_patron_id 1, color_id 1 y
--     el estado genérico ACTIVO = 1 en filas de diccionario), igual que en
--     revisiones anteriores.
--   - Columnas generadas: GENERATED ALWAYS AS (...) STORED (MariaDB acepta
--     STORED como sinónimo de PERSISTENT).
--   - SET NAMES ... COLLATE utf8mb4_unicode_ci: en MySQL 8 la collation de
--     conexión por defecto es utf8mb4_0900_ai_ci y compararla con columnas
--     utf8mb4_unicode_ci da "Illegal mix of collations". Los parámetros de
--     los helpers se declaran CHARACTER SET utf8mb4 y se comparan con
--     COLLATE explícito por el mismo motivo.
--   - sql_mode de sesión explícito, válido en ambos motores (sin
--     NO_AUTO_CREATE_USER, que MySQL 8 ya no acepta); se guarda el previo y
--     se restaura al final. Si el script se corta a mitad, restaurarlo a mano:
--     SET SESSION sql_mode = @tes_old_sql_mode;
--     Ojo: con NO_ZERO_DATE, los ALTER que reconstruyen tablas existentes
--     (gestion__comprobantes, gestion__impuestos_tipos,
--     gestion__empresas_impuestos_config) fallan si tienen fechas
--     '0000-00-00'. Volcados del 2026-10-03: ninguna.
--
-- ---------------------------------------------------------------------
-- CONVENCIONES APLICADAS
-- ---------------------------------------------------------------------
-- - Los 8 campos obligatorios de toda tabla de proceso: empresa_id,
--   sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
--   comprobante_id, entidad_id, f_emision. Más los estándar de
--   gestion__ventas_facturas: f_contabilidad, moneda_id, tipo_cambio,
--   importe_total, observaciones, tabla_estado_registro_id; motivo_anulacion
--   (RT14) y auditoría fecha_creacion / fecha_modificacion / usuario_id /
--   usuario_modificacion_id (mismos tipos que gestion__comprobantes).
-- - comprobante_pv guarda el punto_venta_id (gestion__puntos_venta), igual
--   que gestion__ventas_remitos.comprobante_pv y lo que syncComprobante()
--   graba en gestion__comprobantes.comprobante_pv. Tesorería numera por PV
--   interno de la sucursal (A6, D29): hay que habilitar los tipos en
--   gestion__puntos_venta_comprobantes (bloque comentado en el 03).
-- - comprobante_nro = 0 en Borrador; se asigna al confirmar con el numerador
--   bajo FOR UPDATE (vfObtenerProximoNumero), como en ventas_facturas.
-- - comprobante_id BIGINT UNSIGNED NOT NULL (D1) SIN FK en las operativas:
--   el patrón vigente inserta la operativa con comprobante_id = 0, llama a
--   syncComprobante() (que necesita registro_origen_id) y después actualiza
--   comprobante_id. InnoDB no difiere la validación de FK, así que una FK ahí
--   rompería ese patrón. La relación 1:1 la garantiza el UNIQUE idx_origen
--   (tabla_origen_id, registro_origen_id) de gestion__comprobantes. Las demás
--   referencias a comprobantes (imputaciones, movimientos, cheques) SÍ
--   tienen FK porque el comprobante ya existe cuando se insertan (D1).
-- - tabla_estado_registro_id SMALLINT sin FK (igual que el resto del
--   esquema): procesos NOT NULL sin DEFAULT (la app pone el es_inicial);
--   diccionarios NOT NULL DEFAULT 1, como todos los diccionarios existentes.
-- - Estados: ningún ENUM/flag de estado. Los ENUM que hay son ATRIBUTOS de
--   tipo (tipo de medio, tipo de cuenta, INGRESO/EGRESO, PROPIO/TERCERO),
--   como tipo_calculo u origen_asiento en el esquema actual.
-- - Importes DECIMAL(18,2), tipos de cambio DECIMAL(18,6), siempre
--   positivos: la dirección la da el tipo (C3). El signo del tipo de
--   comprobante solo se usa para ctacte/contabilidad.
-- - Materialización (patrón del sistema, no cálculo en caliente):
--     gestion__comprobantes.importe_pendiente  (D2, caché bajo lock)
--     gestion__tesoreria_cuentas.saldo_actual  (A4, UPDATE saldo = saldo ± x)
--     gestion__rendiciones.importe_*           (totales de la rendición)
--     cont_cuenta_id copiado en obligaciones / ítems de rendición
--   Las consultas SUM() del final son de CONCILIACIÓN, no de lectura normal.
-- - Detalles (*_medios, gestion__cheques_operaciones_items) sin
--   tabla_estado_registro_id ni alta en conf__tablas: su ciclo de vida es el
--   de la cabecera y no tienen botones propios (mismo criterio que
--   gestion__ventas_facturas_detalles). Excepción: gestion__rendiciones_items,
--   que el supervisor aprueba/rechaza de a uno (botones del motor).
-- - FKs sin acciones referenciales (RESTRICT): la app borra los hijos
--   explícitamente al editar un Borrador. Sin FK a conf__empresas ni a
--   conf__usuarios (como el resto de gestion__).
-- - Nombres: fk_<abrev>_<columna>, uk_<abrev>_..., ix_<abrev>_...
--   Abreviaturas: bco conf__bancos, tcta tesoreria_cuentas, mpag medios_pago,
--   cgas categorias_gasto, ctc comprobantes_tipos_cancelaciones,
--   imp comprobantes_imputaciones, cob/cobm cobranzas(+_medios),
--   pag/pagm pagos(+_medios), chq cheques, trf transferencias,
--   mcj movimientos_caja, chop/chopi cheques_operaciones(+_items),
--   obli obligaciones_impositivas, pimp/pimpm pagos_impuestos(+_medios),
--   rant rendiciones_anticipos, rend/rendi rendiciones(+_items),
--   reem/reemm rendiciones_reembolsos(+_medios), tmov tesoreria_movimientos,
--   pret pagos_retenciones, rnum retenciones_numeradores,
--   ecc empresas_cuentas_conceptos.
--   Controlado contra los volcados local/producción: sin choques.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @tes_old_sql_mode = @@SESSION.sql_mode;
-- Mismo modo que los scripts 20261003_ml_m0_*: válido en MariaDB 10.4 y
-- MySQL 8.0 (sin NO_AUTO_CREATE_USER) y sin ONLY_FULL_GROUP_BY.
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =====================================================================
-- Helpers temporales (se eliminan al final)
-- =====================================================================
DROP PROCEDURE IF EXISTS `_tes_assert`;
DROP PROCEDURE IF EXISTS `_tes_add_column`;
DROP PROCEDURE IF EXISTS `_tes_modify_column`;
DROP PROCEDURE IF EXISTS `_tes_add_index`;
DROP PROCEDURE IF EXISTS `_tes_drop_index`;
DROP PROCEDURE IF EXISTS `_tes_add_fk`;
DROP PROCEDURE IF EXISTS `_tes_add_check`;
DROP PROCEDURE IF EXISTS `_tes_migrar_imputaciones`;
DROP PROCEDURE IF EXISTS `_tes_add_tabla`;
DROP PROCEDURE IF EXISTS `_tes_add_estado`;
DROP PROCEDURE IF EXISTS `_tes_drop_si_vacia`;
DROP PROCEDURE IF EXISTS `_tes_ampliar_cont_cuenta_config`;

DELIMITER $$

CREATE PROCEDURE `_tes_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$

CREATE PROCEDURE `_tes_add_column`(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna
    ) THEN
        SET @tes_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD COLUMN `', p_columna, '` ', p_definicion);
        PREPARE tes_stmt FROM @tes_sql;
        EXECUTE tes_stmt;
        DEALLOCATE PREPARE tes_stmt;
    END IF;
END$$

-- p_definicion: 'UNIQUE KEY `nombre` (...)' o 'KEY `nombre` (...)'
CREATE PROCEDURE `_tes_add_index`(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND INDEX_NAME = p_indice
    ) THEN
        SET @tes_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD ', p_definicion);
        PREPARE tes_stmt FROM @tes_sql;
        EXECUTE tes_stmt;
        DEALLOCATE PREPARE tes_stmt;
    END IF;
END$$

CREATE PROCEDURE `_tes_drop_index`(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND INDEX_NAME = p_indice
    ) THEN
        SET @tes_sql = CONCAT('ALTER TABLE `', p_tabla, '` DROP INDEX `', p_indice, '`');
        PREPARE tes_stmt FROM @tes_sql;
        EXECUTE tes_stmt;
        DEALLOCATE PREPARE tes_stmt;
    END IF;
END$$

CREATE PROCEDURE `_tes_add_fk`(IN p_tabla VARCHAR(64), IN p_fk VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla
          AND CONSTRAINT_NAME = p_fk AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        SET @tes_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD CONSTRAINT `', p_fk, '` ', p_definicion);
        PREPARE tes_stmt FROM @tes_sql;
        EXECUTE tes_stmt;
        DEALLOCATE PREPARE tes_stmt;
    END IF;
END$$

-- Migra gestion__comprobantes_imputaciones desde la forma vieja (int, utf8,
-- created_at, UNIQUE uq). Solo actúa si detecta la forma vieja
-- (comp_origen_id INT) y exige que la tabla esté vacía (lo está en local y en
-- producción): con filas, f_imputacion/moneda_origen_id NOT NULL no tendrían
-- valor válido y hay que migrar a mano.
CREATE PROCEDURE `_tes_migrar_imputaciones`()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_imputaciones'
          AND COLUMN_NAME = 'comp_origen_id' AND DATA_TYPE = 'int'
    ) THEN
        IF (SELECT COUNT(*) FROM gestion__comprobantes_imputaciones) > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'gestion__comprobantes_imputaciones tiene filas: migrar a mano antes de correr este script';
        END IF;

        ALTER TABLE `gestion__comprobantes_imputaciones`
            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

        ALTER TABLE `gestion__comprobantes_imputaciones`
            MODIFY `imputacion_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            MODIFY `empresa_id` INT NOT NULL,
            MODIFY `comp_origen_id` BIGINT UNSIGNED NOT NULL
                COMMENT 'Comprobante que cancela (RCX, OP, NC, I_PAG, RG_ANT, RG_REEM)',
            MODIFY `comp_destino_id` BIGINT UNSIGNED NOT NULL
                COMMENT 'Comprobante cancelado (FA, ND, ND_CHR, I_LIQ, RG_REND, RG_ANT)',
            MODIFY `importe_imputado` DECIMAL(18,2) NOT NULL
                COMMENT 'Siempre > 0, en moneda del comprobante DESTINO';

        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_imputaciones'
              AND COLUMN_NAME = 'created_at'
        ) THEN
            ALTER TABLE `gestion__comprobantes_imputaciones`
                CHANGE `created_at` `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
        END IF;
    END IF;
END$$

CREATE PROCEDURE `_tes_add_tabla`(
    IN p_nombre VARCHAR(50) CHARACTER SET utf8mb4,
    IN p_descripcion VARCHAR(255) CHARACTER SET utf8mb4,
    IN p_modulo_id INT,
    IN p_tabla_tipo_id INT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM conf__tablas WHERE tabla_nombre = p_nombre COLLATE utf8mb4_unicode_ci) THEN
        INSERT INTO conf__tablas (tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_patron_id, tabla_estado_registro_id)
        VALUES (p_nombre, p_descripcion, p_modulo_id, p_tabla_tipo_id, 1, 1);
    END IF;
END$$

-- Vincula un estado genérico (por codigo_estandar) a una tabla (por nombre).
-- conf__tablas_estados_registros no tiene UNIQUE (tabla_id, estado_registro_id):
-- se controla con NOT EXISTS.
CREATE PROCEDURE `_tes_add_estado`(
    IN p_tabla VARCHAR(50) CHARACTER SET utf8mb4,
    IN p_codigo_estandar VARCHAR(50) CHARACTER SET utf8mb4,
    IN p_etiqueta VARCHAR(50) CHARACTER SET utf8mb4,
    IN p_es_inicial TINYINT,
    IN p_orden SMALLINT)
BEGIN
    DECLARE v_tabla_id INT;
    DECLARE v_estado_id INT;
    DECLARE v_msg VARCHAR(255);

    SET v_tabla_id = (SELECT tabla_id FROM conf__tablas
                      WHERE tabla_nombre = p_tabla COLLATE utf8mb4_unicode_ci
                      ORDER BY tabla_id LIMIT 1);
    SET v_estado_id = (SELECT estado_registro_id FROM conf__estados_registros
                       WHERE codigo_estandar = p_codigo_estandar COLLATE utf8mb4_unicode_ci
                       ORDER BY estado_registro_id LIMIT 1);
    IF v_tabla_id IS NULL OR v_estado_id IS NULL THEN
        SET v_msg = CONCAT('No se resolvio tabla/estado: ', p_tabla, ' / ', p_codigo_estandar);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros
                   WHERE tabla_id = v_tabla_id AND estado_registro_id = v_estado_id) THEN
        INSERT INTO conf__tablas_estados_registros
            (tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, orden)
        VALUES (v_tabla_id, v_estado_id, p_etiqueta, 1, p_es_inicial, p_orden);
    END IF;
END$$

-- DROP solo si la tabla existe y está vacía; si tiene filas, la deja y avisa.
CREATE PROCEDURE `_tes_drop_si_vacia`(IN p_tabla VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla) THEN
        SET @tes_sql = CONCAT('SELECT COUNT(*) INTO @tes_filas FROM `', p_tabla, '`');
        PREPARE tes_stmt FROM @tes_sql;
        EXECUTE tes_stmt;
        DEALLOCATE PREPARE tes_stmt;
        IF @tes_filas = 0 THEN
            SET @tes_sql = CONCAT('DROP TABLE IF EXISTS `', p_tabla, '`');
            PREPARE tes_stmt FROM @tes_sql;
            EXECUTE tes_stmt;
            DEALLOCATE PREPARE tes_stmt;
        ELSE
            SELECT CONCAT(p_tabla, ' tiene ', @tes_filas, ' filas: NO se elimino') AS aviso;
        END IF;
    END IF;
END$$

-- MODIFY COLUMN condicionado (mismo patrón que _sync_add_column de
-- 20260927_sync_local_a_produccion.sql): solo ejecuta el ALTER si la columna
-- existe y su DATA_TYPE actual es p_tipo_actual ('smallint', 'int', ...).
-- Si ya tiene el tipo nuevo, no hace nada (idempotente).
CREATE PROCEDURE `_tes_modify_column`(
    IN p_tabla VARCHAR(64),
    IN p_columna VARCHAR(64),
    IN p_tipo_actual VARCHAR(64),
    IN p_definicion TEXT)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla
          AND COLUMN_NAME = p_columna AND DATA_TYPE = p_tipo_actual
    ) THEN
        SET @tes_sql = CONCAT('ALTER TABLE `', p_tabla, '` MODIFY COLUMN `', p_columna, '` ', p_definicion);
        PREPARE tes_stmt FROM @tes_sql;
        EXECUTE tes_stmt;
        DEALLOCATE PREPARE tes_stmt;
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- 0) CONTROLES PREVIOS
-- =====================================================================
CALL `_tes_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_tes_assert`(
    (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME IN ('gestion__comprobantes', 'gestion__comprobantes_tipos',
                          'gestion__comprobantes_grupos', 'gestion__comprobantes_subgrupos',
                          'conf__tablas',
                          'conf__tablas_estados_registros', 'conf__estados_registros',
                          'conf__tablas_comprobantes_subgrupos', 'gestion__roles_entidades',
                          'gestion__impuestos_tipos', 'gestion__entidades', 'gestion__sucursales',
                          'gestion__monedas', 'gestion__puntos_venta', 'gestion__cont_cuentas',
                          'gestion__jurisdicciones')) = 16,
    'Faltan tablas base del esquema (comprobantes, conf__tablas, entidades, ...)');

-- Tipos de las PK referenciadas por FK (deben coincidir exactamente).
-- MariaDB informa 'bigint(20) unsigned', MySQL 8 'bigint unsigned': se usa LIKE.
CALL `_tes_assert`(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND (
           (TABLE_NAME = 'gestion__comprobantes'       AND COLUMN_NAME = 'comprobante_id'      AND COLUMN_TYPE LIKE 'bigint%unsigned')
        OR (TABLE_NAME = 'gestion__entidades'          AND COLUMN_NAME = 'entidad_id'          AND COLUMN_TYPE LIKE 'int%unsigned')
        OR (TABLE_NAME = 'gestion__sucursales'         AND COLUMN_NAME = 'sucursal_id'         AND COLUMN_TYPE LIKE 'smallint%unsigned')
        OR (TABLE_NAME = 'gestion__monedas'            AND COLUMN_NAME = 'moneda_id'           AND COLUMN_TYPE LIKE 'smallint%unsigned')
        OR (TABLE_NAME = 'gestion__puntos_venta'       AND COLUMN_NAME = 'punto_venta_id'      AND COLUMN_TYPE LIKE 'smallint%unsigned')
        OR (TABLE_NAME = 'gestion__cont_cuentas'       AND COLUMN_NAME = 'cont_cuenta_id'      AND COLUMN_TYPE LIKE 'int%unsigned')
        OR (TABLE_NAME = 'gestion__comprobantes_tipos' AND COLUMN_NAME = 'comprobante_tipo_id' AND COLUMN_TYPE LIKE 'int%unsigned')
        OR (TABLE_NAME = 'gestion__impuestos_tipos'    AND COLUMN_NAME = 'impuesto_tipo_id'    AND COLUMN_TYPE LIKE 'smallint%unsigned')
        OR (TABLE_NAME = 'gestion__jurisdicciones'     AND COLUMN_NAME = 'jurisdiccion_id'     AND DATA_TYPE = 'int' AND COLUMN_TYPE NOT LIKE '%unsigned')
     )) = 9,
    'Los tipos de PK referenciadas no son los esperados: revisar antes de crear FKs');

-- =====================================================================
-- 1) ESTADOS GENÉRICOS FALTANTES (conf__estados_registros)
-- =====================================================================
-- Se identifican por codigo_estandar (los ids se asignan solos).
-- valor_estandar sigue la escala existente (10 Borrador, 20 Pend. aprob.,
-- 30 Confirmado, ..., 100 Eliminado): los estados "con impacto" quedan entre
-- 30 y 89; Rechazado/Devuelto en 9x y Anulado en 110 (después de Eliminado).
-- Nadie en el código usa valor_estandar para lógica (solo el ABM de estados).
--
-- CONVENCIÓN NUEVA (usar en todas las consultas de saldo): un comprobante
-- está "con impacto" si el estado genérico de su tabla_estado_registro_id
-- tiene codigo_estandar IN ('CONFIRMADO','PAGADO','REEMBOLSADO'). Por eso la
-- rendición APROBADA se mapea al genérico CONFIRMADO (etiqueta 'Aprobada'),
-- igual que ventas_pedidos usa CONFIRMADO con la etiqueta
-- 'Confirmado - Pend.Revisión'. APROBADO/RECHAZADO genéricos se usan para los
-- ítems de rendición.
INSERT INTO conf__estados_registros (estado_registro, codigo_estandar, valor_estandar, color_id, orden_estandar)
SELECT v.estado_registro, v.codigo_estandar, v.valor_estandar, 1, v.orden_estandar
FROM (
              SELECT 'Presentado'  AS estado_registro, 'PRESENTADO'  AS codigo_estandar, 15  AS valor_estandar, 15  AS orden_estandar
    UNION ALL SELECT 'En cartera',  'EN_CARTERA',  31,  31
    UNION ALL SELECT 'Aprobado',    'APROBADO',    32,  32
    UNION ALL SELECT 'Emitido',     'EMITIDO',     33,  33
    UNION ALL SELECT 'Depositado',  'DEPOSITADO',  41,  41
    UNION ALL SELECT 'Endosado',    'ENDOSADO',    42,  42
    UNION ALL SELECT 'Debitado',    'DEBITADO',    43,  43
    UNION ALL SELECT 'Pagado',      'PAGADO',      85,  85
    UNION ALL SELECT 'Reembolsado', 'REEMBOLSADO', 86,  86
    UNION ALL SELECT 'Devuelto',    'DEVUELTO',    94,  94
    UNION ALL SELECT 'Rechazado',   'RECHAZADO',   95,  95
    UNION ALL SELECT 'Anulado',     'ANULADO',     110, 110
) v
WHERE NOT EXISTS (
    SELECT 1 FROM conf__estados_registros er
    WHERE er.codigo_estandar = v.codigo_estandar COLLATE utf8mb4_unicode_ci
);

-- =====================================================================
-- 2) ROLES DE ENTIDAD (gestion__roles_entidades)
-- =====================================================================
-- A8: los organismos (AFIP/ARCA, ARBA, AGIP, municipios) son entidades con rol
-- ORG, así I_LIQ/I_PAG cumplen gestion__comprobantes.entidad_id NOT NULL.
-- Los empleados que rinden gastos son entidades con rol EMP.
INSERT INTO gestion__roles_entidades (rol_entidad_codigo, rol_entidad_nombre, rol_entidad_descripcion)
SELECT v.codigo, v.nombre, v.descripcion
FROM (
              SELECT 'EMP' AS codigo, 'Empleado' AS nombre,
                     'Empleado que recibe anticipos y rinde gastos (tesoreria / rendiciones)' AS descripcion
    UNION ALL SELECT 'ORG', 'Organismo fiscal',
                     'Organismo recaudador (ARCA, ARBA, AGIP, municipio) contraparte de liquidaciones y pagos de impuestos'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM gestion__roles_entidades r
    WHERE r.rol_entidad_codigo = v.codigo COLLATE utf8mb4_unicode_ci
);

-- =====================================================================
-- 3) IMPUESTOS: se EXTIENDE gestion__impuestos_tipos
-- =====================================================================
-- Decisión: extender gestion__impuestos_tipos (catálogo global) con
-- es_liquidable + frecuencia, y NO usar gestion__empresas_impuestos_config
-- como maestro de obligaciones. Motivos:
--   - gestion__empresas_impuestos_config es configuración de CÁLCULO
--     (base_calculo, alicuota, f_desde, tipo_calculo NOT NULL): cargar ahí una
--     DDJJ obligaría a inventar alícuotas y bases.
--   - El "organismo" ya existe: gestion__jurisdicciones.organismo_recaudador
--     (ARCA/AFIP, ARBA, AGIP, ...) y, como contraparte del comprobante, la
--     entidad con rol ORG. Agregar 'organismo' a impuestos_tipos lo duplicaría.
--   - La frecuencia es propia del impuesto (IVA mensual, Ganancias anual).
--   - La cuenta contable "X a pagar" se resuelve al crear la obligación:
--     gestion__empresas_impuestos_config.cont_cuenta_id (impuesto + jurisdicción
--     de la empresa, si hay fila activa) y si no gestion__impuestos_tipos.
--     cuenta_contable_id; se COPIA en gestion__obligaciones_impositivas.
--     cont_cuenta_id (materialización: no cambia si después cambia la config).
CALL `_tes_add_column`('gestion__impuestos_tipos', 'es_liquidable',
    'TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = se liquida por DDJJ (comprobante I_LIQ) y se paga con I_PAG'' AFTER `es_percepcion`');
CALL `_tes_add_column`('gestion__impuestos_tipos', 'frecuencia',
    'ENUM(''MENSUAL'',''BIMESTRAL'',''TRIMESTRAL'',''CUATRIMESTRAL'',''SEMESTRAL'',''ANUAL'',''IRREGULAR'') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Frecuencia de liquidacion (solo es_liquidable = 1)'' AFTER `es_liquidable`');

-- Semillas: impuestos liquidables por DDJJ. cuenta_contable_id NULL (TODO:
-- asignar "IVA a pagar", etc. por empresa vía gestion__empresas_impuestos_config).
-- No se tocan las filas existentes (percepciones/retenciones).
INSERT INTO gestion__impuestos_tipos
    (impuesto_tipo, codigo_afip, aplica_compra, aplica_venta, es_retencion, es_percepcion,
     es_liquidable, frecuencia, cuenta_contable_id, tabla_estado_registro_id)
SELECT v.impuesto_tipo, '', 0, 0, 0, 0, 1, v.frecuencia, NULL, 1
FROM (
              SELECT 'IVA - Posicion mensual'                 AS impuesto_tipo, 'MENSUAL' AS frecuencia
    UNION ALL SELECT 'Impuesto a las Ganancias',              'ANUAL'
    UNION ALL SELECT 'Ingresos Brutos',                       'MENSUAL'
    UNION ALL SELECT 'Tasa municipal (Seguridad e Higiene)',  'MENSUAL'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM gestion__impuestos_tipos it
    WHERE it.impuesto_tipo = v.impuesto_tipo COLLATE utf8mb4_unicode_ci
);

-- ---------------------------------------------------------------------
-- 3b) RETENCIONES PRACTICADAS (D21)
-- ---------------------------------------------------------------------
-- Convención con el flag existente es_retencion (sin columnas nuevas):
--   es_retencion = 1 + aplica_compra = 1 -> retención PRACTICADA por la
--     empresa al pagar a un proveedor (gestion__pagos_retenciones); se
--     ingresa al organismo con un I_LIQ por período (es_liquidable = 1).
--   es_retencion = 1 + aplica_venta = 1  -> retención SUFRIDA (la practica
--     el cliente al pagarnos): medio de pago RETENCION en la cobranza. Así
--     están hoy 'Retencion IIBB' y 'Retencion IVA' (aplica_venta = 1): no se
--     tocan, y por eso los tipos nuevos llevan el sufijo "practicada" (el
--     Word v3.2 los nombra «Retención Ganancias», «Retención IVA», ...: los
--     nombres «Retención IVA/IIBB» ya existen con el sentido de sufridas).
-- Frecuencia MENSUAL (SICORE / SIRCAR / ARBA liquidan por mes; los anticipos
-- quincenales se resuelven con más de un I_LIQ en el período).
-- codigo_afip vacío y cuenta_contable_id NULL: a completar por el usuario.
INSERT INTO gestion__impuestos_tipos
    (impuesto_tipo, codigo_afip, aplica_compra, aplica_venta, es_retencion, es_percepcion,
     es_liquidable, frecuencia, cuenta_contable_id, tabla_estado_registro_id)
SELECT v.impuesto_tipo, '', 1, 0, 1, 0, 1, 'MENSUAL', NULL, 1
FROM (
              SELECT 'Retencion Ganancias practicada' AS impuesto_tipo
    UNION ALL SELECT 'Retencion IVA practicada'
    UNION ALL SELECT 'Retencion IIBB practicada'
    UNION ALL SELECT 'Retencion SUSS practicada'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM gestion__impuestos_tipos it
    WHERE it.impuesto_tipo = v.impuesto_tipo COLLATE utf8mb4_unicode_ci
);

-- ---------------------------------------------------------------------
-- 3b') RETENCIONES SUFRIDAS (D33)
-- ---------------------------------------------------------------------
-- Casa Lucho sufre retenciones de IVA, Ganancias e IIBB (medios de pago
-- RETENCION en la cobranza, script 03). En gestion__impuestos_tipos ya
-- existen 'Retención IIBB' y 'Retención IVA' (es_retencion = 1,
-- aplica_venta = 1; local y producción); faltaba Ganancias. Se crea con la
-- misma convención. No es liquidable (es un crédito a favor, no una DDJJ
-- propia) y cuenta_contable_id va NULL: la cuenta la lleva el medio de pago
-- (gestion__medios_pago.cont_cuenta_id = 1.1.03.07 en la empresa 2).
-- Ojo (no se toca): las filas existentes de IVA e IIBB tienen
-- cuenta_contable_id = IVA a Pagar / IIBB a Pagar (pasivo); tesorería no
-- las usa porque la cuenta sale del medio de pago.
INSERT INTO gestion__impuestos_tipos
    (impuesto_tipo, codigo_afip, aplica_compra, aplica_venta, es_retencion, es_percepcion,
     es_liquidable, frecuencia, cuenta_contable_id, tabla_estado_registro_id)
SELECT 'Retención Ganancias', '', 0, 1, 1, 0, 0, NULL, NULL, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM gestion__impuestos_tipos it
    WHERE it.impuesto_tipo = 'Retención Ganancias' COLLATE utf8mb4_unicode_ci
);

-- Marca de AGENTE DE RETENCIÓN por empresa (D21): una FILA por impuesto de
-- retención en gestion__empresas_impuestos_config, sembrada INACTIVA. Es dato
-- por empresa: desde la revisión 4 (D30) se siembra en
-- 20261003_tesoreria_02_siembra_empresa.sql (§4), con los motivos de diseño.

-- =====================================================================
-- 3c) ALTER SOBRE TABLA EXISTENTE — REVISAR ANTES DE EJECUTAR
--     gestion__empresas_impuestos_config.cont_cuenta_id: SMALLINT UNSIGNED
--     -> INT UNSIGNED DEFAULT NULL, igual que la PK de gestion__cont_cuentas
--     (int(10) unsigned).
--     MOTIVO DEL "REVISAR": es el único ALTER de tipo sobre una tabla
--     existente en uso (no es una tabla nueva de tesorería): la leen
--     empresas_impuestos_config_model.php y facturas_proveedores_model.php.
--     Ensanchar un entero sin signo no pierde datos, pero reconstruye la
--     tabla (copia completa, lock de escritura mientras dura) en producción.
--     Aprobado en el Word v3.6 §9.1 fila 5, con la
--     condición de revisar antes la cantidad de filas y el máximo:
--       SELECT COUNT(*) AS filas, MAX(cont_cuenta_id) AS max_cont_cuenta_id
--         FROM gestion__empresas_impuestos_config;
--     Idempotente: _tes_modify_column solo actúa si la columna sigue siendo
--     smallint (consulta information_schema). No agrega FK (la tabla no
--     tenía; queda fuera de este alcance). Para NO aplicarlo, comentar el
--     CALL de abajo: ni este script ni el 02/03 dependen de él (el 02
--     siembra cont_cuenta_id NULL); sin el ALTER, una cuenta contable con
--     id > 65535 no se puede cargar en esa columna.
-- =====================================================================
CALL `_tes_modify_column`('gestion__empresas_impuestos_config', 'cont_cuenta_id', 'smallint',
    'INT UNSIGNED DEFAULT NULL');
-- ===================== fin 3c ========================================

-- Cuenta «Retenciones a depositar» por impuesto: ALCANZA con los campos
-- existentes, sin columnas nuevas:
--   gestion__empresas_impuestos_config.cont_cuenta_id (por empresa) y, como
--   respaldo, gestion__impuestos_tipos.cuenta_contable_id (global).
--   config.cont_cuenta_id se amplía a INT UNSIGNED en §3c (era SMALLINT;
--   gestion__cont_cuentas.cont_cuenta_id es INT UNSIGNED).
-- El I_LIQ de retenciones usa además la cuenta «impuesto a pagar» de la
-- obligación (gestion__obligaciones_impositivas.cont_cuenta_id) para el
-- asiento de reclasificación Retenciones a depositar / Impuesto a pagar
-- (Word §5.5). Si son la misma cuenta, el asiento se compensa.

-- =====================================================================
-- 4) gestion__comprobantes_imputaciones (tabla universal de cancelación)
-- =====================================================================
-- D5/D6/A2/A7/C3/C6:
--   - origen/destino BIGINT UNSIGNED con FK a gestion__comprobantes.
--   - SIN UNIQUE(origen, destino): un cancelador puede aplicar un remanente al
--     mismo destino en otro momento (imputación con fecha propia) y una
--     imputación anulada no debe impedir re-imputar.
--   - Estado propio por el motor: Borrador (la del documento origen en
--     Borrador, editable, NO cuenta) -> Vigente (genérico CONFIRMADO, cuenta)
--     -> Anulada (no cuenta). "Desimputar" = anular la imputación (C6: el
--     destino no se puede anular mientras tenga imputaciones vigentes).
--     Se agrega Borrador (no pedido explícitamente) porque con D1 las
--     imputaciones se cargan con el documento en Borrador.
--   - importe_imputado siempre > 0 en moneda del DESTINO; importe_origen en
--     moneda del ORIGEN; importe_imputado = importe_origen * tipo_cambio
--     (1 si son de la misma moneda). La diferencia contra el tipo de cambio de
--     emisión del destino va a diferencia de cambio en el asiento.
--   - SUM(imputaciones vigentes del origen) <= importe_total del origen (D6):
--     el no aplicado queda en importe_pendiente del cancelador (cobro a cuenta).
-- Forma final (si la tabla no existe se crea directamente así):
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_imputaciones` (
  `imputacion_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `comp_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Comprobante que cancela (RCX, OP, NC, I_PAG, RG_ANT, RG_REEM)',
  `comp_destino_id` BIGINT UNSIGNED NOT NULL COMMENT 'Comprobante cancelado (FA, ND, ND_CHR, I_LIQ, RG_REND, RG_ANT)',
  `f_imputacion` DATE NOT NULL COMMENT 'Fecha de aplicacion (puede ser posterior a la del origen)',
  `importe_imputado` DECIMAL(18,2) NOT NULL COMMENT 'Siempre > 0, en moneda del comprobante DESTINO',
  `moneda_origen_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Moneda del comprobante origen',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'importe_imputado = importe_origen * tipo_cambio',
  `importe_origen` DECIMAL(18,2) NOT NULL COMMENT 'Siempre > 0, en moneda del comprobante ORIGEN',
  `f_anulacion` DATE DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL COMMENT 'Borrador / Vigente (CONFIRMADO) / Anulada',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`imputacion_id`),
  KEY `ix_imp_empresa_destino` (`empresa_id`, `comp_destino_id`),
  KEY `ix_imp_origen` (`comp_origen_id`),
  KEY `ix_imp_destino_estado` (`comp_destino_id`, `tabla_estado_registro_id`),
  KEY `ix_imp_moneda_origen` (`moneda_origen_id`),
  CONSTRAINT `fk_imp_comp_origen_id` FOREIGN KEY (`comp_origen_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_imp_comp_destino_id` FOREIGN KEY (`comp_destino_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_imp_moneda_origen_id` FOREIGN KEY (`moneda_origen_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si ya existía con la forma vieja (local y producción: vacía), se migra:
CALL `_tes_migrar_imputaciones`();

CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'f_imputacion',
    'DATE NOT NULL COMMENT ''Fecha de aplicacion (puede ser posterior a la del origen)'' AFTER `comp_destino_id`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'moneda_origen_id',
    'SMALLINT UNSIGNED NOT NULL COMMENT ''Moneda del comprobante origen'' AFTER `importe_imputado`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'tipo_cambio',
    'DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT ''importe_imputado = importe_origen * tipo_cambio'' AFTER `moneda_origen_id`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'importe_origen',
    'DECIMAL(18,2) NOT NULL COMMENT ''Siempre > 0, en moneda del comprobante ORIGEN'' AFTER `tipo_cambio`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'f_anulacion',
    'DATE DEFAULT NULL AFTER `importe_origen`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'motivo_anulacion',
    'VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `f_anulacion`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'tabla_estado_registro_id',
    'SMALLINT NOT NULL COMMENT ''Borrador / Vigente (CONFIRMADO) / Anulada'' AFTER `motivo_anulacion`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'fecha_creacion',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `tabla_estado_registro_id`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'fecha_modificacion',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `fecha_creacion`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'usuario_id',
    'INT DEFAULT NULL AFTER `fecha_modificacion`');
CALL `_tes_add_column`('gestion__comprobantes_imputaciones', 'usuario_modificacion_id',
    'INT DEFAULT NULL AFTER `usuario_id`');

CALL `_tes_drop_index`('gestion__comprobantes_imputaciones', 'uq');

-- (empresa_id, comp_destino_id) para listados por empresa; la FK de destino
-- necesita un índice que EMPIECE por comp_destino_id: ix_imp_destino_estado
-- (que además cubre el filtro por estado de la consulta de saldo).
CALL `_tes_add_index`('gestion__comprobantes_imputaciones', 'ix_imp_empresa_destino',
    'KEY `ix_imp_empresa_destino` (`empresa_id`, `comp_destino_id`)');
CALL `_tes_add_index`('gestion__comprobantes_imputaciones', 'ix_imp_origen',
    'KEY `ix_imp_origen` (`comp_origen_id`)');
CALL `_tes_add_index`('gestion__comprobantes_imputaciones', 'ix_imp_destino_estado',
    'KEY `ix_imp_destino_estado` (`comp_destino_id`, `tabla_estado_registro_id`)');
CALL `_tes_add_index`('gestion__comprobantes_imputaciones', 'ix_imp_moneda_origen',
    'KEY `ix_imp_moneda_origen` (`moneda_origen_id`)');

CALL `_tes_add_fk`('gestion__comprobantes_imputaciones', 'fk_imp_comp_origen_id',
    'FOREIGN KEY (`comp_origen_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`)');
CALL `_tes_add_fk`('gestion__comprobantes_imputaciones', 'fk_imp_comp_destino_id',
    'FOREIGN KEY (`comp_destino_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`)');
CALL `_tes_add_fk`('gestion__comprobantes_imputaciones', 'fk_imp_moneda_origen_id',
    'FOREIGN KEY (`moneda_origen_id`) REFERENCES `gestion__monedas` (`moneda_id`)');

-- D26: sin CHECK. importe > 0 y origen <> destino se validan en PHP.

-- =====================================================================
-- 5) gestion__comprobantes: UNIQUE de numeración (A6)
-- =====================================================================
-- Pedido: UNIQUE (empresa_id, comprobante_tipo_id, comprobante_pv, comprobante_nro).
-- Dos ajustes necesarios para que no rompa el sistema actual:
--   a) Los Borradores tienen comprobante_nro = 0 (el número se asigna al
--      confirmar): en local hay 55 remitos tipo 40 y 4 pedidos tipo 1 con
--      pv 1 y nro 0. Se indexa comprobante_nro_unico = NULLIF(nro, 0): los
--      NULL no chocan en un UNIQUE.
--   b) gestion__facturas_proveedores graba en el comprobante la numeración
--      DEL PROVEEDOR: dos proveedores distintos emiten legítimamente
--      "Factura A 0001-00000001". Se agrega numeracion_entidad_id =
--      entidad_id solo para comprobantes originados en facturas_proveedores
--      (0 para los de numeración propia). El tabla_id se resuelve acá por
--      nombre y queda fijo en la expresión de la columna generada: si otra
--      tabla pasa a registrar numeración de terceros, hay que redefinirla.
--      (Hallazgo aparte: facturas_proveedores_model.php ~l.440 controla
--      duplicados por pv+nro+tipo+empresa SIN proveedor; rechaza hoy facturas
--      legítimas de proveedores distintos con el mismo número.)
--
-- RIESGO: si ya hay duplicados con nro > 0, el ADD UNIQUE falla (1062) y el
-- script se corta acá; limpiar y volver a correr. Volcados del 2026-10-03:
-- sin duplicados con nro > 0 ni en local ni en producción.
-- Verificación previa (debe devolver 0 filas):
--   SELECT empresa_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
--          CASE WHEN tabla_origen_id = (SELECT tabla_id FROM conf__tablas
--                                       WHERE tabla_nombre = 'gestion__facturas_proveedores' LIMIT 1)
--               THEN entidad_id ELSE 0 END AS numeracion_entidad_id,
--          COUNT(*) AS cant, GROUP_CONCAT(comprobante_id) AS ids
--   FROM gestion__comprobantes
--   WHERE comprobante_nro <> 0
--   GROUP BY 1, 2, 3, 4, 5
--   HAVING COUNT(*) > 1;
SET @tes_t_fp = (SELECT tabla_id FROM conf__tablas
                 WHERE tabla_nombre = 'gestion__facturas_proveedores' ORDER BY tabla_id LIMIT 1);
SET @tes_def = CONCAT(
    'INT GENERATED ALWAYS AS (CASE WHEN `tabla_origen_id` = ', COALESCE(@tes_t_fp, -1),
    ' THEN `entidad_id` ELSE 0 END) STORED',
    ' COMMENT ''entidad_id si la numeracion es del tercero (facturas_proveedores, tabla_id ',
    COALESCE(@tes_t_fp, -1), '), 0 si es propia'' AFTER `signo`');
CALL `_tes_add_column`('gestion__comprobantes', 'numeracion_entidad_id', @tes_def);
CALL `_tes_add_column`('gestion__comprobantes', 'comprobante_nro_unico',
    'INT GENERATED ALWAYS AS (NULLIF(`comprobante_nro`, 0)) STORED COMMENT ''NULL en Borrador (nro 0): excluido del UNIQUE'' AFTER `numeracion_entidad_id`');
CALL `_tes_add_index`('gestion__comprobantes', 'uk_comp_numeracion',
    'UNIQUE KEY `uk_comp_numeracion` (`empresa_id`, `comprobante_tipo_id`, `comprobante_pv`, `comprobante_nro_unico`, `numeracion_entidad_id`)');

-- importe_pendiente (D2/C5): queda como CACHÉ del saldo, actualizada bajo el
-- lock de §R.3 al confirmar/anular imputaciones. Cambio de CÓDIGO pendiente
-- (no DDL): syncComprobante() hoy hace importe_pendiente = importe_total en
-- cada re-sync; debe dejar de pisarla cuando el comprobante tiene
-- imputaciones (solo inicializarla en el INSERT y mientras esté en Borrador).

-- =====================================================================
-- 6) DICCIONARIOS
-- =====================================================================

-- ---------------------------------------------------------------------
-- 6.1 conf__bancos — catálogo GLOBAL (sin empresa_id): entidades BCRA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conf__bancos` (
  `banco_id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo_bcra` VARCHAR(5) NOT NULL COMMENT 'Codigo de entidad BCRA (3 digitos = primeros 3 del CBU)',
  `banco` VARCHAR(150) NOT NULL,
  `banco_abreviado` VARCHAR(50) DEFAULT NULL,
  `cuit` BIGINT UNSIGNED DEFAULT NULL,
  `orden` SMALLINT UNSIGNED DEFAULT 1,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`banco_id`),
  UNIQUE KEY `uk_bco_codigo_bcra` (`codigo_bcra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6.2 gestion__tesoreria_cuentas — D4: cuentas de tesorería UNIFICADAS
-- ---------------------------------------------------------------------
-- Una sola tabla para CAJA (efectivo), BANCO (cuenta bancaria), VALORES
-- (cheques de terceros en cartera) y CUPONES (cupones de tarjeta pendientes
-- de acreditación). Elimina la FK polimórfica de transferencias y modela
-- cartera de cheques y cupones como cuentas más (depositar = transferir de
-- VALORES a BANCO; liquidación de tarjeta = transferir de CUPONES a BANCO).
-- saldo_actual: MATERIALIZADO; solo se modifica con
--   UPDATE ... SET saldo_actual = saldo_actual ± ? en la misma transacción
--   que inserta el gestion__tesoreria_movimientos, con la fila bloqueada
--   (FOR UPDATE, cuentas en orden ascendente de id). Nunca editar a mano.
CREATE TABLE IF NOT EXISTS `gestion__tesoreria_cuentas` (
  `tesoreria_cuenta_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'NULL = cuenta de toda la empresa (tipico en BANCO)',
  `cuenta_tipo` ENUM('CAJA','BANCO','VALORES','CUPONES') NOT NULL,
  `codigo` VARCHAR(20) DEFAULT NULL,
  `tesoreria_cuenta` VARCHAR(100) NOT NULL COMMENT 'Descriptor: Caja Principal, Banco Nacion CC $, Valores en cartera...',
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `banco_id` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Obligatorio si cuenta_tipo = BANCO',
  `banco_cuenta_tipo` ENUM('CC','CA','VIRTUAL') DEFAULT NULL COMMENT 'Solo BANCO',
  `numero` VARCHAR(50) DEFAULT NULL COMMENT 'Solo BANCO: numero de cuenta',
  `cbu` CHAR(22) DEFAULT NULL COMMENT 'Solo BANCO: CBU/CVU de 22 digitos',
  `alias` VARCHAR(50) DEFAULT NULL COMMENT 'Solo BANCO: alias CBU',
  `cont_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'Cuenta contable (Caja Pesos, Banco Nacion $, Valores a depositar...)',
  `saldo_actual` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'MATERIALIZADO: SUM(ingresos) - SUM(egresos) de gestion__tesoreria_movimientos',
  `permite_saldo_negativo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = admite descubierto (BANCO)',
  `orden` SMALLINT UNSIGNED DEFAULT 1,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`tesoreria_cuenta_id`),
  UNIQUE KEY `uk_tcta_empresa_codigo` (`empresa_id`, `codigo`),
  UNIQUE KEY `uk_tcta_empresa_cbu` (`empresa_id`, `cbu`),
  KEY `ix_tcta_empresa_tipo` (`empresa_id`, `cuenta_tipo`),
  KEY `ix_tcta_sucursal` (`sucursal_id`),
  KEY `ix_tcta_moneda` (`moneda_id`),
  KEY `ix_tcta_banco` (`banco_id`),
  KEY `ix_tcta_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_tcta_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_tcta_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_tcta_banco_id` FOREIGN KEY (`banco_id`) REFERENCES `conf__bancos` (`banco_id`),
  CONSTRAINT `fk_tcta_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6.3 gestion__medios_pago — por empresa
-- ---------------------------------------------------------------------
-- El tipo de medio es un ATRIBUTO (medio_pago_tipo). Con D4 ya no hacen falta
-- impacta_caja/impacta_banco: el impacto lo define el tipo de la cuenta de
-- tesorería de cada línea (tesoreria_cuenta_tipo restringe cuáles se admiten).
-- NOTA_CREDITO no es medio de pago (D7: la NC se aplica con imputación
-- directa NC -> FA). RETENCION no mueve tesorería: va a cont_cuenta_id
-- (retenciones sufridas en cobranzas / a depositar en pagos).
CREATE TABLE IF NOT EXISTS `gestion__medios_pago` (
  `medio_pago_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `codigo` VARCHAR(30) NOT NULL COMMENT 'EFECTIVO, CHQ_TERCERO, CHQ_PROPIO, TRANSFER, TARJ_DEB, RET_IIBB...',
  `medio_pago` VARCHAR(100) NOT NULL COMMENT 'Descriptor visible',
  `medio_pago_tipo` ENUM('EFECTIVO','CHEQUE_PROPIO','CHEQUE_TERCERO','TRANSFERENCIA','DEPOSITO',
                         'TARJETA_DEBITO','TARJETA_CREDITO','RETENCION','OTRO') NOT NULL,
  `tesoreria_cuenta_tipo` ENUM('CAJA','BANCO','VALORES','CUPONES') DEFAULT NULL
      COMMENT 'Tipo de cuenta admitida en las lineas con este medio; NULL = no mueve tesoreria (RETENCION)',
  `tesoreria_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'Cuenta sugerida por defecto',
  `cont_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'Cuenta contable cuando el medio no mueve tesoreria (RETENCION)',
  `impuesto_tipo_id` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Solo RETENCION',
  `requiere_detalle` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = pide datos adicionales (cheque, tarjeta, transferencia, certificado)',
  `aplica_cobro` TINYINT(1) NOT NULL DEFAULT 1,
  `aplica_pago` TINYINT(1) NOT NULL DEFAULT 1,
  `orden` SMALLINT UNSIGNED DEFAULT 1,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`medio_pago_id`),
  UNIQUE KEY `uk_mpag_empresa_codigo` (`empresa_id`, `codigo`),
  KEY `ix_mpag_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_mpag_cont_cuenta` (`cont_cuenta_id`),
  KEY `ix_mpag_impuesto_tipo` (`impuesto_tipo_id`),
  CONSTRAINT `fk_mpag_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_mpag_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_mpag_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6.4 gestion__categorias_gasto — por empresa (rendiciones / movimientos de caja)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gestion__categorias_gasto` (
  `categoria_gasto_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `codigo` VARCHAR(20) NOT NULL COMMENT 'COMBUSTIBLE, VIATICOS, PEAJE, COMIDAS, MATERIALES, CORREO, OTROS',
  `categoria_gasto` VARCHAR(100) NOT NULL,
  `cont_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'Cuenta de gasto por defecto (se copia en el item)',
  `requiere_comprobante` TINYINT(1) NOT NULL DEFAULT 1,
  `requiere_justificacion` TINYINT(1) NOT NULL DEFAULT 0,
  `orden` SMALLINT UNSIGNED DEFAULT 1,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`categoria_gasto_id`),
  UNIQUE KEY `uk_cgas_empresa_codigo` (`empresa_id`, `codigo`),
  KEY `ix_cgas_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_cgas_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6.5 gestion__comprobantes_tipos_cancelaciones — "qué tipo cancela a qué tipo"
-- ---------------------------------------------------------------------
-- Reemplaza los flags cancela_con_* del documento (C3): una fila por par
-- (tipo origen, tipo destino) habilitado por empresa. Al imputar se valida
-- que exista el par activo y, si exige_misma_entidad = 1, que origen y
-- destino tengan la misma entidad_id.
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_tipos_cancelaciones` (
  `comprobante_tipo_cancelacion_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `comprobante_tipo_origen_id` INT UNSIGNED NOT NULL COMMENT 'Tipo que cancela (RCX, OP, NC, I_PAG, RG_ANT, RG_REEM)',
  `comprobante_tipo_destino_id` INT UNSIGNED NOT NULL COMMENT 'Tipo cancelado (FA, ND, ND_CHR, I_LIQ, RG_REND, RG_ANT)',
  `exige_misma_entidad` TINYINT(1) NOT NULL DEFAULT 1,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`comprobante_tipo_cancelacion_id`),
  UNIQUE KEY `uk_ctc_empresa_origen_destino` (`empresa_id`, `comprobante_tipo_origen_id`, `comprobante_tipo_destino_id`),
  KEY `ix_ctc_origen` (`comprobante_tipo_origen_id`),
  KEY `ix_ctc_destino` (`comprobante_tipo_destino_id`),
  CONSTRAINT `fk_ctc_comprobante_tipo_origen_id` FOREIGN KEY (`comprobante_tipo_origen_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_ctc_comprobante_tipo_destino_id` FOREIGN KEY (`comprobante_tipo_destino_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6.6 gestion__empresas_cuentas_conceptos — cuentas por concepto (D35, D36)
-- ---------------------------------------------------------------------
-- Word v3.10 §4.10. Cada cuenta de un asiento de tesorería sale de un
-- maestro que ya existe (cuenta de tesorería, medio de pago, categoría de
-- gasto, entidad, configuración de impuestos, transferencia) o, cuando no
-- hay maestro, de un CONCEPTO de la empresa en esta tabla. Arranca con dos
-- conceptos: VALORES_A_PAGAR (cheque propio emitido) y EMPLEADOS (anticipos
-- y rendiciones, D34).
-- Orden de resolución (D36), documentado también en el COMMENT de la tabla:
--   1) cuenta específica del maestro; 2) concepto de la empresa; 3) si no
--   hay ninguna, error claro y el documento no se confirma. Nunca un id de
--   cuenta fijo en el código.
-- Diccionario por empresa (Activo / Inactivo, §8.1). Los conceptos los
-- SIEMBRA el 02 (cont_cuenta_id NULL) y la cuenta la asigna el 03 por código
-- o el ABM. El ABM solo asigna cuenta a conceptos existentes (no crea
-- conceptos) y valida que la cuenta sea imputable, esté activa y sea de la
-- misma empresa: la FK solo garantiza que exista (D26: sin CHECK).
-- Un concepto nuevo (gastos bancarios, diferencia de cambio, ...) es una fila
-- más en la siembra del 02: sin cambio de estructura.
-- empresa_id INT como el resto de los diccionarios de este script (sin FK a
-- conf__empresas, convención de gestion__). cont_cuenta_id INT UNSIGNED =
-- tipo de la PK de gestion__cont_cuentas.
CREATE TABLE IF NOT EXISTS `gestion__empresas_cuentas_conceptos` (
  `empresa_cuenta_concepto_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `concepto` VARCHAR(40) NOT NULL COMMENT 'Codigo del concepto: VALORES_A_PAGAR, EMPLEADOS. Lo siembra el script 02; el ABM no crea conceptos',
  `cont_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'Cuenta por defecto; NULL hasta configurarla. El ABM valida imputable, activa y de la misma empresa',
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`empresa_cuenta_concepto_id`),
  UNIQUE KEY `uk_ecc_empresa_concepto` (`empresa_id`, `concepto`),
  KEY `ix_ecc_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_ecc_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='D35/D36: cuentas contables POR DEFECTO de la empresa por concepto. Al armar un asiento: 1) cuenta especifica del maestro (entidad, cuenta de tesoreria, medio de pago, categoria de gasto); 2) este concepto de la empresa; 3) si no hay ninguna, error y el documento no se confirma. Nunca un id de cuenta fijo en el codigo.';

-- =====================================================================
-- 7) TABLAS DE PROCESO
-- =====================================================================
-- Relaciones (cardinalidades):
--   gestion__comprobantes 1:1 cada cabecera operativa (vía idx_origen)
--   gestion__comprobantes 1:N gestion__comprobantes_imputaciones (como origen y como destino)
--   gestion__cobranzas / pagos / pagos_impuestos / rendiciones_reembolsos 1:N *_medios
--   gestion__medios_pago 1:N *_medios ; gestion__tesoreria_cuentas 1:N *_medios
--   gestion__cheques 1:N *_medios (recepción en cobranza + endoso en pago)
--   gestion__cheques_operaciones N:M gestion__cheques (vía _items)
--   gestion__tesoreria_cuentas 1:N gestion__tesoreria_movimientos
--   gestion__comprobantes 1:N gestion__tesoreria_movimientos
--   gestion__rendiciones 1:N gestion__rendiciones_items
--   gestion__rendiciones 1:N gestion__rendiciones_reembolsos (uno no anulado; la app lo controla)
--   gestion__rendiciones_anticipos N:M gestion__rendiciones (vía imputaciones RG_ANT -> RG_REND)
--   gestion__obligaciones_impositivas N:M gestion__pagos_impuestos (vía imputaciones I_PAG -> I_LIQ)
--   gestion__transferencias N:1 gestion__tesoreria_cuentas (dos veces: origen y destino)

-- ---------------------------------------------------------------------
-- 7.1 gestion__cobranzas — comprobante RCX (reemplaza a gestion__ventas_cobranzas, D9)
-- ---------------------------------------------------------------------
-- Las facturas que cancela van en gestion__comprobantes_imputaciones
-- (origen = el RCX). SUM(imputaciones) <= importe_total (D6, cobro a cuenta).
-- importe_total = SUM(gestion__cobranzas_medios.importe_comprobante).
CREATE TABLE IF NOT EXISTS `gestion__cobranzas` (
  `cobranza_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (RCX). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Cliente',
  `entidad_sucursal_id` INT DEFAULT NULL,
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`cobranza_id`),
  KEY `ix_cob_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_cob_entidad` (`entidad_id`),
  KEY `ix_cob_comprobante` (`comprobante_id`),
  KEY `ix_cob_estado` (`tabla_estado_registro_id`),
  KEY `ix_cob_sucursal` (`sucursal_id`),
  KEY `ix_cob_tipo` (`comprobante_tipo_id`),
  KEY `ix_cob_pv` (`comprobante_pv`),
  KEY `ix_cob_moneda` (`moneda_id`),
  CONSTRAINT `fk_cob_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_cob_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_cob_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_cob_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_cob_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.2 gestion__pagos — comprobante OP (orden de pago a proveedor)
-- ---------------------------------------------------------------------
-- D21: importe_total = SUM(gestion__pagos_medios.importe_comprobante)
--                    + SUM(gestion__pagos_retenciones.importe convertido).
-- Es validación de APLICACIÓN (cruza tablas), no se modela como CHECK. De
-- tesorería sale solo el neto (los medios); la factura se cancela por el total.
-- D25: una OP que pagó con cheques de terceros NO se anula nunca (si el
-- proveedor devuelve un cheque se registra la devolución de endoso, §7.8,
-- y la deuda reabierta la cancela una OP futura).
-- D28: tampoco se anula una OP con un cheque PROPIO ya Rechazado: el
-- CHP_RECH (§7.8) reabrió la deuda y anular la OP la duplicaría. Un cheque
-- propio Emitido sin débito sí pasa a Anulado junto con la OP (con
-- confirmación del usuario de que fue recuperado); uno Debitado no.
-- RT23: tampoco se anula si alguna retención ya tiene comp_liquidacion_id.
-- Las tres son reglas de código; el modelo las soporta (cheques de la OP vía
-- gestion__pagos_medios.cheque_id y su estado, y comp_liquidacion_id,
-- consultables bajo lock, ver §R.3).
CREATE TABLE IF NOT EXISTS `gestion__pagos` (
  `pago_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (OP). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Proveedor',
  `entidad_sucursal_id` INT DEFAULT NULL,
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`pago_id`),
  KEY `ix_pag_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_pag_entidad` (`entidad_id`),
  KEY `ix_pag_comprobante` (`comprobante_id`),
  KEY `ix_pag_estado` (`tabla_estado_registro_id`),
  KEY `ix_pag_sucursal` (`sucursal_id`),
  KEY `ix_pag_tipo` (`comprobante_tipo_id`),
  KEY `ix_pag_pv` (`comprobante_pv`),
  KEY `ix_pag_moneda` (`moneda_id`),
  CONSTRAINT `fk_pag_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_pag_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_pag_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_pag_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_pag_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.3 gestion__cheques — valores (propios y de terceros)
-- ---------------------------------------------------------------------
-- No es un comprobante: es un VALOR con su propio workflow (motor de
-- estados). Por eso no lleva los 8 campos de proceso: lo mueven los
-- comprobantes que lo referencian (RCX/OP al recibir/emitir/endosar,
-- DB/ND_CHR en gestion__cheques_operaciones).
--   TERCERO: Borrador -> En cartera -> Depositado | Endosado | Devuelto
--            Endosado -> En cartera (devolución de endoso, D20)
--            Depositado / Endosado -> Rechazado (C7). D22: NO existe
--            En cartera -> Rechazado (un cheque en cartera no se presentó ni
--            se entregó: nadie puede rechazarlo).
--   PROPIO:  Borrador -> Emitido -> Debitado | Rechazado | Anulado
--            Emitido -> Rechazado genera el comprobante CHP_RECH (D28,
--            §7.8); la OP que lo entregó no se anula ni se revierte.
-- Borrador = cargado en un RCX/OP todavía en Borrador. Si la línea se quita o
-- el documento se elimina, la app BORRA físicamente el cheque (nunca existió
-- en cartera): así no queda ocupando los UNIQUE.
-- Una cobranza solo se puede anular si todos sus cheques siguen En cartera.
-- tesoreria_cuenta_id: PROPIO = cuenta BANCO librada; TERCERO = cuenta
-- VALORES donde está en custodia (el banco de depósito queda en la operación).
-- RT10: el cheque propio (diferido o no) NO mueve el banco al emitirse; el
-- débito se registra con la operación DEBITO.
-- Unicidad: un número de cheque es único por CUENTA librada, no por banco
-- (el documento usaba (empresa, tipo, banco, numero), que choca entre cuentas
-- distintas del mismo banco):
--   PROPIO:  (tesoreria_cuenta_id, numero)
--   TERCERO: (empresa_id, banco_id, librador_cuit, numero); librador_cuit
--            obligatorio para terceros (validación en PHP, D26).
CREATE TABLE IF NOT EXISTS `gestion__cheques` (
  `cheque_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `cheque_tipo` ENUM('PROPIO','TERCERO') NOT NULL,
  `es_echeq` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = cheque electronico (ECHEQ)',
  `banco_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Banco girado',
  `tesoreria_cuenta_id` INT UNSIGNED NOT NULL COMMENT 'PROPIO: cuenta BANCO librada; TERCERO: cuenta VALORES en custodia',
  `numero` VARCHAR(20) NOT NULL,
  `f_emision` DATE NOT NULL,
  `f_pago` DATE NOT NULL COMMENT 'Fecha desde la que puede cobrarse',
  `es_diferido` TINYINT(1) GENERATED ALWAYS AS (CASE WHEN `f_pago` > `f_emision` THEN 1 ELSE 0 END) STORED,
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `importe` DECIMAL(18,2) NOT NULL,
  `librador_nombre` VARCHAR(200) DEFAULT NULL,
  `librador_cuit` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Mismo tipo que gestion__entidades.cuit',
  `entidad_id` INT UNSIGNED DEFAULT NULL COMMENT 'TERCERO: quien lo entrego (cliente); PROPIO: beneficiario',
  `endosable` TINYINT(1) NOT NULL DEFAULT 1,
  `comp_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Comprobante que lo origino (RCX al recibir, OP al emitir)',
  `comp_destino_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Ultimo comprobante de salida (DB deposito/debito, OP endoso, ND_CHR rechazo); historia completa en gestion__cheques_operaciones_items',
  `motivo_rechazo` VARCHAR(255) DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `clave_propio_cuenta_id` INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `cheque_tipo` = 'PROPIO' THEN `tesoreria_cuenta_id` END) STORED COMMENT 'Solo para uk_chq_propio',
  `clave_tercero_empresa_id` INT GENERATED ALWAYS AS (CASE WHEN `cheque_tipo` = 'TERCERO' THEN `empresa_id` END) STORED COMMENT 'Solo para uk_chq_tercero',
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`cheque_id`),
  UNIQUE KEY `uk_chq_propio` (`clave_propio_cuenta_id`, `numero`),
  UNIQUE KEY `uk_chq_tercero` (`clave_tercero_empresa_id`, `banco_id`, `librador_cuit`, `numero`),
  KEY `ix_chq_empresa_estado` (`empresa_id`, `tabla_estado_registro_id`),
  KEY `ix_chq_empresa_f_pago` (`empresa_id`, `f_pago`),
  KEY `ix_chq_sucursal` (`sucursal_id`),
  KEY `ix_chq_banco` (`banco_id`),
  KEY `ix_chq_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_chq_moneda` (`moneda_id`),
  KEY `ix_chq_entidad` (`entidad_id`),
  KEY `ix_chq_comp_origen` (`comp_origen_id`),
  KEY `ix_chq_comp_destino` (`comp_destino_id`),
  CONSTRAINT `fk_chq_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_chq_banco_id` FOREIGN KEY (`banco_id`) REFERENCES `conf__bancos` (`banco_id`),
  CONSTRAINT `fk_chq_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_chq_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_chq_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_chq_comp_origen_id` FOREIGN KEY (`comp_origen_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_chq_comp_destino_id` FOREIGN KEY (`comp_destino_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.4 gestion__cobranzas_medios — medios de pago de la cobranza
-- ---------------------------------------------------------------------
-- Estructura común a los cuatro *_medios (cobranzas, pagos, pagos_impuestos,
-- rendiciones_reembolsos), para que el model PHP la trate con un mismo helper:
--   - tesoreria_cuenta_id (A1): qué cuenta recibe/entrega el dinero. NULL solo
--     para RETENCION. Cheque de tercero recibido: cuenta VALORES; endosado en
--     un pago: sale de VALORES; cheque propio: su cuenta BANCO (sin movimiento
--     hasta DEBITO, RT10); tarjeta: cuenta CUPONES.
--   - importe en moneda de la línea (= moneda de la cuenta) e
--     importe_comprobante en moneda de la cabecera: SUM(importe_comprobante)
--     = importe_total de la cabecera.
--   - cheque_id NULL: FK real a gestion__cheques (sin FK polimórfica varchar).
--   - Bloques opcionales por tipo de medio: transferencia (nro_operacion,
--     f_valor), tarjeta (tarjeta_*), retención (impuesto/jurisdicción/
--     certificado, RT: certificado obligatorio).
CREATE TABLE IF NOT EXISTS `gestion__cobranzas_medios` (
  `cobranza_medio_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cobranza_id` INT UNSIGNED NOT NULL,
  `empresa_id` INT NOT NULL,
  `medio_pago_id` INT UNSIGNED NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION',
  `moneda_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` INT UNSIGNED DEFAULT NULL,
  `nro_operacion` VARCHAR(50) DEFAULT NULL COMMENT 'Transferencia / deposito',
  `f_valor` DATE DEFAULT NULL COMMENT 'Fecha de acreditacion (transferencia, tarjeta)',
  `tarjeta_marca` VARCHAR(30) DEFAULT NULL,
  `tarjeta_terminal` VARCHAR(20) DEFAULT NULL,
  `tarjeta_lote` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cupon` VARCHAR(20) DEFAULT NULL,
  `tarjeta_autorizacion` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cuotas` TINYINT UNSIGNED DEFAULT NULL,
  `impuesto_tipo_id` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Retencion',
  `jurisdiccion_id` INT DEFAULT NULL COMMENT 'Retencion IIBB',
  `retencion_certificado_nro` VARCHAR(50) DEFAULT NULL,
  `retencion_base_imponible` DECIMAL(18,2) DEFAULT NULL,
  `retencion_alicuota` DECIMAL(10,6) DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`cobranza_medio_id`),
  KEY `ix_cobm_cobranza` (`cobranza_id`),
  KEY `ix_cobm_medio_pago` (`medio_pago_id`),
  KEY `ix_cobm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_cobm_moneda` (`moneda_id`),
  KEY `ix_cobm_cheque` (`cheque_id`),
  KEY `ix_cobm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_cobm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_cobm_cobranza_id` FOREIGN KEY (`cobranza_id`) REFERENCES `gestion__cobranzas` (`cobranza_id`),
  CONSTRAINT `fk_cobm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_cobm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_cobm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_cobm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_cobm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_cobm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.5 gestion__pagos_medios — misma estructura que gestion__cobranzas_medios
-- ---------------------------------------------------------------------
-- D21: las retenciones PRACTICADAS por la empresa NO van como medio de pago:
-- van en gestion__pagos_retenciones (§7.5b), con certificado y liquidación.
-- Los medios RETENCION se configuran con aplica_pago = 0. Las columnas
-- retencion_* quedan NULL en esta tabla; se conservan para que los cuatro
-- *_medios tengan la misma forma (un solo helper PHP).
CREATE TABLE IF NOT EXISTS `gestion__pagos_medios` (
  `pago_medio_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pago_id` INT UNSIGNED NOT NULL,
  `empresa_id` INT NOT NULL,
  `medio_pago_id` INT UNSIGNED NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION',
  `moneda_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` INT UNSIGNED DEFAULT NULL COMMENT 'Cheque propio emitido o cheque de tercero endosado',
  `nro_operacion` VARCHAR(50) DEFAULT NULL,
  `f_valor` DATE DEFAULT NULL,
  `tarjeta_marca` VARCHAR(30) DEFAULT NULL,
  `tarjeta_terminal` VARCHAR(20) DEFAULT NULL,
  `tarjeta_lote` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cupon` VARCHAR(20) DEFAULT NULL,
  `tarjeta_autorizacion` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cuotas` TINYINT UNSIGNED DEFAULT NULL,
  `impuesto_tipo_id` SMALLINT UNSIGNED DEFAULT NULL,
  `jurisdiccion_id` INT DEFAULT NULL,
  `retencion_certificado_nro` VARCHAR(50) DEFAULT NULL,
  `retencion_base_imponible` DECIMAL(18,2) DEFAULT NULL,
  `retencion_alicuota` DECIMAL(10,6) DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`pago_medio_id`),
  KEY `ix_pagm_pago` (`pago_id`),
  KEY `ix_pagm_medio_pago` (`medio_pago_id`),
  KEY `ix_pagm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_pagm_moneda` (`moneda_id`),
  KEY `ix_pagm_cheque` (`cheque_id`),
  KEY `ix_pagm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_pagm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_pagm_pago_id` FOREIGN KEY (`pago_id`) REFERENCES `gestion__pagos` (`pago_id`),
  CONSTRAINT `fk_pagm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_pagm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_pagm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_pagm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_pagm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_pagm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.5b gestion__pagos_retenciones — retenciones practicadas en la OP (D21)
-- D32: Casa Lucho no practica retenciones. La tabla se crea igual y queda
-- sin uso hasta que una empresa sea agente de retención.
-- ---------------------------------------------------------------------
-- Detalle de la OP (ciclo de vida = el de la OP): sin tabla_estado_registro_id
-- ni alta en conf__tablas, igual que los *_medios. "Pendiente de liquidar" no
-- es un estado: es comp_liquidacion_id IS NULL (con la OP confirmada).
--   - importe en moneda base (pesos): si la OP es en otra moneda se convierte
--     con el tipo de cambio de la OP.
--   - certificado_nro: NULL en Borrador; se asigna al CONFIRMAR la OP con
--     gestion__retenciones_numeradores bajo FOR UPDATE (§7.5c). Los NULL no
--     chocan en uk_pret_certificado.
--   - comp_liquidacion_id: comprobante I_LIQ que la ingresó al organismo.
--     Se asigna al confirmar el I_LIQ de retenciones con
--     SELECT ... WHERE retencion_id IN (...) ORDER BY retencion_id FOR UPDATE
--     (verificando que sigue NULL); anular el I_LIQ lo vuelve a NULL (RT23).
--   - jurisdiccion_id (agregado, no pedido): la retención de IIBB se liquida
--     por jurisdicción (ARBA / AGIP); NULL en las nacionales.
--   - Sin CHECK: importe > 0, balance de la OP, etc. son validaciones de
--     aplicación.
-- ix_pret_pendientes cubre la consulta del Word §8.9.
CREATE TABLE IF NOT EXISTS `gestion__pagos_retenciones` (
  `retencion_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `pago_id` INT UNSIGNED NOT NULL,
  `impuesto_tipo_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Tipo de retencion practicada (es_retencion = 1, aplica_compra = 1)',
  `jurisdiccion_id` INT DEFAULT NULL COMMENT 'IIBB: jurisdiccion; NULL nacionales',
  `regimen` VARCHAR(100) DEFAULT NULL COMMENT 'Regimen o concepto de la retencion',
  `base_imponible` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `alicuota` DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  `importe` DECIMAL(18,2) NOT NULL COMMENT 'Importe retenido en moneda base (pesos)',
  `certificado_nro` INT DEFAULT NULL COMMENT 'NULL en Borrador; numeracion propia por empresa e impuesto al confirmar la OP',
  `comp_liquidacion_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'I_LIQ que la incluyo; NULL = pendiente de liquidar',
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`retencion_id`),
  UNIQUE KEY `uk_pret_certificado` (`empresa_id`, `impuesto_tipo_id`, `certificado_nro`),
  KEY `ix_pret_pendientes` (`empresa_id`, `impuesto_tipo_id`, `comp_liquidacion_id`),
  KEY `ix_pret_pago` (`pago_id`),
  KEY `ix_pret_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_pret_jurisdiccion` (`jurisdiccion_id`),
  KEY `ix_pret_comp_liquidacion` (`comp_liquidacion_id`),
  CONSTRAINT `fk_pret_pago_id` FOREIGN KEY (`pago_id`) REFERENCES `gestion__pagos` (`pago_id`),
  CONSTRAINT `fk_pret_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_pret_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_pret_comp_liquidacion_id` FOREIGN KEY (`comp_liquidacion_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.5c gestion__retenciones_numeradores — numeración de certificados (D21)
-- D32: sin uso en Casa Lucho (no emite certificados).
-- ---------------------------------------------------------------------
-- Tabla APARTE y no gestion__comprobantes_numeradores: esa tabla tiene clave
-- (empresa_id, punto_venta_id, comprobante_tipo_id) y un certificado no es un
-- tipo de comprobante ni depende del punto de venta; meterlo ahí obligaría a
-- poner impuesto_tipo_id en comprobante_tipo_id (espacios de ids mezclados) o
-- a inventar tipos de comprobante por impuesto. Además aquella tabla es latin1.
-- Uso (mismo patrón que vfObtenerProximoNumero), dentro de la transacción que
-- confirma la OP, una vez por impuesto en orden ascendente de impuesto_tipo_id:
--   SELECT retencion_numerador_id, ultimo_numero
--     FROM gestion__retenciones_numeradores
--    WHERE empresa_id = ? AND impuesto_tipo_id = ? FOR UPDATE;
--   -> UPDATE ... SET ultimo_numero = ? ; si no existe, INSERT con 1
--      (si dos transacciones insertan a la vez, una falla por
--      uk_rnum_empresa_impuesto y se reintenta).
-- Sin estado ni alta en conf__tablas (como gestion__comprobantes_numeradores).
CREATE TABLE IF NOT EXISTS `gestion__retenciones_numeradores` (
  `retencion_numerador_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `impuesto_tipo_id` SMALLINT UNSIGNED NOT NULL,
  `ultimo_numero` INT NOT NULL DEFAULT 0,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`retencion_numerador_id`),
  UNIQUE KEY `uk_rnum_empresa_impuesto` (`empresa_id`, `impuesto_tipo_id`),
  KEY `ix_rnum_impuesto_tipo` (`impuesto_tipo_id`),
  CONSTRAINT `fk_rnum_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.6 gestion__transferencias — comprobante TB (entre cuentas propias)
-- ---------------------------------------------------------------------
-- C9: genera comprobante (TB) y asiento. A4: lado origen y lado destino con
-- su propio importe y tipo de cambio (cuentas en monedas distintas):
--   origen  = (moneda_id, tipo_cambio, importe_total)  <- campos estándar,
--             los que syncComprobante() graba en el comprobante;
--   destino = (moneda_destino_id, tipo_cambio_destino, importe_destino).
-- La diferencia valorizada en moneda base (comisiones de tarjeta, gastos
-- bancarios, diferencia de cambio) va a cont_cuenta_diferencia_id.
-- Sin contraparte: entidad_id NULL en la operativa; en gestion__comprobantes
-- se graba 0 (entidad_id es NOT NULL sin FK, igual que gestion__cont_asientos).
-- Al confirmar: bloquear AMBAS cuentas en orden ascendente de id (FOR UPDATE).
CREATE TABLE IF NOT EXISTS `gestion__transferencias` (
  `transferencia_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (TB). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED DEFAULT NULL COMMENT 'Sin contraparte: NULL (0 en el comprobante)',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `tesoreria_cuenta_origen_id` INT UNSIGNED NOT NULL,
  `tesoreria_cuenta_destino_id` INT UNSIGNED NOT NULL,
  `moneda_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Lado ORIGEN: moneda de la cuenta origen',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Lado ORIGEN: contra moneda base',
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Lado ORIGEN: importe debitado',
  `moneda_destino_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Lado DESTINO: moneda de la cuenta destino',
  `tipo_cambio_destino` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Lado DESTINO: contra moneda base',
  `importe_destino` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Lado DESTINO: importe acreditado',
  `cont_cuenta_diferencia_id` INT UNSIGNED DEFAULT NULL COMMENT 'Gastos/comisiones/diferencia de cambio entre ambos lados',
  `nro_operacion` VARCHAR(50) DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`transferencia_id`),
  KEY `ix_trf_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_trf_comprobante` (`comprobante_id`),
  KEY `ix_trf_estado` (`tabla_estado_registro_id`),
  KEY `ix_trf_sucursal` (`sucursal_id`),
  KEY `ix_trf_tipo` (`comprobante_tipo_id`),
  KEY `ix_trf_pv` (`comprobante_pv`),
  KEY `ix_trf_entidad` (`entidad_id`),
  KEY `ix_trf_cuenta_origen` (`tesoreria_cuenta_origen_id`),
  KEY `ix_trf_cuenta_destino` (`tesoreria_cuenta_destino_id`),
  KEY `ix_trf_moneda` (`moneda_id`),
  KEY `ix_trf_moneda_destino` (`moneda_destino_id`),
  KEY `ix_trf_cont_cuenta_diferencia` (`cont_cuenta_diferencia_id`),
  CONSTRAINT `fk_trf_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_trf_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_trf_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_trf_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_trf_tesoreria_cuenta_origen_id` FOREIGN KEY (`tesoreria_cuenta_origen_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_trf_tesoreria_cuenta_destino_id` FOREIGN KEY (`tesoreria_cuenta_destino_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_trf_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_trf_moneda_destino_id` FOREIGN KEY (`moneda_destino_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_trf_cont_cuenta_diferencia_id` FOREIGN KEY (`cont_cuenta_diferencia_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.7 gestion__movimientos_caja — comprobante MC (ingreso/egreso manual)
-- ---------------------------------------------------------------------
-- C9: genera comprobante MC y asiento (subgrupo AUTO). La contrapartida es
-- cont_cuenta_id (o la de la categoría de gasto); la app la exige al
-- confirmar. Sirve para cualquier cuenta de tesorería (caja o banco: gastos
-- bancarios, intereses, aportes, retiros).
CREATE TABLE IF NOT EXISTS `gestion__movimientos_caja` (
  `movimiento_caja_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (MC). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED DEFAULT NULL COMMENT 'Contraparte opcional (NULL -> 0 en el comprobante)',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `tipo_movimiento` ENUM('INGRESO','EGRESO') NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED NOT NULL,
  `medio_pago_id` INT UNSIGNED DEFAULT NULL,
  `categoria_gasto_id` INT UNSIGNED DEFAULT NULL COMMENT 'Solo egresos por gasto',
  `cont_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'Contrapartida contable (obligatoria al confirmar)',
  `concepto` VARCHAR(200) NOT NULL,
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`movimiento_caja_id`),
  KEY `ix_mcj_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_mcj_comprobante` (`comprobante_id`),
  KEY `ix_mcj_estado` (`tabla_estado_registro_id`),
  KEY `ix_mcj_sucursal` (`sucursal_id`),
  KEY `ix_mcj_tipo` (`comprobante_tipo_id`),
  KEY `ix_mcj_pv` (`comprobante_pv`),
  KEY `ix_mcj_entidad` (`entidad_id`),
  KEY `ix_mcj_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_mcj_medio_pago` (`medio_pago_id`),
  KEY `ix_mcj_categoria_gasto` (`categoria_gasto_id`),
  KEY `ix_mcj_cont_cuenta` (`cont_cuenta_id`),
  KEY `ix_mcj_moneda` (`moneda_id`),
  CONSTRAINT `fk_mcj_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_mcj_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_mcj_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_mcj_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_mcj_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_mcj_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_mcj_categoria_gasto_id` FOREIGN KEY (`categoria_gasto_id`) REFERENCES `gestion__categorias_gasto` (`categoria_gasto_id`),
  CONSTRAINT `fk_mcj_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_mcj_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.8 gestion__cheques_operaciones (+ _items) — comprobantes DB / ND_CHR / DEV_END / CHP_RECH
-- ---------------------------------------------------------------------
-- Tabla NO prevista en el pedido: hace falta porque gestion__comprobantes
-- tiene UNIQUE idx_origen (tabla_origen_id, registro_origen_id): un cheque
-- puede generar varios comprobantes en su vida (depósito y después rechazo),
-- así que no puede ser él mismo el registro origen. Cada operación sobre uno
-- o más cheques es un documento con su comprobante:
--   DEPOSITO   (DB)     cheques de terceros En cartera -> Depositado.
--                       Cuenta = BANCO destino; sale de VALORES, entra a BANCO.
--   DEBITO     (DB)     cheques propios Emitido -> Debitado (RT10).
--                       Cuenta = BANCO del cheque.
--   RECHAZO    (ND_CHR) tercero Depositado/Endosado -> Rechazado (C7; D22:
--                       no desde En cartera). ND interna al cliente
--                       (entidad_id) que se cancela con un RCX. Si estaba
--                       Depositado: débito en la cuenta BANCO; si estaba
--                       Endosado: sin tesorería (la ND del proveedor entra por
--                       facturas de proveedores).
--                       Propio Emitido -> Rechazado (D28): comprobante
--                       CHP_RECH, entidad_id = proveedor, SIN movimiento de
--                       tesorería (RT10: el banco nunca se debitó). Signo '+',
--                       impacta ctacte y contabilidad: el asiento revierte la
--                       contrapartida con que la OP acreditó el cheque (Debe
--                       esa cuenta / Haber Proveedores). Reabre la deuda por el
--                       importe del cheque y la cancelan OP FUTURAS (regla
--                       OP -> CHP_RECH). La OP NO se anula ni se revierte.
--                       Se usa la misma operación RECHAZO: el tipo de
--                       comprobante (ND_CHR tercero / CHP_RECH propio) es el
--                       que distingue, igual que DB sirve a DEPOSITO y a
--                       DEBITO. Una operación RECHAZO no mezcla cheques
--                       propios y de terceros (validación en PHP).
--   DEVOLUCION (ND_CHR) tercero En cartera -> Devuelto al cliente.
--   DEVOLUCION_ENDOSO (DEV_END, D20) tercero Endosado -> En cartera: el
--                       proveedor devolvió físicamente el cheque. INGRESO en
--                       la cuenta VALORES (tesoreria_cuenta_id) y entidad_id =
--                       proveedor. Comprobante NUEVO DEV_END (no se reusa):
--                         - DB es bancario y no impacta ctacte; acá no hay
--                           banco y sí se reabre la deuda con el proveedor.
--                         - ND_CHR es deuda del CLIENTE que cancela un RCX;
--                           usarlo con un proveedor mezclaría contraparte,
--                           regla de cancelación y asiento.
--                         - C_NDA/C_NDX son documentos del proveedor con su
--                           numeración (facturas_proveedores); DEV_END es
--                           interno, con numeración propia.
--                       DEV_END: signo '+', impacta ctacte y contabilidad
--                       (Debe Valores a depositar / Haber Proveedores): la
--                       deuda vuelve a abrirse por el importe del cheque y la
--                       cancelan OP FUTURAS (regla OP -> DEV_END, D25). La OP
--                       que endosó no se toca: nunca se anula (D25).
-- cheque_estado_previo_id en el ítem permite revertir al anular la operación.
CREATE TABLE IF NOT EXISTS `gestion__cheques_operaciones` (
  `cheque_operacion_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL COMMENT 'DB (deposito/debito), ND_CHR (rechazo/devolucion de tercero), DEV_END (devolucion de endoso) o CHP_RECH (rechazo de cheque propio)',
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (DB / ND_CHR / DEV_END / CHP_RECH). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED DEFAULT NULL COMMENT 'RECHAZO de tercero/DEVOLUCION: cliente; RECHAZO de propio (CHP_RECH) y DEVOLUCION_ENDOSO: proveedor; DEPOSITO/DEBITO: NULL',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `operacion` ENUM('DEPOSITO','DEBITO','RECHAZO','DEVOLUCION','DEVOLUCION_ENDOSO') NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'Cuenta afectada (ver tabla de operaciones arriba)',
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'SUM(items.importe) (+ gastos de rechazo si se cobran al cliente)',
  `motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo de rechazo informado por el banco',
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`cheque_operacion_id`),
  KEY `ix_chop_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_chop_comprobante` (`comprobante_id`),
  KEY `ix_chop_estado` (`tabla_estado_registro_id`),
  KEY `ix_chop_sucursal` (`sucursal_id`),
  KEY `ix_chop_tipo` (`comprobante_tipo_id`),
  KEY `ix_chop_pv` (`comprobante_pv`),
  KEY `ix_chop_entidad` (`entidad_id`),
  KEY `ix_chop_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_chop_moneda` (`moneda_id`),
  CONSTRAINT `fk_chop_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_chop_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_chop_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_chop_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_chop_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_chop_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__cheques_operaciones_items` (
  `cheque_operacion_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cheque_operacion_id` INT UNSIGNED NOT NULL,
  `empresa_id` INT NOT NULL,
  `cheque_id` INT UNSIGNED NOT NULL,
  `importe` DECIMAL(18,2) NOT NULL,
  `cheque_estado_previo_id` SMALLINT DEFAULT NULL COMMENT 'tabla_estado_registro_id del cheque antes de la operacion (para revertir al anular)',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`cheque_operacion_item_id`),
  UNIQUE KEY `uk_chopi_operacion_cheque` (`cheque_operacion_id`, `cheque_id`),
  KEY `ix_chopi_cheque` (`cheque_id`),
  CONSTRAINT `fk_chopi_cheque_operacion_id` FOREIGN KEY (`cheque_operacion_id`) REFERENCES `gestion__cheques_operaciones` (`cheque_operacion_id`),
  CONSTRAINT `fk_chopi_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.9 gestion__obligaciones_impositivas — comprobante I_LIQ (DDJJ)
-- ---------------------------------------------------------------------
-- A8: entidad_id = organismo (rol ORG), NOT NULL. Estados: Borrador ->
-- Pendiente de pago (genérico CONFIRMADO) -> Pagada (PAGADO, derivado por
-- recalcularEstadoDestino() cuando el saldo llega a 0, D8) / Anulada.
-- SIN estado VENCIDA (D8): vencida = f_vto < CURDATE() AND saldo > 0, se
-- calcula al leer. C6: no se puede anular con imputaciones I_PAG vigentes.
-- f_vto (1er vencimiento) usa la columna estándar del comprobante.
-- D21: el I_LIQ de retenciones practicadas es una obligación más con
-- impuesto_tipo_id = tipo de retención; las retenciones que incluye se
-- reconocen por gestion__pagos_retenciones.comp_liquidacion_id =
-- comprobante_id de esta obligación (sin tabla puente).
CREATE TABLE IF NOT EXISTS `gestion__obligaciones_impositivas` (
  `obligacion_impositiva_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (I_LIQ). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Organismo fiscal (rol ORG)',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `f_vto` DATE NOT NULL COMMENT 'Primer vencimiento',
  `f_vto_2` DATE DEFAULT NULL COMMENT 'Segundo vencimiento (con recargo)',
  `impuesto_tipo_id` SMALLINT UNSIGNED NOT NULL COMMENT 'gestion__impuestos_tipos con es_liquidable = 1',
  `jurisdiccion_id` INT DEFAULT NULL COMMENT 'IIBB / tasas: jurisdiccion; NULL nacionales',
  `periodo_fiscal` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM o YYYY',
  `cuota_nro` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Anticipo / cuota de plan',
  `descripcion` VARCHAR(200) NOT NULL COMMENT 'IVA marzo 2026, Anticipo 1 Ganancias...',
  `nro_ddjj` VARCHAR(50) DEFAULT NULL,
  `nro_vep` VARCHAR(50) DEFAULT NULL,
  `obligacion_rectificada_id` INT UNSIGNED DEFAULT NULL COMMENT 'DDJJ original que esta rectifica',
  `cont_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'MATERIALIZADO al crear: cuenta "X a pagar" (config empresa o impuestos_tipos)',
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe determinado de la DDJJ',
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`obligacion_impositiva_id`),
  KEY `ix_obli_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_obli_empresa_vto` (`empresa_id`, `f_vto`),
  KEY `ix_obli_impuesto_periodo` (`empresa_id`, `impuesto_tipo_id`, `periodo_fiscal`),
  KEY `ix_obli_comprobante` (`comprobante_id`),
  KEY `ix_obli_estado` (`tabla_estado_registro_id`),
  KEY `ix_obli_sucursal` (`sucursal_id`),
  KEY `ix_obli_tipo` (`comprobante_tipo_id`),
  KEY `ix_obli_pv` (`comprobante_pv`),
  KEY `ix_obli_entidad` (`entidad_id`),
  KEY `ix_obli_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_obli_jurisdiccion` (`jurisdiccion_id`),
  KEY `ix_obli_rectificada` (`obligacion_rectificada_id`),
  KEY `ix_obli_cont_cuenta` (`cont_cuenta_id`),
  KEY `ix_obli_moneda` (`moneda_id`),
  CONSTRAINT `fk_obli_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_obli_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_obli_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_obli_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_obli_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_obli_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_obli_obligacion_rectificada_id` FOREIGN KEY (`obligacion_rectificada_id`) REFERENCES `gestion__obligaciones_impositivas` (`obligacion_impositiva_id`),
  CONSTRAINT `fk_obli_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_obli_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.10 gestion__pagos_impuestos (+ _medios) — comprobante I_PAG
-- ---------------------------------------------------------------------
-- Imputa I_LIQ del mismo organismo (entidad_id) vía imputaciones.
CREATE TABLE IF NOT EXISTS `gestion__pagos_impuestos` (
  `pago_impuesto_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (I_PAG). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Organismo fiscal (rol ORG)',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `nro_vep` VARCHAR(50) DEFAULT NULL,
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` TEXT DEFAULT NULL COMMENT 'Plan de pagos, convenio, etc.',
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`pago_impuesto_id`),
  KEY `ix_pimp_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_pimp_entidad` (`entidad_id`),
  KEY `ix_pimp_comprobante` (`comprobante_id`),
  KEY `ix_pimp_estado` (`tabla_estado_registro_id`),
  KEY `ix_pimp_sucursal` (`sucursal_id`),
  KEY `ix_pimp_tipo` (`comprobante_tipo_id`),
  KEY `ix_pimp_pv` (`comprobante_pv`),
  KEY `ix_pimp_moneda` (`moneda_id`),
  CONSTRAINT `fk_pimp_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_pimp_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_pimp_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_pimp_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_pimp_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__pagos_impuestos_medios` (
  `pago_impuesto_medio_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pago_impuesto_id` INT UNSIGNED NOT NULL,
  `empresa_id` INT NOT NULL,
  `medio_pago_id` INT UNSIGNED NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION (p.ej. saldo a favor / retenciones sufridas computadas)',
  `moneda_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` INT UNSIGNED DEFAULT NULL,
  `nro_operacion` VARCHAR(50) DEFAULT NULL,
  `f_valor` DATE DEFAULT NULL,
  `tarjeta_marca` VARCHAR(30) DEFAULT NULL,
  `tarjeta_terminal` VARCHAR(20) DEFAULT NULL,
  `tarjeta_lote` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cupon` VARCHAR(20) DEFAULT NULL,
  `tarjeta_autorizacion` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cuotas` TINYINT UNSIGNED DEFAULT NULL,
  `impuesto_tipo_id` SMALLINT UNSIGNED DEFAULT NULL,
  `jurisdiccion_id` INT DEFAULT NULL,
  `retencion_certificado_nro` VARCHAR(50) DEFAULT NULL,
  `retencion_base_imponible` DECIMAL(18,2) DEFAULT NULL,
  `retencion_alicuota` DECIMAL(10,6) DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`pago_impuesto_medio_id`),
  KEY `ix_pimpm_pago_impuesto` (`pago_impuesto_id`),
  KEY `ix_pimpm_medio_pago` (`medio_pago_id`),
  KEY `ix_pimpm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_pimpm_moneda` (`moneda_id`),
  KEY `ix_pimpm_cheque` (`cheque_id`),
  KEY `ix_pimpm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_pimpm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_pimpm_pago_impuesto_id` FOREIGN KEY (`pago_impuesto_id`) REFERENCES `gestion__pagos_impuestos` (`pago_impuesto_id`),
  CONSTRAINT `fk_pimpm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_pimpm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_pimpm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_pimpm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_pimpm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_pimpm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.11 gestion__rendiciones_anticipos — comprobante RG_ANT (C8)
-- ---------------------------------------------------------------------
-- El anticipo al empleado es un comprobante (deudor: el empleado), con
-- egreso de tesorería. Al aprobar la rendición, el RG_ANT se imputa al RG_REND
-- (regla RG_ANT -> RG_REND). D24: los RG_ANT a aplicar se ELIGEN en el
-- Borrador de la rendición (imputaciones en estado Borrador); al aprobar se
-- recortan hasta importe_aprobado y pasan a Vigente; sin aplicación
-- automática. El excedente queda como saldo del RG_ANT y el empleado lo
-- devuelve con un RCX imputado al RG_ANT (regla RCX -> RG_ANT).
-- Un solo medio de pago por anticipo (efectivo o transferencia), en cabecera.
CREATE TABLE IF NOT EXISTS `gestion__rendiciones_anticipos` (
  `rendicion_anticipo_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (RG_ANT). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Empleado (rol EMP)',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `f_vto` DATE DEFAULT NULL COMMENT 'Fecha limite para rendir',
  `descripcion` VARCHAR(200) NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED NOT NULL,
  `medio_pago_id` INT UNSIGNED NOT NULL,
  `cheque_id` INT UNSIGNED DEFAULT NULL,
  `nro_operacion` VARCHAR(50) DEFAULT NULL,
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`rendicion_anticipo_id`),
  KEY `ix_rant_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_rant_entidad` (`entidad_id`),
  KEY `ix_rant_comprobante` (`comprobante_id`),
  KEY `ix_rant_estado` (`tabla_estado_registro_id`),
  KEY `ix_rant_sucursal` (`sucursal_id`),
  KEY `ix_rant_tipo` (`comprobante_tipo_id`),
  KEY `ix_rant_pv` (`comprobante_pv`),
  KEY `ix_rant_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_rant_medio_pago` (`medio_pago_id`),
  KEY `ix_rant_cheque` (`cheque_id`),
  KEY `ix_rant_moneda` (`moneda_id`),
  CONSTRAINT `fk_rant_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_rant_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_rant_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_rant_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_rant_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_rant_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_rant_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_rant_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.12 gestion__rendiciones — comprobante RG_REND
-- ---------------------------------------------------------------------
-- A9/RT12 + D1: el comprobante existe desde Borrador (sin impacto) y se
-- CONFIRMA al APROBAR. Estados: Borrador -> Presentada -> Aprobada (genérico
-- CONFIRMADO) -> Reembolsada (REEMBOLSADO, derivado: saldo 0, D8) / Anulada.
-- D19: el rechazo total es la transición Presentada -> Borrador (función del
-- motor) grabando motivo_rechazo; el comprobante sigue en Borrador, sin
-- impactos. No hay estado 'Rechazada' para la rendición (se quitó de §8.4).
-- D23: importe_total = importe del comprobante: importe_bruto mientras no
-- esté aprobada (informativo, sin impacto) e importe_aprobado al aprobar
-- (Word: total_bruto / total_aprobado).
-- Totales MATERIALIZADOS (se recalculan en la transacción que los afecta):
--   importe_bruto        = SUM(items.importe_convertido)
--   importe_aprobado     = SUM(items aprobados)
--   importe_anticipos    = SUM(imputaciones vigentes RG_ANT -> este RG_REND)
--   importe_a_reembolsar = importe_aprobado - importe_anticipos. Con el
--                          recorte de D24 nunca es negativo: > 0 se paga con
--                          RG_REEM; 0 queda Reembolsada. Si el anticipo
--                          supera lo aprobado, el excedente es saldo del
--                          RG_ANT (RCX del empleado), no de la rendición.
CREATE TABLE IF NOT EXISTS `gestion__rendiciones` (
  `rendicion_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 hasta aprobar; se numera al aprobar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (RG_REND). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Empleado (rol EMP)',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `periodo` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM de referencia',
  `descripcion` VARCHAR(200) NOT NULL,
  `f_presentacion` DATE DEFAULT NULL,
  `f_aprobacion` DATE DEFAULT NULL,
  `aprobado_por_usuario_id` INT DEFAULT NULL,
  `motivo_rechazo` TEXT DEFAULT NULL,
  `importe_bruto` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `importe_aprobado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `importe_anticipos` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `importe_a_reembolsar` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'aprobado - anticipos aplicados; nunca negativo (D24, recorte al aprobar)',
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe del comprobante RG_REND',
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`rendicion_id`),
  KEY `ix_rend_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_rend_empresa_periodo` (`empresa_id`, `periodo`),
  KEY `ix_rend_entidad` (`entidad_id`),
  KEY `ix_rend_comprobante` (`comprobante_id`),
  KEY `ix_rend_estado` (`tabla_estado_registro_id`),
  KEY `ix_rend_sucursal` (`sucursal_id`),
  KEY `ix_rend_tipo` (`comprobante_tipo_id`),
  KEY `ix_rend_pv` (`comprobante_pv`),
  KEY `ix_rend_moneda` (`moneda_id`),
  CONSTRAINT `fk_rend_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_rend_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_rend_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_rend_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_rend_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.13 gestion__rendiciones_items — estado POR MOTOR
-- ---------------------------------------------------------------------
-- Estado por motor (no ENUM estado_item): el supervisor aprueba/rechaza cada
-- ítem con botones, y eso es exactamente lo que el motor resuelve con
-- conf__paginas_funciones (Pendiente -> Aprobado | Rechazado). Así el ítem
-- queda registrado en conf__tablas con sus transiciones, como todo lo demás.
-- cont_cuenta_id MATERIALIZADO desde la categoría al cargar (editable).
CREATE TABLE IF NOT EXISTS `gestion__rendiciones_items` (
  `rendicion_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rendicion_id` INT UNSIGNED NOT NULL,
  `empresa_id` INT NOT NULL,
  `f_gasto` DATE NOT NULL,
  `categoria_gasto_id` INT UNSIGNED NOT NULL,
  `descripcion` VARCHAR(300) NOT NULL,
  `entidad_id` INT UNSIGNED DEFAULT NULL COMMENT 'Proveedor del gasto si esta dado de alta',
  `proveedor_nombre` VARCHAR(200) DEFAULT NULL COMMENT 'Texto libre si no es entidad',
  `proveedor_cuit` BIGINT UNSIGNED DEFAULT NULL,
  `comprobante_externo_nro` VARCHAR(50) DEFAULT NULL COMMENT 'Numero del ticket / factura del gasto',
  `tiene_comprobante` TINYINT(1) NOT NULL DEFAULT 1,
  `moneda_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Moneda del gasto',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda del gasto -> moneda de la rendicion',
  `importe` DECIMAL(18,2) NOT NULL COMMENT 'En moneda del gasto',
  `importe_convertido` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la rendicion = importe * tipo_cambio',
  `cont_cuenta_id` INT UNSIGNED DEFAULT NULL,
  `motivo_rechazo` VARCHAR(300) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL COMMENT 'Pendiente / Aprobado / Rechazado',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`rendicion_item_id`),
  KEY `ix_rendi_rendicion_estado` (`rendicion_id`, `tabla_estado_registro_id`),
  KEY `ix_rendi_categoria_gasto` (`categoria_gasto_id`),
  KEY `ix_rendi_entidad` (`entidad_id`),
  KEY `ix_rendi_moneda` (`moneda_id`),
  KEY `ix_rendi_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_rendi_rendicion_id` FOREIGN KEY (`rendicion_id`) REFERENCES `gestion__rendiciones` (`rendicion_id`),
  CONSTRAINT `fk_rendi_categoria_gasto_id` FOREIGN KEY (`categoria_gasto_id`) REFERENCES `gestion__categorias_gasto` (`categoria_gasto_id`),
  CONSTRAINT `fk_rendi_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_rendi_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_rendi_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.14 gestion__rendiciones_reembolsos (+ _medios) — comprobante RG_REEM
-- ---------------------------------------------------------------------
-- Solo para el REMANENTE positivo (C8): importe_total = importe_a_reembolsar
-- de la rendición; se imputa RG_REEM -> RG_REND. Sin UNIQUE(rendicion_id):
-- un reembolso anulado y reemitido deja dos filas; la app controla que haya
-- uno solo no anulado.
CREATE TABLE IF NOT EXISTS `gestion__rendiciones_reembolsos` (
  `rendicion_reembolso_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `comprobante_tipo_id` INT UNSIGNED NOT NULL,
  `comprobante_pv` SMALLINT UNSIGNED NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` INT NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'gestion__comprobantes (RG_REEM). Sin FK: ver encabezado (D1)',
  `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Empleado (rol EMP)',
  `f_emision` DATE NOT NULL,
  `f_contabilidad` DATE NOT NULL,
  `rendicion_id` INT UNSIGNED NOT NULL,
  `moneda_id` SMALLINT UNSIGNED NOT NULL,
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` TEXT DEFAULT NULL,
  `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`rendicion_reembolso_id`),
  KEY `ix_reem_empresa_fecha` (`empresa_id`, `f_emision`),
  KEY `ix_reem_entidad` (`entidad_id`),
  KEY `ix_reem_comprobante` (`comprobante_id`),
  KEY `ix_reem_estado` (`tabla_estado_registro_id`),
  KEY `ix_reem_rendicion` (`rendicion_id`),
  KEY `ix_reem_sucursal` (`sucursal_id`),
  KEY `ix_reem_tipo` (`comprobante_tipo_id`),
  KEY `ix_reem_pv` (`comprobante_pv`),
  KEY `ix_reem_moneda` (`moneda_id`),
  CONSTRAINT `fk_reem_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_reem_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_reem_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_reem_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_reem_rendicion_id` FOREIGN KEY (`rendicion_id`) REFERENCES `gestion__rendiciones` (`rendicion_id`),
  CONSTRAINT `fk_reem_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__rendiciones_reembolsos_medios` (
  `rendicion_reembolso_medio_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rendicion_reembolso_id` INT UNSIGNED NOT NULL,
  `empresa_id` INT NOT NULL,
  `medio_pago_id` INT UNSIGNED NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION',
  `moneda_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` DECIMAL(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` INT UNSIGNED DEFAULT NULL,
  `nro_operacion` VARCHAR(50) DEFAULT NULL,
  `f_valor` DATE DEFAULT NULL,
  `tarjeta_marca` VARCHAR(30) DEFAULT NULL,
  `tarjeta_terminal` VARCHAR(20) DEFAULT NULL,
  `tarjeta_lote` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cupon` VARCHAR(20) DEFAULT NULL,
  `tarjeta_autorizacion` VARCHAR(20) DEFAULT NULL,
  `tarjeta_cuotas` TINYINT UNSIGNED DEFAULT NULL,
  `impuesto_tipo_id` SMALLINT UNSIGNED DEFAULT NULL,
  `jurisdiccion_id` INT DEFAULT NULL,
  `retencion_certificado_nro` VARCHAR(50) DEFAULT NULL,
  `retencion_base_imponible` DECIMAL(18,2) DEFAULT NULL,
  `retencion_alicuota` DECIMAL(10,6) DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`rendicion_reembolso_medio_id`),
  KEY `ix_reemm_reembolso` (`rendicion_reembolso_id`),
  KEY `ix_reemm_medio_pago` (`medio_pago_id`),
  KEY `ix_reemm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_reemm_moneda` (`moneda_id`),
  KEY `ix_reemm_cheque` (`cheque_id`),
  KEY `ix_reemm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_reemm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_reemm_rendicion_reembolso_id` FOREIGN KEY (`rendicion_reembolso_id`) REFERENCES `gestion__rendiciones_reembolsos` (`rendicion_reembolso_id`),
  CONSTRAINT `fk_reemm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_reemm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_reemm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_reemm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_reemm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_reemm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7.15 gestion__tesoreria_movimientos — libro ÚNICO de tesorería (D4)
-- ---------------------------------------------------------------------
-- Reemplaza gestion__cajas_movimientos + gestion__bancos_movimientos del
-- documento. Todo movimiento tiene comprobante (C9): comprobante_id NOT NULL
-- con FK (el comprobante ya existe cuando se confirma).
-- Libro INMUTABLE: anular un documento NO borra ni cambia el estado de sus
-- movimientos; inserta un CONTRAMOVIMIENTO (tipo opuesto, mismo importe,
-- reversa_de_movimiento_id = el original). Motivos: un movimiento ya
-- conciliado contra el extracto no se puede alterar, y el banco muestra el
-- débito y el crédito de una operación revertida.
-- Por eso tabla_estado_registro_id queda siempre en el estado inicial
-- (Activo): la columna está por convención del motor.
-- saldo_actual de la cuenta = SUM(INGRESO) - SUM(EGRESO) de TODAS las filas
-- (ver conciliación en §R.2). tabla_origen_id/registro_origen_id apuntan a la
-- línea que lo generó (p.ej. gestion__cobranzas_medios) para revertir.
CREATE TABLE IF NOT EXISTS `gestion__tesoreria_movimientos` (
  `tesoreria_movimiento_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `sucursal_id` SMALLINT UNSIGNED NOT NULL,
  `tesoreria_cuenta_id` INT UNSIGNED NOT NULL,
  `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'Comprobante que genero el movimiento (RCX, OP, TB, DB, MC, I_PAG, RG_ANT, RG_REEM, ND_CHR)',
  `tabla_origen_id` INT DEFAULT NULL COMMENT 'conf__tablas de la linea origen (p.ej. gestion__cobranzas_medios)',
  `registro_origen_id` INT UNSIGNED DEFAULT NULL,
  `tipo_movimiento` ENUM('INGRESO','EGRESO') NOT NULL,
  `f_movimiento` DATE NOT NULL COMMENT 'Fecha contable (= f_contabilidad del comprobante)',
  `f_valor` DATE NOT NULL COMMENT 'Fecha real de acreditacion / debito',
  `moneda_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Moneda de la cuenta',
  `tipo_cambio` DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Contra moneda base',
  `importe` DECIMAL(18,2) NOT NULL COMMENT 'Siempre > 0, en moneda de la cuenta; la direccion la da tipo_movimiento',
  `medio_pago_id` INT UNSIGNED DEFAULT NULL,
  `cheque_id` INT UNSIGNED DEFAULT NULL,
  `concepto` VARCHAR(200) NOT NULL,
  `conciliado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = conciliado contra extracto',
  `f_conciliacion` DATE DEFAULT NULL,
  `reversa_de_movimiento_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Contramovimiento de anulacion: id del movimiento revertido',
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` INT DEFAULT NULL,
  `usuario_modificacion_id` INT DEFAULT NULL,
  PRIMARY KEY (`tesoreria_movimiento_id`),
  UNIQUE KEY `uk_tmov_reversa` (`reversa_de_movimiento_id`),
  KEY `ix_tmov_cuenta_fecha` (`tesoreria_cuenta_id`, `f_movimiento`),
  KEY `ix_tmov_cuenta_conciliado` (`tesoreria_cuenta_id`, `conciliado`, `f_valor`),
  KEY `ix_tmov_empresa_fecha` (`empresa_id`, `f_movimiento`),
  KEY `ix_tmov_comprobante` (`comprobante_id`),
  KEY `ix_tmov_origen` (`tabla_origen_id`, `registro_origen_id`),
  KEY `ix_tmov_sucursal` (`sucursal_id`),
  KEY `ix_tmov_moneda` (`moneda_id`),
  KEY `ix_tmov_medio_pago` (`medio_pago_id`),
  KEY `ix_tmov_cheque` (`cheque_id`),
  CONSTRAINT `fk_tmov_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_tmov_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_tmov_comprobante_id` FOREIGN KEY (`comprobante_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_tmov_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_tmov_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_tmov_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_tmov_reversa_de_movimiento_id` FOREIGN KEY (`reversa_de_movimiento_id`) REFERENCES `gestion__tesoreria_movimientos` (`tesoreria_movimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8) MOTOR DE ESTADOS: conf__tablas + conf__tablas_estados_registros
-- =====================================================================
-- modulo_id: 2 (gestion) para gestion__*, 1 (conf) para conf__bancos.
-- tabla_tipo_id: 1 Diccionario / 2 Proceso Corto / 3 Proceso Largo.
-- Los ids de conf__tablas y de los estados se resuelven por nombre/código
-- dentro de los helpers. NO se crean conf__paginas ni conf__paginas_funciones.

-- 8.1 Diccionarios (Activo / Inactivo)
CALL `_tes_add_tabla`('conf__bancos', 'Catalogo global de bancos (entidades BCRA)', 1, 1);
CALL `_tes_add_tabla`('gestion__tesoreria_cuentas', 'Cuentas de tesoreria unificadas: CAJA, BANCO, VALORES, CUPONES', 2, 1);
CALL `_tes_add_tabla`('gestion__medios_pago', 'Medios de pago por empresa', 2, 1);
CALL `_tes_add_tabla`('gestion__categorias_gasto', 'Categorias de gasto (rendiciones, movimientos de caja)', 2, 1);
CALL `_tes_add_tabla`('gestion__comprobantes_tipos_cancelaciones', 'Reglas: que tipo de comprobante cancela a que tipo', 2, 1);
CALL `_tes_add_tabla`('gestion__empresas_cuentas_conceptos', 'Cuentas contables por defecto de la empresa por concepto (D35/D36)', 2, 1);

CALL `_tes_add_estado`('conf__bancos', 'ACTIVO', 'Activo', 1, 1);
CALL `_tes_add_estado`('conf__bancos', 'INACTIVO', 'Inactivo', 0, 10);
CALL `_tes_add_estado`('gestion__tesoreria_cuentas', 'ACTIVO', 'Activo', 1, 1);
CALL `_tes_add_estado`('gestion__tesoreria_cuentas', 'INACTIVO', 'Inactivo', 0, 10);
CALL `_tes_add_estado`('gestion__medios_pago', 'ACTIVO', 'Activo', 1, 1);
CALL `_tes_add_estado`('gestion__medios_pago', 'INACTIVO', 'Inactivo', 0, 10);
CALL `_tes_add_estado`('gestion__categorias_gasto', 'ACTIVO', 'Activo', 1, 1);
CALL `_tes_add_estado`('gestion__categorias_gasto', 'INACTIVO', 'Inactivo', 0, 10);
CALL `_tes_add_estado`('gestion__comprobantes_tipos_cancelaciones', 'ACTIVO', 'Activo', 1, 1);
CALL `_tes_add_estado`('gestion__comprobantes_tipos_cancelaciones', 'INACTIVO', 'Inactivo', 0, 10);
CALL `_tes_add_estado`('gestion__empresas_cuentas_conceptos', 'ACTIVO', 'Activo', 1, 1);
CALL `_tes_add_estado`('gestion__empresas_cuentas_conceptos', 'INACTIVO', 'Inactivo', 0, 10);

-- 8.2 Procesos cortos con comprobante (Borrador -> Confirmado -> Anulado; Borrador -> Eliminado)
CALL `_tes_add_tabla`('gestion__cobranzas', 'Cobranzas a clientes (comprobante RCX)', 2, 2);
CALL `_tes_add_tabla`('gestion__pagos', 'Pagos a proveedores (comprobante OP)', 2, 2);
CALL `_tes_add_tabla`('gestion__pagos_impuestos', 'Pagos de impuestos (comprobante I_PAG)', 2, 2);
CALL `_tes_add_tabla`('gestion__transferencias', 'Transferencias entre cuentas de tesoreria (comprobante TB)', 2, 2);
CALL `_tes_add_tabla`('gestion__movimientos_caja', 'Ingresos/egresos manuales de tesoreria (comprobante MC)', 2, 2);
CALL `_tes_add_tabla`('gestion__cheques_operaciones', 'Deposito, debito, rechazo y devolucion de cheques (DB / ND_CHR / DEV_END / CHP_RECH)', 2, 2);
CALL `_tes_add_tabla`('gestion__rendiciones_anticipos', 'Anticipos a rendir a empleados (comprobante RG_ANT)', 2, 2);
CALL `_tes_add_tabla`('gestion__rendiciones_reembolsos', 'Reembolsos de rendiciones (comprobante RG_REEM)', 2, 2);

CALL `_tes_add_estado`('gestion__cobranzas', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__cobranzas', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__cobranzas', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__cobranzas', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_estado`('gestion__pagos', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__pagos', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__pagos', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__pagos', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_estado`('gestion__pagos_impuestos', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__pagos_impuestos', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__pagos_impuestos', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__pagos_impuestos', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_estado`('gestion__transferencias', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__transferencias', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__transferencias', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__transferencias', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_estado`('gestion__movimientos_caja', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__movimientos_caja', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__movimientos_caja', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__movimientos_caja', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_estado`('gestion__cheques_operaciones', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__cheques_operaciones', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__cheques_operaciones', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__cheques_operaciones', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_estado`('gestion__rendiciones_anticipos', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__rendiciones_anticipos', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__rendiciones_anticipos', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__rendiciones_anticipos', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_estado`('gestion__rendiciones_reembolsos', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__rendiciones_reembolsos', 'CONFIRMADO', 'Confirmado', 0, 10);
CALL `_tes_add_estado`('gestion__rendiciones_reembolsos', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__rendiciones_reembolsos', 'ELIMINADO', 'Eliminado', 0, 100);

-- 8.3 Procesos sin página propia / auxiliares (tipo 2)
CALL `_tes_add_tabla`('gestion__comprobantes_imputaciones', 'Imputaciones universales entre comprobantes (cancelador -> cancelado)', 2, 2);
CALL `_tes_add_estado`('gestion__comprobantes_imputaciones', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__comprobantes_imputaciones', 'CONFIRMADO', 'Vigente', 0, 10);
CALL `_tes_add_estado`('gestion__comprobantes_imputaciones', 'ANULADO', 'Anulada', 0, 90);

CALL `_tes_add_tabla`('gestion__tesoreria_movimientos', 'Libro unico de movimientos de tesoreria (inmutable, anulacion por contramovimiento)', 2, 2);
CALL `_tes_add_estado`('gestion__tesoreria_movimientos', 'ACTIVO', 'Registrado', 1, 1);

CALL `_tes_add_tabla`('gestion__rendiciones_items', 'Items de rendicion de gastos (aprobacion por item)', 2, 2);
CALL `_tes_add_estado`('gestion__rendiciones_items', 'PEND_APROB', 'Pendiente', 1, 1);
CALL `_tes_add_estado`('gestion__rendiciones_items', 'APROBADO', 'Aprobado', 0, 10);
CALL `_tes_add_estado`('gestion__rendiciones_items', 'RECHAZADO', 'Rechazado', 0, 20);

-- 8.4 Procesos largos (tipo 3)
CALL `_tes_add_tabla`('gestion__obligaciones_impositivas', 'Liquidaciones impositivas / DDJJ (comprobante I_LIQ)', 2, 3);
CALL `_tes_add_estado`('gestion__obligaciones_impositivas', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__obligaciones_impositivas', 'CONFIRMADO', 'Pendiente de pago', 0, 10);
CALL `_tes_add_estado`('gestion__obligaciones_impositivas', 'PAGADO', 'Pagada', 0, 20);
CALL `_tes_add_estado`('gestion__obligaciones_impositivas', 'ANULADO', 'Anulada', 0, 90);
CALL `_tes_add_estado`('gestion__obligaciones_impositivas', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_tabla`('gestion__rendiciones', 'Rendiciones de gastos de empleados (comprobante RG_REND)', 2, 3);
CALL `_tes_add_estado`('gestion__rendiciones', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__rendiciones', 'PRESENTADO', 'Presentada', 0, 5);
CALL `_tes_add_estado`('gestion__rendiciones', 'CONFIRMADO', 'Aprobada', 0, 10);
-- Sin estado 'Rechazada' (D19): el rechazo total devuelve la rendición a Borrador con motivo_rechazo.
CALL `_tes_add_estado`('gestion__rendiciones', 'REEMBOLSADO', 'Reembolsada', 0, 20);
CALL `_tes_add_estado`('gestion__rendiciones', 'ANULADO', 'Anulada', 0, 90);
CALL `_tes_add_estado`('gestion__rendiciones', 'ELIMINADO', 'Eliminado', 0, 100);

CALL `_tes_add_tabla`('gestion__cheques', 'Cheques propios y de terceros (valores)', 2, 3);
CALL `_tes_add_estado`('gestion__cheques', 'BORRADOR', 'Borrador', 1, 1);
CALL `_tes_add_estado`('gestion__cheques', 'EN_CARTERA', 'En cartera', 0, 10);
CALL `_tes_add_estado`('gestion__cheques', 'EMITIDO', 'Emitido', 0, 11);
CALL `_tes_add_estado`('gestion__cheques', 'DEPOSITADO', 'Depositado', 0, 20);
CALL `_tes_add_estado`('gestion__cheques', 'ENDOSADO', 'Endosado', 0, 21);
CALL `_tes_add_estado`('gestion__cheques', 'DEBITADO', 'Debitado', 0, 22);
CALL `_tes_add_estado`('gestion__cheques', 'DEVUELTO', 'Devuelto', 0, 30);
CALL `_tes_add_estado`('gestion__cheques', 'RECHAZADO', 'Rechazado', 0, 40);
CALL `_tes_add_estado`('gestion__cheques', 'ANULADO', 'Anulado', 0, 90);
CALL `_tes_add_estado`('gestion__cheques', 'ELIMINADO', 'Eliminado', 0, 100);

-- =====================================================================
-- 9) CATÁLOGO DE BANCOS (global)
-- =====================================================================
-- Los subgrupos, tipos de comprobante y reglas de cancelación que estaban
-- en esta sección hasta la revisión 3 (con empresa_id = 2 fijo) pasaron a
-- 20261003_tesoreria_02_siembra_empresa.sql (D30), parametrizados por
-- @tes_empresa_id.

-- Catálogo de bancos (global). Códigos BCRA de 3 dígitos (= primeros 3 del
-- CBU). VERIFICAR contra el listado vigente del BCRA antes de producción.
-- D31: semilla MÍNIMA. Se quitó HSBC (150): no hay cuentas históricas (el
-- script nunca se ejecutó). El resto de las entidades se da de alta a mano.
-- Si una base ya tuviera la fila 150 de una corrida anterior, este script
-- no la borra (inactivarla a mano si no se usa).
INSERT INTO conf__bancos (codigo_bcra, banco, banco_abreviado, orden, tabla_estado_registro_id)
SELECT v.codigo_bcra, v.banco, v.abreviado, v.orden, 1
FROM (
              SELECT '007' AS codigo_bcra, 'Banco de Galicia y Buenos Aires S.A.U.' AS banco, 'Galicia' AS abreviado, 10 AS orden
    UNION ALL SELECT '011', 'Banco de la Nacion Argentina',             'Nacion',      1
    UNION ALL SELECT '014', 'Banco de la Provincia de Buenos Aires',    'Provincia',   2
    UNION ALL SELECT '015', 'Industrial and Commercial Bank of China (Argentina) S.A.U.', 'ICBC', 20
    UNION ALL SELECT '017', 'Banco BBVA Argentina S.A.',                'BBVA',        20
    UNION ALL SELECT '020', 'Banco de la Provincia de Cordoba S.A.',    'Bancor',      20
    UNION ALL SELECT '027', 'Banco Supervielle S.A.',                   'Supervielle', 20
    UNION ALL SELECT '029', 'Banco de la Ciudad de Buenos Aires',       'Ciudad',      20
    UNION ALL SELECT '034', 'Banco Patagonia S.A.',                     'Patagonia',   20
    UNION ALL SELECT '044', 'Banco Hipotecario S.A.',                   'Hipotecario', 20
    UNION ALL SELECT '072', 'Banco Santander Argentina S.A.',           'Santander',   20
    UNION ALL SELECT '191', 'Banco Credicoop Cooperativo Limitado',     'Credicoop',   20
    UNION ALL SELECT '285', 'Banco Macro S.A.',                         'Macro',       20
    UNION ALL SELECT '299', 'Banco Comafi S.A.',                        'Comafi',      20
    UNION ALL SELECT '330', 'Nuevo Banco de Santa Fe S.A.',             'Santa Fe',    20
    UNION ALL SELECT '386', 'Nuevo Banco de Entre Rios S.A.',           'Entre Rios',  20
) v
WHERE NOT EXISTS (
    SELECT 1 FROM conf__bancos b WHERE b.codigo_bcra = v.codigo_bcra COLLATE utf8mb4_unicode_ci
);

-- Diccionarios por empresa SIN semilla en este script (configuración del
-- usuario): gestion__medios_pago, gestion__tesoreria_cuentas,
-- gestion__categorias_gasto (el documento §6.2 sugiere COMBUSTIBLE, VIATICOS,
-- PEAJE, COMIDAS, MATERIALES, CORREO, OTROS con sus cuentas). Plantilla con
-- ejemplos comentados en 20261003_tesoreria_03_datos_iniciales.sql, que
-- también habilita los tipos en gestion__puntos_venta_comprobantes (D29).
-- gestion__empresas_cuentas_conceptos (D35): los conceptos los siembra el
-- 02 (por empresa) y la cuenta la asigna el 03 (bloque 6) o el ABM.

-- =====================================================================
-- 10) REEMPLAZO DE gestion__ventas_cobranzas (D9)
-- =====================================================================
-- Vacía en local y producción, sin referencias en el código (.php/.js) ni
-- fila en conf__tablas. La reemplaza gestion__cobranzas. El DROP queda
-- COMENTADO a propósito (es destructivo): para ejecutarlo, descomentar la
-- línea ANTES de correr el script (el helper se borra al final). El helper
-- solo la elimina si sigue vacía; si tiene filas, avisa y no hace nada.
-- CALL `_tes_drop_si_vacia`('gestion__ventas_cobranzas');

-- =====================================================================
-- V) VERIFICACIÓN FINAL (solo lectura)
-- =====================================================================
-- 20 tablas registradas, cada una con sus estados y exactamente un inicial.
SELECT t.tabla_id, t.tabla_nombre, t.tabla_tipo_id,
       COUNT(ter.tabla_estado_registro_id) AS estados,
       SUM(ter.es_inicial)                 AS iniciales
FROM conf__tablas t
LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = t.tabla_id
WHERE t.tabla_nombre IN ('conf__bancos', 'gestion__tesoreria_cuentas', 'gestion__medios_pago',
                         'gestion__categorias_gasto', 'gestion__comprobantes_tipos_cancelaciones',
                         'gestion__cobranzas', 'gestion__pagos', 'gestion__pagos_impuestos',
                         'gestion__transferencias', 'gestion__movimientos_caja',
                         'gestion__cheques_operaciones', 'gestion__rendiciones_anticipos',
                         'gestion__rendiciones_reembolsos', 'gestion__comprobantes_imputaciones',
                         'gestion__tesoreria_movimientos', 'gestion__rendiciones_items',
                         'gestion__obligaciones_impositivas', 'gestion__rendiciones',
                         'gestion__cheques', 'gestion__empresas_cuentas_conceptos')
GROUP BY t.tabla_id, t.tabla_nombre, t.tabla_tipo_id
ORDER BY t.tabla_id;

-- Catálogo de bancos (D31: sin el código 150).
SELECT banco_id, codigo_bcra, banco, banco_abreviado, orden, tabla_estado_registro_id
FROM conf__bancos
ORDER BY orden, codigo_bcra;

-- Tipos de impuesto globales de tesorería (liquidables y retenciones).
-- Las verificaciones por empresa (tipos, reglas, agente de retención) están
-- al final del 02.
SELECT impuesto_tipo_id, impuesto_tipo, aplica_compra, aplica_venta, es_retencion,
       es_liquidable, frecuencia
FROM gestion__impuestos_tipos
WHERE es_liquidable = 1 OR es_retencion = 1
ORDER BY impuesto_tipo_id;

-- =====================================================================
-- Limpieza de helpers y restauración del sql_mode
-- =====================================================================
DROP PROCEDURE IF EXISTS `_tes_assert`;
DROP PROCEDURE IF EXISTS `_tes_add_column`;
DROP PROCEDURE IF EXISTS `_tes_modify_column`;
DROP PROCEDURE IF EXISTS `_tes_add_index`;
DROP PROCEDURE IF EXISTS `_tes_drop_index`;
DROP PROCEDURE IF EXISTS `_tes_add_fk`;
DROP PROCEDURE IF EXISTS `_tes_add_check`;
DROP PROCEDURE IF EXISTS `_tes_migrar_imputaciones`;
DROP PROCEDURE IF EXISTS `_tes_add_tabla`;
DROP PROCEDURE IF EXISTS `_tes_add_estado`;
DROP PROCEDURE IF EXISTS `_tes_drop_si_vacia`;
DROP PROCEDURE IF EXISTS `_tes_ampliar_cont_cuenta_config`;

SET SESSION sql_mode = @tes_old_sql_mode;

-- =====================================================================
-- R) CONSULTAS DE REFERENCIA (corregidas respecto del documento §2.3/§8)
-- =====================================================================
-- REVISAR (revisión 4) — CONTRADICCIÓN Word/script vs. código, NO corregida
-- acá a propósito: estas consultas y el Word v3.6 (§2.4 y §8) asumen que
-- tabla_estado_registro_id guarda la PK de conf__tablas_estados_registros
-- (un id distinto por tabla) y hacen
--   JOIN conf__tablas_estados_registros ter
--     ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
-- El código vigente guarda el id GENÉRICO de conf__estados_registros:
--   facturas_proveedores_model.php:363 (er.estado_registro_id =
--   fp.tabla_estado_registro_id), ventas_facturas_model.php:390-400
--   (vfEstadoInicial devuelve ter.estado_registro_id), syncComprobante()
--   (default 3 = BORRADOR genérico) y los datos de gestion__comprobantes
--   (valores 3 y 5). Con esa convención el JOIN es directo
--   (JOIN conf__estados_registros er ON er.estado_registro_id =
--   x.tabla_estado_registro_id, sin pasar por ter). Decidirlo antes de
--   implementar los modelos de tesorería.
-- Reglas comunes:
--   - El estado de gestion__comprobantes.tabla_estado_registro_id es el de SU
--     tabla origen (id distinto por tabla, C2): siempre JOIN a
--     conf__tablas_estados_registros + conf__estados_registros y filtrar por
--     codigo_estandar. "Con impacto" = CONFIRMADO, PAGADO, REEMBOLSADO.
--   - Una imputación cuenta si su estado genérico es CONFIRMADO (Vigente). Pasa
--     a Vigente / Anulada en la misma transacción que confirma / anula su
--     origen, así que no hace falta mirar además el estado del origen (RT07).
--   - Saldo = importe_total - aplicado, sin signo (C3). Aplicado de un
--     comprobante = SUM(importe_imputado donde es destino)
--                 + SUM(importe_origen donde es origen)
--     (cubre facturas, cobros a cuenta, NC y RG_ANT, que es origen y destino).
--   - Todo filtrado por empresa_id (multi-tenant).
--
-- ---------------------------------------------------------------------
-- R.1 Saldo pendiente de los comprobantes de una entidad cancelables por un
--     tipo origen (p.ej. RCX): una sola consulta, sin N+1.
--     Parámetros: 1-2) empresa_id, entidad_id (destinos), 3-4) empresa_id,
--     entidad_id (orígenes), 5) comprobante_tipo_origen_id, 6-7) empresa_id,
--     entidad_id. La UI puede leer directamente c.importe_pendiente (caché,
--     D2); esta forma con SUM es la autoritativa.
-- ---------------------------------------------------------------------
-- SELECT c.comprobante_id, ct.codigo, ct.comprobante_tipo, ct.signo,
--        c.comprobante_pv, c.comprobante_nro, c.f_emision, c.f_vto, c.moneda_id,
--        c.importe_total,
--        COALESCE(ap.aplicado, 0)                   AS aplicado,
--        c.importe_total - COALESCE(ap.aplicado, 0) AS saldo_pendiente,
--        c.importe_pendiente                        AS saldo_cache,
--        (c.f_vto IS NOT NULL AND c.f_vto < CURDATE()) AS vencido   -- D8: calculado
-- FROM gestion__comprobantes c
-- JOIN gestion__comprobantes_tipos ct      ON ct.comprobante_tipo_id = c.comprobante_tipo_id
-- JOIN conf__tablas_estados_registros ter  ON ter.tabla_estado_registro_id = c.tabla_estado_registro_id
-- JOIN conf__estados_registros er          ON er.estado_registro_id = ter.estado_registro_id
-- LEFT JOIN (
--     SELECT x.comprobante_id, SUM(x.importe) AS aplicado
--     FROM (
--         SELECT ci.comp_destino_id AS comprobante_id, ci.importe_imputado AS importe,
--                ci.tabla_estado_registro_id
--         FROM gestion__comprobantes_imputaciones ci
--         JOIN gestion__comprobantes d ON d.comprobante_id = ci.comp_destino_id
--         WHERE d.empresa_id = ? AND d.entidad_id = ?
--         UNION ALL
--         SELECT ci.comp_origen_id, ci.importe_origen, ci.tabla_estado_registro_id
--         FROM gestion__comprobantes_imputaciones ci
--         JOIN gestion__comprobantes o ON o.comprobante_id = ci.comp_origen_id
--         WHERE o.empresa_id = ? AND o.entidad_id = ?
--     ) x
--     JOIN conf__tablas_estados_registros teri ON teri.tabla_estado_registro_id = x.tabla_estado_registro_id
--     JOIN conf__estados_registros eri         ON eri.estado_registro_id = teri.estado_registro_id
--     WHERE eri.codigo_estandar = 'CONFIRMADO'
--     GROUP BY x.comprobante_id
-- ) ap ON ap.comprobante_id = c.comprobante_id
-- WHERE EXISTS (
--         SELECT 1
--         FROM gestion__comprobantes_tipos_cancelaciones r
--         JOIN conf__tablas_estados_registros terr ON terr.tabla_estado_registro_id = r.tabla_estado_registro_id
--         JOIN conf__estados_registros err         ON err.estado_registro_id = terr.estado_registro_id
--         WHERE r.empresa_id = c.empresa_id
--           AND r.comprobante_tipo_origen_id = ?
--           AND r.comprobante_tipo_destino_id = c.comprobante_tipo_id
--           AND err.codigo_estandar = 'ACTIVO')
--   AND c.empresa_id = ? AND c.entidad_id = ?
--   AND er.codigo_estandar IN ('CONFIRMADO', 'PAGADO', 'REEMBOLSADO')
--   AND c.importe_total - COALESCE(ap.aplicado, 0) > 0
-- ORDER BY c.f_vto IS NULL, c.f_vto, c.comprobante_id;
--
-- (Nota: las filas de diccionario existentes usan tabla_estado_registro_id = 1
--  como "activo" sin respetar el id propio de la tabla; si la app sigue esa
--  convención para gestion__comprobantes_tipos_cancelaciones, reemplazar el
--  JOIN de r por "r.tabla_estado_registro_id = 1".)
--
-- ---------------------------------------------------------------------
-- R.2 Conciliación de cachés materializadas (deben devolver 0 filas).
-- ---------------------------------------------------------------------
-- a) importe_pendiente vs SUM de imputaciones vigentes (D2/C5), para los
--    comprobantes de tipos que participan en alguna regla de cancelación.
--    Parámetros: empresa_id (x5: dos en el derivado, uno en WHERE, dos en el IN).
-- SELECT c.comprobante_id, ct.codigo, c.importe_total, c.importe_pendiente,
--        c.importe_total - COALESCE(ap.aplicado, 0)                       AS pendiente_calculado,
--        c.importe_pendiente - (c.importe_total - COALESCE(ap.aplicado, 0)) AS diferencia
-- FROM gestion__comprobantes c
-- JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
-- LEFT JOIN (
--     SELECT x.comprobante_id, SUM(x.importe) AS aplicado
--     FROM (
--         SELECT ci.comp_destino_id AS comprobante_id, ci.importe_imputado AS importe, ci.tabla_estado_registro_id
--         FROM gestion__comprobantes_imputaciones ci WHERE ci.empresa_id = ?
--         UNION ALL
--         SELECT ci.comp_origen_id, ci.importe_origen, ci.tabla_estado_registro_id
--         FROM gestion__comprobantes_imputaciones ci WHERE ci.empresa_id = ?
--     ) x
--     JOIN conf__tablas_estados_registros teri ON teri.tabla_estado_registro_id = x.tabla_estado_registro_id
--     JOIN conf__estados_registros eri         ON eri.estado_registro_id = teri.estado_registro_id
--     WHERE eri.codigo_estandar = 'CONFIRMADO'
--     GROUP BY x.comprobante_id
-- ) ap ON ap.comprobante_id = c.comprobante_id
-- WHERE c.empresa_id = ?
--   AND c.comprobante_tipo_id IN (
--         SELECT comprobante_tipo_origen_id  FROM gestion__comprobantes_tipos_cancelaciones WHERE empresa_id = ?
--         UNION
--         SELECT comprobante_tipo_destino_id FROM gestion__comprobantes_tipos_cancelaciones WHERE empresa_id = ?)
--   AND c.importe_pendiente <> c.importe_total - COALESCE(ap.aplicado, 0);
--
-- b) saldo_actual de las cuentas de tesorería vs su libro (A4).
-- SELECT tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.saldo_actual,
--        COALESCE(SUM(CASE m.tipo_movimiento WHEN 'INGRESO' THEN m.importe ELSE -m.importe END), 0) AS saldo_calculado
-- FROM gestion__tesoreria_cuentas tc
-- LEFT JOIN gestion__tesoreria_movimientos m ON m.tesoreria_cuenta_id = tc.tesoreria_cuenta_id
-- WHERE tc.empresa_id = ?
-- GROUP BY tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.saldo_actual
-- HAVING tc.saldo_actual <> saldo_calculado;
--
-- ---------------------------------------------------------------------
-- R.3 Confirmar un documento con imputaciones: locks ordenados (C4, RT04, A4, RT23)
-- ---------------------------------------------------------------------
-- Orden GLOBAL de locks del módulo (igual en confirmar, anular y desimputar,
-- para que no haya ciclos -> sin deadlocks). Cada paso se toma solo si la
-- operación lo necesita, pero nunca en otro orden:
--   1) fila operativa propia (UPDATE optimista)
--   2) gestion__comprobantes por comprobante_id ASC
--   3) gestion__pagos_retenciones por retencion_id ASC        (D21, RT23)
--   4) gestion__tesoreria_cuentas por tesoreria_cuenta_id ASC
--   5) gestion__cheques por cheque_id ASC
--   6) numeradores: gestion__comprobantes_numeradores y después
--      gestion__retenciones_numeradores por impuesto_tipo_id ASC
-- Por qué las retenciones van en 3 (después de comprobantes, antes de
-- cuentas): las tres operaciones que las tocan bloquean antes su comprobante
-- (confirmar I_LIQ de retenciones: el I_LIQ; anular ese I_LIQ: el I_LIQ;
-- anular la OP: la OP y sus destinos) y ninguna vuelve a pedir un comprobante
-- después de tomar retenciones, así que no hay ciclo. Y van antes de cuentas
-- porque anular una OP primero debe verificar RT23 (retenciones sin liquidar)
-- y recién después mover tesorería. Los numeradores van al final: son las
-- filas más disputadas y se toman lo más tarde posible.
--
-- mysqli_begin_transaction($conexion);
--
-- -- 1) Transición optimista de la operativa (RT04). 0 filas -> ROLLBACK
-- --    ("el documento fue modificado por otro usuario").
-- UPDATE gestion__cobranzas
--    SET tabla_estado_registro_id = ? /*confirmado de la tabla*/, usuario_modificacion_id = ?
--  WHERE cobranza_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ? /*borrador*/;
--
-- -- 2) Bloquear origen + TODOS los destinos, ordenados por PK. La lista de
-- --    destinos se lee antes (imputaciones Borrador del origen) y se arma el
-- --    IN con un placeholder por id.
-- SELECT c.comprobante_id, c.empresa_id, c.entidad_id, c.comprobante_tipo_id, c.moneda_id,
--        c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id
--   FROM gestion__comprobantes c
--  WHERE c.empresa_id = ? AND c.comprobante_id IN (?, ?, ?)
--  ORDER BY c.comprobante_id
--    FOR UPDATE;
--
-- -- 3) Con los locks tomados, recalcular lo ya aplicado a cada destino (otra
-- --    cobranza de otra sucursal pudo imputar entre la lectura y el lock):
-- SELECT ci.comp_destino_id, SUM(ci.importe_imputado) AS aplicado
--   FROM gestion__comprobantes_imputaciones ci
--   JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = ci.tabla_estado_registro_id
--   JOIN conf__estados_registros er         ON er.estado_registro_id = ter.estado_registro_id
--  WHERE ci.empresa_id = ? AND ci.comp_destino_id IN (?, ?, ?)
--    AND er.codigo_estandar = 'CONFIRMADO'
--  GROUP BY ci.comp_destino_id;
-- --    Validar en PHP, por destino: misma empresa; par (tipo origen, tipo
-- --    destino) activo en gestion__comprobantes_tipos_cancelaciones; misma
-- --    entidad si exige_misma_entidad; destino "con impacto"; importe nuevo
-- --    <= importe_total - aplicado. Por origen: SUM(importe_origen) <=
-- --    importe_total (D6). Cualquier falla -> ROLLBACK.
--
-- -- 4) Imputaciones del origen: Borrador -> Vigente.
-- UPDATE gestion__comprobantes_imputaciones
--    SET tabla_estado_registro_id = ? /*vigente*/, usuario_modificacion_id = ?
--  WHERE empresa_id = ? AND comp_origen_id = ? AND tabla_estado_registro_id = ? /*borrador*/;
--
-- -- 5) Caché (D2): descontar en cada destino y en el origen, con guarda.
-- --    0 filas -> ROLLBACK (la caché quedaría negativa: inconsistencia).
-- UPDATE gestion__comprobantes
--    SET importe_pendiente = importe_pendiente - ?
--  WHERE comprobante_id = ? AND empresa_id = ? AND importe_pendiente >= ?;
--
-- -- 6) syncComprobante() del origen (estado espejo; SIN pisar importe_pendiente)
-- --    y recalcularEstadoDestino() de cada destino (D8: PAGADO/REEMBOLSADO si
-- --    importe_pendiente = 0, vuelta a CONFIRMADO al anular).
--
-- -- 7) Tesorería: bloquear las cuentas de las líneas, en orden.
-- SELECT tesoreria_cuenta_id, saldo_actual, permite_saldo_negativo
--   FROM gestion__tesoreria_cuentas
--  WHERE empresa_id = ? AND tesoreria_cuenta_id IN (?, ?)
--  ORDER BY tesoreria_cuenta_id
--    FOR UPDATE;
-- --    Por línea: INSERT gestion__tesoreria_movimientos (...) y
-- UPDATE gestion__tesoreria_cuentas
--    SET saldo_actual = saldo_actual + ? /* -? en egresos */
--  WHERE tesoreria_cuenta_id = ? AND empresa_id = ?;
--
-- -- 8) Cheques de la cobranza: Borrador -> En cartera (FOR UPDATE por cheque_id ASC).
-- -- 9) Numeración (FOR UPDATE en gestion__comprobantes_numeradores), asiento
-- --    (subgrupo AUTO), log en gestion__logs (accion 'UPDATE', RT13).
-- mysqli_commit($conexion);   // try/catch -> mysqli_rollback($conexion)
--
-- Confirmar el I_LIQ de retenciones (D21, Word §5.5, RT23):
--   1) UPDATE optimista de gestion__obligaciones_impositivas.
--   2) SELECT ... FROM gestion__comprobantes WHERE comprobante_id = ? FOR UPDATE.
--   3) SELECT r.retencion_id, r.importe, r.comp_liquidacion_id
--        FROM gestion__pagos_retenciones r
--       WHERE r.empresa_id = ? AND r.impuesto_tipo_id = ? AND r.retencion_id IN (?, ?, ?)
--       ORDER BY r.retencion_id
--         FOR UPDATE;
--      Rechazar si alguna ya tiene comp_liquidacion_id (otro usuario la
--      liquidó) o si SUM(importe) <> importe_total de la obligación; verificar
--      además que su OP siga confirmada.
--   4) UPDATE gestion__pagos_retenciones SET comp_liquidacion_id = ?
--       WHERE retencion_id IN (...) AND comp_liquidacion_id IS NULL;
--      (filas afectadas <> cantidad -> ROLLBACK).
--   5) numerador del I_LIQ, asiento de reclasificación, COMMIT.
-- Anular ese I_LIQ: mismos pasos 1-3 y comp_liquidacion_id = NULL.
-- Anular una OP: si pagó con algún cheque de terceros, o con un cheque
-- propio ya Rechazado, se rechaza sin más (D25 / D28: nunca se anula; se
-- verifica leyendo gestion__pagos_medios.cheque_id y el estado del cheque
-- con la OP bloqueada en el paso 2). Si no, bloquear sus retenciones
-- (paso 3) y rechazar si alguna tiene comp_liquidacion_id (RT23).
-- Rechazo de cheque propio (D28): bloquear el cheque (paso 5), Emitido ->
-- Rechazado, crear la operación RECHAZO con su CHP_RECH (syncComprobante,
-- ctacte del proveedor +, asiento) en la misma transacción; sin tesorería.
-- Confirmar una OP con retenciones: el paso 6 asigna certificado_nro por
-- impuesto (gestion__retenciones_numeradores FOR UPDATE).
--
-- Anular un DESTINO (C6): bloquear su fila de gestion__comprobantes con FOR
-- UPDATE y rechazar si tiene imputaciones Vigentes (desimputar primero).
-- Anular un ORIGEN: mismos locks; imputaciones Vigente -> Anulada (con
-- f_anulacion), devolver importe_pendiente a destinos y origen,
-- contramovimientos de tesorería y recalcularEstadoDestino().
-- =====================================================================
