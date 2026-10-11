-- =====================================================================================================
-- Percepciones recibidas en compras (IVA, Ganancias, IIBB): habilitarlas en las facturas de proveedores
-- -----------------------------------------------------------------------------------------------------
-- La pantalla de Facturas de Proveedores (gestion pág. 57 y gestion_normal pág. 557) ofrece como
-- "Otros impuestos" las configuraciones de gestion__empresas_impuestos_config vinculadas al subgrupo
-- Facturas de Compra (código FC) de la empresa en gestion__empresas_impuestos_config_subgrupos.
-- Hasta acá solo la percepción de IIBB de la empresa 2 estaba vinculada; las de IVA y Ganancias, y todas
-- las de la empresa 6, no aparecían.
--
-- Vincula (o reactiva) al subgrupo FC de su empresa toda configuración activa de un impuesto de compras
-- que sea percepción (gestion__impuestos_tipos.aplica_compra = 1 AND es_percepcion = 1: hoy 1 IIBB,
-- 2 IVA, 3 Ganancias) y que tenga cuenta contable del plan de la propia empresa (el asiento de la factura
-- la necesita). Sin ids fijos: subgrupo por empresa + código, cuenta validada contra la empresa.
-- Idempotente (UNIQUE uk_config_subgrupo); corre en MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

-- 1) Reactivar vínculos existentes dados de baja
UPDATE gestion__empresas_impuestos_config_subgrupos ics
INNER JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ics.empresa_impuesto_config_id
INNER JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
INNER JOIN gestion__comprobantes_subgrupos sg ON sg.comprobante_subgrupo_id = ics.comprobante_subgrupo_id
                                             AND sg.empresa_id = cfg.empresa_id AND sg.codigo = 'FC'
INNER JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = cfg.cont_cuenta_id AND cc.empresa_id = cfg.empresa_id
SET ics.tabla_estado_registro_id = 1
WHERE cfg.tabla_estado_registro_id = 1
  AND it.aplica_compra = 1 AND it.es_percepcion = 1
  AND ics.tabla_estado_registro_id <> 1;

-- 2) Vincular las que no tienen vínculo con el subgrupo FC de su empresa
INSERT INTO gestion__empresas_impuestos_config_subgrupos
    (empresa_impuesto_config_id, comprobante_subgrupo_id, tabla_estado_registro_id)
SELECT cfg.empresa_impuesto_config_id, sg.comprobante_subgrupo_id, 1
FROM gestion__empresas_impuestos_config cfg
INNER JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
INNER JOIN gestion__comprobantes_subgrupos sg ON sg.empresa_id = cfg.empresa_id AND sg.codigo = 'FC'
                                             AND sg.tabla_estado_registro_id = 1
INNER JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = cfg.cont_cuenta_id AND cc.empresa_id = cfg.empresa_id
WHERE cfg.tabla_estado_registro_id = 1
  AND it.aplica_compra = 1 AND it.es_percepcion = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__empresas_impuestos_config_subgrupos x
                  WHERE x.empresa_impuesto_config_id = cfg.empresa_impuesto_config_id
                    AND x.comprobante_subgrupo_id = sg.comprobante_subgrupo_id);

-- 3) Control: percepciones de compra habilitadas por empresa (las que falten no tienen cuenta contable
--    de la empresa: cargarla en Configuración de Impuestos y volver a correr el script)
SELECT cfg.empresa_id, cfg.empresa_impuesto_config_id, it.impuesto_tipo, j.jurisdiccion_nombre,
       CONCAT(cc.codigo, ' - ', cc.nombre) AS cuenta,
       CASE WHEN ics.tabla_estado_registro_id = 1 THEN 'habilitada en FC' ELSE 'SIN HABILITAR' END AS estado
FROM gestion__empresas_impuestos_config cfg
INNER JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = cfg.cont_cuenta_id AND cc.empresa_id = cfg.empresa_id
LEFT JOIN gestion__comprobantes_subgrupos sg ON sg.empresa_id = cfg.empresa_id AND sg.codigo = 'FC'
LEFT JOIN gestion__empresas_impuestos_config_subgrupos ics ON ics.empresa_impuesto_config_id = cfg.empresa_impuesto_config_id
                                                          AND ics.comprobante_subgrupo_id = sg.comprobante_subgrupo_id
WHERE cfg.tabla_estado_registro_id = 1 AND it.aplica_compra = 1 AND it.es_percepcion = 1
ORDER BY cfg.empresa_id, it.impuesto_tipo_id;
