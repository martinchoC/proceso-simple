-- ==============================================================================
-- 20261011_sindicatos_06_ddl_fase3.sql
-- Fase 3 Módulo 6 (Sindicatos): Cobranzas, Volantes de Pago y Rendiciones
-- Especificación Técnica (§2.3, §2.4, §5.4, §6, §7.1, §7.2, §7.3)
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Tablas de Negocio: Volantes de Pago y Rendiciones
-- ------------------------------------------------------------------------------

-- 1.1 Cabecera de Volantes de Pago (§5.4)
CREATE TABLE IF NOT EXISTS `sindi__volantes` (
    `volante_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `empleador_id` INT UNSIGNED NOT NULL,
    `persona_id` INT UNSIGNED NULL COMMENT 'Para volantes directos de afiliados/jubilados',
    `tipo_volante` ENUM('EMPLEADOR', 'AFILIADO', 'PLAN', 'ACTA') NOT NULL DEFAULT 'EMPLEADOR',
    `numero` VARCHAR(30) NOT NULL COMMENT 'Número legible único por empresa',
    `comprobante_id` BIGINT UNSIGNED NULL COMMENT 'Vínculo al comprobante formal de numeración (tipo VOL)',
    `f_emision` DATE NOT NULL,
    `f_pago_1` DATE NOT NULL,
    `importe_capital_1` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `importe_interes_1` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `importe_1` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe fijo total al primer vencimiento',
    `f_pago_2` DATE NOT NULL,
    `importe_capital_2` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `importe_interes_2` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `importe_2` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe fijo total al segundo vencimiento',
    `codigo_barras` VARCHAR(60) NOT NULL COMMENT 'Código de barras BNA Code128',
    `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 3 COMMENT '3 Borrador, 31 Emitido, 39 Canc. Parcial, 40 Canc. Total, 20 Vencido, 38 Anulado',
    `observaciones` VARCHAR(255) NULL,
    `usuario_creacion_id` INT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `usuario_actualizacion_id` INT NULL,
    `fecha_actualizacion` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`volante_id`),
    UNIQUE KEY `uk_volante_numero` (`empresa_id`, `numero`),
    KEY `idx_volante_empresa_empleador` (`empresa_id`, `empleador_id`, `tabla_estado_registro_id`),
    KEY `idx_volante_fechas` (`empresa_id`, `f_pago_1`, `f_pago_2`),
    KEY `idx_volante_codigo_barras` (`codigo_barras`),
    CONSTRAINT `fk_volantes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
    CONSTRAINT `fk_volantes_empleador` FOREIGN KEY (`empleador_id`) REFERENCES `sindi__empleadores` (`empleador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de volantes de pago de deudas sindicales';

-- 1.2 Detalle de Deudas Incluidas en el Volante
CREATE TABLE IF NOT EXISTS `sindi__volantes_detalles` (
    `volante_detalle_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `volante_id` INT UNSIGNED NOT NULL,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `comprobante_id` BIGINT UNSIGNED NOT NULL COMMENT 'Comprobante de deuda (DFS, DCS, etc.)',
    `concepto_id` INT UNSIGNED NULL COMMENT 'Concepto de origen si aplica',
    `periodo` INT NOT NULL COMMENT 'Período AAAAMM de la deuda',
    `f_vto_original` DATE NOT NULL,
    `saldo_deuda` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `interes_1` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `subtotal_1` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `interes_2` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `subtotal_2` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`volante_detalle_id`),
    UNIQUE KEY `uk_volante_comprobante` (`volante_id`, `comprobante_id`),
    KEY `idx_vol_det_comp` (`comprobante_id`),
    CONSTRAINT `fk_vol_det_volante` FOREIGN KEY (`volante_id`) REFERENCES `sindi__volantes` (`volante_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle de comprobantes de deuda incluidos en un volante';

-- 1.3 Tramos de Interés por Detalle de Volante (Auditoría de Cálculo exacto)
CREATE TABLE IF NOT EXISTS `sindi__volantes_detalles_intereses` (
    `volante_detalle_interes_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `volante_detalle_id` INT UNSIGNED NOT NULL,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `vencimiento_nro` TINYINT NOT NULL COMMENT '1 o 2 (primer o segundo vencimiento)',
    `tasa_interes_id` INT UNSIGNED NULL,
    `f_desde` DATE NOT NULL,
    `f_hasta` DATE NOT NULL,
    `dias` INT NOT NULL,
    `tasa_mensual` DECIMAL(7,4) NOT NULL,
    `interes_calculado` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`volante_detalle_interes_id`),
    KEY `idx_vdi_detalle` (`volante_detalle_id`),
    CONSTRAINT `fk_vdi_detalle` FOREIGN KEY (`volante_detalle_id`) REFERENCES `sindi__volantes_detalles` (`volante_detalle_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tramos de tasas aplicados para auditoría de intereses';

-- 1.4 Publicación de Volante en Canales de Cobro (§7.3)
CREATE TABLE IF NOT EXISTS `sindi__volantes_canales` (
    `volante_canal_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `volante_id` INT UNSIGNED NOT NULL,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `medio_pago_id` INT UNSIGNED NOT NULL,
    `id_deuda_externo` VARCHAR(100) NULL COMMENT 'CPE Red Link, Identificador Interbanking, etc.',
    `estado_canal` ENUM('PENDIENTE', 'PUBLICADO', 'RECHAZADO', 'DESPUBLICADO') NOT NULL DEFAULT 'PENDIENTE',
    `f_publicacion` DATETIME NULL,
    `f_baja` DATETIME NULL,
    `resultado_codigo` VARCHAR(50) NULL,
    `resultado_detalle` TEXT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`volante_canal_id`),
    UNIQUE KEY `uk_volante_canal` (`volante_id`, `medio_pago_id`),
    KEY `idx_vc_medio` (`medio_pago_id`),
    CONSTRAINT `fk_vc_volante` FOREIGN KEY (`volante_id`) REFERENCES `sindi__volantes` (`volante_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vc_medio` FOREIGN KEY (`medio_pago_id`) REFERENCES `sindi__medios_pago` (`medio_pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estado de publicación del volante en canales de cobro';

-- 1.5 Cabecera de Rendición de Cobranzas
CREATE TABLE IF NOT EXISTS `sindi__rendiciones` (
    `rendicion_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `medio_pago_id` INT UNSIGNED NOT NULL,
    `f_rendicion` DATE NOT NULL,
    `archivo_nombre` VARCHAR(255) NULL,
    `archivo_hash` VARCHAR(64) NULL,
    `total_registros` INT NOT NULL DEFAULT 0,
    `total_importe` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 8 COMMENT '8 Recibido, 5 Confirmado/Conciliado, 35 Pagado/Procesado, 27 Con Error',
    `observaciones` TEXT NULL,
    `usuario_id` INT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`rendicion_id`),
    KEY `idx_rend_empresa_medio` (`empresa_id`, `medio_pago_id`, `f_rendicion`),
    CONSTRAINT `fk_rend_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
    CONSTRAINT `fk_rend_medio` FOREIGN KEY (`medio_pago_id`) REFERENCES `sindi__medios_pago` (`medio_pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de archivos y lotes de rendición';

-- 1.6 Líneas de Rendición Conciliadas
CREATE TABLE IF NOT EXISTS `sindi__rendiciones_lineas` (
    `rendicion_linea_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rendicion_id` INT UNSIGNED NOT NULL,
    `empresa_id` SMALLINT UNSIGNED NOT NULL,
    `numero_linea` INT NOT NULL DEFAULT 1,
    `volante_id` INT UNSIGNED NULL,
    `identificador_externo` VARCHAR(100) NULL COMMENT 'Código de barras leído, id de transacción, etc.',
    `f_pago` DATE NOT NULL,
    `f_acreditacion` DATE NULL,
    `importe_rendido` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `sucursal_banco` VARCHAR(50) NULL,
    `canal_origen` VARCHAR(50) NULL,
    `resultado_conciliacion` ENUM('OK', 'DIFERENCIA', 'SIN_VOLANTE', 'DUPLICADA', 'ERROR') NOT NULL DEFAULT 'OK',
    `comprobante_cobro_id` BIGINT UNSIGNED NULL COMMENT 'Comprobante formal de cobro generado',
    `nota_debito_interes_id` BIGINT UNSIGNED NULL COMMENT 'ND de intereses emitida por este pago',
    `observaciones` VARCHAR(255) NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`rendicion_linea_id`),
    KEY `idx_rl_rendicion` (`rendicion_id`),
    KEY `idx_rl_volante` (`volante_id`),
    CONSTRAINT `fk_rl_rendicion` FOREIGN KEY (`rendicion_id`) REFERENCES `sindi__rendiciones` (`rendicion_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Renglones individuales normalizados de rendición';

-- ------------------------------------------------------------------------------
-- 2. Registro en Diccionarios del Sistema (conf__tablas)
-- ------------------------------------------------------------------------------

INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`) VALUES
(528, 'sindi__volantes'),
(529, 'sindi__volantes_detalles'),
(530, 'sindi__volantes_detalles_intereses'),
(531, 'sindi__volantes_canales'),
(532, 'sindi__rendiciones'),
(533, 'sindi__rendiciones_lineas')
ON DUPLICATE KEY UPDATE tabla_nombre = VALUES(tabla_nombre);

-- ------------------------------------------------------------------------------
-- 3. Registro de Estados en conf__tablas_estados_registros
-- ------------------------------------------------------------------------------

-- Estados para sindi__volantes (tabla_id = 528)
INSERT INTO `conf__tablas_estados_registros` (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`) VALUES
(528, 3,  'Borrador',           7, 1, 0, 1),
(528, 31, 'Emitido',            1, 0, 0, 2),
(528, 39, 'Cancelado Parcial',  4, 0, 0, 3),
(528, 40, 'Cancelado Total',    2, 0, 1, 4),
(528, 20, 'Vencido',            3, 0, 1, 5),
(528, 38, 'Anulado',            6, 0, 1, 6)
ON DUPLICATE KEY UPDATE tabla_estado_registro = VALUES(tabla_estado_registro), color_id = VALUES(color_id);

-- Estados para sindi__rendiciones (tabla_id = 532)
INSERT INTO `conf__tablas_estados_registros` (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`) VALUES
(532, 8,  'Recibido',           4, 1, 0, 1),
(532, 5,  'Conciliado',         1, 0, 0, 2),
(532, 35, 'Procesado',          2, 0, 1, 3),
(532, 27, 'Con Error',          3, 0, 0, 4)
ON DUPLICATE KEY UPDATE tabla_estado_registro = VALUES(tabla_estado_registro), color_id = VALUES(color_id);

-- ------------------------------------------------------------------------------
-- 4. Registro de Páginas en conf__paginas
-- ------------------------------------------------------------------------------

INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`) VALUES
(1030, 6, 'Volantes de Pago',                 'volantes.php',           26, 'Emisión y seguimiento de volantes de pago con código de barras', 1000, 40, 528, 1, 1, 0, NULL),
(1031, 6, 'Rendiciones y Cobranzas',          'rendiciones.php',        100, 'Procesamiento de rendiciones y conciliación automática',        1000, 50, 532, 1, 1, 0, NULL),
(1032, 6, 'Cuenta Corriente de Empleadores',  'ctacte_empleadores.php', 9,  'Consulta de cuenta corriente sindical de empleadores',         1000, 60, 528, 1, 1, 0, NULL)
ON DUPLICATE KEY UPDATE
  `pagina` = VALUES(`pagina`),
  `url` = VALUES(`url`),
  `pagina_descripcion` = VALUES(`pagina_descripcion`),
  `tabla_id` = VALUES(`tabla_id`);

-- ------------------------------------------------------------------------------
-- 5. Registro de Funciones y Permisos (conf__paginas_funciones)
-- ------------------------------------------------------------------------------

INSERT INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `nombre_funcion`, `tabla_estado_registro_id`) VALUES
(9030, 1030, 'Ver Volantes',           1),
(9031, 1030, 'Generar Volante',        1),
(9032, 1030, 'Anular Volante',         1),
(9033, 1030, 'Imprimir Volante',       1),
(9040, 1031, 'Ver Rendiciones',        1),
(9041, 1031, 'Subir Archivo Rendición',1),
(9042, 1031, 'Conciliar Rendición',    1),
(9043, 1031, 'Procesar Cobranza',      1),
(9050, 1032, 'Ver Cuenta Corriente',   1),
(9051, 1032, 'Exportar Resumen',       1)
ON DUPLICATE KEY UPDATE `nombre_funcion` = VALUES(`nombre_funcion`);

-- Habilitar permisos a perfiles 601 y 602 (Admin y Operador Sindicatos)
INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT p.perfil_id, f.pagina_funcion_id, 1
FROM `conf__perfiles` p
CROSS JOIN `conf__paginas_funciones` f
WHERE p.perfil_id IN (601, 602) AND f.pagina_funcion_id BETWEEN 9030 AND 9051;

-- ------------------------------------------------------------------------------
-- 6. Tipos de Comprobante para Volantes, ND Intereses y Recibos Sindicales (§7.1)
-- ------------------------------------------------------------------------------

-- Tipo 305: Volante de Pago (VOL) - signo 0 / neutral, no impacta contabilidad
INSERT INTO `gestion__comprobantes_tipos` (
    `comprobante_tipo_id`, `empresa_id`, `comprobante_grupo_id`, `comprobante_subgrupo_id`,
    `comprobante_fiscal_id`, `impacta_stock`, `stock_signo`, `impacta_contabilidad`,
    `impacta_ctacte`, `requiere_debito_cuenta`, `comprobante_tipo`, `codigo`, `letra`,
    `signo`, `comentario`, `orden`, `tabla_estado_registro_id`
) VALUES (
    305, 3, 31, 301, 0, 0, NULL, 0, 0, 0,
    'Volante de Pago Sindical', 'VOL', 'X', '+',
    'Volante de pago de deudas sindicales a dos fechas', 5, 1
) ON DUPLICATE KEY UPDATE comprobante_tipo = VALUES(comprobante_tipo);

-- Tipo 306: Nota de Débito Intereses Resarcitorios (NDI) - signo +1, contable, ctacte
INSERT INTO `gestion__comprobantes_tipos` (
    `comprobante_tipo_id`, `empresa_id`, `comprobante_grupo_id`, `comprobante_subgrupo_id`,
    `comprobante_fiscal_id`, `impacta_stock`, `stock_signo`, `impacta_contabilidad`,
    `impacta_ctacte`, `requiere_debito_cuenta`, `comprobante_tipo`, `codigo`, `letra`,
    `signo`, `comentario`, `orden`, `tabla_estado_registro_id`
) VALUES (
    306, 3, 31, 301, 0, 0, NULL, 1, 1, 0,
    'ND Intereses Resarcitorios', 'NDI', 'X', '+',
    'Nota de débito por intereses devengados al cobrar volante', 6, 1
) ON DUPLICATE KEY UPDATE comprobante_tipo = VALUES(comprobante_tipo);

-- Tipo 307: Recibo / Cobranza Sindical (REC) - signo -1, contable, ctacte
INSERT INTO `gestion__comprobantes_tipos` (
    `comprobante_tipo_id`, `empresa_id`, `comprobante_grupo_id`, `comprobante_subgrupo_id`,
    `comprobante_fiscal_id`, `impacta_stock`, `stock_signo`, `impacta_contabilidad`,
    `impacta_ctacte`, `requiere_debito_cuenta`, `comprobante_tipo`, `codigo`, `letra`,
    `signo`, `comentario`, `orden`, `tabla_estado_registro_id`
) VALUES (
    307, 3, 31, 301, 0, 0, NULL, 1, 1, 0,
    'Cobranza Aporte Sindical', 'REC', 'X', '-',
    'Recibo de cobranza que cancela deudas y ND de intereses', 7, 1
) ON DUPLICATE KEY UPDATE comprobante_tipo = VALUES(comprobante_tipo);

-- Cancelaciones: REC (307) cancela DFS (301), DCS (302) y NDI (306)
INSERT INTO `gestion__comprobantes_tipos_cancelaciones` (`comprobante_tipo_origen_id`, `comprobante_tipo_destino_id`, `tabla_estado_registro_id`) VALUES
(307, 301, 1),
(307, 302, 1),
(307, 306, 1)
ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1;

SET FOREIGN_KEY_CHECKS = 1;
