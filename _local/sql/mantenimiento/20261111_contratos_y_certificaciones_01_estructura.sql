-- -----------------------------------------------------------------------------------------------------------------
-- 20261111_contratos_y_certificaciones_01_estructura.sql
-- Seguimiento de Contratos y Certificaciones de Avance en Sistema de Gestión.
-- Diseño técnico: _local/docs/gestion/16_contratos_y_certificaciones_avance.md
--
-- 1. Ampliación de columnas de vigencia, plazos y modalidades en gestion__ordenes_compra.
-- 2. Nueva tabla gestion__ordenes_compra_certificaciones (mediciones de avance e hitos).
-- 3. Columna certificacion_id en gestion__facturas_proveedores para vincular factura al certificado.
-- 4. Registro en conf__tablas (tabla_id = 458; los estados, en _02).
--
-- Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0).
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------------------------------------------
-- 1. Columnas en gestion__ordenes_compra
-- -----------------------------------------------------------------------------------------------------------------
-- Una columna por vez (2026-11-11): con un solo control sobre tipo_circuito, una base clonada a mitad de la primera
-- versión del script (estudio_gili) quedó sin fecha_inicio / fecha_fin / responsable_tecnico_id y el índice fallaba.
SET @c := 'tipo_circuito';
SET @d := "ADD COLUMN `tipo_circuito` VARCHAR(25) NOT NULL DEFAULT 'BIENES' COMMENT 'BIENES / CONTRATO_RECURRENTE / CONTRATO_AVANCE' AFTER `comprobante_tipo_id`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'es_contrato';
SET @d := "ADD COLUMN `es_contrato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Tratamiento especial como contrato / servicio recurrente' AFTER `tipo_circuito`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'contrato_nro_legal';
SET @d := "ADD COLUMN `contrato_nro_legal` VARCHAR(100) DEFAULT NULL COMMENT 'Número o código legal externo del contrato' AFTER `comprobante_nro`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'fecha_inicio';
SET @d := "ADD COLUMN `fecha_inicio` DATE DEFAULT NULL COMMENT 'Inicio de vigencia' AFTER `f_entrega_estimada`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'fecha_fin';
SET @d := "ADD COLUMN `fecha_fin` DATE DEFAULT NULL COMMENT 'Fin de vigencia del contrato' AFTER `fecha_inicio`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'dias_preaviso_rescision';
SET @d := "ADD COLUMN `dias_preaviso_rescision` INT(11) NOT NULL DEFAULT 30 COMMENT 'Días de anticipación para notificar no renovación' AFTER `fecha_fin`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'tiene_renovacion_automatica';
SET @d := "ADD COLUMN `tiene_renovacion_automatica` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Renovación automática si no hay preaviso' AFTER `dias_preaviso_rescision`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'requiere_certificacion_avance';
SET @d := "ADD COLUMN `requiere_certificacion_avance` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Exige certificado aprobado para facturar' AFTER `tiene_renovacion_automatica`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'pct_anticipo_financiero';
SET @d := "ADD COLUMN `pct_anticipo_financiero` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de anticipo pactado a amortizar en certificados' AFTER `requiere_certificacion_avance`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'pct_fondo_reparo';
SET @d := "ADD COLUMN `pct_fondo_reparo` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de fondo de reparo / retención de garantía' AFTER `pct_anticipo_financiero`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'responsable_tecnico_id';
SET @d := "ADD COLUMN `responsable_tecnico_id` INT(11) DEFAULT NULL COMMENT 'Usuario habilitado para aprobar certificaciones' AFTER `pct_fondo_reparo`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := 'archivo_contrato_adjunto';
SET @d := "ADD COLUMN `archivo_contrato_adjunto` VARCHAR(255) DEFAULT NULL COMMENT 'Ruta del archivo PDF del contrato firmado' AFTER `responsable_tecnico_id`";
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND COLUMN_NAME = @c) = 0,
    CONCAT('ALTER TABLE `gestion__ordenes_compra` ', @d), CONCAT('SELECT ''', @c, ' ya existe'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices de búsqueda y alertas de vencimiento en gestion__ordenes_compra
SET @existe_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra' AND INDEX_NAME = 'idx_oc_contratos_vigencia');
SET @sql := IF(@existe_idx = 0,
    "ALTER TABLE `gestion__ordenes_compra`
     ADD KEY `idx_oc_contratos_vigencia` (`empresa_id`, `es_contrato`, `fecha_fin`),
     ADD KEY `idx_oc_tipo_circuito` (`empresa_id`, `tipo_circuito`)",
    "SELECT 'Índices de contratos ya presentes en gestion__ordenes_compra'");
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------------------------------------------
-- 2. Nueva Tabla: gestion__ordenes_compra_certificaciones
-- -----------------------------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gestion__ordenes_compra_certificaciones` (
  `certificacion_id` INT(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` INT(11) NOT NULL,
  `orden_compra_id` INT(11) NOT NULL COMMENT 'FK a gestion__ordenes_compra',
  `nro_certificado` INT(11) NOT NULL COMMENT 'Correlativo por contrato (1, 2, 3...)',
  `fecha_medicion` DATE NOT NULL COMMENT 'Fecha de corte físico / medición',
  `periodo_mes` VARCHAR(7) DEFAULT NULL COMMENT 'Período correspondiente AAAA-MM',
  `avance_acumulado_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de avance físico total acumulado a la fecha',
  `avance_periodo_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de avance físico correspondiente a este certificado',
  `monto_bruto_periodo` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto bruto del avance valorizado a precios del contrato',
  `descuento_anticipo` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Amortización proporcional de anticipo financiero otorgado',
  `fondo_reparo_retenido` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Retención de garantía / fondo de reparo',
  `monto_neto_facturable` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe neto final autorizado a facturar',
  `tabla_estado_registro_id` INT(11) NOT NULL DEFAULT 3 COMMENT 'Id genérico: 3 Borrador, 4 En revisión, 5 Aprobado (habilita factura), 12 Facturado, 6 Eliminado, 38 Anulado',
  `usuario_aprobacion_id` INT(11) DEFAULT NULL COMMENT 'Usuario técnico que aprobó la medición',
  `fecha_aprobacion` DATETIME DEFAULT NULL,
  `factura_proveedor_id` INT(11) DEFAULT NULL COMMENT 'FK a gestion__facturas_proveedores cuando el certificado es absorbido por una factura',
  `observaciones_tecnicas` TEXT DEFAULT NULL,
  `archivo_adjunto` VARCHAR(255) DEFAULT NULL COMMENT 'Ruta de la memoria técnica / acta de medición / fotos',
  `usuario_creacion_id` INT(11) DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_modificacion_id` INT(11) DEFAULT NULL,
  `fecha_modificacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`certificacion_id`),
  UNIQUE KEY `uk_oc_nro_cert` (`orden_compra_id`, `nro_certificado`),
  KEY `idx_cert_empresa_estado` (`empresa_id`, `tabla_estado_registro_id`),
  KEY `idx_cert_factura` (`factura_proveedor_id`),
  KEY `idx_cert_fecha` (`empresa_id`, `fecha_medicion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificaciones de avance físico / hitos para contratos y órdenes de compra de servicios';

-- -----------------------------------------------------------------------------------------------------------------
-- 3. Columna certificacion_id en gestion__facturas_proveedores
-- -----------------------------------------------------------------------------------------------------------------
SET @existe_fpc := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'certificacion_id');
SET @sql := IF(@existe_fpc = 0,
    "ALTER TABLE `gestion__facturas_proveedores`
     ADD COLUMN `certificacion_id` INT(11) DEFAULT NULL COMMENT 'FK a gestion__ordenes_compra_certificaciones' AFTER `orden_compra_id`,
     ADD KEY `idx_fp_certificacion` (`certificacion_id`)",
    "SELECT 'Columna certificacion_id ya presente en gestion__facturas_proveedores'");
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------------------------------------------
-- 4. Registro en conf__tablas
-- -----------------------------------------------------------------------------------------------------------------
INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT 458, 'gestion__ordenes_compra_certificaciones', 'Certificaciones de avance e hitos de contratos', 2, 2, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` WHERE `tabla_id` = 458 OR `tabla_nombre` = 'gestion__ordenes_compra_certificaciones');
