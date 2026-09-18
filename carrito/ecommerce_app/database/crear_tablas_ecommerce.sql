/* ===================================================================
   PS ECOMMERCE B2B - TABLAS PROPIAS DE LA TIENDA
   ===================================================================
   Crea las 4 tablas que la aplicacion necesita para funcionar.

   ESTE SCRIPT NO TOCA EL ERP:
     - No hace ALTER sobre ninguna tabla gestion__ ni conf__
     - No inserta ni actualiza ninguna fila del ERP
     - No crea claves foraneas hacia tablas del ERP
     - Solo hace CREATE TABLE IF NOT EXISTS de tablas ecom__

   Todo lo que la aplicacion LEE del ERP (entidades, productos, precios,
   tipos de cliente, usuarios, perfiles) se lee tal como esta definido.
   Estas 4 tablas son almacenamiento propio del storefront: carrito,
   intentos de login y el vinculo con el pedido generado.

   Se puede ejecutar mas de una vez sin efecto.
   =================================================================== */

SET NAMES utf8mb4;

/* Intentos de login: anti fuerza bruta y auditoria de accesos.
   Es la tabla que falta y produce el error 500 en /login.           */

CREATE TABLE IF NOT EXISTS `ecom__login_intentos` (
    `login_intento_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario`          VARCHAR(20)  NOT NULL,
    `ip`               VARCHAR(45)  NOT NULL,
    `exito`            TINYINT(1)   NOT NULL DEFAULT 0,
    `user_agent`       VARCHAR(255) NULL,
    `creado_en`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`login_intento_id`),
    KEY `idx_login_intentos_usuario_fecha` (`usuario`, `creado_en`),
    KEY `idx_login_intentos_ip_fecha` (`ip`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Carrito del lado del servidor. Nunca guarda precios: se recalculan
   contra las listas del ERP en cada lectura.                         */

CREATE TABLE IF NOT EXISTS `ecom__carritos` (
    `carrito_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id`      INT UNSIGNED NOT NULL,
    `usuario_id`      INT          NOT NULL,
    `entidad_id`      INT UNSIGNED NOT NULL,
    `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`carrito_id`),
    UNIQUE KEY `uk_ecom_carrito_usuario` (`empresa_id`, `usuario_id`),
    KEY `idx_ecom_carritos_entidad` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecom__carritos_items` (
    `carrito_item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `carrito_id`      INT UNSIGNED    NOT NULL,
    `producto_id`     INT UNSIGNED    NOT NULL,
    `cantidad`        DECIMAL(15,4)   NOT NULL,
    `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`carrito_item_id`),
    UNIQUE KEY `uk_ecom_carrito_producto` (`carrito_id`, `producto_id`),
    KEY `idx_ecom_carritos_items_producto` (`producto_id`),
    CONSTRAINT `fk_ecom_carritos_items_carrito`
        FOREIGN KEY (`carrito_id`) REFERENCES `ecom__carritos` (`carrito_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Trazabilidad: que pedido de compra genero cada operacion de la tienda.
   Sin FK hacia gestion__compras_pedidos, para no acoplar el ERP.      */

CREATE TABLE IF NOT EXISTS `ecom__pedidos` (
    `ecom_pedido_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `compra_pedido_id` INT UNSIGNED NOT NULL,
    `empresa_id`       INT UNSIGNED NOT NULL,
    `usuario_id`       INT          NOT NULL,
    `entidad_id`       INT UNSIGNED NOT NULL,
    `origen`           VARCHAR(30)  NOT NULL DEFAULT 'ecommerce',
    `creado_en`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ecom_pedido_id`),
    UNIQUE KEY `uk_ecom_pedidos_compra_pedido` (`compra_pedido_id`),
    KEY `idx_ecom_pedidos_entidad` (`empresa_id`, `entidad_id`, `compra_pedido_id`),
    KEY `idx_ecom_pedidos_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ========== VERIFICACION ========================================= */

SELECT 'Tablas de la tienda' AS verificacion,
       t.esperada AS tabla,
       IF((SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = t.esperada) = 1,
          'creada', 'FALTA') AS estado
  FROM (SELECT 'ecom__login_intentos' AS esperada
        UNION ALL SELECT 'ecom__carritos'
        UNION ALL SELECT 'ecom__carritos_items'
        UNION ALL SELECT 'ecom__pedidos') t;
