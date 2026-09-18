SET @USUARIO := 'martin';
SET @MODULO_ID := (SELECT modulo_id FROM conf__modulos WHERE modulo = 'Ecommerce B2B' LIMIT 1);
SET @USUARIO_ID := (SELECT usuario_id FROM conf__usuarios WHERE usuario = @USUARIO LIMIT 1);
SET @EMPRESA_TIENDA := (SELECT empresa_id FROM conf__empresas_modulos
                         WHERE modulo_id = @MODULO_ID AND tabla_estado_registro_id = 1 LIMIT 1);

SELECT '1. Contexto' AS paso, @MODULO_ID AS modulo_id, @USUARIO_ID AS usuario_id,
       @EMPRESA_TIENDA AS empresa_de_la_tienda;

SELECT '2. Usuario' AS paso, usuario_id, usuario, usuario_nombre, tabla_estado_registro_id AS estado
  FROM conf__usuarios WHERE usuario = @USUARIO;

SELECT '3. Entidades que podrian ser el cliente' AS paso,
       entidad_id, empresa_id, entidad_nombre, entidad_fantasia,
       es_cliente, tabla_estado_registro_id AS estado
  FROM gestion__entidades
 WHERE entidad_nombre LIKE CONCAT('%', @USUARIO, '%')
    OR entidad_fantasia LIKE CONCAT('%', @USUARIO, '%')
 ORDER BY empresa_id, entidad_id;

SELECT '4. Clientes validos de la empresa de la tienda' AS paso,
       entidad_id, empresa_id, entidad_nombre
  FROM gestion__entidades
 WHERE empresa_id = @EMPRESA_TIENDA
   AND es_cliente = 1
   AND tabla_estado_registro_id = 1
 ORDER BY entidad_nombre
 LIMIT 30;

SELECT '5. Vinculos ya cargados' AS paso, ue.usuario_entidad_id, ue.empresa_id, ue.usuario_id,
       ue.entidad_id, ue.tabla_estado_registro_id AS estado, u.usuario, e.entidad_nombre
  FROM ecom__usuarios_entidades ue
  LEFT JOIN conf__usuarios u ON u.usuario_id = ue.usuario_id
  LEFT JOIN gestion__entidades e ON e.entidad_id = ue.entidad_id;

SELECT '6. Perfiles vigentes del usuario en el modulo' AS paso,
       up.usuario_perfil_id, ep.empresa_id, ep.empresa_perfil_nombre,
       up.fecha_inicio, up.fecha_fin, up.tabla_estado_registro_id AS estado
  FROM conf__usuarios_perfiles up
  INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
 WHERE up.usuario_id = @USUARIO_ID
   AND ep.modulo_id = @MODULO_ID;

SELECT '7. Lo que ve la aplicacion' AS paso, COUNT(*) AS clientes_encontrados
  FROM ecom__usuarios_entidades ue
 INNER JOIN gestion__entidades e
         ON e.entidad_id = ue.entidad_id
        AND e.empresa_id = ue.empresa_id
        AND e.es_cliente = 1
        AND e.tabla_estado_registro_id = 1
 WHERE ue.usuario_id = @USUARIO_ID
   AND ue.empresa_id = @EMPRESA_TIENDA
   AND ue.tabla_estado_registro_id = 1;
