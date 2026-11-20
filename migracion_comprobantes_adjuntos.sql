-- =============================================================================
-- Adjuntos universales para cualquier comprobante (gestion__comprobantes).
-- Base para el futuro expediente del comprobante (adjuntos, comentarios, log).
-- Compatible con MySQL y MariaDB (idempotente).
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `gestion__comprobantes_adjuntos` (
  `comprobante_adjunto_id` INT(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` INT(11) NOT NULL,
  `comprobante_id` BIGINT(20) UNSIGNED NOT NULL,
  `archivo_ruta` VARCHAR(255) NOT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(80) NOT NULL,
  `tamanio_bytes` INT(11) NOT NULL DEFAULT 0,
  `sha256` CHAR(64) DEFAULT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `usuario_id` INT(11) NOT NULL,
  `fecha_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tabla_estado_registro_id` SMALLINT(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`comprobante_adjunto_id`),
  KEY `idx_comp_adj_emp_comp` (`empresa_id`, `comprobante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
