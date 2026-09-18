/* ===================================================================
   PS ECOMMERCE B2B - ACCESO WEB POR TIPO DE CLIENTE
   ===================================================================
   Segunda condicion de acceso, ademas de usuario = CUIL:

     gestion__entidades.entidad_tipo_id
       -> gestion__entidades_clientes_tipos.entidad_cliente_tipo_id
          -> acceso_web = 1

   La aplicacion FALLA CERRADO: si la tabla no existe, si la columna
   acceso_web no existe, si la entidad no tiene tipo, o si acceso_web
   no es exactamente 1, el acceso se deniega.

   PARTE A  verifica y, si hace falta, agrega la columna acceso_web.
   PARTE B  audita quien entra y quien no.
   PARTE C  (opcional) habilita el acceso web de un tipo de cliente.

   Idempotente. Sin DELIMITER ni comentarios de linea.
   =================================================================== */

SET NAMES utf8mb4;

SET @EMPRESA_ID := 2;
SET @COLUMNA    := 'cuil';

/* ========== PARTE A1 - LA TABLA Y LA COLUMNA EXISTEN? ============ */

SELECT 'A1. Estructura requerida' AS paso,
       (SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'gestion__entidades_clientes_tipos') AS existe_tabla,
       (SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'gestion__entidades_clientes_tipos'
           AND column_name = 'entidad_cliente_tipo_id')          AS existe_pk,
       (SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'gestion__entidades_clientes_tipos'
           AND column_name = 'acceso_web')                       AS existe_acceso_web,
       IF((SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'gestion__entidades_clientes_tipos') = 0,
          'ERROR: falta la tabla, nadie va a poder entrar',
          'OK') AS validacion;

/* ========== PARTE A2 - AGREGAR acceso_web SI FALTA ===============
   Solo si la tabla ya existe. Se crea en 0: ningun tipo queda
   habilitado por accidente, hay que habilitarlo a mano (PARTE C).   */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_clientes_tipos') = 1
           AND (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_clientes_tipos'
                   AND column_name = 'acceso_web') = 0,
  'ALTER TABLE `gestion__entidades_clientes_tipos` ADD COLUMN `acceso_web` TINYINT(1) NOT NULL DEFAULT 0',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE A3 - INDICE DE APOYO =========================== */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_clientes_tipos'
                   AND column_name = 'acceso_web') = 1
           AND (SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_clientes_tipos'
                   AND index_name = 'idx_clientes_tipos_acceso_web') = 0,
  'CREATE INDEX `idx_clientes_tipos_acceso_web` ON `gestion__entidades_clientes_tipos` (`entidad_cliente_tipo_id`, `acceso_web`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE B1 - TIPOS DE CLIENTE Y SU ACCESO ============== */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_clientes_tipos'
                   AND column_name = 'acceso_web') = 1,
  'SELECT ''B1. Tipos de cliente'' AS paso, t.entidad_cliente_tipo_id, t.acceso_web, IF(t.acceso_web = 1, ''habilita el ecommerce'', ''NO habilita el ecommerce'') AS efecto, COUNT(e.entidad_id) AS entidades_con_ese_tipo FROM gestion__entidades_clientes_tipos t LEFT JOIN gestion__entidades e ON e.entidad_tipo_id = t.entidad_cliente_tipo_id AND e.es_cliente = 1 AND e.tabla_estado_registro_id = 1 GROUP BY t.entidad_cliente_tipo_id, t.acceso_web ORDER BY t.entidad_cliente_tipo_id',
  'SELECT ''B1. FALTA la tabla o la columna acceso_web'' AS paso');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE B2 - QUIEN ENTRA Y QUIEN NO ==================== */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_clientes_tipos'
                   AND column_name = 'acceso_web') = 0,
  'SELECT ''B2. FALTA la tabla o la columna acceso_web: nadie entra'' AS paso',
  CONCAT(
   'SELECT ''B2. Usuarios activos y su acceso'' AS paso, ',
   'u.usuario_id, u.usuario, u.usuario_nombre, ',
   'e.entidad_id, e.entidad_nombre, e.entidad_tipo_id, t.acceso_web, ',
   'IF(e.entidad_id IS NULL, ',
   '   IF(u.usuario REGEXP ''^[0-9]{11}$'', ',
   '      ''NO ENTRA: no hay cliente activo con ese CUIL'', ',
   '      ''NO ENTRA: el usuario no es un CUIL de 11 digitos''), ',
   '   IF(e.entidad_tipo_id IS NULL, ''NO ENTRA: la entidad no tiene entidad_tipo_id'', ',
   '   IF(t.entidad_cliente_tipo_id IS NULL, ''NO ENTRA: el entidad_tipo_id no existe en la tabla de tipos'', ',
   '   IF(t.acceso_web = 1, ''ENTRA'', ''NO ENTRA: el tipo de cliente tiene acceso_web = 0'')))) AS acceso ',
   'FROM conf__usuarios u ',
   'LEFT JOIN gestion__entidades e ',
   '       ON e.empresa_id = ', @EMPRESA_ID, ' ',
   '      AND e.es_cliente = 1 AND e.tabla_estado_registro_id = 1 ',
   '      AND e.`', @COLUMNA, '` = u.usuario ',
   'LEFT JOIN gestion__entidades_clientes_tipos t ',
   '       ON t.entidad_cliente_tipo_id = e.entidad_tipo_id ',
   'WHERE u.tabla_estado_registro_id = 1 ',
   'ORDER BY acceso, u.usuario_id'));
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE C - HABILITAR UN TIPO DE CLIENTE ===============
   Poner el entidad_cliente_tipo_id que salio en B1 y ejecutar.
   0 = no cambia nada.

   Rollback: UPDATE gestion__entidades_clientes_tipos
                SET acceso_web = 0 WHERE entidad_cliente_tipo_id = <id>;
   =================================================================== */

SET @TIPO_A_HABILITAR := 0;

SET @sql := IF(@TIPO_A_HABILITAR > 0
           AND (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'gestion__entidades_clientes_tipos'
                   AND column_name = 'acceso_web') = 1,
  'UPDATE gestion__entidades_clientes_tipos SET acceso_web = 1 WHERE entidad_cliente_tipo_id = @TIPO_A_HABILITAR',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT 'C1. Resultado' AS paso,
       @TIPO_A_HABILITAR AS tipo_habilitado,
       IF(@TIPO_A_HABILITAR = 0,
          'PARTE C sin usar (completar @TIPO_A_HABILITAR para habilitar un tipo)',
          'Tipo habilitado, revisar B2 volviendo a ejecutar el script') AS nota;
