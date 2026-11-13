-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================
-- Migracion de PRODUCCION Casa Lucho al formato actual (servidor nuevo)
-- Script 03: DATOS DERIVADOS
-- 2026-10-31
-- =====================================================================
-- Requiere los scripts 01 y 02. Escrito a mano (no generado), salvo los dos
-- INSERT del paso 2, armados desde la base local.
--
-- No modifica datos maestros (entidades, productos, precios, costos, usuarios).
-- Completa lo que el codigo nuevo necesita y se deriva de lo que ya hay en
-- produccion:
--   1) Roles de entidad (equivale a 20261019_entidades_roles_01_backfill): una
--      fila en gestion__entidades_roles por cada es_cliente / es_proveedor, con
--      la cuenta contable de la entidad. Ademas, CLI a quien tiene pedidos,
--      remitos o facturas de venta y PRO a quien tiene facturas de proveedor.
--      Los proveedores tienen la cuenta de proveedores guardada en
--      cont_cuenta_id_cliente (codigo viejo): se toma de ahi si no es cliente.
--   2) Gastos bancarios (Conciliacion Bancaria): los productos BCO_COMISION,
--      BCO_INTERES y BCO_LEY25413 (servicios sin stock) y los conceptos
--      bancarios de la empresa 2, por codigo (los ids de local no sirven: el
--      producto toma el id que le toque en produccion).
--   3) Signo de las notas de credito en gestion__comprobantes
--      (20261008_comprobantes_signo_notas_credito_01).
--   4) Espejo de los remitos en gestion__comprobantes: estado y numero
--      (20261008_ventas_remitos_espejo_01).
--   5) Puntos de venta internos (codigo >= 10000 o "Interno" / "Info") como
--      NO_FISCAL (20261013_puntos_venta_sistema_emision).
--   6) Saldo (importe_pendiente) de las facturas de proveedor confirmadas que
--      el codigo viejo dejo en 0: no hay pagos ni imputaciones en produccion,
--      asi que el saldo es el total. Sin esto aparecen como pagadas en la
--      cuenta corriente de proveedores y no se pueden pagar con una OP.
--   7) Solo consulta: saldos del stock viejo (_bak20261008) para cargar el
--      inventario inicial con un ajuste de stock.
--
-- Reanudable: cada sentencia solo toca filas que siguen pendientes (el paso 1
-- lee las columnas viejas de gestion__entidades y se saltea si el 04 ya las
-- borro).
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261031_migracion_casalucho_03_datos.sql
-- =====================================================================

SET NAMES utf8mb4;
-- Sin USE: la base se indica en la linea de comando.
SET @OLD_SQL_MODE = @@SQL_MODE;
-- Produccion tiene fechas de alta 0000-00-00 en gestion__entidades.
SET SESSION SQL_MODE = 'NO_ENGINE_SUBSTITUTION';

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Roles de entidad. Con PREPARE: solo si gestion__entidades todavia tiene
--    las columnas viejas (si ya se corrio el 04, el paso se saltea).
-- ---------------------------------------------------------------------
SET @mig_cols_viejas = (SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades'
                          AND COLUMN_NAME IN ('es_cliente', 'es_proveedor', 'cont_cuenta_id_cliente', 'cont_cuenta_id_proveedor'));

-- Clientes
SET @mig_sql = IF(@mig_cols_viejas = 4, '
INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, cont_cuenta_id, f_alta, f_baja, tabla_estado_registro_id)
SELECT e.entidad_id, r.rol_entidad_id, NULLIF(e.cont_cuenta_id_cliente, 0),
       IF(e.fecha_alta >= ''1900-01-01'', DATE(e.fecha_alta), CURDATE()), NULL, 1
FROM gestion__entidades e
JOIN gestion__roles_entidades r ON r.rol_entidad_codigo = ''CLI''
WHERE e.es_cliente = 1
   OR EXISTS (SELECT 1 FROM gestion__ventas_pedidos x WHERE x.entidad_id = e.entidad_id)
   OR EXISTS (SELECT 1 FROM gestion__ventas_remitos x WHERE x.entidad_id = e.entidad_id)
   OR EXISTS (SELECT 1 FROM gestion__ventas_facturas x WHERE x.entidad_id = e.entidad_id)
ON DUPLICATE KEY UPDATE cont_cuenta_id = COALESCE(gestion__entidades_roles.cont_cuenta_id, VALUES(cont_cuenta_id))', 'DO 0');
PREPARE mig_stmt FROM @mig_sql;
EXECUTE mig_stmt;
DEALLOCATE PREPARE mig_stmt;

-- Proveedores
SET @mig_sql = IF(@mig_cols_viejas = 4, '
INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, cont_cuenta_id, f_alta, f_baja, tabla_estado_registro_id)
SELECT e.entidad_id, r.rol_entidad_id,
       COALESCE(NULLIF(e.cont_cuenta_id_proveedor, 0), IF(e.es_cliente = 1, NULL, NULLIF(e.cont_cuenta_id_cliente, 0))),
       IF(e.fecha_alta >= ''1900-01-01'', DATE(e.fecha_alta), CURDATE()), NULL, 1
FROM gestion__entidades e
JOIN gestion__roles_entidades r ON r.rol_entidad_codigo = ''PRO''
WHERE e.es_proveedor = 1
   OR EXISTS (SELECT 1 FROM gestion__facturas_proveedores x WHERE x.entidad_id = e.entidad_id)
ON DUPLICATE KEY UPDATE cont_cuenta_id = COALESCE(gestion__entidades_roles.cont_cuenta_id, VALUES(cont_cuenta_id))', 'DO 0');
PREPARE mig_stmt FROM @mig_sql;
EXECUTE mig_stmt;
DEALLOCATE PREPARE mig_stmt;

-- ---------------------------------------------------------------------
-- 2) Gastos bancarios: productos BCO_* y conceptos bancarios de la empresa 2
-- ---------------------------------------------------------------------
INSERT INTO gestion__productos (`empresa_id`, `producto_codigo`, `producto_nombre`, `codigo_barras`, `producto_descripcion`, `producto_categoria_id`, `cont_cuenta_id`, `cont_cuenta_compra_id`, `cont_cuenta_venta_id`, `cont_cuenta_cmv_id`, `producto_tipo_id`, `lado`, `material`, `color`, `peso`, `dimensiones`, `garantia`, `unidad_medida_id`, `iva_alicuota_id`, `impuesto_interno_porcentaje`, `impuesto_interno_fijo`, `controla_stock`, `es_servicio`, `tabla_estado_registro_id`, `compatibilidad_texto`, `compatibilidad_busqueda`, `proveedores_busqueda`, `aplica_compra`, `aplica_venta`)
SELECT * FROM (
    SELECT '2' AS `empresa_id`, 'BCO_COMISION' AS `producto_codigo`, 'Comisiones y gastos bancarios' AS `producto_nombre`, NULL AS `codigo_barras`, NULL AS `producto_descripcion`, '1' AS `producto_categoria_id`, '841' AS `cont_cuenta_id`, '841' AS `cont_cuenta_compra_id`, NULL AS `cont_cuenta_venta_id`, NULL AS `cont_cuenta_cmv_id`, '2' AS `producto_tipo_id`, NULL AS `lado`, NULL AS `material`, NULL AS `color`, NULL AS `peso`, NULL AS `dimensiones`, NULL AS `garantia`, '1' AS `unidad_medida_id`, '1' AS `iva_alicuota_id`, NULL AS `impuesto_interno_porcentaje`, NULL AS `impuesto_interno_fijo`, '0' AS `controla_stock`, '1' AS `es_servicio`, '1' AS `tabla_estado_registro_id`, NULL AS `compatibilidad_texto`, NULL AS `compatibilidad_busqueda`, NULL AS `proveedores_busqueda`, '1' AS `aplica_compra`, '0' AS `aplica_venta`
    UNION ALL
    SELECT '2', 'BCO_INTERES', 'Intereses bancarios', NULL, NULL, '1', '840', '840', NULL, NULL, '2', NULL, NULL, NULL, NULL, NULL, NULL, '1', '1', NULL, NULL, '0', '1', '1', NULL, NULL, NULL, '1', '0'
    UNION ALL
    SELECT '2', 'BCO_LEY25413', 'Impuesto s/ débitos y créditos (ley 25.413)', NULL, NULL, '1', '1297', '1297', NULL, NULL, '2', NULL, NULL, NULL, NULL, NULL, NULL, '1', '4', NULL, NULL, '0', '1', '1', NULL, NULL, NULL, '1', '0'
) s
WHERE NOT EXISTS (SELECT 1 FROM gestion__productos x WHERE x.empresa_id = s.empresa_id AND x.producto_codigo = s.producto_codigo);

INSERT INTO gestion__bancos_conceptos (`empresa_id`, `codigo`, `concepto`, `naturaleza`, `rol`, `iva_incluido`, `impuesto_tipo_id`, `jurisdiccion_id`, `es_cobranza`, `orden`, `tabla_estado_registro_id`, `fecha_creacion`, `fecha_modificacion`, `usuario_id`, `usuario_modificacion_id`, `producto_id`)
SELECT s.`empresa_id`, s.`codigo`, s.`concepto`, s.`naturaleza`, s.`rol`, s.`iva_incluido`, s.`impuesto_tipo_id`, s.`jurisdiccion_id`, s.`es_cobranza`, s.`orden`, s.`tabla_estado_registro_id`, s.`fecha_creacion`, s.`fecha_modificacion`, s.`usuario_id`, s.`usuario_modificacion_id`, p.producto_id
FROM (
    SELECT '2' AS `empresa_id`, 'COMISION' AS `codigo`, 'Comisiones y gastos bancarios' AS `concepto`, 'GASTO_BANCARIO' AS `naturaleza`, 'ITEM' AS `rol`, '0' AS `iva_incluido`, NULL AS `impuesto_tipo_id`, NULL AS `jurisdiccion_id`, '0' AS `es_cobranza`, '10' AS `orden`, '1' AS `tabla_estado_registro_id`, '2026-10-28 08:06:20' AS `fecha_creacion`, '2026-10-28 08:06:20' AS `fecha_modificacion`, NULL AS `usuario_id`, NULL AS `usuario_modificacion_id`, 'BCO_COMISION' AS `_producto_codigo`
    UNION ALL
    SELECT '2', 'INTERES', 'Intereses bancarios', 'GASTO_BANCARIO', 'ITEM', '0', NULL, NULL, '0', '20', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, 'BCO_INTERES'
    UNION ALL
    SELECT '2', 'LEY25413', 'Impuesto s/ débitos y créditos (ley 25.413)', 'GASTO_BANCARIO', 'ITEM', '0', NULL, NULL, '0', '30', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, 'BCO_LEY25413'
    UNION ALL
    SELECT '2', 'IVA', 'IVA crédito fiscal del banco', 'GASTO_BANCARIO', 'IVA', '0', NULL, NULL, '0', '40', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'PERCEP_IVA', 'Percepción IVA', 'GASTO_BANCARIO', 'PERCEPCION_IVA', '0', '2', NULL, '0', '50', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'PERCEP_GAN', 'Percepción Ganancias', 'GASTO_BANCARIO', 'PERCEPCION_GANANCIAS', '0', '3', NULL, '0', '55', '1', '2026-10-30 00:30:52', '2026-10-30 00:30:52', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'PERCEP_IIBB', 'Percepción IIBB', 'GASTO_BANCARIO', 'PERCEPCION_IIBB', '0', '1', NULL, '0', '60', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'SIRCREB', 'Ingresos Brutos SIRCREB (retención s/ créditos)', 'GASTO_BANCARIO', 'RETENCION_IIBB', '0', '1', NULL, '0', '70', '1', '2026-10-28 08:06:20', '2026-10-30 00:30:52', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'CHEQUE', 'Cheques (depósito / pago / rechazo)', 'MOVIMIENTO_PROPIO', NULL, '0', NULL, NULL, '0', '110', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'DEPOSITO', 'Depósitos', 'MOVIMIENTO_PROPIO', NULL, '0', NULL, NULL, '0', '120', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'TRANSFERENCIA', 'Transferencias', 'MOVIMIENTO_PROPIO', NULL, '0', NULL, NULL, '1', '130', '1', '2026-10-28 08:06:20', '2026-10-31 11:15:44', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'DEBIN', 'Débito inmediato (DEBIN)', 'MOVIMIENTO_PROPIO', NULL, '0', NULL, NULL, '0', '140', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'PAGO_SERVICIOS', 'Pago de servicios (P.SERV)', 'MOVIMIENTO_PROPIO', NULL, '0', NULL, NULL, '0', '150', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
    UNION ALL
    SELECT '2', 'FCI', 'Fondo común de inversión (suscripción / rescate)', 'MOVIMIENTO_PROPIO', NULL, '0', NULL, NULL, '0', '160', '1', '2026-10-28 08:06:20', '2026-10-28 08:06:20', NULL, NULL, NULL
) s
LEFT JOIN gestion__productos p ON p.empresa_id = s.empresa_id AND p.producto_codigo = s._producto_codigo
WHERE NOT EXISTS (SELECT 1 FROM gestion__bancos_conceptos x WHERE x.empresa_id = s.empresa_id AND x.codigo = s.codigo);

-- ---------------------------------------------------------------------
-- 3) Signo de las notas de credito (facturas de venta y de proveedor)
-- ---------------------------------------------------------------------
UPDATE gestion__comprobantes c
JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
SET c.signo = IF(ct.signo = '-', -1, 1)
WHERE c.tabla_origen_id IN (SELECT t.tabla_id FROM conf__tablas t
                            WHERE t.tabla_nombre IN ('gestion__ventas_facturas', 'gestion__facturas_proveedores'))
  AND c.signo <> IF(ct.signo = '-', -1, 1);

-- ---------------------------------------------------------------------
-- 4) Espejo de los remitos de venta
-- ---------------------------------------------------------------------
UPDATE gestion__comprobantes c
JOIN gestion__ventas_remitos r ON r.venta_remito_id = c.registro_origen_id
SET c.tabla_estado_registro_id = r.tabla_estado_registro_id,
    c.comprobante_nro = r.comprobante_nro
WHERE c.tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__ventas_remitos')
  AND (c.tabla_estado_registro_id <> r.tabla_estado_registro_id OR c.comprobante_nro <> r.comprobante_nro);

-- ---------------------------------------------------------------------
-- 5) Puntos de venta internos
-- ---------------------------------------------------------------------
UPDATE `gestion__puntos_venta`
SET `sistema_emision` = 'NO_FISCAL'
WHERE `sistema_emision` = 'WSFE'
  AND (`codigo_fiscal` IS NULL OR `codigo_fiscal` >= 10000 OR `nombre` LIKE '%Interno%' OR `nombre` LIKE '%Info%');

-- ---------------------------------------------------------------------
-- 6) Saldo de las facturas de proveedor confirmadas sin pagos
-- ---------------------------------------------------------------------
UPDATE gestion__comprobantes c
SET c.importe_pendiente = c.importe_total
WHERE c.tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__facturas_proveedores')
  AND c.tabla_estado_registro_id = 5
  AND c.importe_total > 0
  AND c.importe_pendiente = 0
  AND NOT EXISTS (SELECT 1 FROM gestion__comprobantes_imputaciones i
                  WHERE i.comp_destino_id = c.comprobante_id OR i.comp_origen_id = c.comprobante_id);

COMMIT;

SET SESSION SQL_MODE = @OLD_SQL_MODE;

-- ---------------------------------------------------------------------
-- Verificacion
-- ---------------------------------------------------------------------
SELECT r.rol_entidad_codigo AS rol, COUNT(*) AS entidades, SUM(er.cont_cuenta_id IS NOT NULL) AS con_cuenta
FROM gestion__entidades_roles er
JOIN gestion__roles_entidades r ON r.rol_entidad_id = er.rol_entidad_id
GROUP BY r.rol_entidad_codigo;

SELECT 'entidades sin rol' AS control,
       (SELECT COUNT(*) FROM gestion__entidades e
         WHERE NOT EXISTS (SELECT 1 FROM gestion__entidades_roles er WHERE er.entidad_id = e.entidad_id)) AS cantidad
UNION ALL
SELECT 'conceptos de gasto sin producto',
       (SELECT COUNT(*) FROM gestion__bancos_conceptos WHERE empresa_id = 2 AND rol = 'ITEM' AND producto_id IS NULL)
UNION ALL
SELECT 'remitos desincronizados',
       (SELECT COUNT(*) FROM gestion__comprobantes c
          JOIN gestion__ventas_remitos r ON r.venta_remito_id = c.registro_origen_id
         WHERE c.tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__ventas_remitos')
           AND (c.tabla_estado_registro_id <> r.tabla_estado_registro_id OR c.comprobante_nro <> r.comprobante_nro));

-- Notas de credito con asiento ya generado: regenerar el asiento desde la pantalla
-- (boton "Generar asiento"), porque el codigo viejo los hacia con el sentido de una factura.
SELECT c.comprobante_id, ct.comprobante_tipo, c.comprobante_pv, c.comprobante_nro, a.cont_asiento_id
FROM gestion__comprobantes c
JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
JOIN gestion__cont_asientos a ON a.comprobante_id = c.comprobante_id
WHERE ct.signo = '-'
  AND c.tabla_origen_id IN (SELECT t.tabla_id FROM conf__tablas t
                            WHERE t.tabla_nombre IN ('gestion__ventas_facturas', 'gestion__facturas_proveedores'));

-- ---------------------------------------------------------------------
-- 7) Stock viejo: saldo por sucursal / deposito / producto, para cargar el
--    inventario inicial con un ajuste de stock (Stock > Ajustes) en la boca
--    que corresponda. El stock nuevo arranca en cero.
-- ---------------------------------------------------------------------
SELECT s.empresa_id, s.sucursal_id, s.deposito_id, s.producto_id,
       p.producto_codigo, p.producto_nombre, s.cantidad, s.costo_promedio
FROM gestion__stock_bak20261008 s
LEFT JOIN gestion__productos p ON p.producto_id = s.producto_id
WHERE s.cantidad <> 0
ORDER BY s.empresa_id, s.sucursal_id, s.deposito_id, p.producto_codigo;
