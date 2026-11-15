-- Vendedores y carteras (paso 5a): indicador, rol VEN, usuario de cada vendedor, cartera de clientes con vigencia y vendedor / origen en el pedido.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261118_vendedores_carteras_01.sql
-- Diseño en _local/docs/gestion/23_vendedores_carteras.md (decisiones aprobadas el 2026-11-15).
--
-- 1. conf__empresas.usa_vendedores (0/1, por defecto 0). Apagado, nada cambia.
-- 2. Rol de entidad VEN "Vendedor" (catálogo global gestion__roles_entidades, id 9).
-- 3. gestion__vendedores_usuarios (tabla 469): qué usuario es qué vendedor en la empresa, con vigencia.
-- 4. gestion__vendedores_carteras (tabla 470): clientes de cada vendedor, con vigencia (un vendedor por cliente a
--    una fecha; nada se borra, se cierra con f_hasta).
-- 5. gestion__ventas_pedidos.vendedor_entidad_id y .origen (NULL por defecto).
--
-- Solo agrega (columnas NULL / con valor por defecto, tablas y filas nuevas). Reanudable e idempotente
-- (MariaDB 10.4 / MySQL 8.0). Va ANTES del código. La página va en 20261118_vendedores_carteras_02_pagina.sql.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_vendedores');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_vendedores TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = vendedores, carteras de clientes y vendedor en el pedido'' ',
    'SELECT ''conf__empresas.usa_vendedores ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Rol VEN
INSERT INTO `gestion__roles_entidades` (`rol_entidad_id`, `rol_entidad_codigo`, `rol_entidad_nombre`, `rol_entidad_descripcion`)
SELECT 9, 'VEN', 'Vendedor', 'Vendedor de la empresa: tiene una cartera de clientes y toma pedidos'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `gestion__roles_entidades` WHERE `rol_entidad_codigo` = 'VEN')
  AND NOT EXISTS (SELECT 1 FROM `gestion__roles_entidades` WHERE `rol_entidad_id` = 9);

-- 3. Usuario de cada vendedor
CREATE TABLE IF NOT EXISTS `gestion__vendedores_usuarios` (
  `vendedor_usuario_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `vendedor_entidad_id` int(11) NOT NULL COMMENT 'Entidad con rol VEN',
  `usuario_id` int(11) NOT NULL COMMENT 'conf__usuarios',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`vendedor_usuario_id`),
  KEY `idx_vus_usuario` (`empresa_id`, `usuario_id`, `tabla_estado_registro_id`),
  KEY `idx_vus_vendedor` (`empresa_id`, `vendedor_entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuario que es cada vendedor (un usuario, un vendedor por empresa a una fecha)';

-- 4. Cartera
CREATE TABLE IF NOT EXISTS `gestion__vendedores_carteras` (
  `cartera_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `vendedor_entidad_id` int(11) NOT NULL COMMENT 'Entidad con rol VEN',
  `cliente_entidad_id` int(11) NOT NULL COMMENT 'Entidad con rol CLI',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL COMMENT 'NULL = vigente',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo (anulada por error)',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`cartera_id`),
  KEY `idx_vca_cliente` (`empresa_id`, `cliente_entidad_id`, `tabla_estado_registro_id`, `f_desde`),
  KEY `idx_vca_vendedor` (`empresa_id`, `vendedor_entidad_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cartera de clientes de cada vendedor, con vigencia';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, 1, 1, 1
FROM (SELECT 469 AS id, 'gestion__vendedores_usuarios' AS nombre, 'Usuario de cada vendedor' AS descr
      UNION ALL SELECT 470, 'gestion__vendedores_carteras', 'Cartera de clientes de cada vendedor') v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

-- 5. Vendedor y origen del pedido
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos' AND COLUMN_NAME = 'vendedor_entidad_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__ventas_pedidos ADD COLUMN vendedor_entidad_id INT(11) DEFAULT NULL COMMENT ''Vendedor (entidad con rol VEN); NULL = sin vendedor'', ADD KEY idx_vped_vendedor (empresa_id, vendedor_entidad_id)',
    'SELECT ''gestion__ventas_pedidos.vendedor_entidad_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos' AND COLUMN_NAME = 'origen');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__ventas_pedidos ADD COLUMN origen VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''ERP / VENDEDOR / CARRITO / APP; NULL = anterior al indicador'' ',
    'SELECT ''gestion__ventas_pedidos.origen ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
