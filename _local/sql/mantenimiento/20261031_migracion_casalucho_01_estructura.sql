-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================
-- Migracion de PRODUCCION Casa Lucho al formato actual (servidor nuevo)
-- Script 01: ESTRUCTURA
-- 2026-10-31
-- =====================================================================
-- Surge de cargar _local/sql/casalucho_produccion.sql (volcado del 2026-10-31
-- de ps_gestion_casalucho, MySQL 8.0.46) en una base local y compararla con la
-- base local viva (gestion_multipyme) por information_schema. Generado.
-- Produccion esta en el estado del 2026-10-17 (no se le corrio ningun script
-- de sincronizacion posterior): este juego de scripts reemplaza a los
-- 20261025_sync_local_a_produccion_*, que no se corrieron.
-- NO copia datos: la configuracion va en el 02, los datos derivados en el 03 y
-- el borrado de columnas viejas en el 04.
--
-- Orden completo del pase al servidor nuevo:
--   0) Restaurar en el servidor nuevo el volcado de produccion (con rutinas,
--      triggers y eventos) y NO abrir el sistema todavia.
--   1) 20261031_migracion_casalucho_01_estructura.sql   (este)
--   2) 20261031_migracion_casalucho_02_configuracion.sql
--   3) 20261031_migracion_casalucho_03_datos.sql
--   4) 20261031_migracion_casalucho_04_limpieza.sql
--   5) Subir el codigo actual, el .env del servidor nuevo y uploads/
--      (logo de la empresa: uploads/empresas/logo_2_20261010121810_d703529e.png).
--
-- Que hace:
--   1) Matriz Legal: baja de las 23 tablas del prototipo (equivale a
--      20261002_ml_m0_01_baja_prototipo.sql). En produccion estan vacias salvo
--      filas semilla de 3 catalogos; el script 02 carga las de local. Las 13
--      que el modelo nuevo no tiene NO se borran: se renombran con sufijo
--      _bak20261031 y sin sus FKs (2026-11-01).
--   2) Tablas VACIAS con la forma vieja: gestion__perfiles_sucursales,
--      gestion__usuarios_sucursales_excepciones, gestion__comprobantes_imputaciones.
--      Se borran y se crean como en local. Si alguna tuviera filas, el script se
--      corta (no se pierde nada).
--   3) Stock por boca (20261008_stock_01_ddl.sql): gestion__stock,
--      gestion__stock_movimientos, _detalles y _tipos se RENOMBRAN con sufijo
--      _bak20261008. NO se borran: en el volcado tienen 8 saldos, 34 movimientos y
--      110 lineas. El stock nuevo arranca vacio: hay que cargar el inventario
--      inicial con un ajuste de stock (ver la consulta al final del script 03).
--   4) CREATE TABLE IF NOT EXISTS de las 232 tablas que faltan.
--   5) Columnas que faltan en tablas existentes, y las que cambiaron de tipo o
--      pasaron a admitir NULL (se ignoran los anchos de display de los enteros,
--      que MySQL 8 no guarda).
--   6) Indices que faltan. gestion__productos.producto_codigo deja de ser unico
--      global y pasa a unico por empresa (uk_productos_empresa_codigo). Las
--      integraciones ARCA admiten varios certificados por ambiente
--      (20261018_arca_certificado_parametros_01): se reemplazan los UNIQUE
--      uk_integraciones_empresas / uk_integraciones_autenticaciones.
--   7) FKs que faltan (las que tienen datos huerfanos quedan como PENDIENTE).
--
-- NO toca: datos, usuarios, ni lo que existe solo en produccion
-- (conf__imagenes y sus datos, gestion__depositos, ecom__usuarios_entidades,
-- ecom__pedidos_margenes, gestion__ventas_cobranzas,
-- conf__usuarios_password_resets, *_copy, columnas
-- ecom__carritos.margen_porcentaje / ecom__pedidos.venta_pedido_id, triggers
-- trg_* y el evento ev_refrescar_compatibilidad_anual). Las columnas viejas de
-- roles de gestion__entidades (es_cliente, es_proveedor, cont_cuenta_id_*) las
-- borra el script 04, despues de que el 03 cargue gestion__entidades_roles.
--
-- Reanudable: cada paso consulta information_schema antes de ejecutarse (el DDL
-- hace commit implicito, no hay rollback). Correr con el cliente mysql, sin
-- --force:
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261031_migracion_casalucho_01_estructura.sql
-- Antes: respaldo completo de la base.
-- =====================================================================

SET NAMES utf8mb4;
-- Sin USE: la base se indica en la linea de comando.
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET SESSION lock_wait_timeout = 60;
-- Produccion tiene fechas 0000-00-00 (gestion__cont_asientos, accesos_pagina): sin
-- NO_ZERO_DATE / STRICT para que los ALTER que reconstruyen la tabla no fallen en MySQL 8.
SET @OLD_SQL_MODE = @@SQL_MODE;
SET SESSION SQL_MODE = 'NO_ENGINE_SUBSTITUTION';

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
DROP PROCEDURE IF EXISTS `_sync_resguardar`;

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

-- Resguarda p_tabla como p_tabla + '_bak20261031': le quita sus FKs (todas en un solo ALTER: en MariaDB
-- 10.4, borrarlas en sentencias separadas fallo con fk_td_destino) y la renombra. Reanudable.
CREATE PROCEDURE `_sync_resguardar`(IN p_tabla VARCHAR(64))
BEGIN
    DECLARE v_fks TEXT DEFAULT NULL;
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla)
       AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = CONCAT(p_tabla, '_bak20261031')) THEN
        SELECT GROUP_CONCAT(CONCAT('DROP FOREIGN KEY `', CONSTRAINT_NAME, '`') SEPARATOR ', ') INTO v_fks
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla;
        IF v_fks IS NOT NULL THEN
            CALL _sync_exec(CONCAT('ALTER TABLE `', p_tabla, '` ', v_fks));
        END IF;
        CALL _sync_exec(CONCAT('RENAME TABLE `', p_tabla, '` TO `', p_tabla, '_bak20261031`'));
    END IF;
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) Matriz Legal: baja del prototipo (23 tablas)
-- ---------------------------------------------------------------------
-- 13 tablas que el modelo nuevo no tiene: se resguardan como *_bak20261031 (no se borran)
CALL _sync_resguardar('ml__tareas_evidencias');
CALL _sync_resguardar('ml__tareas_dependencias');
CALL _sync_resguardar('ml__tareas_ciclos');
CALL _sync_resguardar('ml__tareas');
CALL _sync_resguardar('ml__obligaciones_raci');
CALL _sync_resguardar('ml__procedimientos_obligaciones');
CALL _sync_resguardar('ml__normas_procedimientos');
CALL _sync_resguardar('ml__normas_ambitos_aplicacion');
CALL _sync_resguardar('ml__roles_organizacionales');
CALL _sync_resguardar('ml__raci_tipos');
CALL _sync_resguardar('ml__jurisdicciones');
CALL _sync_resguardar('ml__jurisdicciones_tipos');
CALL _sync_resguardar('ml__ambitos_organizacionales');
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
-- 2) Tablas vacias con la forma vieja (imputaciones, accesos por sucursal)
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
-- 4) Tablas nuevas (232)
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`banco_id`),
  UNIQUE KEY `uk_bco_codigo_bcra` (`codigo_bcra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- conf__dashboard_cache
CREATE TABLE IF NOT EXISTS `conf__dashboard_cache` (
  `empresa_id` smallint(6) unsigned NOT NULL,
  `widget_id` int(10) unsigned NOT NULL,
  `clave` char(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha1(parámetros + sucursales permitidas)',
  `payload` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculado_en` datetime NOT NULL,
  `expira_en` datetime NOT NULL,
  PRIMARY KEY (`empresa_id`,`widget_id`,`clave`),
  KEY `ix_dc_widget` (`widget_id`),
  KEY `ix_dc_expira` (`expira_en`),
  CONSTRAINT `fk_dc_widget` FOREIGN KEY (`widget_id`) REFERENCES `conf__dashboard_widgets` (`widget_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Caché con vencimiento de los resultados de widgets';

-- conf__dashboard_perfiles_widgets
CREATE TABLE IF NOT EXISTS `conf__dashboard_perfiles_widgets` (
  `perfil_id` int(11) unsigned NOT NULL COMMENT 'Perfil base (conf__perfiles)',
  `widget_id` int(10) unsigned NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `ancho` tinyint(3) unsigned DEFAULT NULL COMMENT 'NULL = ancho_default del widget',
  PRIMARY KEY (`perfil_id`,`widget_id`),
  KEY `ix_dpw_widget` (`widget_id`),
  CONSTRAINT `fk_dpw_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `conf__perfiles` (`perfil_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dpw_widget` FOREIGN KEY (`widget_id`) REFERENCES `conf__dashboard_widgets` (`widget_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Layout por defecto del dashboard por perfil base';

-- conf__dashboard_usuarios_widgets
CREATE TABLE IF NOT EXISTS `conf__dashboard_usuarios_widgets` (
  `usuario_id` int(11) NOT NULL,
  `empresa_id` smallint(6) unsigned NOT NULL,
  `widget_id` int(10) unsigned NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `ancho` tinyint(3) unsigned DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`,`empresa_id`,`widget_id`),
  KEY `ix_duw_widget` (`widget_id`),
  KEY `ix_duw_empresa` (`empresa_id`),
  CONSTRAINT `fk_duw_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_duw_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_duw_widget` FOREIGN KEY (`widget_id`) REFERENCES `conf__dashboard_widgets` (`widget_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Layout del dashboard elegido por el usuario (todos los permitidos, ocultos con visible = 0)';

-- conf__dashboard_widgets
CREATE TABLE IF NOT EXISTS `conf__dashboard_widgets` (
  `widget_id` int(10) unsigned NOT NULL COMMENT 'Explícito: módulo 2 = 1..99, módulo 5 = mismo id + 500',
  `modulo_id` tinyint(3) unsigned NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texto del ? de ayuda',
  `tipo` enum('kpi','lista','minichart','atajos','badge') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'badge = número sobre el acceso directo de pagina_id, no se dibuja como tarjeta',
  `handler` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'core/dashboard/widgets/<handler>.php',
  `pagina_id` int(11) DEFAULT NULL COMMENT 'Permiso y link; NULL solo para atajos',
  `parametros_default` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON como texto',
  `ancho_default` tinyint(3) unsigned NOT NULL DEFAULT 4 COMMENT 'col-lg-N: 3, 4, 6 o 12',
  `ttl_segundos` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT '0 = sin caché',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`widget_id`),
  UNIQUE KEY `uk_dw_modulo_codigo` (`modulo_id`,`codigo`),
  KEY `ix_dw_pagina` (`pagina_id`),
  CONSTRAINT `fk_dw_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `conf__modulos` (`modulo_id`),
  CONSTRAINT `fk_dw_pagina` FOREIGN KEY (`pagina_id`) REFERENCES `conf__paginas` (`pagina_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de widgets del dashboard de inicio';

-- conf__empresas_vinculaciones
CREATE TABLE IF NOT EXISTS `conf__empresas_vinculaciones` (
  `vinculacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_origen_id` smallint(6) unsigned NOT NULL COMMENT 'Empresa que otorga el acceso a sus datos',
  `empresa_destino_id` smallint(6) unsigned NOT NULL COMMENT 'Empresa o Estudio que recibe el acceso',
  `tipo_vinculo` enum('ESTUDIO_CONTABLE','EMPRESA_VINCULADA','AUDITORIA','SINDICATO','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ESTUDIO_CONTABLE' COMMENT 'Tipo de relación',
  `modo_acceso` enum('LECTURA','LECTURA_ESCRITURA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LECTURA' COMMENT 'Nivel de acceso concedido (Lectura o Lectura/Escritura para asientos y liquidaciones)',
  `permiso_ventas` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Acceso a ventas, comprobantes y clientes',
  `permiso_compras` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Acceso a compras, facturas de proveedor y retenciones',
  `permiso_tesoreria` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Acceso a cuentas bancarias, movimientos y pagos',
  `permiso_contabilidad` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Acceso a plan de cuentas, asientos y balances',
  `permiso_stock` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Acceso a articulos, stock y depositos',
  `permiso_sindicatos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Acceso a nomina de personal y aportes sindicales',
  `permiso_impuestos` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Acceso a DDJJ, retenciones y libros IVA',
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date DEFAULT NULL COMMENT 'NULL = sin limite de vigencia',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1 COMMENT '1 = Activa / Autorizada, 2 = Inactiva / Revocada',
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`vinculacion_id`),
  UNIQUE KEY `uk_origen_destino` (`empresa_origen_id`,`empresa_destino_id`),
  KEY `idx_cev_destino` (`empresa_destino_id`,`tabla_estado_registro_id`),
  KEY `idx_cev_origen` (`empresa_origen_id`,`tabla_estado_registro_id`),
  CONSTRAINT `fk_cev_destino` FOREIGN KEY (`empresa_destino_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_cev_origen` FOREIGN KEY (`empresa_origen_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vinculaciones y autorizaciones cross-tenant entre empresas y estudios contables';

-- conf__modulos_incompatibles
CREATE TABLE IF NOT EXISTS `conf__modulos_incompatibles` (
  `modulo_incompatible_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `modulo_id_1` int(10) unsigned NOT NULL,
  `modulo_id_2` int(10) unsigned NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Módulos mutuamente excluyentes',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`modulo_incompatible_id`),
  UNIQUE KEY `uk_modulos_incompatibles` (`modulo_id_1`,`modulo_id_2`),
  KEY `idx_mod_inc_1` (`modulo_id_1`),
  KEY `idx_mod_inc_2` (`modulo_id_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- conf__perfiles_accesos_directos
CREATE TABLE IF NOT EXISTS `conf__perfiles_accesos_directos` (
  `perfil_id` int(11) unsigned NOT NULL COMMENT 'Perfil base (conf__perfiles)',
  `pagina_id` int(11) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`perfil_id`,`pagina_id`),
  KEY `ix_pad_pagina` (`pagina_id`),
  CONSTRAINT `fk_pad_pagina` FOREIGN KEY (`pagina_id`) REFERENCES `conf__paginas` (`pagina_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pad_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `conf__perfiles` (`perfil_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Accesos directos obligatorios por perfil base';

-- conf__usuarios_accesos_directos
CREATE TABLE IF NOT EXISTS `conf__usuarios_accesos_directos` (
  `usuario_id` int(11) NOT NULL,
  `empresa_id` smallint(6) unsigned NOT NULL,
  `pagina_id` int(11) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`usuario_id`,`empresa_id`,`pagina_id`),
  KEY `ix_uad_pagina` (`pagina_id`),
  KEY `ix_uad_empresa` (`empresa_id`),
  CONSTRAINT `fk_uad_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uad_pagina` FOREIGN KEY (`pagina_id`) REFERENCES `conf__paginas` (`pagina_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uad_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Accesos directos elegidos por el usuario';

-- conf__usuarios_inicio
CREATE TABLE IF NOT EXISTS `conf__usuarios_inicio` (
  `usuario_id` int(11) NOT NULL,
  `empresa_id` smallint(6) unsigned NOT NULL,
  `modulo_id` tinyint(3) unsigned NOT NULL,
  `accesos_personalizados` tinyint(1) NOT NULL DEFAULT 0,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`,`empresa_id`,`modulo_id`),
  KEY `ix_ui_empresa` (`empresa_id`),
  KEY `ix_ui_modulo` (`modulo_id`),
  CONSTRAINT `fk_ui_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ui_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `conf__modulos` (`modulo_id`),
  CONSTRAINT `fk_ui_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Marca de personalización de la pantalla de inicio por usuario, empresa y módulo';

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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
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
  `f_traspaso` datetime DEFAULT current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`norma_id`),
  KEY `idx_empresa_vigencia` (`empresa_id`,`vigencia_desde`),
  KEY `idx_organismo` (`organismo_emisor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__normas_clientes_impacto
CREATE TABLE IF NOT EXISTS `estudio__normas_clientes_impacto` (
  `impacto_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `norma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `f_evaluacion` datetime NOT NULL DEFAULT current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `f_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`amortizacion_id`),
  KEY `idx_empresa_periodo` (`empresa_id`,`periodo`,`activo_id`),
  KEY `idx_activo` (`activo_id`),
  KEY `idx_asiento` (`asiento_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__aprobaciones_registro
CREATE TABLE IF NOT EXISTS `gestion__aprobaciones_registro` (
  `aprobacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `documento` enum('OBLIGACION','OP') COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_id` int(10) unsigned NOT NULL COMMENT 'obligacion_id o pago_id',
  `accion` enum('APROBAR','RECHAZAR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `importe` decimal(18,2) NOT NULL DEFAULT 0.00,
  `empresa_perfil_id` int(11) DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `usuario_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`aprobacion_id`),
  KEY `ix_aprreg_doc` (`empresa_id`,`documento`,`registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__aprobaciones_reglas
CREATE TABLE IF NOT EXISTS `gestion__aprobaciones_reglas` (
  `regla_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `documento` enum('OBLIGACION','OP') COLLATE utf8mb4_unicode_ci NOT NULL,
  `obligacion_tipo_id` int(10) unsigned DEFAULT NULL COMMENT 'Solo OBLIGACION: NULL = todos los tipos; con valor gana sobre la general',
  `requiere_aprobacion` tinyint(1) NOT NULL DEFAULT 1,
  `importe_desde` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Requiere aprobacion si el importe es >= a este valor',
  `doble_aprobacion_desde` decimal(18,2) DEFAULT NULL COMMENT 'NULL = una sola; desde este importe, dos aprobaciones de usuarios distintos',
  `permite_autoaprobar` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = quien carga el documento no lo puede aprobar',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`regla_id`),
  KEY `ix_aprreg_empresa_doc` (`empresa_id`,`documento`,`obligacion_tipo_id`),
  KEY `fk_aprreg_tipo` (`obligacion_tipo_id`),
  CONSTRAINT `fk_aprreg_tipo` FOREIGN KEY (`obligacion_tipo_id`) REFERENCES `gestion__obligaciones_tipos` (`obligacion_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__aprobaciones_topes
CREATE TABLE IF NOT EXISTS `gestion__aprobaciones_topes` (
  `tope_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `documento` enum('OBLIGACION','OP') COLLATE utf8mb4_unicode_ci NOT NULL,
  `obligacion_tipo_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = todos los tipos',
  `empresa_perfil_id` int(11) NOT NULL COMMENT 'conf__empresas_perfiles',
  `importe_maximo` decimal(18,2) DEFAULT NULL COMMENT 'NULL = sin tope',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`tope_id`),
  KEY `ix_aprtope_empresa_doc` (`empresa_id`,`documento`,`empresa_perfil_id`),
  KEY `fk_aprtope_tipo` (`obligacion_tipo_id`),
  CONSTRAINT `fk_aprtope_tipo` FOREIGN KEY (`obligacion_tipo_id`) REFERENCES `gestion__obligaciones_tipos` (`obligacion_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_conceptos
CREATE TABLE IF NOT EXISTS `gestion__bancos_conceptos` (
  `concepto_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `concepto` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `naturaleza` enum('GASTO_BANCARIO','MOVIMIENTO_PROPIO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('ITEM','IVA','PERCEPCION_IVA','PERCEPCION_IIBB','PERCEPCION_GANANCIAS','RETENCION_IIBB') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iva_incluido` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = el importe del banco ya incluye el IVA (comision sin linea de IVA aparte): neto = importe / (1 + alicuota del producto)',
  `producto_id` int(10) unsigned DEFAULT NULL COMMENT 'Obligatorio si rol = ITEM (BCO_COMISION, BCO_INTERES, BCO_LEY25413...)',
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL COMMENT 'Obligatorio si rol = PERCEPCION_* (gestion__impuestos_tipos)',
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'Provincia de la percepción IIBB / SIRCREB',
  `es_cobranza` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Concepto que se asigna a las líneas de las que se genera un recibo de cobranza',
  `orden` smallint(5) unsigned NOT NULL DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`concepto_id`),
  UNIQUE KEY `uk_bcon_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `ix_bcon_producto` (`producto_id`),
  KEY `ix_bcon_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `fk_bcon_jurisdiccion_id` (`jurisdiccion_id`),
  CONSTRAINT `fk_bcon_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_bcon_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_bcon_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_conciliaciones
CREATE TABLE IF NOT EXISTS `gestion__bancos_conciliaciones` (
  `conciliacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL,
  `origen` enum('MOVIMIENTOS','GASTOS') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'MOVIMIENTOS = contra tesoreria existente; GASTOS = generada por "Generar comprobante"',
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'GASTOS: C_RB generado',
  `factura_proveedor_id` int(11) DEFAULT NULL COMMENT 'GASTOS: gestion__facturas_proveedores (INT con signo, como su PK)',
  `f_conciliacion` date NOT NULL,
  `importe_extracto` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma con signo del lado extracto',
  `importe_tesoreria` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma con signo del lado tesoreria',
  `diferencia` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 5 COMMENT '5 vigente / 38 deshecha',
  `motivo_deshacer` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`conciliacion_id`),
  KEY `ix_bconc_cuenta_fecha` (`tesoreria_cuenta_id`,`f_conciliacion`),
  KEY `ix_bconc_comprobante` (`comprobante_id`),
  KEY `ix_bconc_factura` (`factura_proveedor_id`),
  CONSTRAINT `fk_bconc_comprobante_id` FOREIGN KEY (`comprobante_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_bconc_factura_proveedor_id` FOREIGN KEY (`factura_proveedor_id`) REFERENCES `gestion__facturas_proveedores` (`factura_proveedor_id`),
  CONSTRAINT `fk_bconc_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_conciliaciones_items
CREATE TABLE IF NOT EXISTS `gestion__bancos_conciliaciones_items` (
  `conciliacion_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conciliacion_id` bigint(20) unsigned NOT NULL,
  `lado` enum('EXTRACTO','TESORERIA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `extracto_id` bigint(20) unsigned DEFAULT NULL COMMENT 'lado EXTRACTO',
  `tesoreria_movimiento_id` bigint(20) unsigned DEFAULT NULL COMMENT 'lado TESORERIA',
  `importe_aplicado` decimal(18,2) NOT NULL COMMENT 'Con signo del lado (egreso < 0)',
  PRIMARY KEY (`conciliacion_item_id`),
  KEY `ix_bcit_conciliacion` (`conciliacion_id`),
  KEY `ix_bcit_extracto` (`extracto_id`),
  KEY `ix_bcit_movimiento` (`tesoreria_movimiento_id`),
  CONSTRAINT `fk_bcit_conciliacion_id` FOREIGN KEY (`conciliacion_id`) REFERENCES `gestion__bancos_conciliaciones` (`conciliacion_id`),
  CONSTRAINT `fk_bcit_extracto_id` FOREIGN KEY (`extracto_id`) REFERENCES `gestion__bancos_extracto` (`extracto_id`),
  CONSTRAINT `fk_bcit_movimiento_id` FOREIGN KEY (`tesoreria_movimiento_id`) REFERENCES `gestion__tesoreria_movimientos` (`tesoreria_movimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_extracto
CREATE TABLE IF NOT EXISTS `gestion__bancos_extracto` (
  `extracto_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `importacion_id` int(10) unsigned NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL,
  `nro_linea` int(10) unsigned NOT NULL COMMENT 'Fila del archivo',
  `f_movimiento` date NOT NULL,
  `f_valor` date DEFAULT NULL,
  `descripcion` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Texto del banco: escapar siempre al mostrar (XSS)',
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N° de cheque / transferencia / CUIT',
  `importe` decimal(18,2) NOT NULL COMMENT 'Con signo: < 0 debito (egreso), > 0 credito (ingreso)',
  `saldo` decimal(18,2) DEFAULT NULL,
  `hash_mov` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 de fecha+descripcion+importe+saldo+ordinal de ocurrencia',
  `concepto_id` int(10) unsigned DEFAULT NULL,
  `concepto_origen` enum('REGLA','MANUAL') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regla_id` int(10) unsigned DEFAULT NULL,
  `estado` enum('PENDIENTE','CLASIFICADA','CONCILIADA','IGNORADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `conciliacion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'MATERIALIZADO desde PHP (sin triggers)',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`extracto_id`),
  UNIQUE KEY `uk_bext_cuenta_hash` (`empresa_id`,`tesoreria_cuenta_id`,`hash_mov`),
  KEY `ix_bext_cuenta_estado_fecha` (`tesoreria_cuenta_id`,`estado`,`f_movimiento`),
  KEY `ix_bext_importacion` (`importacion_id`),
  KEY `ix_bext_concepto` (`concepto_id`),
  KEY `ix_bext_conciliacion` (`conciliacion_id`),
  KEY `ix_bext_regla` (`regla_id`),
  CONSTRAINT `fk_bext_concepto_id` FOREIGN KEY (`concepto_id`) REFERENCES `gestion__bancos_conceptos` (`concepto_id`),
  CONSTRAINT `fk_bext_conciliacion_id` FOREIGN KEY (`conciliacion_id`) REFERENCES `gestion__bancos_conciliaciones` (`conciliacion_id`),
  CONSTRAINT `fk_bext_importacion_id` FOREIGN KEY (`importacion_id`) REFERENCES `gestion__bancos_importaciones` (`importacion_id`),
  CONSTRAINT `fk_bext_regla_id` FOREIGN KEY (`regla_id`) REFERENCES `gestion__bancos_reglas` (`regla_id`),
  CONSTRAINT `fk_bext_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_formatos
CREATE TABLE IF NOT EXISTS `gestion__bancos_formatos` (
  `formato_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `banco_id` smallint(5) unsigned NOT NULL,
  `formato` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre: Nación CC xlsx v2026...',
  `tipo_archivo` enum('XLSX','XLS','CSV') COLLATE utf8mb4_unicode_ci NOT NULL,
  `hoja` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'XLSX: nombre de hoja (NULL = primera)',
  `fila_inicio` smallint(5) unsigned NOT NULL DEFAULT 2 COMMENT 'Primera fila con datos (1 = primera)',
  `delimitador` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CSV',
  `codificacion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTF-8',
  `formato_fecha` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'd/m/Y' COMMENT 'Formato PHP date(); d-M-Y para 21-oct-2026',
  `idioma_fecha` enum('ES','EN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ES' COMMENT 'Meses abreviados en texto (ene, abr, ago, dic...)',
  `orden_archivo` enum('ASC','DESC') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ASC' COMMENT 'DESC = el archivo viene con lo mas nuevo primero (se invierte al importar)',
  `saldo_posterior` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = la columna saldo es el saldo DESPUES del movimiento',
  `cuenta_fila` smallint(5) unsigned DEFAULT NULL COMMENT 'Fila (1 = primera) del encabezado con el n° de cuenta, para validar que el archivo es de la cuenta elegida',
  `cuenta_columna` smallint(5) unsigned DEFAULT NULL COMMENT '0 = columna A',
  `cuenta_regex` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Extrae el n° de cuenta, p.ej. Cuenta:\\s*([0-9\\-/]+)',
  `referencia_regex` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Extrae la referencia de la descripcion (CUIT de 11 digitos, n° de cheque...); grupo 1',
  `sep_decimal` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ',',
  `sep_miles` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modo_importe` enum('SIGNO','DEBITO_CREDITO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SIGNO',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL COMMENT 'NULL = vigente',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`formato_id`),
  KEY `ix_bfmt_empresa_banco` (`empresa_id`,`banco_id`,`f_desde`),
  KEY `fk_bfmt_banco_id` (`banco_id`),
  CONSTRAINT `fk_bfmt_banco_id` FOREIGN KEY (`banco_id`) REFERENCES `conf__bancos` (`banco_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_formatos_columnas
CREATE TABLE IF NOT EXISTS `gestion__bancos_formatos_columnas` (
  `formato_columna_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `formato_id` int(10) unsigned NOT NULL,
  `campo` enum('FECHA','FECHA_VALOR','DESCRIPCION','REFERENCIA','IMPORTE','DEBITO','CREDITO','SALDO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `columna_indice` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`formato_columna_id`),
  UNIQUE KEY `uk_bfcol_formato_campo` (`formato_id`,`campo`),
  CONSTRAINT `fk_bfcol_formato_id` FOREIGN KEY (`formato_id`) REFERENCES `gestion__bancos_formatos` (`formato_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_importaciones
CREATE TABLE IF NOT EXISTS `gestion__bancos_importaciones` (
  `importacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `tesoreria_cuenta_id` int(10) unsigned NOT NULL COMMENT 'Cuenta BANCO',
  `formato_id` int(10) unsigned NOT NULL COMMENT 'Formato usado (auditoria)',
  `archivo_nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256: evita reimportar el mismo archivo',
  `archivo_ruta` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Fuera del webroot',
  `f_desde` date DEFAULT NULL,
  `f_hasta` date DEFAULT NULL,
  `saldo_inicial` decimal(18,2) DEFAULT NULL,
  `saldo_final` decimal(18,2) DEFAULT NULL,
  `cantidad_movimientos` int(10) unsigned NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 5 COMMENT '5 vigente / 38 anulado',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`importacion_id`),
  UNIQUE KEY `uk_bimp_cuenta_hash` (`empresa_id`,`tesoreria_cuenta_id`,`archivo_hash`),
  KEY `ix_bimp_cuenta_periodo` (`tesoreria_cuenta_id`,`f_hasta`),
  KEY `ix_bimp_formato` (`formato_id`),
  CONSTRAINT `fk_bimp_formato_id` FOREIGN KEY (`formato_id`) REFERENCES `gestion__bancos_formatos` (`formato_id`),
  CONSTRAINT `fk_bimp_tesoreria_cuenta_id` FOREIGN KEY (`tesoreria_cuenta_id`) REFERENCES `gestion__tesoreria_cuentas` (`tesoreria_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__bancos_reglas
CREATE TABLE IF NOT EXISTS `gestion__bancos_reglas` (
  `regla_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `banco_id` smallint(5) unsigned NOT NULL,
  `concepto_id` int(10) unsigned NOT NULL,
  `prioridad` smallint(5) unsigned NOT NULL DEFAULT 100 COMMENT 'Menor = primero; gana la primera que coincide',
  `tipo_coincidencia` enum('CONTIENE','EMPIEZA','REGEX','IGUAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONTIENE',
  `patron` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'REGEX: validar y limitar largo en PHP; IGUAL: descripción normalizada',
  `aplica_a` enum('DEBITO','CREDITO','AMBOS') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AMBOS',
  `origen` enum('MANUAL','APRENDIDA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL' COMMENT 'APRENDIDA = creada al asignar un concepto a mano a una línea del extracto',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`regla_id`),
  KEY `ix_breg_empresa_banco` (`empresa_id`,`banco_id`,`prioridad`),
  KEY `ix_breg_concepto` (`concepto_id`),
  KEY `fk_breg_banco_id` (`banco_id`),
  KEY `ix_breg_aprendida` (`empresa_id`,`banco_id`,`tipo_coincidencia`,`aplica_a`),
  CONSTRAINT `fk_breg_banco_id` FOREIGN KEY (`banco_id`) REFERENCES `conf__bancos` (`banco_id`),
  CONSTRAINT `fk_breg_concepto_id` FOREIGN KEY (`concepto_id`) REFERENCES `gestion__bancos_conceptos` (`concepto_id`)
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `retencion_certificado_estado` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PENDIENTE / RECIBIDO / CONCILIADO (solo RETENCION)',
  `retencion_f_certificado` date DEFAULT NULL COMMENT 'Fecha del certificado',
  `retencion_regimen` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Régimen / concepto del certificado',
  `retencion_periodo_computo` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'AAAA-MM de cómputo (IVA y cierres)',
  `retencion_adjunto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Archivo del certificado (uploads/retenciones/<empresa>/)',
  `retencion_base_imponible` decimal(18,2) DEFAULT NULL,
  `retencion_alicuota` decimal(10,6) DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`imputacion_id`),
  KEY `ix_imp_empresa_destino` (`empresa_id`,`comp_destino_id`),
  KEY `ix_imp_origen` (`comp_origen_id`),
  KEY `ix_imp_destino_estado` (`comp_destino_id`,`tabla_estado_registro_id`),
  KEY `ix_imp_moneda_origen` (`moneda_origen_id`),
  KEY `ix_imp_empresa_origen` (`empresa_id`,`comp_origen_id`),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`parametro_id`),
  UNIQUE KEY `uk_empresa_param` (`empresa_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__cont_periodos_cierres
CREATE TABLE IF NOT EXISTS `gestion__cont_periodos_cierres` (
  `cierre_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `mes` tinyint(3) unsigned NOT NULL COMMENT '1-12',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `motivo_reapertura` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reapertura_id` int(11) DEFAULT NULL,
  `fecha_reapertura` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 5 COMMENT '5 Cerrado / 38 Reabierto',
  PRIMARY KEY (`cierre_id`),
  KEY `ix_cpc_busqueda` (`empresa_id`,`anio`,`mes`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Meses cerrados de la contabilidad por empresa (bloquean los asientos de ese mes)';

-- gestion__documentos_extracciones
CREATE TABLE IF NOT EXISTS `gestion__documentos_extracciones` (
  `extraccion_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `adjunto_id` int(11) NOT NULL,
  `capa` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'QR / TEXTO / OCR / IA: la que aport├│ los datos (IA = llamada a la API)',
  `proveedor_ia` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tokens_entrada` int(11) DEFAULT NULL,
  `tokens_salida` int(11) DEFAULT NULL,
  `costo_estimado` decimal(12,6) DEFAULT NULL COMMENT 'USD seg├║n los precios de la configuraci├│n',
  `duracion_ms` int(11) DEFAULT NULL,
  `resultado` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'OK / INCOMPLETO / ERROR',
  `mensaje` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datos` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Resultado normalizado (JSON como texto)',
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`extraccion_id`),
  KEY `idx_dex_tope` (`empresa_id`,`capa`,`fecha`),
  KEY `idx_dex_adjunto` (`adjunto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de lecturas de comprobantes desde archivo';

-- gestion__empresas_autorizaciones_copia
CREATE TABLE IF NOT EXISTS `gestion__empresas_autorizaciones_copia` (
  `autorizacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_origen_id` smallint(6) unsigned NOT NULL COMMENT 'Empresa que autoriza el acceso a sus datos',
  `empresa_destino_id` smallint(6) unsigned NOT NULL COMMENT 'Empresa autorizada a copiar los datos',
  `permite_plan_cuentas` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia del plan de cuentas',
  `permite_conceptos_contables` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia de cuentas por concepto contable',
  `permite_roles_cuentas` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia de cuentas por rol de entidad',
  `permite_comprobantes_tipos` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia de tipos de comprobantes',
  `permite_condiciones_pago` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia de condiciones de pago',
  `permite_sucursales_bocas` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia de sucursales y bocas base',
  `permite_listas_precios` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia de listas de precios base',
  `permite_parametros` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Autoriza copia de parametros generales',
  `permite_datos_basicos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Autoriza copia de monedas, tipos de asiento, alicuotas de IVA, tipos y categorias de producto y categorias de gasto',
  `permite_tesoreria` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Autoriza copia de cajas, cuentas de valores y cupones (sin saldo) y medios de cobro y pago',
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date DEFAULT NULL COMMENT 'NULL = sin limite de vigencia',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1 COMMENT '1 = Activa/Autorizada, 2 = Inactiva/Revocada',
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`autorizacion_id`),
  UNIQUE KEY `uk_origen_destino` (`empresa_origen_id`,`empresa_destino_id`),
  KEY `idx_eac_origen` (`empresa_origen_id`,`tabla_estado_registro_id`),
  KEY `idx_eac_destino` (`empresa_destino_id`,`tabla_estado_registro_id`),
  CONSTRAINT `fk_eac_destino` FOREIGN KEY (`empresa_destino_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_eac_origen` FOREIGN KEY (`empresa_origen_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Autorizaciones interempresa para copia de configuración básica';

-- gestion__empresas_copia_log
CREATE TABLE IF NOT EXISTS `gestion__empresas_copia_log` (
  `copia_log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_origen_id` smallint(6) unsigned NOT NULL,
  `empresa_destino_id` smallint(6) unsigned NOT NULL,
  `autorizacion_id` int(10) unsigned DEFAULT NULL,
  `componentes_solicitados` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON de componentes solicitados',
  `detalle_ejecucion` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON con detalle de registros creados por tabla',
  `total_registros_creados` int(10) unsigned NOT NULL DEFAULT 0,
  `usuario_id` int(11) DEFAULT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_copia` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`copia_log_id`),
  KEY `idx_ecl_origen` (`empresa_origen_id`),
  KEY `idx_ecl_destino` (`empresa_destino_id`),
  KEY `idx_ecl_autorizacion` (`autorizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de auditoría de copias de datos iniciales interempresa';

-- gestion__empresas_cuentas_conceptos
CREATE TABLE IF NOT EXISTS `gestion__empresas_cuentas_conceptos` (
  `empresa_cuenta_concepto_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `concepto` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo del concepto: VALORES_A_PAGAR, EMPLEADOS. Lo siembra el script 02; el ABM no crea conceptos',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta por defecto; NULL hasta configurarla. El ABM valida imputable, activa y de la misma empresa',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`empresa_cuenta_concepto_id`),
  UNIQUE KEY `uk_ecc_empresa_concepto` (`empresa_id`,`concepto`),
  KEY `ix_ecc_cont_cuenta` (`cont_cuenta_id`),
  CONSTRAINT `fk_ecc_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='D35/D36: cuentas contables POR DEFECTO de la empresa por concepto. Al armar un asiento: 1) cuenta especifica del maestro (entidad, cuenta de tesoreria, medio de pago, categoria de gasto); 2) este concepto de la empresa; 3) si no hay ninguna, error y el documento no se confirma. Nunca un id de cuenta fijo en el codigo.';

-- gestion__empresas_iibb_inscripciones
CREATE TABLE IF NOT EXISTS `gestion__empresas_iibb_inscripciones` (
  `inscripcion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `jurisdiccion_id` int(11) NOT NULL COMMENT 'gestion__jurisdicciones (provincia)',
  `iibb_regimen_id` int(11) NOT NULL COMMENT 'gestion__iibb_regimenes: LOCAL / CONVENIO_MULTILATERAL / EXENTO',
  `nro_inscripcion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_sede` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Jurisdicción sede (CM) o de la inscripción local',
  `coeficiente_cm` decimal(9,6) DEFAULT NULL COMMENT 'Coeficiente unificado de Convenio Multilateral (0 a 1)',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  PRIMARY KEY (`inscripcion_id`),
  KEY `ix_eii_empresa` (`empresa_id`,`jurisdiccion_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscripciones de la empresa en Ingresos Brutos por jurisdicción';

-- gestion__empresas_impresion
CREATE TABLE IF NOT EXISTS `gestion__empresas_impresion` (
  `empresa_impresion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `comprobante_subgrupo_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = todos los comprobantes de la empresa',
  `formato` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'A4' COMMENT 'A4 | TICKET80',
  `copias_defecto` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '1 a 3 (original, duplicado, triplicado)',
  `mostrar_logo` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_codigo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'columna de c├│digo de producto',
  `mostrar_bonificacion` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'columna % de bonificaci├│n',
  `mostrar_sucursal_cliente` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'sucursal / lugar de entrega del cliente',
  `texto_pie` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'texto libre al pie (datos bancarios, garant├¡a, etc.)',
  `color_acento` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '#RRGGBB para encabezados de tabla; NULL = gris',
  `fuente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tipograf├¡a (clave de IMPRESION_FUENTES en core/impresion.php); NULL = roboto',
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint(5) unsigned NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo (gen├®rico)',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`empresa_impresion_id`),
  UNIQUE KEY `uk_empresa_impresion` (`empresa_id`,`comprobante_subgrupo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuraci├│n de impresi├│n de comprobantes por empresa y subgrupo';

-- gestion__empresas_impuestos_config_situaciones
CREATE TABLE IF NOT EXISTS `gestion__empresas_impuestos_config_situaciones` (
  `config_situacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_impuesto_config_id` int(11) NOT NULL,
  `situacion` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PADRON | INSCRIPTO_JURISDICCION | SIMPLIFICADO_JURISDICCION | INSCRIPTO_OTRA_JURISDICCION | NO_INSCRIPTO | EXENTO | BIEN_DE_USO',
  `requiere_territorio` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = solo si la operaci├│n es en la jurisdicci├│n',
  `alicuota` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'PADRON: se ignora (manda el padr├│n)',
  `tipo_registro_ddjj` tinyint(4) DEFAULT NULL COMMENT 'SIRCIP campo 5: 1 Percepci├│n, 2 Informativo, 3 Excluido, 4 No inscripto, 5 Sobretasa',
  `declarar_si_cero` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = con al├¡cuota 0 igual se registra y va a la DDJJ',
  `leyenda` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texto en la factura; NULL = "Percepci├│n IIBB <jurisdicci├│n>"',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  PRIMARY KEY (`config_situacion_id`),
  UNIQUE KEY `uk_config_situacion` (`empresa_impuesto_config_id`,`situacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Percepciones: al├¡cuota seg├║n la situaci├│n del cliente frente al impuesto';

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
  `mes_cierre_ejercicio` tinyint(3) unsigned NOT NULL DEFAULT 12 COMMENT 'Mes de cierre del ejercicio contable (1-12)',
  `leyenda_fiscal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arca_habilitado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = las facturas fiscales se confirman reci├®n con el CAE de ARCA',
  `arca_integracion_empresa_id` int(10) unsigned DEFAULT NULL COMMENT 'Integración WSFE (certificado + ambiente) con la que se pide el CAE; NULL = la única activa',
  `compra_actualiza_lista_proveedor` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Factura de proveedor confirmada: 0 = no toca la lista del proveedor, 1 = solo carga los que no tienen precio, 2 = siempre (pisa)',
  `compra_actualiza_listas_venta` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Factura de proveedor confirmada: 1 = recalcula las listas de venta por regla con el costo nuevo',
  `logo_archivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Logo para comprobantes impresos (ruta relativa a la ra├¡z, uploads/empresas/)',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ia_lectura_habilitada` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = la lectura de facturas de proveedores puede mandar el archivo a la IA',
  `ia_lectura_tope_diario` int(11) NOT NULL DEFAULT 20 COMMENT 'Lecturas con IA por d├¡a para la empresa',
  `ia_api_key_cifrada` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clave de la API de la IA de la empresa, cifrada (core/secretos.php)',
  PRIMARY KEY (`empresa_parametro_id`),
  KEY `idx_empresa_vigencia` (`empresa_id`,`f_hasta`,`tabla_estado_registro_id`),
  KEY `idx_condicion_fiscal` (`condicion_fiscal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__empresas_roles_cuentas
CREATE TABLE IF NOT EXISTS `gestion__empresas_roles_cuentas` (
  `empresa_rol_cuenta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `rol_entidad_id` tinyint(3) unsigned NOT NULL,
  `cont_cuenta_id` int(10) unsigned NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`empresa_rol_cuenta_id`),
  UNIQUE KEY `uk_empresa_rol` (`empresa_id`,`rol_entidad_id`),
  KEY `ix_erc_cuenta` (`cont_cuenta_id`),
  KEY `fk_erc_rol` (`rol_entidad_id`),
  CONSTRAINT `fk_erc_cuenta` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_erc_rol` FOREIGN KEY (`rol_entidad_id`) REFERENCES `gestion__roles_entidades` (`rol_entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas contables por defecto de la empresa para cada rol de entidad';

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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`entidad_condicion_iibb_id`),
  KEY `idx_entidad_iibb` (`entidad_id`,`tabla_estado_registro_id`,`f_desde`,`f_hasta`),
  KEY `fk_entidad_iibb_regimen` (`regimen_iibb_id`),
  KEY `fk_entidad_iibb_sede` (`jurisdiccion_sede_id`),
  CONSTRAINT `fk_entidad_iibb_regimen` FOREIGN KEY (`regimen_iibb_id`) REFERENCES `gestion__regimenes_iibb` (`regimen_iibb_id`),
  CONSTRAINT `fk_entidad_iibb_sede` FOREIGN KEY (`jurisdiccion_sede_id`) REFERENCES `gestion__iibb_jurisdicciones_sedes` (`jurisdiccion_sede_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__entidades_condiciones_iibb_jurisdicciones
CREATE TABLE IF NOT EXISTS `gestion__entidades_condiciones_iibb_jurisdicciones` (
  `entidad_condicion_iibb_jurisdiccion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entidad_condicion_iibb_id` int(10) unsigned NOT NULL,
  `jurisdiccion_sede_id` int(10) unsigned NOT NULL,
  `f_desde` date DEFAULT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`entidad_condicion_iibb_jurisdiccion_id`),
  UNIQUE KEY `uk_cond_iibb_jur` (`entidad_condicion_iibb_id`,`jurisdiccion_sede_id`),
  KEY `idx_cond_iibb_jur_sede` (`jurisdiccion_sede_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jurisdicciones de alta en IIBB de un cliente (Convenio Multilateral)';

-- gestion__entidades_iibb_exclusiones
CREATE TABLE IF NOT EXISTS `gestion__entidades_iibb_exclusiones` (
  `entidad_iibb_exclusion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `entidad_id` int(10) unsigned NOT NULL,
  `sentido` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PERCEPCION' COMMENT 'PERCEPCION | RETENCION',
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL COMMENT 'Retenci├│n: impuesto practicado; NULL = percepci├│n IIBB',
  `jurisdiccion_id` int(11) NOT NULL,
  `porcentaje_exclusion` decimal(5,2) NOT NULL DEFAULT 100.00,
  `numero_certificado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`entidad_iibb_exclusion_id`),
  KEY `idx_excl_entidad` (`empresa_id`,`entidad_id`,`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificados de exclusi├│n / no percepci├│n de IIBB de clientes';

-- gestion__entidades_retenciones
CREATE TABLE IF NOT EXISTS `gestion__entidades_retenciones` (
  `entidad_retencion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `entidad_id` int(10) unsigned NOT NULL,
  `impuesto_tipo_id` smallint(5) unsigned NOT NULL,
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'IIBB: provincia; NULL = nacional',
  `condicion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'INSCRIPTO | NO_INSCRIPTO | EXENTO | NO_ALCANZADO',
  `regimen_id` int(10) unsigned DEFAULT NULL COMMENT 'R├®gimen habitual del proveedor para este impuesto',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_retencion_id`),
  KEY `ix_eret_entidad` (`empresa_id`,`entidad_id`,`impuesto_tipo_id`,`f_desde`),
  KEY `ix_eret_regimen` (`regimen_id`),
  KEY `fk_eret_impuesto_tipo_id` (`impuesto_tipo_id`),
  CONSTRAINT `fk_eret_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_eret_regimen_id` FOREIGN KEY (`regimen_id`) REFERENCES `gestion__retenciones_regimenes` (`regimen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Condici├│n del proveedor frente a cada retenci├│n, con vigencia';

-- gestion__facturas_proveedores_adjuntos
CREATE TABLE IF NOT EXISTS `gestion__facturas_proveedores_adjuntos` (
  `adjunto_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `factura_proveedor_id` int(11) DEFAULT NULL COMMENT 'NULL hasta que se guarda la factura',
  `archivo_ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relativa a la ra├¡z: uploads/facturas_proveedores/<empresa>/<nombre aleatorio>',
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanio_bytes` int(11) NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_alta` datetime NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`adjunto_id`),
  KEY `idx_fpa_factura` (`empresa_id`,`factura_proveedor_id`),
  KEY `idx_fpa_hash` (`empresa_id`,`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archivo original de las facturas de proveedores cargadas desde archivo';

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

-- gestion__impuestos_periodos_cierres
CREATE TABLE IF NOT EXISTS `gestion__impuestos_periodos_cierres` (
  `cierre_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `impuesto_tipo_id` smallint(5) unsigned NOT NULL COMMENT 'Obligación: 6, 7, 8, 9, 14-17 (periodos_fiscales_core_model.php)',
  `jurisdiccion_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = nacional',
  `periodo` char(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AAAA-MM',
  `origen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL' COMMENT 'MANUAL / IVA_POSICION',
  `referencia_id` int(11) DEFAULT NULL COMMENT 'iva_posicion_id (IVA_POSICION)',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `motivo_reapertura` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_reapertura_id` int(11) DEFAULT NULL,
  `fecha_reapertura` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 5 COMMENT '5 Cerrado / 38 Reabierto',
  PRIMARY KEY (`cierre_id`),
  KEY `ix_ipc_busqueda` (`empresa_id`,`impuesto_tipo_id`,`periodo`,`tabla_estado_registro_id`),
  KEY `ix_ipc_origen` (`origen`,`referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Períodos fiscales cerrados por empresa, impuesto y jurisdicción';

-- gestion__integraciones_delegaciones
CREATE TABLE IF NOT EXISTS `gestion__integraciones_delegaciones` (
  `integracion_delegacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL COMMENT 'Empresa titular de la integración (la que delega)',
  `integracion_empresa_id` int(10) unsigned NOT NULL,
  `empresa_destino_id` smallint(5) unsigned NOT NULL COMMENT 'Empresa que puede usar el certificado',
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_creacion_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Revocada',
  PRIMARY KEY (`integracion_delegacion_id`),
  UNIQUE KEY `uk_integraciones_delegaciones` (`integracion_empresa_id`,`empresa_destino_id`),
  KEY `ix_integraciones_delegaciones_destino` (`empresa_destino_id`,`tabla_estado_registro_id`),
  KEY `ix_integraciones_delegaciones_empresa` (`empresa_id`),
  CONSTRAINT `fk_integraciones_delegaciones_integracion` FOREIGN KEY (`integracion_empresa_id`) REFERENCES `gestion__integraciones_empresas` (`integracion_empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificados ARCA delegados por la empresa titular a otras empresas';

-- gestion__iva_posiciones
CREATE TABLE IF NOT EXISTS `gestion__iva_posiciones` (
  `iva_posicion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `periodo` char(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AAAA-MM',
  `debito_fiscal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `credito_fiscal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `percepciones_iva` decimal(18,2) NOT NULL DEFAULT 0.00,
  `retenciones_iva` decimal(18,2) NOT NULL DEFAULT 0.00,
  `saldo_favor_anterior` decimal(18,2) NOT NULL DEFAULT 0.00,
  `saldo_a_pagar` decimal(18,2) NOT NULL DEFAULT 0.00,
  `saldo_a_favor` decimal(18,2) NOT NULL DEFAULT 0.00,
  `comprobante_id` int(11) DEFAULT NULL COMMENT 'I_LIQ generada cuando da a pagar',
  `cont_asiento_id` int(10) unsigned DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 5,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`iva_posicion_id`),
  KEY `idx_iva_pos_empresa_periodo` (`empresa_id`,`periodo`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Posición mensual de IVA liquidada';

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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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

-- gestion__obligaciones_importaciones
CREATE TABLE IF NOT EXISTS `gestion__obligaciones_importaciones` (
  `importacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `archivo_nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_ruta` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'uploads/obligaciones/<empresa>/ (.htaccess deny)',
  `obligacion_tipo_id` int(10) unsigned DEFAULT NULL COMMENT 'Tipo fijo para todo el archivo (NULL = columna tipo)',
  `filas_leidas` int(10) unsigned NOT NULL DEFAULT 0,
  `filas_importadas` int(10) unsigned NOT NULL DEFAULT 0,
  `filas_error` int(10) unsigned NOT NULL DEFAULT 0,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `confirmar_al_importar` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 3 COMMENT '3 previsualizada / 5 importada / 38 deshecha',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`importacion_id`),
  KEY `ix_oblimp_empresa` (`empresa_id`,`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__obligaciones_pago
CREATE TABLE IF NOT EXISTS `gestion__obligaciones_pago` (
  `obligacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `obligacion_tipo_id` int(10) unsigned DEFAULT NULL COMMENT 'gestion__obligaciones_tipos',
  `origen` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL' COMMENT 'MANUAL / IMPORTACION / IVA_POSICION / PERCEPCIONES_IIBB / RETENCIONES_LIQ / SUELDOS',
  `origen_registro_id` int(10) unsigned DEFAULT NULL COMMENT 'Id en la tabla del generador',
  `importacion_id` int(10) unsigned DEFAULT NULL COMMENT 'gestion__obligaciones_importaciones',
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `comprobante_tipo_id` int(10) unsigned NOT NULL,
  `comprobante_pv` smallint(5) unsigned NOT NULL COMMENT 'punto_venta_id (gestion__puntos_venta)',
  `comprobante_nro` int(11) NOT NULL DEFAULT 0 COMMENT '0 en Borrador; se numera al confirmar',
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'gestion__comprobantes (I_LIQ). Sin FK: ver encabezado (D1)',
  `entidad_id` int(10) unsigned NOT NULL COMMENT 'Beneficiario: empleado, organismo, proveedor (el rol lo fija el tipo)',
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `f_vto` date NOT NULL COMMENT 'Primer vencimiento',
  `f_vto_2` date DEFAULT NULL COMMENT 'Segundo vencimiento (con recargo)',
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL COMMENT 'Solo obligaciones impositivas',
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'IIBB / tasas: jurisdiccion; NULL nacionales',
  `periodo_fiscal` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'YYYY-MM o YYYY (opcional)',
  `cuota_nro` tinyint(3) unsigned DEFAULT NULL COMMENT 'Anticipo / cuota de plan',
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Sueldo octubre 2026, IVA marzo 2026...',
  `nro_ddjj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nro_vep` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N° de recibo de sueldo, contrato, boleta...',
  `obligacion_rectificada_id` int(10) unsigned DEFAULT NULL COMMENT 'DDJJ original que esta rectifica',
  `cont_cuenta_id` int(10) unsigned DEFAULT NULL COMMENT 'Pasivo a pagar: lo cancela la OP (se fija al confirmar)',
  `cont_cuenta_debe_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta del Debe del devengamiento (se fija al confirmar)',
  `aprobaciones_requeridas` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Las que fijo la regla al pedir la aprobacion',
  `moneda_id` smallint(5) unsigned NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe a pagar',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_id`),
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
  KEY `ix_obli_obligacion_tipo` (`obligacion_tipo_id`),
  KEY `ix_obli_importacion` (`importacion_id`),
  KEY `ix_obli_origen` (`empresa_id`,`origen`,`origen_registro_id`),
  KEY `ix_obli_cont_cuenta_debe` (`cont_cuenta_debe_id`),
  CONSTRAINT `fk_obli_comprobante_pv` FOREIGN KEY (`comprobante_pv`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_obli_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_obli_cont_cuenta_debe_id` FOREIGN KEY (`cont_cuenta_debe_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_obli_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_obli_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `fk_obli_importacion_id` FOREIGN KEY (`importacion_id`) REFERENCES `gestion__obligaciones_importaciones` (`importacion_id`),
  CONSTRAINT `fk_obli_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_obli_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_obli_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_obli_obligacion_tipo_id` FOREIGN KEY (`obligacion_tipo_id`) REFERENCES `gestion__obligaciones_tipos` (`obligacion_tipo_id`),
  CONSTRAINT `fk_obli_sucursal_id` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__obligaciones_tipos
CREATE TABLE IF NOT EXISTS `gestion__obligaciones_tipos` (
  `obligacion_tipo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unico por empresa; lo usan la importacion y los generadores (IMP_IVA, SUELDO...)',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clase` enum('OBLIGACION','ANTICIPO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OBLIGACION' COMMENT 'ANTICIPO: el pago queda como saldo a favor de la OP para aplicar despues',
  `rol_entidad_codigo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'gestion__roles_entidades: EMP / PRO / ORG; NULL = cualquiera',
  `comprobante_tipo_codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OBL' COMMENT 'I_LIQ / OBL / SOL_ANT (se resuelve por empresa)',
  `cont_cuenta_debe_id` int(10) unsigned DEFAULT NULL COMMENT 'Gasto del devengamiento (NULL = concepto GASTOS_GENERALES; anticipos: cuenta del rol de la entidad)',
  `cont_cuenta_pasivo_id` int(10) unsigned DEFAULT NULL COMMENT 'Pasivo a pagar (NULL = la cuenta del rol de la entidad)',
  `permite_manual` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = se carga e importa en Obligaciones de Pago; 0 = solo la genera el sistema',
  `origen_sistema` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Generador dueño del tipo: IVA_POSICION, PERCEPCIONES_IIBB, RETENCIONES_LIQ...',
  `impuesto_tipo_id` smallint(5) unsigned DEFAULT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 100,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_tipo_id`),
  UNIQUE KEY `uk_obltipo_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `ix_obltipo_debe` (`cont_cuenta_debe_id`),
  KEY `ix_obltipo_pasivo` (`cont_cuenta_pasivo_id`),
  CONSTRAINT `fk_obltipo_debe` FOREIGN KEY (`cont_cuenta_debe_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_obltipo_pasivo` FOREIGN KEY (`cont_cuenta_pasivo_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__padrones_iibb
CREATE TABLE IF NOT EXISTS `gestion__padrones_iibb` (
  `padron_iibb_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `jurisdiccion_id` int(11) NOT NULL,
  `padron_importacion_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL en WS y MANUAL',
  `periodo` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Obsoleto: usar f_desde/f_hasta',
  `cuit` char(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(70) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alicuota_percepcion` decimal(10,4) NOT NULL,
  `alicuota_retencion` decimal(10,4) DEFAULT NULL COMMENT 'Al├¡cuota de retenci├│n IIBB (%); NULL = no informada',
  `letra_alicuota` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crc` tinyint(3) unsigned DEFAULT NULL,
  `situacion_jurisdiccion` tinyint(4) DEFAULT NULL COMMENT 'D├¡gito del campo 7 de SIRCIP para esta jurisdicci├│n',
  `jurisdiccion_sede_cm` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fuente` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ARCHIVO' COMMENT 'ARCHIVO | WS | MANUAL',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `situacion` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PADRON | INSCRIPTO_OTRA_JURISDICCION | NO_PERCIBIR',
  `codigo_origen` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código crudo de la fuente (letra, grupo)',
  `referencia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MANUAL: N° de constancia / archivo adjunto',
  `integracion_resultado_id` bigint(20) unsigned DEFAULT NULL COMMENT 'WS: log en gestion__integraciones_resultados',
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`padron_iibb_id`),
  UNIQUE KEY `uk_padron_cuit_vig` (`empresa_id`,`jurisdiccion_id`,`cuit`,`fuente`,`f_desde`),
  KEY `idx_padron_importacion` (`padron_importacion_id`),
  KEY `idx_padron_busqueda` (`empresa_id`,`jurisdiccion_id`,`cuit`,`f_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Padr├│n de al├¡cuotas de percepci├│n IIBB por CUIT y per├¡odo';

-- gestion__padrones_iibb_importaciones
CREATE TABLE IF NOT EXISTS `gestion__padrones_iibb_importaciones` (
  `padron_importacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `jurisdiccion_id` int(11) NOT NULL,
  `formato` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `periodo` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'aaaamm (padrones mensuales)',
  `archivo_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_hash` char(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha1',
  `filas_leidas` int(11) NOT NULL DEFAULT 0,
  `filas_cargadas` int(11) NOT NULL DEFAULT 0,
  `filas_rechazadas` int(11) NOT NULL DEFAULT 0,
  `observaciones` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Vigente / 2 Reemplazada',
  `f_desde` date DEFAULT NULL,
  `f_hasta` date DEFAULT NULL,
  PRIMARY KEY (`padron_importacion_id`),
  KEY `idx_pi_imp_periodo` (`empresa_id`,`jurisdiccion_id`,`periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de importaciones de padrones de IIBB';

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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `pago_medio_id` int(10) unsigned DEFAULT NULL COMMENT 'L├¡nea RETENCION de gestion__pagos_medios',
  `entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'Proveedor retenido',
  `impuesto_tipo_id` smallint(5) unsigned NOT NULL COMMENT 'Tipo de retencion practicada (es_retencion = 1, aplica_compra = 1)',
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'IIBB: jurisdiccion; NULL nacionales',
  `empresa_impuesto_config_id` int(11) DEFAULT NULL COMMENT 'Config del agente vigente al calcular',
  `regimen_id` int(10) unsigned DEFAULT NULL COMMENT 'gestion__retenciones_regimenes; el texto queda en regimen',
  `regimen` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Regimen o concepto de la retencion',
  `condicion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Condici├│n del proveedor al calcular',
  `f_retencion` date DEFAULT NULL COMMENT 'Fecha de la OP; per├¡odo de la retenci├│n',
  `base_imponible` decimal(18,2) NOT NULL DEFAULT 0.00,
  `base_acumulada_mes` decimal(18,2) DEFAULT NULL COMMENT 'R├®gimen acumulado mensual: base del mes incluida esta OP',
  `retenciones_previas_mes` decimal(18,2) DEFAULT NULL COMMENT 'R├®gimen acumulado mensual: retenido antes en el mes',
  `porcentaje_exclusion` decimal(5,2) DEFAULT NULL COMMENT 'Certificado de exclusi├│n aplicado',
  `alicuota` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `origen_alicuota` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PADRON | GENERICA | MANUAL',
  `importe` decimal(18,2) NOT NULL COMMENT 'Importe retenido en moneda base (pesos)',
  `importe_calculado` decimal(18,2) DEFAULT NULL COMMENT 'Lo que calcula la config al guardar; NULL = no corresponde retener',
  `motivo_ajuste` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Obligatorio si importe difiere de importe_calculado',
  `certificado_nro` int(11) DEFAULT NULL COMMENT 'NULL en Borrador; numeracion propia por empresa e impuesto al confirmar la OP',
  `comp_liquidacion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'I_LIQ que la incluyo; NULL = pendiente de liquidar',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 3 COMMENT '3 Borrador, 5 Confirmada (con certificado), 38 Anulada (conserva el n├║mero)',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`retencion_id`),
  UNIQUE KEY `uk_pret_certificado_jur` (`empresa_id`,`impuesto_tipo_id`,`jurisdiccion_id`,`certificado_nro`),
  KEY `ix_pret_pendientes` (`empresa_id`,`impuesto_tipo_id`,`comp_liquidacion_id`),
  KEY `ix_pret_pago` (`pago_id`),
  KEY `ix_pret_impuesto_tipo` (`impuesto_tipo_id`),
  KEY `ix_pret_jurisdiccion` (`jurisdiccion_id`),
  KEY `ix_pret_comp_liquidacion` (`comp_liquidacion_id`),
  KEY `ix_pret_pago_medio` (`pago_medio_id`),
  KEY `ix_pret_periodo` (`empresa_id`,`impuesto_tipo_id`,`f_retencion`),
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
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `jurisdiccion_id` int(11) NOT NULL DEFAULT 0 COMMENT 'IIBB: jurisdicci├│n; 0 = impuestos nacionales',
  `ultimo_numero` int(11) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`retencion_numerador_id`),
  UNIQUE KEY `uk_rnum_empresa_impuesto_jur` (`empresa_id`,`impuesto_tipo_id`,`jurisdiccion_id`),
  KEY `ix_rnum_impuesto_tipo` (`impuesto_tipo_id`),
  CONSTRAINT `fk_rnum_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__retenciones_regimenes
CREATE TABLE IF NOT EXISTS `gestion__retenciones_regimenes` (
  `regimen_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = cat├ílogo global (todas las empresas)',
  `impuesto_tipo_id` smallint(5) unsigned NOT NULL COMMENT 'Retenci├│n practicada (es_retencion = 1, aplica_compra = 1)',
  `jurisdiccion_id` int(11) DEFAULT NULL COMMENT 'IIBB: provincia del r├®gimen; NULL = nacional',
  `codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'C├│digo del r├®gimen (el de la DDJJ del organismo)',
  `regimen` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Concepto',
  `base_calculo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NETO_GRAVADO' COMMENT 'NETO_GRAVADO | NETO | IVA | TOTAL',
  `alicuota_inscripto` decimal(10,4) DEFAULT NULL COMMENT '% para inscriptos; NULL = a confirmar',
  `alicuota_no_inscripto` decimal(10,4) DEFAULT NULL COMMENT '% para no inscriptos; NULL = a confirmar',
  `minimo_no_sujeto` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tipo_minimo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POR_PAGO' COMMENT 'POR_PAGO | ACUMULADO_MENSUAL (pagos del mes al proveedor)',
  `modo_minimo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DEDUCIBLE' COMMENT 'DEDUCIBLE: se resta de la base | UMBRAL: si se supera se retiene sobre el total',
  `minimo_retencion` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Retenci├│n menor a este importe = no se retiene',
  `usa_escala` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = inscriptos por escala (gestion__retenciones_regimenes_escalas)',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`regimen_id`),
  KEY `ix_rreg_busqueda` (`empresa_id`,`impuesto_tipo_id`,`jurisdiccion_id`,`codigo`,`f_desde`),
  KEY `ix_rreg_impuesto` (`impuesto_tipo_id`),
  KEY `ix_rreg_jurisdiccion` (`jurisdiccion_id`),
  CONSTRAINT `fk_rreg_impuesto_tipo_id` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `fk_rreg_jurisdiccion_id` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reg├¡menes de retenci├│n con vigencia (cat├ílogo global)';

-- gestion__retenciones_regimenes_escalas
CREATE TABLE IF NOT EXISTS `gestion__retenciones_regimenes_escalas` (
  `escala_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `regimen_id` int(10) unsigned NOT NULL,
  `desde` decimal(18,2) NOT NULL COMMENT 'Base sujeta desde (inclusive)',
  `hasta` decimal(18,2) DEFAULT NULL COMMENT 'Hasta (exclusive); NULL = sin tope',
  `importe_fijo` decimal(18,2) NOT NULL DEFAULT 0.00,
  `alicuota_excedente` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '% sobre el excedente de "desde"',
  `orden` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`escala_id`),
  KEY `ix_resc_regimen` (`regimen_id`),
  CONSTRAINT `fk_resc_regimen_id` FOREIGN KEY (`regimen_id`) REFERENCES `gestion__retenciones_regimenes` (`regimen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Escala por tramos de un r├®gimen de retenci├│n';

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
  `f_creacion` datetime DEFAULT current_timestamp(),
  `f_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `proveedor_entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'BANCO: proveedor (gestion__entidades) del Resumen bancario de gastos; conciliacion bancaria',
  `saldo_actual` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'MATERIALIZADO: SUM(ingresos) - SUM(egresos) de gestion__tesoreria_movimientos',
  `permite_saldo_negativo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = admite descubierto (BANCO)',
  `orden` smallint(5) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  KEY `fk_tcta_proveedor_entidad_id` (`proveedor_entidad_id`),
  CONSTRAINT `fk_tcta_banco_id` FOREIGN KEY (`banco_id`) REFERENCES `conf__bancos` (`banco_id`),
  CONSTRAINT `fk_tcta_cont_cuenta_id` FOREIGN KEY (`cont_cuenta_id`) REFERENCES `gestion__cont_cuentas` (`cont_cuenta_id`),
  CONSTRAINT `fk_tcta_moneda_id` FOREIGN KEY (`moneda_id`) REFERENCES `gestion__monedas` (`moneda_id`),
  CONSTRAINT `fk_tcta_proveedor_entidad_id` FOREIGN KEY (`proveedor_entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
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
  `conciliacion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Conciliacion bancaria vigente (gestion__bancos_conciliaciones); NULL = sin conciliar',
  `reversa_de_movimiento_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Contramovimiento de anulacion: id del movimiento revertido',
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  KEY `ix_tmov_conciliacion` (`conciliacion_id`),
  CONSTRAINT `fk_tmov_cheque_id` FOREIGN KEY (`cheque_id`) REFERENCES `gestion__cheques` (`cheque_id`),
  CONSTRAINT `fk_tmov_comprobante_id` FOREIGN KEY (`comprobante_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`),
  CONSTRAINT `fk_tmov_conciliacion_id` FOREIGN KEY (`conciliacion_id`) REFERENCES `gestion__bancos_conciliaciones` (`conciliacion_id`),
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
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

-- ml__accesos_confidenciales
CREATE TABLE IF NOT EXISTS `ml__accesos_confidenciales` (
  `acceso_confidencial_id` int(11) NOT NULL AUTO_INCREMENT,
  `objeto_tabla` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objeto_id` int(11) NOT NULL,
  `empresa_perfil_id` int(10) unsigned NOT NULL,
  `ambito_tipo_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `region_grupo_id` int(11) DEFAULT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `territorio_id` int(11) DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`acceso_confidencial_id`),
  KEY `ix_acco_objeto_tabla_objeto` (`objeto_tabla`,`objeto_id`),
  KEY `ix_acco_empresa_perfil` (`empresa_perfil_id`),
  KEY `ix_acco_ambito_tipo` (`ambito_tipo_id`),
  KEY `ix_acco_grupo` (`grupo_id`),
  KEY `ix_acco_region_grupo` (`region_grupo_id`),
  KEY `ix_acco_empresa` (`empresa_id`),
  KEY `ix_acco_territorio` (`territorio_id`),
  CONSTRAINT `fk_acco_ambito_tipo_id` FOREIGN KEY (`ambito_tipo_id`) REFERENCES `ml__ambitos_tipos` (`ambito_tipo_id`),
  CONSTRAINT `fk_acco_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_acco_empresa_perfil_id` FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`),
  CONSTRAINT `fk_acco_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`),
  CONSTRAINT `fk_acco_region_grupo_id` FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`),
  CONSTRAINT `fk_acco_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `ck_acco_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`),
  CONSTRAINT `ck_acco_ambito` CHECK ((`grupo_id` is not null) + (`region_grupo_id` is not null) + (`empresa_id` is not null) + (`territorio_id` is not null) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__alertas
CREATE TABLE IF NOT EXISTS `ml__alertas` (
  `alerta_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_id` int(11) DEFAULT NULL,
  `ciclo_tarea_id` int(11) DEFAULT NULL,
  `evidencia_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `canal` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `programada_para` datetime NOT NULL,
  `enviada_en` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`alerta_id`),
  KEY `ix_ale_programada_para_enviada_en` (`programada_para`,`enviada_en`),
  KEY `ix_ale_usuario` (`usuario_id`),
  KEY `ix_ale_ciclo` (`ciclo_id`),
  KEY `ix_ale_ciclo_tarea` (`ciclo_tarea_id`),
  KEY `ix_ale_evidencia` (`evidencia_id`),
  KEY `ix_ale_empresa` (`empresa_id`),
  CONSTRAINT `fk_ale_ciclo_id` FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`),
  CONSTRAINT `fk_ale_ciclo_tarea_id` FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`),
  CONSTRAINT `fk_ale_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_ale_evidencia_id` FOREIGN KEY (`evidencia_id`) REFERENCES `ml__evidencias` (`evidencia_id`),
  CONSTRAINT `fk_ale_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ambitos_tipos
CREATE TABLE IF NOT EXISTS `ml__ambitos_tipos` (
  `ambito_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ambito_tipo_id`),
  UNIQUE KEY `uk_ambt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__auditoria_accesos
CREATE TABLE IF NOT EXISTS `ml__auditoria_accesos` (
  `auditoria_acceso_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `objeto_tabla` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objeto_id` int(11) NOT NULL,
  `accedido_en` datetime NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`auditoria_acceso_id`),
  KEY `ix_auda_objeto_tabla_objeto` (`objeto_tabla`,`objeto_id`),
  KEY `ix_auda_usuario` (`usuario_id`),
  CONSTRAINT `fk_auda_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__cambios_masivos
CREATE TABLE IF NOT EXISTS `ml__cambios_masivos` (
  `cambio_masivo_id` int(11) NOT NULL AUTO_INCREMENT,
  `norma_version_id` int(11) DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `solicitado_por` int(11) DEFAULT NULL,
  `requiere_aprobacion` tinyint(1) NOT NULL DEFAULT 1,
  `aprobado_por` int(11) DEFAULT NULL,
  `aprobado_en` datetime DEFAULT NULL,
  `aplicado_en` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`cambio_masivo_id`),
  KEY `ix_cmas_norma_version` (`norma_version_id`),
  KEY `ix_cmas_solicitado_por` (`solicitado_por`),
  KEY `ix_cmas_aprobado_por` (`aprobado_por`),
  CONSTRAINT `fk_cmas_aprobado_por` FOREIGN KEY (`aprobado_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_cmas_norma_version_id` FOREIGN KEY (`norma_version_id`) REFERENCES `ml__normas_versiones` (`norma_version_id`),
  CONSTRAINT `fk_cmas_solicitado_por` FOREIGN KEY (`solicitado_por`) REFERENCES `conf__usuarios` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__cambios_masivos_detalles
CREATE TABLE IF NOT EXISTS `ml__cambios_masivos_detalles` (
  `cambio_masivo_detalle_id` int(11) NOT NULL AUTO_INCREMENT,
  `cambio_masivo_id` int(11) NOT NULL,
  `entidad_id` int(11) NOT NULL,
  `ciclos_afectados` int(11) NOT NULL,
  `tareas_afectadas` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`cambio_masivo_detalle_id`),
  KEY `ix_cmad_cambio_masivo` (`cambio_masivo_id`),
  KEY `ix_cmad_entidad` (`entidad_id`),
  CONSTRAINT `fk_cmad_cambio_masivo_id` FOREIGN KEY (`cambio_masivo_id`) REFERENCES `ml__cambios_masivos` (`cambio_masivo_id`),
  CONSTRAINT `fk_cmad_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__cargas
CREATE TABLE IF NOT EXISTS `ml__cargas` (
  `carga_id` int(11) NOT NULL AUTO_INCREMENT,
  `carga_tipo_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `archivo_ref` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validada_en` datetime DEFAULT NULL,
  `confirmada_por` int(11) DEFAULT NULL,
  `confirmada_en` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`carga_id`),
  KEY `ix_car_carga_tipo` (`carga_tipo_id`),
  KEY `ix_car_empresa` (`empresa_id`),
  KEY `ix_car_confirmada_por` (`confirmada_por`),
  CONSTRAINT `fk_car_carga_tipo_id` FOREIGN KEY (`carga_tipo_id`) REFERENCES `ml__cargas_tipos` (`carga_tipo_id`),
  CONSTRAINT `fk_car_confirmada_por` FOREIGN KEY (`confirmada_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_car_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__cargas_filas
CREATE TABLE IF NOT EXISTS `ml__cargas_filas` (
  `carga_fila_id` int(11) NOT NULL AUTO_INCREMENT,
  `carga_id` int(11) NOT NULL,
  `nro_fila` int(11) NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `errores` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`carga_fila_id`),
  UNIQUE KEY `uk_carf_carga_nro_fila` (`carga_id`,`nro_fila`),
  CONSTRAINT `fk_carf_carga_id` FOREIGN KEY (`carga_id`) REFERENCES `ml__cargas` (`carga_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__cargas_tipos
CREATE TABLE IF NOT EXISTS `ml__cargas_tipos` (
  `carga_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`carga_tipo_id`),
  UNIQUE KEY `uk_cart_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ciclos
CREATE TABLE IF NOT EXISTS `ml__ciclos` (
  `ciclo_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `entidad_id` int(11) NOT NULL,
  `norma_version_id` int(11) DEFAULT NULL,
  `ciclo_origen_id` int(11) DEFAULT NULL,
  `ciclo_vinculo_tipo_id` int(11) DEFAULT NULL,
  `periodo_desde` date NOT NULL,
  `periodo_hasta` date NOT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_vencimiento` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ciclo_id`),
  KEY `ix_cic_obligacion_asignada_periodo_desde` (`obligacion_asignada_id`,`periodo_desde`),
  KEY `ix_cic_empresa_fecha_vencimiento` (`empresa_id`,`fecha_vencimiento`),
  KEY `ix_cic_entidad` (`entidad_id`),
  KEY `ix_cic_ciclo_origen` (`ciclo_origen_id`),
  KEY `ix_cic_norma_version` (`norma_version_id`),
  KEY `ix_cic_ciclo_vinculo_tipo` (`ciclo_vinculo_tipo_id`),
  CONSTRAINT `fk_cic_ciclo_origen_id` FOREIGN KEY (`ciclo_origen_id`) REFERENCES `ml__ciclos` (`ciclo_id`),
  CONSTRAINT `fk_cic_ciclo_vinculo_tipo_id` FOREIGN KEY (`ciclo_vinculo_tipo_id`) REFERENCES `ml__ciclos_vinculos_tipos` (`ciclo_vinculo_tipo_id`),
  CONSTRAINT `fk_cic_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_cic_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_cic_norma_version_id` FOREIGN KEY (`norma_version_id`) REFERENCES `ml__normas_versiones` (`norma_version_id`),
  CONSTRAINT `fk_cic_obligacion_asignada_id` FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ciclos_raci
CREATE TABLE IF NOT EXISTS `ml__ciclos_raci` (
  `ciclo_raci_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_id` int(11) NOT NULL,
  `raci_rol_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ciclo_raci_id`),
  UNIQUE KEY `uk_cicr_ciclo_raci_rol_usuario_desde` (`ciclo_id`,`raci_rol_id`,`usuario_id`,`vigente_desde`),
  KEY `ix_cicr_usuario_hasta` (`usuario_id`,`vigente_hasta`),
  KEY `ix_cicr_raci_rol` (`raci_rol_id`),
  KEY `ix_cicr_empresa` (`empresa_id`),
  CONSTRAINT `fk_cicr_ciclo_id` FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`),
  CONSTRAINT `fk_cicr_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_cicr_raci_rol_id` FOREIGN KEY (`raci_rol_id`) REFERENCES `ml__raci_roles` (`raci_rol_id`),
  CONSTRAINT `fk_cicr_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `ck_cicr_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ciclos_tareas
CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas` (
  `ciclo_tarea_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_id` int(11) NOT NULL,
  `tarea_plantilla_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `procedimiento_version_id` int(11) DEFAULT NULL,
  `requiere_revision` tinyint(1) NOT NULL,
  `requiere_evidencia` tinyint(1) NOT NULL,
  `fecha_activacion` datetime DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_vencimiento` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ciclo_tarea_id`),
  KEY `ix_cta_ciclo` (`ciclo_id`),
  KEY `ix_cta_empresa_tabla_estado_registro_fecha_vencimiento` (`empresa_id`,`tabla_estado_registro_id`,`fecha_vencimiento`),
  KEY `ix_cta_tarea_plantilla` (`tarea_plantilla_id`),
  KEY `ix_cta_procedimiento_version` (`procedimiento_version_id`),
  CONSTRAINT `fk_cta_ciclo_id` FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`),
  CONSTRAINT `fk_cta_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_cta_procedimiento_version_id` FOREIGN KEY (`procedimiento_version_id`) REFERENCES `ml__procedimientos_versiones` (`procedimiento_version_id`),
  CONSTRAINT `fk_cta_tarea_plantilla_id` FOREIGN KEY (`tarea_plantilla_id`) REFERENCES `ml__tareas_plantillas` (`tarea_plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ciclos_tareas_dependencias
CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas_dependencias` (
  `ciclo_tarea_dependencia_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id` int(11) NOT NULL,
  `ciclo_tarea_predecesora_id` int(11) NOT NULL,
  `estado_activador_id` smallint(6) NOT NULL,
  `obligatoria` tinyint(1) NOT NULL DEFAULT 1,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ciclo_tarea_dependencia_id`),
  UNIQUE KEY `uk_ctad_ciclo_tarea_ciclo_tarea_predecesora` (`ciclo_tarea_id`,`ciclo_tarea_predecesora_id`),
  KEY `ix_ctad_ciclo_tarea_predecesora` (`ciclo_tarea_predecesora_id`),
  KEY `ix_ctad_empresa` (`empresa_id`),
  CONSTRAINT `fk_ctad_ciclo_tarea_id` FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`),
  CONSTRAINT `fk_ctad_ciclo_tarea_predecesora_id` FOREIGN KEY (`ciclo_tarea_predecesora_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`),
  CONSTRAINT `fk_ctad_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ciclos_tareas_mensajes
CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas_mensajes` (
  `tarea_mensaje_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COMENTARIO',
  `empresa_id` smallint(5) unsigned NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tarea_mensaje_id`),
  KEY `ix_tm_tarea` (`ciclo_tarea_id`),
  KEY `ix_tm_usuario` (`usuario_id`),
  CONSTRAINT `fk_tm_tarea` FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ciclos_tareas_raci
CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas_raci` (
  `ciclo_tarea_raci_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id` int(11) NOT NULL,
  `raci_rol_id` int(11) NOT NULL,
  `posicion_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ciclo_tarea_raci_id`),
  UNIQUE KEY `uk_ctar_ciclo_tarea_raci_rol_usuario_desde` (`ciclo_tarea_id`,`raci_rol_id`,`usuario_id`,`vigente_desde`),
  KEY `ix_ctar_usuario_hasta` (`usuario_id`,`vigente_hasta`),
  KEY `ix_ctar_raci_rol` (`raci_rol_id`),
  KEY `ix_ctar_empresa` (`empresa_id`),
  KEY `ix_ctar_posicion` (`posicion_id`),
  CONSTRAINT `fk_ctar_ciclo_tarea_id` FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`),
  CONSTRAINT `fk_ctar_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_ctar_posicion` FOREIGN KEY (`posicion_id`) REFERENCES `ml__posiciones` (`posicion_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ctar_raci_rol_id` FOREIGN KEY (`raci_rol_id`) REFERENCES `ml__raci_roles` (`raci_rol_id`),
  CONSTRAINT `fk_ctar_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `ck_ctar_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__ciclos_vinculos_tipos
CREATE TABLE IF NOT EXISTS `ml__ciclos_vinculos_tipos` (
  `ciclo_vinculo_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`ciclo_vinculo_tipo_id`),
  UNIQUE KEY `uk_cvt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__configuracion_grupo
CREATE TABLE IF NOT EXISTS `ml__configuracion_grupo` (
  `configuracion_grupo_id` int(11) NOT NULL AUTO_INCREMENT,
  `grupo_id` int(11) NOT NULL,
  `opcion_id` int(11) NOT NULL,
  `opcion_valor_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`configuracion_grupo_id`),
  KEY `ix_cfgg_grupo_opcion_hasta` (`grupo_id`,`opcion_id`,`vigente_hasta`),
  KEY `ix_cfgg_opcion` (`opcion_id`),
  KEY `ix_cfgg_opcion_valor` (`opcion_valor_id`),
  CONSTRAINT `fk_cfgg_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`),
  CONSTRAINT `fk_cfgg_opcion_id` FOREIGN KEY (`opcion_id`) REFERENCES `ml__opciones` (`opcion_id`),
  CONSTRAINT `fk_cfgg_opcion_valor_id` FOREIGN KEY (`opcion_valor_id`) REFERENCES `ml__opciones_valores` (`opcion_valor_id`),
  CONSTRAINT `ck_cfgg_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__criticidades
CREATE TABLE IF NOT EXISTS `ml__criticidades` (
  `criticidad_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peso` int(11) NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`criticidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__datos_tipos
CREATE TABLE IF NOT EXISTS `ml__datos_tipos` (
  `dato_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_valor` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`dato_tipo_id`),
  UNIQUE KEY `uk_datt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__delegaciones
CREATE TABLE IF NOT EXISTS `ml__delegaciones` (
  `delegacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `usuario_suplente_id` int(11) NOT NULL,
  `empresa_perfil_id` int(10) unsigned DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`delegacion_id`),
  KEY `ix_dele_usuario` (`usuario_id`),
  KEY `ix_dele_usuario_suplente` (`usuario_suplente_id`),
  KEY `ix_dele_empresa_perfil` (`empresa_perfil_id`),
  CONSTRAINT `fk_dele_empresa_perfil_id` FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`),
  CONSTRAINT `fk_dele_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_dele_usuario_suplente_id` FOREIGN KEY (`usuario_suplente_id`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `ck_dele_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__empresas_eventos
CREATE TABLE IF NOT EXISTS `ml__empresas_eventos` (
  `empresa_evento_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_evento_tipo_id` int(11) NOT NULL,
  `empresa_origen_id` smallint(5) unsigned NOT NULL,
  `empresa_destino_id` smallint(5) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`empresa_evento_id`),
  KEY `ix_eev_empresa_evento_tipo` (`empresa_evento_tipo_id`),
  KEY `ix_eev_empresa_origen` (`empresa_origen_id`),
  KEY `ix_eev_empresa_destino` (`empresa_destino_id`),
  CONSTRAINT `fk_eev_empresa_destino_id` FOREIGN KEY (`empresa_destino_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_eev_empresa_evento_tipo_id` FOREIGN KEY (`empresa_evento_tipo_id`) REFERENCES `ml__empresas_eventos_tipos` (`empresa_evento_tipo_id`),
  CONSTRAINT `fk_eev_empresa_origen_id` FOREIGN KEY (`empresa_origen_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__empresas_eventos_tipos
CREATE TABLE IF NOT EXISTS `ml__empresas_eventos_tipos` (
  `empresa_evento_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`empresa_evento_tipo_id`),
  UNIQUE KEY `uk_eevt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__empresas_grupo
CREATE TABLE IF NOT EXISTS `ml__empresas_grupo` (
  `empresa_grupo_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `pais_territorio_id` int(11) NOT NULL,
  `entidad_raiz_id` int(11) DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`empresa_grupo_id`),
  KEY `ix_egr_empresa` (`empresa_id`),
  KEY `ix_egr_grupo` (`grupo_id`),
  KEY `ix_egr_pais_territorio` (`pais_territorio_id`),
  KEY `ix_egr_entidad_raiz` (`entidad_raiz_id`),
  CONSTRAINT `fk_egr_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_egr_entidad_raiz_id` FOREIGN KEY (`entidad_raiz_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_egr_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`),
  CONSTRAINT `fk_egr_pais_territorio_id` FOREIGN KEY (`pais_territorio_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `ck_egr_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__empresas_nombres
CREATE TABLE IF NOT EXISTS `ml__empresas_nombres` (
  `empresa_nombre_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `razon_social` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`empresa_nombre_id`),
  KEY `ix_enom_empresa_hasta` (`empresa_id`,`vigente_hasta`),
  CONSTRAINT `fk_enom_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `ck_enom_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__empresas_regiones
CREATE TABLE IF NOT EXISTS `ml__empresas_regiones` (
  `empresa_region_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `region_grupo_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`empresa_region_id`),
  KEY `ix_ereg_empresa_hasta` (`empresa_id`,`vigente_hasta`),
  KEY `ix_ereg_region_grupo` (`region_grupo_id`),
  CONSTRAINT `fk_ereg_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_ereg_region_grupo_id` FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`),
  CONSTRAINT `ck_ereg_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_id`),
  KEY `ix_ent_codigo` (`codigo`),
  KEY `ix_ent_entidad_padre` (`entidad_padre_id`),
  KEY `ix_ent_entidad_tipo` (`entidad_tipo_id`),
  CONSTRAINT `fk_ent_entidad_padre_id` FOREIGN KEY (`entidad_padre_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_ent_entidad_tipo_id` FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`),
  CONSTRAINT `ck_ent_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__entidades_datos
CREATE TABLE IF NOT EXISTS `ml__entidades_datos` (
  `entidad_dato_id` int(11) NOT NULL AUTO_INCREMENT,
  `entidad_id` int(11) NOT NULL,
  `dato_tipo_id` int(11) NOT NULL,
  `valor_numero` decimal(18,4) DEFAULT NULL,
  `valor_texto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_fecha` date DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_dato_id`),
  KEY `ix_entd_entidad_dato_tipo_hasta` (`entidad_id`,`dato_tipo_id`,`vigente_hasta`),
  KEY `ix_entd_dato_tipo` (`dato_tipo_id`),
  CONSTRAINT `fk_entd_dato_tipo_id` FOREIGN KEY (`dato_tipo_id`) REFERENCES `ml__datos_tipos` (`dato_tipo_id`),
  CONSTRAINT `fk_entd_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `ck_entd_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__entidades_empresas
CREATE TABLE IF NOT EXISTS `ml__entidades_empresas` (
  `entidad_empresa_id` int(11) NOT NULL AUTO_INCREMENT,
  `entidad_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `es_titular` tinyint(1) NOT NULL DEFAULT 1,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `titular_abierta_id` int(11) GENERATED ALWAYS AS (case when `es_titular` = 1 and `vigente_hasta` is null then `entidad_id` else NULL end) STORED,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_empresa_id`),
  UNIQUE KEY `uk_ente_titular_abierta` (`titular_abierta_id`),
  KEY `ix_ente_entidad_hasta` (`entidad_id`,`vigente_hasta`),
  KEY `ix_ente_empresa_hasta` (`empresa_id`,`vigente_hasta`),
  CONSTRAINT `fk_ente_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_ente_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `ck_ente_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_tipo_id`),
  KEY `ix_entt_grupo_empresa_codigo` (`grupo_id`,`empresa_id`,`codigo`),
  KEY `ix_entt_empresa` (`empresa_id`),
  CONSTRAINT `fk_entt_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_entt_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__entidades_ubicaciones
CREATE TABLE IF NOT EXISTS `ml__entidades_ubicaciones` (
  `entidad_ubicacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `entidad_id` int(11) NOT NULL,
  `territorio_id` int(11) NOT NULL,
  `es_base` tinyint(1) NOT NULL DEFAULT 1,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`entidad_ubicacion_id`),
  KEY `ix_entu_entidad_es_base_hasta` (`entidad_id`,`es_base`,`vigente_hasta`),
  KEY `ix_entu_territorio_hasta` (`territorio_id`,`vigente_hasta`),
  CONSTRAINT `fk_entu_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_entu_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `ck_entu_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__evidencias
CREATE TABLE IF NOT EXISTS `ml__evidencias` (
  `evidencia_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id` int(11) NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_ref` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_comprobante` tinyint(1) NOT NULL DEFAULT 0,
  `valida_hasta` date DEFAULT NULL,
  `retener_hasta` date DEFAULT NULL,
  `confidencial` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`evidencia_id`),
  KEY `ix_evi_ciclo_tarea` (`ciclo_tarea_id`),
  KEY `ix_evi_valida_hasta` (`valida_hasta`),
  KEY `ix_evi_empresa` (`empresa_id`),
  CONSTRAINT `fk_evi_ciclo_tarea_id` FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`),
  CONSTRAINT `fk_evi_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__excepciones
CREATE TABLE IF NOT EXISTS `ml__excepciones` (
  `excepcion_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id` int(11) NOT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fundamento` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fundamento_ref` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solicitada_por` int(11) DEFAULT NULL,
  `aprobada_por` int(11) DEFAULT NULL,
  `aprobada_legal_por` int(11) DEFAULT NULL,
  `aprobada_en` datetime DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`excepcion_id`),
  KEY `ix_exc_obligacion_asignada_hasta` (`obligacion_asignada_id`,`vigente_hasta`),
  KEY `ix_exc_empresa` (`empresa_id`),
  KEY `ix_exc_solicitada_por` (`solicitada_por`),
  KEY `ix_exc_aprobada_por` (`aprobada_por`),
  KEY `ix_exc_aprobada_legal_por` (`aprobada_legal_por`),
  CONSTRAINT `fk_exc_aprobada_legal_por` FOREIGN KEY (`aprobada_legal_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_exc_aprobada_por` FOREIGN KEY (`aprobada_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_exc_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_exc_obligacion_asignada_id` FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`),
  CONSTRAINT `fk_exc_solicitada_por` FOREIGN KEY (`solicitada_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `ck_exc_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__feriados
CREATE TABLE IF NOT EXISTS `ml__feriados` (
  `feriado_id` int(11) NOT NULL AUTO_INCREMENT,
  `territorio_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`feriado_id`),
  UNIQUE KEY `uk_fer_territorio_fecha` (`territorio_id`,`fecha`),
  CONSTRAINT `fk_fer_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__grupos
CREATE TABLE IF NOT EXISTS `ml__grupos` (
  `grupo_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_titular_id` smallint(5) unsigned NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`grupo_id`),
  KEY `ix_grp_empresa_titular` (`empresa_titular_id`),
  CONSTRAINT `fk_grp_empresa_titular_id` FOREIGN KEY (`empresa_titular_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__incidencias
CREATE TABLE IF NOT EXISTS `ml__incidencias` (
  `incidencia_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_id` int(11) NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `abierta_en` datetime NOT NULL,
  `ciclo_seguimiento_id` int(11) DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`incidencia_id`),
  KEY `ix_inci_ciclo` (`ciclo_id`),
  KEY `ix_inci_ciclo_seguimiento` (`ciclo_seguimiento_id`),
  KEY `ix_inci_empresa` (`empresa_id`),
  CONSTRAINT `fk_inci_ciclo_id` FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`),
  CONSTRAINT `fk_inci_ciclo_seguimiento_id` FOREIGN KEY (`ciclo_seguimiento_id`) REFERENCES `ml__ciclos` (`ciclo_id`),
  CONSTRAINT `fk_inci_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__indicadores_cierres
CREATE TABLE IF NOT EXISTS `ml__indicadores_cierres` (
  `indicador_cierre_id` int(11) NOT NULL AUTO_INCREMENT,
  `grupo_id` int(11) NOT NULL,
  `periodo_desde` date NOT NULL,
  `periodo_hasta` date NOT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `region_grupo_id` int(11) DEFAULT NULL,
  `territorio_id` int(11) DEFAULT NULL,
  `ciclos_contados` int(11) NOT NULL,
  `cumplidos_a_tiempo` int(11) NOT NULL,
  `cumplidos_fuera_plazo` int(11) NOT NULL,
  `incumplidos` int(11) NOT NULL,
  `eximidos` int(11) NOT NULL,
  `por_vencer` int(11) NOT NULL,
  `ponderacion_opcion_valor_id` int(11) DEFAULT NULL,
  `calculado_en` datetime NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`indicador_cierre_id`),
  KEY `ix_indc_grupo_periodo_desde_periodo_hasta` (`grupo_id`,`periodo_desde`,`periodo_hasta`),
  KEY `ix_indc_empresa` (`empresa_id`),
  KEY `ix_indc_region_grupo` (`region_grupo_id`),
  KEY `ix_indc_territorio` (`territorio_id`),
  KEY `ix_indc_ponderacion_opcion_valor` (`ponderacion_opcion_valor_id`),
  CONSTRAINT `fk_indc_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_indc_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`),
  CONSTRAINT `fk_indc_ponderacion_opcion_valor_id` FOREIGN KEY (`ponderacion_opcion_valor_id`) REFERENCES `ml__opciones_valores` (`opcion_valor_id`),
  CONSTRAINT `fk_indc_region_grupo_id` FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`),
  CONSTRAINT `fk_indc_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__log_cambios
CREATE TABLE IF NOT EXISTS `ml__log_cambios` (
  `log_cambio_id` int(11) NOT NULL AUTO_INCREMENT,
  `tabla` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_id` int(11) NOT NULL,
  `accion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `antes_json` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `despues_json` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`log_cambio_id`),
  KEY `ix_logc_tabla_registro` (`tabla`,`registro_id`),
  KEY `ix_logc_fecha` (`fecha`),
  KEY `ix_logc_usuario` (`usuario_id`),
  CONSTRAINT `fk_logc_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)
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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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

-- ml__normas_conflictos
CREATE TABLE IF NOT EXISTS `ml__normas_conflictos` (
  `norma_conflicto_id` int(11) NOT NULL AUTO_INCREMENT,
  `norma_a_id` int(11) NOT NULL,
  `norma_b_id` int(11) NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `escalado_a` int(11) DEFAULT NULL,
  `decision` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decidido_por` int(11) DEFAULT NULL,
  `decidido_en` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_conflicto_id`),
  KEY `ix_ncf_norma_a` (`norma_a_id`),
  KEY `ix_ncf_norma_b` (`norma_b_id`),
  KEY `ix_ncf_escalado_a` (`escalado_a`),
  KEY `ix_ncf_decidido_por` (`decidido_por`),
  CONSTRAINT `fk_ncf_decidido_por` FOREIGN KEY (`decidido_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_ncf_escalado_a` FOREIGN KEY (`escalado_a`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_ncf_norma_a_id` FOREIGN KEY (`norma_a_id`) REFERENCES `ml__normas` (`norma_id`),
  CONSTRAINT `fk_ncf_norma_b_id` FOREIGN KEY (`norma_b_id`) REFERENCES `ml__normas` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__normas_jerarquias
CREATE TABLE IF NOT EXISTS `ml__normas_jerarquias` (
  `norma_jerarquia_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` int(11) NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_jerarquia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__normas_relaciones
CREATE TABLE IF NOT EXISTS `ml__normas_relaciones` (
  `norma_relacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `norma_origen_id` int(11) NOT NULL,
  `norma_destino_id` int(11) NOT NULL,
  `norma_relacion_tipo_id` int(11) NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_relacion_id`),
  KEY `ix_nrl_norma_origen` (`norma_origen_id`),
  KEY `ix_nrl_norma_destino` (`norma_destino_id`),
  KEY `ix_nrl_norma_relacion_tipo` (`norma_relacion_tipo_id`),
  CONSTRAINT `fk_nrl_norma_destino_id` FOREIGN KEY (`norma_destino_id`) REFERENCES `ml__normas` (`norma_id`),
  CONSTRAINT `fk_nrl_norma_origen_id` FOREIGN KEY (`norma_origen_id`) REFERENCES `ml__normas` (`norma_id`),
  CONSTRAINT `fk_nrl_norma_relacion_tipo_id` FOREIGN KEY (`norma_relacion_tipo_id`) REFERENCES `ml__normas_relaciones_tipos` (`norma_relacion_tipo_id`),
  CONSTRAINT `ck_nrl_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__normas_relaciones_tipos
CREATE TABLE IF NOT EXISTS `ml__normas_relaciones_tipos` (
  `norma_relacion_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_relacion_tipo_id`),
  UNIQUE KEY `uk_nrlt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__normas_tipos
CREATE TABLE IF NOT EXISTS `ml__normas_tipos` (
  `norma_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_legal` tinyint(1) NOT NULL DEFAULT 0,
  `genera_obligaciones` tinyint(1) NOT NULL DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_tipo_id`),
  UNIQUE KEY `uk_nort_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__normas_versiones
CREATE TABLE IF NOT EXISTS `ml__normas_versiones` (
  `norma_version_id` int(11) NOT NULL AUTO_INCREMENT,
  `norma_id` int(11) NOT NULL,
  `version` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_publicacion` date DEFAULT NULL,
  `archivo_ref` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_vigente` tinyint(1) NOT NULL DEFAULT 0,
  `confidencial` tinyint(1) NOT NULL DEFAULT 0,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`norma_version_id`),
  UNIQUE KEY `uk_norv_norma_version` (`norma_id`,`version`),
  CONSTRAINT `fk_norv_norma_id` FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`),
  CONSTRAINT `ck_norv_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones
CREATE TABLE IF NOT EXISTS `ml__obligaciones` (
  `obligacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `norma_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `articulo_referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sigla` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icono` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-balance-scale',
  `color_hex` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#0d6efd',
  `imagen_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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

-- ml__obligaciones_alertas
CREATE TABLE IF NOT EXISTS `ml__obligaciones_alertas` (
  `obligacion_alerta_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_id` int(11) NOT NULL,
  `dias_antes` int(11) NOT NULL,
  `destinatario` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_alerta_id`),
  KEY `ix_obla_obligacion` (`obligacion_id`),
  CONSTRAINT `fk_obla_obligacion_id` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones_asignadas
CREATE TABLE IF NOT EXISTS `ml__obligaciones_asignadas` (
  `obligacion_asignada_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_prevaleciente_id` int(11) NOT NULL,
  `entidad_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_asignada_id`),
  KEY `ix_oas_entidad_obligacion_prevaleciente_desde` (`entidad_id`,`obligacion_prevaleciente_id`,`vigente_desde`),
  KEY `ix_oas_empresa_hasta` (`empresa_id`,`vigente_hasta`),
  KEY `ix_oas_obligacion_prevaleciente` (`obligacion_prevaleciente_id`),
  CONSTRAINT `fk_oas_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_oas_entidad_id` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_oas_obligacion_prevaleciente_id` FOREIGN KEY (`obligacion_prevaleciente_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `ck_oas_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones_asignadas_origenes
CREATE TABLE IF NOT EXISTS `ml__obligaciones_asignadas_origenes` (
  `obligacion_asignada_origen_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id` int(11) NOT NULL,
  `obligacion_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_asignada_origen_id`),
  UNIQUE KEY `uk_oaso_obligacion_asignada_obligacion` (`obligacion_asignada_id`,`obligacion_id`),
  KEY `ix_oaso_obligacion` (`obligacion_id`),
  CONSTRAINT `fk_oaso_obligacion_asignada_id` FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`),
  CONSTRAINT `fk_oaso_obligacion_id` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones_asignadas_raci
CREATE TABLE IF NOT EXISTS `ml__obligaciones_asignadas_raci` (
  `obligacion_asignada_raci_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id` int(11) NOT NULL,
  `raci_rol_id` int(11) NOT NULL,
  `posicion_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_asignada_raci_id`),
  UNIQUE KEY `uk_oasr_obligacion_asignada_raci_rol_usuario_desde` (`obligacion_asignada_id`,`raci_rol_id`,`usuario_id`,`vigente_desde`),
  KEY `ix_oasr_usuario_hasta` (`usuario_id`,`vigente_hasta`),
  KEY `ix_oasr_raci_rol` (`raci_rol_id`),
  KEY `ix_oasr_empresa` (`empresa_id`),
  KEY `ix_oar_posicion` (`posicion_id`),
  CONSTRAINT `fk_oar_posicion` FOREIGN KEY (`posicion_id`) REFERENCES `ml__posiciones` (`posicion_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_oasr_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_oasr_obligacion_asignada_id` FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`),
  CONSTRAINT `fk_oasr_raci_rol_id` FOREIGN KEY (`raci_rol_id`) REFERENCES `ml__raci_roles` (`raci_rol_id`),
  CONSTRAINT `fk_oasr_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `ck_oasr_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones_dependencias
CREATE TABLE IF NOT EXISTS `ml__obligaciones_dependencias` (
  `obligacion_dependencia_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_id` int(11) NOT NULL,
  `obligacion_predecesora_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_dependencia_id`),
  KEY `ix_obld_obligacion_obligacion_predecesora` (`obligacion_id`,`obligacion_predecesora_id`),
  KEY `ix_obld_obligacion_predecesora` (`obligacion_predecesora_id`),
  CONSTRAINT `fk_obld_obligacion_id` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `fk_obld_obligacion_predecesora_id` FOREIGN KEY (`obligacion_predecesora_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `ck_obld_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__obligaciones_jurisdicciones
CREATE TABLE IF NOT EXISTS `ml__obligaciones_jurisdicciones` (
  `obligacion_jurisdiccion_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_id` int(11) NOT NULL,
  `territorio_id` int(11) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`obligacion_tipo_entidad_id`),
  KEY `ix_oblt_obligacion_entidad_tipo_desde` (`obligacion_id`,`entidad_tipo_id`,`vigente_desde`),
  KEY `ix_oblt_entidad_tipo` (`entidad_tipo_id`),
  CONSTRAINT `fk_oblt_entidad_tipo_id` FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`),
  CONSTRAINT `fk_oblt_obligacion_id` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `ck_oblt_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__opciones
CREATE TABLE IF NOT EXISTS `ml__opciones` (
  `opcion_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`opcion_id`),
  UNIQUE KEY `uk_opc_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__opciones_valores
CREATE TABLE IF NOT EXISTS `ml__opciones_valores` (
  `opcion_valor_id` int(11) NOT NULL AUTO_INCREMENT,
  `opcion_id` int(11) NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_defecto` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`opcion_valor_id`),
  UNIQUE KEY `uk_opcv_opcion_codigo` (`opcion_id`,`codigo`),
  CONSTRAINT `fk_opcv_opcion_id` FOREIGN KEY (`opcion_id`) REFERENCES `ml__opciones` (`opcion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__organismos
CREATE TABLE IF NOT EXISTS `ml__organismos` (
  `organismo_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `territorio_id` int(11) DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`organismo_id`),
  KEY `ix_org_territorio` (`territorio_id`),
  CONSTRAINT `fk_org_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__periodicidades
CREATE TABLE IF NOT EXISTS `ml__periodicidades` (
  `periodicidad_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `intervalo_cantidad` int(11) DEFAULT NULL,
  `intervalo_unidad` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_evento` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_fija_autoridad` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`periodicidad_id`),
  UNIQUE KEY `uk_peri_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__posiciones
CREATE TABLE IF NOT EXISTS `ml__posiciones` (
  `posicion_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_departamento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posicion_superior_id` int(11) DEFAULT NULL COMMENT 'Posición superior a la que reporta jerárquicamente',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`posicion_id`),
  UNIQUE KEY `uk_pos_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `ix_pos_empresa` (`empresa_id`),
  KEY `ix_pos_superior` (`posicion_superior_id`),
  CONSTRAINT `fk_pos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_pos_superior` FOREIGN KEY (`posicion_superior_id`) REFERENCES `ml__posiciones` (`posicion_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos
CREATE TABLE IF NOT EXISTS `ml__procedimientos` (
  `procedimiento_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `norma_id` int(11) DEFAULT NULL,
  `procedimiento_tipo_id` int(11) DEFAULT 1,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_responsable` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable_usuario_id` int(11) DEFAULT NULL,
  `frecuencia_revision_meses` smallint(6) DEFAULT 12,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidencial` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`procedimiento_id`),
  KEY `ix_prc_empresa` (`empresa_id`),
  KEY `ix_prc_norma` (`norma_id`),
  CONSTRAINT `fk_prc_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_prc_norma_id` FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_aprobaciones
CREATE TABLE IF NOT EXISTS `ml__procedimientos_aprobaciones` (
  `aprobacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `procedimiento_id` int(11) NOT NULL,
  `procedimiento_version_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `rol_firmante` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Aprobador',
  `accion` enum('aprobado','rechazado','solicitado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aprobado',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma_hash` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`aprobacion_id`),
  KEY `idx_aprob_proc` (`procedimiento_id`),
  KEY `idx_aprob_ver` (`procedimiento_version_id`),
  KEY `idx_aprob_usr` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_documentos
CREATE TABLE IF NOT EXISTS `ml__procedimientos_documentos` (
  `procedimiento_documento_id` int(11) NOT NULL AUTO_INCREMENT,
  `procedimiento_padre_id` int(11) NOT NULL,
  `documento_asociado_id` int(11) NOT NULL,
  `tipo_relacion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'anexo',
  `orden` int(11) NOT NULL DEFAULT 1,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 1,
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`procedimiento_documento_id`),
  KEY `idx_proc_padre` (`procedimiento_padre_id`),
  KEY `idx_doc_asociado` (`documento_asociado_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_instrucciones
CREATE TABLE IF NOT EXISTS `ml__procedimientos_instrucciones` (
  `instruccion_id` int(11) NOT NULL AUTO_INCREMENT,
  `procedimiento_version_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_obligatoria` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`instruccion_id`),
  UNIQUE KEY `uk_prci_procedimiento_version_orden` (`procedimiento_version_id`,`orden`),
  CONSTRAINT `fk_prci_procedimiento_version_id` FOREIGN KEY (`procedimiento_version_id`) REFERENCES `ml__procedimientos_versiones` (`procedimiento_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_lecturas
CREATE TABLE IF NOT EXISTS `ml__procedimientos_lecturas` (
  `lectura_id` int(11) NOT NULL AUTO_INCREMENT,
  `procedimiento_version_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `conformidad` tinyint(1) NOT NULL DEFAULT 1,
  `comentario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lectura_id`),
  UNIQUE KEY `uk_lectura_ver_usr` (`procedimiento_version_id`,`usuario_id`),
  KEY `idx_lect_ver` (`procedimiento_version_id`),
  KEY `idx_lect_usr` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_log
CREATE TABLE IF NOT EXISTS `ml__procedimientos_log` (
  `procedimiento_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `procedimiento_id` int(11) NOT NULL,
  `procedimiento_version_id` int(11) DEFAULT NULL,
  `evento` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `datos_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`procedimiento_log_id`),
  KEY `idx_log_proc` (`procedimiento_id`),
  KEY `idx_log_ver` (`procedimiento_version_id`),
  KEY `idx_log_usr` (`usuario_id`),
  KEY `idx_log_evento` (`evento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_tipos
CREATE TABLE IF NOT EXISTS `ml__procedimientos_tipos` (
  `procedimiento_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-file-alt',
  `color_hex` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#0d6efd',
  `orden` int(11) DEFAULT 1,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`procedimiento_tipo_id`),
  UNIQUE KEY `uk_prct_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__procedimientos_versiones
CREATE TABLE IF NOT EXISTS `ml__procedimientos_versiones` (
  `procedimiento_version_id` int(11) NOT NULL AUTO_INCREMENT,
  `procedimiento_id` int(11) NOT NULL,
  `version` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_ref` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_tamano` int(11) DEFAULT NULL,
  `hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resumen_cambios` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `aprobado_en` datetime DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `reemplazado_por_version_id` int(11) DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`procedimiento_version_id`),
  UNIQUE KEY `uk_prcv_procedimiento_version` (`procedimiento_id`,`version`),
  KEY `ix_prcv_aprobado_por` (`aprobado_por`),
  CONSTRAINT `fk_prcv_aprobado_por` FOREIGN KEY (`aprobado_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_prcv_procedimiento_id` FOREIGN KEY (`procedimiento_id`) REFERENCES `ml__procedimientos` (`procedimiento_id`),
  CONSTRAINT `ck_prcv_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__raci_roles
CREATE TABLE IF NOT EXISTS `ml__raci_roles` (
  `raci_rol_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`raci_rol_id`),
  UNIQUE KEY `uk_racr_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__reasignaciones
CREATE TABLE IF NOT EXISTS `ml__reasignaciones` (
  `reasignacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_origen_id` int(11) NOT NULL,
  `usuario_destino_id` int(11) NOT NULL,
  `fecha_efecto` date NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aplicada_por` int(11) DEFAULT NULL,
  `aplicada_en` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`reasignacion_id`),
  KEY `ix_reas_usuario_origen` (`usuario_origen_id`),
  KEY `ix_reas_usuario_destino` (`usuario_destino_id`),
  KEY `ix_reas_aplicada_por` (`aplicada_por`),
  CONSTRAINT `fk_reas_aplicada_por` FOREIGN KEY (`aplicada_por`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_reas_usuario_destino_id` FOREIGN KEY (`usuario_destino_id`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `fk_reas_usuario_origen_id` FOREIGN KEY (`usuario_origen_id`) REFERENCES `conf__usuarios` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__recalculos_pendientes
CREATE TABLE IF NOT EXISTS `ml__recalculos_pendientes` (
  `recalculo_pendiente_id` int(11) NOT NULL AUTO_INCREMENT,
  `causa` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objeto_tabla` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objeto_id` int(11) NOT NULL,
  `fecha_efecto` date NOT NULL,
  `creado_en` datetime NOT NULL,
  `procesado_en` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL,
  PRIMARY KEY (`recalculo_pendiente_id`),
  KEY `ix_recp_tabla_estado_registro_fecha_efecto` (`tabla_estado_registro_id`,`fecha_efecto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__regiones_grupo
CREATE TABLE IF NOT EXISTS `ml__regiones_grupo` (
  `region_grupo_id` int(11) NOT NULL AUTO_INCREMENT,
  `grupo_id` int(11) NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`region_grupo_id`),
  KEY `ix_rgr_grupo` (`grupo_id`),
  CONSTRAINT `fk_rgr_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`),
  CONSTRAINT `ck_rgr_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__tareas_plantillas
CREATE TABLE IF NOT EXISTS `ml__tareas_plantillas` (
  `tarea_plantilla_id` int(11) NOT NULL AUTO_INCREMENT,
  `obligacion_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offset_dias_inicio` int(11) DEFAULT NULL,
  `offset_dias_vencimiento` int(11) DEFAULT NULL,
  `procedimiento_id` int(11) DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`tarea_plantilla_id`),
  UNIQUE KEY `uk_tpl_obligacion_orden` (`obligacion_id`,`orden`),
  KEY `ix_tpl_procedimiento` (`procedimiento_id`),
  CONSTRAINT `fk_tpl_obligacion_id` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `fk_tpl_procedimiento_id` FOREIGN KEY (`procedimiento_id`) REFERENCES `ml__procedimientos` (`procedimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__tareas_plantillas_dependencias
CREATE TABLE IF NOT EXISTS `ml__tareas_plantillas_dependencias` (
  `tarea_plantilla_dependencia_id` int(11) NOT NULL AUTO_INCREMENT,
  `tarea_plantilla_id` int(11) NOT NULL,
  `tarea_plantilla_predecesora_id` int(11) NOT NULL,
  `estado_activador_id` smallint(6) NOT NULL,
  `obligatoria` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`tarea_plantilla_dependencia_id`),
  UNIQUE KEY `uk_tpld_tarea_plantilla_tarea_plantilla_predecesora` (`tarea_plantilla_id`,`tarea_plantilla_predecesora_id`),
  KEY `ix_tpld_tarea_plantilla_predecesora` (`tarea_plantilla_predecesora_id`),
  CONSTRAINT `fk_tpld_tarea_plantilla_id` FOREIGN KEY (`tarea_plantilla_id`) REFERENCES `ml__tareas_plantillas` (`tarea_plantilla_id`),
  CONSTRAINT `fk_tpld_tarea_plantilla_predecesora_id` FOREIGN KEY (`tarea_plantilla_predecesora_id`) REFERENCES `ml__tareas_plantillas` (`tarea_plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__tareas_plantillas_raci
CREATE TABLE IF NOT EXISTS `ml__tareas_plantillas_raci` (
  `tarea_plantilla_raci_id` int(11) NOT NULL AUTO_INCREMENT,
  `tarea_plantilla_id` int(11) NOT NULL,
  `raci_rol_id` int(11) NOT NULL,
  `posicion_id` int(11) DEFAULT NULL,
  `usar_supervisor_como_aprobador` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Rol A aprobado automáticamente por el supervisor del cargo R',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`tarea_plantilla_raci_id`),
  UNIQUE KEY `uk_tpl_raci_pos` (`tarea_plantilla_id`,`raci_rol_id`,`posicion_id`),
  KEY `ix_tplr_plantilla` (`tarea_plantilla_id`),
  KEY `ix_tplr_rol` (`raci_rol_id`),
  KEY `ix_tplr_posicion` (`posicion_id`),
  CONSTRAINT `fk_tplr_plantilla` FOREIGN KEY (`tarea_plantilla_id`) REFERENCES `ml__tareas_plantillas` (`tarea_plantilla_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tplr_posicion` FOREIGN KEY (`posicion_id`) REFERENCES `ml__posiciones` (`posicion_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tplr_rol` FOREIGN KEY (`raci_rol_id`) REFERENCES `ml__raci_roles` (`raci_rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__territorios
CREATE TABLE IF NOT EXISTS `ml__territorios` (
  `territorio_id` int(11) NOT NULL AUTO_INCREMENT,
  `territorio_tipo_id` int(11) NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `huso_horario` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`territorio_id`),
  KEY `ix_ter_territorio_tipo` (`territorio_tipo_id`),
  CONSTRAINT `fk_ter_territorio_tipo_id` FOREIGN KEY (`territorio_tipo_id`) REFERENCES `ml__territorios_tipos` (`territorio_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__territorios_pertenencias
CREATE TABLE IF NOT EXISTS `ml__territorios_pertenencias` (
  `territorio_pertenencia_id` int(11) NOT NULL AUTO_INCREMENT,
  `territorio_id` int(11) NOT NULL,
  `territorio_superior_id` int(11) NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `principal_abierta_id` int(11) GENERATED ALWAYS AS (case when `es_principal` = 1 and `vigente_hasta` is null then `territorio_id` else NULL end) STORED,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`territorio_pertenencia_id`),
  UNIQUE KEY `uk_terp_principal_abierta` (`principal_abierta_id`),
  KEY `ix_terp_territorio_hasta` (`territorio_id`,`vigente_hasta`),
  KEY `ix_terp_territorio_superior_hasta` (`territorio_superior_id`,`vigente_hasta`),
  CONSTRAINT `fk_terp_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `fk_terp_territorio_superior_id` FOREIGN KEY (`territorio_superior_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `ck_terp_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__territorios_tipos
CREATE TABLE IF NOT EXISTS `ml__territorios_tipos` (
  `territorio_tipo_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`territorio_tipo_id`),
  UNIQUE KEY `uk_tert_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__usuarios_ambitos
CREATE TABLE IF NOT EXISTS `ml__usuarios_ambitos` (
  `usuario_ambito_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `empresa_perfil_id` int(10) unsigned NOT NULL,
  `ambito_tipo_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `region_grupo_id` int(11) DEFAULT NULL,
  `empresa_id` smallint(5) unsigned DEFAULT NULL,
  `territorio_id` int(11) DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`usuario_ambito_id`),
  KEY `ix_usam_usuario_hasta` (`usuario_id`,`vigente_hasta`),
  KEY `ix_usam_empresa_perfil` (`empresa_perfil_id`),
  KEY `ix_usam_ambito_tipo` (`ambito_tipo_id`),
  KEY `ix_usam_grupo` (`grupo_id`),
  KEY `ix_usam_region_grupo` (`region_grupo_id`),
  KEY `ix_usam_empresa` (`empresa_id`),
  KEY `ix_usam_territorio` (`territorio_id`),
  CONSTRAINT `fk_usam_ambito_tipo_id` FOREIGN KEY (`ambito_tipo_id`) REFERENCES `ml__ambitos_tipos` (`ambito_tipo_id`),
  CONSTRAINT `fk_usam_empresa_id` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_usam_empresa_perfil_id` FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`),
  CONSTRAINT `fk_usam_grupo_id` FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`),
  CONSTRAINT `fk_usam_region_grupo_id` FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`),
  CONSTRAINT `fk_usam_territorio_id` FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`),
  CONSTRAINT `fk_usam_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`),
  CONSTRAINT `ck_usam_vigencia` CHECK (`vigente_hasta` is null or `vigente_hasta` >= `vigente_desde`),
  CONSTRAINT `ck_usam_ambito` CHECK ((`grupo_id` is not null) + (`region_grupo_id` is not null) + (`empresa_id` is not null) + (`territorio_id` is not null) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__usuarios_posiciones
CREATE TABLE IF NOT EXISTS `ml__usuarios_posiciones` (
  `usuario_posicion_id` int(11) NOT NULL AUTO_INCREMENT,
  `posicion_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `es_titular` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Titular, 0 = Adjunto/Suplente en el puesto',
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL COMMENT 'NULL = Ocupante activo actualmente',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`usuario_posicion_id`),
  KEY `ix_up_posicion` (`posicion_id`),
  KEY `ix_up_usuario` (`usuario_id`),
  KEY `ix_up_vigencia` (`usuario_id`,`vigente_hasta`),
  CONSTRAINT `fk_up_posicion` FOREIGN KEY (`posicion_id`) REFERENCES `ml__posiciones` (`posicion_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ml__vencimientos_reglas
CREATE TABLE IF NOT EXISTS `ml__vencimientos_reglas` (
  `vencimiento_regla_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dato_tipo_id` int(11) DEFAULT NULL,
  `desplazamiento_dias` int(11) DEFAULT NULL,
  `parametros` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`vencimiento_regla_id`),
  UNIQUE KEY `uk_venr_codigo` (`codigo`),
  KEY `ix_venr_dato_tipo` (`dato_tipo_id`),
  CONSTRAINT `fk_venr_dato_tipo_id` FOREIGN KEY (`dato_tipo_id`) REFERENCES `ml__datos_tipos` (`dato_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__actas
CREATE TABLE IF NOT EXISTS `sindi__actas` (
  `acta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empleador_id` int(10) unsigned NOT NULL,
  `numero_acta` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Número identificador del acta (ej. ACT-2026-0001)',
  `tipo_acta` enum('NORMAL','LIBRE_DEUDA','COMPLEMENTARIA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMAL',
  `inspector_usuario_id` int(11) DEFAULT NULL COMMENT 'Usuario inspector asignado',
  `f_inicio` date NOT NULL COMMENT 'Fecha de apertura/inicio de la inspección',
  `f_cierre` date DEFAULT NULL COMMENT 'Fecha de cierre / emisión',
  `f_notificacion` date DEFAULT NULL COMMENT 'Fecha de notificación al empleador',
  `f_vencimiento_72h` datetime DEFAULT NULL COMMENT 'Plazo de 72 hs hábiles para descargo / regularización',
  `total_declarado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_determinado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_pagado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_diferencia` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Diferencia neta de capital',
  `total_intereses` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Intereses liquidados a la fecha del acta',
  `total_acta` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Total exigible (diferencia + intereses)',
  `resolucion_tipo` enum('PENDIENTE','CONTADO','PLAN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `plan_id` int(10) unsigned DEFAULT NULL COMMENT 'Vínculo al plan si se refinancia',
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Vínculo al comprobante tipo ACT en gestion__comprobantes',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 3 COMMENT '3 Borrador, 18 En curso, 5 Confirmado, 40 Cancelado total, 38 Anulado',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`acta_id`),
  UNIQUE KEY `uk_acta_numero` (`empresa_id`,`numero_acta`),
  KEY `idx_acta_empleador` (`empresa_id`,`empleador_id`,`tabla_estado_registro_id`),
  KEY `idx_acta_fechas` (`empresa_id`,`f_inicio`,`f_vencimiento_72h`),
  KEY `idx_acta_comprobante` (`comprobante_id`),
  KEY `fk_actas_empleador` (`empleador_id`),
  CONSTRAINT `fk_actas_empleador` FOREIGN KEY (`empleador_id`) REFERENCES `sindi__empleadores` (`empleador_id`),
  CONSTRAINT `fk_actas_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actas de fiscalización e inspección sindical';

-- sindi__actas_items
CREATE TABLE IF NOT EXISTS `sindi__actas_items` (
  `acta_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `acta_periodo_id` int(10) unsigned NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `concepto_id` int(10) unsigned NOT NULL,
  `importe_declarado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_determinado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_pagado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `diferencia_capital` decimal(18,2) NOT NULL DEFAULT 0.00,
  `intereses` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_item` decimal(18,2) NOT NULL DEFAULT 0.00,
  `comprobante_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`acta_item_id`),
  UNIQUE KEY `uk_acta_item_concepto` (`acta_periodo_id`,`concepto_id`),
  KEY `idx_acta_item_concepto` (`concepto_id`),
  CONSTRAINT `fk_acta_item_concepto` FOREIGN KEY (`concepto_id`) REFERENCES `sindi__conceptos` (`concepto_id`),
  CONSTRAINT `fk_acta_item_periodo` FOREIGN KEY (`acta_periodo_id`) REFERENCES `sindi__actas_periodos` (`acta_periodo_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle por concepto de los períodos de un acta';

-- sindi__actas_periodos
CREATE TABLE IF NOT EXISTS `sindi__actas_periodos` (
  `acta_periodo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `acta_id` int(10) unsigned NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `periodo` int(11) NOT NULL COMMENT 'Período inspeccionado AAAAMM',
  `f_vto_original` date DEFAULT NULL,
  `total_declarado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_determinado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_pagado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `saldo_capital` decimal(18,2) NOT NULL DEFAULT 0.00,
  `intereses` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_periodo` decimal(18,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`acta_periodo_id`),
  UNIQUE KEY `uk_acta_periodo` (`acta_id`,`periodo`),
  KEY `idx_acta_per_empresa` (`empresa_id`,`periodo`),
  CONSTRAINT `fk_acta_per_acta` FOREIGN KEY (`acta_id`) REFERENCES `sindi__actas` (`acta_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Períodos comprendidos en un acta de inspección';

-- sindi__afiliaciones
CREATE TABLE IF NOT EXISTS `sindi__afiliaciones` (
  `afiliacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `persona_id` int(10) unsigned NOT NULL,
  `afiliacion_tipo_id` int(10) unsigned NOT NULL,
  `titular_afiliacion_id` int(10) unsigned DEFAULT NULL,
  `parentesco_id` int(10) unsigned DEFAULT NULL,
  `empleador_id` int(10) unsigned DEFAULT NULL,
  `f_alta` date NOT NULL,
  `f_baja` date DEFAULT NULL,
  `f_vigencia` date DEFAULT NULL,
  `baja_motivo_id` int(10) unsigned DEFAULT NULL,
  `ddjj_baja_id` int(10) unsigned DEFAULT NULL COMMENT 'DDJJ que dio la baja (actividad)',
  `origen` enum('DDJJ','SOLICITUD','MIGRACION') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DDJJ',
  `origen_id` int(10) unsigned DEFAULT NULL COMMENT 'ddjj_id u origen',
  `numero_afiliado` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1 COMMENT '1=Activo, 2=Inactivo/Baja',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`afiliacion_id`),
  KEY `idx_afil_persona` (`empresa_id`,`persona_id`),
  KEY `idx_afil_empleador` (`empleador_id`),
  KEY `idx_afil_vigencia` (`f_alta`,`f_baja`),
  KEY `idx_afil_numero` (`empresa_id`,`numero_afiliado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__afiliaciones_cuotas
CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_cuotas` (
  `afiliacion_cuota_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `afiliacion_id` int(10) unsigned NOT NULL,
  `periodo` int(11) NOT NULL,
  `importe` decimal(18,2) NOT NULL,
  `comprobante_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`afiliacion_cuota_id`),
  UNIQUE KEY `uk_afcuota_periodo` (`afiliacion_id`,`periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuotas directas mensuales generadas (voluntarios y jubilados)';

-- sindi__afiliaciones_documentos
CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_documentos` (
  `afiliacion_documento_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `afiliacion_id` int(10) unsigned NOT NULL,
  `documento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_ruta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_presentacion` date NOT NULL,
  `f_vencimiento` date NOT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`afiliacion_documento_id`),
  KEY `idx_afdoc_afiliacion` (`afiliacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos presentados para excepciones de vigencia';

-- sindi__afiliaciones_solicitudes
CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_solicitudes` (
  `solicitud_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `afiliacion_tipo_id` int(10) unsigned NOT NULL,
  `persona_id` int(10) unsigned DEFAULT NULL,
  `cuil` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_nacimiento` date DEFAULT NULL,
  `sexo` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empleador_id` int(10) unsigned DEFAULT NULL,
  `titular_afiliacion_id` int(10) unsigned DEFAULT NULL,
  `parentesco_id` int(10) unsigned DEFAULT NULL,
  `canal` enum('INTERNO','PORTAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INTERNO',
  `f_solicitud` date NOT NULL,
  `f_resolucion` date DEFAULT NULL,
  `motivo_rechazo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `afiliacion_id` int(10) unsigned DEFAULT NULL COMMENT 'Afiliación creada al aprobar',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 3,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`solicitud_id`),
  KEY `idx_afsol_empresa_estado` (`empresa_id`,`tabla_estado_registro_id`),
  KEY `idx_afsol_cuil` (`empresa_id`,`cuil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitudes de afiliación voluntaria, jubilados y familiares';

-- sindi__afiliaciones_tipos
CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_tipos` (
  `afiliacion_tipo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ACTIVIDAD, VOLUNTARIA, JUBILADO, FAMILIAR',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grupo` enum('TITULAR','FAMILIAR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TITULAR',
  `alta` enum('AUTOMATICA','SOLICITUD') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SOLICITUD',
  `requiere_empleador` tinyint(1) NOT NULL DEFAULT 0,
  `paga_cuota_directa` tinyint(1) NOT NULL DEFAULT 0,
  `importe_cuota` decimal(18,2) NOT NULL DEFAULT 0.00,
  `requiere_titular` tinyint(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`afiliacion_tipo_id`),
  UNIQUE KEY `uk_afiltipo_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__afiliaciones_vigencias
CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_vigencias` (
  `afiliacion_vigencia_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `parentesco_id` int(10) unsigned NOT NULL,
  `edad_maxima` tinyint(3) unsigned DEFAULT NULL COMMENT 'Cobertura hasta el día anterior a cumplir esta edad; NULL = sin límite',
  `edad_maxima_excepcion` tinyint(3) unsigned DEFAULT NULL COMMENT 'Límite extendido presentando el documento de excepción',
  `documento_excepcion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Documento que habilita la excepción (p. ej. certificado de alumno regular)',
  `meses_validez_documento` tinyint(3) unsigned NOT NULL DEFAULT 12,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`afiliacion_vigencia_id`),
  UNIQUE KEY `uk_vigencia_parentesco` (`empresa_id`,`parentesco_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vigencia de la cobertura familiar por parentesco y edad';

-- sindi__bajas_motivos
CREATE TABLE IF NOT EXISTS `sindi__bajas_motivos` (
  `baja_motivo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`baja_motivo_id`),
  UNIQUE KEY `uk_bm_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Motivos de desvinculaci├│n laboral o baja sindical';

-- sindi__categorias
CREATE TABLE IF NOT EXISTS `sindi__categorias` (
  `categoria_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `convenio_id` int(10) unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`categoria_id`),
  UNIQUE KEY `uk_cat_codigo` (`empresa_id`,`convenio_id`,`codigo`),
  KEY `idx_cat_empresa` (`empresa_id`,`convenio_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categor├¡as salariales del CCT';

-- sindi__categorias_escalas
CREATE TABLE IF NOT EXISTS `sindi__categorias_escalas` (
  `escala_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `categoria_id` int(10) unsigned NOT NULL,
  `periodo` int(11) NOT NULL COMMENT 'AAAAMM',
  `basico` decimal(18,2) NOT NULL DEFAULT 0.00,
  `rem_1` decimal(18,2) NOT NULL DEFAULT 0.00,
  `rem_2` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`escala_id`),
  UNIQUE KEY `uk_esc_periodo` (`empresa_id`,`categoria_id`,`periodo`),
  KEY `idx_esc_cat` (`categoria_id`,`periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Escala salarial para c├ílculo del piso de aportes por categor├¡a y per├¡odo';

-- sindi__categorias_mora
CREATE TABLE IF NOT EXISTS `sindi__categorias_mora` (
  `categoria_mora_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dias_desde` int(11) NOT NULL DEFAULT 0,
  `dias_hasta` int(11) NOT NULL DEFAULT 0,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6c757d',
  `orden` int(11) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`categoria_mora_id`),
  UNIQUE KEY `uk_cat_mora` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categor├¡as de mora para sem├íforo de fiscalizaci├│n (Puntual a Cr├│nica)';

-- sindi__conceptos
CREATE TABLE IF NOT EXISTS `sindi__conceptos` (
  `concepto_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `convenio_id` int(10) unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_calculo` enum('REM_TOTAL','REMUNERATIVO','FIJO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'REM_TOTAL',
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `valor_fijo` decimal(18,2) NOT NULL DEFAULT 0.00,
  `solo_afiliados` tinyint(1) NOT NULL DEFAULT 0,
  `aplica_piso_escala` tinyint(1) NOT NULL DEFAULT 1,
  `incluye_sac` tinyint(1) NOT NULL DEFAULT 1,
  `comprobante_tipo_id` int(10) unsigned DEFAULT NULL,
  `comprobante_tipo_reversion_id` int(10) unsigned DEFAULT NULL COMMENT 'Tipo de comprobante de la reversión por rectificativa (signo -1)',
  `cuenta_concepto_codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SINDI_DEUDORES_APORTES',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`concepto_id`),
  UNIQUE KEY `uk_conc_codigo` (`empresa_id`,`codigo`,`f_desde`),
  KEY `idx_conc_convenio` (`empresa_id`,`convenio_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conceptos de liquidaci├│n y aporte mensual de DDJJ';

-- sindi__convenios
CREATE TABLE IF NOT EXISTS `sindi__convenios` (
  `convenio_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`convenio_id`),
  UNIQUE KEY `uk_conv_codigo` (`empresa_id`,`codigo`),
  KEY `idx_conv_empresa` (`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Convenios colectivos de trabajo administrados por el sindicato';

-- sindi__ddjj
CREATE TABLE IF NOT EXISTS `sindi__ddjj` (
  `ddjj_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empleador_id` int(10) unsigned NOT NULL,
  `periodo` int(11) NOT NULL COMMENT 'AAAAMM ej: 202610',
  `secuencia` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0=original, 1..9=rectificativas',
  `ddjj_rectificada_id` int(10) unsigned DEFAULT NULL COMMENT 'DDJJ anterior revertida',
  `media_jornada_piso` enum('COMPLETO','PROPORCIONAL','SIN_PISO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COMPLETO',
  `tipo` enum('EMPLEADOR','OFICIO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EMPLEADOR',
  `origen_ingreso` enum('MANUAL','F931','PLANILLA','LOTE','EMAIL','MIGRACION') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL',
  `f_vto` date NOT NULL,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cantidad_empleados` int(11) NOT NULL DEFAULT 0,
  `codigo_verificacion` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código único para constancia oficial',
  `usuario_presenta_id` int(11) DEFAULT NULL,
  `estudio_id` int(10) unsigned DEFAULT NULL COMMENT 'Estudio contable que presentó',
  `f_presentacion` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 3 COMMENT '3=Borrador, 28=Presentado, 2=Rectificada, 38=Anulada',
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`ddjj_id`),
  UNIQUE KEY `uk_ddjj_secuencia` (`empresa_id`,`empleador_id`,`periodo`,`secuencia`),
  KEY `idx_ddjj_empresa_periodo` (`empresa_id`,`periodo`),
  KEY `idx_ddjj_empleador` (`empleador_id`),
  KEY `idx_ddjj_estado` (`tabla_estado_registro_id`),
  KEY `idx_ddjj_codigo` (`codigo_verificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__ddjj_calculos
CREATE TABLE IF NOT EXISTS `sindi__ddjj_calculos` (
  `ddjj_calculo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `ddjj_id` int(10) unsigned NOT NULL,
  `ddjj_empleado_id` int(10) unsigned NOT NULL,
  `concepto_id` int(10) unsigned NOT NULL,
  `base_declarada` decimal(18,2) NOT NULL DEFAULT 0.00,
  `piso_escala` decimal(18,2) NOT NULL DEFAULT 0.00,
  `base_aplicada` decimal(18,2) NOT NULL DEFAULT 0.00,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `valor_fijo` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe` decimal(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`ddjj_calculo_id`),
  UNIQUE KEY `uk_calc_renglon_concepto` (`ddjj_empleado_id`,`concepto_id`),
  KEY `idx_calc_ddjj` (`ddjj_id`),
  KEY `idx_calc_concepto` (`concepto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__ddjj_conceptos
CREATE TABLE IF NOT EXISTS `sindi__ddjj_conceptos` (
  `ddjj_concepto_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `ddjj_id` int(10) unsigned NOT NULL,
  `concepto_id` int(10) unsigned NOT NULL,
  `base_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cantidad_empleados` int(11) NOT NULL DEFAULT 0,
  `importe_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK gestion__comprobantes',
  PRIMARY KEY (`ddjj_concepto_id`),
  UNIQUE KEY `uk_ddjj_concepto` (`ddjj_id`,`concepto_id`),
  KEY `idx_ddjj_conc_comprobante` (`comprobante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__ddjj_empleados
CREATE TABLE IF NOT EXISTS `sindi__ddjj_empleados` (
  `ddjj_empleado_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `ddjj_id` int(10) unsigned NOT NULL,
  `persona_id` int(10) unsigned NOT NULL,
  `sucursal_id` int(10) unsigned DEFAULT NULL COMMENT 'Establecimiento del empleador donde trabaja (gestion__entidades_sucursales)',
  `categoria_id` int(10) unsigned NOT NULL,
  `rem_1` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Remunerativo',
  `rem_2` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'No remunerativo',
  `rem_3` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Otros conceptos',
  `media_jornada` tinyint(1) NOT NULL DEFAULT 0,
  `jubilado` tinyint(1) NOT NULL DEFAULT 0,
  `afiliado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Marca declarada por empleador',
  `obra_social_id` int(10) unsigned DEFAULT NULL,
  `novedad_id` int(10) unsigned DEFAULT NULL,
  `baja_motivo_id` int(10) unsigned DEFAULT NULL,
  `f_baja` date DEFAULT NULL,
  `localidad_id` int(11) DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ddjj_empleado_id`),
  UNIQUE KEY `uk_ddjj_persona` (`ddjj_id`,`persona_id`),
  KEY `idx_ddjj_emp_persona` (`persona_id`),
  KEY `idx_ddjj_emp_cat` (`categoria_id`),
  KEY `idx_ddjjemp_sucursal` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__ddjj_importaciones
CREATE TABLE IF NOT EXISTS `sindi__ddjj_importaciones` (
  `importacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empleador_id` int(10) unsigned DEFAULT NULL,
  `periodo` int(11) DEFAULT NULL,
  `formato` enum('F931_SICOSS','EXCEL_CSV','LOTE_MULTI') COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filas_total` int(11) NOT NULL DEFAULT 0,
  `filas_aceptadas` int(11) NOT NULL DEFAULT 0,
  `filas_rechazadas` int(11) NOT NULL DEFAULT 0,
  `errores_detalle` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON con log de advertencias y errores',
  `ddjj_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`importacion_id`),
  KEY `idx_imp_empresa_periodo` (`empresa_id`,`periodo`),
  KEY `idx_imp_empleador` (`empleador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sindi__empleadores
CREATE TABLE IF NOT EXISTS `sindi__empleadores` (
  `empleador_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `entidad_id` int(10) unsigned NOT NULL,
  `rubro` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `convenio_id` int(10) unsigned DEFAULT NULL,
  `categoria_mora_id` int(10) unsigned DEFAULT NULL,
  `f_alta_padron` date NOT NULL,
  `origen` enum('AUTOALTA','SINDICATO','OFICIO','MIGRACION') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SINDICATO',
  `legacy_id` int(11) DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`empleador_id`),
  UNIQUE KEY `uk_emp_entidad` (`empresa_id`,`entidad_id`),
  KEY `idx_emp_mora` (`empresa_id`,`categoria_mora_id`),
  KEY `idx_emp_estado` (`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extensi├│n del padr├│n de empleadores vinculada a gestion__entidades';

-- sindi__empleadores_periodos
CREATE TABLE IF NOT EXISTS `sindi__empleadores_periodos` (
  `empleador_periodo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empleador_id` int(10) unsigned NOT NULL,
  `periodo` int(11) NOT NULL COMMENT 'AAAAMM',
  `f_vto` date NOT NULL,
  `ddjj_vigente_id` int(11) DEFAULT NULL,
  `situacion` enum('PENDIENTE','PRESENTADA','FUERA_DE_TERMINO','OFICIO','NO_COMPUTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `dias_atraso` int(11) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`empleador_periodo_id`),
  UNIQUE KEY `uk_ep_periodo` (`empresa_id`,`empleador_id`,`periodo`),
  KEY `idx_ep_situacion` (`empresa_id`,`periodo`,`situacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Seguimiento y estado de cumplimiento mensual de DDJJ por empleador';

-- sindi__estudios
CREATE TABLE IF NOT EXISTS `sindi__estudios` (
  `estudio_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `entidad_id` int(10) unsigned NOT NULL,
  `matricula` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legacy_id` int(11) DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`estudio_id`),
  UNIQUE KEY `uk_est_entidad` (`empresa_id`,`entidad_id`),
  KEY `idx_est_estado` (`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estudios contables vinculados a gestion__entidades';

-- sindi__estudios_empleadores
CREATE TABLE IF NOT EXISTS `sindi__estudios_empleadores` (
  `estudio_empleador_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `estudio_id` int(10) unsigned NOT NULL,
  `empleador_id` int(10) unsigned NOT NULL,
  `iniciado_por` enum('ESTUDIO','EMPLEADOR','SINDICATO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ESTUDIO',
  `f_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `f_resolucion` datetime DEFAULT NULL,
  `f_baja` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 4 COMMENT '4=Pendiente aprobaci├│n, 1=Activo/Aprobado, 25=Rechazado, 2=Inactivo/Baja',
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`estudio_empleador_id`),
  KEY `idx_ee_rel` (`empresa_id`,`estudio_id`,`empleador_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitudes y vinculaciones formales entre estudios y empleadores';

-- sindi__medios_pago
CREATE TABLE IF NOT EXISTS `sindi__medios_pago` (
  `medio_pago_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver` enum('BNA_BARRAS','RED_LINK','INTERBANKING','BAPRO','MANUAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL',
  `ambiente` enum('PRUEBA','PRODUCCION') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRUEBA',
  `orden` int(11) NOT NULL DEFAULT 0,
  `tesoreria_cuenta_codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medio_pago_codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entidad_bancaria_id` int(11) DEFAULT NULL,
  `url_servicio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_autenticacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ente_acuerdo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_secret` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `certificado_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_privada_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verificar_ssl` tinyint(1) NOT NULL DEFAULT 1,
  `publica_deuda` tinyint(1) NOT NULL DEFAULT 0,
  `concepto_defecto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rendicion_modo` enum('API','SFTP','ARCHIVO_MANUAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ARCHIVO_MANUAL',
  `sftp_host` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sftp_puerto` int(11) DEFAULT 22,
  `sftp_usuario` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sftp_clave` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `sftp_carpeta_remota` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sftp_patron_archivo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barras_prefijo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barras_simbologia` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Code128',
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`medio_pago_id`),
  UNIQUE KEY `uk_mp_codigo` (`empresa_id`,`codigo`),
  KEY `idx_mp_empresa` (`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medios de cobro del sindicato con credenciales cifradas';

-- sindi__medios_pago_layout
CREATE TABLE IF NOT EXISTS `sindi__medios_pago_layout` (
  `layout_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `campo_nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `posicion_inicio` int(11) NOT NULL,
  `longitud` int(11) NOT NULL,
  `formato` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'STRING',
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`layout_id`),
  KEY `idx_mpl_mp` (`medio_pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Definici├│n posicional de los archivos de rendici├│n bancaria';

-- sindi__novedades
CREATE TABLE IF NOT EXISTS `sindi__novedades` (
  `novedad_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`novedad_id`),
  UNIQUE KEY `uk_nov_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Novedades de personal (licencias, maternidad, etc.)';

-- sindi__obras_sociales
CREATE TABLE IF NOT EXISTS `sindi__obras_sociales` (
  `obra_social_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo_rnos` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sigla` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_alta` date DEFAULT NULL,
  `f_baja` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`obra_social_id`),
  UNIQUE KEY `uk_rnos` (`codigo_rnos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cat├ílogo de obras sociales de la Superintendencia (c├│digo RNOS F.931)';

-- sindi__parametros
CREATE TABLE IF NOT EXISTS `sindi__parametros` (
  `parametro_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `convenio_id_default` int(10) unsigned DEFAULT NULL,
  `portal_slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sindicato',
  `dias_gracia` int(11) NOT NULL DEFAULT 0,
  `aprobacion_autoalta` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=alta directa, 1=requiere aprobacion administrativa',
  `email_avisos` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_jornada_piso` enum('COMPLETO','PROPORCIONAL','SIN_PISO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COMPLETO',
  `media_jornada_factor` decimal(5,2) NOT NULL DEFAULT 0.50,
  `smtp_host` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_puerto` int(11) DEFAULT 587,
  `smtp_seguridad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'tls',
  `smtp_usuario` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_clave` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `correo_ingreso_host` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_ingreso_puerto` int(11) DEFAULT 993,
  `correo_ingreso_protocolo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'imap',
  `correo_ingreso_seguridad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ssl',
  `correo_ingreso_usuario` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_ingreso_clave` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cifrado AES-256-GCM',
  `gdrive_service_account_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gdrive_folder_id` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`parametro_id`),
  UNIQUE KEY `uk_sp_vigencia` (`empresa_id`,`f_desde`),
  KEY `idx_sp_empresa` (`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Par├ímetros generales de cada sindicato con vigencia temporal';

-- sindi__parentescos
CREATE TABLE IF NOT EXISTS `sindi__parentescos` (
  `parentesco_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parentesco_inverso_id` int(10) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`parentesco_id`),
  UNIQUE KEY `uk_parentesco_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parentescos del grupo familiar (con su inverso)';

-- sindi__personas
CREATE TABLE IF NOT EXISTS `sindi__personas` (
  `persona_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `cuil` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento_tipo_id` smallint(6) DEFAULT NULL,
  `documento_nro` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sexo` char(1) COLLATE utf8mb4_unicode_ci DEFAULT 'M',
  `f_nacimiento` date DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad_id` smallint(6) DEFAULT NULL,
  `entidad_id` int(10) unsigned DEFAULT NULL COMMENT 'Opcional, si paga cuota directa',
  `legacy_id` int(11) DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`persona_id`),
  UNIQUE KEY `uk_persona_cuil` (`empresa_id`,`cuil`),
  KEY `idx_per_ape_nom` (`empresa_id`,`apellido`,`nombre`),
  KEY `idx_per_estado` (`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Padr├│n de trabajadores y afiliados';

-- sindi__planes
CREATE TABLE IF NOT EXISTS `sindi__planes` (
  `plan_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `numero_plan` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identificador único del plan (ej. PLAN-2026-0001)',
  `empleador_id` int(10) unsigned NOT NULL,
  `plan_tipo_id` int(10) unsigned NOT NULL,
  `origen_tipo` enum('DEUDA_DDJJ','ACTA','MIXTO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DEUDA_DDJJ',
  `acta_id` int(10) unsigned DEFAULT NULL COMMENT 'Acta asociada si el plan proviene de una inspección',
  `capital_original` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Deuda base consolidada',
  `interes_financiero` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Interés pactado en la financiación',
  `total_financiado` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Capital + Interés',
  `anticipo_importe` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cantidad_cuotas` int(11) NOT NULL DEFAULT 1,
  `f_acuerdo` date NOT NULL,
  `f_primer_vto` date NOT NULL,
  `periodicidad_dias` int(11) NOT NULL DEFAULT 30,
  `saldo_pendiente` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 3 COMMENT '3 Borrador, 5 Confirmado, 39 Canc. Parcial, 40 Canc. Total, 38 Anulado',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `uk_plan_numero` (`empresa_id`,`numero_plan`),
  KEY `idx_plan_empleador` (`empresa_id`,`empleador_id`,`tabla_estado_registro_id`),
  KEY `idx_plan_acta` (`acta_id`),
  KEY `fk_planes_empleador` (`empleador_id`),
  KEY `fk_planes_tipo` (`plan_tipo_id`),
  CONSTRAINT `fk_planes_acta` FOREIGN KEY (`acta_id`) REFERENCES `sindi__actas` (`acta_id`),
  CONSTRAINT `fk_planes_empleador` FOREIGN KEY (`empleador_id`) REFERENCES `sindi__empleadores` (`empleador_id`),
  CONSTRAINT `fk_planes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_planes_tipo` FOREIGN KEY (`plan_tipo_id`) REFERENCES `sindi__planes_tipos` (`plan_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Acuerdos de planes de pago y facilidades';

-- sindi__planes_cuotas
CREATE TABLE IF NOT EXISTS `sindi__planes_cuotas` (
  `plan_cuota_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `numero_cuota` int(11) NOT NULL COMMENT '0 anticipo, 1..N cuotas',
  `f_vto` date NOT NULL,
  `capital` decimal(18,2) NOT NULL DEFAULT 0.00,
  `interes` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_cuota` decimal(18,2) NOT NULL DEFAULT 0.00,
  `saldo_pendiente` decimal(18,2) NOT NULL DEFAULT 0.00,
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Comprobante formal tipo PLC en gestion__comprobantes',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 3 COMMENT '3 Borrador, 5 Pendiente/Emitido, 40 Cancelado total',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`plan_cuota_id`),
  UNIQUE KEY `uk_plan_cuota` (`plan_id`,`numero_cuota`),
  KEY `idx_plan_cuota_vto` (`empresa_id`,`f_vto`,`tabla_estado_registro_id`),
  KEY `idx_plan_cuota_comp` (`comprobante_id`),
  CONSTRAINT `fk_pc_plan` FOREIGN KEY (`plan_id`) REFERENCES `sindi__planes` (`plan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuotas individuales de planes de pago';

-- sindi__planes_tipos
CREATE TABLE IF NOT EXISTS `sindi__planes_tipos` (
  `plan_tipo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sistema` enum('DIRECTO','FRANCES','ALEMAN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DIRECTO',
  `cuotas_max` int(11) NOT NULL DEFAULT 12,
  `tasa_mensual` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `anticipo_min_porc` decimal(5,2) NOT NULL DEFAULT 0.00,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`plan_tipo_id`),
  UNIQUE KEY `uk_plan_tipo_codigo` (`empresa_id`,`codigo`),
  CONSTRAINT `fk_pt_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de planes de pago y condiciones financieras';

-- sindi__relaciones_laborales
CREATE TABLE IF NOT EXISTS `sindi__relaciones_laborales` (
  `relacion_laboral_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empleador_id` int(10) unsigned NOT NULL,
  `persona_id` int(10) unsigned NOT NULL,
  `sucursal_id` int(10) unsigned DEFAULT NULL COMMENT 'Último establecimiento declarado en la DDJJ',
  `f_alta` date NOT NULL,
  `f_baja` date DEFAULT NULL,
  `ddjj_alta_id` int(11) DEFAULT NULL,
  `ddjj_baja_id` int(11) DEFAULT NULL,
  `baja_motivo_id` int(10) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`relacion_laboral_id`),
  KEY `idx_rl_emp_per` (`empresa_id`,`empleador_id`,`persona_id`,`tabla_estado_registro_id`),
  KEY `idx_rl_per` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relaciones laborales declaradas en DDJJ o dadas de alta por empleador';

-- sindi__rendiciones
CREATE TABLE IF NOT EXISTS `sindi__rendiciones` (
  `rendicion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `f_rendicion` date NOT NULL,
  `archivo_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_registros` int(11) NOT NULL DEFAULT 0,
  `total_importe` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 8 COMMENT '8 Recibido, 5 Confirmado/Conciliado, 35 Pagado/Procesado, 27 Con Error',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`rendicion_id`),
  KEY `idx_rend_empresa_medio` (`empresa_id`,`medio_pago_id`,`f_rendicion`),
  KEY `fk_rend_medio` (`medio_pago_id`),
  CONSTRAINT `fk_rend_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `fk_rend_medio` FOREIGN KEY (`medio_pago_id`) REFERENCES `sindi__medios_pago` (`medio_pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de archivos y lotes de rendición';

-- sindi__rendiciones_lineas
CREATE TABLE IF NOT EXISTS `sindi__rendiciones_lineas` (
  `rendicion_linea_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rendicion_id` int(10) unsigned NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `numero_linea` int(11) NOT NULL DEFAULT 1,
  `volante_id` int(10) unsigned DEFAULT NULL,
  `identificador_externo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código de barras leído, id de transacción, etc.',
  `f_pago` date NOT NULL,
  `f_acreditacion` date DEFAULT NULL,
  `importe_rendido` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sucursal_banco` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canal_origen` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resultado_conciliacion` enum('OK','DIFERENCIA','SIN_VOLANTE','DUPLICADA','ERROR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OK',
  `comprobante_cobro_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Comprobante formal de cobro generado',
  `nota_debito_interes_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ND de intereses emitida por este pago',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rendicion_linea_id`),
  KEY `idx_rl_rendicion` (`rendicion_id`),
  KEY `idx_rl_volante` (`volante_id`),
  CONSTRAINT `fk_rl_rendicion` FOREIGN KEY (`rendicion_id`) REFERENCES `sindi__rendiciones` (`rendicion_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Renglones individuales normalizados de rendición';

-- sindi__tasas_interes
CREATE TABLE IF NOT EXISTS `sindi__tasas_interes` (
  `tasa_interes_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `clase` int(11) NOT NULL DEFAULT 1 COMMENT '1 = Resarcitorio',
  `tasa_mensual` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `f_desde` date NOT NULL,
  `f_hasta` date NOT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `legacy_id` int(11) DEFAULT NULL,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`tasa_interes_id`),
  UNIQUE KEY `uk_tasa_vigencia` (`empresa_id`,`clase`,`f_desde`),
  KEY `idx_tasa_tramo` (`empresa_id`,`clase`,`f_desde`,`f_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tasas de inter├®s mensual por tramos sin superposici├│n';

-- sindi__usuarios_empleadores
CREATE TABLE IF NOT EXISTS `sindi__usuarios_empleadores` (
  `usuario_empleador_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empleador_id` int(10) unsigned NOT NULL,
  `rol` enum('TITULAR','ESTUDIO','DELEGADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TITULAR',
  `estudio_empleador_id` int(10) unsigned DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_empleador_id`),
  UNIQUE KEY `uk_ue` (`usuario_id`,`empresa_id`,`empleador_id`,`rol`),
  KEY `idx_ue_busq` (`usuario_id`,`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignaci├│n de empleadores a usuarios para control de acceso estricto';

-- sindi__vencimientos
CREATE TABLE IF NOT EXISTS `sindi__vencimientos` (
  `vencimiento_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `periodo` int(11) NOT NULL COMMENT 'AAAAMM',
  `terminacion_cuit` tinyint(4) NOT NULL COMMENT '0 al 9',
  `tipo_vencimiento` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DDJJ',
  `f_vto` date NOT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`vencimiento_id`),
  UNIQUE KEY `uk_vto` (`empresa_id`,`periodo`,`terminacion_cuit`,`tipo_vencimiento`),
  KEY `idx_vto_busq` (`empresa_id`,`periodo`,`terminacion_cuit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cronograma de vencimientos por per├¡odo y d├¡gito de CUIT';

-- sindi__volantes
CREATE TABLE IF NOT EXISTS `sindi__volantes` (
  `volante_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `empleador_id` int(10) unsigned DEFAULT NULL COMMENT 'Titular del volante de empleador (NULL en volantes de afiliado)',
  `persona_id` int(10) unsigned DEFAULT NULL COMMENT 'Para volantes directos de afiliados/jubilados',
  `tipo_volante` enum('EMPLEADOR','AFILIADO','PLAN','ACTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EMPLEADOR',
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Número legible único por empresa',
  `comprobante_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Vínculo al comprobante formal de numeración (tipo VOL)',
  `f_emision` date NOT NULL,
  `f_pago_1` date NOT NULL,
  `importe_capital_1` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_interes_1` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_1` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe fijo total al primer vencimiento',
  `f_pago_2` date NOT NULL,
  `importe_capital_2` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_interes_2` decimal(18,2) NOT NULL DEFAULT 0.00,
  `importe_2` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe fijo total al segundo vencimiento',
  `codigo_barras` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código de barras BNA Code128',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 3 COMMENT '3 Borrador, 31 Emitido, 39 Canc. Parcial, 40 Canc. Total, 20 Vencido, 38 Anulado',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_actualizacion_id` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`volante_id`),
  UNIQUE KEY `uk_volante_numero` (`empresa_id`,`numero`),
  KEY `idx_volante_empresa_empleador` (`empresa_id`,`empleador_id`,`tabla_estado_registro_id`),
  KEY `idx_volante_fechas` (`empresa_id`,`f_pago_1`,`f_pago_2`),
  KEY `idx_volante_codigo_barras` (`codigo_barras`),
  KEY `fk_volantes_empleador` (`empleador_id`),
  KEY `idx_volante_persona` (`empresa_id`,`persona_id`,`tabla_estado_registro_id`),
  CONSTRAINT `fk_volantes_empleador` FOREIGN KEY (`empleador_id`) REFERENCES `sindi__empleadores` (`empleador_id`),
  CONSTRAINT `fk_volantes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de volantes de pago de deudas sindicales';

-- sindi__volantes_canales
CREATE TABLE IF NOT EXISTS `sindi__volantes_canales` (
  `volante_canal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `volante_id` int(10) unsigned NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `medio_pago_id` int(10) unsigned NOT NULL,
  `id_deuda_externo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CPE Red Link, Identificador Interbanking, etc.',
  `estado_canal` enum('PENDIENTE','PUBLICADO','RECHAZADO','DESPUBLICADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `f_publicacion` datetime DEFAULT NULL,
  `f_baja` datetime DEFAULT NULL,
  `resultado_codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resultado_detalle` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`volante_canal_id`),
  UNIQUE KEY `uk_volante_canal` (`volante_id`,`medio_pago_id`),
  KEY `idx_vc_medio` (`medio_pago_id`),
  CONSTRAINT `fk_vc_medio` FOREIGN KEY (`medio_pago_id`) REFERENCES `sindi__medios_pago` (`medio_pago_id`),
  CONSTRAINT `fk_vc_volante` FOREIGN KEY (`volante_id`) REFERENCES `sindi__volantes` (`volante_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estado de publicación del volante en canales de cobro';

-- sindi__volantes_detalles
CREATE TABLE IF NOT EXISTS `sindi__volantes_detalles` (
  `volante_detalle_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `volante_id` int(10) unsigned NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'Comprobante de deuda (DFS, DCS, etc.)',
  `concepto_id` int(10) unsigned DEFAULT NULL COMMENT 'Concepto de origen si aplica',
  `periodo` int(11) NOT NULL COMMENT 'Período AAAAMM de la deuda',
  `f_vto_original` date NOT NULL,
  `saldo_deuda` decimal(18,2) NOT NULL DEFAULT 0.00,
  `interes_1` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal_1` decimal(18,2) NOT NULL DEFAULT 0.00,
  `interes_2` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal_2` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`volante_detalle_id`),
  UNIQUE KEY `uk_volante_comprobante` (`volante_id`,`comprobante_id`),
  KEY `idx_vol_det_comp` (`comprobante_id`),
  CONSTRAINT `fk_vol_det_volante` FOREIGN KEY (`volante_id`) REFERENCES `sindi__volantes` (`volante_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle de comprobantes de deuda incluidos en un volante';

-- sindi__volantes_detalles_intereses
CREATE TABLE IF NOT EXISTS `sindi__volantes_detalles_intereses` (
  `volante_detalle_interes_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `volante_detalle_id` int(10) unsigned NOT NULL,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `vencimiento_nro` tinyint(4) NOT NULL COMMENT '1 o 2 (primer o segundo vencimiento)',
  `tasa_interes_id` int(10) unsigned DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date NOT NULL,
  `dias` int(11) NOT NULL,
  `tasa_mensual` decimal(7,4) NOT NULL,
  `interes_calculado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`volante_detalle_interes_id`),
  KEY `idx_vdi_detalle` (`volante_detalle_id`),
  CONSTRAINT `fk_vdi_detalle` FOREIGN KEY (`volante_detalle_id`) REFERENCES `sindi__volantes_detalles` (`volante_detalle_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tramos de tasas aplicados para auditoría de intereses';

-- ---------------------------------------------------------------------
-- 5) Columnas en tablas existentes
-- ---------------------------------------------------------------------
CALL _sync_add_column('conf__empresas', 'condicion_fiscal_id', 'tinyint(4) unsigned DEFAULT NULL AFTER `localidad_id`');
CALL _sync_add_column('conf__empresas', 'iibb_regimen_id', 'tinyint(3) unsigned DEFAULT 1 AFTER `condicion_fiscal_id`');
CALL _sync_add_column('conf__empresas', 'jurisdiccion_sede_id', 'smallint(5) unsigned DEFAULT 2 AFTER `iibb_regimen_id`');
CALL _sync_add_column('conf__empresas', 'tipo_operacion', 'varchar(10) NOT NULL DEFAULT ''MIXTA'' COMMENT ''BIENES / SERVICIOS / MIXTA: define los perfiles predefinidos y las páginas que se ofrecen'' AFTER `base_conf`');
CALL _sync_add_column('conf__empresas', 'formato_fecha', 'varchar(10) NOT NULL DEFAULT ''d/m/Y'' COMMENT ''Formato de fecha en pantallas: d/m/Y, m/d/Y, Y-m-d, d-m-Y, d.m.Y'' AFTER `tipo_operacion`');
CALL _sync_add_column('conf__empresas', 'separador_miles', 'varchar(10) NOT NULL DEFAULT ''PUNTO'' COMMENT ''Separador de miles: PUNTO, COMA, ESPACIO, NINGUNO'' AFTER `formato_fecha`');
CALL _sync_add_column('conf__empresas', 'separador_decimal', 'varchar(10) NOT NULL DEFAULT ''COMA'' COMMENT ''Separador decimal: COMA, PUNTO'' AFTER `separador_miles`');
CALL _sync_add_column('conf__paginas', 'requiere_bienes', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = solo para empresas que venden bienes (pedidos, remitos, stock); se omite en perfiles de empresas SERVICIOS'' AFTER `tabla_id`');
CALL _sync_add_column('conf__perfiles', 'tipo_operacion', 'varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''TODAS'' COMMENT ''TODAS / BIENES / SERVICIOS: empresas a las que se ofrece el perfil base'' AFTER `perfil_nombre`');
CALL _sync_add_column('conf__tablas_estados_registros', 'es_final', 'tinyint(1) NOT NULL DEFAULT 0 AFTER `es_inicial`');
CALL _sync_add_column('conf__tablas_estados_registros', 'aplica_stock', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = en este estado el documento tiene aplicado su movimiento de stock (si su tipo impacta stock)'' AFTER `orden`');
CALL _sync_add_column('gestion__bocas', 'permite_stock_negativo', 'tinyint(1) NOT NULL DEFAULT 1 COMMENT ''1 = un egreso puede dejar el stock de la boca en negativo (venta de mostrador sin stock cargado)'' AFTER `permite_egresos`');
CALL _sync_add_column('gestion__comprobantes', 'numeracion_entidad_id', 'int(11) GENERATED ALWAYS AS (case when `tabla_origen_id` = 84 then `entidad_id` else 0 end) STORED COMMENT ''entidad_id si la numeracion es del tercero (facturas_proveedores, tabla_id 84), 0 si es propia'' AFTER `signo`');
CALL _sync_add_column('gestion__comprobantes', 'comprobante_nro_unico', 'int(11) GENERATED ALWAYS AS (nullif(`comprobante_nro`,0)) STORED COMMENT ''NULL en Borrador (nro 0): excluido del UNIQUE'' AFTER `numeracion_entidad_id`');
CALL _sync_add_column('gestion__comprobantes_fiscales', 'letra', 'char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `comprobante_fiscal`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'impuesto_tipo_id', 'smallint(5) unsigned DEFAULT NULL AFTER `empresa_impuesto_config_id`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'jurisdiccion_id', 'int(11) DEFAULT NULL AFTER `impuesto_tipo_id`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'situacion', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Situaci├│n del cliente al calcular (ver _config_situaciones)'' AFTER `detalle_origen`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'tipo_registro_ddjj', 'tinyint(4) DEFAULT NULL AFTER `situacion`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'padron_iibb_id', 'int(10) unsigned DEFAULT NULL AFTER `tipo_registro_ddjj`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'padron_crc', 'tinyint(3) unsigned DEFAULT NULL COMMENT ''CRC del padr├│n SIRCIP aplicado'' AFTER `padron_iibb_id`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'leyenda', 'varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `padron_crc`');
CALL _sync_add_column('gestion__comprobantes_impuestos', 'liquidacion_comprobante_id', 'int(11) DEFAULT NULL COMMENT ''I_LIQ en la que se declar├│ (NULL = pendiente)'' AFTER `leyenda`');
CALL _sync_add_column('gestion__comprobantes_tipos', 'stock_signo', 'enum(''+'',''-'',''+/-'') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Sentido en stock: + suma, - resta, +/- lo trae cada l├¡nea (ajustes, transferencias). NULL = no mueve'' AFTER `impacta_stock`');
CALL _sync_add_column('gestion__comprobantes_tipos', 'requiere_debito_cuenta', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = comprobante de compra que se cancela por débito directo en una cuenta bancaria (obligatoria)'' AFTER `impacta_ctacte`');
-- era: int(11) NOT NULL DEFAULT NULL
ALTER TABLE `gestion__cont_asientos` MODIFY COLUMN `entidad_sucursal_id` int(11) DEFAULT NULL;
CALL _sync_add_column('gestion__cont_cuentas', 'es_caja_banco', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Cuenta líquida/financiera (Caja, Bancos, Valores en cartera, Billeteras). Base dinámica del Dashboard de Tesorería'' AFTER `es_imputable`');
-- era: smallint(5) unsigned NULL DEFAULT NULL
ALTER TABLE `gestion__empresas_impuestos_config` MODIFY COLUMN `cont_cuenta_id` int(10) unsigned DEFAULT NULL;
CALL _sync_add_column('gestion__empresas_impuestos_config', 'numero_agente', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Nro de agente de recaudaci├│n en la jurisdicci├│n'' AFTER `tabla_estado_registro_id`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'criterio_territorial', 'varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''ENTREGA (sucursal de la entidad) | ESTABLECIMIENTO (sucursal de la empresa)'' AFTER `numero_agente`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'importe_minimo_percepcion', 'decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT ''Percepci├│n menor a este importe = no se percibe'' AFTER `criterio_territorial`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'arca_tributo_id', 'smallint(6) DEFAULT NULL COMMENT ''Id de FEParamGetTiposTributos para <Tributos> de WSFE'' AFTER `importe_minimo_percepcion`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'formato_padron', 'varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Parser del padr├│n: SIRCIP | NULL = sin padr├│n'' AFTER `arca_tributo_id`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'formato_ddjj', 'varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Exportador de DDJJ: SIRCAR_D1 | SIRCIP'' AFTER `formato_padron`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'codigo_jurisdiccion_cm', 'char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''C├│digo Convenio Multilateral (916 = R├¡o Negro)'' AFTER `formato_ddjj`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'codigo_regimen_ddjj', 'varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Tipo de r├®gimen de la DDJJ (SIRCAR campo 10 / SIRCIP campo 4)'' AFTER `codigo_jurisdiccion_cm`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'fuente_alicuota', 'varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''NULL = sin padrón (condición del cliente) | ARCHIVO | WS | MANUAL'' AFTER `codigo_regimen_ddjj`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'sin_dato_accion', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''CONDICION'' COMMENT ''Si la fuente no responde: CONDICION | BLOQUEAR'' AFTER `fuente_alicuota`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'integracion_empresa_id', 'int(10) unsigned DEFAULT NULL COMMENT ''WS: credenciales en gestion__integraciones_empresas'' AFTER `sin_dato_accion`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'ws_vigencia_dias', 'smallint(6) DEFAULT NULL COMMENT ''WS: vigencia del dato si el servicio no la informa'' AFTER `integracion_empresa_id`');
CALL _sync_add_column('gestion__empresas_impuestos_config', 'regimen_id', 'int(10) unsigned DEFAULT NULL COMMENT ''Retenci├│n: r├®gimen por defecto (gestion__retenciones_regimenes)'' AFTER `ws_vigencia_dias`');
CALL _sync_add_column('gestion__entidades_roles', 'cont_cuenta_id', 'int(10) unsigned DEFAULT NULL AFTER `rol_entidad_id`');
CALL _sync_add_column('gestion__entidades_sucursales', 'sucursal_numero', 'smallint(5) unsigned DEFAULT NULL COMMENT ''Número de sucursal dentro de la entidad (1 = Casa Central). Lo usa el módulo 6'' AFTER `empresa_id`');
CALL _sync_add_column('gestion__facturas_proveedores', 'comprobante_asociado_id', 'int(11) DEFAULT NULL COMMENT ''NC/ND: gestion__comprobantes del comprobante asociado'' AFTER `comprobante_id`');
CALL _sync_add_column('gestion__facturas_proveedores', 'pago_automatico_id', 'int(10) unsigned DEFAULT NULL COMMENT ''OP emitida automáticamente al confirmar (contado / débito en cuenta): se anula con el comprobante'' AFTER `comprobante_asociado_id`');
CALL _sync_add_column('gestion__facturas_proveedores', 'orden_compra_id', 'int(11) DEFAULT NULL COMMENT ''gestion__ordenes_compra importada (NULL = factura sin OC)'' AFTER `pago_automatico_id`');
CALL _sync_add_column('gestion__facturas_proveedores', 'tesoreria_cuenta_debito_id', 'int(10) unsigned DEFAULT NULL COMMENT ''Cuenta BANCO de tesorería debitada: al confirmar se emite la OP automática (NULL = pago normal)'' AFTER `condicion_pago_id`');
CALL _sync_add_column('gestion__facturas_proveedores', 'op_punto_venta_id', 'int(11) DEFAULT NULL COMMENT ''PV de la OP autom├ítica (NULL = autom├ítico)'' AFTER `tesoreria_cuenta_debito_id`');
CALL _sync_add_column('gestion__facturas_proveedores', 'CAE', 'varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `observaciones`');
CALL _sync_add_column('gestion__facturas_proveedores', 'f_vto_CAE', 'date DEFAULT NULL COMMENT ''Vencimiento del CAE del proveedor'' AFTER `CAE`');
CALL _sync_add_column('gestion__facturas_proveedores_detalle', 'ordenes_compra_detalle_id', 'int(11) DEFAULT NULL COMMENT ''Línea de OC que recibe (NULL = línea sin OC)'' AFTER `producto_id`');
CALL _sync_add_column('gestion__impuestos_tipos', 'es_liquidable', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = se liquida por DDJJ (comprobante I_LIQ) y se paga con I_PAG'' AFTER `es_percepcion`');
CALL _sync_add_column('gestion__impuestos_tipos', 'frecuencia', 'enum(''MENSUAL'',''BIMESTRAL'',''TRIMESTRAL'',''CUATRIMESTRAL'',''SEMESTRAL'',''ANUAL'',''IRREGULAR'') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Frecuencia de liquidacion (solo es_liquidable = 1)'' AFTER `es_liquidable`');
CALL _sync_add_column('gestion__integraciones_autenticaciones', 'integracion_empresa_id', 'int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''Integración (certificado) a la que pertenece el TA'' AFTER `servicio`');
-- era: enum('MANUAL','IMPORTACION') NOT NULL DEFAULT 'MANUAL'
ALTER TABLE `gestion__productos_proveedores_precios` MODIFY COLUMN `origen_carga` enum('MANUAL','IMPORTACION','ESTIMADO','COMPRA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL';
-- era: enum('MANUAL','IMPORTACION') NOT NULL DEFAULT NULL
ALTER TABLE `gestion__productos_proveedores_precios_historico` MODIFY COLUMN `origen_carga` enum('MANUAL','IMPORTACION','ESTIMADO','COMPRA') COLLATE utf8mb4_unicode_ci NOT NULL;
CALL _sync_add_column('gestion__puntos_venta', 'sistema_emision', 'varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''WSFE'' COMMENT ''Modalidad ARCA: WSFE (Web Service), RCEL (En Línea), FISCAL (Controlador Fiscal), MOVIL (Facturador Móvil), TALONARIO (Manual), NO_FISCAL (Interno)'' AFTER `codigo_fiscal`');
CALL _sync_add_column('gestion__stock_ajustes', 'comprobante_id', 'bigint(20) unsigned DEFAULT NULL COMMENT ''gestion__comprobantes (tabla_origen_id 97)'' AFTER `comprobante_tipo_id`');
CALL _sync_add_column('gestion__ventas_facturas', 'comprobante_asociado_id', 'int(11) DEFAULT NULL COMMENT ''NC/ND: gestion__comprobantes del comprobante asociado'' AFTER `comprobante_id`');
-- era: int(11) NOT NULL DEFAULT NULL
ALTER TABLE `gestion__ventas_facturas` MODIFY COLUMN `entidad_id` int(11) DEFAULT NULL;
CALL _sync_add_column('gestion__ventas_facturas', 'consumidor_final_nombre', 'varchar(150) DEFAULT NULL AFTER `entidad_sucursal_id`');
CALL _sync_add_column('gestion__ventas_facturas', 'destino_bien_uso', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = el cliente declara destino bien de uso / insumo: sin percepci├│n IIBB'' AFTER `observaciones`');
CALL _sync_add_column('gestion__ventas_facturas_detalles', 'leyenda', 'varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Texto opcional que se imprime debajo del producto'' AFTER `producto_id`');
-- era: int(11) NOT NULL DEFAULT NULL
ALTER TABLE `gestion__ventas_pedidos` MODIFY COLUMN `entidad_sucursal_id` int(11) DEFAULT NULL;
-- era: smallint(6) NOT NULL DEFAULT NULL
ALTER TABLE `gestion__ventas_pedidos` MODIFY COLUMN `condicion_pago_id` smallint(6) DEFAULT NULL;
-- era: int(11) NOT NULL DEFAULT NULL
ALTER TABLE `gestion__ventas_remitos` MODIFY COLUMN `entidad_id` int(11) DEFAULT NULL;
-- era: varchar(150) NOT NULL DEFAULT ''
ALTER TABLE `gestion__ventas_remitos` MODIFY COLUMN `consumidor_final_nombre` varchar(150) DEFAULT NULL;

-- ---------------------------------------------------------------------
-- 6) Indices en tablas existentes
-- ---------------------------------------------------------------------
CALL _sync_add_index('conf__empresas_perfiles_funciones', 'idx_perfil_asignado', 'KEY `idx_perfil_asignado` (`empresa_perfil_id`,`asignado`)');
CALL _sync_add_index('conf__empresas_perfiles_funciones', 'idx_funcion_id', 'KEY `idx_funcion_id` (`pagina_funcion_id`)');
CALL _sync_add_index('conf__paginas', 'idx_padre_id', 'KEY `idx_padre_id` (`padre_id`)');
CALL _sync_add_index('conf__paginas', 'idx_modulo_padre_estado', 'KEY `idx_modulo_padre_estado` (`modulo_id`,`padre_id`,`tabla_estado_registro_id`)');
CALL _sync_add_index('conf__paginas_funciones', 'idx_pagina_id', 'KEY `idx_pagina_id` (`pagina_id`)');
CALL _sync_add_index('conf__paginas_funciones', 'idx_pagina_origen', 'KEY `idx_pagina_origen` (`pagina_id`,`tabla_estado_registro_origen_id`)');
CALL _sync_add_index('conf__usuarios_perfiles', 'idx_usuario_empresa_perfil', 'KEY `idx_usuario_empresa_perfil` (`usuario_id`,`empresa_perfil_id`,`tabla_estado_registro_id`)');
CALL _sync_add_index('gestion__comprobantes', 'uk_comp_numeracion', 'UNIQUE KEY `uk_comp_numeracion` (`empresa_id`,`comprobante_tipo_id`,`comprobante_pv`,`comprobante_nro_unico`,`numeracion_entidad_id`)');
CALL _sync_add_index('gestion__comprobantes', 'ix_comp_emp_origen_fecha', 'KEY `ix_comp_emp_origen_fecha` (`empresa_id`,`tabla_origen_id`,`f_emision`)');
CALL _sync_add_index('gestion__comprobantes', 'ix_comp_emp_vto', 'KEY `ix_comp_emp_vto` (`empresa_id`,`f_vto`)');
CALL _sync_add_index('gestion__comprobantes_impuestos', 'idx_ci_ddjj', 'KEY `idx_ci_ddjj` (`impuesto_tipo_id`,`jurisdiccion_id`,`liquidacion_comprobante_id`)');
CALL _sync_add_index('gestion__cont_cuentas', 'ix_cont_cuentas_caja_banco', 'KEY `ix_cont_cuentas_caja_banco` (`empresa_id`,`es_caja_banco`,`tabla_estado_registro_id`)');
CALL _sync_add_index('gestion__entidades', 'idx_entidades_empresa_nombre', 'KEY `idx_entidades_empresa_nombre` (`empresa_id`,`entidad_nombre`)');
CALL _sync_add_index('gestion__entidades_roles', 'uk_entidad_rol', 'UNIQUE KEY `uk_entidad_rol` (`entidad_id`,`rol_entidad_id`)');
CALL _sync_add_index('gestion__entidades_roles', 'idx_entidad_rol', 'KEY `idx_entidad_rol` (`entidad_id`,`rol_entidad_id`,`f_baja`)');
CALL _sync_add_index('gestion__entidades_roles', 'idx_rol_entidad', 'KEY `idx_rol_entidad` (`rol_entidad_id`,`entidad_id`)');
CALL _sync_add_index('gestion__entidades_roles', 'idx_er_cuenta', 'KEY `idx_er_cuenta` (`cont_cuenta_id`)');
CALL _sync_add_index('gestion__entidades_sucursales', 'uk_ent_suc_numero', 'UNIQUE KEY `uk_ent_suc_numero` (`entidad_id`,`sucursal_numero`)');
CALL _sync_add_index('gestion__facturas_proveedores', 'idx_fp_orden_compra', 'KEY `idx_fp_orden_compra` (`orden_compra_id`)');
CALL _sync_add_index('gestion__facturas_proveedores_detalle', 'idx_fpd_oc_detalle', 'KEY `idx_fpd_oc_detalle` (`ordenes_compra_detalle_id`)');
CALL _sync_add_index('gestion__integraciones_autenticaciones', 'uk_integraciones_autenticaciones_integracion', 'UNIQUE KEY `uk_integraciones_autenticaciones_integracion` (`empresa_id`,`integracion_ambiente_id`,`servicio`,`integracion_empresa_id`)');
CALL _sync_add_index('gestion__integraciones_empresas', 'ix_integraciones_empresas_ambiente', 'KEY `ix_integraciones_empresas_ambiente` (`empresa_id`,`integracion_ambiente_id`,`servicio`)');
CALL _sync_add_index('gestion__marcas', 'idx_marcas_empresa', 'KEY `idx_marcas_empresa` (`empresa_id`)');
CALL _sync_add_index('gestion__modelos', 'idx_modelos_empresa_marca', 'KEY `idx_modelos_empresa_marca` (`empresa_id`,`marca_id`)');
CALL _sync_add_index('gestion__productos', 'uk_productos_empresa_codigo', 'UNIQUE KEY `uk_productos_empresa_codigo` (`empresa_id`,`producto_codigo`)');
CALL _sync_add_index('gestion__productos_compatibilidad', 'idx_compat_submodelo', 'KEY `idx_compat_submodelo` (`empresa_id`,`submodelo_id`,`tabla_estado_registro_id`,`producto_id`)');
CALL _sync_add_index('gestion__productos_compatibilidad', 'idx_compat_modelo', 'KEY `idx_compat_modelo` (`empresa_id`,`modelo_id`,`tabla_estado_registro_id`,`submodelo_id`,`producto_id`)');
CALL _sync_add_index('gestion__productos_compatibilidad', 'idx_compat_marca', 'KEY `idx_compat_marca` (`empresa_id`,`marca_id`,`tabla_estado_registro_id`,`producto_id`)');
CALL _sync_add_index('gestion__productos_tipos', 'uk_productos_tipos_empresa_codigo', 'UNIQUE KEY `uk_productos_tipos_empresa_codigo` (`empresa_id`,`producto_tipo_codigo`)');
CALL _sync_add_index('gestion__submodelos', 'idx_submodelos_empresa_modelo', 'KEY `idx_submodelos_empresa_modelo` (`empresa_id`,`modelo_id`)');
CALL _sync_add_index('gestion__ventas_pedidos', 'ix_vped_emp_estado_suc', 'KEY `ix_vped_emp_estado_suc` (`empresa_id`,`tabla_estado_registro_id`,`sucursal_id`)');
-- reemplazado por uk_productos_empresa_codigo (20261011_productos_codigo_por_empresa_01.sql)
CALL _sync_drop_index('gestion__productos', 'producto_codigo');
-- varios certificados por empresa + ambiente + servicio (20261018_arca_certificado_parametros_01.sql)
CALL _sync_drop_index('gestion__integraciones_empresas', 'uk_integraciones_empresas');
CALL _sync_drop_index('gestion__integraciones_autenticaciones', 'uk_integraciones_autenticaciones');

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
-- Limpieza
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
SET SESSION SQL_MODE = @OLD_SQL_MODE;
DROP PROCEDURE IF EXISTS `_sync_exec`;
DROP PROCEDURE IF EXISTS `_sync_add_column`;
DROP PROCEDURE IF EXISTS `_sync_add_index`;
DROP PROCEDURE IF EXISTS `_sync_drop_index`;
DROP PROCEDURE IF EXISTS `_sync_add_fk`;
DROP PROCEDURE IF EXISTS `_sync_recrear`;
DROP PROCEDURE IF EXISTS `_sync_renombrar`;
DROP PROCEDURE IF EXISTS `_sync_resguardar`;
