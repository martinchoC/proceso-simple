SELECT '1. Tablas nuevas del storefront' AS bloque, table_name AS objeto,
       table_rows AS filas_aprox, create_time AS creada
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name LIKE 'ecom!_!_%' ESCAPE '!'
 ORDER BY table_name;

SELECT '2. Columna agregada a una tabla existente' AS bloque,
       table_name AS tabla, column_name AS columna, column_type AS tipo,
       is_nullable AS acepta_null, column_default AS por_defecto
  FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND table_name = 'conf__paginas_funciones'
   AND column_name = 'codigo_funcion';

SELECT '3. Indices agregados' AS bloque, table_name AS tabla, index_name AS indice,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columnas
  FROM information_schema.statistics
 WHERE table_schema = DATABASE()
   AND index_name IN ('uk_paginas_funciones_codigo',
                      'idx_productos_empresa_estado_nombre', 'idx_productos_categoria',
                      'idx_productos_imagenes_producto',
                      'idx_compatibilidad_marca', 'idx_compatibilidad_modelo',
                      'idx_usuarios_perfiles_usuario_vigencia', 'idx_usuarios_perfiles_empresa_perfil',
                      'idx_empresas_perfiles_funciones_perfil', 'idx_paginas_funciones_pagina',
                      'idx_esc_entidad_vigencia', 'idx_compras_pedidos_entidad')
 GROUP BY table_name, index_name
 ORDER BY table_name, index_name;

SELECT '4. Modulo del ecommerce' AS bloque, modulo_id, modulo, tabla_estado_registro_id AS estado
  FROM conf__modulos
 WHERE modulo LIKE 'Ecommerce%';

SELECT '5. Paginas creadas' AS bloque, pagina_id, modulo_id, pagina, url, tabla_estado_registro_id AS estado
  FROM conf__paginas
 WHERE url IN ('/catalogo', '/carrito', '/pedidos');

SELECT '6. Funciones creadas' AS bloque, pagina_funcion_id, pagina_id, nombre_funcion,
       codigo_funcion, tabla_estado_registro_id AS estado
  FROM conf__paginas_funciones
 WHERE codigo_funcion LIKE 'ecom.%';

SELECT '7. Perfiles creados' AS bloque, ep.empresa_perfil_id, ep.empresa_id, e.empresa,
       ep.modulo_id, ep.empresa_perfil_nombre, ep.tabla_estado_registro_id AS estado
  FROM conf__empresas_perfiles ep
  LEFT JOIN conf__empresas e ON e.empresa_id = ep.empresa_id
 WHERE ep.empresa_perfil_nombre = 'Cliente Web';

SELECT '8. Permisos asignados a esos perfiles' AS bloque,
       epf.empresa_perfil_funcion_id, epf.empresa_id, epf.empresa_perfil_id,
       pf.codigo_funcion, epf.asignado
  FROM conf__empresas_perfiles_funciones epf
 INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
 WHERE pf.codigo_funcion LIKE 'ecom.%';

SELECT '9. Modulo habilitado por empresa' AS bloque, em.empresa_modulo_id, em.empresa_id,
       e.empresa, em.modulo_id, m.modulo, em.tabla_estado_registro_id AS estado
  FROM conf__empresas_modulos em
  LEFT JOIN conf__empresas e ON e.empresa_id = em.empresa_id
  LEFT JOIN conf__modulos m ON m.modulo_id = em.modulo_id
 WHERE m.modulo LIKE 'Ecommerce%';

SELECT '10. Fila agregada en conf__tablas' AS bloque, tabla_id, tabla_nombre
  FROM conf__tablas
 WHERE tabla_nombre = 'gestion__compras_pedidos';

SELECT '11. Usuarios habilitados en la tienda' AS bloque,
       ue.usuario_entidad_id, ue.empresa_id, u.usuario, e.entidad_nombre AS cliente
  FROM ecom__usuarios_entidades ue
  LEFT JOIN conf__usuarios u ON u.usuario_id = ue.usuario_id
  LEFT JOIN gestion__entidades e ON e.entidad_id = ue.entidad_id;

SELECT '12. Perfiles asignados a usuarios por el ecommerce' AS bloque,
       up.usuario_perfil_id, u.usuario, ep.empresa_id, ep.empresa_perfil_nombre,
       up.fecha_inicio, up.fecha_fin
  FROM conf__usuarios_perfiles up
 INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
  LEFT JOIN conf__usuarios u ON u.usuario_id = up.usuario_id
 WHERE ep.empresa_perfil_nombre = 'Cliente Web';

SELECT '13. Pedidos generados desde la tienda' AS bloque,
       ep.ecom_pedido_id, ep.compra_pedido_id, ep.empresa_id, ep.entidad_id,
       ep.usuario_id, ep.origen, ep.creado_en
  FROM ecom__pedidos ep
 ORDER BY ep.ecom_pedido_id DESC
 LIMIT 20;

SELECT '14. Comprobantes generados por el modulo ecommerce' AS bloque,
       comprobante_id, tabla_origen_id, registro_origen_id, comprobante_pv,
       comprobante_nro, importe_total, fecha_creacion
  FROM gestion__comprobantes
 WHERE modulo = 'ecommerce'
 ORDER BY comprobante_id DESC
 LIMIT 20;
