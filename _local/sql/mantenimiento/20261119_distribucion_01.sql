-- Distribución (paso 5b): indicador, zonas, zonas de clientes, camiones, hojas de ruta, depósito de despacho del pedido y estado 42 "Para distribución".
-- -----------------------------------------------------------------------------------------------------------------
-- 20261119_distribucion_01.sql
-- Diseño en _local/docs/gestion/24_distribucion.md (decisiones aprobadas el 2026-11-15).
--
-- 1. conf__empresas.usa_distribucion (0/1, por defecto 0). Apagado, nada cambia.
-- 2. gestion__distribucion_zonas (tabla 471): zonas de reparto con sus días.
-- 3. gestion__distribucion_zonas_clientes (tabla 472): zona de cada cliente (o de una sucursal de entrega), con
--    vigencia.
-- 4. gestion__distribucion_camiones (tabla 473): camiones con capacidad en kg y m³.
-- 5. gestion__distribucion_hojas (tabla 474) y gestion__distribucion_hojas_pedidos (tabla 475): hojas de ruta, el
--    orden de visita, la foto de peso / volumen y la rendición de cada pedido.
-- 6. gestion__ventas_pedidos.boca_despacho_id (NULL): depósito desde el que sale el pedido.
-- 7. Estado genérico 42 "Para distribución" (PARA_DISTRIBUCION) y su fila en el catálogo de la tabla 82
--    (gestion__ventas_pedidos), id 272: "Confirmado para distribución", entre 5 y 9.
--
-- Solo agrega (columnas NULL / con valor por defecto, tablas y filas nuevas). Reanudable e idempotente
-- (MariaDB 10.4 / MySQL 8.0). Va ANTES del código. Funciones y páginas: 20261119_distribucion_02_paginas.sql.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Indicador por empresa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_distribucion');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN usa_distribucion TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = zonas, camiones, armado de reparto y hojas de ruta'' ',
    'SELECT ''conf__empresas.usa_distribucion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Zonas
CREATE TABLE IF NOT EXISTS `gestion__distribucion_zonas` (
  `zona_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_reparto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Días de la semana con reparto, ISO 1 = lunes ... 7 = domingo, separados por coma (p. ej. 1,3,5)',
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Inactiva',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`zona_id`),
  KEY `idx_dzo_empresa` (`empresa_id`, `tabla_estado_registro_id`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zonas de reparto';

-- 3. Zona de cada cliente
CREATE TABLE IF NOT EXISTS `gestion__distribucion_zonas_clientes` (
  `zona_cliente_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `zona_id` int(10) unsigned NOT NULL,
  `cliente_entidad_id` int(11) NOT NULL,
  `entidad_sucursal_id` int(11) DEFAULT NULL COMMENT 'Sucursal de entrega del cliente; NULL = todas las del cliente',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL COMMENT 'NULL = vigente',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo (anulada)',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`zona_cliente_id`),
  KEY `idx_dzc_cliente` (`empresa_id`, `cliente_entidad_id`, `tabla_estado_registro_id`, `f_desde`),
  KEY `idx_dzc_zona` (`empresa_id`, `zona_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zona de reparto de cada cliente o sucursal de entrega, con vigencia';

-- 4. Camiones
CREATE TABLE IF NOT EXISTS `gestion__distribucion_camiones` (
  `camion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patente` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacidad_kg` decimal(10,2) DEFAULT NULL COMMENT 'NULL = sin control de peso',
  `capacidad_m3` decimal(8,3) DEFAULT NULL COMMENT 'NULL = sin control de volumen',
  `boca_id` int(11) DEFAULT NULL COMMENT 'Depósito habitual (gestion__bocas)',
  `chofer` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chofer habitual',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`camion_id`),
  KEY `idx_dca_empresa` (`empresa_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Camiones de reparto';

-- 5. Hojas de ruta
CREATE TABLE IF NOT EXISTS `gestion__distribucion_hojas` (
  `hoja_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `numero` int(10) unsigned NOT NULL COMMENT 'Correlativo por empresa',
  `fecha_reparto` date NOT NULL,
  `camion_id` int(10) unsigned DEFAULT NULL,
  `chofer` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boca_id` int(11) DEFAULT NULL COMMENT 'Depósito de salida',
  `peso_kg` decimal(12,3) DEFAULT NULL COMMENT 'Foto al confirmar',
  `volumen_m3` decimal(12,4) DEFAULT NULL COMMENT 'Foto al confirmar',
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 3 COMMENT '3 Borrador / 5 Confirmada / 18 En curso / 21 Rendida / 38 Anulada',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmado_por` int(10) unsigned DEFAULT NULL,
  `confirmado_en` datetime DEFAULT NULL,
  `rendido_por` int(10) unsigned DEFAULT NULL,
  `rendido_en` datetime DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`hoja_id`),
  UNIQUE KEY `uk_dho_numero` (`empresa_id`, `numero`),
  KEY `idx_dho_fecha` (`empresa_id`, `fecha_reparto`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Hojas de ruta de reparto';

CREATE TABLE IF NOT EXISTS `gestion__distribucion_hojas_pedidos` (
  `hoja_pedido_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hoja_id` int(10) unsigned NOT NULL,
  `venta_pedido_id` int(11) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Orden de visita (manual)',
  `peso_kg` decimal(12,3) DEFAULT NULL COMMENT 'Foto al confirmar la hoja',
  `volumen_m3` decimal(12,4) DEFAULT NULL,
  `sin_datos` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = alguna línea sin peso o volumen',
  `resultado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ENTREGADO / PARCIAL / RECHAZADO / NO_VISITADO; NULL = sin rendir',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cobrado_importe` decimal(15,2) DEFAULT NULL,
  `cobrado_medio` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 En la hoja / 2 Sacado de la hoja',
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`hoja_pedido_id`),
  KEY `idx_dhp_hoja` (`hoja_id`, `tabla_estado_registro_id`, `orden`),
  KEY `idx_dhp_pedido` (`venta_pedido_id`, `tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pedidos de cada hoja de ruta, en orden de visita, con su rendición';

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, v.tipo, 1, 1
FROM (SELECT 471 AS id, 'gestion__distribucion_zonas' AS nombre, 'Zonas de reparto' AS descr, 1 AS tipo
      UNION ALL SELECT 472, 'gestion__distribucion_zonas_clientes', 'Zona de reparto de cada cliente', 1
      UNION ALL SELECT 473, 'gestion__distribucion_camiones', 'Camiones de reparto', 1
      UNION ALL SELECT 474, 'gestion__distribucion_hojas', 'Hojas de ruta de reparto', 2
      UNION ALL SELECT 475, 'gestion__distribucion_hojas_pedidos', 'Pedidos de cada hoja de ruta', 2) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

-- 6. Depósito de despacho del pedido
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos' AND COLUMN_NAME = 'boca_despacho_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__ventas_pedidos ADD COLUMN boca_despacho_id INT(11) DEFAULT NULL COMMENT ''Depósito de despacho (gestion__bocas); NULL = sin definir'' ',
    'SELECT ''gestion__ventas_pedidos.boca_despacho_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 7. Estado 42 y su fila en el catálogo de pedidos
INSERT INTO `conf__estados_registros` (`estado_registro_id`, `estado_registro`, `codigo_estandar`, `valor_estandar`, `color_id`, `orden_estandar`)
SELECT 42, 'Para distribución', 'PARA_DISTRIBUCION', 33, 1, 33 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `conf__estados_registros` WHERE `estado_registro_id` = 42);

INSERT INTO `conf__tablas_estados_registros` (`tabla_estado_registro_id`, `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`, `aplica_stock`)
SELECT 272, 82, 42, 'Confirmado para distribución', 1, 0, 0, 7, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas_estados_registros` WHERE `tabla_estado_registro_id` = 272)
  AND NOT EXISTS (SELECT 1 FROM `conf__tablas_estados_registros` WHERE `tabla_id` = 82 AND `estado_registro_id` = 42);

-- Control (solo lectura)
SELECT ter.tabla_estado_registro_id, ter.estado_registro_id, ter.tabla_estado_registro, ter.orden
FROM conf__tablas_estados_registros ter WHERE ter.tabla_id = 82 ORDER BY ter.orden;
