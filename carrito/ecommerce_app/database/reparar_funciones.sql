SET @MODULO_ID  := 4;
SET @EMPRESA_ID := 2;
SET @USUARIO_ID := 3;
SET @VENCE := '2030-12-31';

INSERT INTO conf__paginas (modulo_id, pagina, url, pagina_descripcion, orden, tabla_estado_registro_id)
SELECT @MODULO_ID, 'Catálogo', '/catalogo', 'Catalogo de productos del storefront', 1, 1
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

SELECT 'A. Paginas del modulo' AS paso, @PAG_CATALOGO AS catalogo, @PAG_CARRITO AS carrito, @PAG_PEDIDOS AS pedidos;

UPDATE conf__paginas_funciones
   SET pagina_id = @PAG_CATALOGO, tabla_estado_registro_id = 1
 WHERE codigo_funcion = 'ecom.catalogo.ver' AND @PAG_CATALOGO IS NOT NULL;

UPDATE conf__paginas_funciones
   SET pagina_id = @PAG_CARRITO, tabla_estado_registro_id = 1
 WHERE codigo_funcion = 'ecom.carrito.gestionar' AND @PAG_CARRITO IS NOT NULL;

UPDATE conf__paginas_funciones
   SET pagina_id = @PAG_PEDIDOS, tabla_estado_registro_id = 1
 WHERE codigo_funcion IN ('ecom.pedido.crear', 'ecom.pedido.ver') AND @PAG_PEDIDOS IS NOT NULL;

SELECT 'B. Funciones reapuntadas' AS paso, pf.pagina_funcion_id, pf.codigo_funcion,
       pf.pagina_id, pg.modulo_id, pg.url, pf.tabla_estado_registro_id AS estado
  FROM conf__paginas_funciones pf
  LEFT JOIN conf__paginas pg ON pg.pagina_id = pf.pagina_id
 WHERE pf.codigo_funcion LIKE 'ecom.%'
 ORDER BY pf.codigo_funcion;

SET @PERFIL_ID := (SELECT empresa_perfil_id FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID AND modulo_id = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web' LIMIT 1);

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
