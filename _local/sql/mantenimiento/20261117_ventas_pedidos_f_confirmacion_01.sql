-- Hora exacta de confirmación del pedido de venta (cuando pasa de Borrador a Confirmado o entra confirmado del carrito)
-- ---------------------------------------------------------------------------------------------------------------------
-- 20261117_ventas_pedidos_f_confirmacion_01.sql
-- 1. Agrega gestion__ventas_pedidos.f_confirmacion DATETIME NULL DEFAULT NULL (después de f_emision).
-- 2. Completa los pedidos ya confirmados históricos con la fecha/hora de ecom__pedidos.creado_en o
--    gestion__comprobantes.fecha_creacion.
-- Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0).
-- ---------------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'gestion__ventas_pedidos'
                  AND COLUMN_NAME = 'f_confirmacion');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__ventas_pedidos` ADD COLUMN `f_confirmacion` DATETIME DEFAULT NULL COMMENT ''Fecha y hora exacta en que el pedido pasó a Confirmado'' AFTER `f_emision`',
    'SELECT ''gestion__ventas_pedidos.f_confirmacion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Backfill de pedidos históricos ya confirmados (estado >= 5, excluyendo 3 Borrador, 4 Pend. Aprobación y 6 Eliminado)
UPDATE gestion__ventas_pedidos vp
LEFT JOIN gestion__comprobantes c ON c.comprobante_id = vp.comprobante_id
SET vp.f_confirmacion = COALESCE(c.fecha_creacion, CONCAT(vp.f_emision, ' 00:00:00'))
WHERE vp.f_confirmacion IS NULL
  AND vp.tabla_estado_registro_id NOT IN (3, 4, 6);
