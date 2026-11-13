-- Bandeja de captura de documentos, fase 2 (enlaces externos y portal de clientes).
-- Diseño: _local/docs/gestion/15_bandeja_captura.md
-- Tabla gestion__bandeja_enlaces para accesos públicos seguros con token hash.
-- Columnas enlace_id y requerimiento_id en gestion__bandeja_documentos, y usuario_id nullable para subidas públicas.
-- Reanudable (CREATE IF NOT EXISTS, information_schema). MariaDB 10.4 y MySQL 8.

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------------------------------------------
-- 1. Tabla de enlaces públicos de captura
-- -----------------------------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gestion__bandeja_enlaces` (
  `enlace_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL COMMENT 'Empresa dueña de los documentos',
  `estudio_cliente_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = empresa propia; > 0 = cliente del estudio (estudio__clientes.cliente_id)',
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 del token',
  `token_prefijo` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Primeros caracteres para identificar el link sin exponerlo',
  `etiqueta` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre o descripción para el cliente / proveedor',
  `f_vencimiento` date DEFAULT NULL COMMENT 'Fecha de expiración (NULL = sin vencimiento)',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = habilitado, 0 = revocado',
  `max_archivos_dia` int(11) NOT NULL DEFAULT 50 COMMENT 'Tope diario de archivos por este enlace',
  `max_bytes_archivo` int(11) NOT NULL DEFAULT 15728640 COMMENT 'Tope en bytes por archivo (default 15 MB)',
  `creado_por` int(11) DEFAULT NULL COMMENT 'conf__usuarios.usuario_id',
  `fecha_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`enlace_id`),
  UNIQUE KEY `uk_benl_token_hash` (`token_hash`),
  KEY `idx_benl_empresa` (`empresa_id`, `activo`),
  KEY `idx_benl_cliente` (`empresa_id`, `estudio_cliente_id`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Enlaces públicos externos para subida de comprobantes a la bandeja';

-- -----------------------------------------------------------------------------------------------------------------
-- 2. Modificaciones a gestion__bandeja_documentos
-- -----------------------------------------------------------------------------------------------------------------

-- usuario_id pasa a ser nullable para subidas desde portal público sin sesión
ALTER TABLE `gestion__bandeja_documentos` MODIFY `usuario_id` int(11) DEFAULT NULL COMMENT 'Quien lo subió (NULL si entró por portal público)';

-- Columna enlace_id
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__bandeja_documentos' AND COLUMN_NAME = 'enlace_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__bandeja_documentos ADD COLUMN enlace_id int(11) DEFAULT NULL COMMENT ''gestion__bandeja_enlaces.enlace_id'' AFTER origen, ADD KEY idx_bdoc_enlace (enlace_id)',
    'SELECT ''gestion__bandeja_documentos.enlace_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Columna requerimiento_id (para checklist de fase 2)
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__bandeja_documentos' AND COLUMN_NAME = 'requerimiento_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__bandeja_documentos ADD COLUMN requerimiento_id int(11) DEFAULT NULL COMMENT ''gestion__bandeja_requerimientos.requerimiento_id'' AFTER destino_registro_id, ADD KEY idx_bdoc_requerimiento (requerimiento_id)',
    'SELECT ''gestion__bandeja_documentos.requerimiento_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- -----------------------------------------------------------------------------------------------------------------
-- 3. Registro en conf__tablas
-- -----------------------------------------------------------------------------------------------------------------
INSERT INTO conf__tablas (tabla_id, tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_patron_id, tabla_estado_registro_id)
SELECT 457, 'gestion__bandeja_enlaces', 'Enlaces públicos externos para subida de comprobantes a la bandeja', 2, 2, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__bandeja_enlaces')
  AND NOT EXISTS (SELECT 1 FROM conf__tablas t WHERE t.tabla_id = 457);
