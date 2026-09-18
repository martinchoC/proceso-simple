-- =============================================================================
--  Habilitar un usuario existente para la tienda
--
--  Un usuario de conf__usuarios entra al storefront sólo si tiene LAS DOS cosas:
--    1. una fila en ecom__usuarios_entidades  (a qué cliente representa)
--    2. un perfil vigente del módulo "Ecommerce B2B" (qué puede hacer)
--
--  El punto 2 también se puede hacer desde el sistema de administración de
--  perfiles: el módulo aparece ahí como cualquier otro. El punto 1 es propio
--  del ecommerce y por ahora se carga con este script.
--
--  Editar las tres variables de abajo y ejecutar en phpMyAdmin.
-- =============================================================================

SET @USUARIO   := 'cliente001';                 -- conf__usuarios.usuario
SET @CLIENTE   := 'CLIENTE MAYORISTA 1';        -- gestion__entidades.entidad_nombre
SET @VENCE     := '2030-12-31';                 -- vigencia del perfil

-- ---------------------------------------------------------------------------
SET @MODULO_ID  := (SELECT modulo_id FROM conf__modulos WHERE modulo = 'Ecommerce B2B' LIMIT 1);
SET @USUARIO_ID := (SELECT usuario_id FROM conf__usuarios WHERE usuario = @USUARIO LIMIT 1);
SET @ENTIDAD_ID := (SELECT entidad_id FROM gestion__entidades
                     WHERE entidad_nombre = @CLIENTE AND es_cliente = 1
                       AND tabla_estado_registro_id = 1 LIMIT 1);
SET @EMPRESA_ID := (SELECT empresa_id FROM gestion__entidades WHERE entidad_id = @ENTIDAD_ID);
SET @PERFIL_ID  := (SELECT empresa_perfil_id FROM conf__empresas_perfiles
                     WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID
                       AND empresa_perfil_nombre = 'Cliente Web' LIMIT 1);

-- Control previo: las 4 columnas deben venir con un número, ninguna NULL.
SELECT @USUARIO_ID AS usuario_id, @ENTIDAD_ID AS entidad_id,
       @EMPRESA_ID AS empresa_id, @PERFIL_ID AS empresa_perfil_id;

-- 1) Vínculo usuario -> cliente
INSERT INTO ecom__usuarios_entidades (empresa_id, usuario_id, entidad_id)
SELECT @EMPRESA_ID, @USUARIO_ID, @ENTIDAD_ID
 WHERE @USUARIO_ID IS NOT NULL AND @ENTIDAD_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM ecom__usuarios_entidades
                    WHERE empresa_id = @EMPRESA_ID AND usuario_id = @USUARIO_ID);

-- 2) Perfil vigente del módulo
INSERT INTO conf__usuarios_perfiles
       (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id)
SELECT @USUARIO_ID, @PERFIL_ID, CURDATE(), @VENCE, 1
 WHERE @USUARIO_ID IS NOT NULL AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                    WHERE usuario_id = @USUARIO_ID AND empresa_perfil_id = @PERFIL_ID
                      AND tabla_estado_registro_id = 1
                      AND fecha_fin >= CURDATE());

-- Verificación final: debe devolver 1 y 1
SELECT (SELECT COUNT(*) FROM ecom__usuarios_entidades
         WHERE usuario_id = @USUARIO_ID)                    AS vinculo_cliente,
       (SELECT COUNT(*) FROM conf__usuarios_perfiles
         WHERE usuario_id = @USUARIO_ID AND empresa_perfil_id = @PERFIL_ID
           AND tabla_estado_registro_id = 1 AND fecha_fin >= CURDATE()) AS perfil_vigente;

-- =============================================================================
--  Cambiar la contraseña de un usuario (hash bcrypt)
--  Generar el hash con:  php -r "echo password_hash('LaClave', PASSWORD_DEFAULT);"
--  y pegarlo abajo. NUNCA guardar contraseñas en texto plano.
-- =============================================================================
-- UPDATE conf__usuarios SET password = '$2y$12$...' WHERE usuario = 'cliente001';
