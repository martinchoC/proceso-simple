-- ==============================================================================
-- 20261011_sindicatos_07_ddl_fase4.sql
-- Fase 4 Módulo 6 (Sindicatos): Fiscalización, Actas, Planes de Pago y Procesos
-- Especificación Técnica (§2.5, §5.5, §6, §7.1, §7.2, §9, §10)
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Tablas de Negocio: Actas de Inspección (§5.5)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sindi__actas` (
    `acta_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `empleador_id` INT UNSIGNED NOT NULL,
    `numero_acta` VARCHAR(30) NOT NULL COMMENT 'Número identificador del acta (ej. ACT-2026-0001)',
    `tipo_acta` ENUM('NORMAL', 'LIBRE_DEUDA', 'COMPLEMENTARIA') NOT NULL DEFAULT 'NORMAL',
    `inspector_usuario_id` INT NULL COMMENT 'Usuario inspector asignado',
    `f_inicio` DATE NOT NULL COMMENT 'Fecha de apertura/inicio de la inspección',
    `f_cierre` DATE NULL COMMENT 'Fecha de cierre / emisión',
    `f_notificacion` DATE NULL COMMENT 'Fecha de notificación al empleador',
    `f_vencimiento_72h` DATETIME NULL COMMENT 'Plazo de 72 hs hábiles para descargo / regularización',
    `total_declarado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_determinado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_pagado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_diferencia` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Diferencia neta de capital',
    `total_intereses` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Intereses liquidados a la fecha del acta',
    `total_acta` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Total exigible (diferencia + intereses)',
    `resolucion_tipo` ENUM('PENDIENTE', 'CONTADO', 'PLAN') NOT NULL DEFAULT 'PENDIENTE',
    `plan_id` INT UNSIGNED NULL COMMENT 'Vínculo al plan si se refinancia',
    `comprobante_id` BIGINT UNSIGNED NULL COMMENT 'Vínculo al comprobante tipo ACT en gestion__comprobantes',
    `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 3 COMMENT '3 Borrador, 18 En curso, 5 Confirmado, 40 Cancelado total, 38 Anulado',
    `observaciones` TEXT NULL,
    `usuario_creacion_id` INT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `usuario_actualizacion_id` INT NULL,
    `fecha_actualizacion` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`acta_id`),
    UNIQUE KEY `uk_acta_numero` (`empresa_id`, `numero_acta`),
    KEY `idx_acta_empleador` (`empresa_id`, `empleador_id`, `tabla_estado_registro_id`),
    KEY `idx_acta_fechas` (`empresa_id`, `f_inicio`, `f_vencimiento_72h`),
    KEY `idx_acta_comprobante` (`comprobante_id`),
    CONSTRAINT `fk_actas_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
    CONSTRAINT `fk_actas_empleador` FOREIGN KEY (`empleador_id`) REFERENCES `sindi__empleadores` (`empleador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actas de fiscalización e inspección sindical';

CREATE TABLE IF NOT EXISTS `sindi__actas_periodos` (
    `acta_periodo_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `acta_id` INT UNSIGNED NOT NULL,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `periodo` INT NOT NULL COMMENT 'Período inspeccionado AAAAMM',
    `f_vto_original` DATE NULL,
    `total_declarado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_determinado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_pagado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `saldo_capital` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `intereses` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_periodo` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `observaciones` VARCHAR(255) NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`acta_periodo_id`),
    UNIQUE KEY `uk_acta_periodo` (`acta_id`, `periodo`),
    KEY `idx_acta_per_empresa` (`empresa_id`, `periodo`),
    CONSTRAINT `fk_acta_per_acta` FOREIGN KEY (`acta_id`) REFERENCES `sindi__actas` (`acta_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Períodos comprendidos en un acta de inspección';

CREATE TABLE IF NOT EXISTS `sindi__actas_items` (
    `acta_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `acta_periodo_id` INT UNSIGNED NOT NULL,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `concepto_id` INT UNSIGNED NOT NULL,
    `importe_declarado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `importe_determinado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `importe_pagado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `diferencia_capital` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `intereses` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_item` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `comprobante_id` BIGINT UNSIGNED NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`acta_item_id`),
    UNIQUE KEY `uk_acta_item_concepto` (`acta_periodo_id`, `concepto_id`),
    KEY `idx_acta_item_concepto` (`concepto_id`),
    CONSTRAINT `fk_acta_item_periodo` FOREIGN KEY (`acta_periodo_id`) REFERENCES `sindi__actas_periodos` (`acta_periodo_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_acta_item_concepto` FOREIGN KEY (`concepto_id`) REFERENCES `sindi__conceptos` (`concepto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle por concepto de los períodos de un acta';

-- ------------------------------------------------------------------------------
-- 2. Tablas de Negocio: Planes de Pago (§5.5)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sindi__planes_tipos` (
    `plan_tipo_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `codigo` VARCHAR(20) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `sistema` ENUM('DIRECTO', 'FRANCES', 'ALEMAN') NOT NULL DEFAULT 'DIRECTO',
    `cuotas_max` INT NOT NULL DEFAULT 12,
    `tasa_mensual` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `anticipo_min_porc` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `f_desde` DATE NOT NULL,
    `f_hasta` DATE NULL,
    `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`plan_tipo_id`),
    UNIQUE KEY `uk_plan_tipo_codigo` (`empresa_id`, `codigo`),
    CONSTRAINT `fk_pt_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de planes de pago y condiciones financieras';

CREATE TABLE IF NOT EXISTS `sindi__planes` (
    `plan_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `numero_plan` VARCHAR(30) NOT NULL COMMENT 'Identificador único del plan (ej. PLAN-2026-0001)',
    `empleador_id` INT UNSIGNED NOT NULL,
    `plan_tipo_id` INT UNSIGNED NOT NULL,
    `origen_tipo` ENUM('DEUDA_DDJJ', 'ACTA', 'MIXTO') NOT NULL DEFAULT 'DEUDA_DDJJ',
    `acta_id` INT UNSIGNED NULL COMMENT 'Acta asociada si el plan proviene de una inspección',
    `capital_original` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Deuda base consolidada',
    `interes_financiero` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Interés pactado en la financiación',
    `total_financiado` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Capital + Interés',
    `anticipo_importe` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `cantidad_cuotas` INT NOT NULL DEFAULT 1,
    `f_acuerdo` DATE NOT NULL,
    `f_primer_vto` DATE NOT NULL,
    `periodicidad_dias` INT NOT NULL DEFAULT 30,
    `saldo_pendiente` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 3 COMMENT '3 Borrador, 5 Confirmado, 39 Canc. Parcial, 40 Canc. Total, 38 Anulado',
    `observaciones` TEXT NULL,
    `usuario_creacion_id` INT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `usuario_actualizacion_id` INT NULL,
    `fecha_actualizacion` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`plan_id`),
    UNIQUE KEY `uk_plan_numero` (`empresa_id`, `numero_plan`),
    KEY `idx_plan_empleador` (`empresa_id`, `empleador_id`, `tabla_estado_registro_id`),
    KEY `idx_plan_acta` (`acta_id`),
    CONSTRAINT `fk_planes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
    CONSTRAINT `fk_planes_empleador` FOREIGN KEY (`empleador_id`) REFERENCES `sindi__empleadores` (`empleador_id`),
    CONSTRAINT `fk_planes_tipo` FOREIGN KEY (`plan_tipo_id`) REFERENCES `sindi__planes_tipos` (`plan_tipo_id`),
    CONSTRAINT `fk_planes_acta` FOREIGN KEY (`acta_id`) REFERENCES `sindi__actas` (`acta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Acuerdos de planes de pago y facilidades';

CREATE TABLE IF NOT EXISTS `sindi__planes_cuotas` (
    `plan_cuota_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plan_id` INT UNSIGNED NOT NULL,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `numero_cuota` INT NOT NULL COMMENT '0 anticipo, 1..N cuotas',
    `f_vto` DATE NOT NULL,
    `capital` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `interes` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `total_cuota` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `saldo_pendiente` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `comprobante_id` BIGINT UNSIGNED NULL COMMENT 'Comprobante formal tipo PLC en gestion__comprobantes',
    `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 3 COMMENT '3 Borrador, 5 Pendiente/Emitido, 40 Cancelado total',
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`plan_cuota_id`),
    UNIQUE KEY `uk_plan_cuota` (`plan_id`, `numero_cuota`),
    KEY `idx_plan_cuota_vto` (`empresa_id`, `f_vto`, `tabla_estado_registro_id`),
    KEY `idx_plan_cuota_comp` (`comprobante_id`),
    CONSTRAINT `fk_pc_plan` FOREIGN KEY (`plan_id`) REFERENCES `sindi__planes` (`plan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuotas individuales de planes de pago';

-- ------------------------------------------------------------------------------
-- 3. Registro de Tablas en conf__tablas (534 a 539)
-- ------------------------------------------------------------------------------

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`) VALUES
(534, 'sindi__actas',           'Actas de fiscalización e inspección sindical',      6, 1, 1, 1),
(535, 'sindi__actas_periodos',  'Períodos inspeccionados de actas sindicales',       6, 1, 1, 1),
(536, 'sindi__actas_items',     'Detalle de conceptos inspeccionados en actas',      6, 1, 1, 1),
(537, 'sindi__planes_tipos',    'Tipos y condiciones de planes de pago',             6, 1, 1, 1),
(538, 'sindi__planes',          'Planes de pago acordados con empleadores',          6, 1, 1, 1),
(539, 'sindi__planes_cuotas',   'Cuotas de planes de pago sindicales',               6, 1, 1, 1)
ON DUPLICATE KEY UPDATE
  `tabla_nombre` = VALUES(`tabla_nombre`),
  `tabla_descripcion` = VALUES(`tabla_descripcion`),
  `modulo_id` = VALUES(`modulo_id`);

-- ------------------------------------------------------------------------------
-- 4. Estados para Actas y Planes en conf__tablas_estados_registros
-- ------------------------------------------------------------------------------

-- Estados para sindi__actas (tabla_id = 534)
INSERT INTO `conf__tablas_estados_registros` (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`) VALUES
(534, 3,  'Borrador',           4, 1, 0, 1),
(534, 18, 'En Curso',           1, 0, 0, 2),
(534, 5,  'Confirmado',         2, 0, 0, 3),
(534, 40, 'Cancelado Total',    2, 0, 1, 4),
(534, 38, 'Anulado',            3, 0, 1, 5)
ON DUPLICATE KEY UPDATE tabla_estado_registro = VALUES(tabla_estado_registro), color_id = VALUES(color_id);

-- Estados para sindi__planes (tabla_id = 538)
INSERT INTO `conf__tablas_estados_registros` (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`) VALUES
(538, 3,  'Borrador',           4, 1, 0, 1),
(538, 5,  'Confirmado',         1, 0, 0, 2),
(538, 39, 'Cancelado Parcial',  1, 0, 0, 3),
(538, 40, 'Cancelado Total',    2, 0, 1, 4),
(538, 38, 'Anulado',            3, 0, 1, 5)
ON DUPLICATE KEY UPDATE tabla_estado_registro = VALUES(tabla_estado_registro), color_id = VALUES(color_id);

-- ------------------------------------------------------------------------------
-- 5. Tipos de Comprobante para Actas y Planes (§7.1)
-- ------------------------------------------------------------------------------

-- Tipo 308: Acta Determinada Sindical (ACT) - signo +1, contable, ctacte
INSERT INTO `gestion__comprobantes_tipos` (
    `comprobante_tipo_id`, `empresa_id`, `comprobante_grupo_id`, `comprobante_subgrupo_id`,
    `comprobante_fiscal_id`, `impacta_stock`, `stock_signo`, `impacta_contabilidad`,
    `impacta_ctacte`, `requiere_debito_cuenta`, `comprobante_tipo`, `codigo`, `letra`,
    `signo`, `comentario`, `orden`, `tabla_estado_registro_id`
) VALUES (
    308, 3, 31, 301, 0, 0, NULL, 1, 1, 0,
    'Acta Determinada Sindical', 'ACT', 'X', '+',
    'Acta de determinación de deuda por fiscalización sindical', 8, 1
) ON DUPLICATE KEY UPDATE comprobante_tipo = VALUES(comprobante_tipo);

-- Tipo 309: Cuota Plan de Pago (PLC) - signo +1, no contable (reclasifica en plan), ctacte
INSERT INTO `gestion__comprobantes_tipos` (
    `comprobante_tipo_id`, `empresa_id`, `comprobante_grupo_id`, `comprobante_subgrupo_id`,
    `comprobante_fiscal_id`, `impacta_stock`, `stock_signo`, `impacta_contabilidad`,
    `impacta_ctacte`, `requiere_debito_cuenta`, `comprobante_tipo`, `codigo`, `letra`,
    `signo`, `comentario`, `orden`, `tabla_estado_registro_id`
) VALUES (
    309, 3, 31, 301, 0, 0, NULL, 0, 1, 0,
    'Cuota Plan de Pago Sindical', 'PLC', 'X', '+',
    'Cuota de refinanciación por plan de facilidades de pago', 9, 1
) ON DUPLICATE KEY UPDATE comprobante_tipo = VALUES(comprobante_tipo);

-- Cancelaciones cruzadas en gestion__comprobantes_tipos_cancelaciones:
-- PLC (309) cancela a deudas de origen: DFS (301), DCS (302), NDI (306), ACT (308)
INSERT INTO `gestion__comprobantes_tipos_cancelaciones` (`comprobante_tipo_origen_id`, `comprobante_tipo_destino_id`, `tabla_estado_registro_id`) VALUES
(309, 301, 1),
(309, 302, 1),
(309, 306, 1),
(309, 308, 1)
ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1;

-- REC (307) cancela a ACT (308) y a PLC (309)
INSERT INTO `gestion__comprobantes_tipos_cancelaciones` (`comprobante_tipo_origen_id`, `comprobante_tipo_destino_id`, `tabla_estado_registro_id`) VALUES
(307, 308, 1),
(307, 309, 1)
ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1;

-- ------------------------------------------------------------------------------
-- 6. Cuentas Contables y Conceptos Contables para Actas (§7.2)
-- ------------------------------------------------------------------------------

-- Cuenta 3017 para Ajuste por Actas de Inspección si no existe
INSERT INTO `gestion__cont_cuentas` (
    `cont_cuenta_id`, `empresa_id`, `comprobante_id`, `codigo`, `nombre`, `naturaleza`,
    `cuenta_padre_id`, `nivel`, `orden`, `es_imputable`, `es_caja_banco`, `tabla_estado_registro_id`
) VALUES (
    3017, 3, 0, '4.1.1.01.003', 'Ajuste por Actas de Inspección', 'A',
    3012, 3, 17, 1, 0, 1
) ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Mapeo concepto SINDI_AJUSTE_ACTAS -> cuenta 3017
INSERT INTO `gestion__empresas_cuentas_conceptos` (
    `empresa_id`, `concepto`, `cont_cuenta_id`, `tabla_estado_registro_id`, `usuario_id`
) VALUES (
    3, 'SINDI_AJUSTE_ACTAS', 3017, 1, 1
) ON DUPLICATE KEY UPDATE cont_cuenta_id = VALUES(cont_cuenta_id);

-- ------------------------------------------------------------------------------
-- 7. Registro de Páginas en conf__paginas
-- ------------------------------------------------------------------------------

INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`) VALUES
(1040, 6, 'Tablero de Fiscalización',          'fiscalizacion_tablero.php', 11, 'Monitoreo de cumplimiento, morosidad y alertas de nómina', 1000, 70, 508, 1, 1, 0, NULL),
(1041, 6, 'Actas de Inspección',               'actas.php',                 12, 'Gestión de inspecciones, determinación de deuda y acuerdos', 1000, 80, 534, 1, 1, 0, NULL),
(1042, 6, 'Planes de Pago',                    'planes.php',                13, 'Administración y refinanciación en planes de facilidades',    1000, 90, 538, 1, 1, 0, NULL)
ON DUPLICATE KEY UPDATE
  `pagina` = VALUES(`pagina`),
  `url` = VALUES(`url`),
  `pagina_descripcion` = VALUES(`pagina_descripcion`),
  `tabla_id` = VALUES(`tabla_id`);

-- ------------------------------------------------------------------------------
-- 8. Registro de Funciones y Permisos (conf__paginas_funciones)
-- ------------------------------------------------------------------------------

INSERT INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `nombre_funcion`, `tabla_estado_registro_id`) VALUES
-- Tablero Fiscalización
(9060, 1040, 'Ver Tablero Fiscalización',  1),
(9061, 1040, 'Recalcular Mora',            1),
(9062, 1040, 'Exportar Tablero',           1),
-- Actas de Inspección
(9070, 1041, 'Ver Actas',                  1),
(9071, 1041, 'Crear Acta',                 1),
(9072, 1041, 'Confirmar Acta',             1),
(9073, 1041, 'Anular Acta',                1),
-- Planes de Pago
(9080, 1042, 'Ver Planes',                 1),
(9081, 1042, 'Crear Plan',                 1),
(9082, 1042, 'Confirmar Plan',             1),
(9083, 1042, 'Anular Plan',                1),
-- DDJJ de Oficio
(9090, 1022, 'Ver DDJJ Oficio',            1),
(9091, 1022, 'Generar DDJJ Oficio',        1)
ON DUPLICATE KEY UPDATE `nombre_funcion` = VALUES(`nombre_funcion`);

-- Habilitar permisos a perfiles 601 y 602 (Admin y Operador Sindicatos) y 1 (Superadmin)
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT p.perfil_id, f.pagina_funcion_id, 1
FROM `conf__perfiles` p
CROSS JOIN `conf__paginas_funciones` f
WHERE p.perfil_id IN (1, 601, 602) AND f.pagina_funcion_id BETWEEN 9060 AND 9091;

-- ------------------------------------------------------------------------------
-- 9. Semilla de Tipos de Planes para Empresa 3 (CEC Carmen de Patagones)
-- ------------------------------------------------------------------------------

INSERT INTO `sindi__planes_tipos` (
    `empresa_id`, `codigo`, `nombre`, `sistema`, `cuotas_max`, `tasa_mensual`, `anticipo_min_porc`, `f_desde`, `tabla_estado_registro_id`
) VALUES
(3, 'PLAN_PERM', 'Acuerdo Permanente', 'DIRECTO', 12, 3.0000, 10.00, '2024-01-01', 1),
(3, 'MORA_2024', 'Moratoria Especial', 'DIRECTO', 24, 1.5000, 5.00,  '2024-01-01', 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), tasa_mensual = VALUES(tasa_mensual);

SET FOREIGN_KEY_CHECKS = 1;
