/* ===================================================================
   PS ECOMMERCE B2B - COMPLETAR INSTALACION
   ===================================================================
   Resuelve lo que falto segun el diagnostico:

     1) FALTA la tabla gestion__entidades_sucursales_compra
     2) conf__modulos no tiene el modulo_id 4 (revisar)
     3) No hay ningun vinculo usuario -> cliente cargado

   USO: ejecutar PARTE A, leer los resultados, completar la PARTE B
        y la PARTE C, volver a ejecutar el archivo.
   =================================================================== */

SET NAMES utf8mb4;

SET @EMPRESA_ID := 2;
SET @MODULO_ID  := 4;

/* ========== PARTE A - CREAR LA TABLA QUE FALTA + DIAGNOSTICO ===== */

CREATE TABLE IF NOT EXISTS `gestion__entidades_sucursales_compra` (
    `entidad_sucursal_compra_id` INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
    `entidad_id`                 INT UNSIGNED      NOT NULL,
    `sucursal_id`                SMALLINT UNSIGNED NOT NULL,
    `es_principal`               TINYINT(1)        NOT NULL DEFAULT 0,
    `f_desde`                    DATE              NOT NULL,
    `f_hasta`                    DATE              NULL DEFAULT NULL,
    `observaciones`              VARCHAR(255)      NULL DEFAULT NULL,
    `fecha_alta`                 DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `usuario_alta`               INT UNSIGNED      NULL DEFAULT NULL,
    `tabla_estado_registro_id`   SMALLINT(4)       NOT NULL DEFAULT 1,
    PRIMARY KEY (`entidad_sucursal_compra_id`),
    KEY `FK_esc_empresa`  (`empresa_id`),
    KEY `FK_esc_entidad`  (`entidad_id`),
    KEY `FK_esc_sucursal` (`sucursal_id`),
    KEY `idx_esc_entidad_vigencia` (`empresa_id`, `entidad_id`, `f_desde`, `f_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'A1. Modulos existentes (verificar cual es el del ecommerce)' AS paso,
       modulo_id, modulo, tabla_estado_registro_id AS estado,
       IF(modulo_id = @MODULO_ID, '<== es el @MODULO_ID configurado', '') AS nota
  FROM conf__modulos
 ORDER BY modulo_id;

SELECT 'A2. Sucursales de la empresa (tomar el sucursal_id)' AS paso,
       sucursal_id, sucursal_nombre, tabla_estado_registro_id AS estado
  FROM gestion__sucursales
 WHERE empresa_id = @EMPRESA_ID
   AND tabla_estado_registro_id = 1
 ORDER BY sucursal_id;

SELECT 'A3. Estado actual del vinculo usuario -> cliente' AS paso,
       (SELECT COUNT(*) FROM ecom__usuarios_entidades)          AS vinculos_cargados,
       (SELECT COUNT(*) FROM gestion__entidades_sucursales_compra
         WHERE empresa_id = @EMPRESA_ID)                        AS sucursales_de_compra,
       (SELECT empresa_perfil_id FROM conf__empresas_perfiles
         WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID
           AND empresa_perfil_nombre = 'Cliente Web'
           AND tabla_estado_registro_id = 1 LIMIT 1)            AS perfil_cliente_web;

/* ========== PARTE B - HABILITAR USUARIOS COMO CLIENTES ============
   Poner el entidad_id (de A2 del script anterior: 2 = Empresa2) y
   la lista de usuario_id separados por coma. Los que no correspondan
   se dejan afuera de la lista.                                      */

SET @ENTIDAD_ID  := 2;
SET @USUARIOS    := '3,4,5';
SET @VENCE       := '2030-12-31';

SET @PERFIL_ID := (SELECT empresa_perfil_id FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID
                      AND modulo_id  = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web'
                      AND tabla_estado_registro_id = 1
                    LIMIT 1);

SELECT 'B1. Control previo' AS paso,
       @ENTIDAD_ID AS entidad_id,
       (SELECT entidad_nombre FROM gestion__entidades
         WHERE entidad_id = @ENTIDAD_ID AND empresa_id = @EMPRESA_ID) AS cliente,
       @USUARIOS   AS usuarios_a_habilitar,
       @PERFIL_ID  AS empresa_perfil_id,
       IF((SELECT COUNT(*) FROM gestion__entidades
            WHERE entidad_id = @ENTIDAD_ID AND empresa_id = @EMPRESA_ID
              AND es_cliente = 1 AND tabla_estado_registro_id = 1) = 0,
          'ERROR: la entidad no es cliente activo de esa empresa',
       IF(@PERFIL_ID IS NULL,
          'ERROR: falta el perfil Cliente Web, ejecutar migracion_produccion_completa.sql',
          'OK')) AS validacion;

INSERT INTO ecom__usuarios_entidades (empresa_id, usuario_id, entidad_id, tabla_estado_registro_id)
SELECT @EMPRESA_ID, u.usuario_id, @ENTIDAD_ID, 1
  FROM conf__usuarios u
 WHERE u.tabla_estado_registro_id = 1
   AND FIND_IN_SET(u.usuario_id, @USUARIOS) > 0
   AND EXISTS (SELECT 1 FROM gestion__entidades
                WHERE entidad_id = @ENTIDAD_ID AND empresa_id = @EMPRESA_ID
                  AND es_cliente = 1 AND tabla_estado_registro_id = 1)
   AND NOT EXISTS (SELECT 1 FROM ecom__usuarios_entidades ue
                    WHERE ue.empresa_id = @EMPRESA_ID AND ue.usuario_id = u.usuario_id);

UPDATE ecom__usuarios_entidades
   SET entidad_id = @ENTIDAD_ID, tabla_estado_registro_id = 1
 WHERE empresa_id = @EMPRESA_ID
   AND FIND_IN_SET(usuario_id, @USUARIOS) > 0;

INSERT INTO conf__usuarios_perfiles
       (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id)
SELECT u.usuario_id, @PERFIL_ID, CURDATE(), @VENCE, 1
  FROM conf__usuarios u
 WHERE u.tabla_estado_registro_id = 1
   AND FIND_IN_SET(u.usuario_id, @USUARIOS) > 0
   AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles up
                    WHERE up.usuario_id = u.usuario_id
                      AND up.empresa_perfil_id = @PERFIL_ID
                      AND up.tabla_estado_registro_id = 1
                      AND up.fecha_fin >= CURDATE());

/* ========== PARTE C - SUCURSAL DE COMPRA DEL CLIENTE ==============
   Sin esto el login funciona pero NO se pueden confirmar pedidos.
   Poner el sucursal_id que salio en A2.                             */

SET @SUCURSAL_ID := 0;

INSERT INTO gestion__entidades_sucursales_compra
       (empresa_id, entidad_id, sucursal_id, es_principal, f_desde, f_hasta,
        observaciones, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @ENTIDAD_ID, @SUCURSAL_ID, 1, CURDATE(), NULL,
       'Alta desde la instalacion del ecommerce', 1
 WHERE @SUCURSAL_ID > 0
   AND EXISTS (SELECT 1 FROM gestion__sucursales
                WHERE sucursal_id = @SUCURSAL_ID AND empresa_id = @EMPRESA_ID
                  AND tabla_estado_registro_id = 1)
   AND NOT EXISTS (SELECT 1 FROM gestion__entidades_sucursales_compra
                    WHERE empresa_id = @EMPRESA_ID AND entidad_id = @ENTIDAD_ID
                      AND tabla_estado_registro_id = 1
                      AND f_desde <= CURDATE()
                      AND (f_hasta IS NULL OR f_hasta >= CURDATE()));

/* ========== PARTE D - VERIFICACION FINAL ========================= */

SELECT 'D1. Consulta exacta del login (debe devolver una fila por usuario)' AS paso,
       ue.usuario_id, u.usuario, e.entidad_id, e.entidad_nombre
  FROM ecom__usuarios_entidades ue
 INNER JOIN gestion__entidades e
         ON e.entidad_id = ue.entidad_id
        AND e.empresa_id = ue.empresa_id
        AND e.es_cliente = 1
        AND e.tabla_estado_registro_id = 1
  LEFT JOIN conf__usuarios u ON u.usuario_id = ue.usuario_id
 WHERE ue.empresa_id = @EMPRESA_ID
   AND ue.tabla_estado_registro_id = 1
 ORDER BY ue.usuario_id;

SELECT 'D2. Permisos vigentes por usuario' AS paso,
       up.usuario_id, u.usuario, pf.codigo_funcion
  FROM conf__usuarios_perfiles up
 INNER JOIN conf__empresas_perfiles ep
         ON ep.empresa_perfil_id = up.empresa_perfil_id
        AND ep.empresa_id = @EMPRESA_ID
        AND ep.modulo_id  = @MODULO_ID
        AND ep.tabla_estado_registro_id = 1
 INNER JOIN conf__empresas_perfiles_funciones epf
         ON epf.empresa_perfil_id = ep.empresa_perfil_id
        AND epf.asignado = 1
 INNER JOIN conf__paginas_funciones pf
         ON pf.pagina_funcion_id = epf.pagina_funcion_id
        AND pf.tabla_estado_registro_id = 1
  LEFT JOIN conf__usuarios u ON u.usuario_id = up.usuario_id
 WHERE up.tabla_estado_registro_id = 1
   AND up.fecha_inicio <= CURDATE()
   AND up.fecha_fin    >= CURDATE()
   AND pf.codigo_funcion LIKE 'ecom.%'
 ORDER BY up.usuario_id, pf.codigo_funcion;

SELECT 'D3. Sucursal de compra vigente del cliente' AS paso,
       esc.entidad_sucursal_compra_id, esc.entidad_id, esc.sucursal_id,
       s.sucursal_nombre, esc.es_principal, esc.f_desde, esc.f_hasta
  FROM gestion__entidades_sucursales_compra esc
  LEFT JOIN gestion__sucursales s
         ON s.sucursal_id = esc.sucursal_id AND s.empresa_id = esc.empresa_id
 WHERE esc.empresa_id = @EMPRESA_ID
   AND esc.tabla_estado_registro_id = 1
   AND esc.f_desde <= CURDATE()
   AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE())
 ORDER BY esc.entidad_id, esc.es_principal DESC;

SELECT 'D4. Resumen' AS paso,
       (SELECT COUNT(*) FROM ecom__usuarios_entidades
         WHERE empresa_id = @EMPRESA_ID AND tabla_estado_registro_id = 1) AS usuarios_habilitados,
       (SELECT COUNT(*) FROM gestion__entidades_sucursales_compra
         WHERE empresa_id = @EMPRESA_ID AND tabla_estado_registro_id = 1) AS sucursales_de_compra,
       IF((SELECT COUNT(*) FROM gestion__entidades_sucursales_compra
            WHERE empresa_id = @EMPRESA_ID AND entidad_id = @ENTIDAD_ID
              AND tabla_estado_registro_id = 1) = 0,
          'FALTA: completar @SUCURSAL_ID en la PARTE C, si no fallan los pedidos',
          'OK: se puede navegar y confirmar pedidos') AS estado_final;
