-- -----------------------------------------------------------------------------------------------------------------
-- 20261111_iibb_liquidacion_mensual_01.sql
-- Liquidación mensual de Ingresos Brutos (impuesto propio) en Control Fiscal (página 125 / 625), solapa "Ingresos Brutos".
-- Código: modules/gestion*/iibb_liquidacion_model.php.
--
-- 1. gestion__empresas_iibb_actividades (tabla 459): actividades de cada inscripción IIBB de la empresa, con código
--    (NAES), alícuota (%), mínimo mensual (opcional, previsto) y vigencia. Una principal por inscripción.
-- 2. gestion__iibb_posiciones (tabla 460) + gestion__iibb_posiciones_actividades (tabla 461): DDJJ mensual por
--    jurisdicción. Base = ventas fiscales del mes sin IVA (Convenio Multilateral: × coeficiente), editable por actividad;
--    menos retenciones (4) y percepciones (1) IIBB sufridas de la jurisdicción y el saldo a favor anterior.
--    Estados 5 Liquidada / 38 Anulada (catálogo 268–271).
-- 3. Funciones 3590 / 3591 (página 125) y 8590 / 8591 (página 625): liquidar_iibb y anular_liquidacion_iibb, con los
--    permisos de "Liquidar retenciones" / "Anular liquidación de retenciones".
-- 4. Tipo de obligación IMP_IIBB (I_LIQ, generado por IIBB_POSICION) en cada empresa con tipos de obligación.
-- 5. Conceptos contables IIBB_GASTO, IIBB_A_PAGAR e IIBB_SALDO_FAVOR (sin cuenta: se asignan en Tesorería >
--    Conceptos Contables) en cada empresa con conceptos.
--
-- Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0).
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Actividades de las inscripciones
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

-- 2. Posiciones mensuales
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

-- Tablas y estados (ids explícitos del bloque de gestión)
INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
SELECT v.id, v.nombre, v.descr, 2, v.tipo, 1, 1
FROM (SELECT 459 AS id, 'gestion__empresas_iibb_actividades' AS nombre, 'Actividades IIBB de las inscripciones de la empresa' AS descr, 1 AS tipo
      UNION ALL SELECT 460, 'gestion__iibb_posiciones', 'Liquidación mensual de Ingresos Brutos por jurisdicción', 2
      UNION ALL SELECT 461, 'gestion__iibb_posiciones_actividades', 'Actividades declaradas en cada liquidación de IIBB', 1) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` t WHERE t.`tabla_id` = v.id);

INSERT INTO `conf__tablas_estados_registros`
  (`tabla_estado_registro_id`, `tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`, `aplica_stock`)
SELECT v.id, v.tabla, v.estado, v.etiqueta, v.color, v.inicial, v.final, v.orden, 0
FROM (SELECT 268 AS id, 460 AS tabla, 5 AS estado, 'Liquidada' AS etiqueta, 3 AS color, 1 AS inicial, 0 AS final, 10 AS orden
      UNION ALL SELECT 269, 460, 38, 'Anulada', 2, 0, 1, 90
      UNION ALL SELECT 270, 459, 1, 'Activo', 3, 1, 0, 1
      UNION ALL SELECT 271, 459, 2, 'Inactivo', 2, 0, 0, 10) v
WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas_estados_registros` t WHERE t.`tabla_estado_registro_id` = v.id);

-- 3. Funciones de Control Fiscal
INSERT INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`,
                                       `nombre_funcion`, `accion_js`, `descripcion`,
                                       `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
SELECT v.id, v.pagina, 0, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (SELECT 3590 AS id, 125 AS pagina, 'Liquidar IIBB' AS nombre, 'liquidar_iibb' AS accion, 'Liquidación mensual de Ingresos Brutos' AS descr, 20 AS orden
      UNION ALL SELECT 3591, 125, 'Anular liquidación de IIBB', 'anular_liquidacion_iibb', 'Anula la liquidación mensual de Ingresos Brutos', 21
      UNION ALL SELECT 8590, 625, 'Liquidar IIBB', 'liquidar_iibb', 'Liquidación mensual de Ingresos Brutos', 20
      UNION ALL SELECT 8591, 625, 'Anular liquidación de IIBB', 'anular_liquidacion_iibb', 'Anula la liquidación mensual de Ingresos Brutos', 21) v
WHERE EXISTS (SELECT 1 FROM `conf__paginas` p WHERE p.`pagina_id` = v.pagina)
  AND NOT EXISTS (SELECT 1 FROM `conf__paginas_funciones` f WHERE f.`pagina_funcion_id` = v.id);

-- Permisos: los mismos perfiles que liquidan / anulan retenciones (3431 / 3432 y 8431 / 8432)
INSERT INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT epf.`empresa_id`, epf.`empresa_perfil_id`, m.nueva, 1
FROM `conf__empresas_perfiles_funciones` epf
INNER JOIN (SELECT 3431 AS base, 3590 AS nueva UNION ALL SELECT 3432, 3591
            UNION ALL SELECT 8431, 8590 UNION ALL SELECT 8432, 8591) m ON m.base = epf.`pagina_funcion_id`
WHERE epf.`asignado` = 1
  AND EXISTS (SELECT 1 FROM `conf__paginas_funciones` f WHERE f.`pagina_funcion_id` = m.nueva)
  AND NOT EXISTS (SELECT 1 FROM `conf__empresas_perfiles_funciones` x
                  WHERE x.`empresa_perfil_id` = epf.`empresa_perfil_id` AND x.`pagina_funcion_id` = m.nueva);

INSERT INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT pf.`perfil_id`, m.nueva, 1
FROM `conf__perfiles_funciones` pf
INNER JOIN (SELECT 3431 AS base, 3590 AS nueva UNION ALL SELECT 3432, 3591
            UNION ALL SELECT 8431, 8590 UNION ALL SELECT 8432, 8591) m ON m.base = pf.`pagina_funcion_id`
WHERE pf.`asignado` = 1
  AND EXISTS (SELECT 1 FROM `conf__paginas_funciones` f WHERE f.`pagina_funcion_id` = m.nueva)
  AND NOT EXISTS (SELECT 1 FROM `conf__perfiles_funciones` x WHERE x.`perfil_id` = pf.`perfil_id` AND x.`pagina_funcion_id` = m.nueva);

-- 4. Tipo de obligación del sistema para la I_LIQ de Ingresos Brutos
INSERT INTO `gestion__obligaciones_tipos` (`empresa_id`, `codigo`, `nombre`, `clase`, `rol_entidad_codigo`, `comprobante_tipo_codigo`,
                                           `permite_manual`, `origen_sistema`, `impuesto_tipo_id`, `orden`, `tabla_estado_registro_id`)
SELECT DISTINCT ot.`empresa_id`, 'IMP_IIBB', 'Ingresos Brutos - DDJJ mensual', 'OBLIGACION', NULL, 'I_LIQ', 0, 'IIBB_POSICION', 9, 53, 1
FROM `gestion__obligaciones_tipos` ot
WHERE NOT EXISTS (SELECT 1 FROM `gestion__obligaciones_tipos` x WHERE x.`empresa_id` = ot.`empresa_id` AND x.`codigo` = 'IMP_IIBB');

-- 5. Conceptos contables (la cuenta se asigna en Tesorería > Conceptos Contables)
INSERT INTO `gestion__empresas_cuentas_conceptos` (`empresa_id`, `concepto`, `cont_cuenta_id`, `tabla_estado_registro_id`)
SELECT e.`empresa_id`, c.concepto, NULL, 1
FROM (SELECT DISTINCT `empresa_id` FROM `gestion__empresas_cuentas_conceptos`) e
CROSS JOIN (SELECT 'IIBB_GASTO' AS concepto UNION ALL SELECT 'IIBB_A_PAGAR' UNION ALL SELECT 'IIBB_SALDO_FAVOR') c
WHERE NOT EXISTS (SELECT 1 FROM `gestion__empresas_cuentas_conceptos` x WHERE x.`empresa_id` = e.`empresa_id` AND x.`concepto` = c.concepto);

SELECT pagina_funcion_id, pagina_id, accion_js FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3590, 3591, 8590, 8591);
SELECT COUNT(*) AS tipos_imp_iibb FROM gestion__obligaciones_tipos WHERE codigo = 'IMP_IIBB';
