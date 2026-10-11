-- =====================================================================================================
-- Percepciones recibidas en compras - script 02: IIBB DE TODAS LAS PROVINCIAS (Casa Lucho, empresa 2)
-- -----------------------------------------------------------------------------------------------------
-- Requiere 20261011_percepciones_recibidas_01_subgrupo_fc.sql (mismo criterio de habilitación).
--
-- 1) Jurisdicciones: gestion__jurisdicciones tenía 10 provincias. Agrega las que faltan de las 24
--    (las toma de gestion__iibb_jurisdicciones_sedes, con su provincia y su código de Convenio Multilateral)
--    y completa codigo_externo con el código CM (901..924) donde esté vacío. La tabla es global (sin empresa).
-- 2) Para la empresa (procedimiento con parámetro; se llama para la 2): por cada jurisdicción provincial
--    activa sin config de "Percepción IIBB Recibidas" (impuesto_tipo 1, aplica_compra = 1):
--      - cuenta imputable "Percep. IIBB <Provincia>" hija de la cuenta padre de la percepción de IIBB que
--        la empresa ya tiene configurada (Casa Lucho: 1.1.03.05 Percepciones de IIBB, hija 1.1.03.05.01 BsAs).
--        Código = siguiente libre 1.1.03.05.NN en orden de código CM; si la cuenta ya existe (mismo padre y
--        nombre) se reutiliza.
--      - config manual (alícuota 0: el importe sale de la factura del proveedor), prioridad 2 para que la
--        fila sugerida al abrir una factura siga siendo la de la provincia ya configurada (prioridad 1),
--        y vínculo con el subgrupo FC (Facturas de Compra). Las demás se agregan con "Agregar impuesto".
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

-- 1) Jurisdicciones provinciales faltantes ---------------------------------------------------------------
INSERT INTO gestion__jurisdicciones
    (jurisdiccion_codigo, jurisdiccion_nombre, pais_id, provincia_id, localidad_id, jurisdiccion_tipo_id,
     organismo_recaudador, requiere_padron, codigo_externo, orden, tabla_estado_registro_id)
SELECT n.codigo, n.nombre, 1, s.provincia_id, NULL, 2, s.organismo, 1, s.codigo_comarb, n.orden, 1
FROM (          SELECT '903' AS cm, 'DGR_CAT'  AS codigo, 'Catamarca (DGR)'               AS nombre, 10 AS orden
      UNION ALL SELECT '905', 'DGR_CTES', 'Corrientes (DGR)',             11
      UNION ALL SELECT '906', 'ATP_CHACO','Chaco (ATP)',                  12
      UNION ALL SELECT '908', 'ATER',     'Entre Ríos (ATER)',            13
      UNION ALL SELECT '909', 'DGR_FSA',  'Formosa (DGR)',                14
      UNION ALL SELECT '910', 'DPR_JUJUY','Jujuy (DPR)',                  15
      UNION ALL SELECT '912', 'DGIP_LR',  'La Rioja (DGIP)',              16
      UNION ALL SELECT '914', 'DGR_MIS',  'Misiones (DGR)',               17
      UNION ALL SELECT '917', 'DGR_SALTA','Salta (DGR)',                  18
      UNION ALL SELECT '918', 'DGR_SJ',   'San Juan (DGR)',               19
      UNION ALL SELECT '919', 'DPI_SL',   'San Luis (DPI)',               20
      UNION ALL SELECT '920', 'ASIP_SC',  'Santa Cruz (ASIP)',            21
      UNION ALL SELECT '922', 'DGR_SDE',  'Santiago del Estero (DGR)',    22
      UNION ALL SELECT '923', 'DGR_TUC',  'Tucumán (DGR)',                23
      UNION ALL SELECT '924', 'AREF_TDF', 'Tierra del Fuego (AREF)',      24) n
INNER JOIN gestion__iibb_jurisdicciones_sedes s ON s.codigo_comarb = n.cm
WHERE NOT EXISTS (SELECT 1 FROM gestion__jurisdicciones j WHERE j.jurisdiccion_codigo = n.codigo)
  AND NOT EXISTS (SELECT 1 FROM gestion__jurisdicciones j WHERE j.jurisdiccion_tipo_id = 2 AND j.provincia_id = s.provincia_id);

-- Código CM en las jurisdicciones provinciales que no lo tenían
UPDATE gestion__jurisdicciones j
INNER JOIN gestion__iibb_jurisdicciones_sedes s ON s.provincia_id = j.provincia_id
SET j.codigo_externo = s.codigo_comarb
WHERE j.jurisdiccion_tipo_id = 2 AND (j.codigo_externo IS NULL OR j.codigo_externo = '');

-- 2) Config + cuenta por provincia para la empresa ------------------------------------------------------
DROP PROCEDURE IF EXISTS `_pr02_provincias`;
DELIMITER $$
CREATE PROCEDURE `_pr02_provincias`(IN p_empresa INT)
BEGIN
    DECLARE v_fin INT DEFAULT 0;
    DECLARE v_jur INT;
    DECLARE v_cm VARCHAR(20);
    DECLARE v_prov VARCHAR(100);
    DECLARE v_padre INT;
    DECLARE v_padre_codigo VARCHAR(20);
    DECLARE v_nivel INT;
    DECLARE v_subgrupo INT;
    DECLARE v_cuenta INT;
    DECLARE v_cfg INT;
    DECLARE v_n INT;
    DECLARE v_codigo VARCHAR(20);
    DECLARE v_nombre VARCHAR(255);
    DECLARE cur CURSOR FOR
        SELECT j.jurisdiccion_id, j.codigo_externo, s.jurisdiccion_sede
        FROM gestion__jurisdicciones j
        INNER JOIN gestion__iibb_jurisdicciones_sedes s ON s.provincia_id = j.provincia_id
        WHERE j.jurisdiccion_tipo_id = 2 AND j.tabla_estado_registro_id = 1
          AND NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config c
                          WHERE c.empresa_id = p_empresa AND c.impuesto_tipo_id = 1 AND c.jurisdiccion_id = j.jurisdiccion_id)
        ORDER BY s.codigo_comarb;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin = 1;

    IF (SELECT COUNT(*) FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = 1 AND aplica_compra = 1 AND es_percepcion = 1) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El impuesto 1 no es Percepción IIBB Recibidas (aplica_compra / es_percepcion)';
    END IF;

    SELECT comprobante_subgrupo_id INTO v_subgrupo FROM gestion__comprobantes_subgrupos
    WHERE empresa_id = p_empresa AND codigo = 'FC' AND tabla_estado_registro_id = 1 LIMIT 1;
    IF v_subgrupo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa no tiene el subgrupo FC (Facturas de Compra)';
    END IF;

    -- Cuenta padre: la de la percepción de IIBB recibida que la empresa ya tiene configurada
    SELECT cc.cuenta_padre_id, cc.nivel INTO v_padre, v_nivel
    FROM gestion__empresas_impuestos_config c
    INNER JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = c.cont_cuenta_id AND cc.empresa_id = c.empresa_id
    WHERE c.empresa_id = p_empresa AND c.impuesto_tipo_id = 1 AND cc.cuenta_padre_id IS NOT NULL
    ORDER BY c.prioridad, c.empresa_impuesto_config_id LIMIT 1;
    IF v_padre IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa no tiene una percepción de IIBB recibida con cuenta: no se sabe dónde crear las cuentas';
    END IF;
    SELECT codigo INTO v_padre_codigo FROM gestion__cont_cuentas WHERE cont_cuenta_id = v_padre AND empresa_id = p_empresa;

    SET v_fin = 0;
    OPEN cur;
    leer: LOOP
        FETCH cur INTO v_jur, v_cm, v_prov;
        IF v_fin = 1 THEN
            LEAVE leer;
        END IF;
        SET v_nombre = CONCAT('Percep. IIBB ', v_prov);
        -- SET (...) y no SELECT INTO: un SELECT INTO sin filas dispara el handler NOT FOUND y corta el cursor
        SET v_cuenta = (SELECT cont_cuenta_id FROM gestion__cont_cuentas
                        WHERE empresa_id = p_empresa AND cuenta_padre_id = v_padre AND nombre = v_nombre LIMIT 1);
        IF v_cuenta IS NULL THEN
            SET v_n = 1;
            SET v_codigo = CONCAT(v_padre_codigo, '.', LPAD(v_n, 2, '0'));
            WHILE EXISTS (SELECT 1 FROM gestion__cont_cuentas WHERE empresa_id = p_empresa AND codigo = v_codigo) DO
                SET v_n = v_n + 1;
                SET v_codigo = CONCAT(v_padre_codigo, '.', LPAD(v_n, 2, '0'));
            END WHILE;
            INSERT INTO gestion__cont_cuentas
                (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
            VALUES (p_empresa, 0, v_codigo, v_nombre, 'D', v_padre, v_nivel, v_n, 1, 0, 1);
            SET v_cuenta = LAST_INSERT_ID();
        END IF;

        INSERT INTO gestion__empresas_impuestos_config
            (empresa_id, impuesto_tipo_id, jurisdiccion_id, condicion_fiscal_id, cont_cuenta_id, base_calculo, alicuota,
             minimo_imponible, monto_fijo, aplica_siempre, prioridad, f_desde, f_hasta, tipo_calculo, tabla_estado_registro_id,
             codigo_jurisdiccion_cm)
        VALUES (p_empresa, 1, v_jur, NULL, v_cuenta, 'NETO_GRAVADO', 0, 0, 0, 0, 2, CURDATE(), NULL, 'manual', 1, v_cm);
        SET v_cfg = LAST_INSERT_ID();

        INSERT INTO gestion__empresas_impuestos_config_subgrupos (empresa_impuesto_config_id, comprobante_subgrupo_id, tabla_estado_registro_id)
        VALUES (v_cfg, v_subgrupo, 1);
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL `_pr02_provincias`(2);
DROP PROCEDURE IF EXISTS `_pr02_provincias`;

-- Control (solo lectura): percepciones de IIBB recibidas de la empresa 2 habilitadas en Facturas de Compra
SELECT c.empresa_impuesto_config_id, j.jurisdiccion_codigo, j.jurisdiccion_nombre, j.codigo_externo AS cm,
       CONCAT(cc.codigo, ' - ', cc.nombre) AS cuenta, c.prioridad,
       IF(ics.tabla_estado_registro_id = 1, 'habilitada en FC', 'SIN HABILITAR') AS estado
FROM gestion__empresas_impuestos_config c
INNER JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = c.jurisdiccion_id
LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = c.cont_cuenta_id
LEFT JOIN gestion__comprobantes_subgrupos sg ON sg.empresa_id = c.empresa_id AND sg.codigo = 'FC'
LEFT JOIN gestion__empresas_impuestos_config_subgrupos ics ON ics.empresa_impuesto_config_id = c.empresa_impuesto_config_id
                                                          AND ics.comprobante_subgrupo_id = sg.comprobante_subgrupo_id
WHERE c.empresa_id = 2 AND c.impuesto_tipo_id = 1 AND c.tabla_estado_registro_id = 1
ORDER BY j.codigo_externo;
