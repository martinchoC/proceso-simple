SET @EMPRESA_ID := 2;

ALTER TABLE `ecom__pedidos`
    CHANGE `venta_pedido_id` `compra_pedido_id` INT UNSIGNED NOT NULL;

ALTER TABLE `ecom__pedidos`
    DROP INDEX `uk_ecom_pedidos_venta_pedido`,
    ADD UNIQUE KEY `uk_ecom_pedidos_compra_pedido` (`compra_pedido_id`);

ALTER TABLE `ecom__pedidos`
    DROP INDEX `idx_ecom_pedidos_entidad`,
    ADD KEY `idx_ecom_pedidos_entidad` (`empresa_id`, `entidad_id`, `compra_pedido_id`);

INSERT INTO `conf__tablas` (`tabla_nombre`, `tabla_descripcion`, `tabla_estado_registro_id`)
SELECT 'gestion__compras_pedidos', 'Pedidos de compra', 1
 WHERE NOT EXISTS (SELECT 1 FROM `conf__tablas` WHERE `tabla_nombre` = 'gestion__compras_pedidos');

CREATE INDEX `idx_esc_entidad_vigencia`
    ON `gestion__entidades_sucursales_compra` (`empresa_id`, `entidad_id`, `f_desde`, `f_hasta`);

CREATE INDEX `idx_compras_pedidos_entidad`
    ON `gestion__compras_pedidos` (`empresa_id`, `entidad_id`, `compra_pedido_id`);

SELECT 'A. Tabla origen del comprobante' AS paso,
       (SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__compras_pedidos') AS tabla_origen_pedidos_id,
       'Si el .env tiene ECOM_TABLA_ORIGEN_PEDIDOS_ID, actualizalo con este numero' AS accion;

SELECT 'B. Sucursales de compra cargadas' AS paso,
       esc.entidad_sucursal_compra_id, esc.empresa_id, esc.entidad_id, e.entidad_nombre,
       esc.sucursal_id, s.sucursal_nombre, esc.es_principal,
       esc.f_desde, esc.f_hasta, esc.tabla_estado_registro_id AS estado
  FROM gestion__entidades_sucursales_compra esc
  LEFT JOIN gestion__entidades e ON e.entidad_id = esc.entidad_id
  LEFT JOIN gestion__sucursales s ON s.sucursal_id = esc.sucursal_id
 WHERE esc.empresa_id = @EMPRESA_ID
 ORDER BY esc.entidad_id, esc.es_principal DESC;

SELECT 'C. Clientes SIN sucursal de compra vigente' AS paso,
       e.entidad_id, e.entidad_nombre
  FROM gestion__entidades e
 INNER JOIN ecom__usuarios_entidades ue
         ON ue.entidad_id = e.entidad_id
        AND ue.empresa_id = e.empresa_id
        AND ue.tabla_estado_registro_id = 1
 WHERE e.empresa_id = @EMPRESA_ID
   AND NOT EXISTS (
        SELECT 1 FROM gestion__entidades_sucursales_compra esc
         WHERE esc.entidad_id = e.entidad_id
           AND esc.empresa_id = e.empresa_id
           AND esc.tabla_estado_registro_id = 1
           AND esc.f_desde <= CURDATE()
           AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE())
   );
