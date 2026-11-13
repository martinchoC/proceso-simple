-- -----------------------------------------------------------------------------------------------------------------
-- 20261111_migraciones_registro_01.sql
-- Registro de los scripts de _local/sql/mantenimiento/ aplicados en esta base (conf__migraciones).
--
-- Lo lee y lo escribe el gestor de proyectos (htdocs/proyectos, botón "Base"): una fila por script con su estado
-- APLICADO / MARCADO (ya estaba aplicado de antes, se anotó sin correrlo) / OMITIDO (no corresponde a esta base) /
-- FALLIDO, el md5 del archivo y la salida del cliente mysql. Los paquetes .sql que genera el gestor para producción
-- crean esta misma tabla y anotan cada script al terminarlo. Un script que no va a todas las bases lleva en su
-- primera línea `-- @migracion: particular`.
-- La aplicación no la usa.
--
-- Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0).
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

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
