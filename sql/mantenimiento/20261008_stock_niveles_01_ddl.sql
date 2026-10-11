-- =====================================================================
-- Stock mínimo / máximo por boca + alertas de reposición - script 01: DDL
-- 2026-10-08
--
-- Dos tablas:
--   gestion__stock_niveles  (conf__tablas 429, Diccionario): mínimo y máximo
--       por producto x boca, cargados a mano. Un registro por par.
--   gestion__stock_alertas  (conf__tablas 430, Proceso Corto): alerta de
--       reposición MATERIALIZADA. La abre/actualiza/cierra
--       stockEvaluarNivel() (modules/gestion/stock_niveles_core_model.php), que
--       llama el servicio central de stock (stock_core_model.php) en la misma
--       transacción que mueve gestion__stock; nunca se calcula al vuelo.
--
-- Reglas (ver stock_niveles_core_model.php):
--   - Se abre cuando el stock CRUZA hacia abajo el mínimo (stock < mínimo,
--     estricto) o cuando se carga/sube un mínimo por encima del stock actual.
--   - Se cierra sola (21 Cumplido, motivo REPOSICION) cuando stock >= mínimo.
--   - Una sola abierta por empresa+boca+producto: UNIQUE sobre `abierta`
--     (1 = abierta, NULL = cerrada; los NULL no chocan en el UNIQUE).
--     Funciona igual en MariaDB 10.4 y MySQL 8 sin índices parciales.
--   - El máximo no genera alerta: solo calcula cantidad_sugerida.
--   - Sin sucursal_id: sale de gestion__bocas (filtro de accesos por
--     b.sucursal_id), así no hay copia que se desincronice.
--
-- Estados (ids GENÉRICOS de conf__estados_registros):
--   niveles: 1 Activo / 2 Inactivo (= no controlar)
--   alertas: 17 Pendiente (inicial) / 18 En curso / 21 Cumplido / 26 Cancelado
--
-- Reanudable (CREATE TABLE IF NOT EXISTS). Ambos motores.
-- Correr DESPUÉS de 20261008_stock_01_ddl.sql (gestion__stock por boca_id) y
-- ANTES de 20261008_stock_niveles_02_configuracion.sql.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `gestion__stock_niveles` (
  `stock_nivel_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `boca_id` INT UNSIGNED NOT NULL COMMENT 'Boca con es_deposito = 1',
  `producto_id` INT UNSIGNED NOT NULL COMMENT 'Producto con controla_stock = 1',
  `stock_minimo` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `stock_maximo` DECIMAL(20,6) DEFAULT NULL COMMENT 'NULL = sin tope. No genera alerta: solo calcula la cantidad sugerida',
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo (= no controlar)',
  `usuario_id` INT UNSIGNED DEFAULT NULL COMMENT 'Último usuario que lo modificó',
  `f_alta` DATETIME DEFAULT NULL,
  `f_modificacion` DATETIME DEFAULT NULL,
  PRIMARY KEY (`stock_nivel_id`),
  UNIQUE KEY `uk_snv_boca_producto` (`empresa_id`,`boca_id`,`producto_id`),
  KEY `ix_snv_boca` (`boca_id`),
  KEY `ix_snv_producto` (`producto_id`),
  CONSTRAINT `fk_snv_boca_id` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_snv_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stock minimo y maximo por producto y boca (carga manual)';

CREATE TABLE IF NOT EXISTS `gestion__stock_alertas` (
  `stock_alerta_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT NOT NULL,
  `boca_id` INT UNSIGNED NOT NULL,
  `producto_id` INT UNSIGNED NOT NULL,
  `stock_nivel_id` INT UNSIGNED NOT NULL,
  `abierta` TINYINT(1) DEFAULT 1 COMMENT '1 = abierta, NULL = cerrada. Habilita el UNIQUE de una sola abierta por boca+producto',
  `f_disparo` DATETIME NOT NULL,
  `stock_al_disparo` DECIMAL(20,6) NOT NULL COMMENT 'Foto: stock en el momento en que se abrió',
  `stock_minimo` DECIMAL(20,6) NOT NULL COMMENT 'Copia del mínimo vigente mientras está abierta',
  `stock_maximo` DECIMAL(20,6) DEFAULT NULL COMMENT 'Copia del máximo vigente mientras está abierta',
  `stock_actual` DECIMAL(20,6) NOT NULL COMMENT 'MATERIALIZADO: último saldo visto mientras está abierta',
  `cantidad_sugerida` DECIMAL(20,6) NOT NULL COMMENT 'MATERIALIZADO: GREATEST(COALESCE(maximo, minimo) - stock_actual, 0)',
  `stock_movimiento_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Movimiento que la disparó; NULL = la disparó la carga/edición del nivel',
  `usuario_toma_id` INT UNSIGNED DEFAULT NULL COMMENT 'Quién la pasó a En curso',
  `f_toma` DATETIME DEFAULT NULL,
  `f_cierre` DATETIME DEFAULT NULL,
  `motivo_cierre` ENUM('REPOSICION','DESCARTADA','NIVEL_MODIFICADO','NIVEL_INACTIVO') DEFAULT NULL,
  `usuario_cierre_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = cierre automático',
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 17 COMMENT '17 Pendiente / 18 En curso / 21 Cumplido / 26 Cancelado',
  PRIMARY KEY (`stock_alerta_id`),
  UNIQUE KEY `uk_sal_abierta` (`empresa_id`,`boca_id`,`producto_id`,`abierta`),
  KEY `ix_sal_empresa_estado` (`empresa_id`,`tabla_estado_registro_id`),
  KEY `ix_sal_boca` (`boca_id`),
  KEY `ix_sal_producto` (`producto_id`),
  KEY `ix_sal_stock_nivel` (`stock_nivel_id`),
  CONSTRAINT `fk_sal_boca_id` FOREIGN KEY (`boca_id`) REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_sal_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `gestion__productos` (`producto_id`),
  CONSTRAINT `fk_sal_stock_nivel_id` FOREIGN KEY (`stock_nivel_id`) REFERENCES `gestion__stock_niveles` (`stock_nivel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Alertas de reposicion por stock bajo el minimo (materializadas)';

-- ---------------------------------------------------------------------
-- Conciliación (solo lectura, debe devolver 0 filas): pares con nivel
-- activo donde "stock < mínimo" no coincide con "hay alerta abierta".
-- Ojo: una alerta DESCARTADA con el stock todavía bajo el mínimo aparece acá
-- como diferencia esperable (no se reabre hasta que reponen y vuelve a caer).
-- Parámetro: empresa_id.
-- ---------------------------------------------------------------------
-- SELECT n.stock_nivel_id, n.boca_id, n.producto_id, n.stock_minimo,
--        COALESCE(s.cantidad, 0) AS stock, a.stock_alerta_id
-- FROM gestion__stock_niveles n
-- LEFT JOIN gestion__stock s ON s.empresa_id = n.empresa_id AND s.boca_id = n.boca_id
--                           AND s.producto_id = n.producto_id
-- LEFT JOIN gestion__stock_alertas a ON a.empresa_id = n.empresa_id AND a.boca_id = n.boca_id
--                                   AND a.producto_id = n.producto_id AND a.abierta = 1
-- WHERE n.empresa_id = ? AND n.tabla_estado_registro_id = 1
--   AND (COALESCE(s.cantidad, 0) < n.stock_minimo) <> (a.stock_alerta_id IS NOT NULL);
