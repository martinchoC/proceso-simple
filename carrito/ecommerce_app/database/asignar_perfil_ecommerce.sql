SET @USUARIO_ID := 3;
SET @EMPRESA_ID := 2;
SET @VENCE := '2030-12-31';

SET @MODULO_ID := (SELECT pg.modulo_id
                     FROM conf__paginas_funciones pf
                     INNER JOIN conf__paginas pg ON pg.pagina_id = pf.pagina_id
                    WHERE pf.codigo_funcion LIKE 'ecom.%'
                    GROUP BY pg.modulo_id
                    ORDER BY COUNT(*) DESC
                    LIMIT 1);

SELECT 'A. Modulos existentes' AS paso, modulo_id, modulo, tabla_estado_registro_id AS estado
  FROM conf__modulos ORDER BY modulo_id;

SELECT 'B. Modulo real del ecommerce' AS paso,
       @MODULO_ID AS modulo_id,
       (SELECT modulo FROM conf__modulos WHERE modulo_id = @MODULO_ID) AS nombre,
       'Poner este numero en ECOM_MODULO_ID del .env' AS accion;

INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @MODULO_ID, 1
 WHERE @MODULO_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__empresas_modulos
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID);

UPDATE conf__empresas_modulos SET tabla_estado_registro_id = 1
 WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID;

INSERT INTO conf__perfiles (modulo_id, perfil_nombre, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Cliente Web', 1
 WHERE @MODULO_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__perfiles
                    WHERE modulo_id = @MODULO_ID AND perfil_nombre = 'Cliente Web');

INSERT INTO conf__empresas_perfiles (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @MODULO_ID,
       (SELECT perfil_id FROM conf__perfiles WHERE modulo_id = @MODULO_ID AND perfil_nombre = 'Cliente Web' LIMIT 1),
       'Cliente Web', 1
 WHERE @MODULO_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web');

SET @PERFIL_ID := (SELECT empresa_perfil_id FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web' LIMIT 1);

UPDATE conf__empresas_perfiles SET tabla_estado_registro_id = 1 WHERE empresa_perfil_id = @PERFIL_ID;

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT @EMPRESA_ID, @PERFIL_ID, pf.pagina_funcion_id, 1
  FROM conf__paginas_funciones pf
 WHERE pf.codigo_funcion IN ('ecom.catalogo.ver','ecom.carrito.gestionar','ecom.pedido.crear','ecom.pedido.ver')
   AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones epf
                    WHERE epf.empresa_perfil_id = @PERFIL_ID
                      AND epf.pagina_funcion_id = pf.pagina_funcion_id);

UPDATE conf__empresas_perfiles_funciones SET asignado = 1
 WHERE empresa_perfil_id = @PERFIL_ID
   AND pagina_funcion_id IN (SELECT pagina_funcion_id FROM conf__paginas_funciones
                              WHERE codigo_funcion LIKE 'ecom.%');

INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id)
SELECT @USUARIO_ID, @PERFIL_ID, CURDATE(), @VENCE, 1
 WHERE @USUARIO_ID IS NOT NULL AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                    WHERE usuario_id = @USUARIO_ID AND empresa_perfil_id = @PERFIL_ID
                      AND tabla_estado_registro_id = 1 AND fecha_fin >= CURDATE());

SELECT 'C. Permisos que vera la aplicacion' AS paso, pf.codigo_funcion
  FROM conf__usuarios_perfiles up
 INNER JOIN conf__empresas_perfiles ep
         ON ep.empresa_perfil_id = up.empresa_perfil_id
        AND ep.empresa_id = @EMPRESA_ID
        AND ep.modulo_id = @MODULO_ID
        AND ep.tabla_estado_registro_id = 1
 INNER JOIN conf__empresas_perfiles_funciones epf
         ON epf.empresa_perfil_id = ep.empresa_perfil_id
        AND epf.empresa_id = @EMPRESA_ID
        AND epf.asignado = 1
 INNER JOIN conf__paginas_funciones pf
         ON pf.pagina_funcion_id = epf.pagina_funcion_id
        AND pf.tabla_estado_registro_id = 1
 INNER JOIN conf__paginas pg
         ON pg.pagina_id = pf.pagina_id
        AND pg.modulo_id = @MODULO_ID
        AND pg.tabla_estado_registro_id = 1
 WHERE up.usuario_id = @USUARIO_ID
   AND up.tabla_estado_registro_id = 1
   AND up.fecha_inicio <= CURDATE()
   AND up.fecha_fin >= CURDATE()
   AND pf.codigo_funcion IS NOT NULL;
