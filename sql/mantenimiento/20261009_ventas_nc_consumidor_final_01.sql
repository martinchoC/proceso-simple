-- =====================================================================
-- NC a consumidor final: devolución automática en efectivo
-- 2026-10-09
--
-- Decisión (Pablo, 2026-10-09): una nota de crédito a consumidor final
-- (devolución en mostrador) devuelve el dinero en efectivo automáticamente al
-- confirmarse: vfPostConfirmacion() (ventas_facturas_model.php) emite una OP
-- desde la caja de la sucursal imputada a la NC, que queda cancelada.
-- Esa OP no tiene entidad: gestion__pagos.entidad_id pasa a NULL (la FK
-- fk_pag_entidad_id admite NULL), igual que el recibo de contado a consumidor
-- final en gestion__cobranzas (20261009_notas_credito_aplicacion_01.sql).
--
-- Reanudable. MariaDB 10.4 / MySQL 8.0.
-- =====================================================================

ALTER TABLE `gestion__pagos` MODIFY `entidad_id` INT(10) UNSIGNED NULL DEFAULT NULL;

-- Verificación
SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'entidad_id'
  AND TABLE_NAME IN ('gestion__pagos', 'gestion__cobranzas');
