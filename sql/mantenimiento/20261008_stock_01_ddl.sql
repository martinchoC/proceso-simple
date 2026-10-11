-- =====================================================================
-- Movimientos de stock por boca — ETAPA 1: esquema
-- Fecha: 2026-10-08
--
-- Diseño:
--   * gestion__stock_movimientos = libro INMUTABLE, una fila por producto/boca/movimiento.
--     La cabecera es gestion__comprobantes (todo movimiento tiene comprobante).
--     Nunca UPDATE/DELETE: anular o volver a borrador genera el movimiento opuesto.
--   * gestion__stock = saldo MATERIALIZADO por (empresa, boca, producto), mismo patrón que
--     gestion__tesoreria_cuentas.saldo_actual: se actualiza con la fila bloqueada FOR UPDATE
--     en la misma transacción que el movimiento. Única escritora: stock_core_model.php.
--   * QUÉ mueve stock lo dice el tipo de comprobante (impacta_stock + stock_signo);
--     CUÁNDO lo dice el estado del documento (conf__tablas_estados_registros.aplica_stock).
--     Ninguno de los dos está hardcodeado por tipo de documento.
--
-- Las tablas anteriores (gestion__stock con deposito_id, movimientos cabecera+detalle con líneas
-- duplicadas, gestion__depositos inexistente) se renombran con sufijo _bak20261008.
-- Eliminar a mano cuando se valide. El stock se reinicia con un ajuste de inventario inicial.
--
-- Reanudable: cada paso verifica en information_schema si ya se aplicó.
-- Corre en MariaDB 10.4 y MySQL 8.0.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Resguardo de las versiones anteriores (solo si todavía son la estructura vieja)
-- ---------------------------------------------------------------------
SET @viejo := (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__stock' AND COLUMN_NAME = 'deposito_id');
SET @sql := IF(@viejo > 0, 'RENAME TABLE `gestion__stock` TO `gestion__stock_bak20261008`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @viejo := (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__stock_movimientos' AND COLUMN_NAME = 'stock_movimiento_tipo_id');
SET @sql := IF(@viejo > 0, 'RENAME TABLE `gestion__stock_movimientos` TO `gestion__stock_movimientos_bak20261008`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @viejo := (SELECT COUNT(*) FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__stock_movimientos_detalles');
SET @sql := IF(@viejo > 0, 'RENAME TABLE `gestion__stock_movimientos_detalles` TO `gestion__stock_movimientos_detalles_bak20261008`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @viejo := (SELECT COUNT(*) FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__stock_movimientos_tipos');
SET @sql := IF(@viejo > 0, 'RENAME TABLE `gestion__stock_movimientos_tipos` TO `gestion__stock_movimientos_tipos_bak20261008`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2) Libro de movimientos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gestion__stock_movimientos` (
  `stock_movimiento_id`      BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `empresa_id`               INT UNSIGNED      NOT NULL,
  `sucursal_id`              SMALLINT UNSIGNED NOT NULL COMMENT 'Desnormalizado de la boca, para AccesosSucursalesHelper::filtroSqlSucursal',
  `boca_id`                  INT UNSIGNED      NOT NULL COMMENT 'Por fila y no por comprobante: una transferencia mueve dos bocas',
  `producto_id`              INT UNSIGNED      NOT NULL,
  `tipo_movimiento`          ENUM('INGRESO','EGRESO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad`                 DECIMAL(18,4)     NOT NULL COMMENT 'Siempre > 0; la dirección la da tipo_movimiento',
  `costo_unitario`           DECIMAL(18,6)     NULL DEFAULT NULL,
  `comprobante_id`           BIGINT UNSIGNED   NOT NULL COMMENT 'gestion__comprobantes',
  `comprobante_tipo_id`      INT UNSIGNED      NOT NULL,
  `tabla_origen_id`          INT               NOT NULL COMMENT 'conf__tablas del documento (81 ventas_remitos, 80 ventas_facturas, 84 facturas_proveedores, 97 stock_ajustes...)',
  `registro_origen_id`       INT               NOT NULL,
  `registro_detalle_id`      INT               NOT NULL DEFAULT 0 COMMENT 'Id de la línea en la tabla _detalles del documento de origen',
  `f_movimiento`             DATETIME          NOT NULL,
  `observaciones`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `usuario_id`               INT UNSIGNED      NULL DEFAULT NULL,
  `f_alta`                   DATETIME          NOT NULL,
  PRIMARY KEY (`stock_movimiento_id`),
  KEY `ix_smov_boca_producto` (`empresa_id`, `boca_id`, `producto_id`, `f_movimiento`),
  KEY `ix_smov_origen` (`tabla_origen_id`, `registro_origen_id`),
  KEY `ix_smov_origen_linea` (`tabla_origen_id`, `registro_detalle_id`, `producto_id`),
  KEY `ix_smov_comprobante` (`comprobante_id`),
  KEY `ix_smov_producto` (`producto_id`),
  KEY `ix_smov_sucursal` (`sucursal_id`),
  CONSTRAINT `chk_smov_cantidad`   CHECK (`cantidad` > 0),
  CONSTRAINT `fk_smov_boca`        FOREIGN KEY (`boca_id`)        REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_smov_producto`    FOREIGN KEY (`producto_id`)    REFERENCES `gestion__productos` (`producto_id`),
  CONSTRAINT `fk_smov_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `gestion__comprobantes` (`comprobante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Libro inmutable de movimientos de stock. Lo escribe solo stock_core_model.php';

-- ---------------------------------------------------------------------
-- 3) Saldo materializado por boca
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gestion__stock` (
  `empresa_id`          INT UNSIGNED      NOT NULL,
  `boca_id`             INT UNSIGNED      NOT NULL,
  `producto_id`         INT UNSIGNED      NOT NULL,
  `sucursal_id`         SMALLINT UNSIGNED NOT NULL,
  `cantidad`            DECIMAL(18,4)     NOT NULL DEFAULT 0 COMMENT 'MATERIALIZADO: SUM(INGRESO) - SUM(EGRESO) de gestion__stock_movimientos',
  `costo_promedio`      DECIMAL(18,6)     NOT NULL DEFAULT 0 COMMENT 'Promedio ponderado de la boca, recalculado en cada INGRESO con costo',
  `costo_ultima_compra` DECIMAL(18,6)     NOT NULL DEFAULT 0,
  `f_ultimo_movimiento` DATETIME          NULL DEFAULT NULL,
  PRIMARY KEY (`empresa_id`, `boca_id`, `producto_id`),
  KEY `ix_stock_producto` (`empresa_id`, `producto_id`),
  KEY `ix_stock_sucursal` (`sucursal_id`),
  CONSTRAINT `fk_stock_boca`     FOREIGN KEY (`boca_id`)     REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_stock_producto` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stock actual por boca (materializado). Se reconstruye con stockRecalcular()';

-- ---------------------------------------------------------------------
-- 4) Tipo de comprobante: sentido en stock
--    impacta_stock sigue siendo el interruptor; stock_signo dice el sentido.
--    No se usa `signo`: es el de cta. cte./importe (Factura de venta '+' resta stock).
-- ---------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_tipos' AND COLUMN_NAME = 'stock_signo');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `gestion__comprobantes_tipos` ADD COLUMN `stock_signo` ENUM(''+'',''-'',''+/-'') COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Sentido en stock: + suma, - resta, +/- lo trae cada línea (ajustes, transferencias). NULL = no mueve'' AFTER `impacta_stock`',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 5) Catálogo de estados por tabla: en qué estados el documento tiene el stock aplicado.
--    Por tabla y no por tipo porque cada tabla tiene su circuito (ventas_remitos no pasa
--    por 5: Borrador -> 13 Pend. Facturación -> 15/12).
-- ---------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__tablas_estados_registros' AND COLUMN_NAME = 'aplica_stock');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `conf__tablas_estados_registros` ADD COLUMN `aplica_stock` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = en este estado el documento tiene aplicado su movimiento de stock (si su tipo impacta stock)''',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 6) Boca: admite stock negativo (equivalente a permite_saldo_negativo de tesorería)
-- ---------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__bocas' AND COLUMN_NAME = 'permite_stock_negativo');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `gestion__bocas` ADD COLUMN `permite_stock_negativo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = un egreso puede dejar el stock de la boca en negativo (venta de mostrador sin stock cargado)'' AFTER `permite_egresos`',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 7) Ajustes de stock: comprobante universal (los movimientos lo exigen)
-- ---------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__stock_ajustes' AND COLUMN_NAME = 'comprobante_id');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `gestion__stock_ajustes` ADD COLUMN `comprobante_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT ''gestion__comprobantes (tabla_origen_id 97)'' AFTER `comprobante_tipo_id`',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- deposito_id en gestion__facturas_proveedores y gestion__stock_ajustes(_detalles) pasa a guardar
-- gestion__bocas.boca_id (gestion__depositos nunca existió en la base). Se conserva el nombre de
-- columna para no tocar las pantallas; los combos ya leen gestion__bocas.
