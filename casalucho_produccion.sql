-- --------------------------------------------------------
-- Host:                         92.113.32.222
-- Versión del servidor:         8.0.46-0ubuntu0.24.04.4 - (Ubuntu)
-- SO del servidor:              Linux
-- HeidiSQL Versión:             12.5.0.6677
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Volcando estructura para tabla ps_gestion_casalucho.accesos_pagina
CREATE TABLE IF NOT EXISTS `accesos_pagina` (
  `pagina_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `detalle_pagina` varchar(150) DEFAULT '',
  `pagina_titulo` varchar(200) DEFAULT '',
  `url` varchar(200) DEFAULT NULL,
  `fecha_baja` date DEFAULT NULL,
  `depende` int DEFAULT '0',
  `posicion` int DEFAULT '0',
  `division` int DEFAULT '0',
  `usuario_temp` int DEFAULT '0',
  `acceso_rapido` tinyint DEFAULT '0',
  `session_temp` varchar(250) DEFAULT NULL,
  `imagen_id` double DEFAULT '0',
  PRIMARY KEY (`pagina_id`) USING BTREE,
  KEY `IdModulo` (`pagina_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__botones
CREATE TABLE IF NOT EXISTS `conf__botones` (
  `boton_id` int NOT NULL AUTO_INCREMENT,
  `boton_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `boton_clase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `boton_icono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boton_texto` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boton_tooltip` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`boton_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__colores
CREATE TABLE IF NOT EXISTS `conf__colores` (
  `color_id` int NOT NULL AUTO_INCREMENT,
  `nombre_color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_clase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bg_clase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_clase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`color_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__documento_tipos
CREATE TABLE IF NOT EXISTS `conf__documento_tipos` (
  `documento_tipo_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `documento_tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`documento_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__empresas
CREATE TABLE IF NOT EXISTS `conf__empresas` (
  `empresa_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `empresa` char(200) DEFAULT NULL,
  `documento_tipo_id` smallint DEFAULT NULL,
  `documento_numero` double DEFAULT NULL,
  `telefono` char(250) DEFAULT NULL,
  `domicilio` char(250) DEFAULT NULL,
  `localidad_id` smallint DEFAULT NULL,
  `email` char(250) DEFAULT NULL,
  `base_conf` varchar(150) DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`empresa_id`) USING BTREE,
  KEY `documento_tipo_id` (`empresa_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__empresas_modulos
CREATE TABLE IF NOT EXISTS `conf__empresas_modulos` (
  `empresa_modulo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `modulo_id` tinyint unsigned NOT NULL,
  `tabla_estado_registro_id` tinyint DEFAULT '1',
  PRIMARY KEY (`empresa_modulo_id`),
  UNIQUE KEY `empresa_modulo_unico` (`empresa_id`,`modulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__empresas_perfiles
CREATE TABLE IF NOT EXISTS `conf__empresas_perfiles` (
  `empresa_perfil_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned DEFAULT NULL,
  `modulo_id` int unsigned DEFAULT NULL,
  `perfil_id_base` int unsigned DEFAULT NULL,
  `empresa_perfil_nombre` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`empresa_perfil_id`) USING BTREE,
  KEY `empresa_id` (`empresa_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__empresas_perfiles_funciones
CREATE TABLE IF NOT EXISTS `conf__empresas_perfiles_funciones` (
  `empresa_perfil_funcion_id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `empresa_perfil_id` int NOT NULL,
  `pagina_funcion_id` int NOT NULL,
  `asignado` tinyint(1) DEFAULT '1',
  `fecha_asignacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`empresa_perfil_funcion_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__estados_registros
CREATE TABLE IF NOT EXISTS `conf__estados_registros` (
  `estado_registro_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `estado_registro` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_estandar` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_estandar` smallint unsigned DEFAULT NULL,
  `color_id` tinyint DEFAULT '1',
  `orden_estandar` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`estado_registro_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__iconos
CREATE TABLE IF NOT EXISTS `conf__iconos` (
  `icono_id` int NOT NULL AUTO_INCREMENT,
  `icono_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono_clase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`icono_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__imagenes
CREATE TABLE IF NOT EXISTS `conf__imagenes` (
  `imagen_id` int NOT NULL AUTO_INCREMENT,
  `imagen_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_tamanio` int NOT NULL,
  `imagen_data` longblob,
  `imagen_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`imagen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__localidades
CREATE TABLE IF NOT EXISTS `conf__localidades` (
  `localidad_id` int unsigned NOT NULL,
  `localidad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cp` int unsigned NOT NULL,
  `provincia_id` smallint unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint unsigned DEFAULT '1',
  PRIMARY KEY (`localidad_id`) USING BTREE,
  KEY `cp` (`cp`) USING BTREE,
  KEY `localidad` (`provincia_id`,`localidad`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__localidades2
CREATE TABLE IF NOT EXISTS `conf__localidades2` (
  `localidad_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `provincia_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`localidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__modulos
CREATE TABLE IF NOT EXISTS `conf__modulos` (
  `modulo_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `modulo` varchar(150) DEFAULT '',
  `base_datos` varchar(255) DEFAULT NULL,
  `modulo_url` varchar(200) DEFAULT NULL,
  `email_envio_modulo` varchar(200) DEFAULT NULL,
  `layout_nombre` varchar(200) DEFAULT 'default',
  `usuario_temp` double DEFAULT NULL,
  `session_temp` varchar(100) DEFAULT '',
  `imagen_id` double DEFAULT NULL,
  `depende_id` tinyint DEFAULT '0',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`modulo_id`) USING BTREE,
  KEY `IdModulo` (`modulo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__paginas
CREATE TABLE IF NOT EXISTS `conf__paginas` (
  `pagina_id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int unsigned DEFAULT NULL,
  `pagina` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono_id` int NOT NULL DEFAULT '0',
  `pagina_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `padre_id` int unsigned NOT NULL DEFAULT '0',
  `orden` int unsigned NOT NULL DEFAULT '0',
  `tabla_id` int unsigned DEFAULT NULL,
  `pagina_patron_id` int unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  `es_acceso_directo` tinyint(1) NOT NULL DEFAULT '0',
  `orden_acceso_directo` int unsigned DEFAULT NULL,
  PRIMARY KEY (`pagina_id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `tabla_id` (`tabla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__paginas_funciones
CREATE TABLE IF NOT EXISTS `conf__paginas_funciones` (
  `pagina_funcion_id` int NOT NULL AUTO_INCREMENT,
  `pagina_id` int DEFAULT NULL,
  `icono_id` int DEFAULT NULL,
  `color_id` int DEFAULT '1',
  `funcion_estandar_id` int DEFAULT '1',
  `nombre_funcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion_js` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_origen_id` int unsigned NOT NULL,
  `tabla_estado_registro_destino_id` int unsigned NOT NULL,
  `orden` int unsigned DEFAULT '0',
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`pagina_funcion_id`),
  KEY `color_id` (`color_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__paginas_funciones_estandar
CREATE TABLE IF NOT EXISTS `conf__paginas_funciones_estandar` (
  `funcion_estandar_id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requiere_confirmacion` tinyint(1) DEFAULT '0',
  `cambia_estado` tinyint(1) DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`funcion_estandar_id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__paginas_funciones_tipos
CREATE TABLE IF NOT EXISTS `conf__paginas_funciones_tipos` (
  `pagina_funcion_id` int NOT NULL AUTO_INCREMENT,
  `tabla_tipo_id` int DEFAULT NULL,
  `icono_id` int DEFAULT NULL,
  `color_id` int DEFAULT '1',
  `nombre_funcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion_js` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_origen_id` int unsigned NOT NULL DEFAULT '0',
  `tabla_estado_registro_destino_id` int unsigned NOT NULL,
  `orden` int unsigned DEFAULT '0',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`pagina_funcion_id`) USING BTREE,
  KEY `color_id` (`color_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__paginas_secciones
CREATE TABLE IF NOT EXISTS `conf__paginas_secciones` (
  `seccion_id` int NOT NULL AUTO_INCREMENT,
  `seccion_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seccion_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `orden` int DEFAULT '0',
  PRIMARY KEY (`seccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__paises
CREATE TABLE IF NOT EXISTS `conf__paises` (
  `pais_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `pais` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`pais_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__perfiles
CREATE TABLE IF NOT EXISTS `conf__perfiles` (
  `perfil_id` int unsigned NOT NULL AUTO_INCREMENT,
  `modulo_id` int unsigned DEFAULT NULL,
  `perfil_nombre` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`perfil_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__perfiles_funciones
CREATE TABLE IF NOT EXISTS `conf__perfiles_funciones` (
  `perfil_funcion_id` int NOT NULL AUTO_INCREMENT,
  `perfil_id` int NOT NULL,
  `pagina_funcion_id` int NOT NULL,
  `asignado` tinyint(1) DEFAULT '1',
  `fecha_asignacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`perfil_funcion_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__personas
CREATE TABLE IF NOT EXISTS `conf__personas` (
  `persona_id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL DEFAULT '0',
  `afiliado_idx` int NOT NULL DEFAULT '0',
  `apellido` varchar(150) DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `cuil` double(22,0) unsigned NOT NULL,
  `documento_tipo_id` tinyint unsigned NOT NULL DEFAULT '1',
  `documento` varchar(50) NOT NULL,
  `domicilio` text,
  `cod_postal` varchar(20) DEFAULT NULL,
  `ciudad` varchar(150) DEFAULT NULL,
  `provincia_id` smallint unsigned DEFAULT NULL,
  `localidad_id` mediumint unsigned DEFAULT '7093',
  `telefono` varchar(150) DEFAULT NULL,
  `telefono_celular` varchar(150) DEFAULT NULL,
  `fax` varchar(150) DEFAULT NULL,
  `f_nacimiento` date DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `sexo` varchar(1) DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`cuil`) USING BTREE,
  KEY `razon_social` (`apellido`) USING BTREE,
  KEY `PRNENT` (`persona_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__provincias
CREATE TABLE IF NOT EXISTS `conf__provincias` (
  `provincia_id` int unsigned NOT NULL AUTO_INCREMENT,
  `pais_id` int unsigned NOT NULL DEFAULT '0',
  `provincia` varchar(150) DEFAULT NULL,
  `tabla_estado_registro_id` smallint unsigned DEFAULT '1',
  PRIMARY KEY (`provincia_id`),
  KEY `id_localidad` (`provincia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__tablas
CREATE TABLE IF NOT EXISTS `conf__tablas` (
  `tabla_id` int NOT NULL AUTO_INCREMENT,
  `tabla_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modulo_id` int unsigned DEFAULT '0',
  `tabla_tipo_id` int unsigned DEFAULT '0',
  `tabla_patron_id` int unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tabla_id`),
  KEY `modulo_id` (`modulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__tablas_comprobantes_subgrupos
CREATE TABLE IF NOT EXISTS `conf__tablas_comprobantes_subgrupos` (
  `tabla_comprobante_subgrupo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tabla_id` int unsigned NOT NULL DEFAULT '0',
  `empresa_id` smallint unsigned NOT NULL DEFAULT '0',
  `comprobante_subgrupo_id` int unsigned NOT NULL DEFAULT '0',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tabla_comprobante_subgrupo_id`) USING BTREE,
  UNIQUE KEY `uq_tabla_empresa_subgrupo` (`tabla_id`,`empresa_id`,`comprobante_subgrupo_id`) USING BTREE,
  KEY `idx_tabla_id` (`tabla_id`) USING BTREE,
  KEY `idx_comprobante_subgrupo_id` (`comprobante_subgrupo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__tablas_estados_registros
CREATE TABLE IF NOT EXISTS `conf__tablas_estados_registros` (
  `tabla_estado_registro_id` smallint NOT NULL AUTO_INCREMENT,
  `tabla_id` smallint unsigned NOT NULL DEFAULT '1',
  `estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `tabla_estado_registro` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `color_id` tinyint NOT NULL DEFAULT '1',
  `es_inicial` tinyint(1) NOT NULL DEFAULT '0',
  `orden` smallint unsigned DEFAULT '1',
  PRIMARY KEY (`tabla_estado_registro_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__tablas_tipos
CREATE TABLE IF NOT EXISTS `conf__tablas_tipos` (
  `tabla_tipo_id` int NOT NULL AUTO_INCREMENT,
  `tabla_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tabla_tipo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__tablas_tipos_estados
CREATE TABLE IF NOT EXISTS `conf__tablas_tipos_estados` (
  `tabla_tipo_estado_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tabla_tipo_id` smallint unsigned DEFAULT NULL,
  `estado_registro_id` smallint unsigned DEFAULT '1',
  `orden` smallint unsigned DEFAULT '1',
  `es_inicial` tinyint(1) DEFAULT '0',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tabla_tipo_estado_id`) USING BTREE,
  UNIQUE KEY `uq_tipo_estado` (`tabla_tipo_id`,`estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__unidades_medida
CREATE TABLE IF NOT EXISTS `conf__unidades_medida` (
  `unidad_medida_id` int NOT NULL AUTO_INCREMENT,
  `unidad_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`unidad_medida_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__usuarios
CREATE TABLE IF NOT EXISTS `conf__usuarios` (
  `usuario_id` int NOT NULL AUTO_INCREMENT,
  `usuario_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_temporal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duracion_sid_minutos` int DEFAULT '60',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_id`) USING BTREE,
  UNIQUE KEY `email` (`usuario`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__usuarios_perfiles
CREATE TABLE IF NOT EXISTS `conf__usuarios_perfiles` (
  `usuario_perfil_id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `empresa_perfil_id` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_creacion` int DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_actualizacion` int DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`usuario_perfil_id`),
  KEY `FK_conf__usuarios_perfiles_conf__estados_registros` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf__usuarios_sesiones
CREATE TABLE IF NOT EXISTS `conf__usuarios_sesiones` (
  `sid` char(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sid`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.conf___perfiles_paginas
CREATE TABLE IF NOT EXISTS `conf___perfiles_paginas` (
  `perfil_pagina_id` int unsigned NOT NULL AUTO_INCREMENT,
  `perfil_id` int unsigned DEFAULT NULL,
  `pagina_id` int unsigned DEFAULT NULL COMMENT 'viene sola al elegir la función',
  `estado_registro_id` tinyint DEFAULT '1',
  PRIMARY KEY (`perfil_pagina_id`) USING BTREE,
  KEY `empresa_id` (`perfil_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ecom__carritos
CREATE TABLE IF NOT EXISTS `ecom__carritos` (
  `carrito_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `usuario_id` int NOT NULL,
  `entidad_id` int unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`carrito_id`),
  UNIQUE KEY `uk_ecom_carrito_usuario` (`empresa_id`,`usuario_id`),
  KEY `idx_ecom_carritos_entidad` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ecom__carritos_items
CREATE TABLE IF NOT EXISTS `ecom__carritos_items` (
  `carrito_item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrito_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `cantidad` decimal(15,4) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`carrito_item_id`),
  UNIQUE KEY `uk_ecom_carrito_producto` (`carrito_id`,`producto_id`),
  KEY `idx_ecom_carritos_items_producto` (`producto_id`),
  CONSTRAINT `fk_ecom_carritos_items_carrito` FOREIGN KEY (`carrito_id`) REFERENCES `ecom__carritos` (`carrito_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ecom__login_intentos
CREATE TABLE IF NOT EXISTS `ecom__login_intentos` (
  `login_intento_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT '0',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`login_intento_id`),
  KEY `idx_login_intentos_usuario_fecha` (`usuario`,`creado_en`),
  KEY `idx_login_intentos_ip_fecha` (`ip`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ecom__pedidos
CREATE TABLE IF NOT EXISTS `ecom__pedidos` (
  `ecom_pedido_id` int unsigned NOT NULL AUTO_INCREMENT,
  `compra_pedido_id` int unsigned NOT NULL,
  `empresa_id` int unsigned NOT NULL,
  `usuario_id` int NOT NULL,
  `entidad_id` int unsigned NOT NULL,
  `origen` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ecommerce',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ecom_pedido_id`),
  UNIQUE KEY `uk_ecom_pedidos_compra_pedido` (`compra_pedido_id`),
  KEY `idx_ecom_pedidos_entidad` (`empresa_id`,`entidad_id`,`compra_pedido_id`),
  KEY `idx_ecom_pedidos_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__atributos_valores
CREATE TABLE IF NOT EXISTS `gestion__atributos_valores` (
  `atributo_valor_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `atributo_id` smallint unsigned NOT NULL,
  `valor` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`atributo_valor_id`),
  UNIQUE KEY `uq_atributo_valor` (`atributo_id`,`valor`),
  CONSTRAINT `fk_av_atributo` FOREIGN KEY (`atributo_id`) REFERENCES `gestion__atributos` (`atributo_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__bocas
CREATE TABLE IF NOT EXISTS `gestion__bocas` (
  `boca_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `sucursal_id` smallint unsigned NOT NULL,
  `es_deposito` tinyint(1) NOT NULL DEFAULT '0',
  `boca_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad_id` int unsigned DEFAULT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permite_ingresos` tinyint(1) NOT NULL DEFAULT '1',
  `permite_egresos` tinyint(1) NOT NULL DEFAULT '1',
  `es_principal` tinyint(1) NOT NULL DEFAULT '0',
  `orden` smallint unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `f_alta` datetime DEFAULT NULL,
  `f_modificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`boca_id`) USING BTREE,
  UNIQUE KEY `uk_boca_codigo` (`empresa_id`,`sucursal_id`,`codigo`) USING BTREE,
  KEY `idx_empresa` (`empresa_id`) USING BTREE,
  KEY `idx_sucursal` (`sucursal_id`) USING BTREE,
  KEY `idx_localidad` (`localidad_id`) USING BTREE,
  KEY `idx_estado` (`tabla_estado_registro_id`) USING BTREE,
  CONSTRAINT `fk_bocas_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__compras_pedidos
CREATE TABLE IF NOT EXISTS `gestion__compras_pedidos` (
  `compra_pedido_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `comprobante_tipo_id` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `comprobante_pv` int NOT NULL,
  `comprobante_nro` int NOT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int DEFAULT NULL,
  `f_emision` date NOT NULL,
  `f_entrega_estimada` date DEFAULT NULL,
  `condicion_pago_id` smallint NOT NULL,
  `direccion_entrega` varchar(50) NOT NULL DEFAULT '1.000000',
  `moneda_id` smallint NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `importe_bruto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `descuento_general_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `descuento_general` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_neto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_exento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_no_gravado` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_iva` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_otros_impuestos` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `usuario_id` int NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  `observaciones` text,
  PRIMARY KEY (`compra_pedido_id`) USING BTREE,
  KEY `empresa_id` (`empresa_id`) USING BTREE,
  KEY `sucursal_id` (`sucursal_id`) USING BTREE,
  KEY `entidad_id` (`entidad_id`) USING BTREE,
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__compras_pedidos_detalles
CREATE TABLE IF NOT EXISTS `gestion__compras_pedidos_detalles` (
  `compra_pedido_detalle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `compra_pedido_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(15,4) NOT NULL,
  `precio_unitario` decimal(18,6) NOT NULL,
  `descuento_general_pct` decimal(5,2) NOT NULL COMMENT 'viene de la tabla de condiciones de clientes',
  `descuento_general` decimal(20,2) NOT NULL COMMENT 'precio_unitario*descuento_general_pct',
  `precio_unitario_neto` decimal(18,6) NOT NULL COMMENT 'precio_unitario-descuento_general',
  `importe_neto` decimal(18,2) NOT NULL COMMENT 'cantidad*precio_unitario_neto',
  `iva_alicuota_id` smallint unsigned NOT NULL,
  `porcentaje_iva` decimal(5,2) NOT NULL,
  `importe_iva` decimal(18,2) NOT NULL,
  `importe_no_gravado` decimal(18,2) NOT NULL,
  `importe_exento` decimal(18,2) NOT NULL,
  `importe_linea` decimal(18,2) NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`compra_pedido_detalle_id`) USING BTREE,
  KEY `producto_id` (`producto_id`) USING BTREE,
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`) USING BTREE,
  KEY `venta_pedido_id` (`compra_pedido_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes
CREATE TABLE IF NOT EXISTS `gestion__comprobantes` (
  `comprobante_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tabla_origen_id` int NOT NULL DEFAULT '0',
  `registro_origen_id` int NOT NULL,
  `modulo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `comprobante_tipo_id` int NOT NULL,
  `comprobante_pv` int DEFAULT NULL,
  `comprobante_nro` int NOT NULL,
  `comprobante_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int DEFAULT NULL,
  `f_emision` date NOT NULL,
  `f_contabilidad` date DEFAULT NULL,
  `f_vto` date DEFAULT NULL,
  `moneda_id` int NOT NULL,
  `tipo_cambio` decimal(12,6) NOT NULL DEFAULT '1.000000',
  `importe_bruto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `descuento_general` decimal(18,2) NOT NULL DEFAULT '0.00',
  `no_gravado` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_neto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_exento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_no_gravado` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_iva` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_otros_impuestos` decimal(14,2) NOT NULL DEFAULT '0.00',
  `importe_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `importe_pendiente` decimal(14,2) NOT NULL DEFAULT '0.00',
  `signo` tinyint NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` int NOT NULL,
  `observaciones` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int DEFAULT NULL,
  `usuario_modificacion_id` int DEFAULT NULL,
  PRIMARY KEY (`comprobante_id`),
  UNIQUE KEY `idx_origen` (`tabla_origen_id`,`registro_origen_id`) USING BTREE,
  KEY `idx_empresa_fecha` (`empresa_id`,`f_emision`),
  KEY `idx_entidad` (`entidad_id`),
  KEY `idx_tipo` (`comprobante_tipo_id`),
  KEY `idx_modulo` (`modulo`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_detalles
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_detalles` (
  `comprobante_detalle_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_id` bigint unsigned NOT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `cantidad` decimal(20,2) NOT NULL,
  `precio_unitario` decimal(20,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(20,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(20,2) GENERATED ALWAYS AS ((`cantidad` * `precio_unitario`)) STORED,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`comprobante_detalle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_detalles_relaciones
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_detalles_relaciones` (
  `relacion_id` bigint NOT NULL AUTO_INCREMENT,
  `comprobante_detalle_origen_id` bigint NOT NULL,
  `comprobante_detalle_destino_id` bigint NOT NULL,
  `cantidad_relacionada` decimal(18,4) NOT NULL,
  `f_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`relacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_fiscales
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_fiscales` (
  `comprobante_fiscal_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` tinyint(3) unsigned zerofill NOT NULL,
  `comprobante_fiscal` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`comprobante_fiscal_id`) USING BTREE,
  KEY `comprobante_grupo_id` (`comprobante_fiscal`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_grupos
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_grupos` (
  `comprobante_grupo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL DEFAULT '0',
  `comprobante_grupo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comentario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`comprobante_grupo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_impuestos
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_impuestos` (
  `comprobante_impuesto_id` int NOT NULL AUTO_INCREMENT,
  `comprobante_id` int NOT NULL,
  `empresa_impuesto_config_id` int DEFAULT NULL,
  `tipo_origen` enum('manual','padron','regla') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_imponible` decimal(18,6) NOT NULL,
  `alicuota` decimal(10,6) NOT NULL,
  `importe` decimal(18,6) NOT NULL,
  `detalle_origen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`comprobante_impuesto_id`),
  KEY `idx_comprobante` (`comprobante_id`),
  KEY `idx_config` (`empresa_impuesto_config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_imputaciones
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_imputaciones` (
  `imputacion_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `comp_origen_id` int unsigned NOT NULL,
  `comp_destino_id` int unsigned NOT NULL,
  `importe_imputado` decimal(18,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`imputacion_id`),
  UNIQUE KEY `uq` (`comp_origen_id`,`comp_destino_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_numeradores
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_numeradores` (
  `numerador_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `punto_venta_id` int NOT NULL,
  `comprobante_tipo_id` smallint unsigned NOT NULL,
  `ultimo_numero` bigint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`numerador_id`),
  UNIQUE KEY `uk_numerador` (`empresa_id`,`punto_venta_id`,`comprobante_tipo_id`),
  KEY `punto_venta_id` (`punto_venta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_subgrupos
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_subgrupos` (
  `comprobante_subgrupo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL DEFAULT '0',
  `comprobante_grupo_id` int unsigned NOT NULL DEFAULT '0',
  `tabla_id` int unsigned NOT NULL DEFAULT '0',
  `comprobante_subgrupo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comentario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  `tipo_asiento_id` int DEFAULT NULL,
  PRIMARY KEY (`comprobante_subgrupo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_sucursales
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_sucursales` (
  `comprobante_sucursal_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `sucursal_id` int unsigned NOT NULL,
  `comprobante_tipo_id` smallint unsigned NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`comprobante_sucursal_id`),
  UNIQUE KEY `uk_sucursal_comprobante` (`sucursal_id`,`comprobante_tipo_id`),
  KEY `comprobante_tipo_id` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__comprobantes_tipos
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_tipos` (
  `comprobante_tipo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL DEFAULT '0',
  `comprobante_grupo_id` smallint unsigned NOT NULL DEFAULT '0',
  `comprobante_subgrupo_id` smallint unsigned NOT NULL DEFAULT '0',
  `comprobante_fiscal_id` smallint unsigned NOT NULL DEFAULT '0',
  `impacta_stock` tinyint(1) NOT NULL DEFAULT '0',
  `impacta_contabilidad` tinyint(1) NOT NULL DEFAULT '0',
  `impacta_ctacte` tinyint(1) NOT NULL DEFAULT '0',
  `comprobante_tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `letra` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signo` enum('+','-','+/-') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '+',
  `comentario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`comprobante_tipo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__condiciones_fiscales
CREATE TABLE IF NOT EXISTS `gestion__condiciones_fiscales` (
  `condicion_fiscal_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `condicion_fiscal` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `condicion_fiscal_codigo` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `condicion_fiscal_descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`condicion_fiscal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__condiciones_pago
CREATE TABLE IF NOT EXISTS `gestion__condiciones_pago` (
  `condicion_pago_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `condicion_pago` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('CONTADO','CUENTA_CORRIENTE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONTADO',
  `cantidad_cuotas` tinyint unsigned NOT NULL DEFAULT '1',
  `dias_primer_vencimiento` smallint unsigned NOT NULL DEFAULT '0',
  `dias_entre_cuotas` smallint unsigned NOT NULL DEFAULT '0',
  `porcentaje_anticipo` decimal(5,2) NOT NULL DEFAULT '0.00',
  `aplica_interes` tinyint(1) NOT NULL DEFAULT '0',
  `interes_mensual` decimal(5,2) DEFAULT NULL,
  `permite_modificar` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint unsigned DEFAULT '0',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`condicion_pago_id`),
  UNIQUE KEY `uk_empresa_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_agrupaciones
CREATE TABLE IF NOT EXISTS `gestion__cont_agrupaciones` (
  `cont_agrupacion_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `es_base_plan` tinyint(1) NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`cont_agrupacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_agrupaciones_nodos
CREATE TABLE IF NOT EXISTS `gestion__cont_agrupaciones_nodos` (
  `cont_agrupacion_nodo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `cont_agrupacion_id` int unsigned NOT NULL,
  `cont_cuenta_id` int unsigned DEFAULT NULL,
  `cont_agrupacion_nodo_padre_id` int unsigned DEFAULT NULL,
  `nivel` tinyint unsigned NOT NULL,
  `orden` int NOT NULL,
  `es_nodo_virtual` tinyint(1) NOT NULL,
  `etiqueta` varchar(255) DEFAULT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`cont_agrupacion_nodo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_agrupaciones_tipos
CREATE TABLE IF NOT EXISTS `gestion__cont_agrupaciones_tipos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_contable` tinyint(1) DEFAULT '0',
  `estado` enum('activo','inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_asientos
CREATE TABLE IF NOT EXISTS `gestion__cont_asientos` (
  `cont_asiento_id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `deposito_id` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int NOT NULL,
  `cont_tipo_asiento_id` int NOT NULL,
  `f_asiento` date NOT NULL,
  `anio` smallint unsigned NOT NULL DEFAULT '0',
  `mes` tinyint unsigned NOT NULL DEFAULT '0',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `moneda_id` int NOT NULL,
  `tipo_cambio` decimal(18,6) DEFAULT '1.000000',
  `usuario_creacion_id` int DEFAULT NULL,
  `usuario_modificacion_id` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `tabla_estado_registro_id` smallint unsigned DEFAULT '3',
  PRIMARY KEY (`cont_asiento_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_asientos_detalles
CREATE TABLE IF NOT EXISTS `gestion__cont_asientos_detalles` (
  `cont_asiento_id` int NOT NULL,
  `cuenta_id` int NOT NULL,
  `area_id` int DEFAULT NULL,
  `departamento_id` int DEFAULT NULL,
  `centro_costo_id` int DEFAULT NULL,
  `proyecto_id` int DEFAULT NULL,
  `entidad_id` int DEFAULT NULL,
  `comprobante_id` int DEFAULT NULL,
  `comprobante_id_destino` int DEFAULT NULL,
  `tipo` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'M',
  `importe_local` decimal(18,2) DEFAULT '0.00',
  `importe_ifrs` decimal(18,2) DEFAULT '0.00',
  `importe_impositivo` decimal(18,2) DEFAULT '0.00',
  `moneda_id` int NOT NULL,
  `tipo_cambio` decimal(18,6) DEFAULT '1.000000',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `estado` enum('activo','inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `cuenta_id` (`cuenta_id`),
  KEY `area_id` (`area_id`),
  KEY `departamento_id` (`departamento_id`),
  KEY `centro_costo_id` (`centro_costo_id`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `asiento_id` (`cont_asiento_id`) USING BTREE,
  KEY `idx_entidad_cuenta` (`entidad_id`,`cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_cuentas
CREATE TABLE IF NOT EXISTS `gestion__cont_cuentas` (
  `cont_cuenta_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `comprobante_id` int unsigned NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `naturaleza` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuenta_padre_id` int unsigned DEFAULT NULL,
  `nivel` tinyint unsigned NOT NULL,
  `orden` int NOT NULL DEFAULT '0',
  `es_imputable` tinyint(1) NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`cont_cuenta_id`),
  KEY `idx_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `idx_empresa_padre` (`empresa_id`,`cuenta_padre_id`),
  KEY `idx_empresa_nivel` (`empresa_id`,`nivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_estructuras
CREATE TABLE IF NOT EXISTS `gestion__cont_estructuras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `tipo` enum('area','departamento','centro_costo','proyecto') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estructura_padre_id` int DEFAULT NULL,
  `nivel` tinyint DEFAULT '1',
  `tipo_registro` enum('titulo','detalle') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'titulo',
  `estado` enum('activo','inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `estructura_padre_id` (`estructura_padre_id`),
  CONSTRAINT `gestion__cont_estructuras_ibfk_1` FOREIGN KEY (`estructura_padre_id`) REFERENCES `gestion__cont_estructuras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_tipos_asientos
CREATE TABLE IF NOT EXISTS `gestion__cont_tipos_asientos` (
  `cont_tipo_asiento_id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cont_tipo_asiento` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` enum('manual','automatico') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `modulo_origen` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` tinyint unsigned DEFAULT '1',
  PRIMARY KEY (`cont_tipo_asiento_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__cont_tipos_cuentas
CREATE TABLE IF NOT EXISTS `gestion__cont_tipos_cuentas` (
  `cont_tipo_cuenta_id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cont_tipo_cuenta` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permite_imputacion` tinyint(1) DEFAULT '0',
  `estado` enum('activo','inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  PRIMARY KEY (`cont_tipo_cuenta_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__depositos
CREATE TABLE IF NOT EXISTS `gestion__depositos` (
  `deposito_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `sucursal_id` smallint unsigned NOT NULL,
  `deposito_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad_id` int unsigned DEFAULT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permite_ingresos` tinyint(1) NOT NULL DEFAULT '1',
  `permite_egresos` tinyint(1) NOT NULL DEFAULT '1',
  `es_principal` tinyint(1) NOT NULL DEFAULT '0',
  `orden` smallint unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `f_alta` datetime DEFAULT NULL,
  `f_modificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`deposito_id`),
  UNIQUE KEY `uk_deposito_codigo` (`empresa_id`,`sucursal_id`,`codigo`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_localidad` (`localidad_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__documentos_tipos
CREATE TABLE IF NOT EXISTS `gestion__documentos_tipos` (
  `tipo_documento_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mascara` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tipo_documento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__empresas_impuestos_config
CREATE TABLE IF NOT EXISTS `gestion__empresas_impuestos_config` (
  `empresa_impuesto_config_id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `impuesto_tipo_id` smallint unsigned NOT NULL,
  `jurisdiccion_id` int DEFAULT NULL,
  `condicion_fiscal_id` tinyint unsigned DEFAULT NULL,
  `cont_cuenta_id` smallint unsigned DEFAULT NULL,
  `base_calculo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alicuota` decimal(10,6) NOT NULL,
  `minimo_imponible` decimal(18,6) DEFAULT '0.000000',
  `monto_fijo` decimal(18,6) DEFAULT '0.000000',
  `aplica_siempre` tinyint(1) DEFAULT '1',
  `prioridad` smallint DEFAULT '1',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tipo_calculo` enum('manual','padron','regla') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`empresa_impuesto_config_id`),
  KEY `impuesto_tipo_id` (`impuesto_tipo_id`),
  CONSTRAINT `gestion__empresas_impuestos_config_ibfk_1` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__empresas_impuestos_config_operaciones
CREATE TABLE IF NOT EXISTS `gestion__empresas_impuestos_config_operaciones` (
  `empresa_impuesto_config_operacion_id` int NOT NULL AUTO_INCREMENT,
  `empresa_impuesto_config_id` int unsigned NOT NULL,
  `producto_tipo_id` smallint unsigned NOT NULL DEFAULT '0',
  `condicion_fiscal_id` smallint unsigned NOT NULL,
  `base_calculo` varchar(50) DEFAULT NULL,
  `alicuota` decimal(10,2) DEFAULT NULL,
  `minimo_imponible` decimal(18,2) DEFAULT '0.00',
  `monto_fijo` decimal(18,2) DEFAULT '0.00',
  `f_desde` date DEFAULT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`empresa_impuesto_config_operacion_id`),
  UNIQUE KEY `uk_config_operacion` (`empresa_impuesto_config_id`,`producto_tipo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__empresas_impuestos_config_subgrupos
CREATE TABLE IF NOT EXISTS `gestion__empresas_impuestos_config_subgrupos` (
  `empresa_impuesto_config_subgrupo_id` int NOT NULL AUTO_INCREMENT,
  `empresa_impuesto_config_id` int NOT NULL,
  `comprobante_subgrupo_id` int NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`empresa_impuesto_config_subgrupo_id`),
  UNIQUE KEY `uk_config_subgrupo` (`empresa_impuesto_config_id`,`comprobante_subgrupo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades
CREATE TABLE IF NOT EXISTS `gestion__entidades` (
  `entidad_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `entidad_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_fantasia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entidad_tipo_id` tinyint unsigned DEFAULT NULL,
  `cuit` bigint unsigned DEFAULT NULL,
  `sitio_web` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicilio_legal` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad_id` int DEFAULT NULL,
  `cont_cuenta_id_proveedor` int DEFAULT NULL,
  `cont_cuenta_id_cliente` int DEFAULT NULL,
  `es_proveedor` tinyint DEFAULT '0',
  `es_cliente` tinyint DEFAULT '0',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_alta` datetime DEFAULT CURRENT_TIMESTAMP,
  `usuario_alta` int unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`entidad_id`),
  KEY `cuit_id_idx` (`cuit`) USING BTREE,
  KEY `es_proveedor_idx` (`es_proveedor`,`entidad_nombre`) USING BTREE,
  KEY `entidad_nombre_id_idx` (`entidad_nombre`) USING BTREE,
  KEY `es_cliente_id` (`es_cliente`,`entidad_nombre`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_clientes_tipos
CREATE TABLE IF NOT EXISTS `gestion__entidades_clientes_tipos` (
  `entidad_cliente_tipo_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `entidad_cliente_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_estandar` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceso_web` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=No accede, 1=Accede al carrito virtual',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  `fecha_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_alta` int unsigned DEFAULT NULL,
  PRIMARY KEY (`entidad_cliente_tipo_id`) USING BTREE,
  UNIQUE KEY `UK_entidad_cliente_tipo` (`entidad_cliente_tipo`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_condiciones_clientes
CREATE TABLE IF NOT EXISTS `gestion__entidades_condiciones_clientes` (
  `entidad_condicion_cliente_id` int NOT NULL AUTO_INCREMENT,
  `entidad_id` int NOT NULL,
  `condicion_pago_id` int DEFAULT NULL,
  `entidad_cliente_tipo_id` int DEFAULT NULL,
  `lista_precio_id` int DEFAULT NULL,
  `limite_credito` decimal(14,2) DEFAULT NULL,
  `cliente_descuento_general` decimal(5,2) DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`entidad_condicion_cliente_id`),
  KEY `idx_entidad_cond_cliente_vigente` (`entidad_id`,`f_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_condiciones_fiscales
CREATE TABLE IF NOT EXISTS `gestion__entidades_condiciones_fiscales` (
  `entidad_condicion_fiscal_id` int unsigned NOT NULL AUTO_INCREMENT,
  `entidad_id` int unsigned NOT NULL,
  `condicion_fiscal_id` int unsigned NOT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`entidad_condicion_fiscal_id`),
  KEY `entidad_id_idx` (`entidad_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_condiciones_proveedores
CREATE TABLE IF NOT EXISTS `gestion__entidades_condiciones_proveedores` (
  `entidad_condicion_proveedor_id` int NOT NULL AUTO_INCREMENT,
  `entidad_id` int NOT NULL,
  `condicion_pago_id` int DEFAULT NULL,
  `proveedor_categoria_id` int DEFAULT NULL,
  `proveedor_descuento_general` decimal(5,2) DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`entidad_condicion_proveedor_id`),
  KEY `idx_entidad_cond_proveedor_vigente` (`entidad_id`,`f_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_impuestos_padron
CREATE TABLE IF NOT EXISTS `gestion__entidades_impuestos_padron` (
  `entidad_impuesto_padron_id` int NOT NULL AUTO_INCREMENT,
  `entidad_id` int NOT NULL,
  `impuesto_jurisdiccion_id` int NOT NULL,
  `alicuota` decimal(10,6) NOT NULL,
  `tipo_contribuyente` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `fuente` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`entidad_impuesto_padron_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_listas_precios
CREATE TABLE IF NOT EXISTS `gestion__entidades_listas_precios` (
  `entidad_lista_precio_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `entidad_id` int unsigned NOT NULL,
  `lista_precio_id` int unsigned NOT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entidad_lista_precio_id`),
  KEY `idx_entidades_listas_precios_entidad_vigencia` (`empresa_id`,`entidad_id`,`f_desde`,`f_hasta`),
  KEY `idx_entidades_listas_precios_lista` (`lista_precio_id`),
  KEY `idx_entidades_listas_precios_estado` (`tabla_estado_registro_id`),
  CONSTRAINT `fk_entidades_listas_precios_lista` FOREIGN KEY (`lista_precio_id`) REFERENCES `gestion__listas_precios` (`lista_precio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_roles
CREATE TABLE IF NOT EXISTS `gestion__entidades_roles` (
  `entidad_rol_id` int unsigned NOT NULL AUTO_INCREMENT,
  `entidad_id` int unsigned NOT NULL,
  `rol_entidad_id` tinyint unsigned NOT NULL,
  `f_alta` date NOT NULL,
  `f_baja` date DEFAULT NULL,
  `tabla_estado_registro_id` tinyint unsigned DEFAULT '1',
  PRIMARY KEY (`entidad_rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_sucursales
CREATE TABLE IF NOT EXISTS `gestion__entidades_sucursales` (
  `sucursal_id` int unsigned NOT NULL AUTO_INCREMENT,
  `entidad_id` int unsigned NOT NULL,
  `empresa_id` int unsigned NOT NULL,
  `sucursal_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sucursal_direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad_id` int unsigned DEFAULT NULL,
  `sucursal_telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sucursal_email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sucursal_contacto` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_sucursales_compra
CREATE TABLE IF NOT EXISTS `gestion__entidades_sucursales_compra` (
  `entidad_sucursal_compra_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `entidad_id` int unsigned NOT NULL,
  `sucursal_id` smallint unsigned NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = Sucursal principal de compra',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_alta` int unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`entidad_sucursal_compra_id`) USING BTREE,
  KEY `FK_esc_empresa` (`empresa_id`) USING BTREE,
  KEY `FK_esc_entidad` (`entidad_id`) USING BTREE,
  KEY `FK_esc_sucursal` (`sucursal_id`) USING BTREE,
  CONSTRAINT `FK_esc_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`),
  CONSTRAINT `FK_esc_entidad` FOREIGN KEY (`entidad_id`) REFERENCES `gestion__entidades` (`entidad_id`),
  CONSTRAINT `FK_esc_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__entidades_tipos
CREATE TABLE IF NOT EXISTS `gestion__entidades_tipos` (
  `entidad_tipo_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `entidad_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`entidad_tipo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__facturas_proveedores
CREATE TABLE IF NOT EXISTS `gestion__facturas_proveedores` (
  `factura_proveedor_id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `deposito_id` int DEFAULT NULL,
  `comprobante_tipo_id` int NOT NULL,
  `comprobante_id` int NOT NULL DEFAULT '0',
  `comprobante_pv` int DEFAULT NULL,
  `comprobante_nro` int NOT NULL DEFAULT '0',
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int DEFAULT NULL,
  `f_emision` date NOT NULL,
  `f_contabilidad` date DEFAULT NULL,
  `f_vencimiento` date NOT NULL,
  `f_entrega_estimada` date DEFAULT NULL,
  `condicion_pago_id` int DEFAULT NULL,
  `moneda_id` int NOT NULL,
  `tipo_cambio` decimal(12,6) DEFAULT '1.000000',
  `direccion_entrega` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `descuento_general_pct` decimal(12,2) DEFAULT '0.00',
  `no_gravado` decimal(12,2) DEFAULT '0.00',
  `exento` decimal(12,2) DEFAULT '0.00',
  `descuentos` decimal(12,2) DEFAULT '0.00',
  `impuestos` decimal(12,2) DEFAULT '0.00',
  `otros_impuestos` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` int NOT NULL DEFAULT '3',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`factura_proveedor_id`) USING BTREE,
  KEY `idx_empresa` (`empresa_id`) USING BTREE,
  KEY `idx_comprobante` (`comprobante_tipo_id`) USING BTREE,
  KEY `idx_entidad` (`entidad_id`) USING BTREE,
  KEY `idx_estado` (`tabla_estado_registro_id`) USING BTREE,
  KEY `idx_fecha_emision` (`f_emision`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__facturas_proveedores_detalle
CREATE TABLE IF NOT EXISTS `gestion__facturas_proveedores_detalle` (
  `factura_proveedor_detalle_id` int NOT NULL AUTO_INCREMENT,
  `factura_proveedor_id` int NOT NULL,
  `empresa_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(20,2) NOT NULL DEFAULT '0.00',
  `cantidad_recibida` decimal(20,2) NOT NULL DEFAULT '0.00',
  `precio_unitario` decimal(20,2) NOT NULL DEFAULT '0.00',
  `descuento_item_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `descuento_general_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `precio_unitario_bruto` decimal(20,2) NOT NULL DEFAULT '0.00',
  `precio_unitario_neto` decimal(20,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(20,2) NOT NULL DEFAULT '0.00',
  `descuento_general` decimal(20,2) NOT NULL DEFAULT '0.00',
  `descuento_item` decimal(20,2) NOT NULL DEFAULT '0.00',
  `neto_gravado` decimal(20,2) NOT NULL DEFAULT '0.00',
  `no_gravado` decimal(20,2) NOT NULL DEFAULT '0.00',
  `exento` decimal(20,2) NOT NULL DEFAULT '0.00',
  `iva_alicuota_id` int DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) DEFAULT '0.00',
  `iva_importe` decimal(12,2) DEFAULT '0.00',
  `total_linea` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`factura_proveedor_detalle_id`) USING BTREE,
  KEY `idx_producto` (`producto_id`) USING BTREE,
  KEY `idx_orden_compra` (`factura_proveedor_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__impuestos_jurisdicciones
CREATE TABLE IF NOT EXISTS `gestion__impuestos_jurisdicciones` (
  `impuesto_jurisdiccion_id` int NOT NULL AUTO_INCREMENT,
  `impuesto_tipo_id` smallint unsigned NOT NULL,
  `jurisdiccion_id` int NOT NULL,
  `tipo_calculo` enum('manual','padron','regla') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_local` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_padron` tinyint(1) DEFAULT '0',
  `cuenta_contable_id` int DEFAULT NULL,
  `orden` smallint DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`impuesto_jurisdiccion_id`),
  UNIQUE KEY `uq_imp_jur` (`impuesto_tipo_id`,`jurisdiccion_id`),
  KEY `jurisdiccion_id` (`jurisdiccion_id`),
  CONSTRAINT `gestion__impuestos_jurisdicciones_ibfk_1` FOREIGN KEY (`impuesto_tipo_id`) REFERENCES `gestion__impuestos_tipos` (`impuesto_tipo_id`),
  CONSTRAINT `gestion__impuestos_jurisdicciones_ibfk_2` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `gestion__jurisdicciones` (`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__impuestos_tipos
CREATE TABLE IF NOT EXISTS `gestion__impuestos_tipos` (
  `impuesto_tipo_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `impuesto_tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_afip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aplica_compra` tinyint(1) DEFAULT '1',
  `aplica_venta` tinyint(1) DEFAULT '0',
  `es_retencion` tinyint(1) DEFAULT '0',
  `es_percepcion` tinyint(1) DEFAULT '0',
  `cuenta_contable_id` int DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`impuesto_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__impuestos__iva_alicuotas
CREATE TABLE IF NOT EXISTS `gestion__impuestos__iva_alicuotas` (
  `iva_alicuota_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iva_alicuota` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL,
  `es_gravado` tinyint(1) NOT NULL DEFAULT '1',
  `es_exento` tinyint(1) NOT NULL DEFAULT '0',
  `es_no_gravado` tinyint(1) NOT NULL DEFAULT '0',
  `cont_cuenta_compra_id` int NOT NULL DEFAULT '0',
  `cont_cuenta_venta_id` int NOT NULL DEFAULT '0',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`iva_alicuota_id`),
  UNIQUE KEY `uk_empresa_codigo` (`empresa_id`,`codigo`),
  KEY `ix_empresa` (`empresa_id`),
  KEY `ix_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__jurisdicciones
CREATE TABLE IF NOT EXISTS `gestion__jurisdicciones` (
  `jurisdiccion_id` int NOT NULL AUTO_INCREMENT,
  `jurisdiccion_codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jurisdiccion_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pais_id` int NOT NULL,
  `provincia_id` int DEFAULT NULL,
  `localidad_id` int DEFAULT NULL,
  `jurisdiccion_tipo_id` smallint unsigned NOT NULL,
  `organismo_recaudador` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_padron` tinyint(1) DEFAULT '0',
  `codigo_externo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`jurisdiccion_id`),
  UNIQUE KEY `uq_jurisdiccion_codigo` (`jurisdiccion_codigo`),
  KEY `idx_tipo` (`jurisdiccion_tipo_id`),
  KEY `idx_provincia` (`provincia_id`),
  CONSTRAINT `gestion__jurisdicciones_ibfk_1` FOREIGN KEY (`jurisdiccion_tipo_id`) REFERENCES `gestion__jurisdicciones_tipos` (`jurisdiccion_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__jurisdicciones_tipos
CREATE TABLE IF NOT EXISTS `gestion__jurisdicciones_tipos` (
  `jurisdiccion_tipo_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `jurisdiccion_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`jurisdiccion_tipo_id`),
  UNIQUE KEY `uq_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__listas_precios
CREATE TABLE IF NOT EXISTS `gestion__listas_precios` (
  `lista_precio_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `lista_precio_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lista_precio_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `lista_precio_origen_id` smallint unsigned NOT NULL,
  `lista_base_id` int unsigned DEFAULT NULL,
  `moneda_id` smallint unsigned DEFAULT NULL,
  `requiere_recalculo` tinyint(1) NOT NULL DEFAULT '0',
  `f_ultimo_recalculo` datetime DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lista_precio_id`),
  UNIQUE KEY `uk_listas_precios_empresa_codigo` (`empresa_id`,`lista_precio_codigo`),
  KEY `idx_listas_precios_empresa_base` (`empresa_id`),
  KEY `idx_listas_precios_origen` (`lista_precio_origen_id`),
  KEY `idx_listas_precios_base` (`lista_base_id`),
  KEY `idx_listas_precios_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__listas_precios_origenes
CREATE TABLE IF NOT EXISTS `gestion__listas_precios_origenes` (
  `lista_precio_origen_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `lista_precio_origen_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lista_precio_origen_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lista_precio_origen_id`),
  UNIQUE KEY `uk_listas_precios_origenes_codigo` (`lista_precio_origen_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__listas_precios_productos
CREATE TABLE IF NOT EXISTS `gestion__listas_precios_productos` (
  `lista_precio_producto_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `lista_precio_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `producto_costo_id` int unsigned DEFAULT NULL,
  `precio_origen` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `porcentaje_general_aplicado` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `importe_general_aplicado` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `lista_precio_regla_id` int unsigned DEFAULT NULL,
  `porcentaje_regla_aplicado` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `importe_regla_aplicado` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `es_manual` tinyint(1) NOT NULL DEFAULT '0',
  `precio_manual` decimal(18,6) DEFAULT NULL,
  `precio_final` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lista_precio_producto_id`),
  KEY `idx_listas_precios_productos_lista_producto_vigencia` (`empresa_id`,`lista_precio_id`,`producto_id`,`f_desde`,`f_hasta`),
  KEY `idx_listas_precios_productos_vigente` (`lista_precio_id`,`producto_id`),
  KEY `idx_listas_precios_productos_producto` (`producto_id`),
  KEY `idx_listas_precios_productos_regla` (`lista_precio_regla_id`),
  KEY `idx_listas_precios_productos_costo` (`producto_costo_id`),
  KEY `idx_listas_precios_productos_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__listas_precios_productos_historial
CREATE TABLE IF NOT EXISTS `gestion__listas_precios_productos_historial` (
  `lista_precio_producto_historial_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `lista_precio_id` int unsigned NOT NULL,
  `lista_precio_producto_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `producto_costo_id` int unsigned DEFAULT NULL,
  `precio_origen` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `porcentaje_general_aplicado` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `importe_general_aplicado` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `lista_precio_regla_id` int unsigned DEFAULT NULL,
  `porcentaje_regla_aplicado` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `importe_regla_aplicado` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `es_manual` tinyint(1) NOT NULL DEFAULT '0',
  `precio_manual` decimal(18,6) DEFAULT NULL,
  `precio_final` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_historial` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lista_precio_producto_historial_id`) USING BTREE,
  KEY `idx_historial_lista_producto` (`lista_precio_id`,`producto_id`) USING BTREE,
  KEY `idx_historial_fecha` (`fecha_historial`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__listas_precios_reglas
CREATE TABLE IF NOT EXISTS `gestion__listas_precios_reglas` (
  `lista_precio_regla_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `lista_precio_id` int unsigned NOT NULL,
  `regla_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `lista_precio_regla_valor_tipo_id` smallint unsigned NOT NULL,
  `valor_ajuste` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `producto_id` int unsigned DEFAULT NULL,
  `producto_categoria_id` int unsigned DEFAULT NULL,
  `marca_id` int unsigned DEFAULT NULL,
  `modelo_id` int unsigned DEFAULT NULL,
  `submodelo_id` int unsigned DEFAULT NULL,
  `producto_tipo_id` int unsigned DEFAULT NULL,
  `entidad_id` int unsigned DEFAULT NULL,
  `prioridad` smallint unsigned NOT NULL DEFAULT '100',
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `es_promocion` tinyint(1) NOT NULL DEFAULT '0',
  `permite_acumulacion` tinyint(1) NOT NULL DEFAULT '0',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lista_precio_regla_id`),
  KEY `idx_listas_precios_reglas_lista_vigencia` (`lista_precio_id`,`f_desde`,`f_hasta`),
  KEY `idx_listas_precios_reglas_valor_tipo` (`lista_precio_regla_valor_tipo_id`),
  KEY `idx_listas_precios_reglas_alcance_producto` (`producto_id`),
  KEY `idx_listas_precios_reglas_alcance_categoria` (`producto_categoria_id`),
  KEY `idx_listas_precios_reglas_prioridad` (`lista_precio_id`,`prioridad`),
  KEY `idx_listas_precios_reglas_estado` (`tabla_estado_registro_id`),
  KEY `idx_listas_precios_reglas_alcance_marca` (`marca_id`) USING BTREE,
  KEY `idx_listas_precios_reglas_alcance_grupo` (`producto_tipo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__listas_precios_reglas_valores_tipos
CREATE TABLE IF NOT EXISTS `gestion__listas_precios_reglas_valores_tipos` (
  `lista_precio_regla_valor_tipo_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `lista_precio_regla_valor_tipo_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lista_precio_regla_valor_tipo_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lista_precio_regla_valor_tipo_id`),
  UNIQUE KEY `uk_listas_precios_reglas_valores_tipos_codigo` (`lista_precio_regla_valor_tipo_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__logs
CREATE TABLE IF NOT EXISTS `gestion__logs` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int DEFAULT NULL,
  `tabla` varchar(100) NOT NULL,
  `registro_id` bigint NOT NULL,
  `accion` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT') NOT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `usuario_id` int DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `tabla` (`tabla`),
  KEY `registro_id` (`registro_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `gestion__logs_chk_1` CHECK (json_valid(`datos_anteriores`)),
  CONSTRAINT `gestion__logs_chk_2` CHECK (json_valid(`datos_nuevos`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__marcas
CREATE TABLE IF NOT EXISTS `gestion__marcas` (
  `marca_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL DEFAULT '0',
  `marca_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`marca_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__modelos
CREATE TABLE IF NOT EXISTS `gestion__modelos` (
  `modelo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `marca_id` int unsigned NOT NULL,
  `modelo_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`modelo_id`),
  KEY `marca_id` (`marca_id`),
  CONSTRAINT `gestion__modelos_ibfk_1` FOREIGN KEY (`marca_id`) REFERENCES `gestion__marcas` (`marca_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__monedas
CREATE TABLE IF NOT EXISTS `gestion__monedas` (
  `moneda_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `moneda` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `simbolo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_moneda_base` tinyint(1) NOT NULL DEFAULT '0',
  `cantidad_decimales` tinyint unsigned NOT NULL DEFAULT '2',
  `cotizacion_actual` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `permite_editar_cotizacion` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint unsigned DEFAULT '0',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`moneda_id`),
  UNIQUE KEY `uk_empresa_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ordenes_compra
CREATE TABLE IF NOT EXISTS `gestion__ordenes_compra` (
  `orden_compra_id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `comprobante_tipo_id` int NOT NULL,
  `punto_venta_id` int NOT NULL,
  `comprobante_nro` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int DEFAULT NULL,
  `f_emision` date NOT NULL,
  `f_entrega_estimada` date DEFAULT NULL,
  `condicion_pago_id` int DEFAULT NULL,
  `moneda_id` int NOT NULL,
  `tipo_cambio` decimal(12,6) DEFAULT '1.000000',
  `direccion_entrega` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `descuentos` decimal(12,2) DEFAULT '0.00',
  `impuestos` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` int NOT NULL DEFAULT '3',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_creacion_id` int DEFAULT NULL,
  `usuario_modificacion_id` int DEFAULT NULL,
  PRIMARY KEY (`orden_compra_id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_comprobante` (`comprobante_tipo_id`),
  KEY `idx_entidad` (`entidad_id`),
  KEY `idx_estado` (`tabla_estado_registro_id`),
  KEY `idx_fecha_emision` (`f_emision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ordenes_compra_detalle
CREATE TABLE IF NOT EXISTS `gestion__ordenes_compra_detalle` (
  `ordenes_compra_detalle_id` int NOT NULL AUTO_INCREMENT,
  `orden_compra_id` int NOT NULL,
  `empresa_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `cantidad_recibida` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `precio_unitario` decimal(12,6) NOT NULL DEFAULT '0.000000',
  `no_gravado` decimal(12,6) NOT NULL DEFAULT '0.000000',
  `exento` decimal(12,6) NOT NULL DEFAULT '0.000000',
  `neto_gravado` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `iva_alicuota_id` int DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) DEFAULT '0.00',
  `iva_importe` decimal(12,4) DEFAULT '0.0000',
  `total_linea` decimal(12,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`ordenes_compra_detalle_id`),
  KEY `idx_orden_compra` (`orden_compra_id`),
  KEY `idx_producto` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__perfiles_sucursales
CREATE TABLE IF NOT EXISTS `gestion__perfiles_sucursales` (
  `perfil_sucursal_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_perfil_id` int unsigned NOT NULL,
  `sucursal_id` smallint unsigned NOT NULL,
  `punto_venta_id` smallint unsigned DEFAULT NULL,
  `comprobante_tipo_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`perfil_sucursal_id`),
  UNIQUE KEY `uk_grant` (`empresa_perfil_id`,`sucursal_id`,`punto_venta_id`,`comprobante_tipo_id`),
  KEY `idx_empresa_perfil` (`empresa_perfil_id`),
  KEY `fk_persuc_sucursal` (`sucursal_id`),
  KEY `fk_persuc_pv` (`punto_venta_id`),
  KEY `fk_persuc_tipo` (`comprobante_tipo_id`),
  CONSTRAINT `fk_persuc_empresa_perfil` FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`),
  CONSTRAINT `fk_persuc_pv` FOREIGN KEY (`punto_venta_id`) REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_persuc_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_persuc_tipo` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos
CREATE TABLE IF NOT EXISTS `gestion__productos` (
  `producto_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL DEFAULT '0',
  `producto_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_barras` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `producto_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `producto_categoria_id` int unsigned NOT NULL DEFAULT '1',
  `cont_cuenta_id` int unsigned NOT NULL,
  `producto_tipo_id` int unsigned NOT NULL DEFAULT '1',
  `lado` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peso` decimal(6,2) DEFAULT NULL,
  `dimensiones` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `garantia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad_medida_id` int unsigned DEFAULT NULL,
  `iva_alicuota_id` smallint unsigned NOT NULL,
  `impuesto_interno_porcentaje` decimal(5,2) DEFAULT NULL,
  `impuesto_interno_fijo` decimal(12,2) DEFAULT NULL,
  `controla_stock` tinyint unsigned NOT NULL DEFAULT '1',
  `es_servicio` tinyint(1) NOT NULL DEFAULT '0',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  `compatibilidad_texto` text COLLATE utf8mb4_unicode_ci COMMENT 'Materializado: marca+modelo+submodelo+año, para MOSTRAR en grillas sin recalcular en cada consulta',
  `compatibilidad_busqueda` text COLLATE utf8mb4_unicode_ci COMMENT 'Materializado: igual a compatibilidad_texto pero con cada año del rango expandido, para que BUSCAR un año intermedio matchee',
  `proveedores_busqueda` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`producto_id`),
  UNIQUE KEY `producto_codigo` (`producto_codigo`),
  KEY `idx_empresa_nombre` (`empresa_id`,`producto_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_categorias
CREATE TABLE IF NOT EXISTS `gestion__productos_categorias` (
  `producto_categoria_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `producto_categoria_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_categoria_padre_id` int unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  `producto_tipo_id` int unsigned DEFAULT '1',
  `cont_cuenta_ingreso_id` int DEFAULT NULL,
  `cont_cuenta_cmv_id` int DEFAULT NULL,
  PRIMARY KEY (`producto_categoria_id`),
  UNIQUE KEY `uk_categoria_tipo` (`producto_categoria_id`,`producto_tipo_id`),
  KEY `producto_categoria_padre_id` (`producto_categoria_padre_id`),
  KEY `fk_categoria_producto_tipo` (`producto_tipo_id`),
  CONSTRAINT `fk_categoria_producto_tipo` FOREIGN KEY (`producto_tipo_id`) REFERENCES `gestion__productos_tipos` (`producto_tipo_id`),
  CONSTRAINT `gestion__productos_categorias_ibfk_1` FOREIGN KEY (`producto_categoria_padre_id`) REFERENCES `gestion__productos_categorias` (`producto_categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_combos
CREATE TABLE IF NOT EXISTS `gestion__productos_combos` (
  `producto_combo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `combo_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metodo_precio_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRECIO_FIJO',
  `porcentaje_descuento_componentes` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_combo_id`),
  UNIQUE KEY `uk_productos_combos_empresa_producto` (`empresa_id`,`producto_id`),
  KEY `idx_productos_combos_estado` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_combos_detalles
CREATE TABLE IF NOT EXISTS `gestion__productos_combos_detalles` (
  `producto_combo_detalle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `producto_combo_id` int unsigned NOT NULL,
  `producto_componente_id` int unsigned NOT NULL,
  `cantidad` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_combo_detalle_id`),
  KEY `idx_productos_combos_detalles_combo` (`producto_combo_id`),
  KEY `idx_productos_combos_detalles_componente` (`empresa_id`,`producto_componente_id`),
  KEY `idx_productos_combos_detalles_estado` (`tabla_estado_registro_id`),
  CONSTRAINT `fk_productos_combos_detalles_combo` FOREIGN KEY (`producto_combo_id`) REFERENCES `gestion__productos_combos` (`producto_combo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_compatibilidad
CREATE TABLE IF NOT EXISTS `gestion__productos_compatibilidad` (
  `compatibilidad_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `marca_id` int unsigned NOT NULL,
  `modelo_id` int unsigned NOT NULL,
  `submodelo_id` int unsigned DEFAULT NULL,
  `anio_desde` year NOT NULL DEFAULT '2000',
  `anio_hasta` year DEFAULT '2100',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`compatibilidad_id`),
  KEY `producto_id` (`empresa_id`,`producto_id`,`marca_id`,`modelo_id`,`submodelo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_compatibilidad_copy
CREATE TABLE IF NOT EXISTS `gestion__productos_compatibilidad_copy` (
  `compatibilidad_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `marca_id` int unsigned NOT NULL,
  `modelo_id` int unsigned NOT NULL,
  `submodelo_id` int unsigned DEFAULT NULL,
  `anio_desde` year NOT NULL DEFAULT '2000',
  `anio_hasta` year DEFAULT '2100',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`compatibilidad_id`) USING BTREE,
  KEY `producto_id` (`empresa_id`,`producto_id`,`marca_id`,`modelo_id`,`submodelo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_copy
CREATE TABLE IF NOT EXISTS `gestion__productos_copy` (
  `producto_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL DEFAULT '0',
  `producto_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_barras` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `producto_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `producto_categoria_id` int unsigned NOT NULL,
  `cont_cuenta_id` int unsigned NOT NULL,
  `producto_tipo_id` int unsigned NOT NULL DEFAULT '1',
  `lado` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peso` decimal(6,2) DEFAULT NULL,
  `dimensiones` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `garantia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad_medida_id` int unsigned DEFAULT NULL,
  `iva_alicuota_id` smallint unsigned NOT NULL,
  `impuesto_interno_porcentaje` decimal(5,2) DEFAULT NULL,
  `impuesto_interno_fijo` decimal(12,2) DEFAULT NULL,
  `controla_stock` tinyint unsigned NOT NULL DEFAULT '1',
  `es_servicio` tinyint(1) NOT NULL DEFAULT '0',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`producto_id`) USING BTREE,
  UNIQUE KEY `producto_codigo` (`producto_codigo`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_costos
CREATE TABLE IF NOT EXISTS `gestion__productos_costos` (
  `producto_costo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `entidad_id` int unsigned NOT NULL,
  `costo_actual` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `moneda_id` smallint unsigned DEFAULT NULL,
  `producto_costo_origen_id` smallint unsigned DEFAULT NULL,
  `comprobante_id` int unsigned DEFAULT NULL,
  `f_actualizacion` date NOT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_costo_id`),
  UNIQUE KEY `uk_productos_costos_empresa_producto` (`empresa_id`,`producto_id`),
  KEY `idx_productos_costos_producto` (`producto_id`),
  KEY `idx_productos_costos_origen` (`producto_costo_origen_id`),
  KEY `idx_productos_costos_estado` (`tabla_estado_registro_id`),
  CONSTRAINT `fk_productos_costos_origen` FOREIGN KEY (`producto_costo_origen_id`) REFERENCES `gestion__productos_costos_origenes` (`producto_costo_origen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_costos_ajustes
CREATE TABLE IF NOT EXISTS `gestion__productos_costos_ajustes` (
  `producto_costo_ajuste_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `producto_costo_ajuste_tipo_id` smallint unsigned NOT NULL,
  `producto_costo_ajuste_valor_tipo_id` smallint unsigned DEFAULT NULL,
  `ajuste_descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_ajuste` decimal(18,6) DEFAULT NULL,
  `entidad_id` int unsigned DEFAULT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `proveedor_lista_costo_id` int unsigned DEFAULT NULL,
  `comprobante_id` int unsigned DEFAULT NULL,
  `f_informado` date NOT NULL,
  `f_vigencia_desde` date NOT NULL,
  `f_vigencia_hasta` date DEFAULT NULL,
  `requiere_aprobacion` tinyint(1) NOT NULL DEFAULT '1',
  `f_aprobacion` datetime DEFAULT NULL,
  `aprobado_por` int unsigned DEFAULT NULL,
  `f_aplicacion` datetime DEFAULT NULL,
  `aplicado_por` int unsigned DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_costo_ajuste_id`),
  KEY `idx_costos_ajustes_empresa_tipo` (`empresa_id`,`producto_costo_ajuste_tipo_id`),
  KEY `idx_costos_ajustes_entidad` (`entidad_id`),
  KEY `idx_costos_ajustes_producto` (`producto_id`),
  KEY `idx_costos_ajustes_lista_proveedor` (`proveedor_lista_costo_id`),
  KEY `idx_costos_ajustes_estado` (`tabla_estado_registro_id`),
  KEY `fk_costos_ajustes_tipo` (`producto_costo_ajuste_tipo_id`),
  KEY `fk_costos_ajustes_valor_tipo` (`producto_costo_ajuste_valor_tipo_id`),
  CONSTRAINT `fk_costos_ajustes_tipo` FOREIGN KEY (`producto_costo_ajuste_tipo_id`) REFERENCES `gestion__productos_costos_ajustes_tipos` (`producto_costo_ajuste_tipo_id`),
  CONSTRAINT `fk_costos_ajustes_valor_tipo` FOREIGN KEY (`producto_costo_ajuste_valor_tipo_id`) REFERENCES `gestion__productos_costos_ajustes_valores_tipos` (`producto_costo_ajuste_valor_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_costos_ajustes_detalles
CREATE TABLE IF NOT EXISTS `gestion__productos_costos_ajustes_detalles` (
  `producto_costo_ajuste_detalle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `producto_costo_ajuste_id` int unsigned NOT NULL,
  `empresa_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `costo_anterior` decimal(18,6) DEFAULT NULL,
  `valor_ajuste` decimal(18,6) DEFAULT NULL,
  `costo_nuevo` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `producto_costo_historial_id` int unsigned DEFAULT NULL,
  `proveedor_lista_costo_detalle_id` int unsigned DEFAULT NULL,
  `fue_aplicado` tinyint(1) NOT NULL DEFAULT '0',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_costo_ajuste_detalle_id`),
  KEY `idx_costos_ajustes_det_ajuste` (`producto_costo_ajuste_id`),
  KEY `idx_costos_ajustes_det_producto` (`empresa_id`,`producto_id`),
  KEY `idx_costos_ajustes_det_lista_proveedor` (`proveedor_lista_costo_detalle_id`),
  KEY `idx_costos_ajustes_det_estado` (`tabla_estado_registro_id`),
  CONSTRAINT `fk_costos_ajustes_det_cab` FOREIGN KEY (`producto_costo_ajuste_id`) REFERENCES `gestion__productos_costos_ajustes` (`producto_costo_ajuste_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_costos_ajustes_tipos
CREATE TABLE IF NOT EXISTS `gestion__productos_costos_ajustes_tipos` (
  `producto_costo_ajuste_tipo_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `producto_costo_ajuste_tipo_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_costo_ajuste_tipo_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_costo_ajuste_tipo_id`),
  UNIQUE KEY `uk_productos_costos_ajustes_tipos_codigo` (`producto_costo_ajuste_tipo_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_costos_ajustes_valores_tipos
CREATE TABLE IF NOT EXISTS `gestion__productos_costos_ajustes_valores_tipos` (
  `producto_costo_ajuste_valor_tipo_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `producto_costo_ajuste_valor_tipo_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_costo_ajuste_valor_tipo_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_costo_ajuste_valor_tipo_id`),
  UNIQUE KEY `uk_productos_costos_ajustes_valores_tipos_codigo` (`producto_costo_ajuste_valor_tipo_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_costos_historial
CREATE TABLE IF NOT EXISTS `gestion__productos_costos_historial` (
  `producto_costo_historial_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `entidad_id` int unsigned DEFAULT NULL,
  `costo_anterior` decimal(18,6) DEFAULT NULL,
  `costo_nuevo` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `moneda_id` smallint unsigned DEFAULT NULL,
  `producto_costo_origen_id` smallint unsigned DEFAULT NULL,
  `comprobante_id` int unsigned DEFAULT NULL,
  `f_desde` date NOT NULL,
  `f_hasta` date DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_por` int unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_costo_historial_id`),
  KEY `idx_costos_hist_empresa_producto_fecha` (`empresa_id`,`producto_id`,`f_desde`,`f_hasta`),
  KEY `idx_costos_hist_origen` (`producto_costo_origen_id`),
  KEY `idx_costos_hist_estado` (`tabla_estado_registro_id`),
  CONSTRAINT `fk_costos_hist_origen` FOREIGN KEY (`producto_costo_origen_id`) REFERENCES `gestion__productos_costos_origenes` (`producto_costo_origen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_costos_origenes
CREATE TABLE IF NOT EXISTS `gestion__productos_costos_origenes` (
  `producto_costo_origen_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `producto_costo_origen_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_costo_origen_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_costo_origen_id`),
  UNIQUE KEY `uk_productos_costos_origenes_codigo` (`producto_costo_origen_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_imagenes
CREATE TABLE IF NOT EXISTS `gestion__productos_imagenes` (
  `producto_imagen_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` bigint unsigned NOT NULL,
  `empresa_id` smallint unsigned NOT NULL,
  `imagen_id` bigint unsigned NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_principal` tinyint(1) DEFAULT '0',
  `orden` smallint DEFAULT '0',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `usuario_creacion_id` bigint unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`producto_imagen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_locaciones
CREATE TABLE IF NOT EXISTS `gestion__productos_locaciones` (
  `producto_locacion_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` bigint unsigned NOT NULL,
  `sucursal_id` smallint unsigned NOT NULL,
  `local_id` smallint unsigned NOT NULL,
  `seccion_id` bigint unsigned NOT NULL,
  `estanteria_id` bigint unsigned NOT NULL,
  `estante_id` bigint unsigned NOT NULL,
  `stock_actual` decimal(12,3) DEFAULT '0.000',
  `stock_reservado` decimal(12,3) DEFAULT '0.000',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`producto_locacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_proveedores
CREATE TABLE IF NOT EXISTS `gestion__productos_proveedores` (
  `producto_proveedor_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` int unsigned NOT NULL DEFAULT '0',
  `entidad_id` mediumint unsigned NOT NULL DEFAULT '0',
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `codigo_proveedor` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`producto_proveedor_id`),
  KEY `idx_producto_empresa_estado` (`producto_id`,`empresa_id`,`tabla_estado_registro_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_proveedores_precios
CREATE TABLE IF NOT EXISTS `gestion__productos_proveedores_precios` (
  `producto_proveedor_precio_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_proveedor_id` bigint unsigned NOT NULL,
  `empresa_id` int unsigned NOT NULL,
  `entidad_id` mediumint unsigned NOT NULL,
  `precio_lista` decimal(14,4) NOT NULL,
  `moneda_id` smallint NOT NULL DEFAULT '1',
  `f_vigencia_desde` date NOT NULL,
  `origen_carga` enum('MANUAL','IMPORTACION') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL',
  `archivo_importacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_carga_id` int unsigned NOT NULL,
  `usuario_modificacion_id` int unsigned DEFAULT NULL,
  `f_carga` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`producto_proveedor_precio_id`) USING BTREE,
  UNIQUE KEY `uq_precio_vigente_prod_prov` (`producto_proveedor_id`) USING BTREE,
  KEY `idx_precios_actual_entidad` (`entidad_id`,`empresa_id`,`tabla_estado_registro_id`) USING BTREE,
  CONSTRAINT `fk_precios_actual_prod_prov` FOREIGN KEY (`producto_proveedor_id`) REFERENCES `gestion__productos_proveedores` (`producto_proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_proveedores_precios_historico
CREATE TABLE IF NOT EXISTS `gestion__productos_proveedores_precios_historico` (
  `producto_proveedor_precio_historico_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_proveedor_id` bigint unsigned NOT NULL,
  `precio_lista` decimal(14,4) NOT NULL,
  `moneda_id` smallint NOT NULL,
  `f_vigencia_desde` date NOT NULL,
  `f_vigencia_hasta` date DEFAULT NULL,
  `origen_carga` enum('MANUAL','IMPORTACION') COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_importacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_carga_id` int unsigned NOT NULL,
  `f_carga` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`producto_proveedor_precio_historico_id`) USING BTREE,
  KEY `idx_historico_prod_vigencia` (`producto_proveedor_id`,`f_vigencia_desde`) USING BTREE,
  CONSTRAINT `fk_historico_prod_prov` FOREIGN KEY (`producto_proveedor_id`) REFERENCES `gestion__productos_proveedores` (`producto_proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_tipos
CREATE TABLE IF NOT EXISTS `gestion__productos_tipos` (
  `producto_tipo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `producto_tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `producto_tipo_codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maneja_stock` tinyint unsigned DEFAULT '0',
  `es_compuesto` tinyint unsigned DEFAULT '0',
  `afecta_facturacion` tinyint unsigned DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`producto_tipo_id`),
  UNIQUE KEY `producto_tipo_codigo` (`producto_tipo_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__productos_ubicaciones
CREATE TABLE IF NOT EXISTS `gestion__productos_ubicaciones` (
  `producto_ubicacion_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` bigint unsigned NOT NULL,
  `sucursal_ubicacion_id` int unsigned NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`producto_ubicacion_id`),
  KEY `fk_pu_ubicacion` (`sucursal_ubicacion_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__proveedores_categorias
CREATE TABLE IF NOT EXISTS `gestion__proveedores_categorias` (
  `proveedor_categoria_id` int NOT NULL AUTO_INCREMENT,
  `proveedor_categoria` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL,
  PRIMARY KEY (`proveedor_categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__puntos_venta
CREATE TABLE IF NOT EXISTS `gestion__puntos_venta` (
  `punto_venta_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL DEFAULT '0',
  `sucursal_id` smallint unsigned NOT NULL,
  `boca_id` smallint unsigned DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_fiscal` int DEFAULT NULL,
  `es_web` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Indica si este registro es el punto de venta web (1) o físico/otro (0)',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `usuario_creacion_id` bigint unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`punto_venta_id`),
  KEY `emp_suc_boca` (`sucursal_id`,`empresa_id`,`boca_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__puntos_venta_comprobantes
CREATE TABLE IF NOT EXISTS `gestion__puntos_venta_comprobantes` (
  `punto_venta_comprobante_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `punto_venta_id` int NOT NULL,
  `comprobante_tipo_id` smallint unsigned NOT NULL,
  `requiere_afip` tinyint(1) NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`punto_venta_comprobante_id`),
  UNIQUE KEY `uk_pv_tipo` (`punto_venta_id`,`comprobante_tipo_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__roles_entidades
CREATE TABLE IF NOT EXISTS `gestion__roles_entidades` (
  `rol_entidad_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `rol_entidad_codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_entidad_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_entidad_descripcion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`rol_entidad_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__stock
CREATE TABLE IF NOT EXISTS `gestion__stock` (
  `empresa_id` int unsigned NOT NULL,
  `sucursal_id` int unsigned NOT NULL,
  `deposito_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `cantidad` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `costo_unitario` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `costo_promedio` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `costo_total` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `costo_ultima_compra` decimal(20,6) NOT NULL DEFAULT '0.000000',
  UNIQUE KEY `uk_stock` (`empresa_id`,`sucursal_id`,`deposito_id`,`producto_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__stock_ajustes
CREATE TABLE IF NOT EXISTS `gestion__stock_ajustes` (
  `stock_ajuste_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `sucursal_id` smallint unsigned NOT NULL,
  `deposito_id` int unsigned NOT NULL,
  `comprobante_tipo_id` int unsigned NOT NULL,
  `fecha` datetime NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock_movimiento_id` bigint unsigned DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  `usuario_id` int unsigned DEFAULT NULL,
  `f_alta` datetime DEFAULT NULL,
  `f_modificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`stock_ajuste_id`),
  KEY `idx_stock_ajuste_empresa_fecha` (`empresa_id`,`fecha`),
  KEY `idx_stock_ajuste_sucursal` (`sucursal_id`),
  KEY `idx_stock_ajuste_deposito` (`deposito_id`),
  KEY `idx_stock_ajuste_comprobante_tipo` (`comprobante_tipo_id`),
  KEY `idx_stock_ajuste_estado` (`tabla_estado_registro_id`),
  KEY `idx_stock_ajuste_movimiento` (`stock_movimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__stock_ajustes_detalles
CREATE TABLE IF NOT EXISTS `gestion__stock_ajustes_detalles` (
  `stock_ajuste_detalle_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_ajuste_id` bigint unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `deposito_id` int unsigned NOT NULL,
  `stock_sistema` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `stock_fisico` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `diferencia` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `stock_movimiento_tipo_id` tinyint unsigned DEFAULT NULL,
  `cantidad_ajuste` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `costo_unitario` decimal(20,6) DEFAULT NULL,
  `costo_total` decimal(20,6) DEFAULT NULL,
  `observacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`stock_ajuste_detalle_id`),
  KEY `idx_stock_ajuste_det_ajuste` (`stock_ajuste_id`),
  KEY `idx_stock_ajuste_det_producto` (`producto_id`),
  KEY `idx_stock_ajuste_det_deposito` (`deposito_id`),
  KEY `idx_stock_ajuste_det_tipo` (`stock_movimiento_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__stock_movimientos
CREATE TABLE IF NOT EXISTS `gestion__stock_movimientos` (
  `stock_movimiento_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `sucursal_id` int unsigned NOT NULL,
  `deposito_id` int unsigned NOT NULL,
  `comprobante_tipo_id` int unsigned DEFAULT NULL,
  `comprobante_id` bigint unsigned DEFAULT NULL,
  `stock_movimiento_tipo_id` tinyint unsigned NOT NULL,
  `fecha` datetime NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  `f_alta` datetime DEFAULT NULL,
  `f_modificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`stock_movimiento_id`),
  KEY `Índice 2` (`sucursal_id`,`deposito_id`,`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__stock_movimientos_detalles
CREATE TABLE IF NOT EXISTS `gestion__stock_movimientos_detalles` (
  `stock_movimiento_detalle_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_movimiento_id` bigint unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `cantidad` decimal(20,2) NOT NULL,
  `costo_unitario` decimal(20,2) DEFAULT NULL,
  `costo_total` decimal(20,2) DEFAULT NULL,
  `deposito_id` int unsigned NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`stock_movimiento_detalle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__stock_movimientos_tipos
CREATE TABLE IF NOT EXISTS `gestion__stock_movimientos_tipos` (
  `stock_movimiento_tipo_id` tinyint NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signo` smallint DEFAULT NULL,
  PRIMARY KEY (`stock_movimiento_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__stock_recepciones
CREATE TABLE IF NOT EXISTS `gestion__stock_recepciones` (
  `stock_recepcion_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `orden_compra_id` int DEFAULT NULL,
  `deposito_id` int NOT NULL,
  `fecha_recepcion` datetime NOT NULL,
  `usuario_id` int NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  `observaciones` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stock_recepcion_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `comprobante_id` (`comprobante_id`),
  KEY `orden_compra_id` (`orden_compra_id`),
  KEY `deposito_id` (`deposito_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__submodelos
CREATE TABLE IF NOT EXISTS `gestion__submodelos` (
  `submodelo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `modelo_id` int unsigned NOT NULL,
  `submodelo_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`submodelo_id`),
  KEY `modelo_id` (`modelo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__sucursales
CREATE TABLE IF NOT EXISTS `gestion__sucursales` (
  `sucursal_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint unsigned NOT NULL,
  `sucursal_tipo_id` tinyint unsigned NOT NULL,
  `sucursal_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad_id` int unsigned DEFAULT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`sucursal_id`) USING BTREE,
  KEY `FK_gestion__locales_conf__empresas` (`empresa_id`),
  KEY `FK_gestion__locales_gestion__locales_tipos` (`sucursal_tipo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__sucursales_tipos
CREATE TABLE IF NOT EXISTS `gestion__sucursales_tipos` (
  `sucursal_tipo_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL DEFAULT '0',
  `sucursal_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`sucursal_tipo_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__sucursales_ubicaciones
CREATE TABLE IF NOT EXISTS `gestion__sucursales_ubicaciones` (
  `sucursal_ubicacion_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `sucursal_id` smallint unsigned NOT NULL,
  `boca_id` smallint unsigned NOT NULL,
  `seccion` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estanteria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estante` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `posicion` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`sucursal_ubicacion_id`) USING BTREE,
  KEY `idx_local` (`empresa_id`,`sucursal_id`,`boca_id`,`seccion`,`posicion`,`estante`,`estanteria`) USING BTREE,
  KEY `idx_busqueda_rapida` (`empresa_id`,`sucursal_id`,`boca_id`) USING BTREE,
  FULLTEXT KEY `idx_busqueda_completa` (`seccion`,`estanteria`,`estante`,`posicion`,`descripcion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__unidades_medida
CREATE TABLE IF NOT EXISTS `gestion__unidades_medida` (
  `unidad_medida_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `unidad_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad_abreviatura` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`unidad_medida_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__usuarios_sucursales_excepciones
CREATE TABLE IF NOT EXISTS `gestion__usuarios_sucursales_excepciones` (
  `usuario_excepcion_id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `sucursal_id` int unsigned NOT NULL,
  `punto_venta_id` int unsigned DEFAULT NULL,
  `comprobante_tipo_id` int unsigned DEFAULT NULL,
  `tipo_excepcion` enum('PERMITE','DENIEGA') NOT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `usuario_alta_id` int unsigned NOT NULL,
  `f_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_excepcion_id`),
  KEY `idx_usuario_vigencia` (`usuario_id`,`fecha_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_cobranzas
CREATE TABLE IF NOT EXISTS `gestion__ventas_cobranzas` (
  `venta_cobranza_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `fecha_cobranza` date NOT NULL,
  `importe_total` decimal(18,2) NOT NULL,
  `moneda_id` smallint NOT NULL,
  `usuario_id` int NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  `observaciones` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`venta_cobranza_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `sucursal_id` (`sucursal_id`),
  KEY `entidad_id` (`entidad_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_facturas
CREATE TABLE IF NOT EXISTS `gestion__ventas_facturas` (
  `venta_factura_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `comprobante_tipo_id` int NOT NULL,
  `punto_venta_id` int NOT NULL,
  `comprobante_nro` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int DEFAULT NULL,
  `condicion_pago_id` int DEFAULT NULL,
  `f_emision` date NOT NULL,
  `f_contabilidad` date NOT NULL,
  `f_vto` date DEFAULT NULL,
  `moneda_id` smallint NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `importe_bruto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `descuento_general_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `descuento_general` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_neto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_exento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_no_gravado` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_iva` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_otros_impuestos` decimal(18,2) NOT NULL DEFAULT '0.00',
  `importe_total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `observaciones` text,
  `CAE` text,
  `f_vto_CAE` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`venta_factura_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `sucursal_id` (`sucursal_id`),
  KEY `entidad_id` (`entidad_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`),
  KEY `fecha_factura` (`f_emision`) USING BTREE,
  KEY `punto_venta_id` (`punto_venta_id`) USING BTREE,
  KEY `condicion_pago_id` (`condicion_pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_facturas_detalles
CREATE TABLE IF NOT EXISTS `gestion__ventas_facturas_detalles` (
  `venta_factura_detalle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_factura_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `venta_remito_detalle_id` int DEFAULT NULL,
  `venta_pedido_detalle_id` int DEFAULT NULL,
  `cantidad` decimal(15,4) NOT NULL,
  `precio_unitario` decimal(18,6) NOT NULL,
  `descuento_general_pct` decimal(5,2) NOT NULL,
  `descuento_general` decimal(18,2) NOT NULL,
  `precio_unitario_neto` decimal(18,2) NOT NULL,
  `importe_neto` decimal(18,2) NOT NULL,
  `iva_alicuota_id` smallint unsigned NOT NULL,
  `porcentaje_iva` decimal(5,2) NOT NULL,
  `importe_iva` decimal(18,2) NOT NULL,
  `importe_no_gravado` decimal(18,2) NOT NULL,
  `importe_exento` decimal(18,2) NOT NULL,
  `importe_linea` decimal(18,2) NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`venta_factura_detalle_id`),
  KEY `venta_factura_id` (`venta_factura_id`),
  KEY `producto_id` (`producto_id`),
  KEY `iva_alicuota_id` (`iva_alicuota_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_pedidos
CREATE TABLE IF NOT EXISTS `gestion__ventas_pedidos` (
  `venta_pedido_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `comprobante_tipo_id` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `punto_venta_id` int NOT NULL,
  `comprobante_nro` int NOT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int NOT NULL,
  `f_emision` date NOT NULL,
  `f_entrega_estimada` date DEFAULT NULL,
  `condicion_pago_id` smallint NOT NULL,
  `direccion_entrega` varchar(50) NOT NULL DEFAULT '1.000000',
  `moneda_id` smallint NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `descuento_general_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `descuentos` decimal(18,2) NOT NULL DEFAULT '0.00',
  `exento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `no_gravado` decimal(18,2) NOT NULL DEFAULT '0.00',
  `impuestos` decimal(18,2) NOT NULL DEFAULT '0.00',
  `otros_impuestos` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `observaciones` text,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`venta_pedido_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `sucursal_id` (`sucursal_id`),
  KEY `entidad_id` (`entidad_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_pedidos_detalles
CREATE TABLE IF NOT EXISTS `gestion__ventas_pedidos_detalles` (
  `venta_pedido_detalle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_pedido_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(15,2) NOT NULL,
  `cantidad_entregada` decimal(15,2) NOT NULL,
  `facturado` decimal(15,2) NOT NULL,
  `precio_unitario` decimal(18,2) NOT NULL,
  `descuento_item_pct` decimal(5,2) NOT NULL,
  `descuento_general_pct` decimal(5,2) NOT NULL COMMENT 'viene de la tabla de condiciones de clientes',
  `descuento_general` decimal(20,2) NOT NULL COMMENT 'precio_unitario*descuento_general_pct',
  `descuento_item` decimal(20,2) NOT NULL,
  `precio_unitario_bruto` decimal(18,2) NOT NULL,
  `precio_unitario_neto` decimal(18,2) NOT NULL COMMENT 'precio_unitario-descuento_general',
  `neto_gravado` decimal(18,2) NOT NULL COMMENT 'cantidad*precio_unitario_neto',
  `iva_alicuota_id` smallint unsigned NOT NULL,
  `iva_porcentaje` decimal(5,2) NOT NULL,
  `iva_importe` decimal(18,2) NOT NULL,
  `no_gravado` decimal(18,2) NOT NULL,
  `exento` decimal(18,2) NOT NULL,
  `total_linea` decimal(18,2) NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`venta_pedido_detalle_id`),
  KEY `venta_pedido_id` (`venta_pedido_id`),
  KEY `producto_id` (`producto_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_presupuestos
CREATE TABLE IF NOT EXISTS `gestion__ventas_presupuestos` (
  `venta_presupuesto_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int DEFAULT NULL,
  `fecha_presupuesto` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `numero` bigint NOT NULL,
  `moneda_id` smallint NOT NULL,
  `tipo_cambio` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total_impuestos` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `convertido_a_pedido_id` int DEFAULT NULL,
  `convertido_a_factura_id` int DEFAULT NULL,
  `usuario_id` int NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  `observaciones` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`venta_presupuesto_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `sucursal_id` (`sucursal_id`),
  KEY `entidad_id` (`entidad_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_presupuestos_detalles
CREATE TABLE IF NOT EXISTS `gestion__ventas_presupuestos_detalles` (
  `venta_presupuesto_detalle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_presupuesto_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(15,4) NOT NULL,
  `precio_unitario` decimal(18,6) NOT NULL,
  `iva_alicuota_id` smallint unsigned NOT NULL,
  `porcentaje_iva` decimal(5,2) NOT NULL,
  `importe_iva` decimal(18,2) NOT NULL,
  `importe_neto` decimal(18,2) NOT NULL,
  `importe_total` decimal(18,2) NOT NULL,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`venta_presupuesto_detalle_id`),
  KEY `venta_presupuesto_id` (`venta_presupuesto_id`),
  KEY `producto_id` (`producto_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_remitos
CREATE TABLE IF NOT EXISTS `gestion__ventas_remitos` (
  `venta_remito_id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `boca_id` int NOT NULL,
  `comprobante_tipo_id` int NOT NULL,
  `comprobante_pv` int NOT NULL,
  `comprobante_nro` int NOT NULL,
  `comprobante_id` int NOT NULL,
  `f_emision` date NOT NULL,
  `entidad_id` int NOT NULL,
  `entidad_sucursal_id` int DEFAULT NULL,
  `observaciones` text,
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`venta_remito_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `sucursal_id` (`sucursal_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.gestion__ventas_remitos_detalles
CREATE TABLE IF NOT EXISTS `gestion__ventas_remitos_detalles` (
  `venta_remito_detalle_id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_remito_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `venta_pedido_detalle_id` int DEFAULT NULL,
  `cantidad` decimal(15,4) NOT NULL,
  `facturado` bigint NOT NULL DEFAULT '0',
  `cantidad_facturada` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `precio_unitario_bruto` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `descuento_general_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `descuento_general` decimal(20,2) NOT NULL DEFAULT '0.00',
  `precio_unitario_neto` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `importe_linea` decimal(18,6) NOT NULL,
  `iva_alicuota_id` smallint unsigned DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT '0.00',
  `iva_importe` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tabla_estado_registro_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`venta_remito_detalle_id`),
  KEY `venta_remito_id` (`venta_remito_id`),
  KEY `producto_id` (`producto_id`),
  KEY `tabla_estado_registro_id` (`tabla_estado_registro_id`),
  KEY `iva_alicuota_id` (`iva_alicuota_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__ambitos_organizacionales
CREATE TABLE IF NOT EXISTS `ml__ambitos_organizacionales` (
  `ambito_id` int NOT NULL AUTO_INCREMENT,
  `ambito_tipo_id` int NOT NULL,
  `ambito_padre_id` int DEFAULT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`ambito_id`),
  KEY `fk_ambito_tipo` (`ambito_tipo_id`),
  KEY `fk_ambito_padre` (`ambito_padre_id`),
  CONSTRAINT `fk_ambito_padre` FOREIGN KEY (`ambito_padre_id`) REFERENCES `ml__ambitos_organizacionales` (`ambito_id`),
  CONSTRAINT `fk_ambito_tipo` FOREIGN KEY (`ambito_tipo_id`) REFERENCES `ml__ambitos_tipos` (`ambito_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__ambitos_tipos
CREATE TABLE IF NOT EXISTS `ml__ambitos_tipos` (
  `ambito_tipo_id` int NOT NULL AUTO_INCREMENT,
  `ambito_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint unsigned DEFAULT '1',
  PRIMARY KEY (`ambito_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__entidades
CREATE TABLE IF NOT EXISTS `ml__entidades` (
  `entidad_id` int NOT NULL AUTO_INCREMENT,
  `empresa_ambito_id` int NOT NULL,
  `entidad_tipo_id` int NOT NULL,
  `entidad_padre_id` int DEFAULT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jurisdiccion_id` int DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`entidad_id`),
  KEY `fk_ent_empresa` (`empresa_ambito_id`),
  KEY `fk_ent_tipo` (`entidad_tipo_id`),
  KEY `fk_ent_padre` (`entidad_padre_id`),
  KEY `fk_ent_jur` (`jurisdiccion_id`),
  CONSTRAINT `fk_ent_empresa` FOREIGN KEY (`empresa_ambito_id`) REFERENCES `ml__ambitos_organizacionales` (`ambito_id`),
  CONSTRAINT `fk_ent_jur` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `ml__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_ent_padre` FOREIGN KEY (`entidad_padre_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_ent_tipo` FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__entidades_tipos
CREATE TABLE IF NOT EXISTS `ml__entidades_tipos` (
  `entidad_tipo_id` int NOT NULL AUTO_INCREMENT,
  `entidad_tipo` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`entidad_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__jurisdicciones
CREATE TABLE IF NOT EXISTS `ml__jurisdicciones` (
  `jurisdiccion_id` int NOT NULL AUTO_INCREMENT,
  `jurisdiccion_tipo_id` int NOT NULL,
  `jurisdiccion_padre_id` int DEFAULT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`jurisdiccion_id`),
  KEY `fk_jur_tipo` (`jurisdiccion_tipo_id`),
  KEY `fk_jur_padre` (`jurisdiccion_padre_id`),
  CONSTRAINT `fk_jur_padre` FOREIGN KEY (`jurisdiccion_padre_id`) REFERENCES `ml__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_jur_tipo` FOREIGN KEY (`jurisdiccion_tipo_id`) REFERENCES `ml__jurisdicciones_tipos` (`jurisdiccion_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__jurisdicciones_tipos
CREATE TABLE IF NOT EXISTS `ml__jurisdicciones_tipos` (
  `jurisdiccion_tipo_id` int NOT NULL AUTO_INCREMENT,
  `jurisdiccion_tipo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`jurisdiccion_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__normas
CREATE TABLE IF NOT EXISTS `ml__normas` (
  `norma_id` int NOT NULL AUTO_INCREMENT,
  `ambito_definicion_id` int NOT NULL,
  `norma_tipo_id` int NOT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `organismo_emisor` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_vigencia_desde` date DEFAULT NULL,
  `fecha_vigencia_hasta` date DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`norma_id`),
  KEY `fk_norma_ambito` (`ambito_definicion_id`),
  KEY `fk_norma_tipo` (`norma_tipo_id`),
  CONSTRAINT `fk_norma_ambito` FOREIGN KEY (`ambito_definicion_id`) REFERENCES `ml__ambitos_organizacionales` (`ambito_id`),
  CONSTRAINT `fk_norma_tipo` FOREIGN KEY (`norma_tipo_id`) REFERENCES `ml__normas_tipos` (`norma_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__normas_ambitos_aplicacion
CREATE TABLE IF NOT EXISTS `ml__normas_ambitos_aplicacion` (
  `norma_ambito_aplicacion_id` int NOT NULL AUTO_INCREMENT,
  `norma_id` int NOT NULL,
  `ambito_id` int NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`norma_ambito_aplicacion_id`),
  UNIQUE KEY `uk_norma_ambito` (`norma_id`,`ambito_id`),
  KEY `fk_norma_aplica_ambito` (`ambito_id`),
  CONSTRAINT `fk_norma_aplica_ambito` FOREIGN KEY (`ambito_id`) REFERENCES `ml__ambitos_organizacionales` (`ambito_id`),
  CONSTRAINT `fk_norma_aplica_norma` FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__normas_procedimientos
CREATE TABLE IF NOT EXISTS `ml__normas_procedimientos` (
  `norma_procedimiento_id` int NOT NULL AUTO_INCREMENT,
  `norma_id` int NOT NULL,
  `procedimiento_id` int NOT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`norma_procedimiento_id`),
  UNIQUE KEY `uk_norma_proc` (`norma_id`,`procedimiento_id`),
  KEY `fk_np_proc` (`procedimiento_id`),
  CONSTRAINT `fk_np_norma` FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`),
  CONSTRAINT `fk_np_proc` FOREIGN KEY (`procedimiento_id`) REFERENCES `ml__procedimientos` (`procedimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__normas_tipos
CREATE TABLE IF NOT EXISTS `ml__normas_tipos` (
  `norma_tipo_id` int NOT NULL AUTO_INCREMENT,
  `norma_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`norma_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__obligaciones
CREATE TABLE IF NOT EXISTS `ml__obligaciones` (
  `obligacion_id` int NOT NULL AUTO_INCREMENT,
  `empresa_ambito_id` int NOT NULL,
  `norma_id` int NOT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_obligacion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frecuencia_tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frecuencia_cada` int DEFAULT NULL,
  `criterio_cumplimiento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo_evidencia_requerida` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`obligacion_id`),
  KEY `fk_obl_empresa` (`empresa_ambito_id`),
  KEY `fk_obl_norma` (`norma_id`),
  CONSTRAINT `fk_obl_empresa` FOREIGN KEY (`empresa_ambito_id`) REFERENCES `ml__ambitos_organizacionales` (`ambito_id`),
  CONSTRAINT `fk_obl_norma` FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__obligaciones_jurisdicciones
CREATE TABLE IF NOT EXISTS `ml__obligaciones_jurisdicciones` (
  `obligacion_jurisdiccion_id` int NOT NULL AUTO_INCREMENT,
  `obligacion_id` int NOT NULL,
  `jurisdiccion_id` int NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`obligacion_jurisdiccion_id`),
  UNIQUE KEY `uk_obl_jur` (`obligacion_id`,`jurisdiccion_id`),
  KEY `fk_oj_jur` (`jurisdiccion_id`),
  CONSTRAINT `fk_oj_jur` FOREIGN KEY (`jurisdiccion_id`) REFERENCES `ml__jurisdicciones` (`jurisdiccion_id`),
  CONSTRAINT `fk_oj_obl` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__obligaciones_raci
CREATE TABLE IF NOT EXISTS `ml__obligaciones_raci` (
  `obligacion_raci_id` int NOT NULL AUTO_INCREMENT,
  `obligacion_id` int NOT NULL,
  `rol_organizacional_id` int NOT NULL,
  `raci_tipo_id` int NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`obligacion_raci_id`),
  UNIQUE KEY `uk_raci` (`obligacion_id`,`rol_organizacional_id`,`raci_tipo_id`),
  KEY `fk_or_rol` (`rol_organizacional_id`),
  KEY `fk_or_raci` (`raci_tipo_id`),
  CONSTRAINT `fk_or_obl` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `fk_or_raci` FOREIGN KEY (`raci_tipo_id`) REFERENCES `ml__raci_tipos` (`raci_tipo_id`),
  CONSTRAINT `fk_or_rol` FOREIGN KEY (`rol_organizacional_id`) REFERENCES `ml__roles_organizacionales` (`rol_organizacional_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__obligaciones_tipos_entidades
CREATE TABLE IF NOT EXISTS `ml__obligaciones_tipos_entidades` (
  `obligacion_tipo_entidad_id` int NOT NULL AUTO_INCREMENT,
  `obligacion_id` int NOT NULL,
  `entidad_tipo_id` int NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`obligacion_tipo_entidad_id`),
  UNIQUE KEY `uk_obl_ent` (`obligacion_id`,`entidad_tipo_id`),
  KEY `fk_ote_tipo` (`entidad_tipo_id`),
  CONSTRAINT `fk_ote_obl` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `fk_ote_tipo` FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__procedimientos
CREATE TABLE IF NOT EXISTS `ml__procedimientos` (
  `procedimiento_id` int NOT NULL AUTO_INCREMENT,
  `ambito_definicion_id` int NOT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `objetivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `alcance` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_aprobacion` date DEFAULT NULL,
  `area_responsable` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`procedimiento_id`),
  KEY `fk_proc_ambito` (`ambito_definicion_id`),
  CONSTRAINT `fk_proc_ambito` FOREIGN KEY (`ambito_definicion_id`) REFERENCES `ml__ambitos_organizacionales` (`ambito_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__procedimientos_instrucciones
CREATE TABLE IF NOT EXISTS `ml__procedimientos_instrucciones` (
  `procedimiento_instruccion_id` int NOT NULL AUTO_INCREMENT,
  `procedimiento_id` int NOT NULL,
  `orden` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion_paso` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `evidencia_requerida` tinyint(1) NOT NULL DEFAULT '0',
  `tipo_evidencia_sugerida` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`procedimiento_instruccion_id`),
  KEY `fk_instr_proc` (`procedimiento_id`),
  CONSTRAINT `fk_instr_proc` FOREIGN KEY (`procedimiento_id`) REFERENCES `ml__procedimientos` (`procedimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__procedimientos_obligaciones
CREATE TABLE IF NOT EXISTS `ml__procedimientos_obligaciones` (
  `procedimiento_obligacion_id` int NOT NULL AUTO_INCREMENT,
  `obligacion_id` int NOT NULL,
  `procedimiento_id` int NOT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`procedimiento_obligacion_id`),
  UNIQUE KEY `uk_obl_proc` (`obligacion_id`,`procedimiento_id`),
  KEY `fk_po_proc` (`procedimiento_id`),
  CONSTRAINT `fk_po_obl` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`),
  CONSTRAINT `fk_po_proc` FOREIGN KEY (`procedimiento_id`) REFERENCES `ml__procedimientos` (`procedimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__raci_tipos
CREATE TABLE IF NOT EXISTS `ml__raci_tipos` (
  `raci_tipo_id` int NOT NULL AUTO_INCREMENT,
  `codigo` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`raci_tipo_id`),
  UNIQUE KEY `uk_raci_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__roles_organizacionales
CREATE TABLE IF NOT EXISTS `ml__roles_organizacionales` (
  `rol_organizacional_id` int NOT NULL AUTO_INCREMENT,
  `empresa_ambito_id` int NOT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`rol_organizacional_id`),
  KEY `fk_rol_empresa` (`empresa_ambito_id`),
  CONSTRAINT `fk_rol_empresa` FOREIGN KEY (`empresa_ambito_id`) REFERENCES `ml__ambitos_organizacionales` (`ambito_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__tareas
CREATE TABLE IF NOT EXISTS `ml__tareas` (
  `tarea_id` int NOT NULL AUTO_INCREMENT,
  `obligacion_id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `ciclo_id` int DEFAULT NULL,
  `periodo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_cierre` date DEFAULT NULL,
  `estado_tarea` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resultado` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tarea_id`),
  KEY `fk_tarea_obl` (`obligacion_id`),
  KEY `fk_tarea_ent` (`entidad_id`),
  CONSTRAINT `fk_tarea_ent` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_tarea_obl` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__tareas_ciclos
CREATE TABLE IF NOT EXISTS `ml__tareas_ciclos` (
  `ciclo_id` int NOT NULL AUTO_INCREMENT,
  `obligacion_id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `frecuencia_tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frecuencia_cada` int DEFAULT NULL,
  `proxima_fecha` date DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`ciclo_id`),
  UNIQUE KEY `uk_ciclo` (`obligacion_id`,`entidad_id`),
  KEY `fk_tc_ent` (`entidad_id`),
  CONSTRAINT `fk_tc_ent` FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`),
  CONSTRAINT `fk_tc_obl` FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__tareas_dependencias
CREATE TABLE IF NOT EXISTS `ml__tareas_dependencias` (
  `tarea_dependencia_id` int NOT NULL AUTO_INCREMENT,
  `tarea_origen_id` int NOT NULL,
  `tarea_destino_id` int NOT NULL,
  `condicion` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tarea_dependencia_id`),
  KEY `fk_td_origen` (`tarea_origen_id`),
  KEY `fk_td_destino` (`tarea_destino_id`),
  CONSTRAINT `fk_td_destino` FOREIGN KEY (`tarea_destino_id`) REFERENCES `ml__tareas` (`tarea_id`),
  CONSTRAINT `fk_td_origen` FOREIGN KEY (`tarea_origen_id`) REFERENCES `ml__tareas` (`tarea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.ml__tareas_evidencias
CREATE TABLE IF NOT EXISTS `ml__tareas_evidencias` (
  `tarea_evidencia_id` int NOT NULL AUTO_INCREMENT,
  `tarea_id` int NOT NULL,
  `tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `archivo_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_carga` datetime DEFAULT NULL,
  `tabla_estado_registro_id` smallint NOT NULL DEFAULT '1',
  PRIMARY KEY (`tarea_evidencia_id`),
  KEY `fk_te_tarea` (`tarea_id`),
  CONSTRAINT `fk_te_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `ml__tareas` (`tarea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.provincias
CREATE TABLE IF NOT EXISTS `provincias` (
  `provincia_id` int unsigned NOT NULL AUTO_INCREMENT,
  `provincia` varchar(150) DEFAULT NULL,
  `estado_registro_id` smallint unsigned DEFAULT '1',
  PRIMARY KEY (`provincia_id`),
  KEY `id_localidad` (`provincia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.xxx_conf__tablas_estados
CREATE TABLE IF NOT EXISTS `xxx_conf__tablas_estados` (
  `tabla_estado_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tabla_id` int unsigned NOT NULL,
  `tabla_estado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `color_id` tinyint NOT NULL DEFAULT '1',
  `estado_registro_id` int unsigned NOT NULL,
  `orden` smallint unsigned DEFAULT '1',
  `valor` smallint unsigned DEFAULT '1',
  PRIMARY KEY (`tabla_estado_id`),
  UNIQUE KEY `tabla_id` (`tabla_id`,`estado_registro_id`),
  KEY `estado_registro_id` (`estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.xxx_gestion__listas_precios
CREATE TABLE IF NOT EXISTS `xxx_gestion__listas_precios` (
  `lista_precio_id` int NOT NULL AUTO_INCREMENT,
  `lista_precio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `empresa_id` int DEFAULT NULL,
  `lista_base_id` int DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT '0',
  `metodo_calculo` enum('manual','automatico') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `margen_ganancia` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tipo` enum('venta','compra') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'venta',
  `estado` enum('activa','inactiva') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `f_vigencia_desde` date DEFAULT NULL,
  `f_vigencia_hasta` date DEFAULT NULL,
  `f_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_baja` datetime DEFAULT NULL,
  `f_actualizacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id_alta` int DEFAULT NULL,
  `usuario_id_modificacion` int DEFAULT NULL,
  `ip_origen` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`lista_precio_id`) USING BTREE,
  KEY `empresa_id` (`empresa_id`),
  KEY `estado` (`estado`),
  KEY `f_vigencia_desde` (`f_vigencia_desde`),
  KEY `f_vigencia_hasta` (`f_vigencia_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.xxx_gestion__listas_precios_ajustes
CREATE TABLE IF NOT EXISTS `xxx_gestion__listas_precios_ajustes` (
  `ajuste_id` int NOT NULL AUTO_INCREMENT,
  `lista_id` int NOT NULL,
  `tipo_ajuste` enum('lote','individual','automatico') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `proveedor_id` int DEFAULT NULL,
  `producto_id` int DEFAULT '0',
  `producto_categoria_id` int DEFAULT '0',
  `marca_id` int DEFAULT '0',
  `modelo_id` int DEFAULT '0',
  `submodelo_id` int DEFAULT '0',
  `porcentaje` decimal(7,4) DEFAULT NULL,
  `monto_fijo` decimal(15,2) DEFAULT NULL,
  `criterio` enum('aumento','reduccion','reemplazo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'aumento',
  `f_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_id_alta` int DEFAULT NULL,
  `ip_origen` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ajuste_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.xxx_gestion__listas_precios_productos
CREATE TABLE IF NOT EXISTS `xxx_gestion__listas_precios_productos` (
  `lista_precio_producto_id` int NOT NULL AUTO_INCREMENT,
  `lista_precio_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `precio_unitario` decimal(15,2) NOT NULL,
  `f_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `ajuste_id` int DEFAULT NULL,
  PRIMARY KEY (`lista_precio_producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla ps_gestion_casalucho.xxx_gestion__listas_precios_productos_historial
CREATE TABLE IF NOT EXISTS `xxx_gestion__listas_precios_productos_historial` (
  `lista_precio_producto_historial_id` int NOT NULL AUTO_INCREMENT,
  `lista_precio_producto_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `precio_unitario` decimal(15,2) NOT NULL,
  `f_alta` datetime DEFAULT CURRENT_TIMESTAMP,
  `f_baja` datetime DEFAULT NULL,
  `ajuste_id` int DEFAULT NULL,
  PRIMARY KEY (`lista_precio_producto_historial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para procedimiento ps_gestion_casalucho.sp_recalcular_compatibilidad_por_marca
DELIMITER //
CREATE PROCEDURE `sp_recalcular_compatibilidad_por_marca`(IN p_marca_id INT UNSIGNED)
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_producto_id BIGINT UNSIGNED;
    DECLARE cur CURSOR FOR
        SELECT DISTINCT producto_id
        FROM gestion__productos_compatibilidad
        WHERE marca_id = p_marca_id AND tabla_estado_registro_id = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    loop_marca: LOOP
        FETCH cur INTO v_producto_id;
        IF v_done THEN LEAVE loop_marca; END IF;
        CALL sp_recalcular_compatibilidad_producto(v_producto_id);
    END LOOP;
    CLOSE cur;
END//
DELIMITER ;

-- Volcando estructura para procedimiento ps_gestion_casalucho.sp_recalcular_compatibilidad_por_modelo
DELIMITER //
CREATE PROCEDURE `sp_recalcular_compatibilidad_por_modelo`(IN p_modelo_id INT UNSIGNED)
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_producto_id BIGINT UNSIGNED;
    DECLARE cur CURSOR FOR
        SELECT DISTINCT producto_id
        FROM gestion__productos_compatibilidad
        WHERE modelo_id = p_modelo_id AND tabla_estado_registro_id = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    loop_modelo: LOOP
        FETCH cur INTO v_producto_id;
        IF v_done THEN LEAVE loop_modelo; END IF;
        CALL sp_recalcular_compatibilidad_producto(v_producto_id);
    END LOOP;
    CLOSE cur;
END//
DELIMITER ;

-- Volcando estructura para procedimiento ps_gestion_casalucho.sp_recalcular_compatibilidad_por_submodelo
DELIMITER //
CREATE PROCEDURE `sp_recalcular_compatibilidad_por_submodelo`(IN p_submodelo_id INT UNSIGNED)
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_producto_id BIGINT UNSIGNED;
    DECLARE cur CURSOR FOR
        SELECT DISTINCT producto_id
        FROM gestion__productos_compatibilidad
        WHERE submodelo_id = p_submodelo_id AND tabla_estado_registro_id = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    loop_submodelo: LOOP
        FETCH cur INTO v_producto_id;
        IF v_done THEN LEAVE loop_submodelo; END IF;
        CALL sp_recalcular_compatibilidad_producto(v_producto_id);
    END LOOP;
    CLOSE cur;
END//
DELIMITER ;

-- Volcando estructura para procedimiento ps_gestion_casalucho.sp_recalcular_compatibilidad_producto
DELIMITER //
CREATE PROCEDURE `sp_recalcular_compatibilidad_producto`(IN p_producto_id BIGINT UNSIGNED)
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_marca_nombre VARCHAR(100);
    DECLARE v_modelo_nombre VARCHAR(100);
    DECLARE v_submodelo_nombre VARCHAR(100);
    DECLARE v_anio_desde SMALLINT;
    DECLARE v_anio_hasta SMALLINT;
    DECLARE v_anio_hasta_cap INT;
    DECLARE v_anio_actual INT;
    DECLARE v_texto TEXT DEFAULT '';
    DECLARE v_busqueda TEXT DEFAULT '';
    DECLARE v_item_texto TEXT;
    DECLARE v_y INT;

    DECLARE cur CURSOR FOR
        SELECT ma.marca_nombre, mo.modelo_nombre, sm.submodelo_nombre,
               pc.anio_desde, pc.anio_hasta
        FROM gestion__productos_compatibilidad pc
        INNER JOIN gestion__marcas ma ON pc.marca_id = ma.marca_id
        INNER JOIN gestion__modelos mo ON pc.modelo_id = mo.modelo_id
        LEFT JOIN gestion__submodelos sm ON pc.submodelo_id = sm.submodelo_id
        WHERE pc.producto_id = p_producto_id
          AND pc.tabla_estado_registro_id = 1
        ORDER BY ma.marca_nombre, mo.modelo_nombre;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    SET v_anio_actual = YEAR(CURDATE());

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_marca_nombre, v_modelo_nombre, v_submodelo_nombre, v_anio_desde, v_anio_hasta;
        IF v_done THEN
            LEAVE read_loop;
        END IF;

        SET v_item_texto = CONCAT(
            v_marca_nombre, ' ', v_modelo_nombre,
            IF(v_submodelo_nombre IS NOT NULL, CONCAT(' ', v_submodelo_nombre), ''),
            ' (', v_anio_desde, '-', IFNULL(v_anio_hasta, 'act.'), ')'
        );
        SET v_texto = IF(v_texto = '', v_item_texto, CONCAT(v_texto, ' | ', v_item_texto));
        SET v_busqueda = IF(v_busqueda = '', v_item_texto, CONCAT(v_busqueda, ' | ', v_item_texto));

        -- CAMBIO: tope en año actual (antes: año actual + 2). Una
        -- compatibilidad "vigente" (anio_hasta grande / 2100 por defecto)
        -- ya no expande años futuros que todavía no llegaron — el EVENT
        -- diario se encarga de sumarlos a medida que el calendario avanza.
        SET v_anio_hasta_cap = LEAST(IFNULL(v_anio_hasta, v_anio_actual), v_anio_actual);
        SET v_y = v_anio_desde;
        WHILE v_y <= v_anio_hasta_cap DO
            SET v_busqueda = CONCAT(v_busqueda, ' ', v_y);
            SET v_y = v_y + 1;
        END WHILE;

    END LOOP;
    CLOSE cur;

    UPDATE gestion__productos
       SET compatibilidad_texto = NULLIF(v_texto, ''),
           compatibilidad_busqueda = NULLIF(v_busqueda, '')
     WHERE producto_id = p_producto_id;
END//
DELIMITER ;

-- Volcando estructura para procedimiento ps_gestion_casalucho.sp_refrescar_compatibilidad_vigentes
DELIMITER //
CREATE PROCEDURE `sp_refrescar_compatibilidad_vigentes`()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_producto_id BIGINT UNSIGNED;

    -- Solo productos con AL MENOS UNA fila de compatibilidad "abierta":
    -- sin anio_hasta, o con anio_hasta en el año actual o más adelante.
    -- Una compatibilidad ya cerrada en el pasado (ej. "2015-2018") nunca
    -- gana años nuevos, así que no hace falta tocarla de nuevo.
    DECLARE cur CURSOR FOR
        SELECT DISTINCT producto_id
        FROM gestion__productos_compatibilidad
        WHERE tabla_estado_registro_id = 1
          AND (anio_hasta IS NULL OR anio_hasta >= YEAR(CURDATE()));

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    loop_vigentes: LOOP
        FETCH cur INTO v_producto_id;
        IF v_done THEN LEAVE loop_vigentes; END IF;
        CALL sp_recalcular_compatibilidad_producto(v_producto_id);
    END LOOP;
    CLOSE cur;
END//
DELIMITER ;

-- Volcando estructura para evento ps_gestion_casalucho.ev_refrescar_compatibilidad_anual
DELIMITER //
CREATE EVENT `ev_refrescar_compatibilidad_anual` ON SCHEDULE EVERY 1 DAY STARTS '2026-09-06 03:00:00' ON COMPLETION PRESERVE ENABLE DO BEGIN
    CALL sp_refrescar_compatibilidad_vigentes();
END//
DELIMITER ;

-- Volcando estructura para disparador ps_gestion_casalucho.trg_compat_after_delete
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='';
DELIMITER //
CREATE TRIGGER `trg_compat_after_delete` AFTER DELETE ON `gestion__productos_compatibilidad` FOR EACH ROW BEGIN
    CALL sp_recalcular_compatibilidad_producto(OLD.producto_id);
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador ps_gestion_casalucho.trg_compat_after_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='';
DELIMITER //
CREATE TRIGGER `trg_compat_after_insert` AFTER INSERT ON `gestion__productos_compatibilidad` FOR EACH ROW BEGIN
    CALL sp_recalcular_compatibilidad_producto(NEW.producto_id);
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador ps_gestion_casalucho.trg_compat_after_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='';
DELIMITER //
CREATE TRIGGER `trg_compat_after_update` AFTER UPDATE ON `gestion__productos_compatibilidad` FOR EACH ROW BEGIN
    CALL sp_recalcular_compatibilidad_producto(NEW.producto_id);
    -- Por si alguna vez se reasigna producto_id de una compatibilidad
    -- existente (no debería pasar en el flujo normal, pero por las dudas):
    -- recalcular también el producto viejo, que se quedó sin esa fila.
    IF NEW.producto_id <> OLD.producto_id THEN
        CALL sp_recalcular_compatibilidad_producto(OLD.producto_id);
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador ps_gestion_casalucho.trg_marca_after_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='';
DELIMITER //
CREATE TRIGGER `trg_marca_after_update` AFTER UPDATE ON `gestion__marcas` FOR EACH ROW BEGIN
    IF NOT (NEW.marca_nombre <=> OLD.marca_nombre) THEN
        CALL sp_recalcular_compatibilidad_por_marca(NEW.marca_id);
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador ps_gestion_casalucho.trg_modelo_after_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='';
DELIMITER //
CREATE TRIGGER `trg_modelo_after_update` AFTER UPDATE ON `gestion__modelos` FOR EACH ROW BEGIN
    IF NOT (NEW.modelo_nombre <=> OLD.modelo_nombre) THEN
        CALL sp_recalcular_compatibilidad_por_modelo(NEW.modelo_id);
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador ps_gestion_casalucho.trg_submodelo_after_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='';
DELIMITER //
CREATE TRIGGER `trg_submodelo_after_update` AFTER UPDATE ON `gestion__submodelos` FOR EACH ROW BEGIN
    IF NOT (NEW.submodelo_nombre <=> OLD.submodelo_nombre) THEN
        CALL sp_recalcular_compatibilidad_por_submodelo(NEW.submodelo_id);
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
