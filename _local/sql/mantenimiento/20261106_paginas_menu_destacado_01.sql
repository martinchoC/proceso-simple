-- =====================================================================================================
-- Páginas destacadas en el menú lateral (conf/paginas.php, se pinta en templates/adminlte/header1.php)
-- -----------------------------------------------------------------------------------------------------
-- conf__paginas.menu_estilo     '' = normal | 'RESALTADO' = fondo suave + borde izquierdo + negrita | 'BORDE' = solo borde
-- conf__paginas.menu_color_id   color del catálogo conf__colores (se usa el color de Bootstrap de su color_clase:
--                               btn-success -> success). NULL = Primario.
-- conf__paginas.menu_etiqueta   texto corto opcional que se muestra como etiqueta a la derecha ("Diario", "Nuevo").
-- La etiqueta se muestra aunque el estilo sea normal.
-- Además destaca Nuevos Pedidos de Clientes y Remitos Pendientes Facturación (módulos 2 y 5).
-- DDL no transaccional: cada columna se agrega solo si falta (se puede reanudar). MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__paginas' AND COLUMN_NAME = 'menu_estilo');
SET @sql := IF(@col = 0,
    "ALTER TABLE conf__paginas ADD COLUMN menu_estilo VARCHAR(12) NOT NULL DEFAULT '' COMMENT ''''' normal, RESALTADO, BORDE' AFTER orden_acceso_directo",
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__paginas' AND COLUMN_NAME = 'menu_color_id');
SET @sql := IF(@col = 0,
    "ALTER TABLE conf__paginas ADD COLUMN menu_color_id INT NULL COMMENT 'conf__colores.color_id del destacado' AFTER menu_estilo",
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__paginas' AND COLUMN_NAME = 'menu_etiqueta');
SET @sql := IF(@col = 0,
    "ALTER TABLE conf__paginas ADD COLUMN menu_etiqueta VARCHAR(15) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Etiqueta corta en el menú' AFTER menu_color_id",
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Destacadas iniciales: color Éxito (btn-success) del catálogo, resuelto por clase y no por id
UPDATE conf__paginas
SET menu_estilo = 'RESALTADO',
    menu_color_id = (SELECT c.color_id FROM conf__colores c WHERE c.color_clase = 'btn-success' ORDER BY c.color_id LIMIT 1)
WHERE modulo_id IN (2, 5)
  AND url IN ('ventas_pedidos_gestion.php', 'ventas_remitos_pendientes_facturacion.php')
  AND tabla_estado_registro_id = 1
  AND menu_estilo = '';

-- Control (solo lectura)
SELECT p.pagina_id, p.modulo_id, p.pagina, p.url, p.menu_estilo, c.nombre_color, p.menu_etiqueta
FROM conf__paginas p
LEFT JOIN conf__colores c ON c.color_id = p.menu_color_id
WHERE p.menu_estilo <> '' OR p.menu_etiqueta IS NOT NULL
ORDER BY p.modulo_id, p.orden;
