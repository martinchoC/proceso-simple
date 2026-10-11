-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.22-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
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


-- Volcando estructura de base de datos para gestion_multipyme
CREATE DATABASE IF NOT EXISTS `gestion_multipyme` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `gestion_multipyme`;

-- Volcando estructura para tabla gestion_multipyme.gestion__comprobantes_fiscales
CREATE TABLE IF NOT EXISTS `gestion__comprobantes_fiscales` (
  `comprobante_fiscal_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` tinyint(3) unsigned zerofill NOT NULL,
  `comprobante_fiscal` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`comprobante_fiscal_id`) USING BTREE,
  KEY `comprobante_grupo_id` (`comprobante_fiscal`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla gestion_multipyme.gestion__comprobantes_fiscales: ~96 rows (aproximadamente)
DELETE FROM `gestion__comprobantes_fiscales`;
INSERT INTO `gestion__comprobantes_fiscales` (`comprobante_fiscal_id`, `codigo`, `comprobante_fiscal`, `tabla_estado_registro_id`) VALUES
	(1, 001, 'FACTURAS A', 1),
	(2, 002, 'NOTAS DE DEBITO A', 1),
	(3, 003, 'NOTAS DE CREDITO A', 1),
	(4, 004, 'RECIBOS A', 1),
	(5, 005, 'NOTAS DE VENTA AL CONTADO A', 1),
	(6, 006, 'FACTURAS B', 1),
	(7, 007, 'NOTAS DE DEBITO B', 1),
	(8, 008, 'NOTAS DE CREDITO B', 1),
	(9, 009, 'RECIBOS B', 1),
	(10, 010, 'NOTAS DE VENTA AL CONTADO B', 1),
	(11, 011, 'FACTURAS C', 1),
	(12, 012, 'NOTAS DE DEBITO C', 1),
	(13, 013, 'NOTAS DE CREDITO C', 1),
	(14, 015, 'RECIBOS C', 1),
	(15, 016, 'NOTAS DE VENTA AL CONTADO C', 1),
	(16, 017, 'LIQUIDACION DE SERVICIOS PUBLICOS CLASE A', 2),
	(17, 018, 'LIQUIDACION DE SERVICIOS PUBLICOS CLASE B', 2),
	(18, 019, 'FACTURAS DE EXPORTACION', 2),
	(19, 020, 'NOTAS DE DEBITO POR OPERACIONES CON EL EXTERIOR', 2),
	(20, 021, 'NOTAS DE CREDITO POR OPERACIONES CON EL EXTERIOR', 2),
	(21, 022, 'FACTURAS - PERMISO EXPORTACION SIMPLIFICADO - DTO.', 2),
	(22, 023, 'COMPROBANTES ?A? DE COMPRA PRIMARIA PARA EL SECTOR', 2),
	(23, 024, 'COMPROBANTES ?A? DE CONSIGNACION PRIMARIA PARA EL ', 2),
	(24, 025, 'COMPROBANTES ?B? DE COMPRA PRIMARIA PARA EL SECTOR', 2),
	(25, 026, 'COMPROBANTES ?B? DE CONSIGNACION PRIMARIA PARA EL ', 2),
	(26, 027, 'LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE A', 2),
	(27, 028, 'LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE B', 2),
	(28, 029, 'LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE C', 2),
	(29, 030, 'COMPROBANTES DE COMPRA DE BIENES USADOS', 2),
	(30, 031, 'MANDATO - CONSIGNACION', 2),
	(31, 032, 'COMPROBANTES PARA RECICLAR MATERIALES', 2),
	(32, 033, 'LIQUIDACION PRIMARIA DE GRANOS', 2),
	(33, 034, 'COMPROBANTES A DEL APARTADO A  INCISO F)  R.G. N? ', 2),
	(34, 035, 'COMPROBANTES B DEL ANEXO I, APARTADO A, INC. F), R', 2),
	(35, 036, 'COMPROBANTES C DEL Anexo I, Apartado A, INC.F), R.', 2),
	(36, 037, 'NOTAS DE DEBITO O DOCUMENTO EQUIVALENTE QUE CUMPLA', 2),
	(37, 038, 'NOTAS DE CREDITO O DOCUMENTO EQUIVALENTE QUE CUMPL', 2),
	(38, 039, 'OTROS COMPROBANTES A QUE CUMPLEN CON LA R G  1415', 2),
	(39, 040, 'OTROS COMPROBANTES B QUE CUMPLAN CON LA R.G. N° 14', 2),
	(40, 041, 'OTROS COMPROBANTES C QUE CUMPLAN CON LA R.G. N? 14', 2),
	(41, 043, 'NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSI', 2),
	(42, 044, 'NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSI', 2),
	(43, 045, 'NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSIT', 2),
	(44, 046, 'NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSIT', 2),
	(45, 047, 'NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSIT', 2),
	(46, 048, 'NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSI', 2),
	(47, 049, 'COMPROBANTES DE COMPRA DE BIENES NO REGISTRABLES A', 2),
	(48, 050, 'RECIBO FACTURA A  REGIMEN DE FACTURA DE CREDITO ', 2),
	(49, 051, 'FACTURAS M', 1),
	(50, 052, 'NOTAS DE DEBITO M', 1),
	(51, 053, 'NOTAS DE CREDITO M', 1),
	(52, 054, 'RECIBOS M', 2),
	(53, 055, 'NOTAS DE VENTA AL CONTADO M', 2),
	(54, 056, 'COMPROBANTES M DEL ANEXO I  APARTADO A  INC F) R.G', 2),
	(55, 057, 'OTROS COMPROBANTES M QUE CUMPLAN CON LA R.G. N? 14', 2),
	(56, 058, 'CUENTAS DE VENTA Y LIQUIDO PRODUCTO M', 2),
	(57, 059, 'LIQUIDACIONES M', 2),
	(58, 060, 'CUENTAS DE VENTA Y LIQUIDO PRODUCTO A', 2),
	(59, 061, 'CUENTAS DE VENTA Y LIQUIDO PRODUCTO B', 2),
	(60, 063, 'LIQUIDACIONES A', 2),
	(61, 064, 'LIQUIDACIONES B', 2),
	(62, 066, 'DESPACHO DE IMPORTACION', 2),
	(63, 068, 'LIQUIDACION C', 2),
	(64, 070, 'RECIBOS FACTURA DE CREDITO', 2),
	(65, 080, 'INFORME DIARIO DE CIERRE (ZETA) - CONTROLADORES FI', 2),
	(66, 081, 'TIQUE FACTURA A   ', 2),
	(67, 082, 'TIQUE FACTURA B', 2),
	(68, 083, 'TIQUE', 2),
	(69, 088, 'REMITO ELECTRONICO', 2),
	(70, 089, 'RESUMEN DE DATOS', 2),
	(71, 090, 'OTROS COMPROBANTES - DOCUMENTOS EXCEPTUADOS - NOTA', 2),
	(72, 091, 'REMITOS R', 2),
	(73, 099, 'OTROS COMPROBANTES QUE NO CUMPLEN O EST?N EXCEPTUA', 2),
	(74, 110, 'TIQUE NOTA DE CREDITO ', 2),
	(75, 111, 'TIQUE FACTURA C', 2),
	(76, 112, ' TIQUE NOTA DE CREDITO A', 2),
	(77, 113, 'TIQUE NOTA DE CREDITO B', 2),
	(78, 114, 'TIQUE NOTA DE CREDITO C', 2),
	(79, 115, 'TIQUE NOTA DE DEBITO A', 2),
	(80, 116, 'TIQUE NOTA DE DEBITO B', 2),
	(81, 117, 'TIQUE NOTA DE DEBITO C', 2),
	(82, 118, 'TIQUE FACTURA M', 2),
	(83, 119, 'TIQUE NOTA DE CREDITO M', 2),
	(84, 120, 'TIQUE NOTA DE DEBITO M', 2),
	(85, 201, 'FACTURA DE CREDITO ELECTRONICA MiPyMEs (FCE) A', 2),
	(86, 202, 'NOTA DE DEBITO ELECTRONICA MiPyMEs (FCE) A', 2),
	(87, 203, 'NOTA DE CREDITO ELECTRONICA MiPyMEs (FCE) A', 2),
	(88, 206, 'FACTURA DE CREDITO ELECTRONICA MiPyMEs (FCE) B', 2),
	(89, 207, 'NOTA DE DEBITO ELECTRONICA MiPyMEs (FCE) B', 2),
	(90, 208, 'NOTA DE CREDITO ELECTRONICA MiPyMEs (FCE) B', 2),
	(91, 211, 'FACTURA DE CREDITO ELECTRONICA MiPyMEs (FCE) C', 2),
	(92, 212, 'NOTA DE DEBITO ELECTRONICA MiPyMEs (FCE) C', 2),
	(93, 213, 'NOTA DE CREDITO ELECTRONICA MiPyMEs (FCE) C', 2),
	(94, 255, 'LIQUIDACION SECUNDARIA DE GRANOS', 2),
	(95, 255, 'CERTIFICACION ELECTRONICA (GRANOS)', 2),
	(96, 255, 'REMITO ELECTR?NICO C?RNICO ', 2);

-- Volcando estructura para tabla gestion_multipyme.gestion__condiciones_fiscales
CREATE TABLE IF NOT EXISTS `gestion__condiciones_fiscales` (
  `condicion_fiscal_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `condicion_fiscal` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condicion_fiscal_codigo` char(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `condicion_fiscal_descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`condicion_fiscal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla gestion_multipyme.gestion__condiciones_fiscales: ~6 rows (aproximadamente)
DELETE FROM `gestion__condiciones_fiscales`;
INSERT INTO `gestion__condiciones_fiscales` (`condicion_fiscal_id`, `condicion_fiscal`, `condicion_fiscal_codigo`, `condicion_fiscal_descripcion`, `tabla_estado_registro_id`) VALUES
	(1, 'Responsable Inscripto', 'RI', 'Inscripto en IVA, emite facturas A a otros RI, B a CF/Monotributistas/Exentos, y M según AFIP.', 1),
	(2, 'Consumidor Final', 'CF', 'Persona física que adquiere bienes o servicios para uso personal.', 1),
	(3, 'Monotributista', 'MT', 'Pequeño contribuyente inscripto en el Régimen Simplificado.', 1),
	(4, 'Exento en IVA', 'EX', 'Exento por normativa en IVA, emite factura C.', 1),
	(5, 'No Categorizado', 'NC', 'Sujeto sin CUIT válido o no categorizado en AFIP.', 1),
	(6, 'Sujeto del Exterior', 'EXT', 'Cliente o proveedor del exterior, factura tipo E.', 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
