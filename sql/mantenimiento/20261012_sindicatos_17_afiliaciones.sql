-- ==============================================================================
-- 20261012_sindicatos_17_afiliaciones.sql
-- Módulo 6 (Sindicatos) - Fase 5: afiliaciones y grupo familiar (especificación §5.6).
--
-- 1. Tablas: parentescos, reglas de vigencia por edad (con su excepción por documento),
--    documentos presentados, solicitudes de afiliación y cuotas directas generadas.
--    sindi__afiliaciones: ddjj_baja_id y número de afiliado único por empresa.
-- 2. Corrección de datos: la confirmación de DDJJ creaba una afiliación de ACTIVIDAD nueva en cada
--    presentación (sin clave única) y con el tipo de la empresa 3 en cualquier empresa. Se remapea el
--    tipo por código y quedan anuladas (38) las repetidas.
-- 3. Catálogos iniciales por empresa: parentescos con su inverso y regla de vigencia de hijos
--    (hasta 21 años; hasta 25 con certificado de alumno regular, válido 12 meses).
-- 4. Rol de entidad AFIL (afiliado que paga su cuota directa), tipo de comprobante CAF (cuota de
--    afiliado) y sus conceptos contables donde el plan de la empresa tiene las cuentas.
-- 5. Configuración: tablas 540-544, páginas 1050 (Afiliados y grupo familiar), 1051 (Solicitudes)
--    y 1054 (Tipos, parentescos y vigencias), estados, funciones 16300-16399 y permisos para los
--    perfiles internos.
--
-- Corre en MariaDB 10.4 y MySQL 8. Reanudable.
-- ==============================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------------------
-- 1. Tablas
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sindi__parentescos` (
  `parentesco_id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                SMALLINT UNSIGNED NOT NULL,
  `codigo`                    VARCHAR(20) NOT NULL,
  `nombre`                    VARCHAR(60) NOT NULL,
  `parentesco_inverso_id`     INT UNSIGNED NULL DEFAULT NULL,
  `tabla_estado_registro_id`  SMALLINT(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`parentesco_id`),
  UNIQUE KEY `uk_parentesco_codigo` (`empresa_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parentescos del grupo familiar (con su inverso)';

CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_vigencias` (
  `afiliacion_vigencia_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                SMALLINT UNSIGNED NOT NULL,
  `parentesco_id`             INT UNSIGNED NOT NULL,
  `edad_maxima`               TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Cobertura hasta el día anterior a cumplir esta edad; NULL = sin límite',
  `edad_maxima_excepcion`     TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Límite extendido presentando el documento de excepción',
  `documento_excepcion`       VARCHAR(100) NULL DEFAULT NULL COMMENT 'Documento que habilita la excepción (p. ej. certificado de alumno regular)',
  `meses_validez_documento`   TINYINT UNSIGNED NOT NULL DEFAULT 12,
  `tabla_estado_registro_id`  SMALLINT(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`afiliacion_vigencia_id`),
  UNIQUE KEY `uk_vigencia_parentesco` (`empresa_id`, `parentesco_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vigencia de la cobertura familiar por parentesco y edad';

CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_documentos` (
  `afiliacion_documento_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                SMALLINT UNSIGNED NOT NULL,
  `afiliacion_id`             INT UNSIGNED NOT NULL,
  `documento`                 VARCHAR(100) NOT NULL,
  `archivo_nombre`            VARCHAR(255) NULL DEFAULT NULL,
  `archivo_ruta`              VARCHAR(255) NULL DEFAULT NULL,
  `f_presentacion`            DATE NOT NULL,
  `f_vencimiento`             DATE NOT NULL,
  `tabla_estado_registro_id`  SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`       INT NULL DEFAULT NULL,
  `fecha_creacion`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`afiliacion_documento_id`),
  KEY `idx_afdoc_afiliacion` (`afiliacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos presentados para excepciones de vigencia';

CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_solicitudes` (
  `solicitud_id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                SMALLINT UNSIGNED NOT NULL,
  `afiliacion_tipo_id`        INT UNSIGNED NOT NULL,
  `persona_id`                INT UNSIGNED NULL DEFAULT NULL,
  `cuil`                      VARCHAR(11) NOT NULL,
  `apellido`                  VARCHAR(100) NOT NULL,
  `nombre`                    VARCHAR(100) NOT NULL,
  `f_nacimiento`              DATE NULL DEFAULT NULL,
  `sexo`                      CHAR(1) NULL DEFAULT NULL,
  `email`                     VARCHAR(150) NULL DEFAULT NULL,
  `telefono`                  VARCHAR(50) NULL DEFAULT NULL,
  `empleador_id`              INT UNSIGNED NULL DEFAULT NULL,
  `titular_afiliacion_id`     INT UNSIGNED NULL DEFAULT NULL,
  `parentesco_id`             INT UNSIGNED NULL DEFAULT NULL,
  `canal`                     ENUM('INTERNO','PORTAL') NOT NULL DEFAULT 'INTERNO',
  `f_solicitud`               DATE NOT NULL,
  `f_resolucion`              DATE NULL DEFAULT NULL,
  `motivo_rechazo`            VARCHAR(255) NULL DEFAULT NULL,
  `afiliacion_id`             INT UNSIGNED NULL DEFAULT NULL COMMENT 'Afiliación creada al aprobar',
  `observaciones`             VARCHAR(255) NULL DEFAULT NULL,
  `tabla_estado_registro_id`  SMALLINT(4) NOT NULL DEFAULT 3,
  `usuario_creacion_id`       INT NULL DEFAULT NULL,
  `fecha_creacion`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id`  INT NULL DEFAULT NULL,
  `fecha_actualizacion`       DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`solicitud_id`),
  KEY `idx_afsol_empresa_estado` (`empresa_id`, `tabla_estado_registro_id`),
  KEY `idx_afsol_cuil` (`empresa_id`, `cuil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitudes de afiliación voluntaria, jubilados y familiares';

CREATE TABLE IF NOT EXISTS `sindi__afiliaciones_cuotas` (
  `afiliacion_cuota_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`                SMALLINT UNSIGNED NOT NULL,
  `afiliacion_id`             INT UNSIGNED NOT NULL,
  `periodo`                   INT NOT NULL,
  `importe`                   DECIMAL(18,2) NOT NULL,
  `comprobante_id`            BIGINT UNSIGNED NULL DEFAULT NULL,
  `fecha_creacion`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`afiliacion_cuota_id`),
  UNIQUE KEY `uk_afcuota_periodo` (`afiliacion_id`, `periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuotas directas mensuales generadas (voluntarios y jubilados)';

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sindi__afiliaciones' AND COLUMN_NAME = 'ddjj_baja_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE sindi__afiliaciones ADD COLUMN ddjj_baja_id INT UNSIGNED NULL DEFAULT NULL COMMENT ''DDJJ que dio la baja (actividad)'' AFTER baja_motivo_id',
    'SELECT ''ddjj_baja_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Los tipos se habilitan/inhabilitan con la transición genérica, que registra fecha_actualizacion
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sindi__afiliaciones_tipos' AND COLUMN_NAME = 'fecha_actualizacion');
SET @sql := IF(@existe = 0,
    'ALTER TABLE sindi__afiliaciones_tipos ADD COLUMN fecha_actualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
    'SELECT ''sindi__afiliaciones_tipos.fecha_actualizacion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------------------------
-- 2. Corrección de afiliaciones de actividad (tipo de otra empresa y repetidas)
-- ------------------------------------------------------------------------------
UPDATE sindi__afiliaciones a
INNER JOIN sindi__afiliaciones_tipos t_mal ON t_mal.afiliacion_tipo_id = a.afiliacion_tipo_id AND t_mal.empresa_id <> a.empresa_id
INNER JOIN sindi__afiliaciones_tipos t_ok ON t_ok.empresa_id = a.empresa_id AND t_ok.codigo = t_mal.codigo
SET a.afiliacion_tipo_id = t_ok.afiliacion_tipo_id;

UPDATE sindi__afiliaciones a
INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id AND t.codigo = 'ACTIVIDAD'
INNER JOIN (
    SELECT x.empresa_id, x.persona_id, x.empleador_id, MIN(x.afiliacion_id) AS conservar
    FROM sindi__afiliaciones x
    INNER JOIN sindi__afiliaciones_tipos tx ON tx.afiliacion_tipo_id = x.afiliacion_tipo_id AND tx.codigo = 'ACTIVIDAD'
    WHERE x.tabla_estado_registro_id = 1
    GROUP BY x.empresa_id, x.persona_id, x.empleador_id
) k ON k.empresa_id = a.empresa_id AND k.persona_id = a.persona_id AND k.empleador_id <=> a.empleador_id
SET a.tabla_estado_registro_id = 38
WHERE a.tabla_estado_registro_id = 1 AND a.afiliacion_id <> k.conservar;

-- Número de afiliado para las afiliaciones titulares vigentes que no lo tienen (secuencia por empresa)
UPDATE sindi__afiliaciones a
INNER JOIN (
    SELECT afiliacion_id,
           ROW_NUMBER() OVER (PARTITION BY empresa_id ORDER BY afiliacion_id) AS nro
    FROM sindi__afiliaciones
    WHERE titular_afiliacion_id IS NULL AND numero_afiliado IS NULL AND tabla_estado_registro_id IN (1, 2, 20)
) n ON n.afiliacion_id = a.afiliacion_id
SET a.numero_afiliado = LPAD(n.nro + (SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(z.numero_afiliado, '/', 1) AS UNSIGNED)), 0)
                                      FROM (SELECT empresa_id, numero_afiliado FROM sindi__afiliaciones) z
                                      WHERE z.empresa_id = a.empresa_id AND z.numero_afiliado IS NOT NULL), 6, '0');

-- El número es de la persona (lo comparten sus afiliaciones titulares, p. ej. actividad con dos
-- empleadores): índice común, la unicidad por persona la asegura sindi_afil_numero_titular().
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sindi__afiliaciones' AND INDEX_NAME = 'uk_afil_numero');
SET @sql := IF(@existe > 0, 'ALTER TABLE sindi__afiliaciones DROP INDEX uk_afil_numero', 'SELECT ''sin uk_afil_numero''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sindi__afiliaciones' AND INDEX_NAME = 'idx_afil_numero');
SET @sql := IF(@existe = 0,
    'ALTER TABLE sindi__afiliaciones ADD KEY idx_afil_numero (empresa_id, numero_afiliado)',
    'SELECT ''idx_afil_numero ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

START TRANSACTION;

-- ------------------------------------------------------------------------------
-- 3. Catálogos iniciales (en cada empresa que tiene tipos de afiliación)
-- ------------------------------------------------------------------------------
INSERT INTO sindi__parentescos (empresa_id, codigo, nombre, tabla_estado_registro_id)
SELECT e.empresa_id, p.codigo, p.nombre, 1
FROM (SELECT DISTINCT empresa_id FROM sindi__afiliaciones_tipos) e
CROSS JOIN (SELECT 'CONYUGE' AS codigo, 'Cónyuge / conviviente' AS nombre
            UNION ALL SELECT 'HIJO', 'Hijo/a'
            UNION ALL SELECT 'PADRE', 'Padre'
            UNION ALL SELECT 'MADRE', 'Madre'
            UNION ALL SELECT 'SUEGRO', 'Suegro'
            UNION ALL SELECT 'SUEGRA', 'Suegra'
            UNION ALL SELECT 'OTRO', 'Otro familiar a cargo') p
WHERE NOT EXISTS (SELECT 1 FROM sindi__parentescos x WHERE x.empresa_id = e.empresa_id AND x.codigo = p.codigo);

-- Inversos (desde el familiar hacia el titular)
UPDATE sindi__parentescos p
INNER JOIN sindi__parentescos inv ON inv.empresa_id = p.empresa_id
       AND inv.codigo = CASE p.codigo WHEN 'CONYUGE' THEN 'CONYUGE' WHEN 'HIJO' THEN 'PADRE' WHEN 'PADRE' THEN 'HIJO'
                                      WHEN 'MADRE' THEN 'HIJO' WHEN 'OTRO' THEN 'OTRO' ELSE 'OTRO' END
SET p.parentesco_inverso_id = inv.parentesco_id
WHERE p.parentesco_inverso_id IS NULL;

INSERT INTO sindi__afiliaciones_vigencias (empresa_id, parentesco_id, edad_maxima, edad_maxima_excepcion, documento_excepcion, meses_validez_documento, tabla_estado_registro_id)
SELECT p.empresa_id, p.parentesco_id, 21, 25, 'Certificado de alumno regular', 12, 1
FROM sindi__parentescos p
WHERE p.codigo = 'HIJO'
  AND NOT EXISTS (SELECT 1 FROM sindi__afiliaciones_vigencias x WHERE x.empresa_id = p.empresa_id AND x.parentesco_id = p.parentesco_id);

-- ------------------------------------------------------------------------------
-- 4. Rol AFIL, tipo de comprobante CAF y conceptos contables de cuotas
-- ------------------------------------------------------------------------------
INSERT INTO gestion__roles_entidades (rol_entidad_id, rol_entidad_codigo, rol_entidad_nombre, rol_entidad_descripcion)
SELECT (SELECT MAX(rol_entidad_id) + 1 FROM gestion__roles_entidades r), 'AFIL', 'Afiliado',
       'Afiliado al sindicato que paga su cuota en forma directa (voluntario o jubilado)'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gestion__roles_entidades WHERE rol_entidad_codigo = 'AFIL');

INSERT INTO gestion__comprobantes_tipos (
    empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id,
    impacta_stock, impacta_contabilidad, impacta_ctacte, requiere_debito_cuenta,
    comprobante_tipo, codigo, signo, comentario, orden, tabla_estado_registro_id)
SELECT p.empresa_id, p.comprobante_grupo_id, p.comprobante_subgrupo_id, 0, 0, 1, 1, 0,
       'Cuota de Afiliado', 'CAF', '+', 'Cuota mensual de afiliados voluntarios y jubilados', 11, 1
FROM gestion__comprobantes_tipos p
WHERE p.codigo = 'PLC'
  AND NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos x WHERE x.empresa_id = p.empresa_id AND x.codigo = 'CAF');

INSERT INTO gestion__puntos_venta_comprobantes (empresa_id, punto_venta_id, comprobante_tipo_id, tabla_estado_registro_id)
SELECT t.empresa_id, pvc.punto_venta_id, t.comprobante_tipo_id, 1
FROM gestion__comprobantes_tipos t
INNER JOIN gestion__comprobantes_tipos plc ON plc.empresa_id = t.empresa_id AND plc.codigo = 'PLC'
INNER JOIN gestion__puntos_venta_comprobantes pvc ON pvc.comprobante_tipo_id = plc.comprobante_tipo_id
WHERE t.codigo = 'CAF'
  AND NOT EXISTS (SELECT 1 FROM gestion__puntos_venta_comprobantes x WHERE x.punto_venta_id = pvc.punto_venta_id AND x.comprobante_tipo_id = t.comprobante_tipo_id);

-- Recurso por cuotas: en los planes armados por el script 12 es la cuenta 4.1.03
INSERT INTO gestion__empresas_cuentas_conceptos (empresa_id, concepto, cont_cuenta_id, tabla_estado_registro_id, fecha_creacion)
SELECT c.empresa_id, 'SINDI_CUOTAS_AFILIADOS', c.cont_cuenta_id, 1, NOW()
FROM gestion__cont_cuentas c
WHERE c.codigo = '4.1.03' AND c.es_imputable = 1 AND c.nombre LIKE 'Cuotas de afiliados%'
  AND NOT EXISTS (SELECT 1 FROM gestion__empresas_cuentas_conceptos x WHERE x.empresa_id = c.empresa_id AND x.concepto = 'SINDI_CUOTAS_AFILIADOS');

-- ------------------------------------------------------------------------------
-- 5. Configuración de tablas, páginas, estados, funciones y permisos
-- ------------------------------------------------------------------------------
INSERT INTO conf__tablas (tabla_id, tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_estado_registro_id)
SELECT t.id, t.nombre, t.descr, 6, t.tipo, 1
FROM (SELECT 540 AS id, 'sindi__parentescos' AS nombre, 'Parentescos del grupo familiar' AS descr, 1 AS tipo
      UNION ALL SELECT 541, 'sindi__afiliaciones_vigencias', 'Vigencias por edad de la cobertura familiar', 1
      UNION ALL SELECT 542, 'sindi__afiliaciones_documentos', 'Documentos de excepción de vigencia', 1
      UNION ALL SELECT 543, 'sindi__afiliaciones_solicitudes', 'Solicitudes de afiliación', 2
      UNION ALL SELECT 544, 'sindi__afiliaciones_cuotas', 'Cuotas directas de afiliados', 1) t
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas x WHERE x.tabla_id = t.id);

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, tabla_estado_registro_id)
SELECT t.id, 6, t.pagina, t.url, NULL, t.descr, t.padre, t.orden, t.tabla, 1
FROM (SELECT 1050 AS id, 'Afiliados y Grupo Familiar' AS pagina, 'afiliados.php' AS url, 'Afiliaciones, familiares a cargo, documentos y vigencias' AS descr, 1019 AS padre, 40 AS orden, 527 AS tabla
      UNION ALL SELECT 1051, 'Solicitudes de Afiliación', 'afiliaciones_solicitudes.php', 'Solicitudes de afiliación voluntaria, jubilados y familiares', 1019, 50, 543
      UNION ALL SELECT 1054, 'Tipos, Parentescos y Vigencias', 'afiliaciones_config.php', 'Tipos de afiliación, parentescos y vigencias por edad', 1009, 70, 526) t
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas x WHERE x.pagina_id = t.id);

INSERT INTO conf__tablas_estados_registros (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, es_final, orden, aplica_stock)
SELECT t.id, t.tabla, t.estado, t.nombre, t.color, t.inicial, t.final, t.orden, 0
FROM (SELECT 3110 AS id, 527 AS tabla, 1 AS estado, 'Activa' AS nombre, 3 AS color, 1 AS inicial, 0 AS final, 1 AS orden
      UNION ALL SELECT 3111, 527, 2,  'Baja',        4, 0, 1, 2
      UNION ALL SELECT 3112, 527, 20, 'Vencida',     5, 0, 0, 3
      UNION ALL SELECT 3113, 527, 38, 'Anulada',     8, 0, 1, 4
      UNION ALL SELECT 3114, 543, 3,  'Borrador',    2, 1, 0, 1
      UNION ALL SELECT 3115, 543, 4,  'Pendiente de aprobación', 5, 0, 0, 2
      UNION ALL SELECT 3116, 543, 30, 'Aprobada',    3, 0, 1, 3
      UNION ALL SELECT 3117, 543, 25, 'Rechazada',   4, 0, 1, 4
      UNION ALL SELECT 3118, 543, 38, 'Anulada',     8, 0, 1, 5
      UNION ALL SELECT 3119, 526, 1,  'Activo',      3, 1, 0, 1
      UNION ALL SELECT 3120, 526, 2,  'Inactivo',    4, 0, 0, 2) t
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros x WHERE x.tabla_id = t.tabla AND x.estado_registro_id = t.estado)
  AND NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros y WHERE y.tabla_estado_registro_id = t.id);

DROP TEMPORARY TABLE IF EXISTS tmp_funciones_afil;
CREATE TEMPORARY TABLE tmp_funciones_afil (
    id INT NOT NULL PRIMARY KEY, pagina_id INT NOT NULL, nombre VARCHAR(100) NOT NULL, accion_js VARCHAR(100) NOT NULL,
    origen INT NOT NULL, destino INT NOT NULL, orden INT NOT NULL, color_id INT NOT NULL, icono_clase VARCHAR(100) NULL,
    solo_lectura TINYINT NOT NULL
) ENGINE=MEMORY;

INSERT INTO tmp_funciones_afil VALUES
-- 1050 Afiliados y grupo familiar (1 Activa, 2 Baja, 20 Vencida)
(16300, 1050, 'Nueva afiliación',     'agregar',          0,  1, 10, 1,  'bi bi-plus-circle-fill', 0),
(16301, 1050, 'Ficha',                'ver',              1,  1, 20, 9,  NULL, 1),
(16302, 1050, 'Ficha',                'ver',              2,  2, 21, 9,  NULL, 1),
(16303, 1050, 'Ficha',                'ver',              20, 20, 22, 9, NULL, 1),
(16304, 1050, 'Agregar familiar',     'agregar_familiar', 1,  1, 30, 3,  'bi bi-plus-circle-fill', 0),
(16305, 1050, 'Presentar documento',  'documento',        1,  1, 40, 6,  'bi bi-upload', 0),
(16306, 1050, 'Presentar documento',  'documento',        20, 1, 41, 6,  'bi bi-upload', 0),
(16307, 1050, 'Dar de baja',          'baja',             1,  2, 50, 4,  'bi bi-trash-fill', 0),
(16308, 1050, 'Ver todos los afiliados', 'consultar',     0,  0, 90, 10, NULL, 1),
-- 1051 Solicitudes (3 Borrador, 4 Pendiente, 30 Aprobada, 25 Rechazada, 38 Anulada)
(16320, 1051, 'Nueva solicitud',      'agregar',          0,  3, 10, 1,  'bi bi-plus-circle-fill', 0),
(16321, 1051, 'Editar',               'editar',           3,  3, 20, 9,  NULL, 0),
(16322, 1051, 'Enviar',               'enviar',           3,  4, 30, 6,  NULL, 0),
(16323, 1051, 'Anular',               'anular',           3, 38, 40, 4,  'bi bi-trash-fill', 0),
(16324, 1051, 'Aprobar',              'aprobar',          4, 30, 50, 3,  'bi bi-check-circle-fill', 0),
(16325, 1051, 'Rechazar',             'rechazar',         4, 25, 60, 4,  NULL, 0),
(16326, 1051, 'Ver',                  'ver',              4,  4, 70, 10, NULL, 1),
(16327, 1051, 'Ver',                  'ver',              30, 30, 71, 10, NULL, 1),
(16328, 1051, 'Ver',                  'ver',              25, 25, 72, 10, NULL, 1),
(16329, 1051, 'Ver',                  'ver',              38, 38, 73, 10, NULL, 1),
-- 1054 Tipos, parentescos y vigencias (tipos: 1 Activo, 2 Inactivo)
(16340, 1054, 'Editar',               'editar',           1,  1, 10, 9,  NULL, 0),
(16341, 1054, 'Inhabilitar',          'inactivar',        1,  2, 20, 4,  NULL, 0),
(16342, 1054, 'Habilitar',            'habilitar',        2,  1, 30, 3,  NULL, 0),
(16343, 1054, 'Administrar parentescos y vigencias', 'catalogos', 0, 0, 40, 10, NULL, 0),
(16344, 1054, 'Consultar',            'consultar',        0,  0, 90, 10, NULL, 1);

INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT t.id, t.pagina_id, (SELECT MIN(i.icono_id) FROM conf__iconos i WHERE i.icono_clase = t.icono_clase),
       t.color_id, 1, t.nombre, t.accion_js, NULL, t.origen, t.destino, t.orden, 1
FROM tmp_funciones_afil t
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = t.id);

-- Permisos: administradores y operadores del sindicato (601, 602) y administradores generales (1, 2)
-- todo; inspectores (603) solo consulta. Los perfiles externos no acceden.
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT p.perfil_id, t.id, 1
FROM tmp_funciones_afil t
INNER JOIN conf__perfiles p ON p.perfil_id IN (601, 602) OR (p.perfil_id = 603 AND t.solo_lectura = 1)
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = t.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT ep.empresa_id, ep.empresa_perfil_id, t.id, 1
FROM tmp_funciones_afil t
INNER JOIN conf__empresas_perfiles ep
        ON ep.tabla_estado_registro_id = 1
       AND (ep.perfil_id_base IN (1, 2, 601, 602) OR (ep.perfil_id_base = 603 AND t.solo_lectura = 1))
WHERE EXISTS (SELECT 1 FROM conf__empresas_modulos em WHERE em.empresa_id = ep.empresa_id AND em.modulo_id = 6)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = t.id);

DROP TEMPORARY TABLE IF EXISTS tmp_funciones_afil;

COMMIT;

-- ------------------------------------------------------------------------------
-- Verificación
-- ------------------------------------------------------------------------------
SELECT 'parentescos' AS control, COUNT(*) AS cantidad FROM sindi__parentescos
UNION ALL SELECT 'vigencias', COUNT(*) FROM sindi__afiliaciones_vigencias
UNION ALL SELECT 'actividad repetidas vigentes', COUNT(*) FROM (
    SELECT a.empresa_id, a.persona_id, a.empleador_id FROM sindi__afiliaciones a
    JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id AND t.codigo = 'ACTIVIDAD'
    WHERE a.tabla_estado_registro_id = 1 GROUP BY a.empresa_id, a.persona_id, a.empleador_id HAVING COUNT(*) > 1) d
UNION ALL SELECT 'afiliaciones con tipo de otra empresa', COUNT(*) FROM sindi__afiliaciones a
    JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id WHERE t.empresa_id <> a.empresa_id
UNION ALL SELECT 'funciones de afiliaciones', COUNT(*) FROM conf__paginas_funciones WHERE pagina_id IN (1050, 1051, 1054)
UNION ALL SELECT 'tipos CAF', COUNT(*) FROM gestion__comprobantes_tipos WHERE codigo = 'CAF';
