-- =====================================================================
-- Sincronizacion LOCAL (gestion_multipyme) -> DESARROLLO (ps_gestion_casalucho_desarrollo)
-- Script 01: ESTRUCTURA
-- 2026-10-09
-- =====================================================================
-- Surge de cargar casalucho_desarrollo.sql (volcado del 2026-10-09) en una base
-- local y compararla con gestion_multipyme por information_schema. Generado; ver
-- el resumen de cada seccion. NO copia datos: la configuracion va en el script 02.
--
-- Que hace:
--   1) Matriz Legal: baja de las 23 tablas del prototipo (equivale a
--      20261002_ml_m0_01_baja_prototipo.sql, que desarrollo nunca corrio). En
--      desarrollo solo tienen filas semilla; el script 02 carga las de local.
--   2) Tablas que en desarrollo tienen la forma vieja y estan VACIAS:
--      gestion__perfiles_sucursales, gestion__usuarios_sucursales_excepciones,
--      gestion__comprobantes_imputaciones. Se borran y se crean como en local.
--      Si alguna tuviera filas, el script se corta (no se pierde nada).
--   3) Stock por boca (20261008_stock_01_ddl.sql): gestion__stock,
--      gestion__stock_movimientos, _detalles y _tipos se RENOMBRAN con sufijo
--      _bak20261008 (no se borran). El stock nuevo arranca vacio: hay que cargar un
--      ajuste de inventario inicial, igual que se hizo en local.
--   4) CREATE TABLE IF NOT EXISTS de las 80 tablas que faltan (tesoreria,
--      estudio contable, fiscal, stock, Matriz Legal M0..M8, etc.).
--   5) Columnas, indices y FKs que faltan en tablas existentes, y las columnas
--      que cambiaron de tipo o pasaron a admitir NULL.
--   6) Procedimientos de compatibilidad de productos (version de local).
--
-- NO toca: datos operativos, usuarios, ni lo que existe solo en desarrollo
-- (gestion__depositos, ecom__usuarios_entidades, *_copy, gestion__ventas_cobranzas,
-- conf__usuarios_password_resets, el evento ev_refrescar_compatibilidad_anual,
-- FKs FK_esc_* de gestion__entidades_sucursales_compra).
--
-- Reanudable: cada paso consulta information_schema antes de ejecutarse (el DDL
-- hace commit implicito, no hay rollback). Correr con el cliente mysql, sin
-- --force:
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 ps_gestion_casalucho_desarrollo < 20261009_sync_local_a_desarrollo_01_estructura.sql
-- Antes: respaldo completo de la base (mysqldump 8.0 con --set-gtid-purged=OFF).
-- =====================================================================

SET NAMES utf8mb4;
USE `ps_gestion_casalucho_desarrollo`;
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET SESSION lock_wait_timeout = 60;

-- ---------------------------------------------------------------------
-- Helpers temporales (se eliminan al final)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_sync_exec`;
DROP PROCEDURE IF EXISTS `_sync_add_column`;
DROP PROCEDURE IF EXISTS `_sync_add_index`;
DROP PROCEDURE IF EXISTS `_sync_drop_index`;
DROP PROCEDURE IF EXISTS `_sync_add_fk`;
DROP PROCEDURE IF EXISTS `_sync_recrear`;
DROP PROCEDURE IF EXISTS `_sync_renombrar`;

DELIMITER $$

CREATE PROCEDURE `_sync_exec`(IN p_sql TEXT)
BEGIN
    SET @sync_sql = p_sql;
    PREPARE sync_stmt FROM @sync_sql;
    EXECUTE sync_stmt;
    DEALLOCATE PREPARE sync_stmt;
END$$

CREATE PROCEDURE `_sync_add_column`(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna) THEN
        CALL _sync_exec(CONCAT('ALTER TABLE `', p_tabla, '` ADD COLUMN `', p_columna, '` ', p_definicion));
    END IF;
END$$

CREATE PROCEDURE `_sync_add_index`(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND INDEX_NAME = p_indice) THEN
        CALL _sync_exec(CONCAT('ALTER TABLE `', p_tabla, '` ADD ', p_definicion));
    END IF;
END$$

CREATE PROCEDURE `_sync_drop_index`(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND INDEX_NAME = p_indice) THEN
        CALL _sync_exec(CONCAT('ALTER TABLE `', p_tabla, '` DROP INDEX `', p_indice, '`'));
    END IF;
END$$

CREATE PROCEDURE `_sync_add_fk`(IN p_tabla VARCHAR(64), IN p_fk VARCHAR(64), IN p_definicion TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla
                     AND CONSTRAINT_NAME = p_fk AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
        CALL _sync_exec(CONCAT('ALTER TABLE `', p_tabla, '` ADD ', p_definicion));
    END IF;
END$$

-- Borra p_tabla si todavia tiene la forma vieja (le falta p_columna_nueva).
-- Con p_exige_vacia = 1 corta el script si la tabla tiene filas.
CREATE PROCEDURE `_sync_recrear`(IN p_tabla VARCHAR(64), IN p_columna_nueva VARCHAR(64), IN p_exige_vacia TINYINT)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla)
       AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna_nueva) THEN
        SET @sync_filas = 0;
        CALL _sync_exec(CONCAT('SELECT COUNT(*) INTO @sync_filas FROM `', p_tabla, '`'));
        IF p_exige_vacia = 1 AND @sync_filas > 0 THEN
            SET @sync_msg = CONCAT(p_tabla, ' tiene filas con la estructura vieja: migrar a mano antes de seguir');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @sync_msg;
        END IF;
        CALL _sync_exec(CONCAT('DROP TABLE `', p_tabla, '`'));
    END IF;
END$$

-- Renombra p_tabla a p_destino si existe, si p_destino no existe y (p_columna_vieja vacia o la tabla la tiene).
CREATE PROCEDURE `_sync_renombrar`(IN p_tabla VARCHAR(64), IN p_destino VARCHAR(64), IN p_columna_vieja VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla)
       AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_destino)
       AND (p_columna_vieja = '' OR EXISTS (SELECT 1 FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna_vieja)) THEN
        CALL _sync_exec(CONCAT('RENAME TABLE `', p_tabla, '` TO `', p_destino, '`'));
    END IF;
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) Matriz Legal: baja del prototipo (23 tablas)
-- ---------------------------------------------------------------------
-- 13 tablas que el modelo nuevo no tiene (solo filas semilla en desarrollo)
DROP TABLE IF EXISTS `ml__tareas_evidencias`;
DROP TABLE IF EXISTS `ml__tareas_dependencias`;
DROP TABLE IF EXISTS `ml__tareas_ciclos`;
DROP TABLE IF EXISTS `ml__tareas`;
DROP TABLE IF EXISTS `ml__obligaciones_raci`;
DROP TABLE IF EXISTS `ml__procedimientos_obligaciones`;
DROP TABLE IF EXISTS `ml__normas_procedimientos`;
DROP TABLE IF EXISTS `ml__normas_ambitos_aplicacion`;
DROP TABLE IF EXISTS `ml__roles_organizacionales`;
DROP TABLE IF EXISTS `ml__raci_tipos`;
DROP TABLE IF EXISTS `ml__jurisdicciones`;
DROP TABLE IF EXISTS `ml__jurisdicciones_tipos`;
DROP TABLE IF EXISTS `ml__ambitos_organizacionales`;
-- 10 tablas que el modelo nuevo redefine: se borran solo si siguen con la forma del prototipo
CALL _sync_recrear('ml__ambitos_tipos', 'codigo', 0);
CALL _sync_recrear('ml__entidades', 'descripcion', 0);
CALL _sync_recrear('ml__entidades_tipos', 'grupo_id', 0);
CALL _sync_recrear('ml__normas', 'norma_jerarquia_id', 0);
CALL _sync_recrear('ml__normas_tipos', 'codigo', 0);
CALL _sync_recrear('ml__obligaciones', 'empresa_id', 0);
CALL _sync_recrear('ml__obligaciones_jurisdicciones', 'territorio_id', 0);
CALL _sync_recrear('ml__obligaciones_tipos_entidades', 'regla_json', 0);
CALL _sync_recrear('ml__procedimientos', 'empresa_id', 0);
CALL _sync_recrear('ml__procedimientos_instrucciones', 'instruccion_id', 0);

-- ---------------------------------------------------------------------
-- 2) Tablas vacias con la forma vieja (accesos por sucursal, imputaciones)
-- ---------------------------------------------------------------------
CALL _sync_recrear('gestion__comprobantes_imputaciones', 'f_imputacion', 1);
CALL _sync_recrear('gestion__perfiles_sucursales', 'empresa_id', 1);
CALL _sync_recrear('gestion__usuarios_sucursales_excepciones', 'empresa_id', 1);

-- ---------------------------------------------------------------------
-- 3) Stock por boca: resguardo de las tablas viejas (20261008_stock_01_ddl.sql)
-- ---------------------------------------------------------------------
CALL _sync_renombrar('gestion__stock', 'gestion__stock_bak20261008', 'deposito_id');
CALL _sync_renombrar('gestion__stock_movimientos', 'gestion__stock_movimientos_bak20261008', 'stock_movimiento_tipo_id');
CALL _sync_renombrar('gestion__stock_movimientos_detalles', 'gestion__stock_movimientos_detalles_bak20261008', '');
CALL _sync_renombrar('gestion__stock_movimientos_tipos', 'gestion__stock_movimientos_tipos_bak20261008', '');

-- ---------------------------------------------------------------------
-- 4) Tablas nuevas (80)
-- ---------------------------------------------------------------------

-- afip__condiciones_iva
CREATE TABLE IF NOT EXISTS `afip__condiciones_iva` (
  `condicion_iva_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `codigo_afip` tinyint(3) unsigned DEFAULT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abreviatura` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discrimina_iva` tinyint(1) NOT NULL DEFAULT 0,
  `emite_letra_default` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`condicion_iva_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- conf__bancos
CREATE TABLE IF NOT EXISTS `conf__bancos` (
  `banco_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `codigo_bcra` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo de entidad BCRA (3 digitos = primeros 3 del CBU)',
  `banco` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banco_abreviado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuit` bigint(20) unsigned DEFAULT NULL,
  `orden` smallint(5) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`banco_id`),
  UNIQUE KEY `uk_bco_codigo_bcra` (`codigo_bcra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__calendario_vencimientos
CREATE TABLE IF NOT EXISTS `estudio__calendario_vencimientos` (
  `calendario_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `impuesto_obligacion_id` int(10) unsigned NOT NULL,
  `jurisdiccion_id` int(10) unsigned DEFAULT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `mes_periodo` tinyint(3) unsigned NOT NULL COMMENT '1-12',
  `terminacion_cuit` tinyint(4) NOT NULL COMMENT '0 a 9 (o -1 si es general sin distincion)',
  `f_vencimiento` date NOT NULL,
  `f_vencimiento_prorroga` date DEFAULT NULL,
  PRIMARY KEY (`calendario_id`),
  KEY `idx_busqueda_calendario` (`impuesto_obligacion_id`,`jurisdiccion_id`,`anio`,`mes_periodo`,`terminacion_cuit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__clientes
CREATE TABLE IF NOT EXISTS `estudio__clientes` (
  `cliente_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant Estudio Contable propietario',
  `cliente_tipo_id` int(10) unsigned NOT NULL,
  `razon_social` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuit` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `terminacion_cuit` tinyint(4) NOT NULL COMMENT 'Calculado 0-9 para calendarios fiscales',
  `condicion_iva_id` int(10) unsigned NOT NULL COMMENT 'FK gestion__entidades_condiciones_fiscales',
  `empresa_vinculada_id` int(10) unsigned DEFAULT NULL COMMENT 'FK conf__empresas si el cliente usa el ERP',
  `mes_cierre_ejercicio` tinyint(4) NOT NULL DEFAULT 12,
  `ingresos_brutos_tipo` enum('LOCAL','CONVENIO_MULTILATERAL','EXENTO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LOCAL',
  `jurisdiccion_sede_id` int(10) unsigned DEFAULT NULL COMMENT 'FK gestion__jurisdicciones sede',
  `es_empleador` tinyint(1) NOT NULL DEFAULT 0,
  `cantidad_empleados` int(10) unsigned NOT NULL DEFAULT 0,
  `usuario_r_defecto_id` int(10) unsigned DEFAULT NULL COMMENT 'Usuario Responsable asignado en conf__usuarios',
  `usuario_a_defecto_id` int(10) unsigned DEFAULT NULL COMMENT 'Usuario Accountable/Supervisor en conf__usuarios',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cliente_id`),
  KEY `idx_empresa_cuit` (`empresa_id`,`cuit`),
  KEY `idx_empresa_terminacion` (`empresa_id`,`terminacion_cuit`),
  KEY `idx_empresa_vinculada` (`empresa_vinculada_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__clientes_abonos
CREATE TABLE IF NOT EXISTS `estudio__clientes_abonos` (
  `abono_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Estudio Contable',
  `cliente_id` bigint(20) unsigned NOT NULL,
  `concepto_id` bigint(20) unsigned NOT NULL,
  `punto_venta_id` int(10) unsigned NOT NULL COMMENT 'FK gestion__comprobantes_numeradores del estudio',
  `condicion_venta_id` int(10) unsigned NOT NULL DEFAULT 1,
  `importe_base` decimal(14,2) NOT NULL DEFAULT 0.00,
  `dia_facturacion` tinyint(4) NOT NULL DEFAULT 1,
  `aplica_adicionales_automaticos` tinyint(1) NOT NULL DEFAULT 0,
  `tope_comprobantes_incluidos` int(10) unsigned NOT NULL DEFAULT 0,
  `precio_excedente_comprobante` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tope_legajos_incluidos` int(10) unsigned NOT NULL DEFAULT 0,
  `precio_excedente_legajo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ultimo_periodo_facturado` int(10) unsigned DEFAULT NULL COMMENT 'YYYYMM',
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`abono_id`),
  KEY `idx_empresa_cliente` (`empresa_id`,`cliente_id`),
  KEY `idx_concepto` (`concepto_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__clientes_jurisdicciones
CREATE TABLE IF NOT EXISTS `estudio__clientes_jurisdicciones` (
  `cliente_jurisdiccion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `jurisdiccion_id` int(10) unsigned NOT NULL COMMENT 'FK gestion__jurisdicciones (901 CABA, 902 BA, etc.)',
  `tipo_inscripcion` enum('SEDE_PRINCIPAL','EXTRAÑA_JURISDICCION','REGIMEN_SIMPLIFICADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EXTRAÑA_JURISDICCION',
  `numero_inscripcion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coeficiente_cm05` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `es_agente_retencion` tinyint(1) NOT NULL DEFAULT 0,
  `es_agente_percepcion` tinyint(1) NOT NULL DEFAULT 0,
  `f_alta` date NOT NULL,
  `f_baja` date DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`cliente_jurisdiccion_id`),
  UNIQUE KEY `uk_cliente_jurisdiccion` (`cliente_id`,`jurisdiccion_id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__clientes_obligaciones
CREATE TABLE IF NOT EXISTS `estudio__clientes_obligaciones` (
  `cliente_obligacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `impuesto_obligacion_id` int(10) unsigned NOT NULL,
  `jurisdiccion_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL si es ARCA nacional',
  `cbu_pago` varchar(22) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_pago_defecto` enum('VEP','DEBITO_DIRECTO','BANCO','EFECTIVO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VEP',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`cliente_obligacion_id`),
  UNIQUE KEY `uk_cli_obliga_juris` (`cliente_id`,`impuesto_obligacion_id`,`jurisdiccion_id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_obligacion` (`impuesto_obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__clientes_tipos
CREATE TABLE IF NOT EXISTS `estudio__clientes_tipos` (
  `cliente_tipo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cliente_tipo_id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__honorarios_conceptos
CREATE TABLE IF NOT EXISTS `estudio__honorarios_conceptos` (
  `concepto_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `producto_id` bigint(20) unsigned NOT NULL COMMENT 'FK gestion__productos (servicio base para ventas_facturas)',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_cargo` enum('FIJO_MENSUAL','POR_COMPROBANTE','POR_LEGAJO','POR_HORA','EVENTUAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FIJO_MENSUAL',
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`concepto_id`),
  KEY `idx_empresa_producto` (`empresa_id`,`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__honorarios_novedades
CREATE TABLE IF NOT EXISTS `estudio__honorarios_novedades` (
  `novedad_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `concepto_id` bigint(20) unsigned NOT NULL,
  `periodo` int(10) unsigned NOT NULL COMMENT 'YYYYMM a facturar',
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `importe_unitario` decimal(14,2) NOT NULL,
  `importe_total` decimal(14,2) NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `facturado` tinyint(1) NOT NULL DEFAULT 0,
  `venta_factura_id` bigint(20) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`novedad_id`),
  KEY `idx_empresa_periodo` (`empresa_id`,`periodo`,`cliente_id`),
  KEY `idx_facturado` (`facturado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__honorarios_tarifarios
CREATE TABLE IF NOT EXISTS `estudio__honorarios_tarifarios` (
  `tarifario_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `moneda_id` int(10) unsigned NOT NULL DEFAULT 1,
  `vigencia_desde` date NOT NULL,
  `vigencia_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`tarifario_id`),
  KEY `idx_empresa_vigencia` (`empresa_id`,`vigencia_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__honorarios_tarifarios_detalles
CREATE TABLE IF NOT EXISTS `estudio__honorarios_tarifarios_detalles` (
  `tarifario_detalle_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tarifario_id` bigint(20) unsigned NOT NULL,
  `concepto_id` bigint(20) unsigned NOT NULL,
  `importe_unitario` decimal(14,2) NOT NULL DEFAULT 0.00,
  `unidad_medida` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ARS, Modulos CPCECABA, Modulos FACPCE',
  PRIMARY KEY (`tarifario_detalle_id`),
  UNIQUE KEY `uk_tarifario_concepto` (`tarifario_id`,`concepto_id`),
  KEY `idx_concepto` (`concepto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__impuestos_obligaciones
CREATE TABLE IF NOT EXISTS `estudio__impuestos_obligaciones` (
  `impuesto_obligacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organismo` enum('ARCA','COMARB','PROVINCIAL','MUNICIPAL','SINDICATO','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_oficial` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: IVA, F931, CM03, CM05, ARBA_LOC',
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `frecuencia` enum('MENSUAL','BIMESTRAL','CUATRIMESTRAL','ANUAL','EVENTUAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MENSUAL',
  `aplica_por_cuit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si rige segun terminacion CUIT 0-9',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`impuesto_obligacion_id`),
  UNIQUE KEY `uk_codigo_organismo` (`organismo`,`codigo_oficial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__licencias
CREATE TABLE IF NOT EXISTS `estudio__licencias` (
  `licencia_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL COMMENT 'Colaborador titular que se ausenta',
  `tipo_licencia` enum('VACACIONES','MEDICA','ESTUDIO','OTRA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VACACIONES',
  `f_desde` date NOT NULL,
  `f_hasta` date NOT NULL,
  `usuario_suplente_r_id` int(10) unsigned DEFAULT NULL COMMENT 'Suplente por defecto para tareas R',
  `usuario_suplente_a_id` int(10) unsigned DEFAULT NULL COMMENT 'Suplente por defecto para tareas A',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 4,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`licencia_id`),
  KEY `idx_empresa_fechas` (`empresa_id`,`f_desde`,`f_hasta`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__licencias_delegaciones
CREATE TABLE IF NOT EXISTS `estudio__licencias_delegaciones` (
  `delegacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `licencia_id` bigint(20) unsigned NOT NULL,
  `tarea_id` bigint(20) unsigned NOT NULL,
  `rol_delegado` enum('R','A') COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_titular_id` int(10) unsigned NOT NULL,
  `usuario_suplente_id` int(10) unsigned NOT NULL,
  `f_traspaso` datetime DEFAULT CURRENT_TIMESTAMP,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '1=Vigente, 2=Revertida',
  PRIMARY KEY (`delegacion_id`),
  KEY `idx_tarea` (`tarea_id`),
  KEY `idx_suplente` (`usuario_suplente_id`),
  KEY `idx_licencia` (`licencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__minigestion_comprobantes
CREATE TABLE IF NOT EXISTS `estudio__minigestion_comprobantes` (
  `comprobante_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant del estudio contable',
  `cliente_id` bigint(20) unsigned NOT NULL COMMENT 'FK estudio__clientes.cliente_id',
  `tipo_operacion` enum('VENTA','COMPRA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_emision` date NOT NULL,
  `periodo_fiscal` int(10) unsigned NOT NULL COMMENT 'YYYYMM',
  `comprobante_tipo_id` int(10) unsigned NOT NULL DEFAULT 4 COMMENT 'FK gestion__comprobantes_tipos',
  `comprobante_letra` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'A',
  `punto_venta` int(10) unsigned NOT NULL DEFAULT 1,
  `comprobante_nro` bigint(20) unsigned NOT NULL DEFAULT 1,
  `cuit_contraparte` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social_contraparte` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `neto_gravado` decimal(16,2) NOT NULL DEFAULT 0.00,
  `alicuota_iva` decimal(5,2) NOT NULL DEFAULT 21.00,
  `importe_iva` decimal(16,2) NOT NULL DEFAULT 0.00,
  `no_gravado_exento` decimal(16,2) NOT NULL DEFAULT 0.00,
  `percepcion_iibb` decimal(16,2) NOT NULL DEFAULT 0.00,
  `percepcion_iva` decimal(16,2) NOT NULL DEFAULT 0.00,
  `importe_total` decimal(16,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`comprobante_id`),
  KEY `idx_empresa_cliente_op` (`empresa_id`,`cliente_id`,`tipo_operacion`,`periodo_fiscal`),
  KEY `idx_periodo` (`empresa_id`,`periodo_fiscal`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__normas
CREATE TABLE IF NOT EXISTS `estudio__normas` (
  `norma_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `organismo_emisor` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ARCA, ARBA, AGIP, COMARB, MTSS, UIF',
  `tipo_norma` enum('RESOLUCION_GENERAL','DECRETO','LEY','DISPOSICION','CIRCULAR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RESOLUCION_GENERAL',
  `nro_norma` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_boletin_oficial` date NOT NULL,
  `vigencia_desde` date NOT NULL,
  `vigencia_hasta` date DEFAULT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumen_tecnico` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `genera_nueva_obligacion` tinyint(1) NOT NULL DEFAULT 0,
  `impuesto_obligacion_id` int(10) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 3,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`norma_id`),
  KEY `idx_empresa_vigencia` (`empresa_id`,`vigencia_desde`),
  KEY `idx_organismo` (`organismo_emisor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__normas_clientes_impacto
CREATE TABLE IF NOT EXISTS `estudio__normas_clientes_impacto` (
  `impacto_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `norma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `f_evaluacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado_impacto` enum('ALCANZADO','EXENTO','NOTIFICADO','APLICADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ALCANZADO',
  `tarea_generada_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`impacto_id`),
  UNIQUE KEY `uk_norma_cliente` (`norma_id`,`cliente_id`),
  KEY `idx_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__normas_condiciones
CREATE TABLE IF NOT EXISTS `estudio__normas_condiciones` (
  `condicion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `norma_id` bigint(20) unsigned NOT NULL,
  `parametro` enum('CONDICION_IVA','TIPO_PERSONA','JURISDICCION','ACTIVIDAD_AFIP','EMPLEADOR','VENTAS_ANUALES_MAYOR_A') COLLATE utf8mb4_unicode_ci NOT NULL,
  `operador` enum('=','!=','IN','>=','<=') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '=',
  `valor_esperado` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`condicion_id`),
  KEY `idx_norma` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__tareas
CREATE TABLE IF NOT EXISTS `estudio__tareas` (
  `tarea_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Campo de Proceso 1: Tenant Estudio',
  `sucursal_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2: Sucursal',
  `comprobante_tipo_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo comprobante',
  `comprobante_pv` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta',
  `comprobante_nro` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo',
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes',
  `entidad_id` bigint(20) unsigned NOT NULL COMMENT 'Campo de Proceso 7: FK gestion__entidades (Cliente)',
  `f_emision` date NOT NULL COMMENT 'Campo de Proceso 8: Fecha creacion tarea',
  `cliente_id` bigint(20) unsigned NOT NULL COMMENT 'FK estudio__clientes',
  `vencimiento_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK estudio__vencimientos_generados si aplica',
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_inicio` date NOT NULL,
  `f_limite` date NOT NULL,
  `usuario_r_id` int(10) unsigned NOT NULL COMMENT 'Responsible (conf__usuarios)',
  `usuario_a_id` int(10) unsigned NOT NULL COMMENT 'Accountable (conf__usuarios)',
  `contactos_c` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Consulted',
  `contactos_i` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Informed',
  `horas_estimadas` decimal(5,2) NOT NULL DEFAULT 0.00,
  `horas_reales` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 4,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tarea_id`),
  KEY `idx_empresa_r` (`empresa_id`,`usuario_r_id`),
  KEY `idx_empresa_a` (`empresa_id`,`usuario_a_id`),
  KEY `idx_cliente_vto` (`cliente_id`,`f_limite`),
  KEY `idx_vencimiento` (`vencimiento_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__tareas_plantillas
CREATE TABLE IF NOT EXISTS `estudio__tareas_plantillas` (
  `plantilla_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `impuesto_obligacion_id` int(10) unsigned NOT NULL,
  `nombre_tarea` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` tinyint(4) NOT NULL DEFAULT 1,
  `dias_previos_vto` tinyint(4) NOT NULL DEFAULT 5,
  `horas_estimadas` decimal(5,2) NOT NULL DEFAULT 1.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`plantilla_id`),
  KEY `idx_empresa_obligacion` (`empresa_id`,`impuesto_obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__tareas_tiempos
CREATE TABLE IF NOT EXISTS `estudio__tareas_tiempos` (
  `tiempo_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `tarea_id` bigint(20) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `f_registro` date NOT NULL,
  `minutos` int(10) unsigned NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tiempo_id`),
  KEY `idx_tarea` (`tarea_id`),
  KEY `idx_empresa_usuario` (`empresa_id`,`usuario_id`,`f_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__tasas_municipales
CREATE TABLE IF NOT EXISTS `estudio__tasas_municipales` (
  `tasa_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `municipio_nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tasa_nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Seguridad e Higiene, Red Vial, etc.',
  `nro_cuenta_partida` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dia_vencimiento_fijo` tinyint(4) DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`tasa_id`),
  KEY `idx_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__vencimientos_generados
CREATE TABLE IF NOT EXISTS `estudio__vencimientos_generados` (
  `vencimiento_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Campo de Proceso 1: Tenant Estudio Contable',
  `sucursal_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2: Sucursal del Estudio',
  `comprobante_tipo_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo doc (0 si no emite comprobante fiscal)',
  `comprobante_pv` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta',
  `comprobante_nro` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo',
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes (VEP/Liq)',
  `entidad_id` bigint(20) unsigned NOT NULL COMMENT 'Campo de Proceso 7: FK gestion__entidades (Cliente)',
  `f_emision` date NOT NULL COMMENT 'Campo de Proceso 8: Fecha de alta/generacion',
  `cliente_id` bigint(20) unsigned NOT NULL COMMENT 'FK estudio__clientes',
  `impuesto_obligacion_id` int(10) unsigned NOT NULL,
  `jurisdiccion_id` int(10) unsigned DEFAULT NULL,
  `periodo_fiscal` int(10) unsigned NOT NULL COMMENT 'YYYYMM',
  `f_limite` date NOT NULL,
  `importe_determinado` decimal(14,2) NOT NULL DEFAULT 0.00,
  `importe_saldo_favor` decimal(14,2) NOT NULL DEFAULT 0.00,
  `importe_a_pagar` decimal(14,2) NOT NULL DEFAULT 0.00,
  `vep_nro` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_presentacion` datetime DEFAULT NULL,
  `f_pago` datetime DEFAULT NULL,
  `comprobante_pago_adjunto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 3,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`vencimiento_id`),
  KEY `idx_empresa_periodo` (`empresa_id`,`periodo_fiscal`,`cliente_id`),
  KEY `idx_f_limite` (`f_limite`),
  KEY `idx_estado` (`tabla_estado_registro_id`),
  KEY `idx_comprobante` (`comprobante_id`),
  KEY `idx_entidad` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__vinculaciones
CREATE TABLE IF NOT EXISTS `estudio__vinculaciones` (
  `vinculacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_origen_id` int(10) unsigned NOT NULL COMMENT 'Empresa cliente que otorga el acceso',
  `empresa_estudio_id` int(10) unsigned NOT NULL COMMENT 'Empresa del estudio contable que recibe acceso',
  `token_autorizacion` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_solicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_aprobacion` datetime DEFAULT NULL,
  `usuario_aprobador_id` int(10) unsigned DEFAULT NULL COMMENT 'Usuario administrador de la empresa origen',
  `permiso_ventas` tinyint(1) NOT NULL DEFAULT 1,
  `permiso_compras` tinyint(1) NOT NULL DEFAULT 1,
  `permiso_tesoreria` tinyint(1) NOT NULL DEFAULT 1,
  `permiso_contabilidad` tinyint(1) NOT NULL DEFAULT 1,
  `permiso_stock` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 4,
  PRIMARY KEY (`vinculacion_id`),
  UNIQUE KEY `uk_vinculacion` (`empresa_origen_id`,`empresa_estudio_id`),
  KEY `idx_estudio` (`empresa_estudio_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__activos_fijos
CREATE TABLE IF NOT EXISTS `gestion__activos_fijos` (
  `activo_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Campo de Proceso 1',
  `sucursal_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2',
  `comprobante_tipo_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo comprobante origen',
  `comprobante_pv` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta origen',
  `comprobante_nro` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo origen',
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes (Factura de compra)',
  `entidad_id` bigint(20) unsigned NOT NULL COMMENT 'Campo de Proceso 7: FK gestion__entidades (Proveedor)',
  `f_emision` date NOT NULL COMMENT 'Campo de Proceso 8: Fecha de alta / incorporacion',
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria_activo` enum('INMUEBLE','RODADO','MAQUINARIA','MUEBLES_UTILES','EQUIPO_COMPUTACION','INSTALACION','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_adquisicion` date NOT NULL,
  `valor_origen` decimal(16,2) NOT NULL,
  `valor_residual` decimal(16,2) NOT NULL DEFAULT 0.00,
  `vida_util_meses` int(10) unsigned NOT NULL,
  `meses_amortizados` int(10) unsigned NOT NULL DEFAULT 0,
  `metodo_amortizacion` enum('LINEAL_MENSUAL','LINEAL_ANUAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LINEAL_MENSUAL',
  `cuenta_activo_id` bigint(20) unsigned NOT NULL COMMENT 'FK gestion__cont_cuentas (Bienes de Uso)',
  `cuenta_amort_acum_id` bigint(20) unsigned NOT NULL COMMENT 'FK gestion__cont_cuentas (Regularizadora Activo)',
  `cuenta_gasto_amort_id` bigint(20) unsigned NOT NULL COMMENT 'FK gestion__cont_cuentas (Resultado Negativo)',
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`activo_id`),
  KEY `idx_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `idx_comprobante` (`comprobante_id`),
  KEY `idx_cuentas` (`cuenta_activo_id`,`cuenta_amort_acum_id`,`cuenta_gasto_amort_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__activos_fijos_amortizaciones
CREATE TABLE IF NOT EXISTS `gestion__activos_fijos_amortizaciones` (
  `amortizacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Campo de Proceso 1',
  `sucursal_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2',
  `comprobante_tipo_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo comprobante',
  `comprobante_pv` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta',
  `comprobante_nro` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo',
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes',
  `entidad_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 7: Entidad',
  `f_emision` date NOT NULL COMMENT 'Campo de Proceso 8: Fecha de cálculo',
  `activo_id` bigint(20) unsigned NOT NULL COMMENT 'FK gestion__activos_fijos',
  `periodo` int(10) unsigned NOT NULL COMMENT 'YYYYMM devengado',
  `cuota_nro` int(10) unsigned NOT NULL,
  `importe_amortizacion` decimal(14,2) NOT NULL,
  `asiento_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK gestion__cont_asientos (generado con origen AUTO)',
  `f_asiento` date NOT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 3,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`amortizacion_id`),
  KEY `idx_empresa_periodo` (`empresa_id`,`periodo`,`activo_id`),
  KEY `idx_activo` (`activo_id`),
  KEY `idx_asiento` (`asiento_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__categorias_gasto
CREATE TABLE IF NOT EXISTS `gestion__categorias_gasto` (
  `categoria_gasto_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'COMBUSTIBLE, VIATICOS, PEAJE, COMIDAS, MATERIALES, CORREO, OTROS',
  `categoria_gasto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta de gasto por defecto (se copia en el item)',
  `requiere_comprobante` tinyint(1) NOT NULL DEFAULT 1,
  `requiere_justificacion` tinyint(1) NOT NULL DEFAULT 0,
  `orden` smallint(5) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`categoria_gasto_id`),
  UNIQUE KEY `uk_cgas_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `ix_cgas_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_cgas_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cheques
CREATE TABLE IF NOT EXISTS `gestion__cheques` (
  `cheque_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `cheque_tipo` enum('PROPIO','TERCERO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_echeq` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = cheque electronico (ECHEQ)',
  `banco_id` smallint(5) unsigned NOT NULL COMMENT 'Banco girado',
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL COMMENT 'PROPIO: cuenta BANCO librada; TERCERO: cuenta VALORES en custodia',
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_emision` date NOT NULL,
  `f_pago` date NOT NULL COMMENT 'Fecha desde la que puede cobrarse',
  `es_diferido` tinyint(1) GENERATED ALWAYS AS (case when `f_pago` > `f_emision` then 1 else 0 end) STORED,
  `moneda_id` smallint(5) unsigned NOT NULL,
  `importe` decimal(18,2) NOT NULL,
  `librador_nombre` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `librador_cuit` bigint(20) unsigned DEFAULT NULL COMMENT 'Mismo tipo que gestion__entidades.cuit',
  `entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'TERCERO: quien lo entrego (cliente); PROPIO: beneficiario',
  `endosable` tinyint(1) NOT NULL DEFAULT 1,
  `comp_origen_id` bigint(20) unsigned NOT NULL COMMENT 'Comprobante que lo origino (RCX al recibir, OP al emitir)',
  `comp_destino_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Ultimo comprobante de salida (DB deposito/debito, OP endoso, ND_CHR rechazo); historia completa en gestion__cheques_operaciones_items',
  `motivo_rechazo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_propio_cuenta_id` int(10) unsigned GENERATED ALWAYS AS (case when `cheque_tipo` = 'PROPIO' then `tesoreria_cuenta_id` end) STORED COMMENT 'Solo para uk_chq_propio',
  `clave_tercero_empresa_id` int(11) GENERATED ALWAYS AS (case when `cheque_tipo` = 'TERCERO' then `empresa_id` end) STORED COMMENT 'Solo para uk_chq_tercero',
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`cheque_id`),
  UNIQUE KEY `uk_chq_propio` (`clave_propio_cuenta_id`,`numero`),
  UNIQUE KEY `uk_chq_tercero` (`clave_tercero_empresa_id`,`banco_id`,`librador_cuit`,`numero`),
  KEY `ix_chq_empresa_estado` (`empresa_id`,`tabla_estado_registro_id`),
  KEY `ix_chq_empresa_f_pago` (`empresa_id`,`f_pago`),
  KEY `ix_chq_sucursal` (`sucursal_id`),
  KEY `ix_chq_banco` (`banco_id`),
  KEY `ix_chq_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_chq_moneda` (`moneda_id`),
  KEY `ix_chq_entidad` (`entidad_id`),
  KEY `ix_chq_comp_origen` (`comp_origen_id`),
  KEY `ix_chq_comp_destino` (`comp_destino_id`),
  CONSTRAINT `fk_chq_banco_id` FOREIGN KEY (`banco_id`) REFERENCES `conf__bancos` (`banco_id`),
  CONSTRAINT `fk_chq_comp_destino_id` FOREIGN KEY (`comp_destino_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_chq_comp_origen_id` FOREIGN KEY (`comp_origen_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_chq_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_chq_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_chq_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_chq_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cheques_operaciones
CREATE TABLE IF NOT EXISTS `gestion__cheques_operaciones` (
  `cheque_operacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL COMMENT 'DB (deposito/debito), ND_CHR (rechazo/devolucion de tercero), DEV_END (devolucion de endoso) o CHP_RECH (rechazo de cheque propio)',
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (DB / ND_CHR / DEV_END / CHP_RECH). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'RECHAZO de tercero/DEVOLUCION: cliente; RECHAZO de propio (CHP_RECH) y DEVOLUCION_ENDOSO: proveedor; DEPOSITO/DEBITO: NULL',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `operacion` enum('DEPOSITO','DEBITO','RECHAZO','DEVOLUCION','DEVOLUCION_ENDOSO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta afectada (ver tabla de operaciones arriba)',
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'SUM(items.importe) (+ gastos de rechazo si se cobran al cliente)',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motivo de rechazo informado por el banco',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`cheque_operacion_id`),
  KEY `ix_chop_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `ix_chop_comprobante` (`comprobante_id`),
  KEY `ix_chop_estado` (`tabla_estado_registro_id`),
  KEY `ix_chop_sucursal` (`sucursal_id`),
  KEY `ix_chop_tipo` (`comprobante_tipo_id`),
  KEY `ix_chop_pv` (`comprobante_pv`),
  KEY `ix_chop_entidad` (`entidad_id`),
  KEY `ix_chop_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_chop_moneda` (`moneda_id`),
  CONSTRAINT `fk_chop_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_chop_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_chop_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_chop_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_chop_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_chop_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cheques_operaciones_items
CREATE TABLE IF NOT EXISTS `gestion__cheques_operaciones_items` (
  `cheque_operacion_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cheque_operacion_id` int(10) unsigned NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `cheque_id` int(10) unsigned NOT NULL,
  `importe` decimal(18,2) NOT NULL,
  `cheque_estado_previo_id` smallint(6) DEFAULT NULL COMMENT 'tabla_estado_registro_id del cheque antes de la operacion (para revertir al anular)',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`cheque_operacion_item_id`),
  UNIQUE KEY `uk_chopi_operacion_cheque` (`cheque_operacion_id`,`cheque_id`),
  KEY `ix_chopi_cheque` (`cheque_id`),
  CONSTRAINT `fk_chopi_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_chopi_cheque_operacion_id` FOREIGN KEY (`cheque_operacion_id`) REFERENCES `gestion__cheques_operaciones` (`cheque_operacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cobranzas
CREATE TABLE IF NOT EXISTS `gestion__cobranzas` (
  `cobranza_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (RCX). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned DEFAULT NULL,
  `entidad_sucursal_id` int(11) DEFAULT NULL,
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`cobranza_id`),
  KEY `ix_cob_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `ix_cob_entidad` (`entidad_id`),
  KEY `ix_cob_comprobante` (`comprobante_id`),
  KEY `ix_cob_estado` (`tabla_estado_registro_id`),
  KEY `ix_cob_sucursal` (`sucursal_id`),
  KEY `ix_cob_tipo` (`comprobante_tipo_id`),
  KEY `ix_cob_pv` (`comprobante_pv`),
  KEY `ix_cob_moneda` (`moneda_id`),
  CONSTRAINT `fk_cob_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_cob_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_cob_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_cob_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_cob_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cobranzas_medios
CREATE TABLE IF NOT EXISTS `gestion__cobranzas_medios` (
  `cobranza_medio_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cobranza_id` int(10) unsigned NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION',
  `moneda_id` smallint(5) unsigned NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` decimal(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` decimal(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` int(10) unsigned DEFAULT NULL,
  `nro_operacion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Transferencia / deposito',
  `f_valor` date DEFAULT NULL COMMENT 'Fecha de acreditacion (transferencia, tarjeta)',
  `tarjeta_marca` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_terminal` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_lote` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cupon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_autorizacion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cuotas` tinyint(3) unsigned DEFAULT NULL,
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL COMMENT 'Retencion',
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'Retencion IIBB',
  `retencion_certificado_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retencion_base_imponible` decimal(18,2) DEFAULT NULL,
  `retencion_alicuota` decimal(10,6) DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`cobranza_medio_id`),
  KEY `ix_cobm_cobranza` (`cobranza_id`),
  KEY `ix_cobm_medio_pago` (`medio_pago_id`),
  KEY `ix_cobm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_cobm_moneda` (`moneda_id`),
  KEY `ix_cobm_cheque` (`cheque_id`),
  KEY `ix_cobm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_cobm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_cobm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_cobm_cobranza_id` FOREIGN KEY (`cobranza_id`) REFERENCES `gestion__cobranzas` (`cobranza_id`),
  CONSTRAINT `fk_cobm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_cobm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_cobm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_cobm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_cobm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cobranzas_retenciones
CREATE TABLE IF NOT EXISTS `gestion__cobranzas_retenciones` (
  `cobranza_retencion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL,
  `tipo_retencion` enum('GANANCIAS','IVA','IIBB','SUSS','OTRA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jurisdiccion_id` int(10) unsigned DEFAULT NULL,
  `nro_certificado` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_retencion` date NOT NULL,
  `importe_base` decimal(14,2) NOT NULL DEFAULT 0.00,
  `alicuota` decimal(5,2) NOT NULL DEFAULT 0.00,
  `importe_retenido` decimal(14,2) NOT NULL,
  `cuenta_contable_id` bigint(20) unsigned NOT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cobranza_retencion_id`),
  KEY `idx_empresa_comprobante` (`empresa_id`,`comprobante_id`),
  KEY `idx_tipo_jurisdiccion` (`tipo_retencion`,`jurisdiccion_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__comprobantes_imputaciones
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_imputaciones` (
  `imputacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `comp_origen_id` bigint(20) unsigned NOT NULL COMMENT 'Comprobante que cancela (RCX, OP, NC, I_PAG, RG_ANT, RG_REEM)',
  `comp_destino_id` bigint(20) unsigned NOT NULL COMMENT 'Comprobante cancelado (FA, ND, ND_CHR, I_LIQ, RG_REND, RG_ANT)',
  `f_imputacion` date NOT NULL COMMENT 'Fecha de aplicacion (puede ser posterior a la del origen)',
  `importe_imputado` decimal(18,2) NOT NULL COMMENT 'Siempre > 0, en moneda del comprobante DESTINO',
  `moneda_origen_id` smallint(5) unsigned NOT NULL COMMENT 'Moneda del comprobante origen',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'importe_imputado = importe_origen * tipo_cambio',
  `importe_origen` decimal(18,2) NOT NULL COMMENT 'Siempre > 0, en moneda del comprobante ORIGEN',
  `f_anulacion` date DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL COMMENT 'Borrador / Vigente (CONFIRMADO) / Anulada',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`imputacion_id`),
  KEY `ix_imp_empresa_destino` (`empresa_id`,`comp_destino_id`),
  KEY `ix_imp_origen` (`comp_origen_id`),
  KEY `ix_imp_destino_estado` (`comp_destino_id`,`tabla_estado_registro_id`),
  KEY `ix_imp_moneda_origen` (`moneda_origen_id`),
  CONSTRAINT `fk_imp_comp_destino_id` FOREIGN KEY (`comp_destino_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_imp_comp_origen_id` FOREIGN KEY (`comp_origen_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_imp_moneda_origen_id` FOREIGN KEY (`moneda_origen_id`) REFERENCES `gestion__monedas` (`moneda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__comprobantes_relacion_fiscal
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_relacion_fiscal` (
  `relacion_fiscal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `condicion_fiscal_emisor_id` tinyint(3) unsigned NOT NULL,
  `condicion_fiscal_receptor_id` tinyint(3) unsigned NOT NULL,
  `comprobante_fiscal_id` smallint(5) unsigned NOT NULL,
  `discrimina_iva` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`relacion_fiscal_id`),
  UNIQUE KEY `uk_cruce_fiscal` (`condicion_fiscal_emisor_id`,`condicion_fiscal_receptor_id`),
  KEY `fk_rf_emisor` (`condicion_fiscal_emisor_id`),
  KEY `fk_rf_receptor` (`condicion_fiscal_receptor_id`),
  KEY `fk_rf_fiscal` (`comprobante_fiscal_id`),
  CONSTRAINT `fk_rf_emisor` FOREIGN KEY (`condicion_fiscal_emisor_id`) REFERENCES `gestion__condiciones_fiscales` (`condicion_fiscal_id`),
  CONSTRAINT `fk_rf_fiscal` FOREIGN KEY (`comprobante_fiscal_id`) REFERENCES `gestion__comprobantes_fiscales` (`comprobante_fiscal_id`),
  CONSTRAINT `fk_rf_receptor` FOREIGN KEY (`condicion_fiscal_receptor_id`) REFERENCES `gestion__condiciones_fiscales` (`condicion_fiscal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__comprobantes_tipos_cancelaciones
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_tipos_cancelaciones` (
  `comprobante_tipo_cancelacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `comprobante_tipo_origen_id` int(10) unsigned NOT NULL COMMENT 'Tipo que cancela (RCX, OP, NC, I_PAG, RG_ANT, RG_REEM)',
  `comprobante_tipo_destino_id` int(10) unsigned NOT NULL COMMENT 'Tipo cancelado (FA, ND, ND_CHR, I_LIQ, RG_REND, RG_ANT)',
  `exige_misma_entidad` tinyint(1) NOT NULL DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`comprobante_tipo_cancelacion_id`),
  UNIQUE KEY `uk_ctc_empresa_origen_destino` (`empresa_id`,`comprobante_tipo_origen_id`,`comprobante_tipo_destino_id`),
  KEY `ix_ctc_origen` (`comprobante_tipo_origen_id`),
  KEY `ix_ctc_destino` (`comprobante_tipo_destino_id`),
  CONSTRAINT `fk_ctc_comprobante_tipo_destino_id` FOREIGN KEY (`comprobante_tipo_destino_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_ctc_comprobante_tipo_origen_id` FOREIGN KEY (`comprobante_tipo_origen_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__comprobantes_tipos_dependencias
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_tipos_dependencias` (
  `comprobante_tipo_dependencia_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL DEFAULT 0,
  `comprobante_tipo_id` int(10) unsigned NOT NULL COMMENT 'Tipo dependiente (NC, ND)',
  `comprobante_tipo_padre_id` int(10) unsigned NOT NULL COMMENT 'Tipo previo del que puede depender (FA, ND, NC)',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`comprobante_tipo_dependencia_id`),
  UNIQUE KEY `uk_ctd_empresa_tipo_padre` (`empresa_id`,`comprobante_tipo_id`,`comprobante_tipo_padre_id`),
  KEY `ix_ctd_tipo` (`comprobante_tipo_id`),
  KEY `ix_ctd_padre` (`comprobante_tipo_padre_id`),
  CONSTRAINT `fk_ctd_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_ctd_comprobante_tipo_padre_id` FOREIGN KEY (`comprobante_tipo_padre_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `ck_ctd_no_autorreferencia` CHECK (`comprobante_tipo_id` <> `comprobante_tipo_padre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cont_cierres_ejercicios
CREATE TABLE IF NOT EXISTS `gestion__cont_cierres_ejercicios` (
  `cierre_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `anio_ejercicio` smallint(5) unsigned NOT NULL,
  `f_inicio` date NOT NULL,
  `f_cierre` date NOT NULL,
  `asiento_refundicion_id` bigint(20) unsigned NOT NULL,
  `asiento_cierre_patrimonial_id` bigint(20) unsigned NOT NULL,
  `asiento_apertura_id` bigint(20) unsigned DEFAULT NULL,
  `resultado_neto_ejercicio` decimal(16,2) NOT NULL,
  `usuario_cierre_id` int(10) unsigned NOT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 5,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cierre_id`),
  UNIQUE KEY `uk_empresa_ejercicio` (`empresa_id`,`anio_ejercicio`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cont_parametros
CREATE TABLE IF NOT EXISTS `gestion__cont_parametros` (
  `parametro_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `cuenta_deudores_ventas_id` bigint(20) unsigned NOT NULL,
  `cuenta_ventas_defecto_id` bigint(20) unsigned NOT NULL,
  `cuenta_iva_debito_id` bigint(20) unsigned NOT NULL,
  `cuenta_proveedores_id` bigint(20) unsigned NOT NULL,
  `cuenta_gastos_defecto_id` bigint(20) unsigned NOT NULL,
  `cuenta_iva_credito_id` bigint(20) unsigned NOT NULL,
  `cuenta_caja_defecto_id` bigint(20) unsigned NOT NULL,
  `cuenta_banco_defecto_id` bigint(20) unsigned NOT NULL,
  `cuenta_resultado_ejercicio_id` bigint(20) unsigned NOT NULL,
  `cuenta_resultados_acumulados_id` bigint(20) unsigned NOT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`parametro_id`),
  UNIQUE KEY `uk_empresa_param` (`empresa_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__empresas_cuentas_conceptos
CREATE TABLE IF NOT EXISTS `gestion__empresas_cuentas_conceptos` (
  `empresa_cuenta_concepto_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `concepto` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo del concepto: VALORES_A_PAGAR, EMPLEADOS. Lo siembra el script 02; el ABM no crea conceptos',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta por defecto; NULL hasta configurarla. El ABM valida imputable, activa y de la misma empresa',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`empresa_cuenta_concepto_id`),
  UNIQUE KEY `uk_ecc_empresa_concepto` (`empresa_id`,`concepto`),
  KEY `ix_ecc_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_ecc_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D35/D36: cuentas contables POR DEFECTO de la empresa por concepto. Al armar un asiento: 1) cuenta especifica del maestro (entidad, cuenta de tesoreria, medio de pago, categoria de gasto); 2) este concepto de la empresa; 3) si no hay ninguna, error y el documento no se confirma. Nunca un id de cuenta fijo en el codigo.';

-- gestion__empresas_parametros
CREATE TABLE IF NOT EXISTS `gestion__empresas_parametros` (
  `empresa_parametro_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `condicion_fiscal_id` tinyint(4) unsigned NOT NULL,
  `punto_venta_defecto_id` int(11) DEFAULT NULL,
  `lista_precio_defecto_id` int(11) DEFAULT NULL,
  `condicion_pago_defecto_id` int(11) DEFAULT NULL,
  `ingresos_brutos_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iibb_regimen_id` tinyint(3) unsigned DEFAULT 1,
  `jurisdiccion_sede_id` smallint(5) unsigned DEFAULT 2,
  `inicio_actividades` date DEFAULT NULL,
  `leyenda_fiscal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arca_habilitado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = las facturas fiscales se confirman reci├®n con el CAE de ARCA',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`empresa_parametro_id`),
  KEY `idx_empresa_vigencia` (`empresa_id`,`f_hasta`,`tabla_estado_registro_id`),
  KEY `idx_condicion_fiscal` (`condicion_fiscal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__entidades_condiciones_iibb
CREATE TABLE IF NOT EXISTS `gestion__entidades_condiciones_iibb` (
  `entidad_condicion_iibb_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entidad_id` int(10) unsigned NOT NULL,
  `regimen_iibb_id` tinyint(3) unsigned NOT NULL,
  `numero_inscripcion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jurisdiccion_sede_id` int(10) unsigned DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entidad_condicion_iibb_id`),
  KEY `idx_entidad_iibb` (`entidad_id`,`tabla_estado_registro_id`,`f_desde`,`f_hasta`),
  KEY `fk_entidad_iibb_regimen` (`regimen_iibb_id`),
  KEY `fk_entidad_iibb_sede` (`jurisdiccion_sede_id`),
  CONSTRAINT `fk_entidad_iibb_regimen` FOREIGN KEY (`regimen_iibb_id`) REFERENCES `gestion__regimenes_iibb` (`regimen_iibb_id`),
  CONSTRAINT `fk_entidad_iibb_sede` FOREIGN KEY (`jurisdiccion_sede_id`) REFERENCES `gestion__iibb_jurisdicciones_sedes` (`jurisdiccion_sede_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__iibb_jurisdicciones_sedes
CREATE TABLE IF NOT EXISTS `gestion__iibb_jurisdicciones_sedes` (
  `jurisdiccion_sede_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo_comarb` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jurisdiccion_sede` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organismo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provincia_id` int(10) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`jurisdiccion_sede_id`),
  UNIQUE KEY `uk_codigo_comarb` (`codigo_comarb`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__iibb_regimenes
CREATE TABLE IF NOT EXISTS `gestion__iibb_regimenes` (
  `iibb_regimen_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `iibb_regimen_codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iibb_regimen_nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` tinyint(3) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`iibb_regimen_id`),
  UNIQUE KEY `uk_regimen_cod` (`iibb_regimen_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__medios_pago
CREATE TABLE IF NOT EXISTS `gestion__medios_pago` (
  `medio_pago_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'EFECTIVO, CHQ_TERCERO, CHQ_PROPIO, TRANSFER, TARJ_DEB, RET_IIBB...',
  `medio_pago` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptor visible',
  `medio_pago_tipo` enum('EFECTIVO','CHEQUE_PROPIO','CHEQUE_TERCERO','TRANSFERENCIA','DEPOSITO','TARJETA_DEBITO','TARJETA_CREDITO','RETENCION','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tesoreria_cuenta_tipo` enum('CAJA','BANCO','VALORES','CUPONES') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tipo de cuenta admitida en las lineas con este medio; NULL = no mueve tesoreria (RETENCION)',
  `tesoreria_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta sugerida por defecto',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta contable cuando el medio no mueve tesoreria (RETENCION)',
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL COMMENT 'Solo RETENCION',
  `requiere_detalle` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = pide datos adicionales (cheque, tarjeta, transferencia, certificado)',
  `aplica_cobro` tinyint(1) NOT NULL DEFAULT 1,
  `aplica_pago` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`medio_pago_id`),
  UNIQUE KEY `uk_mpag_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `ix_mpag_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_mpag_cont_cuenta` (`cont_cuenta_id`),
  KEY `ix_mpag_impuesto_tipo` (`impuesto_tipo_id`),
  CONSTRAINT `fk_mpag_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_mpag_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_mpag_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__movimientos_caja
CREATE TABLE IF NOT EXISTS `gestion__movimientos_caja` (
  `movimiento_caja_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (MC). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'Contraparte opcional (NULL -> 0 en el comprobante)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `tipo_movimiento` enum('INGRESO','EGRESO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL,
  `medio_pago_id` int(10) unsigned DEFAULT NULL,
  `categoria_gasto_id` int(10) unsigned DEFAULT NULL COMMENT 'Solo egresos por gasto',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Contrapartida contable (obligatoria al confirmar)',
  `concepto` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`movimiento_caja_id`),
  KEY `ix_mcj_empresa_fecha` (`empresa_id`,`f_emision`),
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
  CONSTRAINT `fk_mcj_categoria_gasto_id` FOREIGN KEY (`categoria_gasto_id`) REFERENCES `gestion__categorias_gasto` (`categoria_gasto_id`),
  CONSTRAINT `fk_mcj_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_mcj_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_mcj_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_mcj_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_mcj_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_mcj_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_mcj_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_mcj_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__obligaciones_impositivas
CREATE TABLE IF NOT EXISTS `gestion__obligaciones_impositivas` (
  `obligacion_impositiva_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (I_LIQ). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned NOT NULL COMMENT 'Organismo fiscal (rol ORG)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `f_vto` date NOT NULL COMMENT 'Primer vencimiento',
  `f_vto_2` date DEFAULT NULL COMMENT 'Segundo vencimiento (con recargo)',
  `impuesto_tipo_id` smallint(5) unsigned NOT NULL COMMENT 'gestion__impuestos_tipos con es_liquidable = 1',
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'IIBB / tasas: jurisdiccion; NULL nacionales',
  `periodo_fiscal` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM o YYYY',
  `cuota_nro` tinyint(3) unsigned DEFAULT NULL COMMENT 'Anticipo / cuota de plan',
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IVA marzo 2026, Anticipo 1 Ganancias...',
  `nro_ddjj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_vep` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `obligacion_rectificada_id` int(10) unsigned DEFAULT NULL COMMENT 'DDJJ original que esta rectifica',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'MATERIALIZADO al crear: cuenta "X a pagar" (config empresa o impuestos_tipos)',
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe determinado de la DDJJ',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_impositiva_id`),
  KEY `ix_obli_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `ix_obli_empresa_vto` (`empresa_id`,`f_vto`),
  KEY `ix_obli_impuesto_periodo` (`empresa_id`,`impuesto_tipo_id`,`periodo_fiscal`),
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
  CONSTRAINT `fk_obli_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_obli_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_obli_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_obli_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_obli_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_obli_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_obli_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_obli_obligacion_rectificada_id` FOREIGN KEY (`obligacion_rectificada_id`) REFERENCES `gestion__obligaciones_impositivas` (`obligacion_impositiva_id`),
  CONSTRAINT `fk_obli_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__pagos
CREATE TABLE IF NOT EXISTS `gestion__pagos` (
  `pago_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (OP). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned DEFAULT NULL,
  `entidad_sucursal_id` int(11) DEFAULT NULL,
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`pago_id`),
  KEY `ix_pag_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `ix_pag_entidad` (`entidad_id`),
  KEY `ix_pag_comprobante` (`comprobante_id`),
  KEY `ix_pag_estado` (`tabla_estado_registro_id`),
  KEY `ix_pag_sucursal` (`sucursal_id`),
  KEY `ix_pag_tipo` (`comprobante_tipo_id`),
  KEY `ix_pag_pv` (`comprobante_pv`),
  KEY `ix_pag_moneda` (`moneda_id`),
  CONSTRAINT `fk_pag_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_pag_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_pag_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_pag_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_pag_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__pagos_impuestos
CREATE TABLE IF NOT EXISTS `gestion__pagos_impuestos` (
  `pago_impuesto_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (I_PAG). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned NOT NULL COMMENT 'Organismo fiscal (rol ORG)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `nro_vep` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Plan de pagos, convenio, etc.',
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`pago_impuesto_id`),
  KEY `ix_pimp_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `ix_pimp_entidad` (`entidad_id`),
  KEY `ix_pimp_comprobante` (`comprobante_id`),
  KEY `ix_pimp_estado` (`tabla_estado_registro_id`),
  KEY `ix_pimp_sucursal` (`sucursal_id`),
  KEY `ix_pimp_tipo` (`comprobante_tipo_id`),
  KEY `ix_pimp_pv` (`comprobante_pv`),
  KEY `ix_pimp_moneda` (`moneda_id`),
  CONSTRAINT `fk_pimp_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_pimp_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_pimp_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_pimp_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_pimp_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__pagos_impuestos_medios
CREATE TABLE IF NOT EXISTS `gestion__pagos_impuestos_medios` (
  `pago_impuesto_medio_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pago_impuesto_id` int(10) unsigned NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION (p.ej. saldo a favor / retenciones sufridas computadas)',
  `moneda_id` smallint(5) unsigned NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` decimal(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` decimal(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` int(10) unsigned DEFAULT NULL,
  `nro_operacion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_valor` date DEFAULT NULL,
  `tarjeta_marca` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_terminal` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_lote` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cupon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_autorizacion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cuotas` tinyint(3) unsigned DEFAULT NULL,
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL,
  `jurisdiccion_id` int(11) DEFAULT NULL,
  `retencion_certificado_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retencion_base_imponible` decimal(18,2) DEFAULT NULL,
  `retencion_alicuota` decimal(10,6) DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`pago_impuesto_medio_id`),
  KEY `ix_pimpm_pago_impuesto` (`pago_impuesto_id`),
  KEY `ix_pimpm_medio_pago` (`medio_pago_id`),
  KEY `ix_pimpm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_pimpm_moneda` (`moneda_id`),
  KEY `ix_pimpm_cheque` (`cheque_id`),
  KEY `ix_pimpm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_pimpm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_pimpm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_pimpm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_pimpm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_pimpm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_pimpm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_pimpm_pago_impuesto_id` FOREIGN KEY (`pago_impuesto_id`) REFERENCES `gestion__pagos_impuestos` (`pago_impuesto_id`),
  CONSTRAINT `fk_pimpm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__pagos_medios
CREATE TABLE IF NOT EXISTS `gestion__pagos_medios` (
  `pago_medio_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pago_id` int(10) unsigned NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION',
  `moneda_id` smallint(5) unsigned NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` decimal(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` decimal(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` int(10) unsigned DEFAULT NULL COMMENT 'Cheque propio emitido o cheque de tercero endosado',
  `nro_operacion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_valor` date DEFAULT NULL,
  `tarjeta_marca` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_terminal` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_lote` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cupon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_autorizacion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cuotas` tinyint(3) unsigned DEFAULT NULL,
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL,
  `jurisdiccion_id` int(11) DEFAULT NULL,
  `retencion_certificado_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retencion_base_imponible` decimal(18,2) DEFAULT NULL,
  `retencion_alicuota` decimal(10,6) DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`pago_medio_id`),
  KEY `ix_pagm_pago` (`pago_id`),
  KEY `ix_pagm_medio_pago` (`medio_pago_id`),
  KEY `ix_pagm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_pagm_moneda` (`moneda_id`),
  KEY `ix_pagm_cheque` (`cheque_id`),
  KEY `ix_pagm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_pagm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_pagm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_pagm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_pagm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_pagm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_pagm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_pagm_pago_id` FOREIGN KEY (`pago_id`) REFERENCES `gestion__pagos` (`pago_id`),
  CONSTRAINT `fk_pagm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__pagos_retenciones
CREATE TABLE IF NOT EXISTS `gestion__pagos_retenciones` (
  `retencion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `pago_id` int(10) unsigned NOT NULL,
  `impuesto_tipo_id` smallint(5) unsigned NOT NULL COMMENT 'Tipo de retencion practicada (es_retencion = 1, aplica_compra = 1)',
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'IIBB: jurisdiccion; NULL nacionales',
  `regimen` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Regimen o concepto de la retencion',
  `base_imponible` decimal(18,2) NOT NULL DEFAULT 0.00,
  `alicuota` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `importe` decimal(18,2) NOT NULL COMMENT 'Importe retenido en moneda base (pesos)',
  `certificado_nro` int(11) DEFAULT NULL COMMENT 'NULL en Borrador; numeracion propia por empresa e impuesto al confirmar la OP',
  `comp_liquidacion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'I_LIQ que la incluyo; NULL = pendiente de liquidar',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`retencion_id`),
  UNIQUE KEY `uk_pret_certificado` (`empresa_id`,`impuesto_tipo_id`,`certificado_nro`),
  KEY `ix_pret_pendientes` (`empresa_id`,`impuesto_tipo_id`,`comp_liquidacion_id`),
  KEY `ix_pret_pago` (`pago_id`),
  KEY `ix_pret_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_pret_jurisdiccion` (`jurisdiccion_id`),
  KEY `ix_pret_comp_liquidacion` (`comp_liquidacion_id`),
  CONSTRAINT `fk_pret_comp_liquidacion_id` FOREIGN KEY (`comp_liquidacion_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_pret_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_pret_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_pret_pago_id` FOREIGN KEY (`pago_id`) REFERENCES `gestion__pagos` (`pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__perfiles_sucursales
CREATE TABLE IF NOT EXISTS `gestion__perfiles_sucursales` (
  `perfil_sucursal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empresa_perfil_id` int(11) unsigned NOT NULL,
  `sucursal_id` smallint(5) unsigned DEFAULT NULL COMMENT 'NULL = todas las sucursales de la empresa',
  `boca_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = todas las bocas de la sucursal',
  `punto_venta_id` smallint(5) unsigned DEFAULT NULL COMMENT 'NULL = todos los PV del nivel superior',
  `comprobante_tipo_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = todos los tipos habilitados en el PV',
  `es_predeterminado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se preselecciona en los combos',
  `k_sucursal` smallint(5) unsigned GENERATED ALWAYS AS (ifnull(`sucursal_id`,0)) STORED,
  `k_boca` int(10) unsigned GENERATED ALWAYS AS (ifnull(`boca_id`,0)) STORED,
  `k_punto_venta` smallint(5) unsigned GENERATED ALWAYS AS (ifnull(`punto_venta_id`,0)) STORED,
  `k_comprobante_tipo` int(10) unsigned GENERATED ALWAYS AS (ifnull(`comprobante_tipo_id`,0)) STORED,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`perfil_sucursal_id`),
  UNIQUE KEY `uk_ps_grant` (`empresa_perfil_id`,`k_sucursal`,`k_boca`,`k_punto_venta`,`k_comprobante_tipo`),
  KEY `idx_ps_empresa_perfil` (`empresa_id`,`empresa_perfil_id`,`tabla_estado_registro_id`),
  KEY `idx_ps_sucursal` (`sucursal_id`),
  KEY `idx_ps_boca` (`boca_id`),
  KEY `idx_ps_pv` (`punto_venta_id`),
  KEY `idx_ps_tipo` (`comprobante_tipo_id`),
  CONSTRAINT `fk_ps_boca` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_ps_empresa_perfil` FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`),
  CONSTRAINT `fk_ps_pv` FOREIGN KEY (`punto_venta_id`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_ps_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_ps_tipo` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Accesos por perfil a sucursal/boca/PV/tipo. Sin fila = sin acceso.';

-- gestion__regimenes_iibb
CREATE TABLE IF NOT EXISTS `gestion__regimenes_iibb` (
  `regimen_iibb_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `regimen_iibb` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `regimen_iibb_codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `regimen_iibb_descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_jurisdiccion_sede` tinyint(1) NOT NULL DEFAULT 1,
  `requiere_numero_inscripcion` tinyint(1) NOT NULL DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`regimen_iibb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__rendiciones
CREATE TABLE IF NOT EXISTS `gestion__rendiciones` (
  `rendicion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 hasta aprobar; se numera al aprobar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (RG_REND). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned NOT NULL COMMENT 'Empleado (rol EMP)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `periodo` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM de referencia',
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_presentacion` date DEFAULT NULL,
  `f_aprobacion` date DEFAULT NULL,
  `aprobado_por_usuario_id` int(11) DEFAULT NULL,
  `motivo_rechazo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importe_bruto` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_aprobado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_anticipos` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_a_reembolsar` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'aprobado - anticipos aplicados; nunca negativo (D24, recorte al aprobar)',
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe del comprobante RG_REND',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`rendicion_id`),
  KEY `ix_rend_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `ix_rend_empresa_periodo` (`empresa_id`,`periodo`),
  KEY `ix_rend_entidad` (`entidad_id`),
  KEY `ix_rend_comprobante` (`comprobante_id`),
  KEY `ix_rend_estado` (`tabla_estado_registro_id`),
  KEY `ix_rend_sucursal` (`sucursal_id`),
  KEY `ix_rend_tipo` (`comprobante_tipo_id`),
  KEY `ix_rend_pv` (`comprobante_pv`),
  KEY `ix_rend_moneda` (`moneda_id`),
  CONSTRAINT `fk_rend_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_rend_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_rend_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_rend_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_rend_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__rendiciones_anticipos
CREATE TABLE IF NOT EXISTS `gestion__rendiciones_anticipos` (
  `rendicion_anticipo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (RG_ANT). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned NOT NULL COMMENT 'Empleado (rol EMP)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `f_vto` date DEFAULT NULL COMMENT 'Fecha limite para rendir',
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `cheque_id` int(10) unsigned DEFAULT NULL,
  `nro_operacion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`rendicion_anticipo_id`),
  KEY `ix_rant_empresa_fecha` (`empresa_id`,`f_emision`),
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
  CONSTRAINT `fk_rant_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_rant_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_rant_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_rant_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_rant_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_rant_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_rant_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_rant_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__rendiciones_items
CREATE TABLE IF NOT EXISTS `gestion__rendiciones_items` (
  `rendicion_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rendicion_id` int(10) unsigned NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `f_gasto` date NOT NULL,
  `categoria_gasto_id` int(10) unsigned NOT NULL,
  `descripcion` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'Proveedor del gasto si esta dado de alta',
  `proveedor_nombre` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texto libre si no es entidad',
  `proveedor_cuit` bigint(20) unsigned DEFAULT NULL,
  `comprobante_externo_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numero del ticket / factura del gasto',
  `tiene_comprobante` tinyint(1) NOT NULL DEFAULT 1,
  `moneda_id` smallint(5) unsigned NOT NULL COMMENT 'Moneda del gasto',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda del gasto -> moneda de la rendicion',
  `importe` decimal(18,2) NOT NULL COMMENT 'En moneda del gasto',
  `importe_convertido` decimal(18,2) NOT NULL COMMENT 'En moneda de la rendicion = importe * tipo_cambio',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL,
  `motivo_rechazo` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL COMMENT 'Pendiente / Aprobado / Rechazado',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`rendicion_item_id`),
  KEY `ix_rendi_rendicion_estado` (`rendicion_id`,`tabla_estado_registro_id`),
  KEY `ix_rendi_categoria_gasto` (`categoria_gasto_id`),
  KEY `ix_rendi_entidad` (`entidad_id`),
  KEY `ix_rendi_moneda` (`moneda_id`),
  KEY `ix_rendi_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_rendi_categoria_gasto_id` FOREIGN KEY (`categoria_gasto_id`) REFERENCES `gestion__categorias_gasto` (`categoria_gasto_id`),
  CONSTRAINT `fk_rendi_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_rendi_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_rendi_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_rendi_rendicion_id` FOREIGN KEY (`rendicion_id`) REFERENCES `gestion__rendiciones` (`rendicion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__rendiciones_reembolsos
CREATE TABLE IF NOT EXISTS `gestion__rendiciones_reembolsos` (
  `rendicion_reembolso_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (RG_REEM). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned NOT NULL COMMENT 'Empleado (rol EMP)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `rendicion_id` int(10) unsigned NOT NULL,
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`rendicion_reembolso_id`),
  KEY `ix_reem_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `ix_reem_entidad` (`entidad_id`),
  KEY `ix_reem_comprobante` (`comprobante_id`),
  KEY `ix_reem_estado` (`tabla_estado_registro_id`),
  KEY `ix_reem_rendicion` (`rendicion_id`),
  KEY `ix_reem_sucursal` (`sucursal_id`),
  KEY `ix_reem_tipo` (`comprobante_tipo_id`),
  KEY `ix_reem_pv` (`comprobante_pv`),
  KEY `ix_reem_moneda` (`moneda_id`),
  CONSTRAINT `fk_reem_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_reem_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_reem_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_reem_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_reem_rendicion_id` FOREIGN KEY (`rendicion_id`) REFERENCES `gestion__rendiciones` (`rendicion_id`),
  CONSTRAINT `fk_reem_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__rendiciones_reembolsos_medios
CREATE TABLE IF NOT EXISTS `gestion__rendiciones_reembolsos_medios` (
  `rendicion_reembolso_medio_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rendicion_reembolso_id` int(10) unsigned NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL solo si el medio es RETENCION',
  `moneda_id` smallint(5) unsigned NOT NULL COMMENT 'Moneda de la linea (= de la cuenta)',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Moneda de la linea -> moneda de la cabecera',
  `importe` decimal(18,2) NOT NULL COMMENT 'En moneda de la linea',
  `importe_comprobante` decimal(18,2) NOT NULL COMMENT 'En moneda de la cabecera; SUM = importe_total',
  `cheque_id` int(10) unsigned DEFAULT NULL,
  `nro_operacion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_valor` date DEFAULT NULL,
  `tarjeta_marca` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_terminal` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_lote` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cupon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_autorizacion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta_cuotas` tinyint(3) unsigned DEFAULT NULL,
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL,
  `jurisdiccion_id` int(11) DEFAULT NULL,
  `retencion_certificado_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retencion_base_imponible` decimal(18,2) DEFAULT NULL,
  `retencion_alicuota` decimal(10,6) DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`rendicion_reembolso_medio_id`),
  KEY `ix_reemm_reembolso` (`rendicion_reembolso_id`),
  KEY `ix_reemm_medio_pago` (`medio_pago_id`),
  KEY `ix_reemm_tesoreria_cuenta` (`tesoreria_cuenta_id`),
  KEY `ix_reemm_moneda` (`moneda_id`),
  KEY `ix_reemm_cheque` (`cheque_id`),
  KEY `ix_reemm_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_reemm_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_reemm_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_reemm_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_reemm_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_reemm_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_reemm_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_reemm_rendicion_reembolso_id` FOREIGN KEY (`rendicion_reembolso_id`) REFERENCES `gestion__rendiciones_reembolsos` (`rendicion_reembolso_id`),
  CONSTRAINT `fk_reemm_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__retenciones_numeradores
CREATE TABLE IF NOT EXISTS `gestion__retenciones_numeradores` (
  `retencion_numerador_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `impuesto_tipo_id` smallint(5) unsigned NOT NULL,
  `ultimo_numero` int(11) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`retencion_numerador_id`),
  UNIQUE KEY `uk_rnum_empresa_impuesto` (`empresa_id`,`impuesto_tipo_id`),
  KEY `ix_rnum_impuesto_tipo` (`impuesto_tipo_id`),
  CONSTRAINT `fk_rnum_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__stock
CREATE TABLE IF NOT EXISTS `gestion__stock` (
  `empresa_id` int(10) unsigned NOT NULL,
  `boca_id` int(10) unsigned NOT NULL,
  `producto_id` int(10) unsigned NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `cantidad` decimal(18,4) NOT NULL DEFAULT 0.0000 COMMENT 'MATERIALIZADO: SUM(INGRESO) - SUM(EGRESO) de gestion__stock_movimientos',
  `costo_promedio` decimal(18,6) NOT NULL DEFAULT 0.000000 COMMENT 'Promedio ponderado de la boca, recalculado en cada INGRESO con costo',
  `costo_ultima_compra` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `f_ultimo_movimiento` datetime DEFAULT NULL,
  PRIMARY KEY (`empresa_id`,`boca_id`,`producto_id`),
  KEY `ix_stock_producto` (`empresa_id`,`producto_id`),
  KEY `ix_stock_sucursal` (`sucursal_id`),
  KEY `fk_stock_boca` (`boca_id`),
  KEY `fk_stock_producto` (`producto_id`),
  CONSTRAINT `fk_stock_boca` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_stock_producto` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stock actual por boca (materializado). Se reconstruye con stockRecalcular()';

-- gestion__stock_alertas
CREATE TABLE IF NOT EXISTS `gestion__stock_alertas` (
  `stock_alerta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `boca_id` int(10) unsigned NOT NULL,
  `producto_id` int(10) unsigned NOT NULL,
  `stock_nivel_id` int(10) unsigned NOT NULL,
  `abierta` tinyint(1) DEFAULT 1 COMMENT '1 = abierta, NULL = cerrada. Habilita el UNIQUE de una sola abierta por boca+producto',
  `f_disparo` datetime NOT NULL,
  `stock_al_disparo` decimal(20,6) NOT NULL COMMENT 'Foto: stock en el momento en que se abrió',
  `stock_minimo` decimal(20,6) NOT NULL COMMENT 'Copia del mínimo vigente mientras está abierta',
  `stock_maximo` decimal(20,6) DEFAULT NULL COMMENT 'Copia del máximo vigente mientras está abierta',
  `stock_actual` decimal(20,6) NOT NULL COMMENT 'MATERIALIZADO: último saldo visto mientras está abierta',
  `cantidad_sugerida` decimal(20,6) NOT NULL COMMENT 'MATERIALIZADO: GREATEST(COALESCE(maximo, minimo) - stock_actual, 0)',
  `stock_movimiento_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Movimiento que la disparó; NULL = la disparó la carga/edición del nivel',
  `usuario_toma_id` int(10) unsigned DEFAULT NULL COMMENT 'Quién la pasó a En curso',
  `f_toma` datetime DEFAULT NULL,
  `f_cierre` datetime DEFAULT NULL,
  `motivo_cierre` enum('REPOSICION','DESCARTADA','NIVEL_MODIFICADO','NIVEL_INACTIVO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_cierre_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = cierre automático',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 17 COMMENT '17 Pendiente / 18 En curso / 21 Cumplido / 26 Cancelado',
  PRIMARY KEY (`stock_alerta_id`),
  UNIQUE KEY `uk_sal_abierta` (`empresa_id`,`boca_id`,`producto_id`,`abierta`),
  KEY `ix_sal_empresa_estado` (`empresa_id`,`tabla_estado_registro_id`),
  KEY `ix_sal_boca` (`boca_id`),
  KEY `ix_sal_producto` (`producto_id`),
  KEY `ix_sal_stock_nivel` (`stock_nivel_id`),
  CONSTRAINT `fk_sal_boca_id` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_sal_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`),
  CONSTRAINT `fk_sal_stock_nivel_id` FOREIGN KEY (`stock_nivel_id`) REFERENCES `gestion__stock_niveles` (`stock_nivel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alertas de reposicion por stock bajo el minimo (materializadas)';

-- gestion__stock_movimientos
CREATE TABLE IF NOT EXISTS `gestion__stock_movimientos` (
  `stock_movimiento_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL COMMENT 'Desnormalizado de la boca, para AccesosSucursalesHelper::filtroSqlSucursal',
  `boca_id` int(10) unsigned NOT NULL COMMENT 'Por fila y no por comprobante: una transferencia mueve dos bocas',
  `producto_id` int(10) unsigned NOT NULL,
  `tipo_movimiento` enum('INGRESO','EGRESO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(18,4) NOT NULL COMMENT 'Siempre > 0; la direcci├│n la da tipo_movimiento',
  `costo_unitario` decimal(18,6) DEFAULT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes',
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `tabla_origen_id` int(11) NOT NULL COMMENT 'conf__tablas del documento (81 ventas_remitos, 80 ventas_facturas, 84 facturas_proveedores, 97 stock_ajustes...)',
  `registro_origen_id` int(11) NOT NULL,
  `registro_detalle_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Id de la l├¡nea en la tabla _detalles del documento de origen',
  `f_movimiento` datetime NOT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(5) unsigned NOT NULL DEFAULT 1,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `f_alta` datetime NOT NULL,
  PRIMARY KEY (`stock_movimiento_id`),
  KEY `ix_smov_boca_producto` (`empresa_id`,`boca_id`,`producto_id`,`f_movimiento`),
  KEY `ix_smov_origen` (`tabla_origen_id`,`registro_origen_id`),
  KEY `ix_smov_origen_linea` (`tabla_origen_id`,`registro_detalle_id`,`producto_id`),
  KEY `ix_smov_comprobante` (`comprobante_id`),
  KEY `ix_smov_producto` (`producto_id`),
  KEY `ix_smov_sucursal` (`sucursal_id`),
  KEY `fk_smov_boca` (`boca_id`),
  CONSTRAINT `fk_smov_boca` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_smov_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_smov_producto` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`),
  CONSTRAINT `chk_smov_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Libro inmutable de movimientos de stock. Lo escribe solo stock_core_model.php';

-- gestion__stock_niveles
CREATE TABLE IF NOT EXISTS `gestion__stock_niveles` (
  `stock_nivel_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `boca_id` int(10) unsigned NOT NULL COMMENT 'Boca con es_deposito = 1',
  `producto_id` int(10) unsigned NOT NULL COMMENT 'Producto con controla_stock = 1',
  `stock_minimo` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `stock_maximo` decimal(20,6) DEFAULT NULL COMMENT 'NULL = sin tope. No genera alerta: solo calcula la cantidad sugerida',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo (= no controlar)',
  `usuario_id` int(10) unsigned DEFAULT NULL COMMENT 'Último usuario que lo modificó',
  `f_alta` datetime DEFAULT NULL,
  `f_modificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`stock_nivel_id`),
  UNIQUE KEY `uk_snv_boca_producto` (`empresa_id`,`boca_id`,`producto_id`),
  KEY `ix_snv_boca` (`boca_id`),
  KEY `ix_snv_producto` (`producto_id`),
  CONSTRAINT `fk_snv_boca_id` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_snv_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stock minimo y maximo por producto y boca (carga manual)';

-- gestion__sync_cola
CREATE TABLE IF NOT EXISTS `gestion__sync_cola` (
  `sync_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant emisor',
  `tabla_origen` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'gestion__comprobantes, gestion__cont_asientos',
  `registro_id` bigint(20) unsigned NOT NULL,
  `accion` enum('INSERT','UPDATE','DELETE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INSERT',
  `payload_json` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_envio` enum('PENDIENTE','ENVIADO','ERROR','REINTENTANDO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `reintentos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `ultimo_error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `f_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sync_id`),
  KEY `idx_estado_envio` (`estado_envio`,`reintentos`),
  KEY `idx_empresa_tabla` (`empresa_id`,`tabla_origen`,`registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__tesoreria_cuentas
CREATE TABLE IF NOT EXISTS `gestion__tesoreria_cuentas` (
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned DEFAULT NULL COMMENT 'NULL = cuenta de toda la empresa (tipico en BANCO)',
  `cuenta_tipo` enum('CAJA','BANCO','VALORES','CUPONES') COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tesoreria_cuenta` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptor: Caja Principal, Banco Nacion CC $, Valores en cartera...',
  `moneda_id` smallint(5) unsigned NOT NULL,
  `banco_id` smallint(5) unsigned DEFAULT NULL COMMENT 'Obligatorio si cuenta_tipo = BANCO',
  `banco_cuenta_tipo` enum('CC','CA','VIRTUAL') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Solo BANCO',
  `numero` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Solo BANCO: numero de cuenta',
  `cbu` char(22) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Solo BANCO: CBU/CVU de 22 digitos',
  `alias` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Solo BANCO: alias CBU',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta contable (Caja Pesos, Banco Nacion $, Valores a depositar...)',
  `saldo_actual` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'MATERIALIZADO: SUM(ingresos) - SUM(egresos) de gestion__tesoreria_movimientos',
  `permite_saldo_negativo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = admite descubierto (BANCO)',
  `orden` smallint(5) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`tesoreria_cuenta_id`),
  UNIQUE KEY `uk_tcta_empresa_codigo` (`empresa_id`,`codigo`),
  UNIQUE KEY `uk_tcta_empresa_cbu` (`empresa_id`,`cbu`),
  KEY `ix_tcta_empresa_tipo` (`empresa_id`,`cuenta_tipo`),
  KEY `ix_tcta_sucursal` (`sucursal_id`),
  KEY `ix_tcta_moneda` (`moneda_id`),
  KEY `ix_tcta_banco` (`banco_id`),
  KEY `ix_tcta_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_tcta_banco_id` FOREIGN KEY (`banco_id`) REFERENCES `conf__bancos` (`banco_id`),
  CONSTRAINT `fk_tcta_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_tcta_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_tcta_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__tesoreria_movimientos
CREATE TABLE IF NOT EXISTS `gestion__tesoreria_movimientos` (
  `tesoreria_movimiento_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'Comprobante que genero el movimiento (RCX, OP, TB, DB, MC, I_PAG, RG_ANT, RG_REEM, ND_CHR)',
  `tabla_origen_id` int(11) DEFAULT NULL COMMENT 'conf__tablas de la linea origen (p.ej. gestion__cobranzas_medios)',
  `registro_origen_id` int(10) unsigned DEFAULT NULL,
  `tipo_movimiento` enum('INGRESO','EGRESO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_movimiento` date NOT NULL COMMENT 'Fecha contable (= f_contabilidad del comprobante)',
  `f_valor` date NOT NULL COMMENT 'Fecha real de acreditacion / debito',
  `moneda_id` smallint(5) unsigned NOT NULL COMMENT 'Moneda de la cuenta',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Contra moneda base',
  `importe` decimal(18,2) NOT NULL COMMENT 'Siempre > 0, en moneda de la cuenta; la direccion la da tipo_movimiento',
  `medio_pago_id` int(10) unsigned DEFAULT NULL,
  `cheque_id` int(10) unsigned DEFAULT NULL,
  `concepto` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conciliado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = conciliado contra extracto',
  `f_conciliacion` date DEFAULT NULL,
  `reversa_de_movimiento_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Contramovimiento de anulacion: id del movimiento revertido',
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`tesoreria_movimiento_id`),
  UNIQUE KEY `uk_tmov_reversa` (`reversa_de_movimiento_id`),
  KEY `ix_tmov_cuenta_fecha` (`tesoreria_cuenta_id`,`f_movimiento`),
  KEY `ix_tmov_cuenta_conciliado` (`tesoreria_cuenta_id`,`conciliado`,`f_valor`),
  KEY `ix_tmov_empresa_fecha` (`empresa_id`,`f_movimiento`),
  KEY `ix_tmov_comprobante` (`comprobante_id`),
  KEY `ix_tmov_origen` (`tabla_origen_id`,`registro_origen_id`),
  KEY `ix_tmov_sucursal` (`sucursal_id`),
  KEY `ix_tmov_moneda` (`moneda_id`),
  KEY `ix_tmov_medio_pago` (`medio_pago_id`),
  KEY `ix_tmov_cheque` (`cheque_id`),
  CONSTRAINT `fk_tmov_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_tmov_comprobante_id` FOREIGN KEY (`comprobante_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_tmov_medio_pago_id` FOREIGN KEY (`medio_pago_id`) REFERENCES `gestion__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_tmov_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_tmov_reversa_de_movimiento_id` FOREIGN KEY (`reversa_de_movimiento_id`) REFERENCES `gestion__tesoreria_movimientos` (`tesoreria_movimiento_id`),
  CONSTRAINT `fk_tmov_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_tmov_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__transferencias
CREATE TABLE IF NOT EXISTS `gestion__transferencias` (
  `transferencia_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (TB). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'Sin contraparte: NULL (0 en el comprobante)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `tesoreria_cuenta_origen_id` int(10) unsigned NOT NULL,
  `tesoreria_cuenta_destino_id` int(10) unsigned NOT NULL,
  `moneda_id` smallint(5) unsigned NOT NULL COMMENT 'Lado ORIGEN: moneda de la cuenta origen',
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Lado ORIGEN: contra moneda base',
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Lado ORIGEN: importe debitado',
  `moneda_destino_id` smallint(5) unsigned NOT NULL COMMENT 'Lado DESTINO: moneda de la cuenta destino',
  `tipo_cambio_destino` decimal(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'Lado DESTINO: contra moneda base',
  `importe_destino` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Lado DESTINO: importe acreditado',
  `cont_cuenta_diferencia_id` int(10) unsigned DEFAULT NULL COMMENT 'Gastos/comisiones/diferencia de cambio entre ambos lados',
  `nro_operacion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`transferencia_id`),
  KEY `ix_trf_empresa_fecha` (`empresa_id`,`f_emision`),
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
  CONSTRAINT `fk_trf_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_trf_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_trf_cont_cuenta_diferencia_id` FOREIGN KEY (`cont_cuenta_diferencia_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_trf_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_trf_moneda_destino_id` FOREIGN KEY (`moneda_destino_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_trf_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_trf_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_trf_tesoreria_cuenta_destino_id` FOREIGN KEY (`tesoreria_cuenta_destino_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`),
  CONSTRAINT `fk_trf_tesoreria_cuenta_origen_id` FOREIGN KEY (`tesoreria_cuenta_origen_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__usuarios_sucursales_excepciones
CREATE TABLE IF NOT EXISTS `gestion__usuarios_sucursales_excepciones` (
  `usuario_excepcion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `sucursal_id` smallint(5) unsigned DEFAULT NULL COMMENT 'NULL = todas las sucursales de la empresa',
  `boca_id` int(10) unsigned DEFAULT NULL,
  `punto_venta_id` smallint(5) unsigned DEFAULT NULL,
  `comprobante_tipo_id` int(10) unsigned DEFAULT NULL,
  `tipo_excepcion` enum('PERMITE','DENIEGA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date DEFAULT NULL COMMENT 'NULL = sin vencimiento',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_excepcion_id`),
  KEY `idx_use_usuario_vigencia` (`empresa_id`,`usuario_id`,`fecha_desde`,`fecha_hasta`),
  KEY `idx_use_sucursal` (`sucursal_id`),
  KEY `idx_use_boca` (`boca_id`),
  KEY `idx_use_pv` (`punto_venta_id`),
  KEY `idx_use_tipo` (`comprobante_tipo_id`),
  CONSTRAINT `fk_use_boca` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_use_pv` FOREIGN KEY (`punto_venta_id`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_use_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_use_tipo` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `ck_use_vigencia` CHECK (`fecha_hasta` is null or `fecha_hasta` >= `fecha_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Excepciones por usuario sobre accesos de sucursal. DENIEGA prevalece.';

-- ml__ambitos_tipos
CREATE TABLE IF NOT EXISTS `ml__ambitos_tipos` (
  `ambito_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ambito_tipo_id`),
  UNIQUE KEY `uk_ambt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__entidades
CREATE TABLE IF NOT EXISTS `ml__entidades` (
  `entidad_id` int(11) NOT NULL AUTO_INCREMENT,
  `entidad_tipo_id` int(11) NOT NULL,
  `entidad_padre_id` int(11) DEFAULT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_raiz` tinyint(1) NOT NULL DEFAULT 0,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_id`),
  KEY `ix_ent_codigo` (`codigo`),
  KEY `ix_ent_entidad_padre` (`entidad_padre_id`),
  KEY `ix_ent_entidad_tipo` (`entidad_tipo_id`),
  CONSTRAINT `fk_ent_entidad_padre_id` FOREIGN KEY (`entidad_padre_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_ent_entidad_tipo_id` FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`),
  CONSTRAINT `ck_ent_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__entidades_tipos
CREATE TABLE IF NOT EXISTS `ml__entidades_tipos` (
  `entidad_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `grupo_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_fisica` tinyint(1) NOT NULL DEFAULT 1,
  `es_logica` tinyint(1) NOT NULL DEFAULT 0,
  `es_movil` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_tipo_id`),
  KEY `ix_entt_grupo_empresa_codigo` (`grupo_id`,`empresa_id`,`codigo`),
  KEY `ix_entt_empresa` (`empresa_id`),
  CONSTRAINT `fk_entt_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_entt_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__normas
CREATE TABLE IF NOT EXISTS `ml__normas` (
  `norma_id` int(11) NOT NULL AUTO_INCREMENT,
  `norma_tipo_id` int(11) NOT NULL,
  `norma_jerarquia_id` int(11) DEFAULT NULL,
  `organismo_id` int(11) DEFAULT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idioma_original` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo_traducido` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idioma_traducido` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ambito_tipo_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `region_grupo_id` int(11) DEFAULT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `territorio_id` int(11) DEFAULT NULL,
  `requiere_norma_local` tinyint(1) NOT NULL DEFAULT 0,
  `confidencial` tinyint(1) NOT NULL DEFAULT 0,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_id`),
  KEY `ix_nor_codigo` (`codigo`),
  KEY `ix_nor_ambito_tipo_territorio` (`ambito_tipo_id`,`territorio_id`),
  KEY `ix_nor_norma_tipo` (`norma_tipo_id`),
  KEY `ix_nor_norma_jerarquia` (`norma_jerarquia_id`),
  KEY `ix_nor_organismo` (`organismo_id`),
  KEY `ix_nor_grupo` (`grupo_id`),
  KEY `ix_nor_region_grupo` (`region_grupo_id`),
  KEY `ix_nor_empresa` (`empresa_id`),
  KEY `ix_nor_territorio` (`territorio_id`),
  CONSTRAINT `fk_nor_ambito_tipo_id` FOREIGN KEY (`ambito_tipo_id`) REFERENCES `ml__ambitos_tipos` (`ambito_tipo_id`),
  CONSTRAINT `fk_nor_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_nor_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`),
  CONSTRAINT `fk_nor_norma_jerarquia_id` FOREIGN KEY (`norma_jerarquia_id`) REFERENCES `ml__normas_jerarquias` (`norma_jerarquia_id`),
  CONSTRAINT `fk_nor_norma_tipo_id` FOREIGN KEY (`norma_tipo_id`) REFERENCES `ml__normas_tipos` (`norma_tipo_id`),
  CONSTRAINT `fk_nor_organismo_id` FOREIGN KEY (`organismo_id`) REFERENCES `ml__organismos` (`organismo_id`),
  CONSTRAINT `fk_nor_region_grupo_id` FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`),
  CONSTRAINT `fk_nor_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `ck_nor_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`),
  CONSTRAINT `ck_nor_ambito` CHECK ((`grupo_id` is not null) + (`region_grupo_id` is not null) + (`empresa_id` is not null) + (`territorio_id` is not null) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__normas_tipos
CREATE TABLE IF NOT EXISTS `ml__normas_tipos` (
  `norma_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_legal` tinyint(1) NOT NULL DEFAULT 0,
  `genera_obligaciones` tinyint(1) NOT NULL DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_tipo_id`),
  UNIQUE KEY `uk_nort_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones
CREATE TABLE IF NOT EXISTS `ml__obligaciones` (
  `obligacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `norma_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `articulo_referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `periodicidad_id` int(11) NOT NULL,
  `vencimiento_regla_id` int(11) NOT NULL,
  `criticidad_id` int(11) DEFAULT NULL,
  `requiere_revision` tinyint(1) NOT NULL DEFAULT 1,
  `requiere_evidencia` tinyint(1) NOT NULL DEFAULT 1,
  `retencion_evidencias_meses` int(11) DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_id`),
  UNIQUE KEY `uk_obl_norma_codigo` (`norma_id`,`codigo`),
  KEY `ix_obl_norma_hasta` (`norma_id`,`vigente_hasta`),
  KEY `ix_obl_empresa` (`empresa_id`),
  KEY `ix_obl_periodicidad` (`periodicidad_id`),
  KEY `ix_obl_vencimiento_regla` (`vencimiento_regla_id`),
  KEY `ix_obl_criticidad` (`criticidad_id`),
  CONSTRAINT `fk_obl_criticidad_id` FOREIGN KEY (`criticidad_id`) REFERENCES `ml__criticidades` (`criticidad_id`),
  CONSTRAINT `fk_obl_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_obl_norma_id` FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`),
  CONSTRAINT `fk_obl_periodicidad_id` FOREIGN KEY (`periodicidad_id`) REFERENCES `ml__periodicidades` (`periodicidad_id`),
  CONSTRAINT `fk_obl_vencimiento_regla_id` FOREIGN KEY (`vencimiento_regla_id`) REFERENCES `ml__vencimientos_reglas` (`vencimiento_regla_id`),
  CONSTRAINT `ck_obl_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones_jurisdicciones
CREATE TABLE IF NOT EXISTS `ml__obligaciones_jurisdicciones` (
  `obligacion_jurisdiccion_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_id` int(11) NOT NULL,
  `territorio_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_jurisdiccion_id`),
  KEY `ix_oblj_obligacion` (`obligacion_id`),
  KEY `ix_oblj_territorio` (`territorio_id`),
  CONSTRAINT `fk_oblj_obligacion_id` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `fk_oblj_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `ck_oblj_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones_tipos_entidades
CREATE TABLE IF NOT EXISTS `ml__obligaciones_tipos_entidades` (
  `obligacion_tipo_entidad_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_id` int(11) NOT NULL,
  `entidad_tipo_id` int(11) NOT NULL,
  `regla_json` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_tipo_entidad_id`),
  KEY `ix_oblt_obligacion_entidad_tipo_desde` (`obligacion_id`,`entidad_tipo_id`,`vigente_desde`),
  KEY `ix_oblt_entidad_tipo` (`entidad_tipo_id`),
  CONSTRAINT `fk_oblt_entidad_tipo_id` FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`),
  CONSTRAINT `fk_oblt_obligacion_id` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `ck_oblt_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos
CREATE TABLE IF NOT EXISTS `ml__procedimientos` (
  `procedimiento_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `norma_id` int(11) DEFAULT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidencial` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`procedimiento_id`),
  KEY `ix_prc_empresa` (`empresa_id`),
  KEY `ix_prc_norma` (`norma_id`),
  CONSTRAINT `fk_prc_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_prc_norma_id` FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_instrucciones
CREATE TABLE IF NOT EXISTS `ml__procedimientos_instrucciones` (
  `instruccion_id` int(11) NOT NULL AUTO_INCREMENT,
  `procedimiento_version_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_obligatoria` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`instruccion_id`),
  UNIQUE KEY `uk_prci_procedimiento_version_orden` (`procedimiento_version_id`,`orden`),
  CONSTRAINT `fk_prci_procedimiento_version_id` FOREIGN KEY (`procedimiento_version_id`) REFERENCES `ml__procedimientos_versiones` (`procedimiento_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) Columnas en tablas existentes
-- ---------------------------------------------------------------------
CALL _sync_add_column('conf__empresas', 'condicion_fiscal_id', 'tinyint(4) unsigned DEFAULT NULL AFTER `localidad_id`');
CALL _sync_add_column('conf__empresas', 'iibb_regimen_id', 'tinyint(3) unsigned DEFAULT 1 AFTER `condicion_fiscal_id`');
CALL _sync_add_column('conf__empresas', 'jurisdiccion_sede_id', 'smallint(5) unsigned DEFAULT 2 AFTER `iibb_regimen_id`');
CALL _sync_add_column('conf__tablas_estados_registros', 'es_final', 'tinyint(1) NOT NULL DEFAULT 0 AFTER `es_inicial`');
CALL _sync_add_column('conf__tablas_estados_registros', 'aplica_stock', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = en este estado el documento tiene aplicado su movimiento de stock (si su tipo impacta stock)'' AFTER `orden`');
CALL _sync_add_column('gestion__bocas', 'permite_stock_negativo', 'tinyint(1) NOT NULL DEFAULT 1 COMMENT ''1 = un egreso puede dejar el stock de la boca en negativo (venta de mostrador sin stock cargado)'' AFTER `permite_egresos`');
CALL _sync_add_column('gestion__comprobantes', 'numeracion_entidad_id', 'int(11) GENERATED ALWAYS AS (case when `tabla_origen_id` = 84 then `entidad_id` else 0 end) STORED COMMENT ''entidad_id si la numeracion es del tercero (facturas_proveedores, tabla_id 84), 0 si es propia'' AFTER `signo`');
CALL _sync_add_column('gestion__comprobantes', 'comprobante_nro_unico', 'int(11) GENERATED ALWAYS AS (nullif(`comprobante_nro`,0)) STORED COMMENT ''NULL en Borrador (nro 0): excluido del UNIQUE'' AFTER `numeracion_entidad_id`');
CALL _sync_add_column('gestion__comprobantes_fiscales', 'letra', 'char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `comprobante_fiscal`');
CALL _sync_add_column('gestion__comprobantes_tipos', 'stock_signo', 'enum(''+'',''-'',''+/-'') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Sentido en stock: + suma, - resta, +/- lo trae cada l├¡nea (ajustes, transferencias). NULL = no mueve'' AFTER `impacta_stock`');
ALTER TABLE `gestion__cont_asientos` MODIFY COLUMN `entidad_sucursal_id` int(11) DEFAULT NULL;
CALL _sync_add_column('gestion__cont_cuentas', 'es_caja_banco', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Cuenta líquida/financiera (Caja, Bancos, Valores en cartera, Billeteras). Base dinámica del Dashboard de Tesorería'' AFTER `es_imputable`');
ALTER TABLE `gestion__empresas_impuestos_config` MODIFY COLUMN `cont_cuenta_id` int(10) unsigned DEFAULT NULL;
CALL _sync_add_column('gestion__facturas_proveedores', 'comprobante_asociado_id', 'int(11) DEFAULT NULL COMMENT ''NC/ND: gestion__comprobantes del comprobante asociado'' AFTER `comprobante_id`');
CALL _sync_add_column('gestion__facturas_proveedores', 'orden_compra_id', 'int(11) DEFAULT NULL COMMENT ''gestion__ordenes_compra importada (NULL = factura sin OC)'' AFTER `comprobante_asociado_id`');
CALL _sync_add_column('gestion__facturas_proveedores_detalle', 'ordenes_compra_detalle_id', 'int(11) DEFAULT NULL COMMENT ''Línea de OC que recibe (NULL = línea sin OC)'' AFTER `producto_id`');
CALL _sync_add_column('gestion__impuestos_tipos', 'es_liquidable', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = se liquida por DDJJ (comprobante I_LIQ) y se paga con I_PAG'' AFTER `es_percepcion`');
CALL _sync_add_column('gestion__impuestos_tipos', 'frecuencia', 'enum(''MENSUAL'',''BIMESTRAL'',''TRIMESTRAL'',''CUATRIMESTRAL'',''SEMESTRAL'',''ANUAL'',''IRREGULAR'') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Frecuencia de liquidacion (solo es_liquidable = 1)'' AFTER `es_liquidable`');
CALL _sync_add_column('gestion__stock_ajustes', 'comprobante_id', 'bigint(20) unsigned DEFAULT NULL COMMENT ''gestion__comprobantes (tabla_origen_id 97)'' AFTER `comprobante_tipo_id`');
CALL _sync_add_column('gestion__ventas_facturas', 'comprobante_asociado_id', 'int(11) DEFAULT NULL COMMENT ''NC/ND: gestion__comprobantes del comprobante asociado'' AFTER `comprobante_id`');
CALL _sync_add_column('gestion__ventas_facturas', 'consumidor_final_nombre', 'varchar(150) DEFAULT NULL AFTER `entidad_sucursal_id`');
ALTER TABLE `gestion__ventas_facturas` MODIFY COLUMN `entidad_id` int(11) DEFAULT NULL;
ALTER TABLE `gestion__ventas_pedidos` MODIFY COLUMN `entidad_sucursal_id` int(11) DEFAULT NULL;
ALTER TABLE `gestion__ventas_pedidos` MODIFY COLUMN `condicion_pago_id` smallint(6) DEFAULT NULL;
ALTER TABLE `gestion__ventas_remitos` MODIFY COLUMN `entidad_id` int(11) DEFAULT NULL;
ALTER TABLE `gestion__ventas_remitos` MODIFY COLUMN `consumidor_final_nombre` varchar(150) DEFAULT NULL;

-- ---------------------------------------------------------------------
-- 6) Indices en tablas existentes
-- ---------------------------------------------------------------------
CALL _sync_add_index('gestion__comprobantes', 'uk_comp_numeracion', 'UNIQUE KEY `uk_comp_numeracion` (`empresa_id`,`comprobante_tipo_id`,`comprobante_pv`,`comprobante_nro_unico`,`numeracion_entidad_id`)');
CALL _sync_add_index('gestion__cont_cuentas', 'ix_cont_cuentas_caja_banco', 'KEY `ix_cont_cuentas_caja_banco` (`empresa_id`,`es_caja_banco`,`tabla_estado_registro_id`)');
CALL _sync_add_index('gestion__facturas_proveedores', 'idx_fp_orden_compra', 'KEY `idx_fp_orden_compra` (`orden_compra_id`)');
CALL _sync_add_index('gestion__facturas_proveedores_detalle', 'idx_fpd_oc_detalle', 'KEY `idx_fpd_oc_detalle` (`ordenes_compra_detalle_id`)');
CALL _sync_add_index('gestion__marcas', 'idx_marcas_empresa', 'KEY `idx_marcas_empresa` (`empresa_id`)');
CALL _sync_add_index('gestion__modelos', 'idx_modelos_empresa_marca', 'KEY `idx_modelos_empresa_marca` (`empresa_id`,`marca_id`)');
CALL _sync_add_index('gestion__productos_compatibilidad', 'idx_compat_marca', 'KEY `idx_compat_marca` (`empresa_id`,`marca_id`,`tabla_estado_registro_id`,`producto_id`)');
CALL _sync_add_index('gestion__productos_compatibilidad', 'idx_compat_modelo', 'KEY `idx_compat_modelo` (`empresa_id`,`modelo_id`,`tabla_estado_registro_id`,`submodelo_id`,`producto_id`)');
CALL _sync_add_index('gestion__productos_compatibilidad', 'idx_compat_submodelo', 'KEY `idx_compat_submodelo` (`empresa_id`,`submodelo_id`,`tabla_estado_registro_id`,`producto_id`)');
CALL _sync_add_index('gestion__submodelos', 'idx_submodelos_empresa_modelo', 'KEY `idx_submodelos_empresa_modelo` (`empresa_id`,`modelo_id`)');

-- ---------------------------------------------------------------------
-- 7) FKs en tablas existentes (con FOREIGN_KEY_CHECKS = 1: si hay datos
--    huerfanos el ALTER falla y hay que corregirlos antes de reanudar)
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;
-- PENDIENTE: 57 filas de gestion__productos_proveedores_precios apuntan a un gestion__productos_proveedores inexistente (datos operativos, no se tocan).
-- Diagnostico:
--   SELECT x.* FROM `gestion__productos_proveedores_precios` x LEFT JOIN `gestion__productos_proveedores` r ON r.`producto_proveedor_id` = x.`producto_proveedor_id`
--    WHERE x.`producto_proveedor_id` IS NOT NULL AND r.`producto_proveedor_id` IS NULL;
-- Una vez corregidas, agregar la FK:
--   ALTER TABLE `gestion__productos_proveedores_precios` ADD CONSTRAINT `fk_precios_actual_prod_prov` FOREIGN KEY (`producto_proveedor_id`) REFERENCES `gestion__productos_proveedores` (`producto_proveedor_id`);

-- ---------------------------------------------------------------------
-- 8) Procedimientos de compatibilidad de productos (version de local)
-- ---------------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS `sp_recalcular_compatibilidad_por_marca`$$
CREATE PROCEDURE `sp_recalcular_compatibilidad_por_marca`(IN p_marca_id INT UNSIGNED)
BEGIN

    DECLARE v_done INT DEFAULT 0;

    DECLARE v_producto_id BIGINT UNSIGNED;

    DECLARE cur CURSOR FOR

        SELECT DISTINCT producto_id

        FROM gestion__productos_compatibilidad

        WHERE marca_id = p_marca_id AND tabla_estado_registro_id = 1;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;



    OPEN cur;

    loop_marca: LOOP

        FETCH cur INTO v_producto_id;

        IF v_done THEN LEAVE loop_marca; END IF;

        CALL sp_recalcular_compatibilidad_producto(v_producto_id);

    END LOOP;

    CLOSE cur;

END$$
DROP PROCEDURE IF EXISTS `sp_recalcular_compatibilidad_por_modelo`$$
CREATE PROCEDURE `sp_recalcular_compatibilidad_por_modelo`(IN p_modelo_id INT UNSIGNED)
BEGIN

    DECLARE v_done INT DEFAULT 0;

    DECLARE v_producto_id BIGINT UNSIGNED;

    DECLARE cur CURSOR FOR

        SELECT DISTINCT producto_id

        FROM gestion__productos_compatibilidad

        WHERE modelo_id = p_modelo_id AND tabla_estado_registro_id = 1;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;



    OPEN cur;

    loop_modelo: LOOP

        FETCH cur INTO v_producto_id;

        IF v_done THEN LEAVE loop_modelo; END IF;

        CALL sp_recalcular_compatibilidad_producto(v_producto_id);

    END LOOP;

    CLOSE cur;

END$$
DROP PROCEDURE IF EXISTS `sp_recalcular_compatibilidad_por_submodelo`$$
CREATE PROCEDURE `sp_recalcular_compatibilidad_por_submodelo`(IN p_submodelo_id INT UNSIGNED)
BEGIN

    DECLARE v_done INT DEFAULT 0;

    DECLARE v_producto_id BIGINT UNSIGNED;

    DECLARE cur CURSOR FOR

        SELECT DISTINCT producto_id

        FROM gestion__productos_compatibilidad

        WHERE submodelo_id = p_submodelo_id AND tabla_estado_registro_id = 1;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;



    OPEN cur;

    loop_submodelo: LOOP

        FETCH cur INTO v_producto_id;

        IF v_done THEN LEAVE loop_submodelo; END IF;

        CALL sp_recalcular_compatibilidad_producto(v_producto_id);

    END LOOP;

    CLOSE cur;

END$$
DROP PROCEDURE IF EXISTS `sp_refrescar_compatibilidad_vigentes`$$
CREATE PROCEDURE `sp_refrescar_compatibilidad_vigentes`()
BEGIN

    DECLARE v_done INT DEFAULT 0;

    DECLARE v_producto_id BIGINT UNSIGNED;



    -- Solo productos con AL MENOS UNA fila de compatibilidad "abierta":

    -- sin anio_hasta, o con anio_hasta en el año actual o más adelante.

    -- Una compatibilidad ya cerrada en el pasado (ej. "2015-2018") nunca

    -- gana años nuevos, así que no hace falta tocarla de nuevo.

    DECLARE cur CURSOR FOR

        SELECT DISTINCT producto_id

        FROM gestion__productos_compatibilidad

        WHERE tabla_estado_registro_id = 1

          AND (anio_hasta IS NULL OR anio_hasta >= YEAR(CURDATE()));



    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;



    OPEN cur;

    loop_vigentes: LOOP

        FETCH cur INTO v_producto_id;

        IF v_done THEN LEAVE loop_vigentes; END IF;

        CALL sp_recalcular_compatibilidad_producto(v_producto_id);

    END LOOP;

    CLOSE cur;

END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- Limpieza
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
DROP PROCEDURE IF EXISTS `_sync_exec`;
DROP PROCEDURE IF EXISTS `_sync_add_column`;
DROP PROCEDURE IF EXISTS `_sync_add_index`;
DROP PROCEDURE IF EXISTS `_sync_drop_index`;
DROP PROCEDURE IF EXISTS `_sync_add_fk`;
DROP PROCEDURE IF EXISTS `_sync_recrear`;
DROP PROCEDURE IF EXISTS `_sync_renombrar`;
