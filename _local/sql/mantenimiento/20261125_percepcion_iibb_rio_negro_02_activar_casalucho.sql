-- @migracion: particular
-- Casa Lucho (empresa 2) como agente de percepción de IIBB de Río Negro, desde el 19/11/2026.
-- =====================================================================================================
-- 20261125_percepcion_iibb_rio_negro_02_activar_casalucho.sql
-- Para producción (ps_gestion_casalucho). Diseño en _local/docs/gestion/09_percepciones_iibb.md.
--
-- En producción la config de percepción IIBB Río Negro de la empresa 2 ya existe (impuesto 6, jurisdicción 8,
-- cuenta 2.1.04.06 "Percepciones IIBB Río Negro a Depositar", subgrupo Facturación, alícuotas por situación), pero
-- inactiva (estado 2) y sin N° de agente ni tributo ARCA. Este script:
--   1. La activa con vigencia desde el 19/11/2026: solo las facturas con fecha de emisión desde ese día llevan percepción.
--   2. N° de agente = CUIT 30711256780 (se imprime en la factura) si no tiene uno cargado.
--   3. Tributo ARCA 7 "Percepción de IIBB" (FEParamGetTiposTributos) si no lo tiene.
--   4. Alícuotas: 1% inscripto local del régimen general, 0,5% Convenio Multilateral (crea la fila de CM si falta).
--   5. Que esté ligada al subgrupo de comprobantes Facturación (código FACT) de la empresa.
-- Queda pendiente el código de régimen SIRCAR (campo 10), que solo usa la exportación de la DDJJ: se carga en
-- Impuestos Adic. - Conf (página 73), solapa "Agente de Percepción".
-- Busca la config por empresa + impuesto + jurisdicción (no por id). Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;

SET @empresa_id   := 2;
SET @impuesto_id  := 6;            -- Percepción IIBB Efectuadas
SET @jurisdiccion := 8;            -- Río Negro (DGR)
SET @f_desde      := '2026-11-19';
SET @nro_agente   := '30711256780';
SET @tributo_arca := 7;            -- Percepción de IIBB

SET @cfg := (SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config
             WHERE empresa_id = @empresa_id AND impuesto_tipo_id = @impuesto_id AND jurisdiccion_id = @jurisdiccion
               AND tabla_estado_registro_id IN (1, 2)
             ORDER BY tabla_estado_registro_id, empresa_impuesto_config_id DESC LIMIT 1);

-- Controles previos: sin config o sin cuenta contable válida de la empresa, el script no cambia nada
SELECT IF(@cfg IS NULL,
          'ERROR: la empresa 2 no tiene configuración de percepción IIBB Río Negro. No se cambió nada: cargarla en Impuestos Adic. - Conf (página 73).',
          CONCAT('Config de percepción IIBB Río Negro encontrada: empresa_impuesto_config_id = ', @cfg)) AS control_config;

SET @cuenta_ok := (SELECT COUNT(*) FROM gestion__empresas_impuestos_config cfg
                   JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = cfg.cont_cuenta_id
                   WHERE cfg.empresa_impuesto_config_id = @cfg AND cc.empresa_id = @empresa_id AND cc.es_imputable = 1);
SELECT IF(@cfg IS NOT NULL AND @cuenta_ok = 0,
          'ERROR: la config no tiene una cuenta contable imputable de la empresa. No se activó: cargar la cuenta en la solapa Agente de Percepción.',
          'Cuenta contable: OK') AS control_cuenta;

SET @aplicar := IF(@cfg IS NOT NULL AND @cuenta_ok > 0, 1, 0);

START TRANSACTION;

-- 1 a 3. Activar, vigencia, N° de agente y tributo ARCA
UPDATE gestion__empresas_impuestos_config
SET tabla_estado_registro_id = 1,
    f_desde = @f_desde,
    f_hasta = NULL,
    numero_agente = IF(numero_agente IS NULL OR TRIM(numero_agente) = '', @nro_agente, numero_agente),
    arca_tributo_id = IF(arca_tributo_id IS NULL OR arca_tributo_id = 0, @tributo_arca, arca_tributo_id)
WHERE empresa_impuesto_config_id = @cfg AND @aplicar = 1;

-- 4. Alícuotas por situación (fila de CM faltante: copia la del inscripto local)
INSERT INTO gestion__empresas_impuestos_config_situaciones
       (empresa_impuesto_config_id, situacion, requiere_territorio, alicuota, tipo_registro_ddjj, declarar_si_cero,
        leyenda, tabla_estado_registro_id)
SELECT s.empresa_impuesto_config_id, 'CONVENIO_JURISDICCION', s.requiere_territorio, 0.5000, s.tipo_registro_ddjj,
       s.declarar_si_cero, s.leyenda, 1
FROM gestion__empresas_impuestos_config_situaciones s
WHERE @aplicar = 1 AND s.empresa_impuesto_config_id = @cfg
  AND s.situacion = 'INSCRIPTO_JURISDICCION' AND s.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config_situaciones x
                  WHERE x.empresa_impuesto_config_id = @cfg AND x.situacion = 'CONVENIO_JURISDICCION'
                    AND x.tabla_estado_registro_id = 1);

INSERT INTO gestion__empresas_impuestos_config_situaciones
       (empresa_impuesto_config_id, situacion, requiere_territorio, alicuota, tipo_registro_ddjj, declarar_si_cero,
        leyenda, tabla_estado_registro_id)
SELECT @cfg, 'INSCRIPTO_JURISDICCION', 1, 1.0000, NULL, 0, NULL, 1 FROM DUAL
WHERE @aplicar = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config_situaciones x
                  WHERE x.empresa_impuesto_config_id = @cfg AND x.situacion = 'INSCRIPTO_JURISDICCION'
                    AND x.tabla_estado_registro_id = 1);

UPDATE gestion__empresas_impuestos_config_situaciones
SET alicuota = CASE situacion WHEN 'INSCRIPTO_JURISDICCION' THEN 1.0000 ELSE 0.5000 END
WHERE @aplicar = 1 AND empresa_impuesto_config_id = @cfg AND tabla_estado_registro_id = 1
  AND situacion IN ('INSCRIPTO_JURISDICCION', 'CONVENIO_JURISDICCION');

-- 5. Subgrupo Facturación de la empresa (por código, no por id)
SET @subgrupo := (SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos
                  WHERE empresa_id = @empresa_id AND codigo = 'FACT' AND tabla_estado_registro_id = 1
                  ORDER BY comprobante_subgrupo_id LIMIT 1);

UPDATE gestion__empresas_impuestos_config_subgrupos
SET tabla_estado_registro_id = 1
WHERE @aplicar = 1 AND empresa_impuesto_config_id = @cfg AND comprobante_subgrupo_id = @subgrupo;

INSERT INTO gestion__empresas_impuestos_config_subgrupos (empresa_impuesto_config_id, comprobante_subgrupo_id, tabla_estado_registro_id)
SELECT @cfg, @subgrupo, 1 FROM DUAL
WHERE @aplicar = 1 AND @subgrupo IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config_subgrupos x
                  WHERE x.empresa_impuesto_config_id = @cfg AND x.comprobante_subgrupo_id = @subgrupo);

COMMIT;

-- Control final
SELECT cfg.empresa_impuesto_config_id, cfg.tabla_estado_registro_id AS estado, cfg.f_desde, cfg.f_hasta,
       cfg.numero_agente, cfg.arca_tributo_id, cfg.codigo_regimen_ddjj, cfg.formato_ddjj,
       CONCAT(cc.codigo, ' ', cc.nombre) AS cuenta
FROM gestion__empresas_impuestos_config cfg
LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = cfg.cont_cuenta_id
WHERE cfg.empresa_impuesto_config_id = @cfg;

SELECT situacion, alicuota, requiere_territorio, declarar_si_cero
FROM gestion__empresas_impuestos_config_situaciones
WHERE empresa_impuesto_config_id = @cfg AND tabla_estado_registro_id = 1
ORDER BY situacion;

SELECT sub.comprobante_subgrupo_id, cs.comprobante_subgrupo, cs.codigo, sub.tabla_estado_registro_id AS estado
FROM gestion__empresas_impuestos_config_subgrupos sub
JOIN gestion__comprobantes_subgrupos cs ON cs.comprobante_subgrupo_id = sub.comprobante_subgrupo_id
WHERE sub.empresa_impuesto_config_id = @cfg;
