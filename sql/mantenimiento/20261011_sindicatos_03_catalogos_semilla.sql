-- ==============================================================================
-- Script: 20261011_sindicatos_03_catalogos_semilla.sql
-- Propósito: Siembra de catálogos base, parámetros del sindicato y medios de pago
--            para Empresa 3 (CEC Patagones / AECBB) desde develops_aecbb1.
-- ==============================================================================

USE gestion_multipyme;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Parámetros Generales del Sindicato (Página 1001) para Empresa 3
-- ------------------------------------------------------------------------------
INSERT INTO sindi__parametros (
    empresa_id, f_desde, portal_slug, dias_gracia, aprobacion_autoalta, email_avisos,
    media_jornada_piso, media_jornada_factor, convenio_id_default,
    smtp_host, smtp_puerto, smtp_seguridad, smtp_usuario, smtp_clave,
    tabla_estado_registro_id, fecha_creacion
) VALUES (
    3, '2020-01-01', 'sindicato', 0, 0, 'sindicato@aecbb.org.ar',
    'COMPLETO', 0.50, 1,
    'smtp.gmail.com', 587, 'tls', 'avisos@aecbb.org.ar', NULL,
    1, NOW()
) ON DUPLICATE KEY UPDATE
    portal_slug = VALUES(portal_slug),
    media_jornada_piso = VALUES(media_jornada_piso),
    media_jornada_factor = VALUES(media_jornada_factor);

-- ------------------------------------------------------------------------------
-- 2. Convenio Colectivo de Trabajo Principal (CCT 130/75)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__convenios (convenio_id, empresa_id, codigo, nombre, descripcion, tabla_estado_registro_id, fecha_creacion)
VALUES (1, 3, 'CCT 130/75', 'Convenio Colectivo de Trabajo 130/75 - Empleados de Comercio', 'Convenio general de empleados mercantiles y afines', 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ------------------------------------------------------------------------------
-- 3. Conceptos de Aporte Sindical (Art. 100 y Art. 101)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__conceptos (
    concepto_id, empresa_id, convenio_id, codigo, descripcion,
    base_calculo, porcentaje, valor_fijo, solo_afiliados, aplica_piso_escala, incluye_sac,
    cuenta_concepto_codigo, f_desde, tabla_estado_registro_id, fecha_creacion
) VALUES 
(
    1, 3, 1, 'ART100', 'FONDO SOCIAL ART.100 C.C.T. 130/75',
    'REM_TOTAL', 2.00, 0.00, 0, 1, 1,
    'SINDI_DEUDORES_APORTES', '2015-11-21', 1, NOW()
),
(
    2, 3, 1, 'ART101', 'CUOTA SINDICAL ART.101 C.C.T. 130/75',
    'REM_TOTAL', 2.00, 0.00, 1, 1, 1,
    'SINDI_DEUDORES_APORTES', '2015-11-21', 1, NOW()
)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    porcentaje = VALUES(porcentaje),
    solo_afiliados = VALUES(solo_afiliados),
    aplica_piso_escala = VALUES(aplica_piso_escala),
    incluye_sac = VALUES(incluye_sac);

-- ------------------------------------------------------------------------------
-- 4. Categorías de Empleados del CCT 130/75 (desde develops_aecbb1)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__categorias (
    categoria_id, empresa_id, convenio_id, codigo, nombre, descripcion, tabla_estado_registro_id, fecha_creacion
)
SELECT 
    empleado_categoria_id,
    3,
    1,
    CONCAT(TRIM(empleado_categoria), ' - ', TRIM(empleado_subcategoria)),
    CONCAT(TRIM(empleado_categoria), ' (Cat. ', TRIM(empleado_subcategoria), ')'),
    CONCAT('Categoría ', TRIM(empleado_subcategoria), ' del agrupamiento ', TRIM(empleado_categoria)),
    1,
    NOW()
FROM develops_aecbb1.sindi__empleados_categorias
ON DUPLICATE KEY UPDATE
    codigo = VALUES(codigo),
    nombre = VALUES(nombre);

-- ------------------------------------------------------------------------------
-- 5. Escalas Salariales (desde develops_aecbb1)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__categorias_escalas (
    escala_id, empresa_id, categoria_id, periodo, basico, rem_1, rem_2, tabla_estado_registro_id, fecha_creacion
)
SELECT 
    empleado_categoria_escala_id,
    3,
    empleado_categoria_id,
    (anio * 100 + mes),
    COALESCE(basico, 0),
    COALESCE(rem1, 0),
    COALESCE(rem2, 0),
    1,
    NOW()
FROM develops_aecbb1.sindi__empleados_categorias_escalas
ON DUPLICATE KEY UPDATE
    basico = VALUES(basico),
    rem_1 = VALUES(rem_1),
    rem_2 = VALUES(rem_2);

-- ------------------------------------------------------------------------------
-- 6. Tasas de Interés Resarcitorio (desde develops_aecbb1)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__tasas_interes (
    tasa_interes_id, empresa_id, clase, tasa_mensual, f_desde, f_hasta, tabla_estado_registro_id, legacy_id, fecha_creacion
)
SELECT 
    interes_tipo_id,
    3,
    clase,
    porcentaje,
    f_desde,
    f_hasta,
    1,
    interes_tipo_id,
    NOW()
FROM develops_aecbb1.sindi__intereses_tipos
ON DUPLICATE KEY UPDATE
    tasa_mensual = VALUES(tasa_mensual),
    f_desde = VALUES(f_desde),
    f_hasta = VALUES(f_hasta);

-- ------------------------------------------------------------------------------
-- 7. Categorías de Mora
-- ------------------------------------------------------------------------------
INSERT INTO sindi__categorias_mora (
    categoria_mora_id, empresa_id, codigo, nombre, dias_desde, dias_hasta, color, orden, tabla_estado_registro_id, fecha_creacion
) VALUES
(1, 3, 'PUNTUAL', 'Puntual', 0, 15, '#28a745', 1, 1, NOW()),
(2, 3, 'MORA_TEMPRANA', 'Mora Temprana', 16, 30, '#ffc107', 2, 1, NOW()),
(3, 3, 'MORA_MODERADA', 'Mora Moderada', 31, 60, '#fd7e14', 3, 1, NOW()),
(4, 3, 'MORA_PROLONGADA', 'Mora Prolongada', 61, 120, '#dc3545', 4, 1, NOW()),
(5, 3, 'MORA_CRONICA', 'Mora Crónica', 121, 65000, '#6f42c1', 5, 1, NOW())
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    dias_desde = VALUES(dias_desde),
    dias_hasta = VALUES(dias_hasta),
    color = VALUES(color),
    orden = VALUES(orden);

-- ------------------------------------------------------------------------------
-- 8. Obras Sociales (Padrón RNOS nacional desde develops_aecbb1)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__obras_sociales (
    obra_social_id, codigo_rnos, nombre, sigla, f_alta, f_baja, tabla_estado_registro_id, fecha_creacion
)
SELECT 
    obra_social_id,
    LPAD(obra_social_id, 6, '0'),
    CONVERT(COALESCE(NULLIF(obra_social_desc, ''), obra_social) USING utf8mb4),
    CONVERT(obra_social USING utf8mb4),
    IF(f_alta = '0000-00-00', NULL, f_alta),
    IF(f_baja = '0000-00-00', NULL, f_baja),
    IF(estado_registro_id = 100, 2, 1),
    NOW()
FROM develops_aecbb1.sindi__obras_sociales
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    sigla = VALUES(sigla),
    f_alta = VALUES(f_alta),
    f_baja = VALUES(f_baja);

-- ------------------------------------------------------------------------------
-- 9. Novedades y Licencias (desde develops_aecbb1)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__novedades (
    novedad_id, empresa_id, codigo, descripcion, tabla_estado_registro_id, fecha_creacion
)
SELECT 
    novedad_id,
    3,
    CONCAT('NOV_', novedad_id),
    CONVERT(novedad USING utf8mb4),
    1,
    NOW()
FROM develops_aecbb1.sindi__novedades
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion);

-- ------------------------------------------------------------------------------
-- 10. Motivos de Baja (desde develops_aecbb1)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__bajas_motivos (
    baja_motivo_id, empresa_id, codigo, descripcion, tabla_estado_registro_id, fecha_creacion
)
SELECT 
    baja_motivo_id,
    3,
    CONCAT('BAJA_', baja_motivo_id),
    CONVERT(baja_motivo USING utf8mb4),
    1,
    NOW()
FROM develops_aecbb1.sindi__bajas_motivos
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion);

-- ------------------------------------------------------------------------------
-- 11. Medios de Pago y Canales de Recaudación (Página 1006)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__medios_pago (
    medio_pago_id, empresa_id, codigo, nombre, driver, ambiente, orden,
    publica_deuda, rendicion_modo, barras_prefijo, barras_simbologia,
    tabla_estado_registro_id, fecha_creacion
) VALUES
(
    1, 3, 'BNA', 'Banco de la Nación Argentina', 'BNA_BARRAS', 'PRODUCCION', 1,
    0, 'ARCHIVO_MANUAL', '869955555', 'Code128', 1, NOW()
),
(
    2, 3, 'LINK', 'Red Link - Pagos Link', 'RED_LINK', 'PRODUCCION', 2,
    1, 'SFTP', NULL, 'Code128', 1, NOW()
),
(
    3, 3, 'IBNK', 'Interbanking - Cobranzas Directas', 'INTERBANKING', 'PRODUCCION', 3,
    1, 'API', NULL, 'Code128', 1, NOW()
),
(
    4, 3, 'BAPRO', 'Banco Provincia de Buenos Aires', 'BAPRO', 'PRODUCCION', 4,
    0, 'SFTP', NULL, 'Code128', 1, NOW()
),
(
    5, 3, 'CAJA', 'Caja del Sindicato (Cobro por Ventanilla)', 'MANUAL', 'PRODUCCION', 5,
    0, 'ARCHIVO_MANUAL', NULL, 'Code128', 1, NOW()
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    driver = VALUES(driver),
    ambiente = VALUES(ambiente),
    publica_deuda = VALUES(publica_deuda),
    rendicion_modo = VALUES(rendicion_modo);

-- ------------------------------------------------------------------------------
-- 12. Vencimientos de DDJJ recientes (2024-2026 desde develops_aecbb1)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__vencimientos (
    empresa_id, periodo, terminacion_cuit, tipo_vencimiento, f_vto, tabla_estado_registro_id, fecha_creacion
)
SELECT 
    3,
    periodo,
    digito,
    'DDJJ',
    f_vto,
    1,
    NOW()
FROM develops_aecbb1.sindi__vtos_trans
WHERE anio >= 2024
ON DUPLICATE KEY UPDATE
    f_vto = VALUES(f_vto);

SET FOREIGN_KEY_CHECKS = 1;
