/* ===================================================================
   PS ECOMMERCE B2B - MIGRACION COMPLETA PARA PRODUCCION
   ===================================================================
   Base destino: u368960646_gestion
   Aplicar UNA sola vez (pero es idempotente: se puede reejecutar).
   No usa DELIMITER ni stored procedures.
   No usa comentarios de linea "--" (sobrevive a pegados sin saltos).
   No borra ni modifica datos existentes del ERP.

   ANTES DE EJECUTAR: hacer backup en hPanel > Copias de seguridad.

   PASO 1: ajustar las tres variables del BLOQUE 0.
   PASO 2: ejecutar todo el archivo.
   PASO 3: revisar los SELECT de verificacion del final.
   =================================================================== */

/* ================= BLOQUE 0 - VARIABLES A EDITAR ================= */

SET NAMES utf8mb4;

SET @MODULO_ID  := 4;
SET @EMPRESA_ID := 2;
SET @USUARIO_ID := 0;
SET @VENCE      := '2030-12-31';

/* @MODULO_ID   modulo existente donde vive el ecommerce (conf__modulos)
   @EMPRESA_ID  empresa que usa la tienda (conf__empresas)
   @USUARIO_ID  usuario a habilitar como Cliente Web; 0 = no asignar ninguno
   @VENCE       vencimiento del perfil asignado a ese usuario           */

SELECT 'BLOQUE 0. Contexto' AS paso,
       @MODULO_ID  AS modulo_id,
       (SELECT modulo  FROM conf__modulos  WHERE modulo_id  = @MODULO_ID)  AS modulo,
       @EMPRESA_ID AS empresa_id,
       (SELECT empresa FROM conf__empresas WHERE empresa_id = @EMPRESA_ID) AS empresa,
       @USUARIO_ID AS usuario_id,
       (SELECT usuario FROM conf__usuarios WHERE usuario_id = @USUARIO_ID) AS usuario,
       IF((SELECT COUNT(*) FROM conf__modulos  WHERE modulo_id  = @MODULO_ID)  = 0,
          'ERROR: el modulo_id no existe, corregir @MODULO_ID',
          IF((SELECT COUNT(*) FROM conf__empresas WHERE empresa_id = @EMPRESA_ID) = 0,
             'ERROR: el empresa_id no existe, corregir @EMPRESA_ID',
             'OK')) AS validacion;

/* ========== BLOQUE 1 - COLUMNA codigo_funcion (unica alteracion
              sobre una tabla existente del ERP) ==================== */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name  = 'conf__paginas_funciones'
                   AND column_name = 'codigo_funcion') = 0,
  'ALTER TABLE `conf__paginas_funciones` ADD COLUMN `codigo_funcion` VARCHAR(60) NULL DEFAULT NULL AFTER `nombre_funcion`',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'conf__paginas_funciones'
                   AND index_name = 'uk_paginas_funciones_codigo') = 0,
  'ALTER TABLE `conf__paginas_funciones` ADD UNIQUE KEY `uk_paginas_funciones_codigo` (`codigo_funcion`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== BLOQUE 2 - TABLAS NUEVAS DEL STOREFRONT (ecom__) ====== */

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

/* Si ecom__pedidos ya existia con el nombre viejo de columna, se renombra */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name  = 'ecom__pedidos'
                   AND column_name = 'venta_pedido_id') = 1,
  'ALTER TABLE `ecom__pedidos` CHANGE `venta_pedido_id` `compra_pedido_id` INT UNSIGNED NOT NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'ecom__pedidos'
                   AND index_name = 'uk_ecom_pedidos_venta_pedido') > 0,
  'ALTER TABLE `ecom__pedidos` DROP INDEX `uk_ecom_pedidos_venta_pedido`',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'ecom__pedidos'
                   AND index_name = 'uk_ecom_pedidos_compra_pedido') = 0,
  'ALTER TABLE `ecom__pedidos` ADD UNIQUE KEY `uk_ecom_pedidos_compra_pedido` (`compra_pedido_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'ecom__pedidos'
                   AND index_name = 'idx_ecom_pedidos_entidad'
                   AND column_name = 'venta_pedido_id') > 0,
  'ALTER TABLE `ecom__pedidos` DROP INDEX `idx_ecom_pedidos_entidad`, ADD KEY `idx_ecom_pedidos_entidad` (`empresa_id`, `entidad_id`, `compra_pedido_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== BLOQUE 3 - PAGINAS, FUNCIONES Y PERFIL ================ */

INSERT INTO conf__paginas (modulo_id, pagina, url, pagina_descripcion, orden, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Catalogo', '/catalogo', 'Catalogo de productos del storefront', 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/catalogo');

INSERT INTO conf__paginas (modulo_id, pagina, url, pagina_descripcion, orden, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Carrito', '/carrito', 'Carrito de compras', 2, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/carrito');

INSERT INTO conf__paginas (modulo_id, pagina, url, pagina_descripcion, orden, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Pedidos', '/pedidos', 'Pedidos del cliente', 3, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/pedidos');

UPDATE conf__paginas SET tabla_estado_registro_id = 1
 WHERE modulo_id = @MODULO_ID AND url IN ('/catalogo', '/carrito', '/pedidos');

SET @PAG_CATALOGO := (SELECT pagina_id FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/catalogo' LIMIT 1);
SET @PAG_CARRITO  := (SELECT pagina_id FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/carrito'  LIMIT 1);
SET @PAG_PEDIDOS  := (SELECT pagina_id FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/pedidos'  LIMIT 1);

INSERT INTO conf__paginas_funciones
    (pagina_id, nombre_funcion, codigo_funcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT * FROM (
    SELECT @PAG_CATALOGO AS p, 'Ver catalogo'     AS n, 'ecom.catalogo.ver'     AS c, 1 AS o, 1 AS d, 1 AS ord, 1 AS e UNION ALL
    SELECT @PAG_CARRITO,       'Gestionar carrito',     'ecom.carrito.gestionar',     1,      1,      2,      1 UNION ALL
    SELECT @PAG_PEDIDOS,       'Crear pedido',          'ecom.pedido.crear',          1,      1,      3,      1 UNION ALL
    SELECT @PAG_PEDIDOS,       'Ver pedidos',           'ecom.pedido.ver',            1,      1,      4,      1
) AS nuevas
 WHERE nuevas.p IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.codigo_funcion = nuevas.c);

/* Reapunta funciones que hubieran quedado huerfanas de intentos previos */

UPDATE conf__paginas_funciones
   SET pagina_id = @PAG_CATALOGO, tabla_estado_registro_id = 1
 WHERE codigo_funcion = 'ecom.catalogo.ver' AND @PAG_CATALOGO IS NOT NULL;

UPDATE conf__paginas_funciones
   SET pagina_id = @PAG_CARRITO, tabla_estado_registro_id = 1
 WHERE codigo_funcion = 'ecom.carrito.gestionar' AND @PAG_CARRITO IS NOT NULL;

UPDATE conf__paginas_funciones
   SET pagina_id = @PAG_PEDIDOS, tabla_estado_registro_id = 1
 WHERE codigo_funcion IN ('ecom.pedido.crear', 'ecom.pedido.ver') AND @PAG_PEDIDOS IS NOT NULL;

/* Modulo habilitado para la empresa */

INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @MODULO_ID, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_modulos
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID);

UPDATE conf__empresas_modulos SET tabla_estado_registro_id = 1
 WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID;

/* Perfil Cliente Web */

INSERT INTO conf__perfiles (modulo_id, perfil_nombre, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Cliente Web', 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles WHERE modulo_id = @MODULO_ID AND perfil_nombre = 'Cliente Web');

INSERT INTO conf__empresas_perfiles (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @MODULO_ID,
       (SELECT perfil_id FROM conf__perfiles WHERE modulo_id = @MODULO_ID AND perfil_nombre = 'Cliente Web' LIMIT 1),
       'Cliente Web', 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web');

SET @PERFIL_ID := (SELECT empresa_perfil_id FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web' LIMIT 1);

UPDATE conf__empresas_perfiles SET tabla_estado_registro_id = 1 WHERE empresa_perfil_id = @PERFIL_ID;

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT @EMPRESA_ID, @PERFIL_ID, pf.pagina_funcion_id, 1
  FROM conf__paginas_funciones pf
 WHERE pf.codigo_funcion LIKE 'ecom.%'
   AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones epf
                    WHERE epf.empresa_perfil_id = @PERFIL_ID
                      AND epf.pagina_funcion_id = pf.pagina_funcion_id);

UPDATE conf__empresas_perfiles_funciones SET asignado = 1, empresa_id = @EMPRESA_ID
 WHERE empresa_perfil_id = @PERFIL_ID
   AND pagina_funcion_id IN (SELECT pagina_funcion_id FROM conf__paginas_funciones WHERE codigo_funcion LIKE 'ecom.%');

/* ========== BLOQUE 4 - TABLA ORIGEN DEL COMPROBANTE ============== */

INSERT INTO conf__tablas (tabla_nombre, tabla_descripcion, tabla_estado_registro_id)
SELECT 'gestion__compras_pedidos', 'Pedidos de compra', 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__tablas WHERE tabla_nombre = 'gestion__compras_pedidos');

/* ========== BLOQUE 5 - INDICES DE SOPORTE (solo performance) ===== */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='gestion__productos' AND index_name='idx_productos_empresa_estado_nombre')=0,
  'CREATE INDEX `idx_productos_empresa_estado_nombre` ON `gestion__productos` (`empresa_id`, `tabla_estado_registro_id`, `producto_nombre`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='gestion__productos' AND index_name='idx_productos_categoria')=0,
  'CREATE INDEX `idx_productos_categoria` ON `gestion__productos` (`producto_categoria_id`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='gestion__productos_imagenes' AND index_name='idx_productos_imagenes_producto')=0,
  'CREATE INDEX `idx_productos_imagenes_producto` ON `gestion__productos_imagenes` (`producto_id`, `empresa_id`, `es_principal`, `orden`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='gestion__productos_compatibilidad' AND index_name='idx_compatibilidad_marca')=0,
  'CREATE INDEX `idx_compatibilidad_marca` ON `gestion__productos_compatibilidad` (`marca_id`, `producto_id`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='gestion__productos_compatibilidad' AND index_name='idx_compatibilidad_modelo')=0,
  'CREATE INDEX `idx_compatibilidad_modelo` ON `gestion__productos_compatibilidad` (`modelo_id`, `producto_id`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='conf__usuarios_perfiles' AND index_name='idx_usuarios_perfiles_usuario_vigencia')=0,
  'CREATE INDEX `idx_usuarios_perfiles_usuario_vigencia` ON `conf__usuarios_perfiles` (`usuario_id`, `fecha_inicio`, `fecha_fin`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='conf__usuarios_perfiles' AND index_name='idx_usuarios_perfiles_empresa_perfil')=0,
  'CREATE INDEX `idx_usuarios_perfiles_empresa_perfil` ON `conf__usuarios_perfiles` (`empresa_perfil_id`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='conf__empresas_perfiles_funciones' AND index_name='idx_empresas_perfiles_funciones_perfil')=0,
  'CREATE INDEX `idx_empresas_perfiles_funciones_perfil` ON `conf__empresas_perfiles_funciones` (`empresa_perfil_id`, `asignado`, `pagina_funcion_id`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='conf__paginas_funciones' AND index_name='idx_paginas_funciones_pagina')=0,
  'CREATE INDEX `idx_paginas_funciones_pagina` ON `conf__paginas_funciones` (`pagina_id`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='gestion__entidades_sucursales_compra')=1
           AND (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='gestion__entidades_sucursales_compra' AND index_name='idx_esc_entidad_vigencia')=0,
  'CREATE INDEX `idx_esc_entidad_vigencia` ON `gestion__entidades_sucursales_compra` (`empresa_id`, `entidad_id`, `f_desde`, `f_hasta`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='gestion__compras_pedidos')=1
           AND (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='gestion__compras_pedidos' AND index_name='idx_compras_pedidos_entidad')=0,
  'CREATE INDEX `idx_compras_pedidos_entidad` ON `gestion__compras_pedidos` (`empresa_id`, `entidad_id`, `compra_pedido_id`)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== BLOQUE 6 - USUARIO (opcional, si @USUARIO_ID > 0) ==== */

INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id)
SELECT @USUARIO_ID, @PERFIL_ID, CURDATE(), @VENCE, 1
 WHERE @USUARIO_ID > 0 AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                    WHERE usuario_id = @USUARIO_ID AND empresa_perfil_id = @PERFIL_ID
                      AND tabla_estado_registro_id = 1 AND fecha_fin >= CURDATE());

/* Vinculo usuario -> cliente. Toma la entidad del usuario si ya existe
   en gestion__entidades con el mismo nombre de usuario NO se adivina:
   si @USUARIO_ID > 0 hay que indicar la entidad manualmente abajo.     */

SET @ENTIDAD_ID := 0;

INSERT INTO ecom__usuarios_entidades (empresa_id, usuario_id, entidad_id, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @USUARIO_ID, @ENTIDAD_ID, 1
 WHERE @USUARIO_ID > 0 AND @ENTIDAD_ID > 0
   AND EXISTS (SELECT 1 FROM gestion__entidades
                WHERE entidad_id = @ENTIDAD_ID AND empresa_id = @EMPRESA_ID)
   AND NOT EXISTS (SELECT 1 FROM ecom__usuarios_entidades
                    WHERE empresa_id = @EMPRESA_ID AND usuario_id = @USUARIO_ID);

/* ========== BLOQUE 7 - VERIFICACION ============================== */

SELECT 'V1. Tablas del storefront' AS verificacion, table_name AS objeto
  FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name LIKE 'ecom!_!_%' ESCAPE '!'
 ORDER BY table_name;

SELECT 'V2. Columna codigo_funcion' AS verificacion, column_name, column_type, is_nullable
  FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'conf__paginas_funciones'
   AND column_name = 'codigo_funcion';

SELECT 'V3. Indices creados' AS verificacion, table_name, index_name
  FROM information_schema.statistics
 WHERE table_schema = DATABASE()
   AND index_name IN ('uk_paginas_funciones_codigo','idx_productos_empresa_estado_nombre',
                      'idx_productos_categoria','idx_productos_imagenes_producto',
                      'idx_compatibilidad_marca','idx_compatibilidad_modelo',
                      'idx_usuarios_perfiles_usuario_vigencia','idx_usuarios_perfiles_empresa_perfil',
                      'idx_empresas_perfiles_funciones_perfil','idx_paginas_funciones_pagina',
                      'idx_esc_entidad_vigencia','idx_compras_pedidos_entidad')
 GROUP BY table_name, index_name
 ORDER BY table_name, index_name;

SELECT 'V4. Paginas y funciones' AS verificacion, pg.url, pf.codigo_funcion,
       pf.pagina_funcion_id, pf.tabla_estado_registro_id AS estado
  FROM conf__paginas_funciones pf
  LEFT JOIN conf__paginas pg ON pg.pagina_id = pf.pagina_id
 WHERE pf.codigo_funcion LIKE 'ecom.%'
 ORDER BY pf.codigo_funcion;

SELECT 'V5. Modulo habilitado en la empresa' AS verificacion,
       em.empresa_id, em.modulo_id, em.tabla_estado_registro_id AS estado
  FROM conf__empresas_modulos em
 WHERE em.empresa_id = @EMPRESA_ID AND em.modulo_id = @MODULO_ID;

SELECT 'V6. Perfil Cliente Web y sus permisos' AS verificacion,
       @PERFIL_ID AS empresa_perfil_id, pf.codigo_funcion, epf.asignado
  FROM conf__empresas_perfiles_funciones epf
 INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
 WHERE epf.empresa_perfil_id = @PERFIL_ID AND pf.codigo_funcion LIKE 'ecom.%'
 ORDER BY pf.codigo_funcion;

SELECT 'V7. Valores para el .env' AS verificacion,
       @MODULO_ID  AS ECOM_MODULO_ID,
       @EMPRESA_ID AS ECOM_EMPRESA_ID,
       (SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__compras_pedidos')
                   AS ECOM_TABLA_ORIGEN_PEDIDOS_ID;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_sucursales_compra') = 1,
  'SELECT ''V8. Clientes de la tienda SIN sucursal de compra vigente'' AS verificacion, e.entidad_id, e.entidad_nombre FROM gestion__entidades e INNER JOIN ecom__usuarios_entidades ue ON ue.entidad_id = e.entidad_id AND ue.empresa_id = e.empresa_id AND ue.tabla_estado_registro_id = 1 WHERE e.empresa_id = @EMPRESA_ID AND NOT EXISTS (SELECT 1 FROM gestion__entidades_sucursales_compra esc WHERE esc.entidad_id = e.entidad_id AND esc.empresa_id = e.empresa_id AND esc.tabla_estado_registro_id = 1 AND esc.f_desde <= CURDATE() AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE()))',
  'SELECT ''V8. FALTA la tabla gestion__entidades_sucursales_compra: crearla antes de operar'' AS verificacion');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
