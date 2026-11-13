-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================
-- Sincronizacion ps_gestion_casalucho -> gestion_multipyme (base local)
-- Script 01: ESTRUCTURA y DATOS GENERALES
-- 2026-11-02
-- =====================================================================
-- Surge de comparar las dos bases por information_schema (columnas, indices,
-- FKs, triggers, rutinas, eventos) y los datos por clave primaria / clave
-- natural, despues de la migracion 20261031_migracion_casalucho_01..06.
-- Es el camino inverso: lleva a local lo que produccion tiene y local no.
--
-- Estructura (solo agrega, no borra nada de local):
--   1) Tablas propias de produccion, VACIAS (sin sus datos):
--      conf__usuarios_password_resets, ecom__pedidos_margenes,
--      ecom__usuarios_entidades, gestion__depositos, gestion__ventas_cobranzas
--      (esta ultima en latin1, como en produccion).
--   2) Columnas ecom__carritos.margen_porcentaje y ecom__pedidos.venta_pedido_id
--      (+ indice idx_ecom_pedidos_venta_pedido).
--   3) Indice idx_producto_empresa_principal de gestion__productos_proveedores.
--   4) FKs FK_esc_empresa / FK_esc_entidad / FK_esc_sucursal de
--      gestion__entidades_sucursales_compra (local no tiene filas huerfanas).
--   5) conf__usuarios.duracion_sid_minutos: DEFAULT 1440 (local tenia 60; solo
--      afecta a los usuarios nuevos).
--   6) Triggers de compatibilidad de productos (trg_marca / trg_modelo /
--      trg_submodelo_after_update y trg_compat_after_insert / _update /
--      _delete) y el evento diario ev_refrescar_compatibilidad_anual. Las
--      rutinas sp_* que llaman ya estan en local (sp_recalcular_compatibilidad_
--      producto difiere solo en un comentario). El evento queda creado pero no
--      corre mientras event_scheduler este en OFF (como hoy en XAMPP).
--
-- Datos generales (sin empresa):
--   7) conf__iconos: 16 iconos Font Awesome (ids 51-73) que local no tiene.
--   8) conf__paginas_funciones 449-452: "Editar" de pedidos en preparacion (9)
--      y entrega parcial (10) en las paginas 55 / 91, cargadas en produccion
--      el 2026-10-27 (la 452 es la ex 448, ver el script 02 de la migracion).
--      Sin permisos (en produccion tampoco tienen).
--   La funcion "Copiar" de pedidos (3516-3519 / 8516-8519) y sus permisos van
--   por su propio script: 20261101_ventas_pedidos_copiar_01.sql.
--
-- NO copia (datos de la empresa u operativos): usuarios y perfiles de usuario,
-- permisos por empresa, imagenes, productos, marcas / modelos / submodelos,
-- compatibilidades, costos, listas, entidades y sus condiciones (incluida la
-- carga de IIBB por localidad de 20261101_entidades_condiciones_iibb_01),
-- comprobantes, pedidos, remitos, ecom, ni las tablas *_copy / *_bak.
--
-- Reanudable: cada paso consulta information_schema antes de ejecutarse.
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 gestion_multipyme < 20261102_sync_casalucho_a_local_01.sql
-- =====================================================================

SET NAMES utf8mb4;
-- Sin USE: la base se indica en la linea de comando.
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) Tablas propias de produccion (vacias)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conf__usuarios_password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecom__pedidos_margenes` (
  `pedido_margen_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) unsigned NOT NULL,
  `empresa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `entidad_id` int(10) unsigned NOT NULL,
  `porcentaje` decimal(10,2) NOT NULL DEFAULT 0.00,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pedido_margen_id`),
  UNIQUE KEY `uk_ecom_pedidos_margenes_pedido` (`pedido_id`),
  KEY `idx_ecom_pedidos_margenes_usuario` (`usuario_id`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecom__usuarios_entidades` (
  `usuario_entidad_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `entidad_id` int(10) unsigned NOT NULL,
  `tabla_estado_registro_id` smallint(5) unsigned NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`usuario_entidad_id`),
  UNIQUE KEY `uk_ecom_usuario_empresa` (`empresa_id`,`usuario_id`),
  KEY `idx_ecom_usuarios_entidades_entidad` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__depositos` (
  `deposito_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `sucursal_id` smallint(5) unsigned NOT NULL,
  `deposito_nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad_id` int(10) unsigned DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permite_ingresos` tinyint(1) NOT NULL DEFAULT 1,
  `permite_egresos` tinyint(1) NOT NULL DEFAULT 1,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `orden` smallint(5) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(5) unsigned NOT NULL DEFAULT 1,
  `f_alta` datetime DEFAULT NULL,
  `f_modificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`deposito_id`),
  UNIQUE KEY `uk_deposito_codigo` (`empresa_id`,`sucursal_id`,`codigo`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_localidad` (`localidad_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Igual que en produccion (latin1): no la usa el codigo actual
CREATE TABLE IF NOT EXISTS `gestion__ventas_cobranzas` (
  `venta_cobranza_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `comprobante_id` int(11) NOT NULL,
  `entidad_id` int(11) NOT NULL,
  `fecha_cobranza` date NOT NULL,
  `importe_total` decimal(18,2) NOT NULL,
  `moneda_id` smallint(6) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tabla_estado_registro_id` smallint(5) unsigned NOT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`venta_cobranza_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `sucursal_id` (`sucursal_id`),
  KEY `entidad_id` (`entidad_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------
-- 2) a 5) Columnas, indices, FKs y default (con helper temporal)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_sync_local_01`;

DELIMITER $$

CREATE PROCEDURE `_sync_local_01`()
BEGIN
    -- 2) Columnas de ecom
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ecom__carritos' AND COLUMN_NAME = 'margen_porcentaje') THEN
        ALTER TABLE `ecom__carritos` ADD COLUMN `margen_porcentaje` decimal(10,2) DEFAULT NULL AFTER `entidad_id`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ecom__pedidos' AND COLUMN_NAME = 'venta_pedido_id') THEN
        ALTER TABLE `ecom__pedidos` ADD COLUMN `venta_pedido_id` int(10) unsigned NOT NULL DEFAULT 0 AFTER `ecom_pedido_id`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ecom__pedidos' AND INDEX_NAME = 'idx_ecom_pedidos_venta_pedido') THEN
        ALTER TABLE `ecom__pedidos` ADD KEY `idx_ecom_pedidos_venta_pedido` (`venta_pedido_id`);
    END IF;

    -- 3) Indice del proveedor principal
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores' AND INDEX_NAME = 'idx_producto_empresa_principal') THEN
        ALTER TABLE `gestion__productos_proveedores` ADD KEY `idx_producto_empresa_principal` (`producto_id`,`empresa_id`,`es_principal`);
    END IF;

    -- 4) FKs de las sucursales de compra (con FOREIGN_KEY_CHECKS = 1: si hubiera huerfanos, se corta)
    SET FOREIGN_KEY_CHECKS = 1;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades_sucursales_compra'
                     AND CONSTRAINT_NAME = 'FK_esc_empresa' AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
        ALTER TABLE `gestion__entidades_sucursales_compra`
            ADD CONSTRAINT `FK_esc_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades_sucursales_compra'
                     AND CONSTRAINT_NAME = 'FK_esc_entidad' AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
        ALTER TABLE `gestion__entidades_sucursales_compra`
            ADD CONSTRAINT `FK_esc_entidad` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades_sucursales_compra'
                     AND CONSTRAINT_NAME = 'FK_esc_sucursal' AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
        ALTER TABLE `gestion__entidades_sucursales_compra`
            ADD CONSTRAINT `FK_esc_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`);
    END IF;
    SET FOREIGN_KEY_CHECKS = 0;

    -- 5) Duracion de la sesion por defecto
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__usuarios' AND COLUMN_NAME = 'duracion_sid_minutos'
                 AND NOT (COLUMN_DEFAULT <=> '1440')) THEN
        ALTER TABLE `conf__usuarios` ALTER COLUMN `duracion_sid_minutos` SET DEFAULT 1440;
    END IF;
END$$

DELIMITER ;

CALL `_sync_local_01`();
DROP PROCEDURE IF EXISTS `_sync_local_01`;

-- ---------------------------------------------------------------------
-- 6) Triggers y evento de compatibilidad de productos (como en produccion)
-- ---------------------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_marca_after_update`;
DROP TRIGGER IF EXISTS `trg_modelo_after_update`;
DROP TRIGGER IF EXISTS `trg_submodelo_after_update`;
DROP TRIGGER IF EXISTS `trg_compat_after_insert`;
DROP TRIGGER IF EXISTS `trg_compat_after_update`;
DROP TRIGGER IF EXISTS `trg_compat_after_delete`;

DELIMITER $$

CREATE TRIGGER `trg_marca_after_update` AFTER UPDATE ON `gestion__marcas` FOR EACH ROW
BEGIN
    IF NOT (NEW.marca_nombre <=> OLD.marca_nombre) THEN
        CALL sp_recalcular_compatibilidad_por_marca(NEW.marca_id);
    END IF;
END$$

CREATE TRIGGER `trg_modelo_after_update` AFTER UPDATE ON `gestion__modelos` FOR EACH ROW
BEGIN
    IF NOT (NEW.modelo_nombre <=> OLD.modelo_nombre) THEN
        CALL sp_recalcular_compatibilidad_por_modelo(NEW.modelo_id);
    END IF;
END$$

CREATE TRIGGER `trg_submodelo_after_update` AFTER UPDATE ON `gestion__submodelos` FOR EACH ROW
BEGIN
    IF NOT (NEW.submodelo_nombre <=> OLD.submodelo_nombre) THEN
        CALL sp_recalcular_compatibilidad_por_submodelo(NEW.submodelo_id);
    END IF;
END$$

CREATE TRIGGER `trg_compat_after_insert` AFTER INSERT ON `gestion__productos_compatibilidad` FOR EACH ROW
BEGIN
    CALL sp_recalcular_compatibilidad_producto(NEW.producto_id);
END$$

CREATE TRIGGER `trg_compat_after_update` AFTER UPDATE ON `gestion__productos_compatibilidad` FOR EACH ROW
BEGIN
    CALL sp_recalcular_compatibilidad_producto(NEW.producto_id);
    -- Si se reasigna producto_id de una compatibilidad, se recalcula tambien el producto viejo
    IF NEW.producto_id <> OLD.producto_id THEN
        CALL sp_recalcular_compatibilidad_producto(OLD.producto_id);
    END IF;
END$$

CREATE TRIGGER `trg_compat_after_delete` AFTER DELETE ON `gestion__productos_compatibilidad` FOR EACH ROW
BEGIN
    CALL sp_recalcular_compatibilidad_producto(OLD.producto_id);
END$$

CREATE EVENT IF NOT EXISTS `ev_refrescar_compatibilidad_anual`
    ON SCHEDULE EVERY 1 DAY STARTS '2026-09-06 03:00:00'
    ON COMPLETION PRESERVE ENABLE
DO BEGIN
    CALL sp_refrescar_compatibilidad_vigentes();
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 7) Iconos que local no tiene
-- ---------------------------------------------------------------------
START TRANSACTION;

INSERT IGNORE INTO `conf__iconos` (`icono_id`, `icono_nombre`, `icono_clase`, `tabla_estado_registro_id`) VALUES
    (51, 'Home FA', 'fas fa-home', 1),
    (52, 'Dashboard FA', 'fas fa-tachometer-alt', 1),
    (53, 'Tablero FA', 'fas fa-th', 1),
    (55, 'Tablas FA', 'fas fa-table', 1),
    (56, 'Gráficos FA', 'fas fa-chart-bar', 1),
    (57, 'Usuarios FA', 'fas fa-users', 1),
    (58, 'Perfil FA', 'fas fa-user', 1),
    (59, 'Configuración FA', 'fas fa-cog', 1),
    (63, 'Archivo FA', 'fas fa-file', 1),
    (64, 'PDF FA', 'fas fa-file-pdf', 1),
    (65, 'Carpeta FA', 'fas fa-folder', 1),
    (67, 'Carrito FA', 'fas fa-shopping-cart', 1),
    (69, 'Dinero FA', 'fas fa-dollar-sign', 1),
    (71, 'Email FA', 'fas fa-envelope', 1),
    (72, 'Chat FA', 'fas fa-comment', 1),
    (73, 'Teléfono FA', 'fas fa-phone', 1);

-- ---------------------------------------------------------------------
-- 8) "Editar" de pedidos en preparacion / entrega parcial (de produccion)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`,
                                              `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `actualizado_en`, `tabla_estado_registro_id`) VALUES
    (449, 55, 40, 1, 1, 'Editar', 'editar', '', 9, 9, 22, '2026-10-27 19:19:26', 1),
    (450, 91, 40, 1, 1, 'Editar', 'editar', '', 10, 10, 22, '2026-10-27 19:19:26', 1),
    (451, 55, 40, 1, 1, 'Editar', 'editar', '', 10, 10, 22, '2026-10-27 19:19:26', 1),
    (452, 91, 40, 1, 1, 'Editar', 'editar', '', 9, 9, 22, '2026-11-01 09:15:37', 1);

COMMIT;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Control (solo lectura)
SELECT (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE 'trg\_%') AS triggers,
       (SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE()) AS eventos,
       (SELECT COUNT(*) FROM conf__iconos WHERE icono_id IN (51,52,53,55,56,57,58,59,63,64,65,67,69,71,72,73)) AS iconos,
       (SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 449 AND 452) AS funciones_editar;
