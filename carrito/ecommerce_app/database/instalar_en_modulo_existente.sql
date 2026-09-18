SET @MODULO_ID  := 4;
SET @EMPRESA_ID := 2;
SET @USUARIO_ID := 3;
SET @VENCE := '2030-12-31';

SELECT 'A. Contexto' AS paso,
       @MODULO_ID AS modulo_id,
       (SELECT modulo FROM conf__modulos WHERE modulo_id = @MODULO_ID) AS modulo,
       @EMPRESA_ID AS empresa_id,
       (SELECT empresa FROM conf__empresas WHERE empresa_id = @EMPRESA_ID) AS empresa,
       @USUARIO_ID AS usuario_id,
       (SELECT usuario FROM conf__usuarios WHERE usuario_id = @USUARIO_ID) AS usuario;

INSERT INTO conf__paginas (modulo_id, pagina, url, pagina_descripcion, orden, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Catálogo', '/catalogo', 'Catalogo de productos del storefront', 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/catalogo');

INSERT INTO conf__paginas (modulo_id, pagina, url, pagina_descripcion, orden, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Carrito', '/carrito', 'Carrito de compras', 2, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/carrito');

INSERT INTO conf__paginas (modulo_id, pagina, url, pagina_descripcion, orden, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Pedidos', '/pedidos', 'Pedidos del cliente', 3, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/pedidos');

SET @PAG_CATALOGO := (SELECT pagina_id FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/catalogo' LIMIT 1);
SET @PAG_CARRITO  := (SELECT pagina_id FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/carrito'  LIMIT 1);
SET @PAG_PEDIDOS  := (SELECT pagina_id FROM conf__paginas WHERE modulo_id = @MODULO_ID AND url = '/pedidos'  LIMIT 1);

SELECT 'B. Paginas' AS paso, @PAG_CATALOGO AS catalogo, @PAG_CARRITO AS carrito, @PAG_PEDIDOS AS pedidos;

INSERT INTO conf__paginas_funciones
    (pagina_id, nombre_funcion, codigo_funcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT * FROM (
    SELECT @PAG_CATALOGO AS p, 'Ver catálogo'      AS n, 'ecom.catalogo.ver'      AS c, 1 AS o, 1 AS d, 1 AS ord, 1 AS e UNION ALL
    SELECT @PAG_CARRITO,       'Gestionar carrito',      'ecom.carrito.gestionar',      1,      1,      2,      1 UNION ALL
    SELECT @PAG_PEDIDOS,       'Crear pedido',           'ecom.pedido.crear',           1,      1,      3,      1 UNION ALL
    SELECT @PAG_PEDIDOS,       'Ver pedidos',            'ecom.pedido.ver',             1,      1,      4,      1
) AS nuevas
 WHERE nuevas.p IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones pf WHERE pf.codigo_funcion = nuevas.c);

INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @MODULO_ID, 1
 WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_modulos
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID);

UPDATE conf__empresas_modulos SET tabla_estado_registro_id = 1
 WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID;

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

SELECT 'C. Perfil' AS paso, @PERFIL_ID AS empresa_perfil_id;

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT @EMPRESA_ID, @PERFIL_ID, pf.pagina_funcion_id, 1
  FROM conf__paginas_funciones pf
 INNER JOIN conf__paginas pg ON pg.pagina_id = pf.pagina_id AND pg.modulo_id = @MODULO_ID
 WHERE pf.codigo_funcion LIKE 'ecom.%'
   AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones epf
                    WHERE epf.empresa_perfil_id = @PERFIL_ID
                      AND epf.pagina_funcion_id = pf.pagina_funcion_id);

UPDATE conf__empresas_perfiles_funciones SET asignado = 1
 WHERE empresa_perfil_id = @PERFIL_ID
   AND pagina_funcion_id IN (SELECT pagina_funcion_id FROM conf__paginas_funciones WHERE codigo_funcion LIKE 'ecom.%');

INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id)
SELECT @USUARIO_ID, @PERFIL_ID, CURDATE(), @VENCE, 1
 WHERE @USUARIO_ID IS NOT NULL AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                    WHERE usuario_id = @USUARIO_ID AND empresa_perfil_id = @PERFIL_ID
                      AND tabla_estado_registro_id = 1 AND fecha_fin >= CURDATE());

SELECT 'D. Permisos que vera la aplicacion' AS paso, pf.codigo_funcion
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
