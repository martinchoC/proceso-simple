SET @USUARIO_ID := 3;
SET @ENTIDAD_ID := 2;
SET @VENCE := '2030-12-31';

SET @MODULO_ID := (SELECT modulo_id FROM conf__modulos WHERE modulo = 'Ecommerce B2B' LIMIT 1);
SET @EMPRESA_ID := (SELECT empresa_id FROM gestion__entidades WHERE entidad_id = @ENTIDAD_ID);
SET @PERFIL_ID := (SELECT empresa_perfil_id FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID
                      AND modulo_id = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web'
                      AND tabla_estado_registro_id = 1
                    LIMIT 1);

SELECT 'CONTROL PREVIO' AS paso,
       @USUARIO_ID AS usuario_id,
       @ENTIDAD_ID AS entidad_id,
       @EMPRESA_ID AS empresa_id,
       @MODULO_ID  AS modulo_id,
       @PERFIL_ID  AS empresa_perfil_id,
       (SELECT usuario FROM conf__usuarios WHERE usuario_id = @USUARIO_ID) AS usuario,
       (SELECT entidad_nombre FROM gestion__entidades WHERE entidad_id = @ENTIDAD_ID) AS cliente,
       (SELECT es_cliente FROM gestion__entidades WHERE entidad_id = @ENTIDAD_ID) AS es_cliente,
       (SELECT tabla_estado_registro_id FROM gestion__entidades WHERE entidad_id = @ENTIDAD_ID) AS estado_entidad;

INSERT INTO ecom__usuarios_entidades (empresa_id, usuario_id, entidad_id)
SELECT @EMPRESA_ID, @USUARIO_ID, @ENTIDAD_ID
 WHERE @USUARIO_ID IS NOT NULL
   AND @ENTIDAD_ID IS NOT NULL
   AND @EMPRESA_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM ecom__usuarios_entidades
                    WHERE empresa_id = @EMPRESA_ID AND usuario_id = @USUARIO_ID);

INSERT INTO conf__usuarios_perfiles
       (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id)
SELECT @USUARIO_ID, @PERFIL_ID, CURDATE(), @VENCE, 1
 WHERE @USUARIO_ID IS NOT NULL
   AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                    WHERE usuario_id = @USUARIO_ID
                      AND empresa_perfil_id = @PERFIL_ID
                      AND tabla_estado_registro_id = 1
                      AND fecha_fin >= CURDATE());

SELECT 'RESULTADO' AS paso,
       (SELECT COUNT(*) FROM ecom__usuarios_entidades ue
         INNER JOIN gestion__entidades e
                 ON e.entidad_id = ue.entidad_id
                AND e.empresa_id = ue.empresa_id
                AND e.es_cliente = 1
                AND e.tabla_estado_registro_id = 1
         WHERE ue.usuario_id = @USUARIO_ID
           AND ue.empresa_id = @EMPRESA_ID
           AND ue.tabla_estado_registro_id = 1) AS vinculo_cliente_ok,
       (SELECT COUNT(*) FROM conf__usuarios_perfiles
         WHERE usuario_id = @USUARIO_ID
           AND empresa_perfil_id = @PERFIL_ID
           AND tabla_estado_registro_id = 1
           AND fecha_inicio <= CURDATE()
           AND fecha_fin >= CURDATE()) AS perfil_vigente_ok;
