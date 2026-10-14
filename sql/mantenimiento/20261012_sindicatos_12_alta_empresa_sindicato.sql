-- ==============================================================================
-- 20261012_sindicatos_12_alta_empresa_sindicato.sql
-- Módulo 6 (Sindicatos): configuración contable y de emisión de una empresa sindicato.
--
-- Parámetro: @emp = empresa del sindicato (en la base local, 7 = Asociación Empleados de
-- Comercio). Para otra empresa, cambiar el SET de abajo y volver a correr.
--
-- Crea, solo lo que falte (por empresa + código, sin ids fijos):
--   1. Moneda base ARS.
--   2. Sucursal (usa la primera activa; si no hay, crea "Sede Central"), boca y punto de
--      venta para los comprobantes sindicales (no fiscales).
--   3. Tipos de asiento MAN y AUT.
--   4. Plan de cuentas de asociación sindical (entidad sin fines de lucro, RT 11):
--      Activo, Pasivo, Patrimonio Neto, Recursos y Gastos.
--   5. Conceptos contables SINDI_* y los generales de tesorería que aplican a un sindicato
--      (exento de IVA: sin conceptos de IVA ni mercaderías).
--   6. Grupo "Sindical", subgrupo DDJJ (tabla 524) y los tipos DFS, DCS, RFS, RCS, VOL, NDI,
--      REC, ACT, PLC, RPL, habilitados en el punto de venta.
--   7. Cuentas de tesorería (caja, bancos, recaudación por canal, valores) y su asignación a
--      los medios de cobro del sindicato (sindi__medios_pago).
--   8. Tipos de comprobante y cuenta de recurso en los conceptos de aporte (sindi__conceptos).
--
-- Corre en MariaDB 10.4 y MySQL 8. Reanudable.
-- ==============================================================================

SET NAMES utf8mb4;
SET @emp := 7;

START TRANSACTION;

-- ------------------------------------------------------------------------------
-- 1. Moneda base
-- ------------------------------------------------------------------------------
INSERT INTO gestion__monedas (empresa_id, codigo, moneda, simbolo, es_moneda_base, cantidad_decimales,
                              cotizacion_actual, permite_editar_cotizacion, orden, tabla_estado_registro_id)
SELECT @emp, 'ARS', 'Peso Argentino', '$', 1, 2, 1.000000, 0, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gestion__monedas WHERE empresa_id = @emp AND codigo = 'ARS');
SET @moneda := (SELECT moneda_id FROM gestion__monedas WHERE empresa_id = @emp AND codigo = 'ARS');

-- ------------------------------------------------------------------------------
-- 2. Sucursal, boca y punto de venta de emisión sindical
-- ------------------------------------------------------------------------------
INSERT INTO gestion__sucursales (empresa_id, sucursal_tipo_id, sucursal_nombre, descripcion, tabla_estado_registro_id)
SELECT @emp, 5, 'Sede Central', 'Sede gremial y administrativa', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gestion__sucursales WHERE empresa_id = @emp AND tabla_estado_registro_id = 1);
SET @suc := (SELECT MIN(sucursal_id) FROM gestion__sucursales WHERE empresa_id = @emp AND tabla_estado_registro_id = 1);

INSERT INTO gestion__bocas (empresa_id, sucursal_id, es_deposito, boca_nombre, codigo, descripcion, localidad_id,
                            permite_ingresos, permite_egresos, permite_stock_negativo, es_principal, orden, tabla_estado_registro_id)
SELECT @emp, @suc, 0, 'Recaudación Sindical', 'BOC-SINDI', 'Boca de comprobantes y cobranzas sindicales',
       (SELECT localidad_id FROM gestion__sucursales WHERE sucursal_id = @suc), 1, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gestion__bocas WHERE empresa_id = @emp AND sucursal_id = @suc AND codigo = 'BOC-SINDI');
SET @boca := (SELECT boca_id FROM gestion__bocas WHERE empresa_id = @emp AND sucursal_id = @suc AND codigo = 'BOC-SINDI');

INSERT INTO gestion__puntos_venta (empresa_id, sucursal_id, boca_id, nombre, descripcion, codigo_fiscal, es_web, fecha_creacion, tabla_estado_registro_id)
SELECT @emp, @suc, @boca, 'Sede Sindical PV 1', 'Emisión de comprobantes sindicales (no fiscales)', 1, 0, NOW(), 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gestion__puntos_venta WHERE empresa_id = @emp AND boca_id = @boca);
SET @pv := (SELECT MIN(punto_venta_id) FROM gestion__puntos_venta WHERE empresa_id = @emp AND boca_id = @boca);

-- ------------------------------------------------------------------------------
-- 3. Tipos de asiento
-- ------------------------------------------------------------------------------
INSERT INTO gestion__cont_tipos_asientos (empresa_id, codigo, cont_tipo_asiento, descripcion, origen, modulo_origen, tabla_estado_registro_id)
SELECT @emp, t.codigo, t.nombre, t.descr, t.origen, t.modulo, 1
FROM (SELECT 'MAN' AS codigo, 'Asiento Manual' AS nombre, 'Asientos manuales contables' AS descr, 'manual' AS origen, NULL AS modulo
      UNION ALL SELECT 'AUT', 'Automático', 'Asientos automáticos de gestión, DDJJ y cobranzas', 'automatico', 'sindicatos') t
WHERE NOT EXISTS (SELECT 1 FROM gestion__cont_tipos_asientos x WHERE x.empresa_id = @emp AND x.codigo = t.codigo);

-- ------------------------------------------------------------------------------
-- 4. Plan de cuentas (se inserta por nivel para resolver la cuenta padre por código)
-- ------------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_plan_sindicato;
CREATE TEMPORARY TABLE tmp_plan_sindicato (
    codigo VARCHAR(20) NOT NULL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    naturaleza CHAR(1) NOT NULL,
    padre VARCHAR(20) NULL,
    nivel TINYINT NOT NULL,
    orden INT NOT NULL,
    imputable TINYINT NOT NULL,
    caja_banco TINYINT NOT NULL
) ENGINE=MEMORY;

INSERT INTO tmp_plan_sindicato (codigo, nombre, naturaleza, padre, nivel, orden, imputable, caja_banco) VALUES
('1',          'ACTIVO',                                              'D', NULL,      1,   100, 0, 0),
('1.1',        'ACTIVO CORRIENTE',                                    'D', '1',       2,   110, 0, 0),
('1.1.1',      'CAJA Y BANCOS',                                       'D', '1.1',     3,   111, 0, 0),
('1.1.1.01',   'Caja Sede Central',                                   'D', '1.1.1',   4,   112, 1, 1),
('1.1.1.02',   'Banco de la Nación Argentina - Cuenta Recaudadora',   'D', '1.1.1',   4,   113, 1, 1),
('1.1.1.03',   'Banco de la Provincia de Buenos Aires - Cuenta Corriente', 'D', '1.1.1', 4, 114, 1, 1),
('1.1.1.04',   'Recaudación Red Link a acreditar',                    'D', '1.1.1',   4,   115, 1, 1),
('1.1.1.05',   'Recaudación Interbanking a acreditar',                'D', '1.1.1',   4,   116, 1, 1),
('1.1.1.06',   'Valores a depositar',                                 'D', '1.1.1',   4,   117, 1, 1),
('1.1.2',      'CRÉDITOS',                                            'D', '1.1',     3,   120, 0, 0),
('1.1.2.01',   'Deudores por aportes sindicales',                     'D', '1.1.2',   4,   121, 1, 0),
('1.1.2.02',   'Deudores por cuotas de afiliados',                    'D', '1.1.2',   4,   122, 1, 0),
('1.1.2.03',   'Deudores por servicios',                              'D', '1.1.2',   4,   123, 1, 0),
('1.1.2.04',   'Anticipos al personal',                               'D', '1.1.2',   4,   124, 1, 0),
('1.1.2.05',   'Otros créditos',                                      'D', '1.1.2',   4,   125, 1, 0),
('1.2',        'ACTIVO NO CORRIENTE',                                 'D', '1',       2,   130, 0, 0),
('1.2.1',      'BIENES DE USO',                                       'D', '1.2',     3,   131, 0, 0),
('1.2.1.01',   'Inmuebles',                                           'D', '1.2.1',   4,   132, 1, 0),
('1.2.1.02',   'Muebles y útiles',                                    'D', '1.2.1',   4,   133, 1, 0),
('1.2.1.03',   'Equipos de computación',                              'D', '1.2.1',   4,   134, 1, 0),
('1.2.1.09',   'Amortizaciones acumuladas de bienes de uso',          'A', '1.2.1',   4,   139, 1, 0),
('2',          'PASIVO',                                              'A', NULL,      1,   200, 0, 0),
('2.1',        'PASIVO CORRIENTE',                                    'A', '2',       2,   210, 0, 0),
('2.1.1',      'DEUDAS COMERCIALES',                                  'A', '2.1',     3,   211, 0, 0),
('2.1.1.01',   'Proveedores',                                         'A', '2.1.1',   4,   212, 1, 0),
('2.1.1.02',   'Valores diferidos a pagar',                           'A', '2.1.1',   4,   213, 1, 0),
('2.1.2',      'DEUDAS SOCIALES',                                     'A', '2.1',     3,   220, 0, 0),
('2.1.2.01',   'Sueldos a pagar',                                     'A', '2.1.2',   4,   221, 1, 0),
('2.1.2.02',   'Cargas sociales a pagar',                             'A', '2.1.2',   4,   222, 1, 0),
('2.1.3',      'DEUDAS FISCALES',                                     'A', '2.1',     3,   230, 0, 0),
('2.1.3.01',   'Retenciones a depositar',                             'A', '2.1.3',   4,   231, 1, 0),
('2.1.4',      'OTRAS DEUDAS',                                        'A', '2.1',     3,   240, 0, 0),
('2.1.4.01',   'Aportes a la federación a pagar',                     'A', '2.1.4',   4,   241, 1, 0),
('2.1.4.02',   'Cobros a imputar',                                    'A', '2.1.4',   4,   242, 1, 0),
('3',          'PATRIMONIO NETO',                                     'A', NULL,      1,   300, 0, 0),
('3.1',        'Fondo social',                                        'A', '3',       2,   310, 1, 0),
('3.2',        'Resultados no asignados',                             'A', '3',       2,   320, 1, 0),
('4',          'RECURSOS',                                            'A', NULL,      1,   400, 0, 0),
('4.1',        'RECURSOS PARA FINES GENERALES',                       'A', '4',       2,   410, 0, 0),
('4.1.01',     'Aportes Fondo Social Art. 100 CCT 130/75',            'A', '4.1',     3,   411, 1, 0),
('4.1.02',     'Cuota Sindical Art. 101 CCT 130/75',                  'A', '4.1',     3,   412, 1, 0),
('4.1.03',     'Cuotas de afiliados voluntarios y jubilados',         'A', '4.1',     3,   413, 1, 0),
('4.1.04',     'Ajustes por actas de inspección',                     'A', '4.1',     3,   414, 1, 0),
('4.2',        'RECURSOS PARA FINES ESPECÍFICOS',                     'A', '4',       2,   420, 0, 0),
('4.2.01',     'Servicios a afiliados y alquiler de salones',         'A', '4.2',     3,   421, 1, 0),
('4.3',        'RECURSOS FINANCIEROS',                                'A', '4',       2,   430, 0, 0),
('4.3.01',     'Intereses resarcitorios',                             'A', '4.3',     3,   431, 1, 0),
('4.3.02',     'Multas',                                              'A', '4.3',     3,   432, 1, 0),
('4.3.03',     'Gastos administrativos recuperados',                  'A', '4.3',     3,   433, 1, 0),
('5',          'GASTOS',                                              'D', NULL,      1,   500, 0, 0),
('5.1',        'GASTOS GENERALES DE ADMINISTRACIÓN',                  'D', '5',       2,   510, 0, 0),
('5.1.01',     'Sueldos y jornales',                                  'D', '5.1',     3,   511, 1, 0),
('5.1.02',     'Cargas sociales',                                     'D', '5.1',     3,   512, 1, 0),
('5.1.03',     'Honorarios profesionales',                            'D', '5.1',     3,   513, 1, 0),
('5.1.04',     'Servicios públicos',                                  'D', '5.1',     3,   514, 1, 0),
('5.1.05',     'Gastos bancarios y comisiones de recaudación',        'D', '5.1',     3,   515, 1, 0),
('5.1.06',     'Gastos generales',                                    'D', '5.1',     3,   516, 1, 0),
('5.1.07',     'Amortizaciones de bienes de uso',                     'D', '5.1',     3,   517, 1, 0),
('5.2',        'GASTOS ESPECÍFICOS DE ACCIÓN GREMIAL',                'D', '5',       2,   520, 0, 0),
('5.2.01',     'Aportes a la federación',                             'D', '5.2',     3,   521, 1, 0),
('5.2.02',     'Beneficios y ayudas a afiliados',                     'D', '5.2',     3,   522, 1, 0);

-- Nivel 1 (sin padre) y niveles 2..4 resolviendo el padre por código en la misma empresa
INSERT INTO gestion__cont_cuentas (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
SELECT @emp, 0, t.codigo, t.nombre, t.naturaleza, NULL, t.nivel, t.orden, t.imputable, t.caja_banco, 1
FROM tmp_plan_sindicato t
WHERE t.nivel = 1 AND NOT EXISTS (SELECT 1 FROM gestion__cont_cuentas x WHERE x.empresa_id = @emp AND x.codigo = t.codigo);

INSERT INTO gestion__cont_cuentas (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
SELECT @emp, 0, t.codigo, t.nombre, t.naturaleza, p.cont_cuenta_id, t.nivel, t.orden, t.imputable, t.caja_banco, 1
FROM tmp_plan_sindicato t
INNER JOIN (SELECT cont_cuenta_id, codigo FROM gestion__cont_cuentas WHERE empresa_id = @emp) p ON p.codigo = t.padre
WHERE t.nivel = 2 AND NOT EXISTS (SELECT 1 FROM gestion__cont_cuentas x WHERE x.empresa_id = @emp AND x.codigo = t.codigo);

INSERT INTO gestion__cont_cuentas (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
SELECT @emp, 0, t.codigo, t.nombre, t.naturaleza, p.cont_cuenta_id, t.nivel, t.orden, t.imputable, t.caja_banco, 1
FROM tmp_plan_sindicato t
INNER JOIN (SELECT cont_cuenta_id, codigo FROM gestion__cont_cuentas WHERE empresa_id = @emp) p ON p.codigo = t.padre
WHERE t.nivel = 3 AND NOT EXISTS (SELECT 1 FROM gestion__cont_cuentas x WHERE x.empresa_id = @emp AND x.codigo = t.codigo);

INSERT INTO gestion__cont_cuentas (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
SELECT @emp, 0, t.codigo, t.nombre, t.naturaleza, p.cont_cuenta_id, t.nivel, t.orden, t.imputable, t.caja_banco, 1
FROM tmp_plan_sindicato t
INNER JOIN (SELECT cont_cuenta_id, codigo FROM gestion__cont_cuentas WHERE empresa_id = @emp) p ON p.codigo = t.padre
WHERE t.nivel = 4 AND NOT EXISTS (SELECT 1 FROM gestion__cont_cuentas x WHERE x.empresa_id = @emp AND x.codigo = t.codigo);

DROP TEMPORARY TABLE IF EXISTS tmp_plan_sindicato;

-- ------------------------------------------------------------------------------
-- 5. Conceptos contables (Tesorería > Conceptos Contables)
-- ------------------------------------------------------------------------------
INSERT INTO gestion__empresas_cuentas_conceptos (empresa_id, concepto, cont_cuenta_id, tabla_estado_registro_id, fecha_creacion)
SELECT @emp, m.concepto, c.cont_cuenta_id, 1, NOW()
FROM (SELECT 'SINDI_DEUDORES_APORTES' AS concepto, '1.1.2.01' AS codigo
      UNION ALL SELECT 'SINDI_DEUDORES_AFILIADOS', '1.1.2.02'
      UNION ALL SELECT 'SINDI_APORTE_ART100',      '4.1.01'
      UNION ALL SELECT 'SINDI_APORTE_ART101',      '4.1.02'
      UNION ALL SELECT 'SINDI_AJUSTE_ACTAS',       '4.1.04'
      UNION ALL SELECT 'SINDI_INTERESES_GANADOS',  '4.3.01'
      UNION ALL SELECT 'SINDI_MULTAS',             '4.3.02'
      UNION ALL SELECT 'SINDI_GASTOS_ADM',         '4.3.03'
      UNION ALL SELECT 'DEUDORES_VENTAS',          '1.1.2.03'
      UNION ALL SELECT 'VENTAS',                   '4.2.01'
      UNION ALL SELECT 'PROVEEDORES',              '2.1.1.01'
      UNION ALL SELECT 'VALORES_CARTERA',          '1.1.1.06'
      UNION ALL SELECT 'VALORES_A_PAGAR',          '2.1.1.02'
      UNION ALL SELECT 'EMPLEADOS',                '1.1.2.04'
      UNION ALL SELECT 'GASTOS_GENERALES',         '5.1.06') m
INNER JOIN gestion__cont_cuentas c ON c.empresa_id = @emp AND c.codigo = m.codigo
WHERE NOT EXISTS (SELECT 1 FROM gestion__empresas_cuentas_conceptos x WHERE x.empresa_id = @emp AND x.concepto = m.concepto);

-- ------------------------------------------------------------------------------
-- 6. Comprobantes sindicales
-- ------------------------------------------------------------------------------
INSERT INTO gestion__comprobantes_grupos (empresa_id, comprobante_grupo, codigo, orden, tabla_estado_registro_id)
SELECT @emp, 'Sindical', 'SINDI', 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gestion__comprobantes_grupos WHERE empresa_id = @emp AND codigo = 'SINDI');
SET @grupo := (SELECT comprobante_grupo_id FROM gestion__comprobantes_grupos WHERE empresa_id = @emp AND codigo = 'SINDI');

INSERT INTO gestion__comprobantes_subgrupos (empresa_id, comprobante_grupo_id, tabla_id, comprobante_subgrupo, codigo, comentario, orden, tabla_estado_registro_id, origen_asiento)
SELECT @emp, @grupo, 524, 'Declaraciones Juradas', 'DDJJ', 'Comprobantes de DDJJ, reversiones, volantes, cobros, actas y planes', 1, 1, 'AUTO' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gestion__comprobantes_subgrupos WHERE empresa_id = @emp AND codigo = 'DDJJ');
SET @subgrupo := (SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos WHERE empresa_id = @emp AND codigo = 'DDJJ');

INSERT INTO gestion__comprobantes_tipos (empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id,
                                         impacta_stock, impacta_contabilidad, impacta_ctacte, comprobante_tipo, codigo, signo, orden, tabla_estado_registro_id)
SELECT @emp, @grupo, @subgrupo, 0, 0, t.contab, t.ctacte, t.nombre, t.codigo, t.signo, t.orden, 1
FROM (SELECT 'DFS' AS codigo, 'DDJJ - Fondo Social Art. 100' AS nombre, '+' AS signo, 1 AS contab, 1 AS ctacte, 1 AS orden
      UNION ALL SELECT 'DCS', 'DDJJ - Cuota Sindical Art. 101',     '+', 1, 1, 2
      UNION ALL SELECT 'RFS', 'Reversión DDJJ - Fondo Social',       '-', 1, 1, 3
      UNION ALL SELECT 'RCS', 'Reversión DDJJ - Cuota Sindical',     '-', 1, 1, 4
      UNION ALL SELECT 'VOL', 'Volante de Pago Sindical',            '+', 0, 0, 5
      UNION ALL SELECT 'NDI', 'ND Intereses Resarcitorios',          '+', 1, 1, 6
      UNION ALL SELECT 'REC', 'Cobranza Aporte Sindical',            '-', 1, 1, 7
      UNION ALL SELECT 'ACT', 'Acta Determinada Sindical',           '+', 1, 1, 8
      UNION ALL SELECT 'PLC', 'Cuota Plan de Pago Sindical',         '+', 0, 1, 9
      UNION ALL SELECT 'RPL', 'Refinanciación por Plan de Pagos',    '-', 1, 1, 10) t
WHERE NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos x WHERE x.empresa_id = @emp AND x.codigo = t.codigo);

INSERT INTO gestion__puntos_venta_comprobantes (empresa_id, punto_venta_id, comprobante_tipo_id, tabla_estado_registro_id)
SELECT @emp, @pv, t.comprobante_tipo_id, 1
FROM gestion__comprobantes_tipos t
WHERE t.empresa_id = @emp AND t.comprobante_subgrupo_id = @subgrupo
  AND NOT EXISTS (SELECT 1 FROM gestion__puntos_venta_comprobantes x WHERE x.punto_venta_id = @pv AND x.comprobante_tipo_id = t.comprobante_tipo_id);

-- ------------------------------------------------------------------------------
-- 7. Cuentas de tesorería y asignación a los medios de cobro
-- ------------------------------------------------------------------------------
INSERT INTO gestion__tesoreria_cuentas (empresa_id, sucursal_id, cuenta_tipo, codigo, tesoreria_cuenta, moneda_id, cont_cuenta_id,
                                        saldo_actual, permite_saldo_negativo, orden, tabla_estado_registro_id)
SELECT @emp, @suc, t.tipo, t.codigo, t.nombre, @moneda, c.cont_cuenta_id, 0, 0, t.orden, 1
FROM (SELECT 'CAJA' AS tipo, 'CAJA_SEDE' AS codigo, 'Caja Sede Central' AS nombre, '1.1.1.01' AS cuenta, 1 AS orden
      UNION ALL SELECT 'BANCO',   'BNA_RECAUDA',  'Banco Nación - Cuenta Recaudadora',      '1.1.1.02', 2
      UNION ALL SELECT 'BANCO',   'BAPRO_CC',     'Banco Provincia - Cuenta Corriente',     '1.1.1.03', 3
      UNION ALL SELECT 'BANCO',   'LINK_RECAUDA', 'Recaudación Red Link a acreditar',       '1.1.1.04', 4
      UNION ALL SELECT 'BANCO',   'IBNK_RECAUDA', 'Recaudación Interbanking a acreditar',   '1.1.1.05', 5
      UNION ALL SELECT 'VALORES', 'VALORES',      'Valores a depositar',                    '1.1.1.06', 6) t
INNER JOIN gestion__cont_cuentas c ON c.empresa_id = @emp AND c.codigo = t.cuenta
WHERE NOT EXISTS (SELECT 1 FROM gestion__tesoreria_cuentas x WHERE x.empresa_id = @emp AND x.codigo = t.codigo);

-- Solo donde el medio todavía no tiene cuenta asignada (no pisa una configuración hecha a mano)
UPDATE sindi__medios_pago mp
SET mp.tesoreria_cuenta_codigo = CASE mp.driver
        WHEN 'BNA_BARRAS'   THEN 'BNA_RECAUDA'
        WHEN 'RED_LINK'     THEN 'LINK_RECAUDA'
        WHEN 'INTERBANKING' THEN 'IBNK_RECAUDA'
        WHEN 'BAPRO'        THEN 'BAPRO_CC'
        WHEN 'MANUAL'       THEN 'CAJA_SEDE'
    END
WHERE mp.empresa_id = @emp AND (mp.tesoreria_cuenta_codigo IS NULL OR mp.tesoreria_cuenta_codigo = '')
  AND mp.driver IN ('BNA_BARRAS', 'RED_LINK', 'INTERBANKING', 'BAPRO', 'MANUAL');

-- ------------------------------------------------------------------------------
-- 8. Conceptos de aporte: tipos de comprobante y cuenta de recurso
-- ------------------------------------------------------------------------------
UPDATE sindi__conceptos c
INNER JOIN gestion__comprobantes_tipos t ON t.empresa_id = c.empresa_id
       AND t.codigo = CASE c.codigo WHEN 'ART100' THEN 'DFS' WHEN 'ART101' THEN 'DCS' END
SET c.comprobante_tipo_id = t.comprobante_tipo_id
WHERE c.empresa_id = @emp AND c.comprobante_tipo_id IS NULL AND c.codigo IN ('ART100', 'ART101');

UPDATE sindi__conceptos c
INNER JOIN gestion__comprobantes_tipos t ON t.empresa_id = c.empresa_id
       AND t.codigo = CASE c.codigo WHEN 'ART100' THEN 'RFS' WHEN 'ART101' THEN 'RCS' END
SET c.comprobante_tipo_reversion_id = t.comprobante_tipo_id
WHERE c.empresa_id = @emp AND c.comprobante_tipo_reversion_id IS NULL AND c.codigo IN ('ART100', 'ART101');

COMMIT;

-- ------------------------------------------------------------------------------
-- Verificación
-- ------------------------------------------------------------------------------
SELECT 'moneda base' AS control, COUNT(*) AS cantidad FROM gestion__monedas WHERE empresa_id = @emp AND es_moneda_base = 1
UNION ALL SELECT 'puntos de venta', COUNT(*) FROM gestion__puntos_venta WHERE empresa_id = @emp
UNION ALL SELECT 'tipos de asiento', COUNT(*) FROM gestion__cont_tipos_asientos WHERE empresa_id = @emp
UNION ALL SELECT 'cuentas contables (imputables)', SUM(es_imputable) FROM gestion__cont_cuentas WHERE empresa_id = @emp
UNION ALL SELECT 'conceptos contables', COUNT(*) FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = @emp
UNION ALL SELECT 'tipos de comprobante sindicales', COUNT(*) FROM gestion__comprobantes_tipos WHERE empresa_id = @emp AND comprobante_subgrupo_id = @subgrupo
UNION ALL SELECT 'cuentas de tesorería', COUNT(*) FROM gestion__tesoreria_cuentas WHERE empresa_id = @emp
UNION ALL SELECT 'medios de cobro sin cuenta de tesorería', COUNT(*) FROM sindi__medios_pago WHERE empresa_id = @emp AND tabla_estado_registro_id = 1 AND (tesoreria_cuenta_codigo IS NULL OR tesoreria_cuenta_codigo = '')
UNION ALL SELECT 'conceptos de aporte sin tipos', COUNT(*) FROM sindi__conceptos WHERE empresa_id = @emp AND tabla_estado_registro_id = 1 AND (comprobante_tipo_id IS NULL OR comprobante_tipo_reversion_id IS NULL);
