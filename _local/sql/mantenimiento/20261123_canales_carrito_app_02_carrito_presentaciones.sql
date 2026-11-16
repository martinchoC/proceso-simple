-- Canales (doc 28, paso 9-5): ítems del carrito del cliente en una presentación (caja, pack), en una tabla aparte de ecom__carritos_items.
-- -----------------------------------------------------------------------------------------------------------------
-- 20261123_canales_carrito_app_02_carrito_presentaciones.sql
-- Diseño en _local/docs/gestion/28_canales_carrito_app.md.
--
-- ecom__carritos_items tiene UNIQUE (carrito_id, producto_id): no admite cajas y unidades del mismo producto en
-- líneas separadas, y cambiar esa clave no es "solo agregar". Con usa_carrito_integrado, los ítems en presentación van
-- a ecom__carritos_presentaciones (una fila por carrito + producto + presentación, cantidad en presentaciones); los
-- ítems por unidad siguen en ecom__carritos_items como siempre. Apagado, nadie lee ni escribe esta tabla.
--
-- Solo crea una tabla. Reanudable (CREATE TABLE IF NOT EXISTS). Sin FK a ecom__carritos para no depender de que el
-- carrito esté instalado en la base: si ecom__carritos no existe, la tabla queda sin usar.
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ecom__carritos_presentaciones (
    carrito_presentacion_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    carrito_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    presentacion_id INT UNSIGNED NOT NULL COMMENT 'gestion__productos_presentaciones',
    cantidad DECIMAL(15,4) NOT NULL COMMENT 'En presentaciones (cajas)',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (carrito_presentacion_id),
    UNIQUE KEY uk_ecom_carrito_pres (carrito_id, producto_id, presentacion_id),
    KEY idx_ecom_carrito_pres_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ítems del carrito en una presentación (doc 28; solo con usa_carrito_integrado)';
