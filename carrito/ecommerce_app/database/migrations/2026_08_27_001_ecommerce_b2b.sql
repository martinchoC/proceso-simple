-- =============================================================================
--  Storefront B2B — migración para base COMPARTIDA
--  Fecha: 2026-08-27
--
--  Esta base la usan también el sistema de usuarios/perfiles/módulos/empresas
--  y el ERP. Por eso la migración es:
--
--    * ADITIVA      → no borra ni modifica datos existentes
--    * IDEMPOTENTE  → se puede volver a ejecutar sin errores
--    * SIN DROP     → ninguna tabla existente se recrea
--
--  Qué agrega:
--    1. conf__paginas_funciones.codigo_funcion (columna nueva, NULL)
--    2. Tablas nuevas con prefijo ecom__
--    3. Índices de apoyo (sólo si faltan)
--    4. Módulo "Ecommerce B2B" + páginas + funciones + perfil "Cliente Web"
--
--  Cómo ejecutarla en Hostinger:
--    hPanel → Bases de datos → phpMyAdmin → pestaña "Importar" → elegir este
--    archivo → Continuar.  (Hacer backup antes: hPanel → Copias de seguridad.)
--
--  El módulo queda visible en el sistema de administración, así que los
--  permisos del ecommerce se gestionan desde ahí como los de cualquier módulo.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Utilidades idempotentes (se eliminan al final)
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS ecom_add_column;
DROP PROCEDURE IF EXISTS ecom_add_index;

DELIMITER $$

CREATE PROCEDURE ecom_add_column(
    IN p_tabla VARCHAR(64),
    IN p_columna VARCHAR(64),
    IN p_definicion TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = p_tabla
           AND column_name = p_columna
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_tabla, '` ADD COLUMN `', p_columna, '` ', p_definicion);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE ecom_add_index(
    IN p_tabla VARCHAR(64),
    IN p_indice VARCHAR(64),
    IN p_definicion TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = p_tabla
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = p_tabla
           AND index_name = p_indice
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_tabla, '` ADD ', p_definicion);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- -----------------------------------------------------------------------------
-- 1. Permisos por código estable
--    El modelo identifica funciones por ID autonumérico; hardcodearlos en la
--    aplicación sería frágil. La columna es NULL, así que el sistema de
--    administración existente no se ve afectado.
-- -----------------------------------------------------------------------------
CALL ecom_add_column(
    'conf__paginas_funciones',
    'codigo_funcion',
    "VARCHAR(60) NULL DEFAULT NULL COMMENT 'Código estable usado por las aplicaciones (ej: ecom.catalogo.ver)' AFTER `nombre_funcion`"
);
CALL ecom_add_index(
    'conf__paginas_funciones',
    'uk_paginas_funciones_codigo',
    'UNIQUE KEY `uk_paginas_funciones_codigo` (`codigo_funcion`)'
);

-- -----------------------------------------------------------------------------
-- 2. Tablas propias del storefront
-- -----------------------------------------------------------------------------

-- Puente usuario -> entidad cliente. Sin esta fila, el usuario no puede entrar
-- a la tienda: es lo que define su lista de precios y a quién se le factura.
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

-- Auditoría de accesos: alimenta el bloqueo por fuerza bruta y sirve de forense.
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

-- Carrito en servidor. No guarda precios: se recalculan en cada lectura.
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

-- Puente pedido ERP <-> storefront: quién lo generó y desde dónde.
-- Evita agregar columnas a gestion__ventas_pedidos, que usa el ERP.
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

-- Nota sobre claves foráneas: las tablas ecom__ no declaran FK hacia
-- conf__usuarios / gestion__entidades / gestion__productos a propósito. En una
-- base compartida, una FK impediría que el otro sistema borre o reorganice sus
-- registros. La integridad se valida en la aplicación y con las UNIQUE de arriba.

-- -----------------------------------------------------------------------------
-- 3. Índices de apoyo (sólo si faltan)
--    Sin ellos, el catálogo hace full scan de gestion__productos y la
--    resolución de permisos recorre conf__usuarios_perfiles completa.
-- -----------------------------------------------------------------------------
CALL ecom_add_index('gestion__productos', 'idx_productos_empresa_estado_nombre',
    'KEY `idx_productos_empresa_estado_nombre` (`empresa_id`, `tabla_estado_registro_id`, `producto_nombre`)');
CALL ecom_add_index('gestion__productos', 'idx_productos_categoria',
    'KEY `idx_productos_categoria` (`producto_categoria_id`)');
CALL ecom_add_index('gestion__productos_imagenes', 'idx_productos_imagenes_producto',
    'KEY `idx_productos_imagenes_producto` (`producto_id`, `empresa_id`, `es_principal`, `orden`)');
CALL ecom_add_index('gestion__productos_compatibilidad', 'idx_compatibilidad_marca',
    'KEY `idx_compatibilidad_marca` (`marca_id`, `producto_id`)');
CALL ecom_add_index('gestion__productos_compatibilidad', 'idx_compatibilidad_modelo',
    'KEY `idx_compatibilidad_modelo` (`modelo_id`, `producto_id`)');
CALL ecom_add_index('conf__usuarios_perfiles', 'idx_usuarios_perfiles_usuario_vigencia',
    'KEY `idx_usuarios_perfiles_usuario_vigencia` (`usuario_id`, `fecha_inicio`, `fecha_fin`)');
CALL ecom_add_index('conf__usuarios_perfiles', 'idx_usuarios_perfiles_empresa_perfil',
    'KEY `idx_usuarios_perfiles_empresa_perfil` (`empresa_perfil_id`)');
CALL ecom_add_index('conf__empresas_perfiles_funciones', 'idx_empresas_perfiles_funciones_perfil',
    'KEY `idx_empresas_perfiles_funciones_perfil` (`empresa_perfil_id`, `asignado`, `pagina_funcion_id`)');
CALL ecom_add_index('conf__paginas_funciones', 'idx_paginas_funciones_pagina',
    'KEY `idx_paginas_funciones_pagina` (`pagina_id`)');

-- -----------------------------------------------------------------------------
-- 4. Módulo, páginas, funciones y perfil
--    @EMPRESA_ID: dejar en 0 para que se cree el perfil en TODAS las empresas
--    activas, o poner el ID si querés habilitar sólo una.
-- -----------------------------------------------------------------------------
SET @EMPRESA_ID := 0;

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

-- Perfil base del módulo
INSERT INTO `conf__perfiles` (`modulo_id`, `perfil_nombre`, `tabla_estado_registro_id`)
SELECT @MODULO_ID, 'Cliente Web', 1
 WHERE NOT EXISTS (SELECT 1 FROM `conf__perfiles` WHERE `modulo_id` = @MODULO_ID AND `perfil_nombre` = 'Cliente Web');

SET @PERFIL_BASE_ID := (SELECT `perfil_id` FROM `conf__perfiles`
                         WHERE `modulo_id` = @MODULO_ID AND `perfil_nombre` = 'Cliente Web' LIMIT 1);

-- Perfil "Cliente Web" por empresa
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

-- Asignación de las 4 funciones a cada perfil "Cliente Web"
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

-- -----------------------------------------------------------------------------
-- Limpieza
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS ecom_add_column;
DROP PROCEDURE IF EXISTS ecom_add_index;

-- -----------------------------------------------------------------------------
-- Resultado: revisar que devuelva 4 funciones y al menos 1 perfil
-- -----------------------------------------------------------------------------
SELECT (SELECT COUNT(*) FROM conf__paginas_funciones
         WHERE codigo_funcion LIKE 'ecom.%')                          AS funciones_ecom,
       (SELECT COUNT(*) FROM conf__empresas_perfiles
         WHERE modulo_id = @MODULO_ID AND empresa_perfil_nombre = 'Cliente Web') AS perfiles_cliente_web,
       @MODULO_ID                                                     AS modulo_id;
