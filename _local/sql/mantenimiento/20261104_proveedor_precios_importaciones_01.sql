-- =====================================================================================================
-- Precios de Proveedores (proveedor_precios.php, gestion y gestion_normal): historial de importaciones
-- -----------------------------------------------------------------------------------------------------
-- Cada vez que se importa una lista de precios de un proveedor queda registrada la importación (archivo
-- original guardado en uploads/listas_proveedores/<empresa>/, usuario, fecha y totales) y el resultado de
-- cada fila del archivo: precio nuevo / actualizado / sin cambios, código no vinculado, fila con error
-- (sin código, precio inválido, código repetido) y los productos vinculados que no vinieron en el archivo.
-- Sirve para controlar errores en los archivos del proveedor.
--
--   1. gestion__proveedores_precios_importaciones (cabecera) y _detalles (una fila por fila del archivo y
--      producto vinculado; FALTANTE = vinculado que no vino, sin fila del archivo).
--   2. importacion_id en gestion__productos_proveedores_precios y _historico: qué importación cargó el precio.
-- Sin páginas ni funciones nuevas: se ve en la solapa "Importaciones" de la misma página.
-- Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;

-- 1. Tablas ---------------------------------------------------------------------------------------------
-- estado: EN_CURSO mientras el navegador manda los lotes; FINALIZADA; CON_ERRORES (filas con error o lotes
-- que no llegaron). Una EN_CURSO vieja es una importación interrumpida (se cerró la pantalla).
CREATE TABLE IF NOT EXISTS gestion__proveedores_precios_importaciones (
    importacion_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id            INT UNSIGNED NOT NULL,
    entidad_id            INT UNSIGNED NOT NULL,
    archivo_nombre        VARCHAR(255) NOT NULL DEFAULT '',
    archivo_ruta          VARCHAR(255) NULL,
    archivo_bytes         INT UNSIGNED NOT NULL DEFAULT 0,
    archivo_sha256        CHAR(64) NULL,
    filas_archivo         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Filas con algún dato (sin contar el encabezado)',
    nuevos                INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Productos con precio cargado por primera vez',
    actualizados          INT UNSIGNED NOT NULL DEFAULT 0,
    sin_cambios           INT UNSIGNED NOT NULL DEFAULT 0,
    no_vinculados         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Filas con un código que no está vinculado a ningún producto',
    filas_error           INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Sin código, precio inválido, código repetido o error al grabar',
    sin_fecha             INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Filas sin fecha de vigencia válida (se usó la de hoy)',
    faltantes             INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Productos vinculados que no vinieron en el archivo',
    lotes_fallidos        INT UNSIGNED NOT NULL DEFAULT 0,
    estado                ENUM('EN_CURSO','FINALIZADA','CON_ERRORES') NOT NULL DEFAULT 'EN_CURSO',
    usuario_id            INT UNSIGNED NOT NULL DEFAULT 0,
    fecha_inicio          DATETIME NOT NULL,
    fecha_fin             DATETIME NULL,
    PRIMARY KEY (importacion_id),
    KEY idx_ppi_empresa_entidad (empresa_id, entidad_id, fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gestion__proveedores_precios_importaciones_detalles (
    detalle_id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    importacion_id        INT UNSIGNED NOT NULL,
    empresa_id            INT UNSIGNED NOT NULL,
    fila_nro              INT UNSIGNED NULL COMMENT 'Fila de la hoja (la 1 es el encabezado); NULL en FALTANTE',
    codigo_proveedor      VARCHAR(100) NOT NULL DEFAULT '',
    descripcion           VARCHAR(255) NOT NULL DEFAULT '',
    precio_texto          VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'El precio tal como vino en el archivo',
    precio                DECIMAL(14,4) NULL,
    precio_anterior       DECIMAL(14,4) NULL,
    f_vigencia_desde      DATE NULL,
    producto_proveedor_id BIGINT UNSIGNED NULL,
    producto_id           INT UNSIGNED NULL,
    resultado             ENUM('NUEVO','ACTUALIZADO','SIN_CAMBIOS','NO_VINCULADO','SIN_CODIGO','PRECIO_INVALIDO','DUPLICADO','ERROR','FALTANTE') NOT NULL,
    mensaje               VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (detalle_id),
    KEY idx_ppid_importacion (importacion_id, resultado),
    KEY idx_ppid_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Qué importación cargó cada precio --------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores_precios' AND COLUMN_NAME = 'importacion_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__productos_proveedores_precios ADD COLUMN importacion_id INT UNSIGNED NULL COMMENT ''gestion__proveedores_precios_importaciones (origen IMPORTACION)'' AFTER archivo_importacion',
    'SELECT ''gestion__productos_proveedores_precios.importacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores_precios_historico' AND COLUMN_NAME = 'importacion_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__productos_proveedores_precios_historico ADD COLUMN importacion_id INT UNSIGNED NULL COMMENT ''gestion__proveedores_precios_importaciones (origen IMPORTACION)'' AFTER archivo_importacion',
    'SELECT ''gestion__productos_proveedores_precios_historico.importacion_id ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
