<?php
require_once __DIR__ . '/../../db.php';

$sql = <<<'SQL'
SET FOREIGN_KEY_CHECKS = 0;

-- 1.0. Registro de Iconos FontAwesome requeridos en conf__iconos
INSERT INTO `conf__iconos` (`icono_id`, `icono_nombre`, `icono_clase`, `tabla_estado_registro_id`) VALUES
(74, 'Balanza / Estudio FA', 'fas fa-balance-scale', 1),
(75, 'Usuario Ejecutivo FA', 'fas fa-user-tie', 1),
(76, 'Calendario Check FA', 'fas fa-calendar-check', 1),
(77, 'Engranajes FA', 'fas fa-cogs', 1),
(78, 'Factura Dolar FA', 'fas fa-file-invoice-dollar', 1),
(79, 'Recibo FA', 'fas fa-receipt', 1),
(80, 'Martillo Ley FA', 'fas fa-gavel', 1),
(81, 'Tareas FA', 'fas fa-tasks', 1),
(82, 'Reloj Usuario FA', 'fas fa-user-clock', 1),
(83, 'Calculadora FA', 'fas fa-calculator', 1),
(84, 'Buscar Dolar FA', 'fas fa-search-dollar', 1)
ON DUPLICATE KEY UPDATE 
    `icono_nombre` = VALUES(`icono_nombre`), 
    `icono_clase` = VALUES(`icono_clase`), 
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.1. Modulo en conf__modulos (modulo_id = 4)
INSERT INTO `conf__modulos` (
    `modulo_id`, `modulo`, `base_datos`, `modulo_url`, 
    `email_envio_modulo`, `layout_nombre`, `usuario_temp`, 
    `session_temp`, `imagen_id`, `depende_id`, `tabla_estado_registro_id`
)
VALUES (4, 'Estudio Contable', 'estudio__', 'estudio', 'info@developsam.com', 'default', NULL, NULL, 2, 0, 1)
ON DUPLICATE KEY UPDATE 
    `modulo` = VALUES(`modulo`),
    `base_datos` = VALUES(`base_datos`),
    `modulo_url` = VALUES(`modulo_url`),
    `email_envio_modulo` = VALUES(`email_envio_modulo`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.2. Registro de Tablas Fisicas en conf__tablas
INSERT INTO `conf__tablas` (
    `tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, 
    `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`
) VALUES
(401, 'estudio__clientes_tipos', 'Catalogo de tipos de clientes del estudio', 4, 1, 1, 1),
(402, 'estudio__clientes', 'Padron maestro de clientes del estudio contable', 4, 1, 1, 1),
(403, 'estudio__vinculaciones', 'Autorizaciones y consentimientos cross-tenant con clientes ERP', 4, 2, 1, 1),
(404, 'estudio__honorarios_conceptos', 'Catalogo de conceptos y modalidades de honorarios', 4, 1, 1, 1),
(405, 'estudio__honorarios_tarifarios', 'Tarifarios y escalas de precios de honorarios', 4, 1, 1, 1),
(406, 'estudio__honorarios_tarifarios_detalles', 'Precios unitarios por concepto dentro del tarifario', 4, 1, 1, 1),
(407, 'estudio__clientes_abonos', 'Reglas de facturacion recurrente y abonos mensuales por cliente', 4, 1, 1, 1),
(408, 'estudio__honorarios_novedades', 'Cargos eventuales o adicionales del mes a facturar', 4, 2, 1, 1),
(409, 'estudio__impuestos_obligaciones', 'Catalogo maestro de obligaciones tributarias y previsionales', 4, 1, 1, 1),
(410, 'estudio__clientes_jurisdicciones', 'Jurisdicciones provinciales y coeficientes CM05 del cliente', 4, 1, 1, 1),
(411, 'estudio__tasas_municipales', 'Tasas municipales aplicables por cliente', 4, 1, 1, 1),
(412, 'estudio__clientes_obligaciones', 'Matriz de obligaciones asignadas a cada cliente', 4, 1, 1, 1),
(413, 'estudio__calendario_vencimientos', 'Cronograma oficial de vencimientos por organismo y CUIT', 4, 1, 1, 1),
(414, 'estudio__vencimientos_generados', 'Instancias concretas de vencimientos mensuales a presentar/pagar', 4, 3, 1, 1),
(415, 'estudio__normas', 'Registro de normas legales y resoluciones de organismos fiscales', 4, 2, 1, 1),
(416, 'estudio__normas_condiciones', 'Reglas de aplicabilidad automatica de normas a clientes', 4, 1, 1, 1),
(417, 'estudio__normas_clientes_impacto', 'Bitacora de clientes alcanzados por cada norma', 4, 1, 1, 1),
(418, 'estudio__tareas_plantillas', 'Plantillas de tareas tipo para liquidaciones fiscales', 4, 1, 1, 1),
(419, 'estudio__tareas', 'Tareas operativas y flujo de trabajo con matriz RACI', 4, 3, 1, 1),
(420, 'estudio__tareas_tiempos', 'Time tracking y registro de horas dedicadas por tarea', 4, 1, 1, 1),
(421, 'estudio__licencias', 'Programacion de vacaciones y ausencias del personal', 4, 2, 1, 1),
(422, 'estudio__licencias_delegaciones', 'Delegaciones temporales de roles RACI por ausencia', 4, 2, 1, 1),
(423, 'gestion__activos_fijos', 'Padron de bienes de uso y parametros de amortizacion', 2, 3, 1, 1),
(424, 'gestion__activos_fijos_amortizaciones', 'Cuotas devengadas de amortizacion y asientos contables asociados', 2, 3, 1, 1),
(425, 'gestion__sync_cola', 'Cola de sincronizacion API cross-tenant para clientes externos', 2, 2, 1, 1)
ON DUPLICATE KEY UPDATE 
    `tabla_nombre` = VALUES(`tabla_nombre`),
    `tabla_descripcion` = VALUES(`tabla_descripcion`),
    `modulo_id` = VALUES(`modulo_id`),
    `tabla_tipo_id` = VALUES(`tabla_tipo_id`),
    `tabla_patron_id` = VALUES(`tabla_patron_id`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.3. Estados de Registros Específicos por Tabla (conf__tablas_estados_registros)
DELETE FROM `conf__tablas_estados_registros` WHERE `tabla_id` BETWEEN 401 AND 425;
INSERT INTO `conf__tablas_estados_registros` (
    `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`
) VALUES
-- estudio__clientes (402)
(402, 1, 'Activo', 1, 1, 0, 1),
(402, 2, 'Inactivo', 1, 0, 0, 2),
(402, 3, 'Suspendido', 1, 0, 0, 3),
-- estudio__vinculaciones (403)
(403, 4, 'Pendiente de Aprobacion', 1, 1, 0, 1),
(403, 5, 'Aprobada / Vinculada', 1, 0, 0, 2),
(403, 6, 'Revocada / Rechazada', 1, 0, 1, 3),
-- estudio__vencimientos_generados (414)
(414, 3, 'Borrador / Sin Iniciar', 1, 1, 0, 1),
(414, 4, 'En Liquidacion', 1, 0, 0, 2),
(414, 5, 'Listo para Presentar', 1, 0, 0, 3),
(414, 6, 'Presentado', 1, 0, 0, 4),
(414, 7, 'Pagado', 1, 0, 1, 5),
(414, 8, 'Anulado', 1, 0, 1, 6),
-- estudio__normas (415)
(415, 3, 'Borrador', 1, 1, 0, 1),
(415, 4, 'Evaluada / Pendiente Impacto', 1, 0, 0, 2),
(415, 5, 'Vigente / Impactada', 1, 0, 0, 3),
(415, 6, 'Derogada / Vencida', 1, 0, 1, 4),
-- estudio__tareas (419)
(419, 4, 'Pendiente', 1, 1, 0, 1),
(419, 5, 'En Proceso', 1, 0, 0, 2),
(419, 6, 'En Revision (A)', 1, 0, 0, 3),
(419, 7, 'Finalizada', 1, 0, 1, 4),
(419, 8, 'Cancelada', 1, 0, 1, 5),
-- estudio__licencias (421)
(421, 4, 'Programada', 1, 1, 0, 1),
(421, 5, 'En Curso / Delegada', 1, 0, 0, 2),
(421, 6, 'Finalizada / Revertida', 1, 0, 1, 3),
(421, 7, 'Cancelada', 1, 0, 1, 4),
-- gestion__activos_fijos (423)
(423, 1, 'En Servicio / Activo', 1, 1, 0, 1),
(423, 2, 'Totalmente Amortizado', 1, 0, 0, 2),
(423, 3, 'Dado de Baja / Vendido', 1, 0, 1, 3),
-- gestion__activos_fijos_amortizaciones (424)
(424, 3, 'Borrador', 1, 1, 0, 1),
(424, 5, 'Contabilizado', 1, 0, 1, 2);

-- 1.4. Arbol de Paginas en conf__paginas (modulo_id = 4)
INSERT INTO `conf__paginas` (
    `pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, 
    `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, 
    `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`
) VALUES
(400, 4, 'Estudio Contable', '#', 74, 'Modulo de Estudio Contable y Auditoria', 0, 1, 0, 1, 1, 0),
(401, 4, 'Dashboard Estudio', 'dashboard_estudio.php', 70, 'Panel de control principal del estudio contable', 400, 1, 0, 1, 1, 1),
(402, 4, 'Padron de Clientes', 'estudio_clientes.php', 75, 'Gestion y padron de clientes del estudio', 400, 2, 402, 1, 1, 0),
(403, 4, 'Tablero de Vencimientos', 'vencimientos_tablero.php', 76, 'Tablero de control de vencimientos fiscales y previsionales', 400, 3, 414, 1, 1, 0),
(404, 4, 'Generador de Vencimientos', 'vencimientos_generador.php', 77, 'Generador automatico de vencimientos por calendario', 400, 4, 413, 1, 1, 0),
(405, 4, 'Tarifarios y Honorarios', 'honorarios_conceptos.php', 78, 'Tarifarios y conceptos de honorarios profesionales', 400, 5, 405, 1, 1, 0),
(406, 4, 'Facturacion de Abonos', 'honorarios_facturacion.php', 79, 'Emision y facturacion de abonos mensuales', 400, 6, 407, 1, 1, 0),
(407, 4, 'Normas y Resoluciones', 'normas_gestion.php', 80, 'Registro de resoluciones fiscales y reglas de impacto', 400, 7, 415, 1, 1, 0),
(408, 4, 'Matriz RACI y Tareas', 'tareas_raci.php', 81, 'Flujo de trabajo de liquidaciones y matriz RACI', 400, 8, 419, 1, 1, 0),
(409, 4, 'Licencias y Delegaciones', 'licencias_gestion.php', 82, 'Gestion de ausencias y delegacion automatica de tareas', 400, 9, 421, 1, 1, 0),
(410, 4, 'Activos Fijos y Amortiz.', 'activos_amortizaciones.php', 83, 'Gestion de bienes de uso y amortizaciones contables', 400, 10, 423, 1, 1, 0),
(411, 4, 'Consola de Auditoria ERP', 'auditoria_cliente.php', 84, 'Auditoria y revision cross-tenant de clientes ERP', 400, 11, 403, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    `modulo_id` = VALUES(`modulo_id`),
    `pagina` = VALUES(`pagina`),
    `url` = VALUES(`url`),
    `icono_id` = VALUES(`icono_id`),
    `pagina_descripcion` = VALUES(`pagina_descripcion`),
    `padre_id` = VALUES(`padre_id`),
    `orden` = VALUES(`orden`),
    `tabla_id` = VALUES(`tabla_id`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.5. Habilitacion del Modulo para Empresa (empresa_id = 2)
INSERT INTO `conf__empresas_modulos` (`empresa_id`, `modulo_id`, `tabla_estado_registro_id`)
SELECT 2, 4, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `conf__empresas_modulos` WHERE `empresa_id` = 2 AND `modulo_id` = 4);

-- 1.6. Perfiles y Permisos en conf__perfiles y conf___perfiles_paginas
INSERT INTO `conf__perfiles` (`perfil_id`, `modulo_id`, `perfil_nombre`, `tabla_estado_registro_id`) VALUES
(401, 4, 'Administrador Estudio Contable', 1),
(402, 4, 'Contador / Liquidador', 1),
(403, 4, 'Auditor Fiscal', 1)
ON DUPLICATE KEY UPDATE 
    `perfil_nombre` = VALUES(`perfil_nombre`), 
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- Asignar las paginas del modulo 4 a Perfil 1 (Superadmin General) y Perfil 401 (Admin Estudio)
INSERT IGNORE INTO `conf___perfiles_paginas` (`perfil_id`, `pagina_id`, `estado_registro_id`)
SELECT 1, `pagina_id`, 1 FROM `conf__paginas` WHERE `modulo_id` = 4;

INSERT IGNORE INTO `conf___perfiles_paginas` (`perfil_id`, `pagina_id`, `estado_registro_id`)
SELECT 401, `pagina_id`, 1 FROM `conf__paginas` WHERE `modulo_id` = 4;

-- =============================================================================
-- SECCION 2: TABLAS DEL SUBSISTEMA CLIENTES Y VINCULACION CROSS-TENANT
-- =============================================================================

CREATE TABLE IF NOT EXISTS `estudio__clientes_tipos` (
    `cliente_tipo_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__clientes` (
    `cliente_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Tenant Estudio Contable propietario',
    `cliente_tipo_id` INT UNSIGNED NOT NULL,
    `razon_social` VARCHAR(150) NOT NULL,
    `cuit` VARCHAR(13) NOT NULL,
    `terminacion_cuit` TINYINT NOT NULL COMMENT 'Calculado 0-9 para calendarios fiscales',
    `condicion_iva_id` INT UNSIGNED NOT NULL COMMENT 'FK gestion__condiciones_fiscales',
    `empresa_vinculada_id` INT UNSIGNED NULL COMMENT 'FK conf__empresas si el cliente usa el ERP',
    `mes_cierre_ejercicio` TINYINT NOT NULL DEFAULT 12,
    `ingresos_brutos_tipo` ENUM('LOCAL', 'CONVENIO_MULTILATERAL', 'EXENTO') NOT NULL DEFAULT 'LOCAL',
    `jurisdiccion_sede_id` INT UNSIGNED NULL COMMENT 'FK gestion__jurisdicciones sede',
    `es_empleador` TINYINT(1) NOT NULL DEFAULT 0,
    `cantidad_empleados` INT UNSIGNED NOT NULL DEFAULT 0,
    `usuario_r_defecto_id` INT UNSIGNED NULL COMMENT 'Usuario Responsable asignado en conf__usuarios',
    `usuario_a_defecto_id` INT UNSIGNED NULL COMMENT 'Usuario Accountable/Supervisor en conf__usuarios',
    `observaciones` TEXT NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_empresa_cuit` (`empresa_id`, `cuit`),
    KEY `idx_empresa_terminacion` (`empresa_id`, `terminacion_cuit`),
    KEY `idx_empresa_vinculada` (`empresa_vinculada_id`),
    KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__vinculaciones` (
    `vinculacion_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_origen_id` INT UNSIGNED NOT NULL COMMENT 'Empresa cliente que otorga el acceso',
    `empresa_estudio_id` INT UNSIGNED NOT NULL COMMENT 'Empresa del estudio contable que recibe acceso',
    `token_autorizacion` VARCHAR(64) NULL,
    `f_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `f_aprobacion` DATETIME NULL,
    `usuario_aprobador_id` INT UNSIGNED NULL COMMENT 'Usuario administrador de la empresa origen',
    `permiso_ventas` TINYINT(1) NOT NULL DEFAULT 1,
    `permiso_compras` TINYINT(1) NOT NULL DEFAULT 1,
    `permiso_tesoreria` TINYINT(1) NOT NULL DEFAULT 1,
    `permiso_contabilidad` TINYINT(1) NOT NULL DEFAULT 1,
    `permiso_stock` TINYINT(1) NOT NULL DEFAULT 0,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 4,
    UNIQUE KEY `uk_vinculacion` (`empresa_origen_id`, `empresa_estudio_id`),
    KEY `idx_estudio` (`empresa_estudio_id`),
    KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECCION 3: TARIFARIOS, HONORARIOS Y FACTURACION RECURRENTE
-- =============================================================================

CREATE TABLE IF NOT EXISTS `estudio__honorarios_conceptos` (
    `concepto_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `producto_id` INT UNSIGNED NOT NULL COMMENT 'FK gestion__productos (servicio base para ventas_facturas)',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `tipo_cargo` ENUM('FIJO_MENSUAL', 'POR_COMPROBANTE', 'POR_LEGAJO', 'POR_HORA', 'EVENTUAL') NOT NULL DEFAULT 'FIJO_MENSUAL',
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_empresa_producto` (`empresa_id`, `producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__honorarios_tarifarios` (
    `tarifario_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `moneda_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK gestion__monedas',
    `vigencia_desde` DATE NOT NULL,
    `vigencia_hasta` DATE NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    KEY `idx_empresa_vigencia` (`empresa_id`, `vigencia_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__honorarios_tarifarios_detalles` (
    `tarifario_detalle_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarifario_id` BIGINT UNSIGNED NOT NULL,
    `concepto_id` BIGINT UNSIGNED NOT NULL,
    `importe_unitario` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `unidad_medida` VARCHAR(30) NULL COMMENT 'ARS, Modulos CPCECABA, Modulos FACPCE',
    UNIQUE KEY `uk_tarifario_concepto` (`tarifario_id`, `concepto_id`),
    KEY `idx_concepto` (`concepto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__clientes_abonos` (
    `abono_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Estudio Contable',
    `cliente_id` BIGINT UNSIGNED NOT NULL,
    `concepto_id` BIGINT UNSIGNED NOT NULL,
    `punto_venta_id` INT UNSIGNED NOT NULL COMMENT 'FK gestion__puntos_venta del estudio',
    `condicion_venta_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK gestion__condiciones_pago',
    `importe_base` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `dia_facturacion` TINYINT NOT NULL DEFAULT 1,
    `aplica_adicionales_automaticos` TINYINT(1) NOT NULL DEFAULT 0,
    `tope_comprobantes_incluidos` INT UNSIGNED NOT NULL DEFAULT 0,
    `precio_excedente_comprobante` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tope_legajos_incluidos` INT UNSIGNED NOT NULL DEFAULT 0,
    `precio_excedente_legajo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `ultimo_periodo_facturado` INT UNSIGNED NULL COMMENT 'YYYYMM',
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    KEY `idx_empresa_cliente` (`empresa_id`, `cliente_id`),
    KEY `idx_concepto` (`concepto_id`),
    KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__honorarios_novedades` (
    `novedad_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `cliente_id` BIGINT UNSIGNED NOT NULL,
    `concepto_id` BIGINT UNSIGNED NOT NULL,
    `periodo` INT UNSIGNED NOT NULL COMMENT 'YYYYMM a facturar',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `importe_unitario` DECIMAL(14,2) NOT NULL,
    `importe_total` DECIMAL(14,2) NOT NULL,
    `descripcion` VARCHAR(255) NOT NULL,
    `facturado` TINYINT(1) NOT NULL DEFAULT 0,
    `venta_factura_id` INT UNSIGNED NULL COMMENT 'FK gestion__ventas_facturas',
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    KEY `idx_empresa_periodo` (`empresa_id`, `periodo`, `cliente_id`),
    KEY `idx_facturado` (`facturado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECCION 4: MOTOR MULTIJURISDICCIONAL Y CALENDARIOS FISCALES
-- =============================================================================

CREATE TABLE IF NOT EXISTS `estudio__impuestos_obligaciones` (
    `impuesto_obligacion_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organismo` ENUM('ARCA', 'COMARB', 'PROVINCIAL', 'MUNICIPAL', 'SINDICATO', 'OTRO') NOT NULL,
    `codigo_oficial` VARCHAR(20) NOT NULL COMMENT 'Ej: IVA, F931, CM03, CM05, ARBA_LOC',
    `nombre` VARCHAR(150) NOT NULL,
    `frecuencia` ENUM('MENSUAL', 'BIMESTRAL', 'CUATRIMESTRAL', 'ANUAL', 'EVENTUAL') NOT NULL DEFAULT 'MENSUAL',
    `aplica_por_cuit` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si rige segun terminacion CUIT 0-9',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_codigo_organismo` (`organismo`, `codigo_oficial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__clientes_jurisdicciones` (
    `cliente_jurisdiccion_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `cliente_id` BIGINT UNSIGNED NOT NULL,
    `jurisdiccion_id` INT UNSIGNED NOT NULL COMMENT 'FK gestion__jurisdicciones (1 AFIP, 2 ARBA, 3 AGIP, etc.)',
    `tipo_inscripcion` ENUM('SEDE_PRINCIPAL', 'EXTRAÑA_JURISDICCION', 'REGIMEN_SIMPLIFICADO') NOT NULL DEFAULT 'EXTRAÑA_JURISDICCION',
    `numero_inscripcion` VARCHAR(30) NULL,
    `coeficiente_cm05` DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    `es_agente_retencion` TINYINT(1) NOT NULL DEFAULT 0,
    `es_agente_percepcion` TINYINT(1) NOT NULL DEFAULT 0,
    `f_alta` DATE NOT NULL,
    `f_baja` DATE NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_cliente_jurisdiccion` (`cliente_id`, `jurisdiccion_id`),
    KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__tasas_municipales` (
    `tasa_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `cliente_id` BIGINT UNSIGNED NOT NULL,
    `municipio_nombre` VARCHAR(100) NOT NULL,
    `tasa_nombre` VARCHAR(100) NOT NULL COMMENT 'Seguridad e Higiene, Red Vial, etc.',
    `nro_cuenta_partida` VARCHAR(50) NOT NULL,
    `dia_vencimiento_fijo` TINYINT NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    KEY `idx_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__clientes_obligaciones` (
    `cliente_obligacion_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `cliente_id` BIGINT UNSIGNED NOT NULL,
    `impuesto_obligacion_id` INT UNSIGNED NOT NULL,
    `jurisdiccion_id` INT UNSIGNED NULL COMMENT 'NULL si es ARCA nacional',
    `cbu_pago` VARCHAR(22) NULL,
    `metodo_pago_defecto` ENUM('VEP', 'DEBITO_DIRECTO', 'BANCO', 'EFECTIVO') NOT NULL DEFAULT 'VEP',
    `observaciones` VARCHAR(255) NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_cli_obliga_juris` (`cliente_id`, `impuesto_obligacion_id`, `jurisdiccion_id`),
    KEY `idx_empresa` (`empresa_id`),
    KEY `idx_obligacion` (`impuesto_obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__calendario_vencimientos` (
    `calendario_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `impuesto_obligacion_id` INT UNSIGNED NOT NULL,
    `jurisdiccion_id` INT UNSIGNED NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `mes_periodo` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    `terminacion_cuit` TINYINT NOT NULL COMMENT '0 a 9 (o -1 si es general sin distincion)',
    `f_vencimiento` DATE NOT NULL,
    `f_vencimiento_prorroga` DATE NULL,
    KEY `idx_busqueda_calendario` (`impuesto_obligacion_id`, `jurisdiccion_id`, `anio`, `mes_periodo`, `terminacion_cuit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLA DE PROCESO LARGO: estudio__vencimientos_generados
CREATE TABLE IF NOT EXISTS `estudio__vencimientos_generados` (
    `vencimiento_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Campo de Proceso 1: Tenant Estudio Contable',
    `sucursal_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2: Sucursal del Estudio',
    `comprobante_tipo_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo doc (0 si no emite comprobante fiscal)',
    `comprobante_pv` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta',
    `comprobante_nro` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo',
    `comprobante_id` BIGINT UNSIGNED NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes (VEP/Liq)',
    `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Campo de Proceso 7: FK gestion__entidades (Cliente)',
    `f_emision` DATE NOT NULL COMMENT 'Campo de Proceso 8: Fecha de alta/generacion',
    `cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK estudio__clientes',
    `impuesto_obligacion_id` INT UNSIGNED NOT NULL,
    `jurisdiccion_id` INT UNSIGNED NULL,
    `periodo_fiscal` INT UNSIGNED NOT NULL COMMENT 'YYYYMM',
    `f_limite` DATE NOT NULL,
    `importe_determinado` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `importe_saldo_favor` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `importe_a_pagar` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `vep_nro` VARCHAR(30) NULL,
    `f_presentacion` DATETIME NULL,
    `f_pago` DATETIME NULL,
    `comprobante_pago_adjunto` VARCHAR(255) NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 3,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_empresa_periodo` (`empresa_id`, `periodo_fiscal`, `cliente_id`),
    KEY `idx_f_limite` (`f_limite`),
    KEY `idx_estado` (`tabla_estado_registro_id`),
    KEY `idx_comprobante` (`comprobante_id`),
    KEY `idx_entidad` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECCION 5: MOTOR DE NORMAS LEGALES Y REGLAS DE IMPACTO
-- =============================================================================

CREATE TABLE IF NOT EXISTS `estudio__normas` (
    `norma_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `organismo_emisor` VARCHAR(50) NOT NULL COMMENT 'ARCA, ARBA, AGIP, COMARB, MTSS, UIF',
    `tipo_norma` ENUM('RESOLUCION_GENERAL', 'DECRETO', 'LEY', 'DISPOSICION', 'CIRCULAR') NOT NULL DEFAULT 'RESOLUCION_GENERAL',
    `nro_norma` VARCHAR(30) NOT NULL,
    `f_boletin_oficial` DATE NOT NULL,
    `vigencia_desde` DATE NOT NULL,
    `vigencia_hasta` DATE NULL,
    `titulo` VARCHAR(200) NOT NULL,
    `resumen_tecnico` TEXT NOT NULL,
    `genera_nueva_obligacion` TINYINT(1) NOT NULL DEFAULT 0,
    `impuesto_obligacion_id` INT UNSIGNED NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 3,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_empresa_vigencia` (`empresa_id`, `vigencia_desde`),
    KEY `idx_organismo` (`organismo_emisor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__normas_condiciones` (
    `condicion_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `norma_id` BIGINT UNSIGNED NOT NULL,
    `parametro` ENUM('CONDICION_IVA', 'TIPO_PERSONA', 'JURISDICCION', 'ACTIVIDAD_AFIP', 'EMPLEADOR', 'VENTAS_ANUALES_MAYOR_A') NOT NULL,
    `operador` ENUM('=', '!=', 'IN', '>=', '<=') NOT NULL DEFAULT '=',
    `valor_esperado` VARCHAR(255) NOT NULL,
    KEY `idx_norma` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__normas_clientes_impacto` (
    `impacto_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `norma_id` BIGINT UNSIGNED NOT NULL,
    `cliente_id` BIGINT UNSIGNED NOT NULL,
    `f_evaluacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado_impacto` ENUM('ALCANZADO', 'EXENTO', 'NOTIFICADO', 'APLICADO') NOT NULL DEFAULT 'ALCANZADO',
    `tarea_generada_id` BIGINT UNSIGNED NULL,
    UNIQUE KEY `uk_norma_cliente` (`norma_id`, `cliente_id`),
    KEY `idx_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECCION 6: MATRIZ RACI, TIME TRACKING, LICENCIAS Y DELEGACIONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS `estudio__tareas_plantillas` (
    `plantilla_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `impuesto_obligacion_id` INT UNSIGNED NOT NULL,
    `nombre_tarea` VARCHAR(150) NOT NULL,
    `orden` TINYINT NOT NULL DEFAULT 1,
    `dias_previos_vto` TINYINT NOT NULL DEFAULT 5,
    `horas_estimadas` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    KEY `idx_empresa_obligacion` (`empresa_id`, `impuesto_obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLA DE PROCESO LARGO: estudio__tareas
CREATE TABLE IF NOT EXISTS `estudio__tareas` (
    `tarea_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Campo de Proceso 1: Tenant Estudio',
    `sucursal_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2: Sucursal',
    `comprobante_tipo_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo comprobante',
    `comprobante_pv` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta',
    `comprobante_nro` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo',
    `comprobante_id` BIGINT UNSIGNED NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes',
    `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Campo de Proceso 7: FK gestion__entidades (Cliente)',
    `f_emision` DATE NOT NULL COMMENT 'Campo de Proceso 8: Fecha creacion tarea',
    `cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK estudio__clientes',
    `vencimiento_id` BIGINT UNSIGNED NULL COMMENT 'FK estudio__vencimientos_generados si aplica',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `f_inicio` DATE NOT NULL,
    `f_limite` DATE NOT NULL,
    `usuario_r_id` INT NOT NULL COMMENT 'Responsible (conf__usuarios.usuario_id)',
    `usuario_a_id` INT NOT NULL COMMENT 'Accountable (conf__usuarios.usuario_id)',
    `contactos_c` VARCHAR(255) NULL COMMENT 'Consulted',
    `contactos_i` VARCHAR(255) NULL COMMENT 'Informed',
    `horas_estimadas` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `horas_reales` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 4,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_empresa_r` (`empresa_id`, `usuario_r_id`),
    KEY `idx_empresa_a` (`empresa_id`, `usuario_a_id`),
    KEY `idx_cliente_vto` (`cliente_id`, `f_limite`),
    KEY `idx_vencimiento` (`vencimiento_id`),
    KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__tareas_tiempos` (
    `tiempo_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `usuario_id` INT NOT NULL COMMENT 'FK conf__usuarios.usuario_id',
    `f_registro` DATE NOT NULL,
    `minutos` INT UNSIGNED NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tarea` (`tarea_id`),
    KEY `idx_empresa_usuario` (`empresa_id`, `usuario_id`, `f_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__licencias` (
    `licencia_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `usuario_id` INT NOT NULL COMMENT 'Colaborador titular que se ausenta (conf__usuarios.usuario_id)',
    `tipo_licencia` ENUM('VACACIONES', 'MEDICA', 'ESTUDIO', 'OTRA') NOT NULL DEFAULT 'VACACIONES',
    `f_desde` DATE NOT NULL,
    `f_hasta` DATE NOT NULL,
    `usuario_suplente_r_id` INT NULL COMMENT 'Suplente por defecto para tareas R',
    `usuario_suplente_a_id` INT NULL COMMENT 'Suplente por defecto para tareas A',
    `observaciones` TEXT NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 4,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_empresa_fechas` (`empresa_id`, `f_desde`, `f_hasta`),
    KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__licencias_delegaciones` (
    `delegacion_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL,
    `licencia_id` BIGINT UNSIGNED NOT NULL,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `rol_delegado` ENUM('R', 'A') NOT NULL,
    `usuario_titular_id` INT NOT NULL,
    `usuario_suplente_id` INT NOT NULL,
    `f_traspaso` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Vigente, 2=Revertida',
    KEY `idx_tarea` (`tarea_id`),
    KEY `idx_suplente` (`usuario_suplente_id`),
    KEY `idx_licencia` (`licencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECCION 7: MINI-GESTION Y ACTIVOS FIJOS (AMORTIZACIONES)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `gestion__activos_fijos` (
    `activo_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Campo de Proceso 1',
    `sucursal_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2',
    `comprobante_tipo_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo comprobante origen',
    `comprobante_pv` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta origen',
    `comprobante_nro` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo origen',
    `comprobante_id` BIGINT UNSIGNED NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes (Factura de compra)',
    `entidad_id` INT UNSIGNED NOT NULL COMMENT 'Campo de Proceso 7: FK gestion__entidades (Proveedor)',
    `f_emision` DATE NOT NULL COMMENT 'Campo de Proceso 8: Fecha de alta / incorporacion',
    `codigo` VARCHAR(30) NOT NULL,
    `descripcion` VARCHAR(150) NOT NULL,
    `categoria_activo` ENUM('INMUEBLE', 'RODADO', 'MAQUINARIA', 'MUEBLES_UTILES', 'EQUIPO_COMPUTACION', 'INSTALACION', 'OTRO') NOT NULL,
    `f_adquisicion` DATE NOT NULL,
    `valor_origen` DECIMAL(16,2) NOT NULL,
    `valor_residual` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    `vida_util_meses` INT UNSIGNED NOT NULL,
    `meses_amortizados` INT UNSIGNED NOT NULL DEFAULT 0,
    `metodo_amortizacion` ENUM('LINEAL_MENSUAL', 'LINEAL_ANUAL') NOT NULL DEFAULT 'LINEAL_MENSUAL',
    `cuenta_activo_id` INT UNSIGNED NOT NULL COMMENT 'FK gestion__cont_cuentas.cont_cuenta_id (Bienes de Uso)',
    `cuenta_amort_acum_id` INT UNSIGNED NOT NULL COMMENT 'FK gestion__cont_cuentas.cont_cuenta_id (Regularizadora Activo)',
    `cuenta_gasto_amort_id` INT UNSIGNED NOT NULL COMMENT 'FK gestion__cont_cuentas.cont_cuenta_id (Resultado Negativo)',
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_empresa_codigo` (`empresa_id`, `codigo`),
    KEY `idx_comprobante` (`comprobante_id`),
    KEY `idx_cuentas` (`cuenta_activo_id`, `cuenta_amort_acum_id`, `cuenta_gasto_amort_id`),
    KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__activos_fijos_amortizaciones` (
    `amortizacion_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Campo de Proceso 1',
    `sucursal_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Campo de Proceso 2',
    `comprobante_tipo_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 3: Tipo comprobante',
    `comprobante_pv` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 4: Punto de venta',
    `comprobante_nro` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 5: Correlativo',
    `comprobante_id` BIGINT UNSIGNED NULL COMMENT 'Campo de Proceso 6: FK gestion__comprobantes',
    `entidad_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Campo de Proceso 7: Entidad',
    `f_emision` DATE NOT NULL COMMENT 'Campo de Proceso 8: Fecha de cálculo',
    `activo_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK gestion__activos_fijos',
    `periodo` INT UNSIGNED NOT NULL COMMENT 'YYYYMM devengado',
    `cuota_nro` INT UNSIGNED NOT NULL,
    `importe_amortizacion` DECIMAL(14,2) NOT NULL,
    `asiento_id` INT NULL COMMENT 'FK gestion__cont_asientos.cont_asiento_id (generado con origen AUTO)',
    `f_asiento` DATE NOT NULL,
    `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 3,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_empresa_periodo` (`empresa_id`, `periodo`, `activo_id`),
    KEY `idx_activo` (`activo_id`),
    KEY `idx_asiento` (`asiento_id`),
    KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECCION 8: COLA DE SINCRONIZACION API PARA CLIENTES EXTERNOS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `gestion__sync_cola` (
    `sync_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Tenant emisor',
    `tabla_origen` VARCHAR(60) NOT NULL COMMENT 'gestion__comprobantes, gestion__cont_asientos',
    `registro_id` BIGINT UNSIGNED NOT NULL,
    `accion` ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL DEFAULT 'INSERT',
    `payload_json` MEDIUMTEXT NULL,
    `estado_envio` ENUM('PENDIENTE', 'ENVIADO', 'ERROR', 'REINTENTANDO') NOT NULL DEFAULT 'PENDIENTE',
    `reintentos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ultimo_error` TEXT NULL,
    `f_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `f_actualizacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_estado_envio` (`estado_envio`, `reintentos`),
    KEY `idx_empresa_tabla` (`empresa_id`, `tabla_origen`, `registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
SQL;

echo "Iniciando ejecucion de SQL ajustado en la base de datos " . DB_NAME . "...\n";

if (mysqli_multi_query($conn, $sql)) {
    $i = 0;
    do {
        $i++;
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
        if (mysqli_errno($conn)) {
            echo "Error en bloque $i: " . mysqli_error($conn) . "\n";
            exit(1);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    echo "OK: Ejecucion completa de todos los bloques SQL sin errores ($i operaciones procesadas).\n";
} else {
    echo "ERROR general al iniciar multi_query: " . mysqli_error($conn) . "\n";
    exit(1);
}
