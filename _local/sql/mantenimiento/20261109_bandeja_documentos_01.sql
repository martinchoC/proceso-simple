-- Bandeja de captura de documentos, fase 1 (bandeja interna). Diseño: _local/docs/gestion/15_bandeja_captura.md
-- Una fila por archivo recibido (pantalla "Cargar desde archivo" de Facturas de proveedores o carpeta del cron), con su
-- estado de lectura y lo que se creó al confirmarlo. El archivo sigue en uploads/facturas_proveedores/<empresa>/ y en
-- gestion__facturas_proveedores_adjuntos (que es como la factura ve su original): la bandeja apunta al adjunto.
-- Código único en core/bandeja/; los archivos de modules/gestion* solo fijan la página (141 / 641).
-- Sin motor de estados: `estado` propio (como gestion__bancos_extracto); botones por data-funcion + permisos_exigir_accion.
-- Ids libres verificados el 2026-11-09: tabla 456, páginas 141 / 641, funciones 3571–3573 / 8571–8573.
-- Reanudable (CREATE IF NOT EXISTS, columnas e índices por information_schema, WHERE NOT EXISTS). MariaDB 10.4 y MySQL 8.
-- Orden en producción: este script → código (fp_lectura_model.php escribe empresa_ia_id y la bandeja).

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------------------------------------------
-- 1. Bandeja
-- -----------------------------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gestion__bandeja_documentos` (
  `bandeja_documento_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL COMMENT 'Empresa dueña del documento (de la sesión o de la configuración del cron, nunca del request)',
  `estudio_cliente_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = empresa propia; > 0 = cliente del estudio (estudio__clientes.cliente_id)',
  `origen` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PANTALLA / CARPETA / ANTERIOR (cargado antes de la bandeja); fase 2: PORTAL, WHATSAPP, EMAIL',
  `tipo_documento` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FACTURA_COMPRA' COMMENT 'FACTURA_COMPRA; fase 3: GASTO, EXTRACTO, OTRO',
  `archivo_ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relativa a la raíz, sin acceso web',
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanio_bytes` int(11) NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECIBIDO' COMMENT 'RECIBIDO / LEYENDO / LISTO / REVISAR / ERROR / PROCESADO / DESCARTADO / DUPLICADO',
  `motivo` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Por qué quedó en REVISAR / ERROR / DUPLICADO / DESCARTADO',
  `adjunto_id` int(11) DEFAULT NULL COMMENT 'gestion__facturas_proveedores_adjuntos (FACTURA_COMPRA)',
  `extraccion_id` int(11) DEFAULT NULL COMMENT 'Última lectura en gestion__documentos_extracciones',
  `duplicado_de_id` int(11) DEFAULT NULL COMMENT 'Documento con el mismo archivo ya procesado',
  `destino_tabla_id` int(11) DEFAULT NULL COMMENT 'conf__tablas.tabla_id de lo creado al procesarlo (84 = facturas de proveedores)',
  `destino_registro_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Quien lo subió (en el cron, el usuario configurado)',
  `fecha_alta` datetime NOT NULL,
  `usuario_proceso_id` int(11) DEFAULT NULL COMMENT 'Quien lo procesó o descartó',
  `fecha_proceso` datetime DEFAULT NULL,
  PRIMARY KEY (`bandeja_documento_id`),
  UNIQUE KEY `uk_bdoc_adjunto` (`adjunto_id`),
  KEY `idx_bdoc_estado` (`empresa_id`, `estado`, `fecha_alta`),
  KEY `idx_bdoc_cliente` (`empresa_id`, `estudio_cliente_id`, `fecha_alta`),
  KEY `idx_bdoc_hash` (`empresa_id`, `sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bandeja de captura: documentos recibidos y su estado de lectura';

-- -----------------------------------------------------------------------------------------------------------------
-- 2. Log de lecturas: a qué documento de la bandeja corresponde, quién paga la IA y qué cliente del estudio la usó
--    (la IA de un cliente del estudio la paga el estudio: empresa_ia_id = estudio, empresa_id = dueña del documento)
-- -----------------------------------------------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__documentos_extracciones' AND COLUMN_NAME = 'bandeja_documento_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__documentos_extracciones ADD COLUMN bandeja_documento_id int(11) DEFAULT NULL COMMENT ''gestion__bandeja_documentos'' AFTER adjunto_id',
    'SELECT ''gestion__documentos_extracciones.bandeja_documento_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__documentos_extracciones' AND COLUMN_NAME = 'empresa_ia_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__documentos_extracciones ADD COLUMN empresa_ia_id int(11) DEFAULT NULL COMMENT ''Empresa que paga la IA (clave y tope diario); = empresa_id salvo documentos de clientes del estudio'' AFTER empresa_id',
    'SELECT ''gestion__documentos_extracciones.empresa_ia_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__documentos_extracciones' AND COLUMN_NAME = 'estudio_cliente_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__documentos_extracciones ADD COLUMN estudio_cliente_id int(11) NOT NULL DEFAULT 0 COMMENT ''Cliente del estudio que usó la lectura (0 = empresa propia)'' AFTER empresa_ia_id',
    'SELECT ''gestion__documentos_extracciones.estudio_cliente_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE gestion__documentos_extracciones SET empresa_ia_id = empresa_id WHERE empresa_ia_id IS NULL;

-- El tope diario pasa a contarse por la empresa que paga
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__documentos_extracciones' AND INDEX_NAME = 'idx_dex_tope_ia');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__documentos_extracciones ADD KEY idx_dex_tope_ia (empresa_ia_id, capa, fecha)',
    'SELECT ''idx_dex_tope_ia ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__documentos_extracciones' AND INDEX_NAME = 'idx_dex_bandeja');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__documentos_extracciones ADD KEY idx_dex_bandeja (bandeja_documento_id)',
    'SELECT ''idx_dex_bandeja ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Reportes de consumo (fase 4) por cliente del estudio
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__documentos_extracciones' AND INDEX_NAME = 'idx_dex_cliente');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__documentos_extracciones ADD KEY idx_dex_cliente (empresa_ia_id, estudio_cliente_id, fecha)',
    'SELECT ''idx_dex_cliente ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- -----------------------------------------------------------------------------------------------------------------
-- 3. Documentos ya leídos antes de la bandeja: una fila por adjunto (origen ANTERIOR)
--    con factura → PROCESADO; sin factura y con el mismo archivo en una factura → DUPLICADO;
--    sin factura y subido de nuevo después → DESCARTADO; el resto → REVISAR (para descartarlo o cargarlo).
-- -----------------------------------------------------------------------------------------------------------------
INSERT INTO gestion__bandeja_documentos
       (empresa_id, estudio_cliente_id, origen, tipo_documento, archivo_ruta, nombre_original, mime, tamanio_bytes, sha256,
        estado, motivo, adjunto_id, duplicado_de_id, destino_tabla_id, destino_registro_id, usuario_id, fecha_alta,
        usuario_proceso_id, fecha_proceso)
SELECT a.empresa_id, 0, 'ANTERIOR', 'FACTURA_COMPRA', a.archivo_ruta, a.nombre_original, a.mime, a.tamanio_bytes, a.sha256,
       CASE WHEN a.factura_proveedor_id IS NOT NULL THEN 'PROCESADO'
            WHEN p.adjunto_id IS NOT NULL THEN 'DUPLICADO'
            WHEN EXISTS (SELECT 1 FROM gestion__facturas_proveedores_adjuntos n
                         WHERE n.empresa_id = a.empresa_id AND n.sha256 = a.sha256 AND n.adjunto_id > a.adjunto_id) THEN 'DESCARTADO'
            ELSE 'REVISAR' END,
       CASE WHEN a.factura_proveedor_id IS NOT NULL THEN NULL
            WHEN p.adjunto_id IS NOT NULL THEN 'El mismo archivo ya está cargado en una factura'
            WHEN EXISTS (SELECT 1 FROM gestion__facturas_proveedores_adjuntos n
                         WHERE n.empresa_id = a.empresa_id AND n.sha256 = a.sha256 AND n.adjunto_id > a.adjunto_id) THEN 'Se volvió a subir el mismo archivo'
            ELSE 'Leído antes de la bandeja y no se guardó como factura' END,
       a.adjunto_id, NULL,
       IF(a.factura_proveedor_id IS NOT NULL, 84, NULL), a.factura_proveedor_id,
       a.usuario_id, a.fecha_alta,
       IF(a.factura_proveedor_id IS NOT NULL, a.usuario_id, NULL), IF(a.factura_proveedor_id IS NOT NULL, a.fecha_alta, NULL)
FROM gestion__facturas_proveedores_adjuntos a
LEFT JOIN (SELECT empresa_id, sha256, MIN(adjunto_id) AS adjunto_id
             FROM gestion__facturas_proveedores_adjuntos
            WHERE factura_proveedor_id IS NOT NULL AND tabla_estado_registro_id = 1
            GROUP BY empresa_id, sha256) p
       ON p.empresa_id = a.empresa_id AND p.sha256 = a.sha256 AND a.factura_proveedor_id IS NULL
WHERE a.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__bandeja_documentos b WHERE b.adjunto_id = a.adjunto_id);

-- duplicado_de_id = el documento de la bandeja del adjunto que está en la factura
UPDATE gestion__bandeja_documentos d
JOIN gestion__bandeja_documentos o
  ON o.empresa_id = d.empresa_id AND o.sha256 = d.sha256 AND o.estado = 'PROCESADO'
SET d.duplicado_de_id = o.bandeja_documento_id
WHERE d.estado = 'DUPLICADO' AND d.duplicado_de_id IS NULL;

-- Lecturas anteriores: documento y última lectura
UPDATE gestion__documentos_extracciones x
JOIN gestion__bandeja_documentos b ON b.adjunto_id = x.adjunto_id AND b.empresa_id = x.empresa_id
SET x.bandeja_documento_id = b.bandeja_documento_id
WHERE x.bandeja_documento_id IS NULL;

UPDATE gestion__bandeja_documentos b
JOIN (SELECT bandeja_documento_id, MAX(extraccion_id) AS ultima
        FROM gestion__documentos_extracciones WHERE bandeja_documento_id IS NOT NULL GROUP BY bandeja_documento_id) u
  ON u.bandeja_documento_id = b.bandeja_documento_id
SET b.extraccion_id = u.ultima
WHERE b.extraccion_id IS NULL;

-- -----------------------------------------------------------------------------------------------------------------
-- 4. Configuración: tabla, páginas (bajo Compras, después de Facturas de Proveedores), funciones y permisos
-- -----------------------------------------------------------------------------------------------------------------
INSERT INTO conf__tablas (tabla_id, tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_patron_id, tabla_estado_registro_id)
SELECT 456, 'gestion__bandeja_documentos', 'Bandeja de captura: documentos recibidos (pantalla, carpeta, más adelante portal) y su lectura', 2, 2, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__bandeja_documentos')
  AND NOT EXISTS (SELECT 1 FROM conf__tablas t WHERE t.tabla_id = 456);

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT v.id, v.modulo, 'Bandeja de Documentos', 'bandeja_documentos.php', 96, v.descr, v.padre, 25, 456, 0, 1, 1, 0
FROM (
              SELECT 141 AS id, 2 AS modulo, 47 AS padre,
                     'Documentos recibidos (facturas de proveedores leídas desde archivo o desde la carpeta de entrada) y su estado' AS descr
    UNION ALL SELECT 641, 5, 547,
                     'Documentos recibidos (facturas de proveedores leídas desde archivo o desde la carpeta de entrada) y su estado'
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.url = 'bandeja_documentos.php' AND p.modulo_id = v.modulo)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.id);

-- Procesar (guardar en Borrador) no es función de la bandeja: exige "Agregar" de Facturas de Proveedores (233 / 5233)
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina, 0, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (
              SELECT 3571 AS id, 141 AS pagina, 'Acceder' AS nombre, 'consultar' AS accion, 'Ver la bandeja y los archivos' AS descr, 1 AS orden
    UNION ALL SELECT 3572, 141, 'Cargar', 'agregar', 'Subir y leer documentos', 2
    UNION ALL SELECT 3573, 141, 'Descartar', 'inhabilitar', 'Descartar documentos', 3
    UNION ALL SELECT 8571, 641, 'Acceder', 'consultar', 'Ver la bandeja y los archivos', 1
    UNION ALL SELECT 8572, 641, 'Cargar', 'agregar', 'Subir y leer documentos', 2
    UNION ALL SELECT 8573, 641, 'Descartar', 'inhabilitar', 'Descartar documentos', 3
) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina AND p.url = 'bandeja_documentos.php')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = v.pagina AND f.accion_js = v.accion)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

-- Heredan de Facturas de Proveedores: Visualizar (237 / 5237) → Acceder; Agregar (233 / 5233) → Acceder, Cargar y Descartar
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT DISTINCT pf.perfil_id, m.nueva, 1, NOW()
FROM conf__perfiles_funciones pf
JOIN (          SELECT 237 AS vieja, 3571 AS nueva UNION ALL SELECT 233, 3571 UNION ALL SELECT 233, 3572 UNION ALL SELECT 233, 3573
      UNION ALL SELECT 5237, 8571 UNION ALL SELECT 5233, 8571 UNION ALL SELECT 5233, 8572 UNION ALL SELECT 5233, 8573) m
  ON m.vieja = pf.pagina_funcion_id
WHERE pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.nueva)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = m.nueva);

-- Perfiles de las empresas (la única fuente que lee core/permisos.php)
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT DISTINCT epf.empresa_id, epf.empresa_perfil_id, m.nueva, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
JOIN (          SELECT 237 AS vieja, 3571 AS nueva UNION ALL SELECT 233, 3571 UNION ALL SELECT 233, 3572 UNION ALL SELECT 233, 3573
      UNION ALL SELECT 5237, 8571 UNION ALL SELECT 5233, 8571 UNION ALL SELECT 5233, 8572 UNION ALL SELECT 5233, 8573) m
  ON m.vieja = epf.pagina_funcion_id
WHERE epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.nueva)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = m.nueva);
