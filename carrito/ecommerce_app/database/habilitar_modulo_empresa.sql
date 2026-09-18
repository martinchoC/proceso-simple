SET @EMPRESA_ID := 2;
SET @MODULO_ID := (SELECT modulo_id FROM conf__modulos WHERE modulo = 'Ecommerce B2B' LIMIT 1);
SELECT empresa_id, empresa, tabla_estado_registro_id FROM conf__empresas ORDER BY empresa_id;
SELECT @EMPRESA_ID AS empresa_elegida, @MODULO_ID AS modulo_ecommerce;
INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @MODULO_ID, 1
 WHERE @MODULO_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__empresas_modulos
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID);
UPDATE conf__empresas_modulos SET tabla_estado_registro_id = 1
 WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID;
UPDATE conf__empresas_perfiles SET tabla_estado_registro_id = 2
 WHERE modulo_id = @MODULO_ID
   AND empresa_perfil_nombre = 'Cliente Web'
   AND empresa_id <> @EMPRESA_ID;
UPDATE conf__empresas_perfiles SET tabla_estado_registro_id = 1
 WHERE modulo_id = @MODULO_ID
   AND empresa_perfil_nombre = 'Cliente Web'
   AND empresa_id = @EMPRESA_ID;
SELECT ep.empresa_id, e.empresa, ep.empresa_perfil_id, ep.empresa_perfil_nombre,
       ep.tabla_estado_registro_id AS estado_perfil,
       (SELECT COUNT(*) FROM conf__empresas_modulos em
         WHERE em.empresa_id = ep.empresa_id AND em.modulo_id = @MODULO_ID
           AND em.tabla_estado_registro_id = 1) AS modulo_habilitado
  FROM conf__empresas_perfiles ep
  LEFT JOIN conf__empresas e ON e.empresa_id = ep.empresa_id
 WHERE ep.modulo_id = @MODULO_ID
 ORDER BY ep.empresa_id;
