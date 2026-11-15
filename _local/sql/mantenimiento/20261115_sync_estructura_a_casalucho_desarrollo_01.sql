-- @migracion: particular
-- Sincronización estructural: gestion_multipyme -> ps_gestion_casalucho_desarrollo
-- Generado el 2026-11-15. Solo estructura (sin tocar datos operativos, sin DROP).
-- Reanudable e idempotente (compatible con MariaDB 10.4 y MySQL 8.0).
-- ---------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- =================================================================================
-- 1. TABLAS NUEVAS (24 tablas)
-- =================================================================================

-- Tabla: conf__integraciones
CREATE TABLE IF NOT EXISTS `conf__integraciones` (
  `integracion_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL COMMENT 'Identificador técnico estable (ARCA, MERCADOPAGO, ...)',
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`integracion_id`),
  UNIQUE KEY `uk_integraciones_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla: conf__integraciones_ambientes
CREATE TABLE IF NOT EXISTS `conf__integraciones_ambientes` (
  `integracion_ambiente_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `integracion_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(30) NOT NULL COMMENT 'homologacion / produccion / sandbox',
  `nombre` varchar(100) NOT NULL,
  `url_wsaa` varchar(255) DEFAULT NULL COMMENT 'URL de autenticación, si aplica',
  `url_servicio` varchar(255) DEFAULT NULL COMMENT 'URL del servicio, si aplica',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`integracion_ambiente_id`),
  UNIQUE KEY `uk_integraciones_ambientes` (`integracion_id`,`codigo`),
  CONSTRAINT `fk_integraciones_ambientes_integracion` FOREIGN KEY (`integracion_id`) REFERENCES `conf__integraciones` (`integracion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla: conf__integraciones_operaciones
CREATE TABLE IF NOT EXISTS `conf__integraciones_operaciones` (
  `integracion_operacion_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `integracion_id` smallint(5) unsigned NOT NULL,
  `codigo` varchar(50) NOT NULL COMMENT 'Identificador técnico de la operación',
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`integracion_operacion_id`),
  UNIQUE KEY `uk_integraciones_operaciones` (`integracion_id`,`codigo`),
  CONSTRAINT `fk_integraciones_operaciones_integracion` FOREIGN KEY (`integracion_id`) REFERENCES `conf__integraciones` (`integracion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla: gestion__integraciones_autenticaciones
CREATE TABLE IF NOT EXISTS `gestion__integraciones_autenticaciones` (
  `integracion_autenticacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `integracion_ambiente_id` smallint(5) unsigned NOT NULL,
  `servicio` varchar(30) NOT NULL,
  `integracion_empresa_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Integración (certificado) a la que pertenece el TA',
  `tipo_autenticacion` varchar(30) NOT NULL DEFAULT 'token_sign',
  `token` text NOT NULL,
  `sign` text NOT NULL,
  `fecha_hora_generacion` datetime NOT NULL,
  `fecha_hora_vencimiento` datetime NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_hora_actualizacion` datetime NOT NULL,
  PRIMARY KEY (`integracion_autenticacion_id`),
  UNIQUE KEY `uk_integraciones_autenticaciones_integracion` (`empresa_id`,`integracion_ambiente_id`,`servicio`,`integracion_empresa_id`),
  KEY `fk_integraciones_autenticaciones_ambiente` (`integracion_ambiente_id`),
  CONSTRAINT `fk_integraciones_autenticaciones_ambiente` FOREIGN KEY (`integracion_ambiente_id`) REFERENCES `conf__integraciones_ambientes` (`integracion_ambiente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla: gestion__integraciones_empresas
CREATE TABLE IF NOT EXISTS `gestion__integraciones_empresas` (
  `integracion_empresa_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `integracion_ambiente_id` smallint(5) unsigned NOT NULL,
  `servicio` varchar(30) NOT NULL DEFAULT 'wsfe',
  `cuit` char(11) NOT NULL,
  `certificado_ruta` varchar(255) NOT NULL COMMENT 'Relativa a la raíz del proyecto, dentro de arca/',
  `clave_privada_ruta` varchar(255) NOT NULL COMMENT 'Relativa a la raíz del proyecto, dentro de arca/',
  `observaciones` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `usuario_creacion_id` bigint(20) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`integracion_empresa_id`),
  KEY `fk_integraciones_empresas_ambiente` (`integracion_ambiente_id`),
  KEY `ix_integraciones_empresas_ambiente` (`empresa_id`,`integracion_ambiente_id`,`servicio`),
  CONSTRAINT `fk_integraciones_empresas_ambiente` FOREIGN KEY (`integracion_ambiente_id`) REFERENCES `conf__integraciones_ambientes` (`integracion_ambiente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla: gestion__integraciones_resultados
CREATE TABLE IF NOT EXISTS `gestion__integraciones_resultados` (
  `integracion_resultado_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL,
  `integracion_id` smallint(5) unsigned NOT NULL,
  `integracion_ambiente_id` smallint(5) unsigned DEFAULT NULL,
  `integracion_operacion_id` smallint(5) unsigned DEFAULT NULL,
  `resultado_integracion_id` int(10) unsigned DEFAULT NULL COMMENT 'Resultado/categoría si corresponde',
  `codigo` varchar(100) DEFAULT NULL COMMENT 'Código técnico (error ARCA, Resultado A/R, ...)',
  `resultado` varchar(20) NOT NULL COMMENT 'OK / ERROR / RECHAZO / TIMEOUT',
  `descripcion` varchar(500) DEFAULT NULL,
  `referencia_tipo` varchar(60) DEFAULT NULL,
  `referencia_id` bigint(20) unsigned DEFAULT NULL,
  `intento` smallint(5) unsigned NOT NULL DEFAULT 1,
  `fecha_hora_solicitud` datetime NOT NULL,
  `fecha_hora_respuesta` datetime DEFAULT NULL,
  `solicitud` mediumtext DEFAULT NULL,
  `respuesta` mediumtext DEFAULT NULL,
  PRIMARY KEY (`integracion_resultado_id`),
  KEY `ix_integraciones_resultados_empresa_fecha` (`empresa_id`,`fecha_hora_solicitud`),
  KEY `ix_integraciones_resultados_referencia` (`referencia_tipo`,`referencia_id`),
  KEY `fk_integraciones_resultados_integracion` (`integracion_id`),
  CONSTRAINT `fk_integraciones_resultados_integracion` FOREIGN KEY (`integracion_id`) REFERENCES `conf__integraciones` (`integracion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla: gestion__canales_venta
CREATE TABLE IF NOT EXISTS `gestion__canales_venta` (
  `canal_venta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`canal_venta_id`),
  UNIQUE KEY `uk_canales_venta_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Canales de venta de la empresa (minorista, mayorista); el canal del cliente sale de su lista';

-- Tabla: gestion__distribucion_camiones
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
  KEY `idx_dca_empresa` (`empresa_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Camiones de reparto';

-- Tabla: gestion__distribucion_hojas
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
  UNIQUE KEY `uk_dho_numero` (`empresa_id`,`numero`),
  KEY `idx_dho_fecha` (`empresa_id`,`fecha_reparto`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hojas de ruta de reparto';

-- Tabla: gestion__distribucion_hojas_pedidos
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
  KEY `idx_dhp_hoja` (`hoja_id`,`tabla_estado_registro_id`,`orden`),
  KEY `idx_dhp_pedido` (`venta_pedido_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pedidos de cada hoja de ruta, en orden de visita, con su rendición';

-- Tabla: gestion__distribucion_zonas
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
  KEY `idx_dzo_empresa` (`empresa_id`,`tabla_estado_registro_id`,`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zonas de reparto';

-- Tabla: gestion__distribucion_zonas_clientes
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
  KEY `idx_dzc_cliente` (`empresa_id`,`cliente_entidad_id`,`tabla_estado_registro_id`,`f_desde`),
  KEY `idx_dzc_zona` (`empresa_id`,`zona_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zona de reparto de cada cliente o sucursal de entrega, con vigencia';

-- Tabla: gestion__empresas_iibb_actividades
CREATE TABLE IF NOT EXISTS `gestion__empresas_iibb_actividades` (
  `actividad_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `inscripcion_id` int(10) unsigned NOT NULL COMMENT 'gestion__empresas_iibb_inscripciones',
  `codigo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código de actividad (NAES)',
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alicuota` decimal(7,4) NOT NULL DEFAULT 0.0000 COMMENT 'Porcentaje (3.5 = 3,5 %)',
  `minimo_mensual` decimal(18,2) DEFAULT NULL COMMENT 'Impuesto mínimo mensual de la actividad (NULL = sin mínimo)',
  `es_principal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Recibe la base calculada al proponer la liquidación',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo',
  PRIMARY KEY (`actividad_id`),
  KEY `ix_eia_inscripcion` (`empresa_id`,`inscripcion_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actividades IIBB de cada inscripción de la empresa (alícuota y mínimo)';

-- Tabla: gestion__iibb_posiciones
CREATE TABLE IF NOT EXISTS `gestion__iibb_posiciones` (
  `iibb_posicion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `periodo` char(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AAAA-MM',
  `jurisdiccion_id` int(11) NOT NULL,
  `inscripcion_id` int(10) unsigned DEFAULT NULL,
  `iibb_regimen_codigo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'LOCAL / CONVENIO_MULTILATERAL',
  `coeficiente_cm` decimal(9,6) DEFAULT NULL,
  `base_total` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Ventas fiscales del mes sin IVA (todas las jurisdicciones)',
  `base_calculada` decimal(18,2) DEFAULT NULL COMMENT 'Base propuesta: total (local) o total × coeficiente (CM)',
  `base_imponible` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Base declarada: suma de las bases por actividad',
  `impuesto_determinado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `retenciones` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Retenciones IIBB sufridas de la jurisdicción (período de cómputo)',
  `percepciones` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Percepciones IIBB sufridas de la jurisdicción',
  `saldo_favor_anterior` decimal(18,2) NOT NULL DEFAULT 0.00,
  `saldo_a_pagar` decimal(18,2) NOT NULL DEFAULT 0.00,
  `saldo_a_favor` decimal(18,2) NOT NULL DEFAULT 0.00,
  `comprobante_id` int(11) DEFAULT NULL COMMENT 'I_LIQ generada cuando da a pagar',
  `cont_asiento_id` int(10) unsigned DEFAULT NULL,
  `nro_ddjj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 5 COMMENT '5 Liquidada / 38 Anulada',
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`iibb_posicion_id`),
  KEY `ix_iibb_pos_empresa` (`empresa_id`,`jurisdiccion_id`,`periodo`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liquidación mensual de Ingresos Brutos por jurisdicción';

-- Tabla: gestion__iibb_posiciones_actividades
CREATE TABLE IF NOT EXISTS `gestion__iibb_posiciones_actividades` (
  `posicion_actividad_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `iibb_posicion_id` int(10) unsigned NOT NULL,
  `actividad_id` int(10) unsigned DEFAULT NULL,
  `codigo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_imponible` decimal(18,2) NOT NULL DEFAULT 0.00,
  `alicuota` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `impuesto_calculado` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'base × alícuota',
  `minimo_mensual` decimal(18,2) DEFAULT NULL,
  `impuesto_determinado` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Mayor entre el calculado y el mínimo',
  PRIMARY KEY (`posicion_actividad_id`),
  KEY `ix_iibb_posact_posicion` (`iibb_posicion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actividades declaradas en cada liquidación de IIBB';

-- Tabla: gestion__listas_precios_presentaciones
CREATE TABLE IF NOT EXISTS `gestion__listas_precios_presentaciones` (
  `lista_precio_presentacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `lista_precio_id` int(11) NOT NULL,
  `presentacion_id` int(10) unsigned NOT NULL COMMENT 'gestion__productos_presentaciones',
  `producto_id` int(10) unsigned NOT NULL COMMENT 'Producto de la presentación (para filtrar sin JOIN)',
  `precio_final` decimal(18,4) NOT NULL COMMENT 'Precio de la presentación entera (la caja, no el unitario)',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Vigente / 2 Reemplazada (historial)',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`lista_precio_presentacion_id`),
  KEY `idx_lpre_lista` (`empresa_id`,`lista_precio_id`,`presentacion_id`,`tabla_estado_registro_id`),
  KEY `idx_lpre_producto` (`empresa_id`,`producto_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Precio de cada presentación (caja, pack) por lista de precios';

-- Tabla: gestion__precios_reglas_venta
CREATE TABLE IF NOT EXISTS `gestion__precios_reglas_venta` (
  `regla_venta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `tipo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ESCALA (por cantidad, con tramos) / PROMOCION (descuento_pct)',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridad` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Desempate entre reglas del mismo tipo con el mismo %: mayor gana',
  `producto_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = todos los productos (si tampoco hay categoría)',
  `presentacion_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = cualquier presentación (y la unidad)',
  `producto_categoria_id` int(10) unsigned DEFAULT NULL,
  `marca_comercial_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = todas las marcas',
  `lista_precio_id` int(10) unsigned DEFAULT NULL,
  `canal_venta_id` int(10) unsigned DEFAULT NULL,
  `sucursal_id` int(10) unsigned DEFAULT NULL,
  `descuento_pct` decimal(5,2) DEFAULT NULL COMMENT 'PROMOCION: descuento sobre el precio de lista',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Inactiva',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`regla_venta_id`),
  KEY `idx_prv_empresa` (`empresa_id`,`tabla_estado_registro_id`,`f_desde`),
  KEY `idx_prv_producto` (`empresa_id`,`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Escalas por cantidad y promociones de venta, evaluadas al vender';

-- Tabla: gestion__precios_reglas_venta_tramos
CREATE TABLE IF NOT EXISTS `gestion__precios_reglas_venta_tramos` (
  `tramo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `regla_venta_id` int(10) unsigned NOT NULL,
  `cantidad_desde` decimal(18,4) NOT NULL COMMENT 'En unidad base del producto, sumando todo el comprobante',
  `descuento_pct` decimal(5,2) NOT NULL,
  PRIMARY KEY (`tramo_id`),
  KEY `idx_prvt_regla` (`regla_venta_id`,`cantidad_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tramos de una escala por cantidad';

-- Tabla: gestion__productos_marcas
CREATE TABLE IF NOT EXISTS `gestion__productos_marcas` (
  `marca_comercial_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Inactiva',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`marca_comercial_id`),
  KEY `idx_pma_empresa` (`empresa_id`,`tabla_estado_registro_id`,`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Marcas comerciales de los productos (no las de vehículos de gestion__marcas)';

-- Tabla: gestion__productos_presentaciones
CREATE TABLE IF NOT EXISTS `gestion__productos_presentaciones` (
  `presentacion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `producto_id` int(10) unsigned NOT NULL,
  `presentacion_nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: Caja x 12, Pack x 6, Pallet',
  `factor` decimal(18,4) NOT NULL COMMENT 'Unidades de la unidad de medida del producto que contiene (> 0)',
  `codigo_barras` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'EAN-13 / DUN-14 del bulto; único por empresa entre productos y presentaciones activas',
  `peso_bruto_kg` decimal(10,3) DEFAULT NULL,
  `largo_cm` decimal(8,2) DEFAULT NULL,
  `ancho_cm` decimal(8,2) DEFAULT NULL,
  `alto_cm` decimal(8,2) DEFAULT NULL,
  `aplica_compra` tinyint(1) NOT NULL DEFAULT 1,
  `aplica_venta` tinyint(1) NOT NULL DEFAULT 1,
  `es_compra_defecto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Presentación habitual de compra (una por producto)',
  `es_venta_defecto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Presentación habitual de venta (una por producto)',
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Activa / 2 Inactiva (no se borran)',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `f_alta` datetime NOT NULL DEFAULT current_timestamp(),
  `f_modificacion` datetime DEFAULT NULL,
  `venta_minimo` decimal(18,4) DEFAULT NULL COMMENT 'Cantidad mínima de venta, en esta presentación (NULL = sin mínimo)',
  `venta_multiplo` decimal(18,4) DEFAULT NULL COMMENT 'Múltiplo de venta, en esta presentación (NULL = cualquier cantidad)',
  PRIMARY KEY (`presentacion_id`),
  KEY `idx_prpr_producto` (`empresa_id`,`producto_id`,`tabla_estado_registro_id`),
  KEY `idx_prpr_barras` (`empresa_id`,`codigo_barras`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Presentaciones de un producto (caja, pack, pallet) con factor de conversión a la unidad del producto';

-- Tabla: gestion__sucursales_canales_venta
CREATE TABLE IF NOT EXISTS `gestion__sucursales_canales_venta` (
  `sucursal_canal_venta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` int(10) unsigned NOT NULL,
  `canal_venta_id` int(10) unsigned NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 Vende / 2 Ya no vende (historial)',
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`sucursal_canal_venta_id`),
  UNIQUE KEY `uk_sucursales_canales_venta` (`sucursal_id`,`canal_venta_id`),
  KEY `idx_sucursales_canales_venta_empresa` (`empresa_id`,`sucursal_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Canales que vende cada sucursal (sin filas activas = todos)';

-- Tabla: gestion__vendedores_carteras
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
  KEY `idx_vca_cliente` (`empresa_id`,`cliente_entidad_id`,`tabla_estado_registro_id`,`f_desde`),
  KEY `idx_vca_vendedor` (`empresa_id`,`vendedor_entidad_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cartera de clientes de cada vendedor, con vigencia';

-- Tabla: gestion__vendedores_usuarios
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
  KEY `idx_vus_usuario` (`empresa_id`,`usuario_id`,`tabla_estado_registro_id`),
  KEY `idx_vus_vendedor` (`empresa_id`,`vendedor_entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuario que es cada vendedor (un usuario, un vendedor por empresa a una fecha)';

-- Tabla: gestion__ventas_descuentos_topes
CREATE TABLE IF NOT EXISTS `gestion__ventas_descuentos_topes` (
  `descuento_tope_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `empresa_perfil_id` int(11) NOT NULL COMMENT 'conf__empresas_perfiles',
  `tope_pct` decimal(5,2) NOT NULL COMMENT '% máximo de descuento manual que puede dar o aprobar el perfil',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`descuento_tope_id`),
  KEY `idx_vdt_perfil` (`empresa_id`,`empresa_perfil_id`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tope de descuento manual en ventas por perfil (sin fila = 0 %)';

-- =================================================================================
-- 2. COLUMNAS NUEVAS EN TABLAS EXISTENTES
-- =================================================================================

-- conf__empresas.nivel_gestion
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'nivel_gestion');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `nivel_gestion` enum(''COMPLETA'',''SIMPLIFICADA'') NOT NULL DEFAULT ''COMPLETA'' COMMENT ''SIMPLIFICADA = cliente de estudio contable: solo comprobantes, IVA, contabilidad e impuestos'' AFTER `tipo_operacion`', 'SELECT ''conf__empresas.nivel_gestion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_presentaciones
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_presentaciones');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_presentaciones` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = los productos tienen presentaciones (caja, pack, pallet) con factor y código de barras propio'' AFTER `decimales_stock`', 'SELECT ''conf__empresas.usa_presentaciones ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_venta_presentaciones
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_venta_presentaciones');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_venta_presentaciones` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = vende por presentación (precio propio por lista, mínimo y múltiplo); exige usa_presentaciones'' AFTER `tabla_estado_registro_id`', 'SELECT ''conf__empresas.usa_venta_presentaciones ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_canales_venta
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_canales_venta');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_canales_venta` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = canales de venta por lista, sucursales que restringen canales y lista del cliente unificada'' AFTER `usa_venta_presentaciones`', 'SELECT ''conf__empresas.usa_canales_venta ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_reglas_precio_venta
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_reglas_precio_venta');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_reglas_precio_venta` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = escalas por cantidad, promociones y tope del descuento manual al vender'' AFTER `usa_canales_venta`', 'SELECT ''conf__empresas.usa_reglas_precio_venta ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_vendedores
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_vendedores');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_vendedores` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = vendedores, carteras de clientes y vendedor en el pedido'' AFTER `usa_reglas_precio_venta`', 'SELECT ''conf__empresas.usa_vendedores ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_distribucion
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_distribucion');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_distribucion` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = zonas, camiones, armado de reparto y hojas de ruta'' AFTER `usa_vendedores`', 'SELECT ''conf__empresas.usa_distribucion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_marcas_comerciales
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_marcas_comerciales');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_marcas_comerciales` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = marca comercial en los productos'' AFTER `usa_distribucion`', 'SELECT ''conf__empresas.usa_marcas_comerciales ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_ficha_producto_completa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_ficha_producto_completa');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_ficha_producto_completa` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = solapas Proveedores, Precios, Ubicaciones e Imágenes en la ficha de productos del módulo 5'' AFTER `usa_marcas_comerciales`', 'SELECT ''conf__empresas.usa_ficha_producto_completa ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_compra_presentaciones
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_compra_presentaciones');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__empresas` ADD COLUMN `usa_compra_presentaciones` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = órdenes de compra y facturas de proveedor por presentación (caja, pack)'' AFTER `usa_ficha_producto_completa`', 'SELECT ''conf__empresas.usa_compra_presentaciones ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__paginas.requiere_gestion_completa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__paginas' AND COLUMN_NAME = 'requiere_gestion_completa');
SET @sql := IF(@existe = 0, 'ALTER TABLE `conf__paginas` ADD COLUMN `requiere_gestion_completa` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = la página no se ofrece a empresas de gestión simplificada'' AFTER `requiere_bienes`', 'SELECT ''conf__paginas.requiere_gestion_completa ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- estudio__clientes.nivel_servicio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'nivel_servicio');
SET @sql := IF(@existe = 0, 'ALTER TABLE `estudio__clientes` ADD COLUMN `nivel_servicio` enum(''PADRON'',''FISCAL'',''CONTABLE'',''GESTION'') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''PADRON'' COMMENT ''Qué servicio recibe el cliente; define qué datos necesita el estudio'' AFTER `cliente_tipo_id`', 'SELECT ''estudio__clientes.nivel_servicio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- estudio__clientes.origen_datos
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'origen_datos');
SET @sql := IF(@existe = 0, 'ALTER TABLE `estudio__clientes` ADD COLUMN `origen_datos` enum(''LOCAL'',''REMOTO'',''EXTERNO'') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''LOCAL'' COMMENT ''LOCAL = empresa de este ERP; REMOTO = servidor propio del cliente; EXTERNO = software de terceros (importación de archivos)'' AFTER `empresa_vinculada_id`', 'SELECT ''estudio__clientes.origen_datos ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- estudio__clientes.remoto_url
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'remoto_url');
SET @sql := IF(@existe = 0, 'ALTER TABLE `estudio__clientes` ADD COLUMN `remoto_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''URL base del ERP del cliente (solo REMOTO)'' AFTER `origen_datos`', 'SELECT ''estudio__clientes.remoto_url ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__empresas_autorizaciones_copia.permite_impuestos_config
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_autorizaciones_copia' AND COLUMN_NAME = 'permite_impuestos_config');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__empresas_autorizaciones_copia` ADD COLUMN `permite_impuestos_config` tinyint(1) NOT NULL DEFAULT 1 AFTER `permite_tesoreria`', 'SELECT ''gestion__empresas_autorizaciones_copia.permite_impuestos_config ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__empresas_autorizaciones_copia.permite_productos
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_autorizaciones_copia' AND COLUMN_NAME = 'permite_productos');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__empresas_autorizaciones_copia` ADD COLUMN `permite_productos` tinyint(1) NOT NULL DEFAULT 1 AFTER `permite_impuestos_config`', 'SELECT ''gestion__empresas_autorizaciones_copia.permite_productos ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores.certificacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'certificacion_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__facturas_proveedores` ADD COLUMN `certificacion_id` int(11) NULL DEFAULT NULL COMMENT ''FK a gestion__ordenes_compra_certificaciones'' AFTER `orden_compra_id`', 'SELECT ''gestion__facturas_proveedores.certificacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores_detalle.presentacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__facturas_proveedores_detalle` ADD COLUMN `presentacion_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''Presentación comprada (gestion__productos_presentaciones); NULL = por unidad'' AFTER `tabla_estado_registro_id`', 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores_detalle.presentacion_nombre
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_nombre');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__facturas_proveedores_detalle` ADD COLUMN `presentacion_nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación'' AFTER `presentacion_id`', 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_nombre ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores_detalle.presentacion_factor
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_factor');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__facturas_proveedores_detalle` ADD COLUMN `presentacion_factor` decimal(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)'' AFTER `presentacion_nombre`', 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_factor ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores_detalle.presentacion_cantidad
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_cantidad');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__facturas_proveedores_detalle` ADD COLUMN `presentacion_cantidad` decimal(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)'' AFTER `presentacion_factor`', 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_cantidad ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores_detalle.presentacion_precio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'presentacion_precio');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__facturas_proveedores_detalle` ADD COLUMN `presentacion_precio` decimal(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera del proveedor, antes de descuentos'' AFTER `presentacion_cantidad`', 'SELECT ''gestion__facturas_proveedores_detalle.presentacion_precio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__listas_precios.vende_unidad_base
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__listas_precios' AND COLUMN_NAME = 'vende_unidad_base');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__listas_precios` ADD COLUMN `vende_unidad_base` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT ''0 = la lista no ofrece la unidad suelta (solo presentaciones); solo con usa_venta_presentaciones'' AFTER `actualizado_en`', 'SELECT ''gestion__listas_precios.vende_unidad_base ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__listas_precios.canal_venta_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__listas_precios' AND COLUMN_NAME = 'canal_venta_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__listas_precios` ADD COLUMN `canal_venta_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''gestion__canales_venta (NULL = sin canal); solo con usa_canales_venta'' AFTER `vende_unidad_base`', 'SELECT ''gestion__listas_precios.canal_venta_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__listas_precios.descuento_tope_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__listas_precios' AND COLUMN_NAME = 'descuento_tope_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__listas_precios` ADD COLUMN `descuento_tope_pct` decimal(5,2) NULL DEFAULT NULL COMMENT ''Tope del descuento automático (cliente / escala / promoción) en esta lista; NULL = sin tope'' AFTER `canal_venta_id`', 'SELECT ''gestion__listas_precios.descuento_tope_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.tipo_circuito
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'tipo_circuito');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `tipo_circuito` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''BIENES'' COMMENT ''BIENES / CONTRATO_RECURRENTE / CONTRATO_AVANCE'' AFTER `comprobante_tipo_id`', 'SELECT ''gestion__ordenes_compra.tipo_circuito ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.es_contrato
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'es_contrato');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `es_contrato` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Tratamiento especial como contrato / servicio recurrente'' AFTER `tipo_circuito`', 'SELECT ''gestion__ordenes_compra.es_contrato ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.contrato_nro_legal
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'contrato_nro_legal');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `contrato_nro_legal` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Número o código legal externo del contrato'' AFTER `comprobante_nro`', 'SELECT ''gestion__ordenes_compra.contrato_nro_legal ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.fecha_inicio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'fecha_inicio');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `fecha_inicio` date NULL DEFAULT NULL COMMENT ''Inicio de vigencia'' AFTER `f_entrega_estimada`', 'SELECT ''gestion__ordenes_compra.fecha_inicio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.fecha_fin
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'fecha_fin');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `fecha_fin` date NULL DEFAULT NULL COMMENT ''Fin de vigencia del contrato'' AFTER `fecha_inicio`', 'SELECT ''gestion__ordenes_compra.fecha_fin ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.dias_preaviso_rescision
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'dias_preaviso_rescision');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `dias_preaviso_rescision` int(11) NOT NULL DEFAULT 30 COMMENT ''Días de anticipación para notificar no renovación'' AFTER `fecha_fin`', 'SELECT ''gestion__ordenes_compra.dias_preaviso_rescision ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.tiene_renovacion_automatica
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'tiene_renovacion_automatica');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `tiene_renovacion_automatica` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Renovación automática si no hay preaviso'' AFTER `dias_preaviso_rescision`', 'SELECT ''gestion__ordenes_compra.tiene_renovacion_automatica ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.requiere_certificacion_avance
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'requiere_certificacion_avance');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `requiere_certificacion_avance` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Exige certificado aprobado para facturar'' AFTER `tiene_renovacion_automatica`', 'SELECT ''gestion__ordenes_compra.requiere_certificacion_avance ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.pct_anticipo_financiero
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'pct_anticipo_financiero');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `pct_anticipo_financiero` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT ''% de anticipo pactado a amortizar en certificados'' AFTER `requiere_certificacion_avance`', 'SELECT ''gestion__ordenes_compra.pct_anticipo_financiero ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.pct_fondo_reparo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'pct_fondo_reparo');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `pct_fondo_reparo` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT ''% de fondo de reparo / retención de garantía'' AFTER `pct_anticipo_financiero`', 'SELECT ''gestion__ordenes_compra.pct_fondo_reparo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.responsable_tecnico_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'responsable_tecnico_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `responsable_tecnico_id` int(11) NULL DEFAULT NULL COMMENT ''Usuario habilitado para aprobar certificaciones'' AFTER `pct_fondo_reparo`', 'SELECT ''gestion__ordenes_compra.responsable_tecnico_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.archivo_contrato_adjunto
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'archivo_contrato_adjunto');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `archivo_contrato_adjunto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Ruta del archivo PDF del contrato firmado'' AFTER `responsable_tecnico_id`', 'SELECT ''gestion__ordenes_compra.archivo_contrato_adjunto ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra_detalle.presentacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra_detalle` ADD COLUMN `presentacion_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''Presentación comprada (gestion__productos_presentaciones); NULL = por unidad'' AFTER `total_linea`', 'SELECT ''gestion__ordenes_compra_detalle.presentacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra_detalle.presentacion_nombre
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_nombre');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra_detalle` ADD COLUMN `presentacion_nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación'' AFTER `presentacion_id`', 'SELECT ''gestion__ordenes_compra_detalle.presentacion_nombre ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra_detalle.presentacion_factor
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_factor');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra_detalle` ADD COLUMN `presentacion_factor` decimal(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)'' AFTER `presentacion_nombre`', 'SELECT ''gestion__ordenes_compra_detalle.presentacion_factor ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra_detalle.presentacion_cantidad
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_cantidad');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra_detalle` ADD COLUMN `presentacion_cantidad` decimal(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)'' AFTER `presentacion_factor`', 'SELECT ''gestion__ordenes_compra_detalle.presentacion_cantidad ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra_detalle.presentacion_precio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' AND COLUMN_NAME = 'presentacion_precio');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra_detalle` ADD COLUMN `presentacion_precio` decimal(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera del proveedor, antes de descuentos'' AFTER `presentacion_cantidad`', 'SELECT ''gestion__ordenes_compra_detalle.presentacion_precio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__productos.venta_minimo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND COLUMN_NAME = 'venta_minimo');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__productos` ADD COLUMN `venta_minimo` decimal(18,4) NULL DEFAULT NULL COMMENT ''Cantidad mínima de venta en unidad base (NULL = sin mínimo)'' AFTER `aplica_venta`', 'SELECT ''gestion__productos.venta_minimo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__productos.venta_multiplo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND COLUMN_NAME = 'venta_multiplo');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__productos` ADD COLUMN `venta_multiplo` decimal(18,4) NULL DEFAULT NULL COMMENT ''Múltiplo de venta en unidad base (NULL = cualquier cantidad)'' AFTER `venta_minimo`', 'SELECT ''gestion__productos.venta_multiplo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__productos.marca_comercial_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND COLUMN_NAME = 'marca_comercial_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__productos` ADD COLUMN `marca_comercial_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''Marca comercial (gestion__productos_marcas); NULL = sin marca'' AFTER `venta_multiplo`', 'SELECT ''gestion__productos.marca_comercial_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__productos_proveedores.presentacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores' AND COLUMN_NAME = 'presentacion_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__productos_proveedores` ADD COLUMN `presentacion_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''Presentación en la que vende el proveedor (gestion__productos_presentaciones); NULL = por unidad'' AFTER `tabla_estado_registro_id`', 'SELECT ''gestion__productos_proveedores.presentacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.presentacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `presentacion_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''Presentación vendida (gestion__productos_presentaciones); NULL = por unidad'' AFTER `tabla_estado_registro_id`', 'SELECT ''gestion__ventas_facturas_detalles.presentacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.presentacion_nombre
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_nombre');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `presentacion_nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación'' AFTER `presentacion_id`', 'SELECT ''gestion__ventas_facturas_detalles.presentacion_nombre ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.presentacion_factor
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_factor');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `presentacion_factor` decimal(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)'' AFTER `presentacion_nombre`', 'SELECT ''gestion__ventas_facturas_detalles.presentacion_factor ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.presentacion_cantidad
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_cantidad');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `presentacion_cantidad` decimal(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)'' AFTER `presentacion_factor`', 'SELECT ''gestion__ventas_facturas_detalles.presentacion_cantidad ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.presentacion_precio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'presentacion_precio');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `presentacion_precio` decimal(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera, antes del descuento'' AFTER `presentacion_cantidad`', 'SELECT ''gestion__ventas_facturas_detalles.presentacion_precio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.precio_regla_tipo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'precio_regla_tipo');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `precio_regla_tipo` varchar(10) NULL DEFAULT NULL COMMENT ''Regla del descuento automático: CLIENTE / ESCALA / PROMOCION / NINGUNA (NULL = sin reglas de precio)'' AFTER `presentacion_precio`', 'SELECT ''gestion__ventas_facturas_detalles.precio_regla_tipo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.precio_regla_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'precio_regla_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `precio_regla_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''gestion__precios_reglas_venta (ESCALA / PROMOCION)'' AFTER `precio_regla_tipo`', 'SELECT ''gestion__ventas_facturas_detalles.precio_regla_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.descuento_auto_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_auto_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `descuento_auto_pct` decimal(5,2) NULL DEFAULT NULL COMMENT ''Descuento automático (cliente, escala o promoción), ya con el tope de la lista'' AFTER `precio_regla_id`', 'SELECT ''gestion__ventas_facturas_detalles.descuento_auto_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.descuento_manual_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_manual_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `descuento_manual_pct` decimal(5,2) NULL DEFAULT NULL COMMENT ''Descuento manual del vendedor, sobre el neto del automático'' AFTER `descuento_auto_pct`', 'SELECT ''gestion__ventas_facturas_detalles.descuento_manual_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.descuento_aprobado_por
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_aprobado_por');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `descuento_aprobado_por` int(10) unsigned NULL DEFAULT NULL COMMENT ''Usuario cuyo tope cubrió el descuento manual'' AFTER `descuento_manual_pct`', 'SELECT ''gestion__ventas_facturas_detalles.descuento_aprobado_por ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_facturas_detalles.descuento_aprobado_en
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas_detalles' AND COLUMN_NAME = 'descuento_aprobado_en');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_facturas_detalles` ADD COLUMN `descuento_aprobado_en` datetime NULL DEFAULT NULL AFTER `descuento_aprobado_por`', 'SELECT ''gestion__ventas_facturas_detalles.descuento_aprobado_en ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos.vendedor_entidad_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos' AND COLUMN_NAME = 'vendedor_entidad_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos` ADD COLUMN `vendedor_entidad_id` int(11) NULL DEFAULT NULL COMMENT ''Vendedor (entidad con rol VEN); NULL = sin vendedor'' AFTER `tabla_estado_registro_id`', 'SELECT ''gestion__ventas_pedidos.vendedor_entidad_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos.origen
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos' AND COLUMN_NAME = 'origen');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos` ADD COLUMN `origen` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''ERP / VENDEDOR / CARRITO / APP; NULL = anterior al indicador'' AFTER `vendedor_entidad_id`', 'SELECT ''gestion__ventas_pedidos.origen ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos.boca_despacho_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos' AND COLUMN_NAME = 'boca_despacho_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos` ADD COLUMN `boca_despacho_id` int(11) NULL DEFAULT NULL COMMENT ''Depósito de despacho (gestion__bocas); NULL = sin definir'' AFTER `origen`', 'SELECT ''gestion__ventas_pedidos.boca_despacho_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.presentacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `presentacion_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''Presentación vendida (gestion__productos_presentaciones); NULL = por unidad'' AFTER `tabla_estado_registro_id`', 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.presentacion_nombre
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_nombre');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `presentacion_nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación'' AFTER `presentacion_id`', 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_nombre ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.presentacion_factor
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_factor');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `presentacion_factor` decimal(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)'' AFTER `presentacion_nombre`', 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_factor ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.presentacion_cantidad
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_cantidad');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `presentacion_cantidad` decimal(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)'' AFTER `presentacion_factor`', 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_cantidad ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.presentacion_precio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'presentacion_precio');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `presentacion_precio` decimal(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera, antes del descuento'' AFTER `presentacion_cantidad`', 'SELECT ''gestion__ventas_pedidos_detalles.presentacion_precio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.precio_regla_tipo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'precio_regla_tipo');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `precio_regla_tipo` varchar(10) NULL DEFAULT NULL COMMENT ''Regla del descuento automático: CLIENTE / ESCALA / PROMOCION / NINGUNA (NULL = sin reglas de precio)'' AFTER `presentacion_precio`', 'SELECT ''gestion__ventas_pedidos_detalles.precio_regla_tipo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.precio_regla_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'precio_regla_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `precio_regla_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''gestion__precios_reglas_venta (ESCALA / PROMOCION)'' AFTER `precio_regla_tipo`', 'SELECT ''gestion__ventas_pedidos_detalles.precio_regla_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.descuento_auto_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_auto_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `descuento_auto_pct` decimal(5,2) NULL DEFAULT NULL COMMENT ''Descuento automático (cliente, escala o promoción), ya con el tope de la lista'' AFTER `precio_regla_id`', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_auto_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.descuento_manual_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_manual_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `descuento_manual_pct` decimal(5,2) NULL DEFAULT NULL COMMENT ''Descuento manual del vendedor, sobre el neto del automático'' AFTER `descuento_auto_pct`', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_manual_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.descuento_aprobado_por
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_aprobado_por');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `descuento_aprobado_por` int(10) unsigned NULL DEFAULT NULL COMMENT ''Usuario cuyo tope cubrió el descuento manual'' AFTER `descuento_manual_pct`', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_aprobado_por ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos_detalles.descuento_aprobado_en
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME = 'descuento_aprobado_en');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos_detalles` ADD COLUMN `descuento_aprobado_en` datetime NULL DEFAULT NULL AFTER `descuento_aprobado_por`', 'SELECT ''gestion__ventas_pedidos_detalles.descuento_aprobado_en ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.presentacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `presentacion_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''Presentación vendida (gestion__productos_presentaciones); NULL = por unidad'' AFTER `tabla_estado_registro_id`', 'SELECT ''gestion__ventas_remitos_detalles.presentacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.presentacion_nombre
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_nombre');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `presentacion_nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''Foto del nombre de la presentación'' AFTER `presentacion_id`', 'SELECT ''gestion__ventas_remitos_detalles.presentacion_nombre ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.presentacion_factor
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_factor');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `presentacion_factor` decimal(18,4) NULL DEFAULT NULL COMMENT ''Foto del factor (unidades base por presentación)'' AFTER `presentacion_nombre`', 'SELECT ''gestion__ventas_remitos_detalles.presentacion_factor ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.presentacion_cantidad
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_cantidad');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `presentacion_cantidad` decimal(15,4) NULL DEFAULT NULL COMMENT ''Cantidad en presentaciones (cantidad = esta × factor)'' AFTER `presentacion_factor`', 'SELECT ''gestion__ventas_remitos_detalles.presentacion_cantidad ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.presentacion_precio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'presentacion_precio');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `presentacion_precio` decimal(18,6) NULL DEFAULT NULL COMMENT ''Precio de la presentación entera, antes del descuento'' AFTER `presentacion_cantidad`', 'SELECT ''gestion__ventas_remitos_detalles.presentacion_precio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.precio_regla_tipo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'precio_regla_tipo');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `precio_regla_tipo` varchar(10) NULL DEFAULT NULL COMMENT ''Regla del descuento automático: CLIENTE / ESCALA / PROMOCION / NINGUNA (NULL = sin reglas de precio)'' AFTER `presentacion_precio`', 'SELECT ''gestion__ventas_remitos_detalles.precio_regla_tipo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.precio_regla_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'precio_regla_id');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `precio_regla_id` int(10) unsigned NULL DEFAULT NULL COMMENT ''gestion__precios_reglas_venta (ESCALA / PROMOCION)'' AFTER `precio_regla_tipo`', 'SELECT ''gestion__ventas_remitos_detalles.precio_regla_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.descuento_auto_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_auto_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `descuento_auto_pct` decimal(5,2) NULL DEFAULT NULL COMMENT ''Descuento automático (cliente, escala o promoción), ya con el tope de la lista'' AFTER `precio_regla_id`', 'SELECT ''gestion__ventas_remitos_detalles.descuento_auto_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.descuento_manual_pct
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_manual_pct');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `descuento_manual_pct` decimal(5,2) NULL DEFAULT NULL COMMENT ''Descuento manual del vendedor, sobre el neto del automático'' AFTER `descuento_auto_pct`', 'SELECT ''gestion__ventas_remitos_detalles.descuento_manual_pct ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.descuento_aprobado_por
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_aprobado_por');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `descuento_aprobado_por` int(10) unsigned NULL DEFAULT NULL COMMENT ''Usuario cuyo tope cubrió el descuento manual'' AFTER `descuento_manual_pct`', 'SELECT ''gestion__ventas_remitos_detalles.descuento_aprobado_por ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_remitos_detalles.descuento_aprobado_en
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_remitos_detalles' AND COLUMN_NAME = 'descuento_aprobado_en');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_remitos_detalles` ADD COLUMN `descuento_aprobado_en` datetime NULL DEFAULT NULL AFTER `descuento_aprobado_por`', 'SELECT ''gestion__ventas_remitos_detalles.descuento_aprobado_en ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- =================================================================================
-- 3. INDICES NUEVOS EN TABLAS EXISTENTES
-- =================================================================================

-- estudio__minigestion_comprobantes.idx_duplicado
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__minigestion_comprobantes' AND INDEX_NAME = 'idx_duplicado');
SET @sql := IF(@existe = 0, 'ALTER TABLE `estudio__minigestion_comprobantes` ADD INDEX `idx_duplicado` (`empresa_id`,`cliente_id`,`tipo_operacion`,`comprobante_tipo_id`,`punto_venta`,`comprobante_nro`,`cuit_contraparte`)', 'SELECT ''estudio__minigestion_comprobantes.idx_duplicado ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores.idx_fp_certificacion
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND INDEX_NAME = 'idx_fp_certificacion');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__facturas_proveedores` ADD INDEX `idx_fp_certificacion` (`certificacion_id`)', 'SELECT ''gestion__facturas_proveedores.idx_fp_certificacion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.idx_oc_contratos_vigencia
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND INDEX_NAME = 'idx_oc_contratos_vigencia');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD INDEX `idx_oc_contratos_vigencia` (`empresa_id`,`es_contrato`,`fecha_fin`)', 'SELECT ''gestion__ordenes_compra.idx_oc_contratos_vigencia ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.idx_oc_tipo_circuito
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND INDEX_NAME = 'idx_oc_tipo_circuito');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ordenes_compra` ADD INDEX `idx_oc_tipo_circuito` (`empresa_id`,`tipo_circuito`)', 'SELECT ''gestion__ordenes_compra.idx_oc_tipo_circuito ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__productos.idx_prod_marca_comercial
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos' AND INDEX_NAME = 'idx_prod_marca_comercial');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__productos` ADD INDEX `idx_prod_marca_comercial` (`empresa_id`,`marca_comercial_id`)', 'SELECT ''gestion__productos.idx_prod_marca_comercial ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ventas_pedidos.idx_vped_vendedor
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_pedidos' AND INDEX_NAME = 'idx_vped_vendedor');
SET @sql := IF(@existe = 0, 'ALTER TABLE `gestion__ventas_pedidos` ADD INDEX `idx_vped_vendedor` (`empresa_id`,`vendedor_entidad_id`)', 'SELECT ''gestion__ventas_pedidos.idx_vped_vendedor ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
