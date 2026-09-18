SET NAMES utf8mb4;
ALTER TABLE `conf__paginas_funciones`
    ADD COLUMN `codigo_funcion` VARCHAR(60) NULL DEFAULT NULL
        COMMENT 'Código estable usado por las aplicaciones (ej: ecom.catalogo.ver)'
        AFTER `nombre_funcion`;
ALTER TABLE `conf__paginas_funciones`
    ADD UNIQUE KEY `uk_paginas_funciones_codigo` (`codigo_funcion`);
CREATE TABLE IF NOT EXISTS `ecom__usuarios_entidades` (
    `usuario_entidad_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id`               INT UNSIGNED NOT NULL,
    `usuario_id`               INT          NOT NULL,
    `entidad_id`               INT UNSIGNED NOT NULL,
    `tabla_estado_registro_id` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `creado_por`               INT UNSIGNED NULL,
    PRIMARY KEY (`usuario_entidad_id`),
    UNIQUE KEY `uk_ecom_usuario_empresa` (`empresa_id`, `usuario_id`),
    KEY `idx_ecom_usuarios_entidades_entidad` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE IF NOT EXISTS `ecom__pedidos` (
    `ecom_pedido_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `venta_pedido_id` INT UNSIGNED NOT NULL,
    `empresa_id`      INT UNSIGNED NOT NULL,
    `usuario_id`      INT          NOT NULL,
    `entidad_id`      INT UNSIGNED NOT NULL,
    `origen`          VARCHAR(30)  NOT NULL DEFAULT 'ecommerce',
    `creado_en`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ecom_pedido_id`),
    UNIQUE KEY `uk_ecom_pedidos_venta_pedido` (`venta_pedido_id`),
    KEY `idx_ecom_pedidos_entidad` (`empresa_id`, `entidad_id`, `venta_pedido_id`),
    KEY `idx_ecom_pedidos_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET @EMPRESA_ID := 0;   -- 0 = crear el perfil en todas las empresas activas
INSERT INTO `conf__modulos` (`modulo`, `modulo_url`, `layout_nombre`, `tabla_estado_registro_id`)
SELECT 'Ecommerce B2B', '/', 'default', 1
 WHERE NOT EXISTS (SELECT 1 FROM `conf__modulos` WHERE `modulo` = 'Ecommerce B2B');
SET @MODULO_ID := (SELECT `modulo_id` FROM `conf__modulos` WHERE `modulo` = 'Ecommerce B2B' LIMIT 1);
INSERT INTO `conf__paginas` (`modulo_id`, `pagina`, `url`, `pagina_descripcion`, `orden`, `tabla_estado_registro_id`)
SELECT @MODULO_ID, 'Catálogo', '/catalogo', 'Catálogo de productos del storefront', 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM `conf__paginas` WHERE `modulo_id` = @MODULO_ID AND `url` = '/catalogo');
INSERT INTO `conf__paginas` (`modulo_id`, `pagina`, `url`, `pagina_descripcion`, `orden`, `tabla_estado_registro_id`)
SELECT @MODULO_ID, 'Carrito', '/carrito', 'Carrito de compras', 2, 1
 WHERE NOT EXISTS (SELECT 1 FROM `conf__paginas` WHERE `modulo_id` = @MODULO_ID AND `url` = '/carrito');
INSERT INTO `conf__paginas` (`modulo_id`, `pagina`, `url`, `pagina_descripcion`, `orden`, `tabla_estado_registro_id`)
SELECT @MODULO_ID, 'Pedidos', '/pedidos', 'Pedidos del cliente', 3, 1
 WHERE NOT EXISTS (SELECT 1 FROM `conf__paginas` WHERE `modulo_id` = @MODULO_ID AND `url` = '/pedidos');
SET @PAG_CATALOGO := (SELECT `pagina_id` FROM `conf__paginas` WHERE `modulo_id` = @MODULO_ID AND `url` = '/catalogo' LIMIT 1);
SET @PAG_CARRITO  := (SELECT `pagina_id` FROM `conf__paginas` WHERE `modulo_id` = @MODULO_ID AND `url` = '/carrito'  LIMIT 1);
SET @PAG_PEDIDOS  := (SELECT `pagina_id` FROM `conf__paginas` WHERE `modulo_id` = @MODULO_ID AND `url` = '/pedidos'  LIMIT 1);
INSERT INTO `conf__paginas_funciones`
    (`pagina_id`, `nombre_funcion`, `codigo_funcion`,
     `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
SELECT * FROM (
    SELECT @PAG_CATALOGO AS p, 'Ver catálogo'      AS n, 'ecom.catalogo.ver'      AS c, 1 AS o, 1 AS d, 1 AS ord, 1 AS e UNION ALL
    SELECT @PAG_CARRITO,       'Gestionar carrito',      'ecom.carrito.gestionar',      1,      1,      2,      1 UNION ALL
    SELECT @PAG_PEDIDOS,       'Crear pedido',           'ecom.pedido.crear',           1,      1,      3,      1 UNION ALL
    SELECT @PAG_PEDIDOS,       'Ver pedidos',            'ecom.pedido.ver',             1,      1,      4,      1
) AS nuevas
 WHERE NOT EXISTS (SELECT 1 FROM `conf__paginas_funciones` pf WHERE pf.`codigo_funcion` = nuevas.c);
INSERT INTO `conf__perfiles` (`modulo_id`, `perfil_nombre`, `tabla_estado_registro_id`)
SELECT @MODULO_ID, 'Cliente Web', 1
 WHERE NOT EXISTS (SELECT 1 FROM `conf__perfiles` WHERE `modulo_id` = @MODULO_ID AND `perfil_nombre` = 'Cliente Web');
SET @PERFIL_BASE_ID := (SELECT `perfil_id` FROM `conf__perfiles`
                         WHERE `modulo_id` = @MODULO_ID AND `perfil_nombre` = 'Cliente Web' LIMIT 1);
INSERT INTO `conf__empresas_perfiles` (`empresa_id`, `modulo_id`, `perfil_id_base`, `empresa_perfil_nombre`, `tabla_estado_registro_id`)
SELECT emp.`empresa_id`, @MODULO_ID, @PERFIL_BASE_ID, 'Cliente Web', 1
  FROM `conf__empresas` emp
 WHERE emp.`tabla_estado_registro_id` = 1
   AND (@EMPRESA_ID = 0 OR emp.`empresa_id` = @EMPRESA_ID)
   AND NOT EXISTS (
        SELECT 1 FROM `conf__empresas_perfiles` ep
         WHERE ep.`empresa_id` = emp.`empresa_id`
           AND ep.`modulo_id` = @MODULO_ID
           AND ep.`empresa_perfil_nombre` = 'Cliente Web'
   );
INSERT INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT ep.`empresa_id`, ep.`empresa_perfil_id`, pf.`pagina_funcion_id`, 1
  FROM `conf__empresas_perfiles` ep
 CROSS JOIN `conf__paginas_funciones` pf
 WHERE ep.`modulo_id` = @MODULO_ID
   AND ep.`empresa_perfil_nombre` = 'Cliente Web'
   AND ep.`tabla_estado_registro_id` = 1
   AND pf.`codigo_funcion` IN ('ecom.catalogo.ver', 'ecom.carrito.gestionar', 'ecom.pedido.crear', 'ecom.pedido.ver')
   AND NOT EXISTS (
        SELECT 1 FROM `conf__empresas_perfiles_funciones` epf
         WHERE epf.`empresa_perfil_id` = ep.`empresa_perfil_id`
           AND epf.`pagina_funcion_id` = pf.`pagina_funcion_id`
   );
ALTER TABLE `gestion__productos`
    ADD KEY `idx_productos_empresa_estado_nombre` (`empresa_id`, `tabla_estado_registro_id`, `producto_nombre`);
ALTER TABLE `gestion__productos`
    ADD KEY `idx_productos_categoria` (`producto_categoria_id`);
ALTER TABLE `gestion__productos_imagenes`
    ADD KEY `idx_productos_imagenes_producto` (`producto_id`, `empresa_id`, `es_principal`, `orden`);
ALTER TABLE `gestion__productos_compatibilidad`
    ADD KEY `idx_compatibilidad_marca` (`marca_id`, `producto_id`);
ALTER TABLE `gestion__productos_compatibilidad`
    ADD KEY `idx_compatibilidad_modelo` (`modelo_id`, `producto_id`);
ALTER TABLE `conf__usuarios_perfiles`
    ADD KEY `idx_usuarios_perfiles_usuario_vigencia` (`usuario_id`, `fecha_inicio`, `fecha_fin`);
ALTER TABLE `conf__usuarios_perfiles`
    ADD KEY `idx_usuarios_perfiles_empresa_perfil` (`empresa_perfil_id`);
ALTER TABLE `conf__empresas_perfiles_funciones`
    ADD KEY `idx_empresas_perfiles_funciones_perfil` (`empresa_perfil_id`, `asignado`, `pagina_funcion_id`);
ALTER TABLE `conf__paginas_funciones`
    ADD KEY `idx_paginas_funciones_pagina` (`pagina_id`);
SELECT (SELECT COUNT(*) FROM conf__paginas_funciones WHERE codigo_funcion LIKE 'ecom.%') AS funciones_ecom,
       (SELECT COUNT(*) FROM conf__empresas_perfiles
         WHERE modulo_id = @MODULO_ID AND empresa_perfil_nombre = 'Cliente Web')          AS perfiles_cliente_web,
       @MODULO_ID                                                                         AS modulo_id;
