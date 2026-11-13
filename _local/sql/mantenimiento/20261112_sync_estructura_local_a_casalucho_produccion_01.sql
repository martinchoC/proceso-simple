-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- ============================================================================
-- Sincronización de ESTRUCTURA gestion_multipyme (local) -> producción Casa Lucho
-- Generado el 2026-11-12 comparando information_schema de gestion_multipyme
-- contra ps_gestion_casalucho_produccion. Las definiciones salen de
-- SHOW CREATE TABLE de la base local.
--
-- Solo AGREGA: tablas, columnas, índices y claves foráneas que faltan.
-- No borra ni modifica nada existente. No carga datos de configuración
-- (páginas, funciones, permisos, integraciones): esos quedan en los scripts
-- de cada tema (ver el final).
-- Excluidas: tablas *_bakAAAAMMDD y perf_* (diagnóstico de rendimiento local).
-- Sin cambios: diferencias de ancho de display de enteros (int(10) / int(11)).
--
-- Reanudable e idempotente: CREATE TABLE IF NOT EXISTS y cada ALTER guardado
-- con information_schema. MariaDB 10.4 y MySQL 8.0.
-- ============================================================================

SET NAMES utf8mb4;
SET @fk_previo := @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Tablas nuevas (19)
-- ----------------------------------------------------------------------------

-- conf__imagenes
CREATE TABLE IF NOT EXISTS `conf__imagenes` (
  `imagen_id` int(11) NOT NULL AUTO_INCREMENT,
  `imagen_nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_tamanio` int(11) NOT NULL,
  `imagen_data` longblob DEFAULT NULL,
  `imagen_creacion` datetime DEFAULT current_timestamp(),
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`imagen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- conf__integraciones
CREATE TABLE IF NOT EXISTS `conf__integraciones` (
  `integracion_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL COMMENT 'Identificador técnico estable (ARCA, MERCADOPAGO, ...)',
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`integracion_id`),
  UNIQUE KEY `uk_integraciones_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- conf__integraciones_ambientes
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

-- conf__integraciones_operaciones
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

-- conf__migraciones
CREATE TABLE IF NOT EXISTS `conf__migraciones` (
  `migracion_id` int(11) NOT NULL AUTO_INCREMENT,
  `script` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del archivo en _local/sql/mantenimiento/',
  `estado` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'APLICADO / MARCADO (ya estaba, sin correrlo) / OMITIDO (no corresponde) / FALLIDO',
  `checksum` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'md5 del script al aplicarlo o marcarlo',
  `origen` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GESTOR' COMMENT 'GESTOR (htdocs/proyectos) / PAQUETE (.sql generado) / ALTA (heredado al crear la base)',
  `duracion_ms` int(11) DEFAULT NULL,
  `salida` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Salida o error del cliente mysql (recortada)',
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`migracion_id`),
  UNIQUE KEY `uk_migraciones_script` (`script`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scripts de _local/sql/mantenimiento aplicados en esta base';

-- estudio__inspecciones
CREATE TABLE IF NOT EXISTS `estudio__inspecciones` (
  `inspeccion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant del estudio',
  `cliente_id` bigint(20) unsigned NOT NULL COMMENT 'FK estudio__clientes',
  `organismo` enum('ARCA','COMARB','PROVINCIAL','MUNICIPAL','SINDICATO','TRABAJO','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ARCA',
  `tipo` enum('FISCALIZACION','VERIFICACION','REQUERIMIENTO','INTIMACION','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FISCALIZACION',
  `numero_acta` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Acta, orden de intervención o expediente',
  `impuesto_obligacion_id` int(10) unsigned DEFAULT NULL COMMENT 'FK estudio__impuestos_obligaciones (si es de un impuesto puntual)',
  `periodo_desde` int(10) unsigned DEFAULT NULL COMMENT 'YYYYMM',
  `periodo_hasta` int(10) unsigned DEFAULT NULL COMMENT 'YYYYMM',
  `f_inicio` date NOT NULL,
  `f_cierre` date DEFAULT NULL,
  `estado_proceso` enum('ABIERTA','EN_DESCARGO','EN_APELACION','PLAN_PAGOS','CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ABIERTA',
  `monto_reclamado` decimal(16,2) NOT NULL DEFAULT 0.00,
  `monto_aceptado` decimal(16,2) NOT NULL DEFAULT 0.00,
  `monto_pagado` decimal(16,2) NOT NULL DEFAULT 0.00,
  `usuario_r_id` int(10) unsigned DEFAULT NULL COMMENT 'Responsable del estudio',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '1 vigente, 38 anulada',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`inspeccion_id`),
  KEY `idx_empresa_cliente` (`empresa_id`,`cliente_id`),
  KEY `idx_empresa_estado` (`empresa_id`,`estado_proceso`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__inspecciones_hitos
CREATE TABLE IF NOT EXISTS `estudio__inspecciones_hitos` (
  `hito_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `inspeccion_id` bigint(20) unsigned NOT NULL,
  `tipo_hito` enum('NOTIFICACION','REQUERIMIENTO','PRESENTACION','AUDIENCIA','RESOLUCION','PAGO','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OTRO',
  `f_hito` date NOT NULL,
  `f_limite` date DEFAULT NULL COMMENT 'Plazo a cumplir (respuesta, presentación, pago)',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `importe` decimal(16,2) DEFAULT NULL,
  `cumplido` tinyint(1) NOT NULL DEFAULT 0,
  `tarea_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK estudio__tareas si se generó la tarea',
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`hito_id`),
  KEY `idx_inspeccion` (`empresa_id`,`inspeccion_id`,`f_hito`),
  KEY `idx_plazo` (`empresa_id`,`cumplido`,`f_limite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__minigestion_comprobantes_iva
CREATE TABLE IF NOT EXISTS `estudio__minigestion_comprobantes_iva` (
  `iva_linea_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'FK estudio__minigestion_comprobantes',
  `alicuota` decimal(5,2) NOT NULL COMMENT '0, 2.5, 5, 10.5, 21, 27',
  `neto` decimal(16,2) NOT NULL DEFAULT 0.00,
  `iva` decimal(16,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`iva_linea_id`),
  UNIQUE KEY `uk_comprobante_alicuota` (`comprobante_id`,`alicuota`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__minigestion_comprobantes_percepciones
CREATE TABLE IF NOT EXISTS `estudio__minigestion_comprobantes_percepciones` (
  `percepcion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('IVA','IIBB','GANANCIAS','SUSS','MUNICIPAL','OTRA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jurisdiccion_id` int(10) unsigned DEFAULT NULL COMMENT 'FK gestion__jurisdicciones; solo IIBB/MUNICIPAL',
  `importe` decimal(16,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`percepcion_id`),
  KEY `idx_comprobante` (`comprobante_id`),
  KEY `idx_empresa_tipo` (`empresa_id`,`tipo`,`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__minigestion_liquidaciones_iva
CREATE TABLE IF NOT EXISTS `estudio__minigestion_liquidaciones_iva` (
  `liquidacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant del estudio',
  `cliente_id` bigint(20) unsigned NOT NULL,
  `periodo` int(10) unsigned NOT NULL COMMENT 'YYYYMM',
  `debito_fiscal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `credito_fiscal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `percepciones_iva` decimal(16,2) NOT NULL DEFAULT 0.00,
  `retenciones_iva` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'Retenciones / pagos a cuenta informados al liquidar',
  `saldo_favor_anterior` decimal(16,2) NOT NULL DEFAULT 0.00,
  `saldo_a_pagar` decimal(16,2) NOT NULL DEFAULT 0.00,
  `saldo_a_favor` decimal(16,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 5 COMMENT '5 vigente, 38 anulada',
  `created_at` datetime DEFAULT current_timestamp(),
  `anulada_at` datetime DEFAULT NULL,
  PRIMARY KEY (`liquidacion_id`),
  KEY `idx_cliente_periodo` (`empresa_id`,`cliente_id`,`periodo`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- estudio__plantillas_cliente
CREATE TABLE IF NOT EXISTS `estudio__plantillas_cliente` (
  `plantilla_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant del estudio',
  `empresa_origen_id` int(10) unsigned NOT NULL COMMENT 'Empresa modelo que se copia',
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`plantilla_id`),
  UNIQUE KEY `uq_estudio_origen` (`empresa_id`,`empresa_origen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gestion__empresas_iibb_actividades
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

-- gestion__iibb_posiciones
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

-- gestion__iibb_posiciones_actividades
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

-- gestion__integraciones_autenticaciones
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

-- gestion__integraciones_empresas
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

-- gestion__integraciones_resultados
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

-- gestion__ordenes_compra_certificaciones
CREATE TABLE IF NOT EXISTS `gestion__ordenes_compra_certificaciones` (
  `certificacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `orden_compra_id` int(11) NOT NULL COMMENT 'FK a gestion__ordenes_compra',
  `nro_certificado` int(11) NOT NULL COMMENT 'Correlativo por contrato (1, 2, 3...)',
  `fecha_medicion` date NOT NULL COMMENT 'Fecha de corte físico / medición',
  `periodo_mes` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Período correspondiente AAAA-MM',
  `avance_acumulado_pct` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de avance físico total acumulado a la fecha',
  `avance_periodo_pct` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de avance físico correspondiente a este certificado',
  `monto_bruto_periodo` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto bruto del avance valorizado a precios del contrato',
  `descuento_anticipo` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Amortización proporcional de anticipo financiero otorgado',
  `fondo_reparo_retenido` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Retención de garantía / fondo de reparo',
  `monto_neto_facturable` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe neto final autorizado a facturar',
  `tabla_estado_registro_id` int(11) NOT NULL DEFAULT 3 COMMENT 'Id genérico: 3 Borrador, 4 En revisión, 5 Aprobado (habilita factura), 12 Facturado, 6 Eliminado, 38 Anulado',
  `usuario_aprobacion_id` int(11) DEFAULT NULL COMMENT 'Usuario técnico que aprobó la medición',
  `fecha_aprobacion` datetime DEFAULT NULL,
  `factura_proveedor_id` int(11) DEFAULT NULL COMMENT 'FK a gestion__facturas_proveedores cuando el certificado es absorbido por una factura',
  `observaciones_tecnicas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_adjunto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ruta de la memoria técnica / acta de medición / fotos',
  `usuario_creacion_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  `fecha_modificacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`certificacion_id`),
  UNIQUE KEY `uk_oc_nro_cert` (`orden_compra_id`,`nro_certificado`),
  KEY `idx_cert_empresa_estado` (`empresa_id`,`tabla_estado_registro_id`),
  KEY `idx_cert_factura` (`factura_proveedor_id`),
  KEY `idx_cert_fecha` (`empresa_id`,`fecha_medicion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificaciones de avance físico / hitos para contratos y órdenes de compra de servicios';

-- gestion__productos_presentaciones
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
  PRIMARY KEY (`presentacion_id`),
  KEY `idx_prpr_producto` (`empresa_id`,`producto_id`,`tabla_estado_registro_id`),
  KEY `idx_prpr_barras` (`empresa_id`,`codigo_barras`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Presentaciones de un producto (caja, pack, pallet) con factor de conversión a la unidad del producto';

-- ----------------------------------------------------------------------------
-- 2. Columnas nuevas en tablas existentes (21)
-- ----------------------------------------------------------------------------

-- conf__empresas.nivel_gestion
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'nivel_gestion');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `conf__empresas` ADD COLUMN `nivel_gestion` enum(''COMPLETA'',''SIMPLIFICADA'') NOT NULL DEFAULT ''COMPLETA'' COMMENT ''SIMPLIFICADA = cliente de estudio contable: solo comprobantes, IVA, contabilidad e impuestos'' AFTER `tipo_operacion`',
    'SELECT ''conf__empresas.nivel_gestion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__empresas.usa_presentaciones
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'usa_presentaciones');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `conf__empresas` ADD COLUMN `usa_presentaciones` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''1 = los productos tienen presentaciones (caja, pack, pallet) con factor y código de barras propio'' AFTER `decimales_stock`',
    'SELECT ''conf__empresas.usa_presentaciones ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- conf__paginas.requiere_gestion_completa
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__paginas' AND COLUMN_NAME = 'requiere_gestion_completa');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `conf__paginas` ADD COLUMN `requiere_gestion_completa` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = la página no se ofrece a empresas de gestión simplificada'' AFTER `requiere_bienes`',
    'SELECT ''conf__paginas.requiere_gestion_completa ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- estudio__clientes.nivel_servicio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'nivel_servicio');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `estudio__clientes` ADD COLUMN `nivel_servicio` enum(''PADRON'',''FISCAL'',''CONTABLE'',''GESTION'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''PADRON'' COMMENT ''Qué servicio recibe el cliente; define qué datos necesita el estudio'' AFTER `cliente_tipo_id`',
    'SELECT ''estudio__clientes.nivel_servicio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- estudio__clientes.origen_datos
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'origen_datos');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `estudio__clientes` ADD COLUMN `origen_datos` enum(''LOCAL'',''REMOTO'',''EXTERNO'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''LOCAL'' COMMENT ''LOCAL = empresa de este ERP; REMOTO = servidor propio del cliente; EXTERNO = software de terceros (importación de archivos)'' AFTER `empresa_vinculada_id`',
    'SELECT ''estudio__clientes.origen_datos ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- estudio__clientes.remoto_url
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__clientes' AND COLUMN_NAME = 'remoto_url');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `estudio__clientes` ADD COLUMN `remoto_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''URL base del ERP del cliente (solo REMOTO)'' AFTER `origen_datos`',
    'SELECT ''estudio__clientes.remoto_url ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__empresas_autorizaciones_copia.permite_impuestos_config
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_autorizaciones_copia' AND COLUMN_NAME = 'permite_impuestos_config');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__empresas_autorizaciones_copia` ADD COLUMN `permite_impuestos_config` tinyint(1) NOT NULL DEFAULT 1 AFTER `permite_tesoreria`',
    'SELECT ''gestion__empresas_autorizaciones_copia.permite_impuestos_config ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__empresas_autorizaciones_copia.permite_productos
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_autorizaciones_copia' AND COLUMN_NAME = 'permite_productos');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__empresas_autorizaciones_copia` ADD COLUMN `permite_productos` tinyint(1) NOT NULL DEFAULT 1 AFTER `permite_impuestos_config`',
    'SELECT ''gestion__empresas_autorizaciones_copia.permite_productos ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores.certificacion_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'certificacion_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__facturas_proveedores` ADD COLUMN `certificacion_id` int(11) DEFAULT NULL COMMENT ''FK a gestion__ordenes_compra_certificaciones'' AFTER `orden_compra_id`',
    'SELECT ''gestion__facturas_proveedores.certificacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.tipo_circuito
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'tipo_circuito');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `tipo_circuito` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''BIENES'' COMMENT ''BIENES / CONTRATO_RECURRENTE / CONTRATO_AVANCE'' AFTER `comprobante_tipo_id`',
    'SELECT ''gestion__ordenes_compra.tipo_circuito ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.es_contrato
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'es_contrato');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `es_contrato` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Tratamiento especial como contrato / servicio recurrente'' AFTER `tipo_circuito`',
    'SELECT ''gestion__ordenes_compra.es_contrato ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.contrato_nro_legal
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'contrato_nro_legal');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `contrato_nro_legal` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Número o código legal externo del contrato'' AFTER `comprobante_nro`',
    'SELECT ''gestion__ordenes_compra.contrato_nro_legal ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.fecha_inicio
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'fecha_inicio');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `fecha_inicio` date DEFAULT NULL COMMENT ''Inicio de vigencia'' AFTER `f_entrega_estimada`',
    'SELECT ''gestion__ordenes_compra.fecha_inicio ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.fecha_fin
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'fecha_fin');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `fecha_fin` date DEFAULT NULL COMMENT ''Fin de vigencia del contrato'' AFTER `fecha_inicio`',
    'SELECT ''gestion__ordenes_compra.fecha_fin ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.dias_preaviso_rescision
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'dias_preaviso_rescision');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `dias_preaviso_rescision` int(11) NOT NULL DEFAULT 30 COMMENT ''Días de anticipación para notificar no renovación'' AFTER `fecha_fin`',
    'SELECT ''gestion__ordenes_compra.dias_preaviso_rescision ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.tiene_renovacion_automatica
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'tiene_renovacion_automatica');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `tiene_renovacion_automatica` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Renovación automática si no hay preaviso'' AFTER `dias_preaviso_rescision`',
    'SELECT ''gestion__ordenes_compra.tiene_renovacion_automatica ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.requiere_certificacion_avance
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'requiere_certificacion_avance');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `requiere_certificacion_avance` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = Exige certificado aprobado para facturar'' AFTER `tiene_renovacion_automatica`',
    'SELECT ''gestion__ordenes_compra.requiere_certificacion_avance ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.pct_anticipo_financiero
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'pct_anticipo_financiero');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `pct_anticipo_financiero` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT ''% de anticipo pactado a amortizar en certificados'' AFTER `requiere_certificacion_avance`',
    'SELECT ''gestion__ordenes_compra.pct_anticipo_financiero ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.pct_fondo_reparo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'pct_fondo_reparo');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `pct_fondo_reparo` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT ''% de fondo de reparo / retención de garantía'' AFTER `pct_anticipo_financiero`',
    'SELECT ''gestion__ordenes_compra.pct_fondo_reparo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.responsable_tecnico_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'responsable_tecnico_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `responsable_tecnico_id` int(11) DEFAULT NULL COMMENT ''Usuario habilitado para aprobar certificaciones'' AFTER `pct_fondo_reparo`',
    'SELECT ''gestion__ordenes_compra.responsable_tecnico_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.archivo_contrato_adjunto
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = 'archivo_contrato_adjunto');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD COLUMN `archivo_contrato_adjunto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''Ruta del archivo PDF del contrato firmado'' AFTER `responsable_tecnico_id`',
    'SELECT ''gestion__ordenes_compra.archivo_contrato_adjunto ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ----------------------------------------------------------------------------
-- 3. Índices (4)
-- ----------------------------------------------------------------------------

-- estudio__minigestion_comprobantes.idx_duplicado
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudio__minigestion_comprobantes' AND INDEX_NAME = 'idx_duplicado');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `estudio__minigestion_comprobantes` ADD KEY `idx_duplicado` (`empresa_id`,`cliente_id`,`tipo_operacion`,`comprobante_tipo_id`,`punto_venta`,`comprobante_nro`,`cuit_contraparte`)',
    'SELECT ''estudio__minigestion_comprobantes.idx_duplicado ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__facturas_proveedores.idx_fp_certificacion
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND INDEX_NAME = 'idx_fp_certificacion');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__facturas_proveedores` ADD KEY `idx_fp_certificacion` (`certificacion_id`)',
    'SELECT ''gestion__facturas_proveedores.idx_fp_certificacion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.idx_oc_contratos_vigencia
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND INDEX_NAME = 'idx_oc_contratos_vigencia');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD KEY `idx_oc_contratos_vigencia` (`empresa_id`,`es_contrato`,`fecha_fin`)',
    'SELECT ''gestion__ordenes_compra.idx_oc_contratos_vigencia ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- gestion__ordenes_compra.idx_oc_tipo_circuito
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND INDEX_NAME = 'idx_oc_tipo_circuito');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ordenes_compra` ADD KEY `idx_oc_tipo_circuito` (`empresa_id`,`tipo_circuito`)',
    'SELECT ''gestion__ordenes_compra.idx_oc_tipo_circuito ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ----------------------------------------------------------------------------
-- 4. Claves foráneas (1)
-- ----------------------------------------------------------------------------

-- gestion__productos_proveedores_precios.fk_precios_actual_prod_prov (se agrega solo si no hay filas huérfanas)
SET @existe := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores_precios' AND CONSTRAINT_NAME = 'fk_precios_actual_prod_prov' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @huerfanos := (SELECT COUNT(*) FROM `gestion__productos_proveedores_precios` h LEFT JOIN `gestion__productos_proveedores` r ON r.`producto_proveedor_id` = h.`producto_proveedor_id` WHERE h.`producto_proveedor_id` IS NOT NULL AND r.`producto_proveedor_id` IS NULL);
SET @sql := CASE WHEN @existe > 0 THEN 'SELECT ''gestion__productos_proveedores_precios.fk_precios_actual_prod_prov ya existe'''
                 WHEN @huerfanos > 0 THEN CONCAT('SELECT ''gestion__productos_proveedores_precios.fk_precios_actual_prod_prov NO se agrego: ', @huerfanos, ' filas apuntan a gestion__productos_proveedores inexistentes''')
                 ELSE 'ALTER TABLE `gestion__productos_proveedores_precios` ADD CONSTRAINT `fk_precios_actual_prod_prov` FOREIGN KEY (`producto_proveedor_id`) REFERENCES `gestion__productos_proveedores` (`producto_proveedor_id`)' END;
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET FOREIGN_KEY_CHECKS = @fk_previo;

-- ============================================================================
-- Datos de configuración: después de este script correr (o anotar en
-- conf__migraciones según corresponda) los scripts de cada tema, que también
-- cargan páginas, funciones, permisos y catálogos:
--   20261031_migracion_casalucho_02_configuracion.sql / _06_ajustes_configuracion.sql (integraciones)
--   20261104_arca_padron_01.sql
--   20261105_estudio_clientes_origen_datos_01.sql
--   20261105_estudio_minigestion_alicuotas_01.sql
--   20261105_estudio_minigestion_liquidacion_iva_01.sql
--   20261106_estudio_clientes_nivel_servicio_01.sql
--   20261106_estudio_inspecciones_01.sql
--   20261106_nivel_gestion_empresa_01.sql / _02_paginas.sql
--   20261111_contratos_y_certificaciones_01_estructura.sql / _02_ids_estados.sql
--   20261111_iibb_liquidacion_mensual_01.sql
--   20261111_migraciones_registro_01.sql
--   20261112_productos_presentaciones_01.sql
--   20261112_autorizaciones_copia_permisos_01.sql
-- Revisar cada uno antes de correrlo: lo que ya creó este script tiene que
-- saltearse (CREATE TABLE IF NOT EXISTS / chequeo en information_schema).
--
-- FK fk_precios_actual_prod_prov: en producción hay precios que apuntan a
-- vínculos producto-proveedor borrados (57 al 2026-11-12, también lo anota
-- 20261031_migracion_casalucho_05_complemento.sql). Mientras existan, este
-- script no agrega la FK y lo avisa.
-- ============================================================================
