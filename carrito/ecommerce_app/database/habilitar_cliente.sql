/* ===================================================================
   PS ECOMMERCE B2B - HABILITAR UN USUARIO COMO CLIENTE DE LA TIENDA
   ===================================================================
   Corrige: "Tu usuario no tiene un cliente habilitado en la tienda."

   Ese mensaje aparece cuando esta consulta no devuelve ninguna fila:

     ecom__usuarios_entidades ue
       INNER JOIN gestion__entidades e
               ON e.entidad_id = ue.entidad_id
              AND e.empresa_id = ue.empresa_id
              AND e.es_cliente = 1
              AND e.tabla_estado_registro_id = 1
      WHERE ue.usuario_id = <usuario>
        AND ue.empresa_id = <empresa de la sesion>
        AND ue.tabla_estado_registro_id = 1

   USO:
     1) Ejecutar la PARTE A tal cual. Mirar los resultados.
     2) Completar @USUARIO_ID y @ENTIDAD_ID en la PARTE B y ejecutarla.
   =================================================================== */

/* ================= PARTE A - DIAGNOSTICO (solo lectura) ========== */

SET NAMES utf8mb4;

SET @EMPRESA_ID := 2;
SET @MODULO_ID  := 4;

SELECT 'A1. Usuarios activos (buscar aqui el usuario_id del login)' AS paso,
       usuario_id, usuario, usuario_nombre, email,
       tabla_estado_registro_id AS estado
  FROM conf__usuarios
 WHERE tabla_estado_registro_id = 1
 ORDER BY usuario_id
 LIMIT 50;

SELECT 'A2. Clientes disponibles de la empresa (buscar aqui el entidad_id)' AS paso,
       entidad_id, entidad_nombre, entidad_fantasia, cuit,
       es_cliente, tabla_estado_registro_id AS estado
  FROM gestion__entidades
 WHERE empresa_id = @EMPRESA_ID
   AND es_cliente = 1
   AND tabla_estado_registro_id = 1
 ORDER BY entidad_nombre
 LIMIT 50;

SELECT 'A3. Vinculos ya cargados en la tienda' AS paso,
       ue.usuario_entidad_id, ue.empresa_id, ue.usuario_id, u.usuario,
       ue.entidad_id, e.entidad_nombre,
       ue.tabla_estado_registro_id AS estado_vinculo,
       e.es_cliente               AS entidad_es_cliente,
       e.tabla_estado_registro_id AS estado_entidad,
       e.empresa_id               AS empresa_de_la_entidad,
       IF(ue.tabla_estado_registro_id <> 1, 'FALLA: vinculo inactivo',
       IF(e.entidad_id IS NULL,             'FALLA: la entidad no existe',
       IF(e.empresa_id <> ue.empresa_id,    'FALLA: la entidad es de otra empresa',
       IF(e.es_cliente <> 1,                'FALLA: la entidad no esta marcada es_cliente',
       IF(e.tabla_estado_registro_id <> 1,  'FALLA: la entidad esta inactiva',
                                            'OK'))))) AS diagnostico
  FROM ecom__usuarios_entidades ue
  LEFT JOIN conf__usuarios     u ON u.usuario_id = ue.usuario_id
  LEFT JOIN gestion__entidades e ON e.entidad_id = ue.entidad_id
 ORDER BY ue.usuario_entidad_id;

SELECT 'A4. Empresa que va a resolver la aplicacion' AS paso,
       em.empresa_id, emp.empresa, em.modulo_id, m.modulo,
       em.tabla_estado_registro_id AS estado,
       IF(em.empresa_id = @EMPRESA_ID, 'coincide con @EMPRESA_ID',
          'DISTINTA de @EMPRESA_ID: el vinculo debe cargarse con ESTE empresa_id') AS nota
  FROM conf__empresas_modulos em
  LEFT JOIN conf__empresas emp ON emp.empresa_id = em.empresa_id
  LEFT JOIN conf__modulos  m   ON m.modulo_id   = em.modulo_id
 WHERE em.modulo_id = @MODULO_ID
   AND em.tabla_estado_registro_id = 1;

/* ================= PARTE B - APLICAR EL VINCULO ================== */

SET @USUARIO_ID := 0;
SET @ENTIDAD_ID := 0;
SET @VENCE      := '2030-12-31';

/* @USUARIO_ID  usuario_id tomado de A1
   @ENTIDAD_ID  entidad_id tomado de A2
   @EMPRESA_ID  ya definido arriba; debe ser el de A4                */

SET @PERFIL_ID := (SELECT empresa_perfil_id FROM conf__empresas_perfiles
                    WHERE empresa_id = @EMPRESA_ID
                      AND modulo_id  = @MODULO_ID
                      AND empresa_perfil_nombre = 'Cliente Web'
                      AND tabla_estado_registro_id = 1
                    LIMIT 1);

SELECT 'B1. Control previo' AS paso,
       @USUARIO_ID AS usuario_id,
       (SELECT usuario FROM conf__usuarios WHERE usuario_id = @USUARIO_ID) AS usuario,
       @ENTIDAD_ID AS entidad_id,
       (SELECT entidad_nombre FROM gestion__entidades WHERE entidad_id = @ENTIDAD_ID) AS cliente,
       @EMPRESA_ID AS empresa_id,
       @PERFIL_ID  AS empresa_perfil_id,
       IF(@USUARIO_ID = 0 OR @ENTIDAD_ID = 0,
          'FALTA: completar @USUARIO_ID y @ENTIDAD_ID antes de ejecutar la PARTE B',
       IF((SELECT COUNT(*) FROM conf__usuarios WHERE usuario_id = @USUARIO_ID AND tabla_estado_registro_id = 1) = 0,
          'ERROR: el usuario no existe o esta inactivo',
       IF((SELECT COUNT(*) FROM gestion__entidades
            WHERE entidad_id = @ENTIDAD_ID AND empresa_id = @EMPRESA_ID
              AND es_cliente = 1 AND tabla_estado_registro_id = 1) = 0,
          'ERROR: la entidad no existe en esa empresa, no es cliente o esta inactiva',
       IF(@PERFIL_ID IS NULL,
          'ERROR: no existe el perfil Cliente Web, ejecutar antes migracion_produccion_completa.sql',
          'OK, se puede aplicar')))) AS validacion;

/* Vinculo usuario -> cliente (lo que resuelve el mensaje del error) */

INSERT INTO ecom__usuarios_entidades (empresa_id, usuario_id, entidad_id, tabla_estado_registro_id)
SELECT @EMPRESA_ID, @USUARIO_ID, @ENTIDAD_ID, 1
 WHERE @USUARIO_ID > 0 AND @ENTIDAD_ID > 0
   AND EXISTS (SELECT 1 FROM conf__usuarios
                WHERE usuario_id = @USUARIO_ID AND tabla_estado_registro_id = 1)
   AND EXISTS (SELECT 1 FROM gestion__entidades
                WHERE entidad_id = @ENTIDAD_ID AND empresa_id = @EMPRESA_ID
                  AND es_cliente = 1 AND tabla_estado_registro_id = 1)
   AND NOT EXISTS (SELECT 1 FROM ecom__usuarios_entidades
                    WHERE empresa_id = @EMPRESA_ID AND usuario_id = @USUARIO_ID);

/* Si el vinculo ya existia pero apuntaba mal o estaba inactivo, se corrige */

UPDATE ecom__usuarios_entidades
   SET entidad_id = @ENTIDAD_ID, tabla_estado_registro_id = 1
 WHERE @USUARIO_ID > 0 AND @ENTIDAD_ID > 0
   AND empresa_id = @EMPRESA_ID
   AND usuario_id = @USUARIO_ID;

/* Perfil Cliente Web vigente para ese usuario */

INSERT INTO conf__usuarios_perfiles
       (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id)
SELECT @USUARIO_ID, @PERFIL_ID, CURDATE(), @VENCE, 1
 WHERE @USUARIO_ID > 0 AND @PERFIL_ID IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                    WHERE usuario_id = @USUARIO_ID
                      AND empresa_perfil_id = @PERFIL_ID
                      AND tabla_estado_registro_id = 1
                      AND fecha_fin >= CURDATE());

/* ================= PARTE C - VERIFICACION ======================== */

SELECT 'C1. Consulta exacta que hace la aplicacion' AS paso,
       e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit
  FROM ecom__usuarios_entidades ue
 INNER JOIN gestion__entidades e
         ON e.entidad_id = ue.entidad_id
        AND e.empresa_id = ue.empresa_id
        AND e.es_cliente = 1
        AND e.tabla_estado_registro_id = 1
 WHERE ue.usuario_id = @USUARIO_ID
   AND ue.empresa_id = @EMPRESA_ID
   AND ue.tabla_estado_registro_id = 1;

SELECT 'C2. Permisos vigentes del usuario' AS paso, pf.codigo_funcion
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
 WHERE up.usuario_id = @USUARIO_ID
   AND up.tabla_estado_registro_id = 1
   AND up.fecha_inicio <= CURDATE()
   AND up.fecha_fin    >= CURDATE()
   AND pf.codigo_funcion LIKE 'ecom.%'
 ORDER BY pf.codigo_funcion;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_sucursales_compra') = 1,
  'SELECT ''C3. Sucursal de compra del cliente (necesaria para confirmar pedidos)'' AS paso, esc.sucursal_id, s.sucursal_nombre, esc.es_principal, esc.f_desde, esc.f_hasta FROM gestion__entidades_sucursales_compra esc LEFT JOIN gestion__sucursales s ON s.sucursal_id = esc.sucursal_id AND s.empresa_id = esc.empresa_id WHERE esc.entidad_id = @ENTIDAD_ID AND esc.empresa_id = @EMPRESA_ID AND esc.tabla_estado_registro_id = 1 AND esc.f_desde <= CURDATE() AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE()) ORDER BY esc.es_principal DESC',
  'SELECT ''C3. FALTA la tabla gestion__entidades_sucursales_compra'' AS paso');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
