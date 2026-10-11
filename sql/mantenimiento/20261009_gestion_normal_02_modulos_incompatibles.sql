-- Script de creación de la tabla conf__modulos_incompatibles
-- Define restricciones de incompatibilidad / exclusividad mutua entre módulos para una misma empresa.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `conf__modulos_incompatibles` (
  `modulo_incompatible_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo_id_1` INT UNSIGNED NOT NULL,
  `modulo_id_2` INT UNSIGNED NOT NULL,
  `motivo` VARCHAR(255) NULL DEFAULT 'Módulos mutuamente excluyentes',
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`modulo_incompatible_id`),
  UNIQUE KEY `uk_modulos_incompatibles` (`modulo_id_1`, `modulo_id_2`),
  KEY `idx_mod_inc_1` (`modulo_id_1`),
  KEY `idx_mod_inc_2` (`modulo_id_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrar la tabla en conf__tablas si no existe
INSERT INTO `conf__tablas` (`tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT 'conf__modulos_incompatibles', 'Relación de módulos mutuamente excluyentes para una misma empresa', 1, 1, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` WHERE `tabla_nombre` = 'conf__modulos_incompatibles');

-- Definir incompatibilidad entre Módulo 2 (Gestión con compatibilidad) y Módulo 5 (Gestión Normal)
INSERT INTO `conf__modulos_incompatibles` (`modulo_id_1`, `modulo_id_2`, `motivo`, `tabla_estado_registro_id`)
VALUES (2, 5, 'Módulos mutuamente excluyentes: Gestión de Empresas (con compatibilidad) y Gestión Normal (sin compatibilidad)', 1)
ON DUPLICATE KEY UPDATE `motivo` = VALUES(`motivo`), `tabla_estado_registro_id` = 1;

COMMIT;
