-- Datos de prueba (solo base local): clientes del estudio contable (tenant = empresa 2) para simular la
-- importación de "Mis Comprobantes" de ARCA. No va a producción.
--   * 5 clientes externos (sin ERP): sus archivos están en esta carpeta (generar.py en _local/tools/estudio_arca_fixtures).
--   * 3 clientes que usan gestion_multipyme en otra instancia: sin empresa_vinculada_id (no están en esta base).
-- Reanudable: cada alta se saltea si el CUIT ya está en el padrón del estudio.
-- Tipos de cliente (estudio__clientes_tipos, empresa 2): 1 Empresa / Sociedad, 2 Profesional / Autónomo, 3 Comercio / Pyme.
-- Condición IVA (gestion__condiciones_fiscales): 1 Responsable Inscripto, 3 Monotributista.

SET NAMES utf8mb4;
SET @estudio := 2;

INSERT INTO estudio__clientes (empresa_id, cliente_tipo_id, razon_social, cuit, terminacion_cuit, condicion_iva_id,
       empresa_vinculada_id, mes_cierre_ejercicio, ingresos_brutos_tipo, jurisdiccion_sede_id, es_empleador,
       cantidad_empleados, observaciones, tabla_estado_registro_id)
SELECT @estudio, v.tipo, v.razon, v.cuit, CAST(RIGHT(v.cuit, 1) AS UNSIGNED), v.iva, NULL, v.cierre, v.iibb,
       (SELECT j.jurisdiccion_id FROM gestion__jurisdicciones j WHERE j.jurisdiccion_codigo = v.sede),
       v.empleador, v.empleados, v.obs, 1
FROM (
    SELECT 3 AS tipo, 'Ferretería del Valle SRL' AS razon, '30718442148' AS cuit, 1 AS iva, 6 AS cierre,
           'LOCAL' AS iibb, 'DGR_RN' AS sede, 1 AS empleador, 6 AS empleados,
           'Externo sin ERP: compras y ventas desde Mis Comprobantes de ARCA (_local/tests/fixtures/estudio_arca/01_ferreteria_del_valle). Viedma.' AS obs
    UNION ALL SELECT 1, 'Transportes Patagónicos SA', '30709923818', 1, 12, 'CONVENIO_MULTILATERAL', 'DGR_RN', 1, 14,
           'Externo sin ERP: Mis Comprobantes de ARCA (02_transportes_patagonicos). CM: Río Negro sede 62 %, Buenos Aires 38 %. Exporta servicios (Factura E en USD).'
    UNION ALL SELECT 1, 'Agro Insumos Carmen SAS', '30716330571', 1, 3, 'LOCAL', 'ARBA', 1, 3,
           'Externo sin ERP: Mis Comprobantes de ARCA (03_agro_insumos_carmen). Carmen de Patagones. Ventas al 10,5 %, agente de percepción ARBA.'
    UNION ALL SELECT 2, 'Fernández, Lucía Belén', '27314558206', 3, 12, 'LOCAL', 'DGR_RN', 0, 0,
           'Externo sin ERP: Mis Comprobantes de ARCA (04_lucia_fernandez). Monotributo categoría D, psicóloga. Viedma.'
    UNION ALL SELECT 3, 'Gómez, Martín Alejandro', '20287619341', 3, 12, 'LOCAL', 'ARBA', 0, 0,
           'Externo sin ERP: Mis Comprobantes de ARCA (05_martin_gomez). Monotributo categoría H, almacén "Despensa Don Martín". Carmen de Patagones.'
    UNION ALL SELECT 1, 'Metalúrgica Comahue SRL', '30717022889', 1, 12, 'CONVENIO_MULTILATERAL', 'DGR_NQN', 1, 22,
           'Usa gestion_multipyme en otra instancia (servidor propio). Ingreso de datos: ver _local/docs/estudio_contable/02_clientes_con_erp_remoto.md.'
    UNION ALL SELECT 1, 'Distribuidora Golfo Azul SA', '30706554196', 1, 6, 'LOCAL', 'DGR_CHUBUT', 1, 9,
           'Usa gestion_multipyme en otra instancia (servidor propio). Ingreso de datos: ver _local/docs/estudio_contable/02_clientes_con_erp_remoto.md.'
    UNION ALL SELECT 3, 'Panificadora Atlántica SRL', '30714886300', 1, 12, 'LOCAL', 'ARBA', 1, 11,
           'Usa gestion_multipyme en otra instancia (servidor propio). Ingreso de datos: ver _local/docs/estudio_contable/02_clientes_con_erp_remoto.md.'
) v
WHERE NOT EXISTS (SELECT 1 FROM estudio__clientes c WHERE c.empresa_id = @estudio AND c.cuit = v.cuit);

-- Matriz de jurisdicciones (sede + Convenio Multilateral con coeficiente CM05)
INSERT INTO estudio__clientes_jurisdicciones (empresa_id, cliente_id, jurisdiccion_id, tipo_inscripcion,
       coeficiente_cm05, es_agente_percepcion, f_alta, tabla_estado_registro_id)
SELECT @estudio, c.cliente_id, j.jurisdiccion_id, v.tipo_insc, v.coef, v.agente_perc, '2026-01-01', 1
FROM (
    SELECT '30718442148' AS cuit, 'DGR_RN' AS juris, 'SEDE_PRINCIPAL' AS tipo_insc, 0.0000 AS coef, 0 AS agente_perc
    UNION ALL SELECT '30709923818', 'DGR_RN', 'SEDE_PRINCIPAL', 0.6200, 0
    UNION ALL SELECT '30709923818', 'ARBA', 'EXTRAÑA_JURISDICCION', 0.3800, 0
    UNION ALL SELECT '30716330571', 'ARBA', 'SEDE_PRINCIPAL', 0.0000, 1
    UNION ALL SELECT '27314558206', 'DGR_RN', 'REGIMEN_SIMPLIFICADO', 0.0000, 0
    UNION ALL SELECT '20287619341', 'ARBA', 'REGIMEN_SIMPLIFICADO', 0.0000, 0
    UNION ALL SELECT '30717022889', 'DGR_NQN', 'SEDE_PRINCIPAL', 0.7100, 0
    UNION ALL SELECT '30717022889', 'DGR_RN', 'EXTRAÑA_JURISDICCION', 0.2900, 0
    UNION ALL SELECT '30706554196', 'DGR_CHUBUT', 'SEDE_PRINCIPAL', 0.0000, 0
    UNION ALL SELECT '30714886300', 'ARBA', 'SEDE_PRINCIPAL', 0.0000, 0
) v
JOIN estudio__clientes c ON c.empresa_id = @estudio AND c.cuit = v.cuit
JOIN gestion__jurisdicciones j ON j.jurisdiccion_codigo = v.juris
WHERE NOT EXISTS (SELECT 1 FROM estudio__clientes_jurisdicciones x WHERE x.cliente_id = c.cliente_id AND x.jurisdiccion_id = j.jurisdiccion_id);
