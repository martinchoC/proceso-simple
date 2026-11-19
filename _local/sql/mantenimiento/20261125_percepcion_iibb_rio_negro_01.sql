-- Percepción de IIBB Río Negro: código de tributo de ARCA y alícuotas por situación.
-- =====================================================================================================
-- 20261125_percepcion_iibb_rio_negro_01.sql
-- Diseño en _local/docs/gestion/09_percepciones_iibb.md (§ arca_tributo_id, paso 7).
--
--   1. arca_tributo_id = 7 ("Percepción de IIBB", verificado con FEParamGetTiposTributos el 2026-11-18) en las
--      configs de percepción IIBB efectuada (impuesto_tipo_id 6) de Río Negro (jurisdiccion_id 8) que no lo tienen.
--      Sin él, la factura con percepción no se puede aprobar en ARCA (<Tributos> sin Id).
--   2. Alícuotas de Río Negro: 1% contribuyentes locales del régimen general (INSCRIPTO_JURISDICCION) y 0,5%
--      contribuyentes de Convenio Multilateral (CONVENIO_JURISDICCION). Si a una config le falta la fila de CM,
--      se crea copiando la del local.
-- Filtra por jurisdicción e impuesto (no por empresa_impuesto_config_id): sirve para todas las empresas.
-- Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- 1. Código de tributo de ARCA
UPDATE gestion__empresas_impuestos_config
SET arca_tributo_id = 7
WHERE impuesto_tipo_id = 6 AND jurisdiccion_id = 8
  AND (arca_tributo_id IS NULL OR arca_tributo_id = 0);

-- 2a. Fila de Convenio Multilateral faltante (copia la del inscripto local)
INSERT INTO gestion__empresas_impuestos_config_situaciones
       (empresa_impuesto_config_id, situacion, requiere_territorio, alicuota, tipo_registro_ddjj, declarar_si_cero,
        leyenda, tabla_estado_registro_id)
SELECT s.empresa_impuesto_config_id, 'CONVENIO_JURISDICCION', s.requiere_territorio, 0.5000, s.tipo_registro_ddjj,
       s.declarar_si_cero, s.leyenda, 1
FROM gestion__empresas_impuestos_config_situaciones s
JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = s.empresa_impuesto_config_id
WHERE cfg.impuesto_tipo_id = 6 AND cfg.jurisdiccion_id = 8
  AND s.situacion = 'INSCRIPTO_JURISDICCION' AND s.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config_situaciones x
                  WHERE x.empresa_impuesto_config_id = s.empresa_impuesto_config_id
                    AND x.situacion = 'CONVENIO_JURISDICCION' AND x.tabla_estado_registro_id = 1);

-- 2b. Alícuotas
UPDATE gestion__empresas_impuestos_config_situaciones s
JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = s.empresa_impuesto_config_id
SET s.alicuota = CASE s.situacion WHEN 'INSCRIPTO_JURISDICCION' THEN 1.0000 ELSE 0.5000 END
WHERE cfg.impuesto_tipo_id = 6 AND cfg.jurisdiccion_id = 8 AND s.tabla_estado_registro_id = 1
  AND s.situacion IN ('INSCRIPTO_JURISDICCION', 'CONVENIO_JURISDICCION');

COMMIT;

-- Control
SELECT cfg.empresa_id, cfg.empresa_impuesto_config_id, cfg.arca_tributo_id, s.situacion, s.alicuota
FROM gestion__empresas_impuestos_config cfg
LEFT JOIN gestion__empresas_impuestos_config_situaciones s
       ON s.empresa_impuesto_config_id = cfg.empresa_impuesto_config_id AND s.tabla_estado_registro_id = 1
WHERE cfg.impuesto_tipo_id = 6 AND cfg.jurisdiccion_id = 8 AND cfg.tabla_estado_registro_id = 1
ORDER BY cfg.empresa_id, s.situacion;
