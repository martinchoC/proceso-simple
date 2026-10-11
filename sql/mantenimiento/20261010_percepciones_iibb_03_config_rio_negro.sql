-- =====================================================================
-- Percepciones de IIBB practicadas - script 03: CONFIGURACIÓN RÍO NEGRO (Casa Lucho, empresa 2)
-- 2026-10-10 — docs/gestion/09_percepciones_iibb.md §2.1 y §4
--
-- Régimen vigente: Res. ART 985/2018, Anexo VII (sin padrón, DDJJ en SIRCAR Diseño N° 1, jurisdicción 916).
--   INSCRIPTO_JURISDICCION     1,00 %  (directo o CM con alta en Río Negro)
--   SIMPLIFICADO_JURISDICCION  0,05 %
--   NO_INSCRIPTO               0 %, se informa en la DDJJ ("régimen especial a tasa 0 %")
--   INSCRIPTO_OTRA_JURISDICCION / EXENTO / BIEN_DE_USO: excluidos (sin fila = no percibe ni declara)
--   Territorio: lugar de entrega = localidad de la sucursal del cliente (ENTREGA). Base: neto sin IVA (NETO).
--
-- ⚠️ La config se crea INACTIVA (2): faltan datos que no son públicos o hay que confirmar
--    (Nº de agente, código de régimen SIRCAR, código de tributo de ARCA, fecha de alta, alícuotas
--    vigentes del Anexo VII). Completarlos en Impuestos Adic. - Conf (página 73) y "Habilitar".
--
-- Crea la cuenta 2.1.04.06 "Percepciones IIBB Río Negro a Depositar" bajo 2.1.04 si no existe.
-- Cuando Río Negro entre en SIRCIP (Res. ART 407/2025): cerrar esta config (f_hasta) y dar de alta
-- otra con formato_padron = formato_ddjj = 'SIRCIP' y las situaciones de §2.2.
--
-- Idempotente. Ambos motores. Solo empresa 2.
-- =====================================================================

DROP PROCEDURE IF EXISTS `_pi03_config`;
DELIMITER $$
CREATE PROCEDURE `_pi03_config`()
BEGIN
    DECLARE v_empresa INT DEFAULT 2;
    DECLARE v_jur INT;
    DECLARE v_padre INT;
    DECLARE v_cuenta INT;
    DECLARE v_subgrupo INT;
    DECLARE v_cfg INT;

    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;
    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'gestion__empresas_impuestos_config' AND COLUMN_NAME = 'formato_ddjj') = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Correr antes 20261010_percepciones_iibb_01_estructura.sql';
    END IF;

    SELECT jurisdiccion_id INTO v_jur FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = 'DGR_RN' LIMIT 1;
    IF v_jur IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No existe la jurisdicción DGR_RN (Río Negro)';
    END IF;

    SELECT comprobante_subgrupo_id INTO v_subgrupo FROM gestion__comprobantes_subgrupos
    WHERE empresa_id = v_empresa AND codigo = 'FACT' LIMIT 1;
    IF v_subgrupo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa 2 no tiene el subgrupo FACT (Facturación)';
    END IF;

    -- Cuenta del pasivo
    SELECT cont_cuenta_id INTO v_cuenta FROM gestion__cont_cuentas WHERE empresa_id = v_empresa AND codigo = '2.1.04.06' LIMIT 1;
    IF v_cuenta IS NULL THEN
        SELECT cont_cuenta_id INTO v_padre FROM gestion__cont_cuentas WHERE empresa_id = v_empresa AND codigo = '2.1.04' LIMIT 1;
        IF v_padre IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa 2 no tiene la cuenta 2.1.04 Impuestos a Pagar';
        END IF;
        INSERT INTO gestion__cont_cuentas
            (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
        VALUES (v_empresa, 0, '2.1.04.06', 'Percepciones IIBB Río Negro a Depositar', 'H', v_padre, 4, 6, 1, 0, 1);
        SET v_cuenta = LAST_INSERT_ID();
    END IF;

    -- Config del agente (una por empresa + impuesto 6 + jurisdicción + formato de DDJJ)
    SELECT empresa_impuesto_config_id INTO v_cfg FROM gestion__empresas_impuestos_config
    WHERE empresa_id = v_empresa AND impuesto_tipo_id = 6 AND jurisdiccion_id = v_jur AND formato_ddjj = 'SIRCAR_D1'
    ORDER BY empresa_impuesto_config_id LIMIT 1;
    IF v_cfg IS NULL THEN
        INSERT INTO gestion__empresas_impuestos_config
            (empresa_id, impuesto_tipo_id, jurisdiccion_id, condicion_fiscal_id, cont_cuenta_id, base_calculo, alicuota,
             minimo_imponible, monto_fijo, aplica_siempre, prioridad, f_desde, f_hasta, tipo_calculo, tabla_estado_registro_id,
             numero_agente, criterio_territorial, importe_minimo_percepcion, arca_tributo_id, formato_padron, formato_ddjj,
             codigo_jurisdiccion_cm, codigo_regimen_ddjj)
        VALUES (v_empresa, 6, v_jur, NULL, v_cuenta, 'NETO', 0, 0, 0, 1, 1, CURDATE(), NULL, 'regla', 2,
                NULL, 'ENTREGA', 0, NULL, NULL, 'SIRCAR_D1', '916', NULL);
        SET v_cfg = LAST_INSERT_ID();
    END IF;

    INSERT INTO gestion__empresas_impuestos_config_subgrupos (empresa_impuesto_config_id, comprobante_subgrupo_id, tabla_estado_registro_id)
    SELECT v_cfg, v_subgrupo, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config_subgrupos
                      WHERE empresa_impuesto_config_id = v_cfg AND comprobante_subgrupo_id = v_subgrupo);

    INSERT INTO gestion__empresas_impuestos_config_situaciones
        (empresa_impuesto_config_id, situacion, requiere_territorio, alicuota, tipo_registro_ddjj, declarar_si_cero, leyenda, tabla_estado_registro_id)
    SELECT v_cfg, s.situacion, 1, s.alicuota, NULL, s.declarar, NULL, 1
    FROM (          SELECT 'INSCRIPTO_JURISDICCION' AS situacion, 1.0000 AS alicuota, 0 AS declarar
          UNION ALL SELECT 'SIMPLIFICADO_JURISDICCION', 0.0500, 0
          UNION ALL SELECT 'NO_INSCRIPTO', 0.0000, 1) s
    WHERE NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config_situaciones x
                      WHERE x.empresa_impuesto_config_id = v_cfg AND x.situacion = s.situacion);
END$$
DELIMITER ;

CALL `_pi03_config`();
DROP PROCEDURE IF EXISTS `_pi03_config`;

-- Resumen (solo lectura)
SELECT cfg.empresa_impuesto_config_id, cfg.empresa_id, j.jurisdiccion_codigo, cfg.tabla_estado_registro_id AS estado,
       cfg.f_desde, cfg.base_calculo, cfg.criterio_territorial, cfg.formato_ddjj, cfg.codigo_jurisdiccion_cm,
       cfg.numero_agente, cfg.codigo_regimen_ddjj, cfg.arca_tributo_id, cc.codigo AS cuenta
FROM gestion__empresas_impuestos_config cfg
JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = cfg.cont_cuenta_id
WHERE cfg.impuesto_tipo_id = 6;
SELECT s.empresa_impuesto_config_id, s.situacion, s.alicuota, s.requiere_territorio, s.declarar_si_cero
FROM gestion__empresas_impuestos_config_situaciones s
JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = s.empresa_impuesto_config_id
WHERE cfg.impuesto_tipo_id = 6;
