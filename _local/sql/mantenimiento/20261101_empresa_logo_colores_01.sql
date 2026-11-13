-- ==============================================================================
-- Logo de la empresa y colores del encabezado (2026-11-01)
-- conf__empresas guarda el logo ACTUAL de la empresa y los dos colores que se sacan de él:
--   logo_archivo        ruta relativa (uploads/empresas/...), la misma que usa Parámetros de Empresa
--   color_logo_oscuro   #rrggbb, inicio del degradé del encabezado de los modales
--   color_logo_claro    #rrggbb, fin del degradé
-- Se carga desde Empresas (conf, página 27) o desde Parámetros de Empresa (111 / 611): las dos
-- escriben conf__empresas y la vigencia abierta de gestion__empresas_parametros (core/empresa_logo.php).
-- La impresión sigue leyendo el logo de la vigencia de parámetros de la fecha del comprobante.
-- Los colores los calcula PHP (GD): este script solo copia el logo de la vigencia abierta, y
-- header1.php calcula y guarda los colores la primera vez que se abre una página de la empresa.
-- Reanudable: cada ALTER se salta si la columna ya existe. Corre en MariaDB 10.4 y MySQL 8.
-- ==============================================================================

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'logo_archivo');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN logo_archivo VARCHAR(255) NULL COMMENT ''Logo actual de la empresa (uploads/empresas/...); lo sincroniza core/empresa_logo.php'' AFTER separador_decimal',
    'SELECT ''conf__empresas.logo_archivo ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'color_logo_oscuro');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN color_logo_oscuro VARCHAR(7) NULL COMMENT ''Color oscuro sacado del logo (#rrggbb), encabezado de los modales'' AFTER logo_archivo',
    'SELECT ''conf__empresas.color_logo_oscuro ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'color_logo_claro');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN color_logo_claro VARCHAR(7) NULL COMMENT ''Color claro sacado del logo (#rrggbb), encabezado de los modales'' AFTER color_logo_oscuro',
    'SELECT ''conf__empresas.color_logo_claro ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Logo actual = el de la vigencia abierta de parámetros (o la última con logo). Solo donde todavía no hay.
UPDATE conf__empresas e
JOIN (
    SELECT ep.empresa_id, ep.logo_archivo
    FROM gestion__empresas_parametros ep
    WHERE ep.tabla_estado_registro_id = 1 AND ep.logo_archivo IS NOT NULL AND ep.logo_archivo <> ''
      AND ep.empresa_parametro_id = (
          SELECT ep2.empresa_parametro_id FROM gestion__empresas_parametros ep2
          WHERE ep2.empresa_id = ep.empresa_id AND ep2.tabla_estado_registro_id = 1
            AND ep2.logo_archivo IS NOT NULL AND ep2.logo_archivo <> ''
          ORDER BY (ep2.f_hasta IS NULL) DESC, ep2.f_desde DESC, ep2.empresa_parametro_id DESC LIMIT 1)
) x ON x.empresa_id = e.empresa_id
SET e.logo_archivo = x.logo_archivo, e.color_logo_oscuro = NULL, e.color_logo_claro = NULL
WHERE e.logo_archivo IS NULL OR e.logo_archivo = '';

SELECT empresa_id, empresa, logo_archivo, color_logo_oscuro, color_logo_claro FROM conf__empresas ORDER BY empresa_id;
