-- =====================================================================
-- Matriz legal - M0 (instalación del módulo 3), script 01: BAJA DEL PROTOTIPO
-- 2026-10-02
-- =====================================================================
-- Qué hace (docs/matriz-legal/08-diseno-m0.md, sección 1, pasos E3 y E4):
--   E3) En una transacción: borra de conf__tablas_estados_registros las 14
--       filas 83-96 y de conf__tablas las 23 filas 49-71 del prototipo ml__.
--   E4) DROP de las 23 tablas del prototipo, una por sentencia (vía el helper
--       _m0_drop, que solo borra si pasaron los controles), en el orden de
--       las FK (08, "Orden de los DROP"), con FOREIGN_KEY_CHECKS = 1.
--   Al final verifica que no quede nada del prototipo.
-- No toca conf__paginas 63 y 64 (se reutilizan), ni perfiles, ni estados.
--
-- ---------------------------------------------------------------------
-- PASOS MANUALES PREVIOS (no los ejecuta este script)
-- ---------------------------------------------------------------------
-- 0) Ventana de mantenimiento: nadie usando conf/tablas.php, conf/perfiles.php
--    ni conf/estados_registros.php mientras corre.
-- 1) Correr 20261002_ml_m0_00_verificacion.sql y obtener
--    "OK para seguir - prototipo completo: correr 01".
-- 2) Respaldos (08, sección 1, E2). Con el mysqldump del MISMO motor que el
--    servidor: local (MariaDB 10.4) C:\xampp\mysql\bin\mysqldump.exe;
--    producción (MySQL 8.0) el mysqldump 8.0, agregando --set-gtid-purged=OFF
--    (esa opción NO existe en el mysqldump de MariaDB: no agregarla allí).
--    <USUARIO> y <BASE> son placeholders; -p sin valor pide la contraseña
--    (no escribirla en la línea de comandos). <DIR> = carpeta de respaldos.
--
--    2.1) Base completa (red de seguridad):
--    mysqldump -u <USUARIO> -p --single-transaction --no-tablespaces --routines --triggers --events --default-character-set=utf8mb4 <BASE> > <DIR>/20261002_pre_m0_1_completo.sql
--
--    2.2) Las 23 tablas del prototipo, estructura y datos (una fila por INSERT):
--    mysqldump -u <USUARIO> -p --single-transaction --no-tablespaces --skip-extended-insert --complete-insert --default-character-set=utf8mb4 <BASE> ml__ambitos_organizacionales ml__ambitos_tipos ml__entidades ml__entidades_tipos ml__jurisdicciones ml__jurisdicciones_tipos ml__normas ml__normas_ambitos_aplicacion ml__normas_procedimientos ml__normas_tipos ml__obligaciones ml__obligaciones_jurisdicciones ml__obligaciones_raci ml__obligaciones_tipos_entidades ml__procedimientos ml__procedimientos_instrucciones ml__procedimientos_obligaciones ml__raci_tipos ml__roles_organizacionales ml__tareas ml__tareas_ciclos ml__tareas_dependencias ml__tareas_evidencias > <DIR>/20261002_pre_m0_2_ml_prototipo.sql
--
--    2.3) Solo las filas conf__ que se borran (para restaurarlas sin pisar
--    cambios de otros; --complete-insert para que sigan entrando cuando
--    conf__tablas_estados_registros tenga la columna es_final del script 03):
--    mysqldump -u <USUARIO> -p --single-transaction --no-tablespaces --no-create-info --skip-triggers --skip-extended-insert --complete-insert --default-character-set=utf8mb4 --where="tabla_id BETWEEN 49 AND 71" <BASE> conf__tablas conf__tablas_estados_registros > <DIR>/20261002_pre_m0_3_conf_prototipo.sql
--
--    2.4) Las tablas conf__ que M0 modifica, completas (comparar antes/después):
--    mysqldump -u <USUARIO> -p --single-transaction --no-tablespaces --skip-triggers --complete-insert --default-character-set=utf8mb4 <BASE> conf__estados_registros conf__tablas conf__tablas_estados_registros conf__perfiles conf__empresas_perfiles conf__modulos > <DIR>/20261002_pre_m0_4_conf_completo.sql
--
--    Control de los respaldos (Git Bash):
--      grep -c "^CREATE TABLE" <DIR>/20261002_pre_m0_2_ml_prototipo.sql    -> 23
--      grep -c "^INSERT INTO"  <DIR>/20261002_pre_m0_2_ml_prototipo.sql    -> 22 (filas semilla)
--      grep -c "^INSERT INTO"  <DIR>/20261002_pre_m0_3_conf_prototipo.sql  -> 37 (23 + 14)
--    y guardar CHECKSUM TABLE de las tablas del respaldo 2.4.
--
-- ---------------------------------------------------------------------
-- CÓMO SE CORRE
-- ---------------------------------------------------------------------
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261002_ml_m0_01_baja_prototipo.sql
-- Con el cliente mysql (decisión 13 de 03): en modo lote corta en el primer
-- error. NUNCA con --force (seguiría después de un assert fallido).
-- No hay USE: trabaja sobre DATABASE(). Sin base seleccionada, el primer
-- DROP PROCEDURE IF EXISTS (después de los SET de sesión, que no necesitan
-- base) ya falla con ERROR 1046 y no se toca nada; el assert explícito de
-- DATABASE() queda como segunda barrera.
--
-- DELIMITER: los helpers _m0_assert y _m0_drop se crean con DELIMITER $$ ...
-- DELIMITER ;. Es un comando del cliente (no del servidor): lo entienden el
-- cliente mysql y HeidiSQL. Requiere privilegios CREATE ROUTINE y ALTER ROUTINE.
-- lock_wait_timeout de sesión = 60 s: un DROP bloqueado por otra conexión
-- falla con ERROR 1205 (reanudable) en vez de quedar colgado.
--
-- Qué pasa cuando falla un assert (SIGNAL SQLSTATE '45000', ERROR 1644):
--   - Cliente mysql: el script se corta y el cliente sale; al cerrarse la
--     conexión el servidor descarta (ROLLBACK) la transacción abierta de E3.
--     Lo ya hecho por DDL (crear el helper, DROP ya ejecutados) queda: es
--     reanudable. El helper puede quedar creado; la próxima corrida lo
--     recrea y lo borra al final.
--   - HeidiSQL: si está configurado para detenerse ante errores, se detiene
--     pero la sesión sigue abierta CON LA TRANSACCIÓN ABIERTA: ejecutar
--     ROLLBACK; a mano (o cerrar la conexión). Si está configurado para
--     continuar, ni los DELETE ni los DROP hacen nada (barreras @m0_e3_ok y
--     @m0_e4_ok), pero igual: no usar HeidiSQL con "continuar ante errores".
--   Barrera de E4: _m0_drop solo ejecuta el DROP si @m0_e4_ok = 1, que se
--     calcula después de todos los asserts de V1, V2 y V3.
--   Barreras de E3, independientes del cliente:
--     1) Todos los asserts de estado (V2 y V4) van ANTES del primer DELETE.
--     2) Cada DELETE acota por id + tabla_id/estado_registro_id (estados) o
--        por id + tabla_nombre + modulo_id 3 (tablas), y además exige la
--        variable @m0_e3_ok = 1, que solo vale 1 si pasaron todos los
--        controles. El segundo DELETE exige además que el primero haya
--        borrado exactamente 14 filas.
--     3) ROW_COUNT() se controla después de cada DELETE.
--
-- REANUDABLE. Estados posibles y qué hace:
--   filas conf__  tablas ml__   acción
--   23 + 14       23            E3 y E4 completos (caso normal)
--   0 + 0         23 o menos    E3 ya aplicado: lo saltea (ROW_COUNT esperado 0) y sigue con E4
--   0 + 0         0             todo aplicado: no hace nada y verifica
--   a medias      cualquiera    PARA sin tocar nada (no hay forma legítima de llegar ahí:
--                               E3 es una sola transacción)
--   23 + 14       menos de 23   PARA. Este script nunca produce ese estado (E3 va
--                               antes que E4); que falten tablas con sus filas conf__
--                               presentes indica una intervención manual o una
--                               restauración parcial (respaldo 2.3 sin 2.2). Borrar
--                               esas filas "a ciegas" escondería lo que pasó y no se
--                               podría controlar V2 sobre las tablas faltantes. Se
--                               revisa a mano antes de seguir.
--   Además PARA si hay tablas ml__ que no son las 23 del prototipo, o si
--   alguna tiene otra cantidad de columnas que en el volcado: 10 de los 23
--   nombres se repiten en el modelo nuevo (p. ej. ml__normas, ml__entidades),
--   y este script nunca debe borrar tablas creadas por el script 02.
--
-- De dónde salen los ids y los valores esperados (casalucho_produccion.sql,
-- MySQL 8.0.46; iguales en casalucho.sql, MariaDB 10.4.22):
--   - conf__tablas 49-71: :27679-27701 (casalucho.sql :24513-24535).
--   - conf__tablas_estados_registros 83-96: :27807-27820 (casalucho.sql :24640-24653).
--   - Filas semilla por tabla: :88572, :88587, :88629, :88655, :88669, :88744,
--     :88899. TOTAL 22 (los documentos 03 y 08 dicen 24; el desglose suma 22).
--   - 35 FK entre tablas ml__: :88556-88999. Orden de los DROP: 08, sección 1,
--     verificado contra las 35 FK (ninguna tabla se borra antes que sus hijas).
--
-- Compatibilidad MySQL 8 / MariaDB 10.4: sin USE, sin IF NOT EXISTS en
-- índices/columnas, sin CREATE OR REPLACE, sin CTE ni ventanas. Cada DROP
-- hace COMMIT implícito (en MySQL 8 es atómico por sentencia; en MariaDB
-- 10.4 no): por eso va uno por sentencia, con IF EXISTS, fuera de toda
-- transacción. Mensajes de los asserts sin tildes y de menos de 128
-- caracteres (límite de MESSAGE_TEXT).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @m0_old_sql_mode = @@SESSION.sql_mode;
SET @m0_old_fk       = @@SESSION.foreign_key_checks;
SET @m0_old_gcml     = @@SESSION.group_concat_max_len;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET SESSION group_concat_max_len = 1048576;
SET SESSION foreign_key_checks = 1;

-- Un DROP esperando un lock de metadatos (p. ej. una conexión PHP o HeidiSQL
-- con una transacción abierta sobre una tabla ml__) falla a los 60 s con
-- ERROR 1205 en lugar de quedar colgado: el cliente corta y se vuelve a correr.
SET @m0_old_lwt = @@SESSION.lock_wait_timeout;
SET SESSION lock_wait_timeout = 60;

-- Con --safe-updates el cliente podría rechazar los DELETE por tuplas (ERROR 1175).
SET @m0_old_safe = @@SESSION.sql_safe_updates;
SET SESSION sql_safe_updates = 0;

-- Variables de control en cero: si el script se repite en la misma sesión
-- (HeidiSQL), un SET que falle no deja el valor de la corrida anterior.
SET @m0_e3_ok = 0, @m0_e4_ok = 0, @m0_hacer_e3 = 0,
    @v2_cant_mal = NULL, @v2_detalle = NULL, @v2_filas = NULL,
    @p_presentes = NULL, @p_ajenas = NULL, @p_no_innodb = NULL, @p_estructura = NULL,
    @c_tablas = NULL, @c_estados = NULL, @c_restantes = NULL,
    @v3_fk_internas = NULL, @v3_fk_cruzan = NULL, @v3_fk_desconocidas = NULL, @v3_objetos = NULL,
    @m0_rc_estados = NULL, @m0_rc_tablas = NULL;

-- ---------------------------------------------------------------------
-- Helpers temporarios (se borran al final).
-- _m0_assert(condición, mensaje): si la condición es falsa o NULL, corta con
-- SIGNAL SQLSTATE '45000' y el mensaje.
-- _m0_drop(tabla): DROP TABLE IF EXISTS de esa tabla, solo si @m0_e4_ok = 1
-- (segunda barrera de E4: aunque el cliente siguiera después de un assert
-- fallido, ningún DROP se ejecuta).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;
DROP PROCEDURE IF EXISTS `_m0_drop`;

DELIMITER $$

CREATE PROCEDURE `_m0_assert`(IN p_condicion INT, IN p_mensaje VARCHAR(128) CHARACTER SET utf8mb4)
BEGIN
    IF p_condicion IS NULL OR p_condicion = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_mensaje;
    END IF;
END$$

CREATE PROCEDURE `_m0_drop`(IN p_tabla VARCHAR(64))
BEGIN
    IF IFNULL(@m0_e4_ok, 0) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'M0-01 E4: DROP bloqueado (@m0_e4_ok <> 1)';
    END IF;
    SET @m0_drop_sql = CONCAT('DROP TABLE IF EXISTS `', p_tabla, '`');
    PREPARE m0_d FROM @m0_drop_sql;
    EXECUTE m0_d;
    DEALLOCATE PREPARE m0_d;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Entorno
-- ---------------------------------------------------------------------
CALL `_m0_assert`(DATABASE() IS NOT NULL, 'M0-01: no hay base seleccionada (DATABASE() es NULL)');

SET @m0_modulo3 = (SELECT COUNT(*) FROM conf__modulos WHERE modulo_id = 3 AND modulo_url = 'ml');
CALL `_m0_assert`(@m0_modulo3 = 1, 'M0-01: no existe el modulo 3 (modulo_url ml) en conf__modulos');

-- ---------------------------------------------------------------------
-- 1) Diagnóstico del estado (solo lectura). Decide si E3 se aplica o se
--    saltea, y para ante cualquier estado no previsto.
-- ---------------------------------------------------------------------
SET @p_presentes = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('ml__ambitos_organizacionales','ml__ambitos_tipos','ml__entidades',
           'ml__entidades_tipos','ml__jurisdicciones','ml__jurisdicciones_tipos','ml__normas',
           'ml__normas_ambitos_aplicacion','ml__normas_procedimientos','ml__normas_tipos',
           'ml__obligaciones','ml__obligaciones_jurisdicciones','ml__obligaciones_raci',
           'ml__obligaciones_tipos_entidades','ml__procedimientos','ml__procedimientos_instrucciones',
           'ml__procedimientos_obligaciones','ml__raci_tipos','ml__roles_organizacionales',
           'ml__tareas','ml__tareas_ciclos','ml__tareas_dependencias','ml__tareas_evidencias'));

SET @p_ajenas = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name LIKE 'ml\_\_%'
       AND table_name NOT IN ('ml__ambitos_organizacionales','ml__ambitos_tipos','ml__entidades',
           'ml__entidades_tipos','ml__jurisdicciones','ml__jurisdicciones_tipos','ml__normas',
           'ml__normas_ambitos_aplicacion','ml__normas_procedimientos','ml__normas_tipos',
           'ml__obligaciones','ml__obligaciones_jurisdicciones','ml__obligaciones_raci',
           'ml__obligaciones_tipos_entidades','ml__procedimientos','ml__procedimientos_instrucciones',
           'ml__procedimientos_obligaciones','ml__raci_tipos','ml__roles_organizacionales',
           'ml__tareas','ml__tareas_ciclos','ml__tareas_dependencias','ml__tareas_evidencias'));

SET @p_no_innodb = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name LIKE 'ml\_\_%'
       AND (engine IS NULL OR engine <> 'InnoDB'));

-- Huella del prototipo: lista ordenada de nombres de columnas por tabla,
-- según el volcado (idéntica en casalucho_produccion.sql y casalucho.sql).
-- No alcanza con contar columnas: 10 nombres se repiten en el modelo nuevo.
SET @p_estructura = (
    SELECT COUNT(*)
      FROM (          SELECT 'ml__ambitos_organizacionales' AS tabla, 'ambito_id,ambito_tipo_id,ambito_padre_id,codigo,nombre,tabla_estado_registro_id' AS columnas
            UNION ALL SELECT 'ml__ambitos_tipos', 'ambito_tipo_id,ambito_tipo,descripcion,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__entidades', 'entidad_id,empresa_ambito_id,entidad_tipo_id,entidad_padre_id,codigo,nombre,jurisdiccion_id,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__entidades_tipos', 'entidad_tipo_id,entidad_tipo,descripcion,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__jurisdicciones', 'jurisdiccion_id,jurisdiccion_tipo_id,jurisdiccion_padre_id,nombre,codigo,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__jurisdicciones_tipos', 'jurisdiccion_tipo_id,jurisdiccion_tipo,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__normas', 'norma_id,ambito_definicion_id,norma_tipo_id,codigo,titulo,descripcion,organismo_emisor,fecha_vigencia_desde,fecha_vigencia_hasta,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__normas_ambitos_aplicacion', 'norma_ambito_aplicacion_id,norma_id,ambito_id,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__normas_procedimientos', 'norma_procedimiento_id,norma_id,procedimiento_id,observaciones,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__normas_tipos', 'norma_tipo_id,norma_tipo,descripcion,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__obligaciones', 'obligacion_id,empresa_ambito_id,norma_id,codigo,descripcion,tipo_obligacion,frecuencia_tipo,frecuencia_cada,criterio_cumplimiento,tipo_evidencia_requerida,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__obligaciones_jurisdicciones', 'obligacion_jurisdiccion_id,obligacion_id,jurisdiccion_id,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__obligaciones_raci', 'obligacion_raci_id,obligacion_id,rol_organizacional_id,raci_tipo_id,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__obligaciones_tipos_entidades', 'obligacion_tipo_entidad_id,obligacion_id,entidad_tipo_id,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__procedimientos', 'procedimiento_id,ambito_definicion_id,codigo,nombre,objetivo,alcance,version,fecha_aprobacion,area_responsable,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__procedimientos_instrucciones', 'procedimiento_instruccion_id,procedimiento_id,orden,titulo,descripcion_paso,evidencia_requerida,tipo_evidencia_sugerida,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__procedimientos_obligaciones', 'procedimiento_obligacion_id,obligacion_id,procedimiento_id,observaciones,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__raci_tipos', 'raci_tipo_id,codigo,nombre,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__roles_organizacionales', 'rol_organizacional_id,empresa_ambito_id,codigo,nombre,descripcion,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__tareas', 'tarea_id,obligacion_id,entidad_id,ciclo_id,periodo,fecha_inicio,fecha_vencimiento,fecha_cierre,estado_tarea,resultado,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__tareas_ciclos', 'ciclo_id,obligacion_id,entidad_id,frecuencia_tipo,frecuencia_cada,proxima_fecha,activa,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__tareas_dependencias', 'tarea_dependencia_id,tarea_origen_id,tarea_destino_id,condicion,tabla_estado_registro_id'
            UNION ALL SELECT 'ml__tareas_evidencias', 'tarea_evidencia_id,tarea_id,tipo,titulo,url,archivo_nombre,observaciones,fecha_carga,tabla_estado_registro_id') e
      JOIN (SELECT CONVERT(c.table_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS tabla,
                   CONVERT(GROUP_CONCAT(c.column_name ORDER BY c.ordinal_position SEPARATOR ',') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS columnas
              FROM information_schema.columns c
             WHERE c.table_schema = DATABASE()
               AND c.table_name LIKE 'ml\_\_%'
             GROUP BY c.table_name) c
        ON c.tabla = e.tabla COLLATE utf8mb4_unicode_ci
     WHERE c.columnas <> e.columnas COLLATE utf8mb4_unicode_ci);

-- Filas conf__ del prototipo, comparadas exactamente.
SET @c_tablas = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE modulo_id = 3
       AND (tabla_id, tabla_nombre) IN (
           (49,'ml__normas_tipos'),(50,'ml__raci_tipos'),(51,'ml__jurisdicciones_tipos'),
           (52,'ml__entidades_tipos'),(53,'ml__ambitos_tipos'),(54,'ml__ambitos_organizacionales'),
           (55,'ml__jurisdicciones'),(56,'ml__normas'),(57,'ml__normas_ambitos_aplicacion'),
           (58,'ml__procedimientos'),(59,'ml__procedimientos_instrucciones'),(60,'ml__normas_procedimientos'),
           (61,'ml__obligaciones'),(62,'ml__procedimientos_obligaciones'),(63,'ml__obligaciones_jurisdicciones'),
           (64,'ml__obligaciones_tipos_entidades'),(65,'ml__roles_organizacionales'),(66,'ml__obligaciones_raci'),
           (67,'ml__entidades'),(68,'ml__tareas'),(69,'ml__tareas_ciclos'),
           (70,'ml__tareas_dependencias'),(71,'ml__tareas_evidencias')));

SET @c_estados = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros
     WHERE (tabla_estado_registro_id, tabla_id, estado_registro_id) IN (
           (83,49,1),(84,49,2),(85,50,1),(86,50,2),(87,51,1),(88,51,2),(89,52,1),
           (90,52,2),(91,53,1),(92,53,2),(93,55,1),(94,55,2),(95,66,1),(96,66,2)));

SET @m0_hacer_e3 = IF(@c_tablas = 23 AND @c_estados = 14, 1, 0);

SELECT 'diagnostico' AS paso,
       @p_presentes  AS tablas_prototipo_presentes_de_23,
       @p_ajenas     AS tablas_ml_no_esperadas,
       @p_estructura AS tablas_con_otras_columnas,
       @c_tablas     AS filas_conf_tablas_de_23,
       @c_estados    AS filas_conf_estados_de_14,
       IF(@m0_hacer_e3 = 1, 'E3 se aplica', 'E3 se saltea si 0+0') AS e3;

CALL `_m0_assert`(@p_ajenas = 0,     'M0-01 V1: hay tablas ml__ que no son del prototipo (ya corrio 02?). No se toco nada');
CALL `_m0_assert`(@p_no_innodb = 0,  'M0-01 V1: hay tablas ml__ que no son InnoDB o son vistas. No se toco nada');
CALL `_m0_assert`(@p_estructura = 0, 'M0-01 V1: tablas del prototipo con otras columnas (nombre u orden) que en el volcado. No se toco nada');
CALL `_m0_assert`((@c_tablas = 23 AND @c_estados = 14) OR (@c_tablas = 0 AND @c_estados = 0),
                  'M0-01 V4: filas conf__ del prototipo a medias (ni 23+14 ni 0+0). No se toco nada');
CALL `_m0_assert`(NOT (@c_tablas > 0 AND @p_presentes < 23),
                  'M0-01: faltan tablas del prototipo pero siguen sus filas conf__. Revisar a mano');

-- V2 dinámico: COUNT(*) real de cada tabla presente contra su semilla
-- (las ausentes quedan en NULL y no se comparan). Se prepara una vez y se
-- ejecuta en E3 y otra vez justo antes de E4.
SET @v2_sql = (
    SELECT GROUP_CONCAT(
               CONCAT('SELECT ''', e.tabla, ''' AS tabla, ',
                      CASE WHEN i.table_name IS NULL THEN 'NULL'
                           ELSE CONCAT('(SELECT COUNT(*) FROM `', e.tabla, '`)') END,
                      ' AS filas, ', e.esperado, ' AS esperado')
               ORDER BY e.tabla SEPARATOR ' UNION ALL ')
      FROM (          SELECT 'ml__ambitos_organizacionales' AS tabla, 1 AS esperado
            UNION ALL SELECT 'ml__ambitos_tipos', 3
            UNION ALL SELECT 'ml__entidades', 0
            UNION ALL SELECT 'ml__entidades_tipos', 6
            UNION ALL SELECT 'ml__jurisdicciones', 1
            UNION ALL SELECT 'ml__jurisdicciones_tipos', 3
            UNION ALL SELECT 'ml__normas', 0
            UNION ALL SELECT 'ml__normas_ambitos_aplicacion', 0
            UNION ALL SELECT 'ml__normas_procedimientos', 0
            UNION ALL SELECT 'ml__normas_tipos', 4
            UNION ALL SELECT 'ml__obligaciones', 0
            UNION ALL SELECT 'ml__obligaciones_jurisdicciones', 0
            UNION ALL SELECT 'ml__obligaciones_raci', 0
            UNION ALL SELECT 'ml__obligaciones_tipos_entidades', 0
            UNION ALL SELECT 'ml__procedimientos', 0
            UNION ALL SELECT 'ml__procedimientos_instrucciones', 0
            UNION ALL SELECT 'ml__procedimientos_obligaciones', 0
            UNION ALL SELECT 'ml__raci_tipos', 4
            UNION ALL SELECT 'ml__roles_organizacionales', 0
            UNION ALL SELECT 'ml__tareas', 0
            UNION ALL SELECT 'ml__tareas_ciclos', 0
            UNION ALL SELECT 'ml__tareas_dependencias', 0
            UNION ALL SELECT 'ml__tareas_evidencias', 0) e
      LEFT JOIN information_schema.tables i
             ON i.table_schema = DATABASE()
            AND CONVERT(i.table_name USING utf8mb4) COLLATE utf8mb4_unicode_ci = e.tabla COLLATE utf8mb4_unicode_ci);

SET @v2_resumen = CONCAT(
    'SELECT SUM(CASE WHEN filas IS NOT NULL AND filas <> esperado THEN 1 ELSE 0 END), ',
    'GROUP_CONCAT(CASE WHEN filas IS NOT NULL AND filas <> esperado ',
    'THEN CONCAT(tabla, '' '', filas, ''/'', esperado) END ORDER BY tabla SEPARATOR ''; ''), ',
    'SUM(filas) ',
    'INTO @v2_cant_mal, @v2_detalle, @v2_filas ',
    'FROM (', @v2_sql, ') v2');
PREPARE m0_v2 FROM @v2_resumen;

-- =====================================================================
-- E3) Baja de las filas conf__ del prototipo, en transacción.
-- =====================================================================
START TRANSACTION;

-- V2 como assert: ninguna fila fuera de las 22 semillas.
EXECUTE m0_v2;
SELECT 'E3: V2' AS paso, @v2_filas AS filas_en_tablas_presentes, @v2_cant_mal AS tablas_con_diferencias, @v2_detalle AS detalle;
CALL `_m0_assert`(@v2_cant_mal = 0,
                  LEFT(CONCAT('M0-01 V2 (tabla filas/esperado): ', IFNULL(@v2_detalle, '')), 128));

-- V4 como asserts, dentro de la transacción.
SET @e3_tablas  = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE modulo_id = 3
       AND (tabla_id, tabla_nombre) IN (
           (49,'ml__normas_tipos'),(50,'ml__raci_tipos'),(51,'ml__jurisdicciones_tipos'),
           (52,'ml__entidades_tipos'),(53,'ml__ambitos_tipos'),(54,'ml__ambitos_organizacionales'),
           (55,'ml__jurisdicciones'),(56,'ml__normas'),(57,'ml__normas_ambitos_aplicacion'),
           (58,'ml__procedimientos'),(59,'ml__procedimientos_instrucciones'),(60,'ml__normas_procedimientos'),
           (61,'ml__obligaciones'),(62,'ml__procedimientos_obligaciones'),(63,'ml__obligaciones_jurisdicciones'),
           (64,'ml__obligaciones_tipos_entidades'),(65,'ml__roles_organizacionales'),(66,'ml__obligaciones_raci'),
           (67,'ml__entidades'),(68,'ml__tareas'),(69,'ml__tareas_ciclos'),
           (70,'ml__tareas_dependencias'),(71,'ml__tareas_evidencias')));
SET @e3_estados = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros
     WHERE (tabla_estado_registro_id, tabla_id, estado_registro_id) IN (
           (83,49,1),(84,49,2),(85,50,1),(86,50,2),(87,51,1),(88,51,2),(89,52,1),
           (90,52,2),(91,53,1),(92,53,2),(93,55,1),(94,55,2),(95,66,1),(96,66,2)));
SET @e3_tablas_ajenas   = (SELECT COUNT(*) FROM conf__tablas WHERE tabla_id BETWEEN 49 AND 71) - @e3_tablas;
SET @e3_otras_m3        = (SELECT COUNT(*) FROM conf__tablas
                            WHERE (modulo_id = 3 OR tabla_nombre LIKE 'ml\_\_%') AND tabla_id NOT BETWEEN 49 AND 71);
SET @e3_estados_ajenos  = (SELECT COUNT(*) FROM conf__tablas_estados_registros
                            WHERE tabla_estado_registro_id BETWEEN 83 AND 96 OR tabla_id BETWEEN 49 AND 71) - @e3_estados;
SET @e3_paginas_tabla   = (SELECT COUNT(*) FROM conf__paginas WHERE tabla_id BETWEEN 49 AND 71);
SET @e3_paginas_m3      = (SELECT COUNT(*) FROM conf__paginas WHERE modulo_id = 3);
SET @e3_paginas_63_64   = (SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (63, 64) AND modulo_id = 3);
SET @e3_hijas_63_64     = (SELECT COUNT(*) FROM conf__paginas WHERE padre_id IN (63, 64));
SET @e3_funciones_m3    = (SELECT COUNT(*) FROM conf__paginas_funciones pf
                             JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
                            WHERE p.modulo_id = 3);
SET @e3_perfiles_m3     = (SELECT COUNT(*) FROM conf__perfiles WHERE modulo_id = 3);
SET @e3_emp_perfiles_m3 = (SELECT COUNT(*) FROM conf__empresas_perfiles WHERE modulo_id = 3);
SET @e3_subgrupos       = (SELECT COUNT(*) FROM conf__tablas_comprobantes_subgrupos WHERE tabla_id BETWEEN 49 AND 71)
                        + (SELECT COUNT(*) FROM gestion__comprobantes_subgrupos WHERE tabla_id BETWEEN 49 AND 71);

CALL `_m0_assert`(@e3_tablas = @c_tablas AND @e3_estados = @c_estados,
                  'M0-01 V4: las filas conf__ del prototipo cambiaron desde el diagnostico');
CALL `_m0_assert`(@e3_tablas_ajenas = 0,   'M0-01 V4: hay filas ajenas al prototipo en conf__tablas 49-71');
CALL `_m0_assert`(@e3_otras_m3 = 0,        'M0-01 V4: conf__tablas tiene otras filas del modulo 3 o ml__ fuera de 49-71');
CALL `_m0_assert`(@e3_estados_ajenos = 0,  'M0-01 V4: hay estados de tabla ajenos en ids 83-96 o en tablas 49-71');
CALL `_m0_assert`(@e3_paginas_tabla = 0,   'M0-01 V4: hay paginas que apuntan a tablas 49-71');
CALL `_m0_assert`(@e3_paginas_m3 = 2 AND @e3_paginas_63_64 = 2,
                  'M0-01 V4: el modulo 3 no tiene exactamente las paginas 63 y 64');
CALL `_m0_assert`(@e3_hijas_63_64 = 0,     'M0-01 V4: hay paginas hijas de 63/64');
CALL `_m0_assert`(@e3_funciones_m3 = 0,    'M0-01 V4: hay funciones en paginas del modulo 3');
CALL `_m0_assert`(@e3_perfiles_m3 = 0,     'M0-01 V4: hay perfiles del modulo 3');
CALL `_m0_assert`(@e3_emp_perfiles_m3 = 0, 'M0-01 V4: hay perfiles de empresa del modulo 3');
CALL `_m0_assert`(@e3_subgrupos = 0,       'M0-01 V4: hay subgrupos (conf o gestion) con tabla 49-71');

-- Barrera 2: solo vale 1 si E3 corresponde y pasaron todos los controles.
SET @m0_e3_ok = IFNULL(
       @m0_hacer_e3 = 1
   AND @v2_cant_mal = 0
   AND @e3_tablas = 23 AND @e3_estados = 14
   AND @e3_tablas_ajenas = 0 AND @e3_otras_m3 = 0 AND @e3_estados_ajenos = 0
   AND @e3_paginas_tabla = 0 AND @e3_paginas_m3 = 2 AND @e3_paginas_63_64 = 2
   AND @e3_hijas_63_64 = 0 AND @e3_funciones_m3 = 0 AND @e3_perfiles_m3 = 0
   AND @e3_emp_perfiles_m3 = 0 AND @e3_subgrupos = 0
   AND @p_ajenas = 0 AND @p_no_innodb = 0 AND @p_estructura = 0
   AND @p_presentes = 23, 0);

-- Si E3 ya estaba aplicado (0+0), los dos DELETE borran 0 filas y eso es lo esperado.
SET @m0_esperado_estados = IF(@m0_e3_ok = 1, 14, 0);
SET @m0_esperado_tablas  = IF(@m0_e3_ok = 1, 23, 0);

-- Primero los estados de tabla (apuntan a conf__tablas por tabla_id).
DELETE FROM conf__tablas_estados_registros
 WHERE (tabla_estado_registro_id, tabla_id, estado_registro_id) IN (
       (83,49,1),(84,49,2),(85,50,1),(86,50,2),(87,51,1),(88,51,2),(89,52,1),
       (90,52,2),(91,53,1),(92,53,2),(93,55,1),(94,55,2),(95,66,1),(96,66,2))
   AND @m0_e3_ok = 1;
SET @m0_rc_estados = ROW_COUNT();
CALL `_m0_assert`(@m0_rc_estados = @m0_esperado_estados,
                  'M0-01 E3: el DELETE de estados borro una cantidad inesperada; la transaccion se descarta');

DELETE FROM conf__tablas
 WHERE modulo_id = 3
   AND (tabla_id, tabla_nombre) IN (
       (49,'ml__normas_tipos'),(50,'ml__raci_tipos'),(51,'ml__jurisdicciones_tipos'),
       (52,'ml__entidades_tipos'),(53,'ml__ambitos_tipos'),(54,'ml__ambitos_organizacionales'),
       (55,'ml__jurisdicciones'),(56,'ml__normas'),(57,'ml__normas_ambitos_aplicacion'),
       (58,'ml__procedimientos'),(59,'ml__procedimientos_instrucciones'),(60,'ml__normas_procedimientos'),
       (61,'ml__obligaciones'),(62,'ml__procedimientos_obligaciones'),(63,'ml__obligaciones_jurisdicciones'),
       (64,'ml__obligaciones_tipos_entidades'),(65,'ml__roles_organizacionales'),(66,'ml__obligaciones_raci'),
       (67,'ml__entidades'),(68,'ml__tareas'),(69,'ml__tareas_ciclos'),
       (70,'ml__tareas_dependencias'),(71,'ml__tareas_evidencias'))
   AND @m0_e3_ok = 1
   AND @m0_rc_estados = 14;
SET @m0_rc_tablas = ROW_COUNT();
CALL `_m0_assert`(@m0_rc_tablas = @m0_esperado_tablas,
                  'M0-01 E3: el DELETE de conf__tablas borro una cantidad inesperada; la transaccion se descarta');

-- Control dentro de la transacción: no queda nada en los rangos del prototipo.
SET @m0_quedan = (SELECT COUNT(*) FROM conf__tablas WHERE tabla_id BETWEEN 49 AND 71)
               + (SELECT COUNT(*) FROM conf__tablas_estados_registros
                   WHERE tabla_estado_registro_id BETWEEN 83 AND 96 OR tabla_id BETWEEN 49 AND 71);
CALL `_m0_assert`(@m0_quedan = 0, 'M0-01 E3: quedan filas en conf__ 49-71 / 83-96 despues del DELETE');

COMMIT;

SELECT 'E3' AS paso,
       IF(@m0_e3_ok = 1, 'aplicado', 'salteado: ya estaba aplicado') AS resultado,
       @m0_rc_estados AS estados_borrados,
       @m0_rc_tablas  AS tablas_borradas;

-- =====================================================================
-- E4) DROP de las 23 tablas. Cada DROP hace COMMIT implícito: no hay
--     transacción posible. Reanudable por IF EXISTS.
--     Antes, V1 y V3 (y otra vez V2) como asserts.
-- =====================================================================
SET @p_presentes = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('ml__ambitos_organizacionales','ml__ambitos_tipos','ml__entidades',
           'ml__entidades_tipos','ml__jurisdicciones','ml__jurisdicciones_tipos','ml__normas',
           'ml__normas_ambitos_aplicacion','ml__normas_procedimientos','ml__normas_tipos',
           'ml__obligaciones','ml__obligaciones_jurisdicciones','ml__obligaciones_raci',
           'ml__obligaciones_tipos_entidades','ml__procedimientos','ml__procedimientos_instrucciones',
           'ml__procedimientos_obligaciones','ml__raci_tipos','ml__roles_organizacionales',
           'ml__tareas','ml__tareas_ciclos','ml__tareas_dependencias','ml__tareas_evidencias'));
SET @p_ajenas = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name LIKE 'ml\_\_%'
       AND table_name NOT IN ('ml__ambitos_organizacionales','ml__ambitos_tipos','ml__entidades',
           'ml__entidades_tipos','ml__jurisdicciones','ml__jurisdicciones_tipos','ml__normas',
           'ml__normas_ambitos_aplicacion','ml__normas_procedimientos','ml__normas_tipos',
           'ml__obligaciones','ml__obligaciones_jurisdicciones','ml__obligaciones_raci',
           'ml__obligaciones_tipos_entidades','ml__procedimientos','ml__procedimientos_instrucciones',
           'ml__procedimientos_obligaciones','ml__raci_tipos','ml__roles_organizacionales',
           'ml__tareas','ml__tareas_ciclos','ml__tareas_dependencias','ml__tareas_evidencias'));
SET @p_no_innodb = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name LIKE 'ml\_\_%'
       AND (engine IS NULL OR engine <> 'InnoDB'));
SET @c_restantes = (SELECT COUNT(*) FROM conf__tablas WHERE tabla_id BETWEEN 49 AND 71)
                 + (SELECT COUNT(*) FROM conf__tablas_estados_registros
                     WHERE tabla_estado_registro_id BETWEEN 83 AND 96 OR tabla_id BETWEEN 49 AND 71);

-- V3: dependencias.
SET @v3_fk_internas = (
    SELECT COUNT(*) FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE()
       AND unique_constraint_schema = DATABASE()
       AND table_name LIKE 'ml\_\_%'
       AND referenced_table_name LIKE 'ml\_\_%');
SET @v3_fk_cruzan = (
    SELECT COUNT(*) FROM information_schema.referential_constraints
     WHERE ((constraint_schema = DATABASE() AND table_name LIKE 'ml\_\_%')
         OR (unique_constraint_schema = DATABASE() AND referenced_table_name LIKE 'ml\_\_%'))
       AND NOT (constraint_schema = DATABASE()
                AND unique_constraint_schema = DATABASE()
                AND table_name LIKE 'ml\_\_%'
                AND referenced_table_name LIKE 'ml\_\_%'));
SET @v3_fk_desconocidas = (
    SELECT COUNT(*) FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE()
       AND (table_name LIKE 'ml\_\_%' OR referenced_table_name LIKE 'ml\_\_%')
       AND constraint_name NOT IN ('fk_ambito_padre','fk_ambito_tipo','fk_ent_empresa','fk_ent_jur',
           'fk_ent_padre','fk_ent_tipo','fk_jur_padre','fk_jur_tipo','fk_norma_ambito','fk_norma_tipo',
           'fk_norma_aplica_ambito','fk_norma_aplica_norma','fk_np_norma','fk_np_proc','fk_obl_empresa',
           'fk_obl_norma','fk_oj_jur','fk_oj_obl','fk_or_obl','fk_or_raci','fk_or_rol','fk_ote_obl',
           'fk_ote_tipo','fk_proc_ambito','fk_instr_proc','fk_po_obl','fk_po_proc','fk_rol_empresa',
           'fk_tarea_ent','fk_tarea_obl','fk_tc_ent','fk_tc_obl','fk_td_destino','fk_td_origen','fk_te_tarea'));
SET @v3_objetos = (
    SELECT COUNT(*) FROM (
                  SELECT trigger_name AS nombre FROM information_schema.triggers
                   WHERE trigger_schema = DATABASE()
                     AND (event_object_table LIKE 'ml\_\_%' OR action_statement LIKE '%ml\_\_%')
        UNION ALL SELECT table_name FROM information_schema.views
                   WHERE table_schema = DATABASE() AND view_definition LIKE '%ml\_\_%'
        UNION ALL SELECT routine_name FROM information_schema.routines
                   WHERE routine_schema = DATABASE() AND routine_definition LIKE '%ml\_\_%'
        UNION ALL SELECT event_name FROM information_schema.events
                   WHERE event_schema = DATABASE() AND event_definition LIKE '%ml\_\_%'
    ) x);

-- V2 otra vez, sobre las tablas que siguen presentes.
EXECUTE m0_v2;
DEALLOCATE PREPARE m0_v2;

SELECT 'E4: controles previos' AS paso,
       @p_presentes AS tablas_prototipo_presentes,
       @p_ajenas AS tablas_ml_no_esperadas,
       @c_restantes AS filas_conf_restantes,
       @v3_fk_internas AS fk_internas,
       @v3_fk_cruzan AS fk_que_cruzan,
       @v3_fk_desconocidas AS fk_desconocidas,
       @v3_objetos AS objetos_que_mencionan_ml,
       @v2_cant_mal AS v2_tablas_con_diferencias,
       @v2_detalle AS v2_detalle;

CALL `_m0_assert`(@p_ajenas = 0,        'M0-01 V1: hay tablas ml__ que no son del prototipo; no se hace ningun DROP');
CALL `_m0_assert`(@p_no_innodb = 0,     'M0-01 V1: hay tablas ml__ que no son InnoDB o son vistas; no se hace ningun DROP');
CALL `_m0_assert`(@c_restantes = 0,     'M0-01 E4: siguen filas conf__ del prototipo (E3 no se aplico); no se hace ningun DROP');
CALL `_m0_assert`(@v2_cant_mal = 0,
                  LEFT(CONCAT('M0-01 V2 antes de E4 (tabla filas/esperado): ', IFNULL(@v2_detalle, '')), 128));
CALL `_m0_assert`(@v3_fk_cruzan = 0,    'M0-01 V3: hay FK que cruzan el limite ml__ o vienen de otro esquema');
CALL `_m0_assert`(@v3_fk_desconocidas = 0, 'M0-01 V3: hay FK ml__ que no son de las 35 del volcado');
CALL `_m0_assert`(@p_presentes < 23 OR @v3_fk_internas = 35,
                  'M0-01 V3: con las 23 tablas presentes no estan exactamente las 35 FK internas');
CALL `_m0_assert`(@v3_objetos = 0,      'M0-01 V3: hay triggers, vistas, rutinas o eventos que mencionan ml__');

-- Barrera 2 de E4: solo vale 1 si pasaron todos los controles previos.
-- _m0_drop se niega a borrar si no vale 1.
SET @m0_e4_ok = IFNULL(
       @p_ajenas = 0 AND @p_no_innodb = 0 AND @p_estructura = 0 AND @c_restantes = 0
   AND @v2_cant_mal = 0 AND @v3_fk_cruzan = 0 AND @v3_fk_desconocidas = 0
   AND (@p_presentes < 23 OR @v3_fk_internas = 35) AND @v3_objetos = 0, 0);

-- Con los controles de FK activos: si existiera una FK externa que los
-- volcados no muestran, el propio motor frena el DROP.
SET SESSION foreign_key_checks = 1;

-- Cada CALL ejecuta un único DROP TABLE IF EXISTS (una sentencia, un COMMIT
-- implícito por tabla).
-- Nivel 1: nadie las referencia.
CALL `_m0_drop`('ml__tareas_dependencias');
CALL `_m0_drop`('ml__tareas_evidencias');
CALL `_m0_drop`('ml__tareas_ciclos');
CALL `_m0_drop`('ml__normas_ambitos_aplicacion');
CALL `_m0_drop`('ml__normas_procedimientos');
CALL `_m0_drop`('ml__obligaciones_jurisdicciones');
CALL `_m0_drop`('ml__obligaciones_raci');
CALL `_m0_drop`('ml__obligaciones_tipos_entidades');
CALL `_m0_drop`('ml__procedimientos_instrucciones');
CALL `_m0_drop`('ml__procedimientos_obligaciones');
-- Nivel 2: referenciadas solo por el nivel 1.
CALL `_m0_drop`('ml__tareas');
CALL `_m0_drop`('ml__roles_organizacionales');
CALL `_m0_drop`('ml__raci_tipos');
-- Nivel 3.
CALL `_m0_drop`('ml__obligaciones');
CALL `_m0_drop`('ml__procedimientos');
CALL `_m0_drop`('ml__entidades');
-- Nivel 4.
CALL `_m0_drop`('ml__normas');
CALL `_m0_drop`('ml__jurisdicciones');
CALL `_m0_drop`('ml__entidades_tipos');
-- Nivel 5.
CALL `_m0_drop`('ml__normas_tipos');
CALL `_m0_drop`('ml__jurisdicciones_tipos');
CALL `_m0_drop`('ml__ambitos_organizacionales');
-- Nivel 6.
CALL `_m0_drop`('ml__ambitos_tipos');

-- =====================================================================
-- Verificación final: 0 tablas ml__ y 0 filas conf__ del prototipo.
-- =====================================================================
SET @f_ml = (SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name LIKE 'ml\_\_%');
SET @f_conf = (SELECT COUNT(*) FROM conf__tablas WHERE tabla_id BETWEEN 49 AND 71)
            + (SELECT COUNT(*) FROM conf__tablas_estados_registros
                WHERE tabla_estado_registro_id BETWEEN 83 AND 96 OR tabla_id BETWEEN 49 AND 71);

CALL `_m0_assert`(@f_ml = 0,   'M0-01 final: quedan tablas ml__ despues de los DROP');
CALL `_m0_assert`(@f_conf = 0, 'M0-01 final: quedan filas conf__ del prototipo (49-71 / 83-96)');

DROP PROCEDURE IF EXISTS `_m0_assert`;
DROP PROCEDURE IF EXISTS `_m0_drop`;

SET SESSION foreign_key_checks = @m0_old_fk;
SET SESSION sql_mode = @m0_old_sql_mode;
SET SESSION group_concat_max_len = @m0_old_gcml;
SET SESSION lock_wait_timeout = @m0_old_lwt;
SET SESSION sql_safe_updates = @m0_old_safe;

SELECT 'M0-01 terminado: prototipo dado de baja (0 tablas ml__, 0 filas conf__ 49-71 / 83-96). Seguir con 00 y luego 02' AS resultado;
