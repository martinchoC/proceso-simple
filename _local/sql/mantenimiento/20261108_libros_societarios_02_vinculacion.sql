-- Libros societarios compartidos entre el estudio y la empresa del cliente (requiere 20261108_libros_societarios_01.sql).
-- 1. conf__empresas_vinculaciones.permiso_societario: la empresa cliente autoriza al estudio (destino) a ver sus libros
--    societarios (LECTURA) o a cargarlos y modificarlos (LECTURA_ESCRITURA). Por defecto 0: ninguna vinculación existente
--    lo concede hasta que la empresa lo marque en su página de Vinculaciones.
-- 2. gestion__soc_libros / gestion__soc_actas.cargado_por_empresa_id: empresa que cargó el registro cuando no es la dueña
--    (el estudio); NULL = lo cargó la propia empresa.
-- Reanudable (ADD COLUMN solo si falta). Corre en MariaDB 10.4 y MySQL 8.
SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas_vinculaciones' AND COLUMN_NAME = 'permiso_societario');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas_vinculaciones ADD COLUMN permiso_societario tinyint(1) NOT NULL DEFAULT 0 COMMENT ''Libros societarios y actas'' AFTER permiso_impuestos',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__soc_libros' AND COLUMN_NAME = 'cargado_por_empresa_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__soc_libros ADD COLUMN cargado_por_empresa_id int(10) unsigned DEFAULT NULL COMMENT ''Empresa que lo cargó si no es la dueña (estudio)'' AFTER usuario_id',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__soc_actas' AND COLUMN_NAME = 'cargado_por_empresa_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__soc_actas ADD COLUMN cargado_por_empresa_id int(10) unsigned DEFAULT NULL COMMENT ''Empresa que la cargó si no es la dueña (estudio)'' AFTER usuario_id',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
