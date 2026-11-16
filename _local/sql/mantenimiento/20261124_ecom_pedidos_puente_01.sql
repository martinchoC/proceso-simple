-- Carrito: recupera el puente ecom__pedidos de los pedidos de la tienda que lo perdieron (compra_pedido_id UNIQUE = 0).
-- -----------------------------------------------------------------------------------------------------------------
-- 20261124_ecom_pedidos_puente_01.sql
-- En las bases instaladas con migracion_produccion_completa.sql del carrito, ecom__pedidos.compra_pedido_id es NOT NULL
-- y UNIQUE y la tienda no lo completaba (quedaba 0): solo entraba la primera fila y los pedidos siguientes perdían el
-- puente en silencio. El carrito (casalucho-carrito) ahora lo completa con el id del pedido de venta. Este script
-- agrega el puente de los pedidos que quedaron sin él, tomándolos de ecom__pedidos_margenes (la tienda graba ahí cada
-- pedido que confirma), con compra_pedido_id = venta_pedido_id.
--
-- Solo agrega filas en ecom__pedidos. Reanudable (NOT EXISTS). Sin las tablas del carrito no hace nada.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @hay := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('ecom__pedidos', 'ecom__pedidos_margenes'));
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ecom__pedidos' AND COLUMN_NAME IN ('venta_pedido_id', 'compra_pedido_id'));
SET @sql := IF(@hay = 2 AND @col = 2,
    'INSERT INTO ecom__pedidos (venta_pedido_id, compra_pedido_id, empresa_id, usuario_id, entidad_id, origen)
     SELECT m.pedido_id, m.pedido_id, m.empresa_id, m.usuario_id, m.entidad_id, ''ecommerce''
       FROM ecom__pedidos_margenes m
       JOIN gestion__ventas_pedidos v ON v.venta_pedido_id = m.pedido_id
      WHERE NOT EXISTS (SELECT 1 FROM ecom__pedidos p WHERE p.venta_pedido_id = m.pedido_id)
        AND NOT EXISTS (SELECT 1 FROM ecom__pedidos p2 WHERE p2.compra_pedido_id = m.pedido_id)',
    'SELECT ''sin las tablas del carrito: nada que recuperar''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
