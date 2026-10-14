-- =====================================================================================================
-- Retenciones sufridas en cobranzas - script 01: CUENTA DE RETENCIÓN DE IIBB POR PROVINCIA
-- -----------------------------------------------------------------------------------------------------
-- Las cobranzas (gestion y gestion_normal) registran las retenciones de IVA, Ganancias e Ingresos Brutos
-- que nos practican los clientes como medios de cobro de tipo RETENCION (RET_IVA / RET_GAN / RET_IIBB).
-- Desde este cambio la retención de IIBB lleva jurisdicción (provincia) y su cuenta contable sale de la
-- configuración de impuestos de la empresa (gestion__empresas_impuestos_config, impuesto de la retención de
-- IIBB × jurisdicción), igual que las percepciones recibidas (20261011_percepciones_recibidas_02_provincias).
-- Sin esa fila el recibo no se confirma (cobCuentaRetencion en cobranzas_model.php). IVA y Ganancias siguen
-- con la cuenta del medio de cobro.
--
-- Para cada empresa con medio RET_IIBB con cuenta:
--   - La jurisdicción que ya tenía configurada la retención de IIBB (Casa Lucho: ARBA) toma la cuenta del medio
--     (1.1.03.08.01 Retenciones de IIBB - BsAs).
--   - Cada otra provincia activa sin cuenta configurada: cuenta imputable "Retenciones de IIBB - <Provincia>"
--     hija del mismo padre que la del medio (1.1.03.08), código siguiente libre 1.1.03.08.NN (se reutiliza si ya
--     existe con ese nombre), y fila de configuración manual (alícuota 0: el importe sale del certificado).
--   - prioridad 2 y sin subgrupos: no aparecen en facturas ni en el fallback de "otros impuestos" de compras.
--   - f_desde 2000-01-01: una cobranza con fecha anterior al script también encuentra la cuenta.
-- El medio RET_IIBB deja de llamarse "... BsAs ...": la provincia se elige en cada retención.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

UPDATE gestion__medios_pago
SET medio_pago = 'Retención IIBB sufrida'
WHERE codigo = 'RET_IIBB' AND medio_pago_tipo = 'RETENCION' AND medio_pago LIKE '%BsAs%';

DROP PROCEDURE IF EXISTS `_rs01_empresa`;
DROP PROCEDURE IF EXISTS `_rs01_todas`;
DELIMITER $$
CREATE PROCEDURE `_rs01_empresa`(IN p_empresa INT)
BEGIN
    DECLARE v_fin INT DEFAULT 0;
    DECLARE v_imp INT;
    DECLARE v_cta_medio INT;
    DECLARE v_padre INT;
    DECLARE v_nivel INT;
    DECLARE v_padre_codigo VARCHAR(20);
    DECLARE v_jur_medio INT;
    DECLARE v_jur INT;
    DECLARE v_cm VARCHAR(20);
    DECLARE v_prov VARCHAR(100);
    DECLARE v_cfg INT;
    DECLARE v_cta_cfg INT;
    DECLARE v_cuenta INT;
    DECLARE v_n INT;
    DECLARE v_codigo VARCHAR(20);
    DECLARE v_nombre VARCHAR(255);
    DECLARE cur CURSOR FOR
        SELECT j.jurisdiccion_id, j.codigo_externo, s.jurisdiccion_sede
        FROM gestion__jurisdicciones j
        INNER JOIN gestion__iibb_jurisdicciones_sedes s ON s.provincia_id = j.provincia_id
        WHERE j.jurisdiccion_tipo_id = 2 AND j.tabla_estado_registro_id = 1
        ORDER BY s.codigo_comarb;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin = 1;

    -- Impuesto y cuenta del medio RET_IIBB de la empresa (la cuenta es hermana de las que se crean)
    SET v_imp = (SELECT mp.impuesto_tipo_id FROM gestion__medios_pago mp
                 INNER JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = mp.impuesto_tipo_id
                                                        AND it.es_retencion = 1 AND it.aplica_venta = 1
                 WHERE mp.empresa_id = p_empresa AND mp.codigo = 'RET_IIBB' AND mp.medio_pago_tipo = 'RETENCION'
                 LIMIT 1);
    SET v_cta_medio = (SELECT cont_cuenta_id FROM gestion__medios_pago
                       WHERE empresa_id = p_empresa AND codigo = 'RET_IIBB' AND medio_pago_tipo = 'RETENCION' LIMIT 1);
    SET v_padre = (SELECT cuenta_padre_id FROM gestion__cont_cuentas WHERE cont_cuenta_id = v_cta_medio AND empresa_id = p_empresa);
    SET v_nivel = (SELECT nivel FROM gestion__cont_cuentas WHERE cont_cuenta_id = v_cta_medio AND empresa_id = p_empresa);
    SET v_padre_codigo = (SELECT codigo FROM gestion__cont_cuentas WHERE cont_cuenta_id = v_padre AND empresa_id = p_empresa);

    IF v_imp IS NOT NULL AND v_padre IS NOT NULL AND v_padre_codigo IS NOT NULL THEN
        -- Jurisdicción que ya tenía la retención de IIBB configurada: se queda con la cuenta del medio
        SET v_jur_medio = (SELECT jurisdiccion_id FROM gestion__empresas_impuestos_config
                           WHERE empresa_id = p_empresa AND impuesto_tipo_id = v_imp AND jurisdiccion_id IS NOT NULL
                           ORDER BY prioridad, empresa_impuesto_config_id LIMIT 1);

        SET v_fin = 0;
        OPEN cur;
        leer: LOOP
            FETCH cur INTO v_jur, v_cm, v_prov;
            IF v_fin = 1 THEN
                LEAVE leer;
            END IF;

            -- SET (...) y no SELECT INTO: un SELECT INTO sin filas dispara el handler NOT FOUND y corta el cursor
            SET v_cfg = (SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config
                         WHERE empresa_id = p_empresa AND impuesto_tipo_id = v_imp AND jurisdiccion_id = v_jur
                         ORDER BY prioridad, empresa_impuesto_config_id LIMIT 1);
            SET v_cta_cfg = (SELECT cont_cuenta_id FROM gestion__empresas_impuestos_config WHERE empresa_impuesto_config_id = v_cfg);

            IF IFNULL(v_cta_cfg, 0) = 0 THEN
                IF v_jur = v_jur_medio THEN
                    SET v_cuenta = v_cta_medio;
                ELSE
                    SET v_nombre = CONCAT('Retenciones de IIBB - ', v_prov);
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
                END IF;

                IF v_cfg IS NULL THEN
                    INSERT INTO gestion__empresas_impuestos_config
                        (empresa_id, impuesto_tipo_id, jurisdiccion_id, condicion_fiscal_id, cont_cuenta_id, base_calculo, alicuota,
                         minimo_imponible, monto_fijo, aplica_siempre, prioridad, f_desde, f_hasta, tipo_calculo, tabla_estado_registro_id,
                         codigo_jurisdiccion_cm)
                    VALUES (p_empresa, v_imp, v_jur, NULL, v_cuenta, 'NETO_GRAVADO', 0, 0, 0, 0, 2, '2000-01-01', NULL, 'manual', 1, v_cm);
                ELSE
                    UPDATE gestion__empresas_impuestos_config
                    SET cont_cuenta_id = v_cuenta, prioridad = 2, f_desde = LEAST(f_desde, '2000-01-01'),
                        codigo_jurisdiccion_cm = IFNULL(codigo_jurisdiccion_cm, v_cm)
                    WHERE empresa_impuesto_config_id = v_cfg;
                END IF;
            END IF;
        END LOOP;
        CLOSE cur;
    END IF;
END$$

CREATE PROCEDURE `_rs01_todas`()
BEGIN
    DECLARE v_fin INT DEFAULT 0;
    DECLARE v_emp INT;
    DECLARE cur_emp CURSOR FOR
        SELECT DISTINCT empresa_id FROM gestion__medios_pago
        WHERE codigo = 'RET_IIBB' AND medio_pago_tipo = 'RETENCION' AND cont_cuenta_id > 0
        ORDER BY empresa_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin = 1;

    OPEN cur_emp;
    emps: LOOP
        FETCH cur_emp INTO v_emp;
        IF v_fin = 1 THEN
            LEAVE emps;
        END IF;
        CALL `_rs01_empresa`(v_emp);
        SET v_fin = 0;
    END LOOP;
    CLOSE cur_emp;
END$$
DELIMITER ;

CALL `_rs01_todas`();
DROP PROCEDURE IF EXISTS `_rs01_empresa`;
DROP PROCEDURE IF EXISTS `_rs01_todas`;

-- Control (solo lectura): cuenta de la retención de IIBB sufrida por empresa y provincia
SELECT c.empresa_id, j.jurisdiccion_codigo, j.jurisdiccion_nombre, CONCAT(cc.codigo, ' - ', cc.nombre) AS cuenta, c.prioridad, c.f_desde
FROM gestion__empresas_impuestos_config c
INNER JOIN gestion__medios_pago mp ON mp.empresa_id = c.empresa_id AND mp.codigo = 'RET_IIBB' AND mp.impuesto_tipo_id = c.impuesto_tipo_id
INNER JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = c.jurisdiccion_id
LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = c.cont_cuenta_id
WHERE c.tabla_estado_registro_id = 1
ORDER BY c.empresa_id, j.codigo_externo;
