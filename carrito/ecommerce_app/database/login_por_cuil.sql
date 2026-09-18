/* ===================================================================
   PS ECOMMERCE B2B - LOGIN POR CUIL/CUIT
   ===================================================================
   Nueva regla de acceso: conf__usuarios.usuario debe ser igual al
   documento de una entidad cliente activa de la empresa.

   Este script NO cambia datos por si solo:
     PARTE A  crea el indice de apoyo y audita quien podra entrar.
     PARTE B  (opcional, hay que descomentarla) renombra el usuario de
              conf__usuarios para que sea el CUIL del cliente.

   Idempotente. Sin DELIMITER ni comentarios de linea.
   =================================================================== */

SET NAMES utf8mb4;

SET @EMPRESA_ID := 2;
SET @COLUMNA    := 'cuil';

/* @COLUMNA debe coincidir con ECOM_ENTIDAD_DOC_COLUMNA del .env.
   Valores admitidos: cuil | cuit                                     */

/* ========== PARTE A1 - LA COLUMNA CONFIGURADA EXISTE? ============= */

SELECT 'A1. Columna de documento' AS paso,
       @COLUMNA AS columna_configurada,
       (SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'gestion__entidades'
           AND column_name = 'cuil') AS existe_cuil,
       (SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'gestion__entidades'
           AND column_name = 'cuit') AS existe_cuit,
       IF((SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'gestion__entidades'
              AND column_name = @COLUMNA) = 0,
          'ERROR: esa columna no existe, corregir @COLUMNA y ECOM_ENTIDAD_DOC_COLUMNA',
          'OK') AS validacion;

/* ========== PARTE A2 - INDICE DE APOYO ============================
   Sin el, cada login hace un full scan de gestion__entidades.        */

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'gestion__entidades'
                   AND column_name = @COLUMNA) = 1
           AND (SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'gestion__entidades'
                   AND index_name = 'idx_entidades_documento_login') = 0,
  CONCAT('CREATE INDEX `idx_entidades_documento_login` ON `gestion__entidades` ',
         '(`empresa_id`, `es_cliente`, `tabla_estado_registro_id`, `', @COLUMNA, '`)'),
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE A3 - DOCUMENTOS DUPLICADOS ======================
   Dos entidades cliente activas con el mismo documento hacen que la
   aplicacion deniegue el acceso a proposito: no puede saber cual de
   las dos representa el usuario. Hay que corregirlo en el ERP.       */

SET @sql := CONCAT(
  'SELECT ''A3. Documentos duplicados (bloquean el login)'' AS paso, ',
  '`', @COLUMNA, '` AS documento, COUNT(*) AS entidades, ',
  'GROUP_CONCAT(entidad_id) AS entidad_ids, GROUP_CONCAT(entidad_nombre SEPARATOR '' | '') AS nombres ',
  'FROM gestion__entidades WHERE empresa_id = ', @EMPRESA_ID, ' ',
  'AND es_cliente = 1 AND tabla_estado_registro_id = 1 AND `', @COLUMNA, '` IS NOT NULL ',
  'GROUP BY `', @COLUMNA, '` HAVING COUNT(*) > 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE A4 - QUIEN PODRA ENTRAR Y QUIEN NO =============== */

SET @sql := CONCAT(
  'SELECT ''A4. Usuarios activos y su acceso al ecommerce'' AS paso, ',
  'u.usuario_id, u.usuario, u.usuario_nombre, ',
  'e.entidad_id, e.entidad_nombre, ',
  'IF(e.entidad_id IS NOT NULL, ''ENTRA'', ',
  '   IF(u.usuario REGEXP ''^[0-9]{11}$'', ',
  '      ''NO ENTRA: no hay cliente activo con ese CUIL'', ',
  '      ''NO ENTRA: el usuario no es un CUIL de 11 digitos'')) AS acceso ',
  'FROM conf__usuarios u ',
  'LEFT JOIN gestion__entidades e ',
  '       ON e.empresa_id = ', @EMPRESA_ID, ' ',
  '      AND e.es_cliente = 1 ',
  '      AND e.tabla_estado_registro_id = 1 ',
  '      AND e.`', @COLUMNA, '` = u.usuario ',
  'WHERE u.tabla_estado_registro_id = 1 ',
  'ORDER BY acceso, u.usuario_id');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE A5 - CLIENTES SIN USUARIO ======================= */

SET @sql := CONCAT(
  'SELECT ''A5. Clientes sin usuario de acceso'' AS paso, ',
  'e.entidad_id, e.entidad_nombre, e.`', @COLUMNA, '` AS documento ',
  'FROM gestion__entidades e ',
  'WHERE e.empresa_id = ', @EMPRESA_ID, ' ',
  '  AND e.es_cliente = 1 AND e.tabla_estado_registro_id = 1 ',
  '  AND e.`', @COLUMNA, '` IS NOT NULL ',
  '  AND NOT EXISTS (SELECT 1 FROM conf__usuarios u ',
  '                   WHERE u.tabla_estado_registro_id = 1 ',
  '                     AND u.usuario = e.`', @COLUMNA, '`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

/* ========== PARTE B - RENOMBRAR UN USUARIO A SU CUIL ==============
   OPCIONAL Y DESTRUCTIVO: cambia con que texto se loguea la persona.
   La contrasena NO cambia. Antes de usarlo, avisarle al usuario.

   Para aplicarlo: completar los dos valores y quitar los delimitadores
   de comentario de las dos lineas marcadas mas abajo.

   Rollback: UPDATE conf__usuarios SET usuario = '<el anterior>'
             WHERE usuario_id = <id>;
   =================================================================== */

SET @USUARIO_ID     := 0;
SET @USUARIO_NUEVO  := '';

SELECT 'B1. Control previo del renombre' AS paso,
       @USUARIO_ID AS usuario_id,
       (SELECT usuario FROM conf__usuarios WHERE usuario_id = @USUARIO_ID) AS usuario_actual,
       @USUARIO_NUEVO AS usuario_nuevo,
       IF(@USUARIO_ID = 0 OR @USUARIO_NUEVO = '',
          'PARTE B sin usar (asi queda si no se completa)',
       IF(@USUARIO_NUEVO NOT REGEXP '^[0-9]{11}$',
          'ERROR: el usuario nuevo debe ser un CUIL de 11 digitos',
       IF((SELECT COUNT(*) FROM conf__usuarios
            WHERE CAST(usuario AS BINARY) = CAST(@USUARIO_NUEVO AS BINARY)
              AND usuario_id <> @USUARIO_ID) > 0,
          'ERROR: ya existe otro usuario con ese nombre',
          'OK, se puede renombrar'))) AS validacion;

/* Quitar los dos delimitadores de esta linea para aplicar el renombre:
UPDATE conf__usuarios SET usuario = @USUARIO_NUEVO
 WHERE usuario_id = @USUARIO_ID AND @USUARIO_ID > 0
   AND @USUARIO_NUEVO REGEXP '^[0-9]{11}$'
   AND NOT EXISTS (SELECT 1 FROM (SELECT usuario, usuario_id FROM conf__usuarios) x
                    WHERE CAST(x.usuario AS BINARY) = CAST(@USUARIO_NUEVO AS BINARY)
                      AND x.usuario_id <> @USUARIO_ID);
*/
