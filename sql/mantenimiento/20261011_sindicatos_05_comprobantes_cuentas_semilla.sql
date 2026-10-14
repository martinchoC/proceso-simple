-- ==============================================================================
-- Script: 20261011_sindicatos_05_comprobantes_cuentas_semilla.sql
-- Propósito: Siembra de sucursal, PV, cuentas contables, conceptos contables,
--            grupos/subgrupos y tipos de comprobantes sindicales para Empresa 3
--            (CEC Patagones / AECBB).
-- Especificación: docs/sindicatos/01_especificacion_modulo_sindicatos.md (§7.1, §7.2)
-- ==============================================================================

USE gestion_multipyme;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Moneda base para Empresa 3 (ARS)
-- ------------------------------------------------------------------------------
INSERT INTO `gestion__monedas` (
    `empresa_id`, `codigo`, `moneda`, `simbolo`, `es_moneda_base`,
    `cantidad_decimales`, `cotizacion_actual`, `permite_editar_cotizacion`, `orden`, `tabla_estado_registro_id`
) VALUES (
    3, 'ARS', 'Peso Argentino', '$', 1, 2, 1.000000, 0, 1, 1
) ON DUPLICATE KEY UPDATE `es_moneda_base` = 1;

-- ------------------------------------------------------------------------------
-- 2. Sucursal, Boca y Punto de Venta para Empresa 3
-- ------------------------------------------------------------------------------
INSERT INTO `gestion__sucursales` (
    `sucursal_id`, `empresa_id`, `sucursal_tipo_id`, `sucursal_nombre`, `descripcion`, `localidad_id`, `direccion`, `telefono`, `email`, `tabla_estado_registro_id`
) VALUES (
    301, 3, 5, 'Sede Central', 'Sede Sindical y Administrativa', 26985, 'Rodríguez 143, Bahía Blanca', '2914550000', 'info@aecbb.org.ar', 1
) ON DUPLICATE KEY UPDATE `sucursal_nombre` = VALUES(`sucursal_nombre`);

INSERT INTO `gestion__bocas` (
    `boca_id`, `empresa_id`, `sucursal_id`, `es_deposito`, `boca_nombre`, `codigo`, `descripcion`, `localidad_id`, `permite_ingresos`, `permite_egresos`, `permite_stock_negativo`, `es_principal`, `orden`, `tabla_estado_registro_id`
) VALUES (
    301, 3, 301, 0, 'Caja / Recaudación Sindical', 'BOC-SINDI', 'Boca de cobranzas y deudas sindicales', 26985, 1, 1, 1, 1, 1, 1
) ON DUPLICATE KEY UPDATE `boca_nombre` = VALUES(`boca_nombre`);

INSERT INTO `gestion__puntos_venta` (
    `punto_venta_id`, `empresa_id`, `sucursal_id`, `boca_id`, `nombre`, `descripcion`, `codigo_fiscal`, `es_web`, `fecha_creacion`, `tabla_estado_registro_id`
) VALUES (
    301, 3, 301, 301, 'Sede Sindical PV 1', 'Punto de venta emisión comprobantes sindicales', 1, 0, NOW(), 1
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- ------------------------------------------------------------------------------
-- 3. Tipos de Asiento para Empresa 3 (MAN y AUT)
-- ------------------------------------------------------------------------------
INSERT INTO `gestion__cont_tipos_asientos` (
    `empresa_id`, `codigo`, `cont_tipo_asiento`, `descripcion`, `origen`, `modulo_origen`, `tabla_estado_registro_id`
) VALUES 
(3, 'MAN', 'Asiento Manual', 'Asientos manuales contables', 'manual', NULL, 1),
(3, 'AUT', 'Automático', 'Asientos automáticos de gestión y DDJJ', 'automatico', 'sindicatos', 1)
ON DUPLICATE KEY UPDATE `cont_tipo_asiento` = VALUES(`cont_tipo_asiento`);

-- ------------------------------------------------------------------------------
-- 4. Plan de Cuentas Contables básico para Empresa 3 (gestion__cont_cuentas)
-- ------------------------------------------------------------------------------
INSERT INTO `gestion__cont_cuentas` (
    `cont_cuenta_id`, `empresa_id`, `comprobante_id`, `codigo`, `nombre`, `naturaleza`, `cuenta_padre_id`, `nivel`, `orden`, `es_imputable`, `es_caja_banco`, `tabla_estado_registro_id`
) VALUES
(3001, 3, 0, '1', 'ACTIVO', 'D', NULL, 1, 1, 0, 0, 1),
(3002, 3, 0, '1.1', 'ACTIVO CORRIENTE', 'D', 3001, 2, 2, 0, 0, 1),
(3003, 3, 0, '1.1.2', 'CRÉDITOS', 'D', 3002, 3, 3, 0, 0, 1),
(3004, 3, 0, '1.1.2.01', 'CRÉDITOS POR APORTES', 'D', 3003, 4, 4, 0, 0, 1),
(3005, 3, 0, '1.1.2.01.001', 'Deudores por Aportes Sindicales', 'D', 3004, 5, 5, 1, 0, 1),

(3010, 3, 0, '4', 'RECURSOS Y RESULTADOS', 'A', NULL, 1, 10, 0, 0, 1),
(3011, 3, 0, '4.1', 'INGRESOS POR APORTES GREMIALES', 'A', 3010, 2, 11, 0, 0, 1),
(3012, 3, 0, '4.1.1', 'APORTES CCT 130/75', 'A', 3011, 3, 12, 0, 0, 1),
(3013, 3, 0, '4.1.1.01.001', 'Aportes Fondo Social Art. 100', 'A', 3012, 4, 13, 1, 0, 1),
(3014, 3, 0, '4.1.1.01.002', 'Aportes Cuota Sindical Art. 101', 'A', 3012, 4, 14, 1, 0, 1),
(3015, 3, 0, '4.2', 'INGRESOS FINANCIEROS', 'A', 3010, 2, 15, 0, 0, 1),
(3016, 3, 0, '4.2.1.01.001', 'Intereses Resarcitorios Ganados', 'A', 3015, 3, 16, 1, 0, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `naturaleza` = VALUES(`naturaleza`);

-- ------------------------------------------------------------------------------
-- 5. Conceptos Contables de la Empresa (gestion__empresas_cuentas_conceptos)
-- ------------------------------------------------------------------------------
INSERT INTO `gestion__empresas_cuentas_conceptos` (
    `empresa_id`, `concepto`, `cont_cuenta_id`, `tabla_estado_registro_id`, `fecha_creacion`
) VALUES
(3, 'SINDI_DEUDORES_APORTES', 3005, 1, NOW()),
(3, 'SINDI_APORTE_ART100',      3013, 1, NOW()),
(3, 'SINDI_APORTE_ART101',      3014, 1, NOW()),
(3, 'SINDI_INTERESES_GANADOS',  3016, 1, NOW())
ON DUPLICATE KEY UPDATE `cont_cuenta_id` = VALUES(`cont_cuenta_id`);

-- ------------------------------------------------------------------------------
-- 6. Grupo, Subgrupo y Tipos de Comprobante para Empresa 3 (§7.1)
-- ------------------------------------------------------------------------------
INSERT INTO `gestion__comprobantes_grupos` (
    `comprobante_grupo_id`, `empresa_id`, `comprobante_grupo`, `codigo`, `orden`, `tabla_estado_registro_id`
) VALUES (
    31, 3, 'Sindical', 'SINDI', 1, 1
) ON DUPLICATE KEY UPDATE `comprobante_grupo` = VALUES(`comprobante_grupo`);

INSERT INTO `gestion__comprobantes_subgrupos` (
    `comprobante_subgrupo_id`, `empresa_id`, `comprobante_grupo_id`, `tabla_id`,
    `comprobante_subgrupo`, `codigo`, `comentario`, `orden`, `tabla_estado_registro_id`, `origen_asiento`
) VALUES (
    301, 3, 31, 524, 'Declaraciones Juradas', 'DDJJ', 'Comprobantes emitidos por DDJJ y reversiones', 1, 1, 'AUTO'
) ON DUPLICATE KEY UPDATE `comprobante_subgrupo` = VALUES(`comprobante_subgrupo`), `tabla_id` = 524;

INSERT INTO `gestion__comprobantes_tipos` (
    `comprobante_tipo_id`, `empresa_id`, `comprobante_grupo_id`, `comprobante_subgrupo_id`, `comprobante_fiscal_id`,
    `impacta_stock`, `impacta_contabilidad`, `impacta_ctacte`, `comprobante_tipo`, `codigo`, `signo`, `orden`, `tabla_estado_registro_id`
) VALUES
(301, 3, 31, 301, 0, 0, 1, 1, 'DDJJ - Fondo Social Art. 100',   'DFS', '+', 1, 1),
(302, 3, 31, 301, 0, 0, 1, 1, 'DDJJ - Cuota Sindical Art. 101',  'DCS', '+', 2, 1),
(303, 3, 31, 301, 0, 0, 1, 1, 'Reversión DDJJ - Fondo Social',    'RFS', '-', 3, 1),
(304, 3, 31, 301, 0, 0, 1, 1, 'Reversión DDJJ - Cuota Sindical',   'RCS', '-', 4, 1)
ON DUPLICATE KEY UPDATE
    `comprobante_tipo` = VALUES(`comprobante_tipo`),
    `signo` = VALUES(`signo`),
    `impacta_ctacte` = 1,
    `impacta_contabilidad` = 1;

-- ------------------------------------------------------------------------------
-- 7. Tipos de Afiliación Sindical (§5.6)
-- ------------------------------------------------------------------------------
INSERT INTO `sindi__afiliaciones_tipos` (
    `afiliacion_tipo_id`, `empresa_id`, `codigo`, `nombre`, `grupo`, `alta`,
    `requiere_empleador`, `paga_cuota_directa`, `importe_cuota`, `requiere_titular`, `tabla_estado_registro_id`
) VALUES
(1, 3, 'ACTIVIDAD',  'Afiliación por Actividad (CCT 130/75)', 'TITULAR',  'AUTOMATICA', 1, 0, 0.00, 0, 1),
(2, 3, 'VOLUNTARIA', 'Afiliación Voluntaria',                 'TITULAR',  'SOLICITUD',  0, 1, 0.00, 0, 1),
(3, 3, 'JUBILADO',   'Afiliación Jubilados',                  'TITULAR',  'SOLICITUD',  0, 1, 0.00, 0, 1),
(4, 3, 'FAMILIAR',   'Grupo Familiar',                        'FAMILIAR', 'SOLICITUD',  0, 0, 0.00, 1, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `alta` = VALUES(`alta`),
    `requiere_empleador` = VALUES(`requiere_empleador`);

SET FOREIGN_KEY_CHECKS = 1;
