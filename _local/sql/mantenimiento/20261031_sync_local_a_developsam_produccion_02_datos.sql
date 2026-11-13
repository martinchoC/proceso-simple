-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================
-- Sincronizacion LOCAL (gestion_multipyme) -> PRODUCCION multipyme (ps_gestion_developsam_produccion)
-- Script 02: DATOS
-- 2026-10-31
-- =====================================================================
-- Requiere 20261031_sync_local_a_developsam_produccion_01_estructura.sql.
--
-- Comparacion fila por fila (por PK) del volcado developsam_produccion.sql contra local:
-- la configuracion del sistema (conf__tablas, estados, paginas, funciones, perfiles,
-- permisos, tipos de comprobante, plan de cuentas, obligaciones_tipos, dashboard, bancos)
-- es IDENTICA. Lo unico que falta son las filas de gestion__obligaciones_pago: en
-- produccion la tabla quedo con la forma vieja y vacia (ver script 01), pero los
-- comprobantes 538 / 542 / 544 / 546 de la empresa 2 (OBL / I_LIQ, tabla_origen_id 286)
-- ya estan y apuntan a las obligaciones 10-13. Se insertan con el mismo id, solo si el
-- comprobante existe en produccion con ese origen y la obligacion falta.
-- (El comprobante 537 apunta a la obligacion 9, que tampoco existe en local: no se repara.)
-- Son datos del simulador ("SIM") que ya estaban en la copia de empresa 2.
--
-- NO se copia (produccion tiene cambios propios posteriores a la copia, se respetan):
--   conf__empresas: estado de las empresas 2 / 3 / 7 (Inactivas en produccion),
--     domicilio e iibb_regimen_id de la 6.
--   gestion__empresas_parametros (empresa 6: regimen IIBB, inicio de actividades,
--     mes de cierre 6) y gestion__empresas_impuestos_config 17-20 (f_desde / estado).
--   Datos operativos cargados en produccion (comprobantes, facturas, asientos, extractos,
--     reglas bancarias aprendidas 17-18, integracion ARCA 4) y el texto de
--     compatibilidad de 336 productos (derivado, lo recalculan los sp_*).
--
-- Una sola transaccion. Reanudable.
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261031_sync_local_a_developsam_produccion_02_datos.sql
-- =====================================================================

SET NAMES utf8mb4;
SET @OLD_TIME_ZONE = @@TIME_ZONE;
SET TIME_ZONE = '+00:00';

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS `_sync_src`;
CREATE TEMPORARY TABLE `_sync_src` LIKE `gestion__obligaciones_pago`;
INSERT INTO `_sync_src` (`obligacion_id`, `empresa_id`, `obligacion_tipo_id`, `origen`, `origen_registro_id`, `importacion_id`, `sucursal_id`, `comprobante_tipo_id`, `comprobante_pv`, `comprobante_nro`, `comprobante_id`, `entidad_id`, `f_emision`, `f_contabilidad`, `f_vto`, `f_vto_2`, `impuesto_tipo_id`, `jurisdiccion_id`, `periodo_fiscal`, `cuota_nro`, `descripcion`, `nro_ddjj`, `nro_vep`, `referencia`, `obligacion_rectificada_id`, `cont_cuenta_id`, `cont_cuenta_debe_id`, `aprobaciones_requeridas`, `moneda_id`, `tipo_cambio`, `importe_total`, `observaciones`, `motivo_anulacion`, `tabla_estado_registro_id`, `fecha_creacion`, `fecha_modificacion`, `usuario_id`, `usuario_modificacion_id`) VALUES (10,2,1,'MANUAL',NULL,NULL,1,421,1,2,538,8813,'2026-10-30','2026-10-30','2026-10-30',NULL,NULL,NULL,'2026-10',NULL,'Sueldo 2026-10 - SIM Gómez, Ana María',NULL,NULL,'Recibo 2026-10-2',NULL,321,800,0,1,1.000000,720500.50,NULL,NULL,40,'2026-10-30 22:16:51','2026-10-30 22:17:30',1,1);
INSERT INTO `_sync_src` (`obligacion_id`, `empresa_id`, `obligacion_tipo_id`, `origen`, `origen_registro_id`, `importacion_id`, `sucursal_id`, `comprobante_tipo_id`, `comprobante_pv`, `comprobante_nro`, `comprobante_id`, `entidad_id`, `f_emision`, `f_contabilidad`, `f_vto`, `f_vto_2`, `impuesto_tipo_id`, `jurisdiccion_id`, `periodo_fiscal`, `cuota_nro`, `descripcion`, `nro_ddjj`, `nro_vep`, `referencia`, `obligacion_rectificada_id`, `cont_cuenta_id`, `cont_cuenta_debe_id`, `aprobaciones_requeridas`, `moneda_id`, `tipo_cambio`, `importe_total`, `observaciones`, `motivo_anulacion`, `tabla_estado_registro_id`, `fecha_creacion`, `fecha_modificacion`, `usuario_id`, `usuario_modificacion_id`) VALUES (11,2,9,'IVA_POSICION',NULL,NULL,1,46,1,1,542,8814,'2026-10-31','2026-10-31','2026-10-31',NULL,7,NULL,'2026-10',NULL,'IVA - Posición mensual 2026-10','F.2002 SIM',NULL,NULL,NULL,332,NULL,0,1,1.000000,98062.89,NULL,NULL,40,'2026-10-30 22:16:56','2026-10-30 22:16:57',1,1);
INSERT INTO `_sync_src` (`obligacion_id`, `empresa_id`, `obligacion_tipo_id`, `origen`, `origen_registro_id`, `importacion_id`, `sucursal_id`, `comprobante_tipo_id`, `comprobante_pv`, `comprobante_nro`, `comprobante_id`, `entidad_id`, `f_emision`, `f_contabilidad`, `f_vto`, `f_vto_2`, `impuesto_tipo_id`, `jurisdiccion_id`, `periodo_fiscal`, `cuota_nro`, `descripcion`, `nro_ddjj`, `nro_vep`, `referencia`, `obligacion_rectificada_id`, `cont_cuenta_id`, `cont_cuenta_debe_id`, `aprobaciones_requeridas`, `moneda_id`, `tipo_cambio`, `importe_total`, `observaciones`, `motivo_anulacion`, `tabla_estado_registro_id`, `fecha_creacion`, `fecha_modificacion`, `usuario_id`, `usuario_modificacion_id`) VALUES (12,2,10,'PERCEPCIONES_IIBB',NULL,NULL,1,46,1,2,544,8816,'2026-10-30','2026-10-30','2026-10-30',NULL,6,NULL,'2026-10',NULL,'Percepciones IIBB Río Negro (DGR) 2026-10',NULL,NULL,NULL,NULL,1273,1273,0,1,1.000000,9502.62,NULL,NULL,40,'2026-10-30 22:16:57','2026-10-30 22:16:58',1,1);
INSERT INTO `_sync_src` (`obligacion_id`, `empresa_id`, `obligacion_tipo_id`, `origen`, `origen_registro_id`, `importacion_id`, `sucursal_id`, `comprobante_tipo_id`, `comprobante_pv`, `comprobante_nro`, `comprobante_id`, `entidad_id`, `f_emision`, `f_contabilidad`, `f_vto`, `f_vto_2`, `impuesto_tipo_id`, `jurisdiccion_id`, `periodo_fiscal`, `cuota_nro`, `descripcion`, `nro_ddjj`, `nro_vep`, `referencia`, `obligacion_rectificada_id`, `cont_cuenta_id`, `cont_cuenta_debe_id`, `aprobaciones_requeridas`, `moneda_id`, `tipo_cambio`, `importe_total`, `observaciones`, `motivo_anulacion`, `tabla_estado_registro_id`, `fecha_creacion`, `fecha_modificacion`, `usuario_id`, `usuario_modificacion_id`) VALUES (13,2,11,'RETENCIONES_LIQ',NULL,NULL,1,46,1,3,546,8815,'2026-10-30','2026-10-30','2026-10-30',NULL,16,2,'2026-10',NULL,'Retencion IIBB practicada Buenos Aires (ARBA) 2026-10','SIM-ARBA',NULL,NULL,NULL,333,NULL,0,1,1.000000,326.79,NULL,NULL,40,'2026-10-30 22:16:59','2026-10-30 22:16:59',1,1);

INSERT INTO `gestion__obligaciones_pago`
SELECT s.*
FROM `_sync_src` s
JOIN `gestion__comprobantes` c ON c.comprobante_id = s.comprobante_id
     AND c.tabla_origen_id = 286 AND c.registro_origen_id = s.obligacion_id AND c.empresa_id = s.empresa_id
WHERE NOT EXISTS (SELECT 1 FROM `gestion__obligaciones_pago` o WHERE o.obligacion_id = s.obligacion_id);

DROP TEMPORARY TABLE IF EXISTS `_sync_src`;

COMMIT;

SET TIME_ZONE = @OLD_TIME_ZONE;

-- Control (solo lectura): comprobantes de obligaciones sin su fila (esperado: solo el 537)
SELECT c.comprobante_id, c.registro_origen_id, c.empresa_id
FROM gestion__comprobantes c
LEFT JOIN gestion__obligaciones_pago o ON o.obligacion_id = c.registro_origen_id
WHERE c.tabla_origen_id = 286 AND o.obligacion_id IS NULL;
