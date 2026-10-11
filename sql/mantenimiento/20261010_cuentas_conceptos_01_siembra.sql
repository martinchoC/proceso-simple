-- =====================================================================
-- Cuentas contables por defecto por concepto (D35/D36) - script 01: SIEMBRA
-- 2026-10-10
--
-- Cada empresa tiene su propio plan de cuentas. El PHP de modules/gestion y
-- modules/gestion_normal ya no busca cuentas por id fijo (111, 600, 331, 851...)
-- ni por código del plan de Casa Lucho: las cuentas por defecto salen del
-- concepto de la empresa en gestion__empresas_cuentas_conceptos (página
-- Tesorería > Conceptos Contables), ver core/empresa_config.php
-- (emp_cuenta_id / emp_cuenta_concepto_id). Si el concepto no tiene cuenta,
-- el documento no se confirma y el mensaje dice qué concepto configurar.
--
-- Este script agrega a cada empresa que tenga plan de cuentas los conceptos:
--   DEUDORES_VENTAS, VENTAS, IVA_DEBITO, PROVEEDORES, VALORES_CARTERA,
--   IVA_A_PAGAR, GASTOS_GENERALES, MERCADERIAS
--   (+ EMPLEADOS y VALORES_A_PAGAR de tesorería, si la empresa no los tiene).
-- Preasigna la cuenta que el código usaba hasta ahora, buscándola por código en
-- el plan de ESA empresa (en Casa Lucho: 111, 600, 331, 301, 851, 332, 873, 131,
-- 856, 857), así el comportamiento no cambia. Si la empresa no tiene una cuenta
-- con ese código, el concepto queda con cuenta NULL para asignarla en la página.
--
-- ORDEN: correr ANTES de publicar el PHP que usa emp_cuenta_id(). Sin el
-- concepto, el PHP usa gestion__cont_parametros, y en Casa Lucho esos
-- parámetros apuntan todos a 1.1.01.01 Caja Pesos.
--
-- Idempotente: NOT EXISTS + UNIQUE uk_ecc_empresa_concepto. No pisa conceptos
-- existentes. Sin DDL. MariaDB 10.4 y MySQL 8.0.
-- =====================================================================

START TRANSACTION;

INSERT INTO gestion__empresas_cuentas_conceptos
    (empresa_id, concepto, cont_cuenta_id, tabla_estado_registro_id)
SELECT e.empresa_id,
       v.concepto,
       (SELECT c.cont_cuenta_id
          FROM gestion__cont_cuentas c
         WHERE c.empresa_id = e.empresa_id
           AND c.codigo = v.codigo COLLATE utf8mb4_unicode_ci
           AND c.es_imputable = 1
           AND c.tabla_estado_registro_id = 1
         ORDER BY c.cont_cuenta_id
         LIMIT 1),
       1
  FROM conf__empresas e
  JOIN (SELECT 'DEUDORES_VENTAS' AS concepto, '1.1.02.01' AS codigo
        UNION ALL SELECT 'VENTAS',           '4.1.01'
        UNION ALL SELECT 'IVA_DEBITO',       '2.1.04.01'
        UNION ALL SELECT 'PROVEEDORES',      '2.1.01.01'
        UNION ALL SELECT 'VALORES_CARTERA',  '1.1.01.03'
        UNION ALL SELECT 'IVA_A_PAGAR',      '2.1.04.02'
        UNION ALL SELECT 'GASTOS_GENERALES', '6.4.07'
        UNION ALL SELECT 'MERCADERIAS',      '1.1.04.01'
        UNION ALL SELECT 'EMPLEADOS',        '1.1.03.09'
        UNION ALL SELECT 'VALORES_A_PAGAR',  '2.1.01.03') v
 WHERE EXISTS (SELECT 1 FROM gestion__cont_cuentas c WHERE c.empresa_id = e.empresa_id)
   AND NOT EXISTS (SELECT 1
                     FROM gestion__empresas_cuentas_conceptos x
                    WHERE x.empresa_id = e.empresa_id
                      AND x.concepto = v.concepto COLLATE utf8mb4_unicode_ci);

COMMIT;

-- ---------------------------------------------------------------------
-- Verificación: conceptos por empresa y su cuenta. Los que queden con
-- cuenta NULL hay que asignarlos en Tesorería > Conceptos Contables.
-- ---------------------------------------------------------------------
SELECT ecc.empresa_id, ecc.concepto, c.codigo, c.nombre,
       CASE WHEN ecc.cont_cuenta_id IS NULL THEN 'SIN CUENTA: configurar' ELSE 'ok' END AS estado
  FROM gestion__empresas_cuentas_conceptos ecc
  LEFT JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = ecc.cont_cuenta_id
 ORDER BY ecc.empresa_id, ecc.concepto;
