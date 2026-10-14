-- ==============================================================================
-- 20261012_sindicatos_10_conceptos_tipos_cuentas.sql
-- Módulo 6 (Sindicatos): tipo de comprobante y cuenta de recurso por concepto de aporte.
--
-- El código elegía el tipo por el código del concepto (ART101 -> DCS/RCS, cualquier otro -> DFS/RFS)
-- y la cuenta de recurso como 'SINDI_APORTE_' + código, ignorando sindi__conceptos. Ahora cada
-- concepto declara lo suyo (sindi_comprobantes_core.php):
--   - comprobante_tipo_id            tipo de la deuda (ya existía, estaba vacío)
--   - comprobante_tipo_reversion_id  tipo de la reversión por rectificativa (nuevo)
--   - cuenta_concepto_codigo         concepto contable del RECURSO (estaba cargado con la cuenta de deudores)
--
-- 1. Agrega comprobante_tipo_reversion_id.
-- 2. Completa los conceptos ART100 / ART101 existentes con los tipos DFS/RFS y DCS/RCS de su empresa.
-- 3. Corrige cuenta_concepto_codigo = 'SINDI_APORTE_' + código donde quedó 'SINDI_DEUDORES_APORTES'.
-- 4. Borra los conceptos contables SINDI_* que apuntan a cuentas de OTRA empresa (inválidos:
--    emp_cuenta_concepto_id() ya los ignoraba; así el error "falta la cuenta" es claro).
--
-- Reanudable.
-- ==============================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sindi__conceptos'
                  AND COLUMN_NAME = 'comprobante_tipo_reversion_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE sindi__conceptos ADD COLUMN comprobante_tipo_reversion_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''Tipo de comprobante de la reversión por rectificativa (signo -1)'' AFTER comprobante_tipo_id',
    'SELECT ''comprobante_tipo_reversion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Tipos de los conceptos existentes (solo si están vacíos)
UPDATE sindi__conceptos c
INNER JOIN gestion__comprobantes_tipos t
        ON t.empresa_id = c.empresa_id
       AND t.codigo = CASE c.codigo WHEN 'ART100' THEN 'DFS' WHEN 'ART101' THEN 'DCS' END
SET c.comprobante_tipo_id = t.comprobante_tipo_id
WHERE c.comprobante_tipo_id IS NULL AND c.codigo IN ('ART100', 'ART101');

UPDATE sindi__conceptos c
INNER JOIN gestion__comprobantes_tipos t
        ON t.empresa_id = c.empresa_id
       AND t.codigo = CASE c.codigo WHEN 'ART100' THEN 'RFS' WHEN 'ART101' THEN 'RCS' END
SET c.comprobante_tipo_reversion_id = t.comprobante_tipo_id
WHERE c.comprobante_tipo_reversion_id IS NULL AND c.codigo IN ('ART100', 'ART101');

-- 3. Cuenta de recurso
UPDATE sindi__conceptos
SET cuenta_concepto_codigo = CONCAT('SINDI_APORTE_', codigo)
WHERE cuenta_concepto_codigo IS NULL OR cuenta_concepto_codigo IN ('', 'SINDI_DEUDORES_APORTES');

-- 4. Conceptos contables con cuentas de otra empresa
DELETE ecc FROM gestion__empresas_cuentas_conceptos ecc
INNER JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = ecc.cont_cuenta_id
WHERE ecc.concepto LIKE 'SINDI\_%' AND c.empresa_id <> ecc.empresa_id;

-- Verificación: conceptos activos sin tipo o sin cuenta de recurso configurada en su empresa
SELECT c.empresa_id, c.codigo,
       IF(c.comprobante_tipo_id IS NULL, 'FALTA', 'ok') AS tipo_deuda,
       IF(c.comprobante_tipo_reversion_id IS NULL, 'FALTA', 'ok') AS tipo_reversion,
       c.cuenta_concepto_codigo,
       IF(EXISTS (SELECT 1 FROM gestion__empresas_cuentas_conceptos e
                  JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = e.cont_cuenta_id AND cc.empresa_id = e.empresa_id
                  WHERE e.empresa_id = c.empresa_id AND e.concepto = c.cuenta_concepto_codigo), 'ok', 'FALTA') AS cuenta
FROM sindi__conceptos c
WHERE c.tabla_estado_registro_id = 1
ORDER BY c.empresa_id, c.codigo;
