-- =====================================================================
-- Tesorería - script 03: DATOS INICIALES POR EMPRESA (plantilla)
-- medios de pago, cuentas de tesorería, categorías de gasto, numeradores
-- de certificados de retención y puntos de venta internos (D29)
-- 2026-10-03 · generado por disenador-modelo-datos · NO EJECUTADO
-- Revisión 4 (2026-10-03). Especificación funcional vigente:
--   docs/matriz-legal/08_proc_tesoreria_v3_6.docx (decisiones D1-D31;
--   este script cubre D29 y D30, §9.1 filas 4, 7 y 9).
-- =====================================================================
-- ORDEN DE EJECUCIÓN (tres archivos con el mismo prefijo 20261003_tesoreria_):
--   01_ddl.sql                 UNA VEZ por base (estructura + datos globales).
--   02_siembra_empresa.sql     una vez por empresa (tipos, subgrupos, reglas).
--   03_datos_iniciales.sql     ESTE: por empresa, DESPUÉS del 02.
--
-- ES UNA PLANTILLA: todos los bloques que escriben datos (1 a 5) vienen
-- COMENTADOS con valores de EJEMPLO (los de Casa Lucho, empresa 2, tomados
-- de casalucho_local.sql / casalucho_produccion.sql del 2026-10-03). Corrido
-- tal cual, el script solo valida y muestra diagnósticos. Para usarlo:
--   1) Copiarlo (p. ej. 20261003_tesoreria_03_datos_iniciales_emp2.sql) o
--      editarlo en el momento: setear los PARÁMETROS.
--   2) Reemplazar los valores de ejemplo por los reales (Word v3.6 §9.1
--      fila 7: cajas por sucursal, cuentas bancarias con CBU, cuenta de
--      valores por moneda, cupones por tarjeta, medios de pago y cuentas
--      contables asociadas) y descomentar los bloques que se quieran cargar.
--   3) Correrlo. Es repetible: cada INSERT es ... WHERE NOT EXISTS por la
--      clave natural (codigo / nombre), así que volver a correrlo no duplica.
--      Los valores que se cambien después de una corrida NO se actualizan
--      (salvo ultimo_numero de retenciones, que solo sube): corregir a mano
--      o por el ABM.
--
-- REQUISITOS
--   - Base seleccionada con USE (no hay USE en el script).
--   - 01 y 02 ya ejecutados PARA ESTA EMPRESA (se verifica: los 13 tipos de
--     tesorería con subgrupo asignado).
--   - Privilegios: SELECT, INSERT, UPDATE, CREATE ROUTINE y ALTER ROUTINE
--     (helper _tes_assert).
--   - Los ids de otras tablas se resuelven por código o nombre: sucursal por
--     sucursal_nombre, moneda por gestion__monedas.codigo, banco por
--     conf__bancos.codigo_bcra, cuenta contable por gestion__cont_cuentas.codigo
--     (plan de cuentas de la empresa), impuesto por impuesto_tipo, cuenta de
--     tesorería por su codigo. Si un código no se resuelve, la fila NO se
--     inserta (no queda con NULL silencioso): revisar la sección V.
--
-- PARÁMETROS
--   @tes_empresa_id      empresa (NULL a propósito: si no se edita, el script
--                        se corta en el primer control).
--   @tes_pv_prefijo      prefijo del nombre del PV interno de cada sucursal
--                        (bloque 5, D29). Es la clave de idempotencia del PV:
--                        no renombrar el PV antes de terminar.
--   @tes_pv_sucursal_id  NULL = todas las sucursales activas; un id = solo esa.
--   @tes_num_alcance     'SUCURSAL' (default) o 'EMPRESA': de dónde se toma el
--                        último número emitido para continuar la numeración
--                        de RCX/OP/TB/DB en el PV interno (bloque 5.3).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @tes_old_sql_mode = @@SESSION.sql_mode;
-- Válido en MariaDB 10.4 y MySQL 8.0 (sin NO_AUTO_CREATE_USER). Si el script
-- se corta a mitad: SET SESSION sql_mode = @tes_old_sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =====================================================================
-- PARÁMETROS — EDITAR ANTES DE CORRER
-- =====================================================================
SET @tes_empresa_id     = NULL;            -- <<< EDITAR (p. ej. 2 = Casa Lucho)
SET @tes_pv_prefijo     = 'Tesoreria - ';  -- D29: nombre = prefijo + sucursal_nombre
SET @tes_pv_sucursal_id = NULL;            -- NULL = todas las sucursales activas
SET @tes_num_alcance    = 'SUCURSAL';      -- 'SUCURSAL' | 'EMPRESA'

-- =====================================================================
-- Helper temporal (se elimina al final)
-- =====================================================================
DROP PROCEDURE IF EXISTS `_tes_assert`;

DELIMITER $$

CREATE PROCEDURE `_tes_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- 0) CONTROLES PREVIOS
-- =====================================================================
CALL `_tes_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_tes_assert`(@tes_empresa_id IS NOT NULL,
    'Editar el bloque PARAMETROS: @tes_empresa_id esta en NULL');

CALL `_tes_assert`(
    (SELECT COUNT(*) FROM conf__empresas WHERE empresa_id = @tes_empresa_id) = 1,
    'La empresa @tes_empresa_id no existe en conf__empresas');

CALL `_tes_assert`(@tes_num_alcance IN ('SUCURSAL', 'EMPRESA'),
    '@tes_num_alcance debe ser SUCURSAL o EMPRESA');

-- 01 + 02 ya corridos para esta empresa: los 13 tipos de tesorería existen
-- y tienen subgrupo.
CALL `_tes_assert`(
    (SELECT COUNT(DISTINCT codigo) FROM gestion__comprobantes_tipos
     WHERE empresa_id = @tes_empresa_id AND comprobante_subgrupo_id <> 0
       AND codigo IN ('RCX', 'OP', 'TB', 'DB', 'MC', 'ND_CHR', 'DEV_END', 'CHP_RECH',
                      'I_LIQ', 'I_PAG', 'RG_ANT', 'RG_REND', 'RG_REEM')) = 13,
    'Falta correr 20261003_tesoreria_02_siembra_empresa.sql para esta empresa');

SET @tes_er_activo = (SELECT estado_registro_id FROM conf__estados_registros
                      WHERE codigo_estandar = 'ACTIVO' ORDER BY estado_registro_id LIMIT 1);
CALL `_tes_assert`(@tes_er_activo IS NOT NULL,
    'No se resolvio el estado generico ACTIVO en conf__estados_registros');

-- ---------------------------------------------------------------------
-- 0b) DIAGNÓSTICO (solo lectura) para completar los valores de ejemplo
-- ---------------------------------------------------------------------
-- Sucursales, boca candidata para el PV interno (D29) y PV interno ya creado.
SELECT s.sucursal_id, s.sucursal_nombre,
       (SELECT b.boca_id FROM gestion__bocas b
         WHERE b.empresa_id = s.empresa_id AND b.sucursal_id = s.sucursal_id
           AND b.tabla_estado_registro_id = @tes_er_activo
         ORDER BY b.es_principal DESC, b.orden, b.boca_id LIMIT 1) AS boca_candidata_id,
       (SELECT p.punto_venta_id FROM gestion__puntos_venta p
         WHERE p.empresa_id = s.empresa_id AND p.sucursal_id = s.sucursal_id
           AND p.nombre = LEFT(CONCAT(@tes_pv_prefijo, s.sucursal_nombre), 100) COLLATE utf8mb4_unicode_ci
         ORDER BY p.punto_venta_id LIMIT 1) AS pv_interno_id
FROM gestion__sucursales s
WHERE s.empresa_id = @tes_empresa_id
ORDER BY s.sucursal_id;

-- Último número emitido (gestion__comprobantes) y último del numerador
-- (gestion__comprobantes_numeradores) por tipo existente y punto de venta.
-- comprobante_pv guarda el punto_venta_id (syncComprobante(),
-- ventas_facturas_model.php:462; numerador por punto_venta_id en
-- vfObtenerProximoNumero(), ventas_facturas_model.php:403-430).
SELECT ct.codigo, x.punto_venta_id, pv.nombre AS punto_venta, pv.sucursal_id,
       MAX(x.max_emitido) AS max_emitido, MAX(x.max_numerador) AS max_numerador
FROM (
    SELECT c.comprobante_tipo_id, c.comprobante_pv AS punto_venta_id,
           MAX(c.comprobante_nro) AS max_emitido, 0 AS max_numerador
    FROM gestion__comprobantes c
    WHERE c.empresa_id = @tes_empresa_id
    GROUP BY c.comprobante_tipo_id, c.comprobante_pv
    UNION ALL
    SELECT n.comprobante_tipo_id, n.punto_venta_id, 0, MAX(n.ultimo_numero)
    FROM gestion__comprobantes_numeradores n
    WHERE n.empresa_id = @tes_empresa_id
    GROUP BY n.comprobante_tipo_id, n.punto_venta_id
) x
JOIN gestion__comprobantes_tipos ct
  ON ct.comprobante_tipo_id = x.comprobante_tipo_id
 AND ct.empresa_id = @tes_empresa_id
 AND ct.codigo IN ('RCX', 'OP', 'TB', 'DB')
LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = x.punto_venta_id
GROUP BY ct.codigo, x.punto_venta_id, pv.nombre, pv.sucursal_id
ORDER BY ct.codigo, x.punto_venta_id;

START TRANSACTION;

-- =====================================================================
-- 1) CUENTAS DE TESORERÍA (gestion__tesoreria_cuentas, D4)
-- =====================================================================
-- Una fila por caja de sucursal, cuenta bancaria, cartera de valores y
-- cupones de tarjeta (por moneda). Clave natural: (empresa, codigo).
-- Ejemplo con el plan de cuentas de la empresa 2: 1.1.01.01 Caja Pesos,
-- 1.1.01.10 Banco Nación $, 1.1.01.12 Banco Provincia $, 1.1.01.13 Banco
-- Galicia $. El plan NO tiene «Valores a depositar» ni «Cupones a
-- acreditar»: VALORES y CUPONES quedan sin cuenta contable (NULL) hasta
-- crearlas. Solo hay moneda ARS en gestion__monedas (Caja USD / Banco
-- Nación USD del plan no tienen moneda todavía).
-- numero / cbu / alias de ejemplo en NULL: completar con los reales
-- (uk_tcta_empresa_cbu: un CBU no puede repetirse en la empresa).
-- saldo_actual arranca en 0: el saldo inicial se carga con un MC (ingreso)
-- confirmado, nunca editando la columna (materializada, A4).
--
-- INSERT INTO gestion__tesoreria_cuentas
--     (empresa_id, sucursal_id, cuenta_tipo, codigo, tesoreria_cuenta, moneda_id, banco_id,
--      banco_cuenta_tipo, numero, cbu, alias, cont_cuenta_id, saldo_actual,
--      permite_saldo_negativo, orden, tabla_estado_registro_id)
-- SELECT @tes_empresa_id, z.sucursal_id, z.cuenta_tipo, z.codigo, z.nombre, z.moneda_id, z.banco_id,
--        z.banco_cuenta_tipo, z.numero, z.cbu, z.alias, z.cont_cuenta_id, 0,
--        z.permite_negativo, z.orden, @tes_er_activo
-- FROM (
--     SELECT v.*,
--            (SELECT s.sucursal_id FROM gestion__sucursales s
--              WHERE s.empresa_id = @tes_empresa_id
--                AND s.sucursal_nombre = v.sucursal_nombre COLLATE utf8mb4_unicode_ci
--              ORDER BY s.sucursal_id LIMIT 1) AS sucursal_id,
--            (SELECT m.moneda_id FROM gestion__monedas m
--              WHERE m.empresa_id = @tes_empresa_id
--                AND m.codigo = v.moneda_codigo COLLATE utf8mb4_unicode_ci) AS moneda_id,
--            (SELECT b.banco_id FROM conf__bancos b
--              WHERE b.codigo_bcra = v.codigo_bcra COLLATE utf8mb4_unicode_ci) AS banco_id,
--            (SELECT cc.cont_cuenta_id FROM gestion__cont_cuentas cc
--              WHERE cc.empresa_id = @tes_empresa_id
--                AND cc.codigo = v.cont_codigo COLLATE utf8mb4_unicode_ci
--              ORDER BY cc.cont_cuenta_id LIMIT 1) AS cont_cuenta_id
--     FROM (
--                   SELECT 'CAJA' AS cuenta_tipo, 'CAJA_BB' AS codigo, 'Caja Bahia Blanca' AS nombre,
--                          'Bahia Blanca' AS sucursal_nombre, 'ARS' AS moneda_codigo,
--                          NULL AS codigo_bcra, NULL AS banco_cuenta_tipo, NULL AS numero,
--                          NULL AS cbu, NULL AS alias, '1.1.01.01' AS cont_codigo,
--                          0 AS permite_negativo, 1 AS orden
--         UNION ALL SELECT 'CAJA',    'CAJA_MDP',    'Caja Mar del Plata',      'Mar del Plata', 'ARS',
--                          NULL,  NULL, NULL, NULL, NULL, '1.1.01.01', 0, 2
--         UNION ALL SELECT 'BANCO',   'BNA_CC_ARS',  'Banco Nacion CC $',       NULL, 'ARS',
--                          '011', 'CC', NULL, NULL, NULL, '1.1.01.10', 1, 10
--         UNION ALL SELECT 'BANCO',   'BPBA_CC_ARS', 'Banco Provincia CC $',    NULL, 'ARS',
--                          '014', 'CC', NULL, NULL, NULL, '1.1.01.12', 1, 11
--         UNION ALL SELECT 'BANCO',   'GAL_CC_ARS',  'Banco Galicia CC $',      NULL, 'ARS',
--                          '007', 'CC', NULL, NULL, NULL, '1.1.01.13', 1, 12
--         UNION ALL SELECT 'VALORES', 'VALORES_ARS', 'Valores en cartera $',    NULL, 'ARS',
--                          NULL,  NULL, NULL, NULL, NULL, NULL,        0, 20
--         UNION ALL SELECT 'CUPONES', 'CUPONES_ARS', 'Cupones de tarjeta $',    NULL, 'ARS',
--                          NULL,  NULL, NULL, NULL, NULL, NULL,        0, 30
--     ) v
-- ) z
-- WHERE z.moneda_id IS NOT NULL
--   AND (z.sucursal_nombre IS NULL OR z.sucursal_id IS NOT NULL)
--   AND (z.codigo_bcra IS NULL OR z.banco_id IS NOT NULL)
--   AND (z.cont_codigo IS NULL OR z.cont_cuenta_id IS NOT NULL)
--   AND NOT EXISTS (SELECT 1 FROM gestion__tesoreria_cuentas x
--                   WHERE x.empresa_id = @tes_empresa_id
--                     AND x.codigo = z.codigo COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 2) MEDIOS DE PAGO (gestion__medios_pago)
-- =====================================================================
-- Clave natural: (empresa, codigo). tesoreria_cuenta_tipo restringe qué
-- cuentas admite la línea; tesoreria_cuenta_codigo es la cuenta SUGERIDA
-- (NULL = el usuario la elige; en EFECTIVO la caja depende de la sucursal).
-- Las retenciones SUFRIDAS (cobranzas) usan los tipos existentes
-- 'Retención IIBB' / 'Retención IVA' (aplica_venta = 1); van con
-- aplica_pago = 0 porque las PRACTICADAS van en gestion__pagos_retenciones
-- (D21). cont_cuenta del medio RETENCION: completar con la cuenta de
-- «Retenciones sufridas» del plan (en el ejemplo, NULL).
-- Requiere el bloque 1 (cuentas sugeridas por codigo).
--
-- INSERT INTO gestion__medios_pago
--     (empresa_id, codigo, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo, tesoreria_cuenta_id,
--      cont_cuenta_id, impuesto_tipo_id, requiere_detalle, aplica_cobro, aplica_pago, orden,
--      tabla_estado_registro_id)
-- SELECT @tes_empresa_id, z.codigo, z.nombre, z.medio_tipo, z.cuenta_tipo, z.tesoreria_cuenta_id,
--        z.cont_cuenta_id, z.impuesto_tipo_id, z.requiere_detalle, z.aplica_cobro, z.aplica_pago,
--        z.orden, @tes_er_activo
-- FROM (
--     SELECT v.*,
--            (SELECT t.tesoreria_cuenta_id FROM gestion__tesoreria_cuentas t
--              WHERE t.empresa_id = @tes_empresa_id
--                AND t.codigo = v.tesoreria_cuenta_codigo COLLATE utf8mb4_unicode_ci) AS tesoreria_cuenta_id,
--            (SELECT cc.cont_cuenta_id FROM gestion__cont_cuentas cc
--              WHERE cc.empresa_id = @tes_empresa_id
--                AND cc.codigo = v.cont_codigo COLLATE utf8mb4_unicode_ci
--              ORDER BY cc.cont_cuenta_id LIMIT 1) AS cont_cuenta_id,
--            (SELECT it.impuesto_tipo_id FROM gestion__impuestos_tipos it
--              WHERE it.impuesto_tipo = v.impuesto_tipo COLLATE utf8mb4_unicode_ci
--              ORDER BY it.impuesto_tipo_id LIMIT 1) AS impuesto_tipo_id
--     FROM (
--                   SELECT 'EFECTIVO' AS codigo, 'Efectivo' AS nombre, 'EFECTIVO' AS medio_tipo,
--                          'CAJA' AS cuenta_tipo, NULL AS tesoreria_cuenta_codigo, NULL AS cont_codigo,
--                          NULL AS impuesto_tipo, 0 AS requiere_detalle, 1 AS aplica_cobro,
--                          1 AS aplica_pago, 1 AS orden
--         UNION ALL SELECT 'CHQ_TERCERO', 'Cheque de tercero',   'CHEQUE_TERCERO',  'VALORES', 'VALORES_ARS', NULL, NULL, 1, 1, 1, 2
--         UNION ALL SELECT 'CHQ_PROPIO',  'Cheque propio',       'CHEQUE_PROPIO',   'BANCO',   NULL,          NULL, NULL, 1, 0, 1, 3
--         UNION ALL SELECT 'TRANSFER',    'Transferencia',       'TRANSFERENCIA',   'BANCO',   NULL,          NULL, NULL, 1, 1, 1, 4
--         UNION ALL SELECT 'DEPOSITO',    'Deposito bancario',   'DEPOSITO',        'BANCO',   NULL,          NULL, NULL, 1, 1, 0, 5
--         UNION ALL SELECT 'TARJ_DEB',    'Tarjeta de debito',   'TARJETA_DEBITO',  'CUPONES', 'CUPONES_ARS', NULL, NULL, 1, 1, 0, 6
--         UNION ALL SELECT 'TARJ_CRED',   'Tarjeta de credito',  'TARJETA_CREDITO', 'CUPONES', 'CUPONES_ARS', NULL, NULL, 1, 1, 0, 7
--         UNION ALL SELECT 'RET_IIBB',    'Retencion IIBB sufrida', 'RETENCION',    NULL,      NULL,          NULL, 'Retención IIBB', 1, 1, 0, 20
--         UNION ALL SELECT 'RET_IVA',     'Retencion IVA sufrida',  'RETENCION',    NULL,      NULL,          NULL, 'Retención IVA',  1, 1, 0, 21
--     ) v
-- ) z
-- WHERE (z.tesoreria_cuenta_codigo IS NULL OR z.tesoreria_cuenta_id IS NOT NULL)
--   AND (z.cont_codigo IS NULL OR z.cont_cuenta_id IS NOT NULL)
--   AND (z.impuesto_tipo IS NULL OR z.impuesto_tipo_id IS NOT NULL)
--   AND NOT EXISTS (SELECT 1 FROM gestion__medios_pago x
--                   WHERE x.empresa_id = @tes_empresa_id
--                     AND x.codigo = z.codigo COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 3) CATEGORÍAS DE GASTO (gestion__categorias_gasto)
-- =====================================================================
-- Las del Word §6.2. cont_codigo en NULL: el plan de la empresa 2 solo tiene
-- cuentas título de gastos (6.1, 6.2, 6.3, no imputables); completar con
-- cuentas imputables cuando existan. Clave natural: (empresa, codigo).
--
-- INSERT INTO gestion__categorias_gasto
--     (empresa_id, codigo, categoria_gasto, cont_cuenta_id, requiere_comprobante,
--      requiere_justificacion, orden, tabla_estado_registro_id)
-- SELECT @tes_empresa_id, z.codigo, z.nombre, z.cont_cuenta_id, z.requiere_comprobante,
--        z.requiere_justificacion, z.orden, @tes_er_activo
-- FROM (
--     SELECT v.*,
--            (SELECT cc.cont_cuenta_id FROM gestion__cont_cuentas cc
--              WHERE cc.empresa_id = @tes_empresa_id
--                AND cc.codigo = v.cont_codigo COLLATE utf8mb4_unicode_ci
--              ORDER BY cc.cont_cuenta_id LIMIT 1) AS cont_cuenta_id
--     FROM (
--                   SELECT 'COMBUSTIBLE' AS codigo, 'Combustible' AS nombre, NULL AS cont_codigo,
--                          1 AS requiere_comprobante, 0 AS requiere_justificacion, 1 AS orden
--         UNION ALL SELECT 'VIATICOS',   'Viaticos',   NULL, 1, 0, 2
--         UNION ALL SELECT 'PEAJE',      'Peajes',     NULL, 1, 0, 3
--         UNION ALL SELECT 'COMIDAS',    'Comidas',    NULL, 1, 0, 4
--         UNION ALL SELECT 'MATERIALES', 'Materiales', NULL, 1, 0, 5
--         UNION ALL SELECT 'CORREO',     'Correo',     NULL, 1, 0, 6
--         UNION ALL SELECT 'OTROS',      'Otros',      NULL, 1, 1, 99
--     ) v
-- ) z
-- WHERE (z.cont_codigo IS NULL OR z.cont_cuenta_id IS NOT NULL)
--   AND NOT EXISTS (SELECT 1 FROM gestion__categorias_gasto x
--                   WHERE x.empresa_id = @tes_empresa_id
--                     AND x.codigo = z.codigo COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 4) NUMERADORES DE CERTIFICADOS DE RETENCIÓN (gestion__retenciones_numeradores)
-- =====================================================================
-- Word v3.6 §9.1 fila 9: ultimo_numero arranca desde el último certificado
-- YA emitido por impuesto (si la empresa ya emitía certificados fuera del
-- sistema). Si no hay fila, la app la crea con 1 al confirmar la primera OP
-- con retenciones (01 §7.5c): este bloque solo hace falta si hay historia.
-- 4a crea la fila si falta; 4b la sube si el valor indicado es mayor (nunca
-- la baja: no se reutilizan números ya emitidos). Repetible.
--
-- 4a)
-- INSERT INTO gestion__retenciones_numeradores (empresa_id, impuesto_tipo_id, ultimo_numero)
-- SELECT @tes_empresa_id, it.impuesto_tipo_id, v.ultimo_numero
-- FROM (
--               SELECT 'Retencion Ganancias practicada' AS impuesto_tipo, 0 AS ultimo_numero
--     UNION ALL SELECT 'Retencion IVA practicada',  0
--     UNION ALL SELECT 'Retencion IIBB practicada', 0
--     UNION ALL SELECT 'Retencion SUSS practicada', 0
-- ) v
-- JOIN gestion__impuestos_tipos it ON it.impuesto_tipo = v.impuesto_tipo COLLATE utf8mb4_unicode_ci
-- WHERE NOT EXISTS (SELECT 1 FROM gestion__retenciones_numeradores x
--                   WHERE x.empresa_id = @tes_empresa_id AND x.impuesto_tipo_id = it.impuesto_tipo_id);
--
-- 4b) (mismos valores que 4a)
-- UPDATE gestion__retenciones_numeradores n
-- JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = n.impuesto_tipo_id
-- JOIN (
--               SELECT 'Retencion Ganancias practicada' AS impuesto_tipo, 0 AS ultimo_numero
--     UNION ALL SELECT 'Retencion IVA practicada',  0
--     UNION ALL SELECT 'Retencion IIBB practicada', 0
--     UNION ALL SELECT 'Retencion SUSS practicada', 0
-- ) v ON v.impuesto_tipo = it.impuesto_tipo COLLATE utf8mb4_unicode_ci
--    SET n.ultimo_numero = v.ultimo_numero
--  WHERE n.empresa_id = @tes_empresa_id
--    AND n.ultimo_numero < v.ultimo_numero;

-- =====================================================================
-- 5) PUNTOS DE VENTA INTERNOS POR SUCURSAL (D29) — COMENTADO POR DEFECTO
-- =====================================================================
-- Word v3.6 §4.9: tesorería numera con un punto de venta INTERNO (no fiscal)
-- por sucursal, para no consumir numeración fiscal; la numeración de los
-- tipos existentes (RCX, OP, TB, DB) continúa desde el último número
-- emitido; los tipos se habilitan en gestion__puntos_venta_comprobantes.
--
-- Cómo lo usa el código (por eso el bloque es así):
--   - comprobante_pv de las operativas y de gestion__comprobantes guarda el
--     punto_venta_id (syncComprobante(), ventas_facturas_model.php:454-462
--     y :998; ventas_remitos_model.php:15-16 documenta lo mismo).
--   - El numerador es por (empresa_id, punto_venta_id, comprobante_tipo_id)
--     (UNIQUE uk_numerador) y se toma con FOR UPDATE; si no hay fila la crea
--     con 1 (vfObtenerProximoNumero(), ventas_facturas_model.php:403-430).
--   - Los tipos que ofrece una página son los del subgrupo con su tabla_id
--     INTERSECTADOS con los habilitados en el PV con
--     tabla_estado_registro_id = 1 (vfTiposPorPuntoVenta(),
--     ventas_facturas_model.php:233-262).
--   - El ABM de puntos de venta EXIGE boca (puntos_venta_model.php:305-307
--     y :470-471) y lista / obtiene con INNER JOIN gestion__bocas
--     (puntos_venta_model.php:251 y :713): un PV sin boca_id no aparece en
--     el ABM. Por eso se usa la boca
--     activa principal de la sucursal y, si la sucursal no tiene boca, NO se
--     crea el PV (ver diagnóstico 0b: boca_candidata_id NULL).
--   - codigo_fiscal NULL = PV no fiscal: arca_integraciones_model.php:149-152
--     solo toma PV con codigo_fiscal IS NOT NULL para ARCA.
--   - requiere_afip = 0 en los tipos habilitados (comprobantes internos).
--   - Si después se edita el PV en el ABM, guardarComprobantesPuntoVenta()
--     (puntos_venta_model.php:659-703) BORRA y reinserta los tipos tildados:
--     verificar que sigan tildados los de tesorería (se listan porque son
--     tipos activos de la empresa, obtenerComprobantesTipos(),
--     puntos_venta_model.php:591).
--
-- Numeración que "continúa" (5.3): para RCX, OP, TB y DB el numerador del
-- PV interno arranca en el MAYOR entre el último comprobante_nro emitido y
-- el último ultimo_numero de los numeradores de ese tipo, en los PV de la
-- misma sucursal (@tes_num_alcance = 'SUCURSAL') o de toda la empresa
-- ('EMPRESA'). Los tipos nuevos (MC, ND_CHR, DEV_END, CHP_RECH, I_LIQ,
-- I_PAG, RG_*) no tienen historia: el numerador lo crea la app con 1.
-- Volcados del 2026-10-03: NO hay comprobantes RCX/OP/TB/DB emitidos ni
-- numeradores de esos tipos en local ni en producción, así que hoy todos
-- arrancarían en 0 (el primero será el 1). La continuidad es de
-- presentación: el UNIQUE uk_comp_numeracion es por punto de venta, así que
-- no hay choque con números de otros PV.
-- Repetible: el PV se identifica por (empresa, sucursal, nombre =
-- @tes_pv_prefijo + sucursal_nombre); tipos y numeradores por sus UNIQUE.
-- Un numerador que ya existe NO se toca (puede haber avanzado).
--
-- 5.1) Un PV interno por sucursal activa (con boca activa).
-- INSERT INTO gestion__puntos_venta
--     (empresa_id, sucursal_id, boca_id, nombre, descripcion, codigo_fiscal, es_web,
--      tabla_estado_registro_id)
-- SELECT z.empresa_id, z.sucursal_id, z.boca_id, z.nombre,
--        'Punto de venta interno (no fiscal) de tesoreria - D29', NULL, 0, @tes_er_activo
-- FROM (
--     SELECT s.empresa_id, s.sucursal_id,
--            LEFT(CONCAT(@tes_pv_prefijo, s.sucursal_nombre), 100) AS nombre,
--            (SELECT b.boca_id FROM gestion__bocas b
--              WHERE b.empresa_id = s.empresa_id AND b.sucursal_id = s.sucursal_id
--                AND b.tabla_estado_registro_id = @tes_er_activo
--              ORDER BY b.es_principal DESC, b.orden, b.boca_id LIMIT 1) AS boca_id
--     FROM gestion__sucursales s
--     WHERE s.empresa_id = @tes_empresa_id
--       AND s.tabla_estado_registro_id = @tes_er_activo
--       AND (@tes_pv_sucursal_id IS NULL OR s.sucursal_id = @tes_pv_sucursal_id)
-- ) z
-- WHERE z.boca_id IS NOT NULL
--   AND NOT EXISTS (SELECT 1 FROM gestion__puntos_venta p
--                   WHERE p.empresa_id = z.empresa_id AND p.sucursal_id = z.sucursal_id
--                     AND p.nombre = z.nombre COLLATE utf8mb4_unicode_ci);
--
-- 5.2) Habilitar los 13 tipos de tesorería en cada PV interno.
-- INSERT INTO gestion__puntos_venta_comprobantes
--     (empresa_id, punto_venta_id, comprobante_tipo_id, requiere_afip, tabla_estado_registro_id)
-- SELECT @tes_empresa_id, p.punto_venta_id, ct.comprobante_tipo_id, 0, @tes_er_activo
-- FROM gestion__puntos_venta p
-- JOIN gestion__sucursales s
--   ON s.sucursal_id = p.sucursal_id AND s.empresa_id = p.empresa_id
-- JOIN gestion__comprobantes_tipos ct
--   ON ct.empresa_id = @tes_empresa_id
--  AND ct.codigo IN ('RCX', 'OP', 'TB', 'DB', 'MC', 'ND_CHR', 'DEV_END', 'CHP_RECH',
--                    'I_LIQ', 'I_PAG', 'RG_ANT', 'RG_REND', 'RG_REEM')
-- WHERE p.empresa_id = @tes_empresa_id
--   AND p.nombre = LEFT(CONCAT(@tes_pv_prefijo, s.sucursal_nombre), 100) COLLATE utf8mb4_unicode_ci
--   AND (@tes_pv_sucursal_id IS NULL OR p.sucursal_id = @tes_pv_sucursal_id)
--   AND NOT EXISTS (SELECT 1 FROM gestion__puntos_venta_comprobantes x
--                   WHERE x.punto_venta_id = p.punto_venta_id
--                     AND x.comprobante_tipo_id = ct.comprobante_tipo_id);
--
-- 5.3) Numeradores de RCX / OP / TB / DB en cada PV interno, desde el
--      último número emitido (ver arriba). INSERT ... SELECT sobre la misma
--      tabla está permitido en ambos motores (usa tabla temporal interna).
-- INSERT INTO gestion__comprobantes_numeradores
--     (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero)
-- SELECT z.empresa_id, z.punto_venta_id, z.comprobante_tipo_id,
--        GREATEST(z.max_emitido, z.max_numerador)
-- FROM (
--     SELECT p.empresa_id, p.punto_venta_id, ct.comprobante_tipo_id,
--            COALESCE((SELECT MAX(c.comprobante_nro)
--                        FROM gestion__comprobantes c
--                        LEFT JOIN gestion__puntos_venta pc ON pc.punto_venta_id = c.comprobante_pv
--                       WHERE c.empresa_id = @tes_empresa_id
--                         AND c.comprobante_tipo_id = ct.comprobante_tipo_id
--                         AND (@tes_num_alcance = 'EMPRESA' OR pc.sucursal_id = p.sucursal_id)), 0) AS max_emitido,
--            COALESCE((SELECT MAX(n.ultimo_numero)
--                        FROM gestion__comprobantes_numeradores n
--                        LEFT JOIN gestion__puntos_venta pn ON pn.punto_venta_id = n.punto_venta_id
--                       WHERE n.empresa_id = @tes_empresa_id
--                         AND n.comprobante_tipo_id = ct.comprobante_tipo_id
--                         AND (@tes_num_alcance = 'EMPRESA' OR pn.sucursal_id = p.sucursal_id)), 0) AS max_numerador
--     FROM gestion__puntos_venta p
--     JOIN gestion__sucursales s
--       ON s.sucursal_id = p.sucursal_id AND s.empresa_id = p.empresa_id
--     JOIN gestion__comprobantes_tipos ct
--       ON ct.empresa_id = @tes_empresa_id
--      AND ct.codigo IN ('RCX', 'OP', 'TB', 'DB')
--     WHERE p.empresa_id = @tes_empresa_id
--       AND p.nombre = LEFT(CONCAT(@tes_pv_prefijo, s.sucursal_nombre), 100) COLLATE utf8mb4_unicode_ci
--       AND (@tes_pv_sucursal_id IS NULL OR p.sucursal_id = @tes_pv_sucursal_id)
-- ) z
-- WHERE NOT EXISTS (SELECT 1 FROM gestion__comprobantes_numeradores x
--                   WHERE x.empresa_id = z.empresa_id
--                     AND x.punto_venta_id = z.punto_venta_id
--                     AND x.comprobante_tipo_id = z.comprobante_tipo_id);

COMMIT;

-- =====================================================================
-- V) VERIFICACIÓN (solo lectura)
-- =====================================================================
SELECT tc.tesoreria_cuenta_id, tc.codigo, tc.cuenta_tipo, tc.tesoreria_cuenta, tc.sucursal_id,
       tc.moneda_id, b.codigo_bcra, tc.cont_cuenta_id, tc.saldo_actual
FROM gestion__tesoreria_cuentas tc
LEFT JOIN conf__bancos b ON b.banco_id = tc.banco_id
WHERE tc.empresa_id = @tes_empresa_id
ORDER BY tc.orden, tc.codigo;

SELECT mp.medio_pago_id, mp.codigo, mp.medio_pago, mp.medio_pago_tipo, mp.tesoreria_cuenta_tipo,
       mp.tesoreria_cuenta_id, mp.impuesto_tipo_id, mp.aplica_cobro, mp.aplica_pago
FROM gestion__medios_pago mp
WHERE mp.empresa_id = @tes_empresa_id
ORDER BY mp.orden, mp.codigo;

SELECT cg.categoria_gasto_id, cg.codigo, cg.categoria_gasto, cg.cont_cuenta_id
FROM gestion__categorias_gasto cg
WHERE cg.empresa_id = @tes_empresa_id
ORDER BY cg.orden, cg.codigo;

SELECT rn.impuesto_tipo_id, it.impuesto_tipo, rn.ultimo_numero
FROM gestion__retenciones_numeradores rn
JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = rn.impuesto_tipo_id
WHERE rn.empresa_id = @tes_empresa_id
ORDER BY rn.impuesto_tipo_id;

-- PV internos (D29) con sus tipos habilitados y numeradores.
SELECT p.punto_venta_id, p.nombre, p.sucursal_id, p.boca_id, p.codigo_fiscal,
       ct.codigo, pvc.requiere_afip, pvc.tabla_estado_registro_id AS pvc_estado,
       n.ultimo_numero
FROM gestion__puntos_venta p
JOIN gestion__sucursales s
  ON s.sucursal_id = p.sucursal_id AND s.empresa_id = p.empresa_id
LEFT JOIN gestion__puntos_venta_comprobantes pvc
       ON pvc.punto_venta_id = p.punto_venta_id AND pvc.empresa_id = p.empresa_id
LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = pvc.comprobante_tipo_id
LEFT JOIN gestion__comprobantes_numeradores n
       ON n.empresa_id = p.empresa_id AND n.punto_venta_id = p.punto_venta_id
      AND n.comprobante_tipo_id = pvc.comprobante_tipo_id
WHERE p.empresa_id = @tes_empresa_id
  AND p.nombre = LEFT(CONCAT(@tes_pv_prefijo, s.sucursal_nombre), 100) COLLATE utf8mb4_unicode_ci
ORDER BY p.punto_venta_id, ct.orden, ct.codigo;

-- =====================================================================
-- Limpieza del helper y restauración del sql_mode
-- =====================================================================
DROP PROCEDURE IF EXISTS `_tes_assert`;

SET SESSION sql_mode = @tes_old_sql_mode;
