-- =====================================================================
-- Matriz legal - M0 (instalación del módulo 3), script 00: VERIFICACIÓN
-- 2026-10-02
-- =====================================================================
-- Qué hace: solo lectura. Corre las consultas V0, V1, V2, V3, V4 y V6 de
-- docs/matriz-legal/08-diseno-m0.md (sección 1). V5 no va acá: corre
-- después de los DROP (script 01). Al final devuelve UN ÚNICO resultado:
-- una fila "OK para seguir - <estado>" o la lista de fallas, una por fila.
--
-- No escribe nada: solo SELECT, SET de variables de sesión y un PREPARE /
-- EXECUTE de nivel superior (sesión) para V2. No crea procedimientos ni
-- tablas TEMPORARY, así que no hace falta el privilegio CREATE ROUTINE.
--
-- Cómo se corre (cliente mysql; aborta ante el primer error):
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261002_ml_m0_00_verificacion.sql
-- No hay USE: trabaja sobre DATABASE(). Si no se indica base, la primera
-- consulta sobre conf__modulos falla con ERROR 1046 (No database selected)
-- y el cliente corta antes de mostrar nada engañoso.
--
-- Se puede correr en cualquier momento de M0 hasta antes del script 02:
--   - antes de 01            -> "OK para seguir - prototipo completo: correr 01"
--   - después de 01          -> "OK para seguir - prototipo ya dado de baja: seguir con 02"
--   - con 01 cortado a mitad -> el estado intermedio que corresponda.
-- Después de 02/03/04 da fallas a propósito (V1: tablas ml__ que no son del
-- prototipo; V6: bloques de ids ocupados): este script es previo a M0.
--
-- De dónde salen los valores esperados (casalucho_produccion.sql, MySQL
-- 8.0.46; iguales en casalucho.sql, MariaDB 10.4.22):
--   - 23 tablas del prototipo: CREATE TABLE en :88556-88999
--     (casalucho.sql :70321-70742). Columnas por tabla contadas en el volcado
--     (huella para no confundir el prototipo con tablas nuevas de igual nombre:
--     10 de los 23 nombres se repiten en el modelo nuevo).
--   - Filas semilla: :88572 (1), :88587 (3), :88629 (6), :88655 (1),
--     :88669 (3), :88744 (4), :88899 (4). TOTAL 22, NO 24: 03:20, 08:24,
--     08:53 y 08:102 dicen 24, pero el desglose de 08:102 suma 22 y los dos
--     volcados tienen 22 filas. Se controla tabla por tabla.
--   - 35 FK, todas entre tablas ml__ (nombres tomados del volcado).
--   - conf__tablas 49-71 (modulo_id 3): :27679-27701 (casalucho.sql :24513-24535).
--   - conf__tablas_estados_registros 83-96 (tablas 49, 50, 51, 52, 53, 55 y
--     66, Activo e Inactivo): :27807-27820 (casalucho.sql :24640-24653).
--   - conf__paginas 63 y 64 (módulo 3, tabla_id 0, sin hijas ni funciones).
--   - Bloques de ids reservados: 07-clasificacion-de-tablas.md:86-98
--     (incluye conf__paginas 300-399 y conf__paginas_funciones 3001-3999,
--     que la consulta V6 de 08:150-161 no tenía). Códigos nuevos: 07:62-79.
--
-- Compatibilidad MySQL 8 / MariaDB 10.4:
--   - SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci: los literales quedan en la
--     collation de la mayoría de las tablas conf__ consultadas. conf__modulos
--     es latin1 (casalucho_produccion.sql:26911): la comparación con el literal
--     ASCII 'ml' funciona igual porque gana la columna (menor coercibilidad).
--   - information_schema tiene otra collation (utf8mb3, distinta por motor):
--     contra literales se compara directo; en los JOIN se convierte con
--     CONVERT(... USING utf8mb4) COLLATE utf8mb4_unicode_ci en los dos lados.
--   - LIKE 'ml\_\_%': el escape con barra funciona porque el sql_mode de la
--     sesión se fija explícitamente SIN NO_BACKSLASH_ESCAPES.
--   - Alias en minúscula para las columnas de information_schema (MySQL 8 las
--     devuelve en MAYÚSCULAS).
--   - Sin CTE, sin funciones de ventana, sin JSON_TABLE ni REGEXP_LIKE.
--
-- V2 y las tablas que pueden no existir: COUNT(*) sobre una tabla inexistente
-- es un error, y el script tiene que poder correr con el prototipo ya dado de
-- baja. Se arma el SQL con GROUP_CONCAT a partir de la lista esperada cruzada
-- con information_schema.tables (COUNT(*) real si la tabla existe, NULL si
-- no) y se ejecuta con PREPARE/EXECUTE de nivel superior, que existe igual en
-- los dos motores y no necesita un procedimiento.
--
-- Resultado final: UNION ALL de SELECTs que devuelven una fila solo cuando
-- algo falla, unido con LEFT JOIN a una fila fija: si no hay fallas queda una
-- sola fila "OK para seguir". Se eligió UNION ALL y no TEMPORARY para que el
-- script no escriba nada, ni siquiera en la sesión.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @m0_old_sql_mode = @@SESSION.sql_mode;
SET @m0_old_gcml     = @@SESSION.group_concat_max_len;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET SESSION group_concat_max_len = 1048576;

-- Variables de V2 en NULL: si el script se repite en la misma sesión
-- (HeidiSQL), un SET que falle no deja el valor de la corrida anterior.
SET @v2_cant_mal = NULL, @v2_detalle = NULL, @v2_filas = NULL, @v2_esperadas = NULL;

-- ---------------------------------------------------------------------
-- V0) Entorno. Solo informa (alimenta el diseño del DDL, 08 sección 7).
-- usuario: en MySQL 8, information_schema.triggers/events/routines solo
-- muestran lo que ese usuario tiene permitido ver (afecta a V3).
-- ---------------------------------------------------------------------
SELECT VERSION()                          AS version_motor,
       DATABASE()                         AS base,
       CURRENT_USER()                     AS usuario,
       @@GLOBAL.sql_mode                  AS sql_mode_global,
       @m0_old_sql_mode                   AS sql_mode_sesion_previo,
       @@SESSION.sql_mode                 AS sql_mode_sesion_script,
       @@explicit_defaults_for_timestamp  AS explicit_defaults_for_timestamp,
       @@character_set_server             AS character_set_server,
       @@collation_server                 AS collation_server,
       @@time_zone                        AS time_zone,
       @@system_time_zone                 AS system_time_zone,
       @@innodb_default_row_format        AS innodb_default_row_format,
       @@lower_case_table_names           AS lower_case_table_names,
       @@foreign_key_checks               AS foreign_key_checks,
       @@log_bin                          AS log_bin,
       @@log_bin_trust_function_creators  AS log_bin_trust_function_creators;

-- Base seleccionada y módulo 3 ("Matriz Legal", modulo_url 'ml', :26932).
-- Si DATABASE() es NULL, esta sentencia falla con ERROR 1046 y el script corta.
SET @v0_base    = DATABASE();
SET @v0_modulo3 = (SELECT COUNT(*) FROM conf__modulos WHERE modulo_id = 3 AND modulo_url = 'ml');

-- ---------------------------------------------------------------------
-- V1) Inventario exacto del prototipo.
--     Falla si: hay tablas ml__ que no son las 23 del prototipo; alguna no
--     es InnoDB (o es una vista); alguna tiene otras columnas (nombre u
--     orden) que en el volcado; o faltan tablas mientras siguen sus filas
--     conf__ (ver V4).
-- ---------------------------------------------------------------------
SET @v1_presentes = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('ml__ambitos_organizacionales','ml__ambitos_tipos','ml__entidades',
           'ml__entidades_tipos','ml__jurisdicciones','ml__jurisdicciones_tipos','ml__normas',
           'ml__normas_ambitos_aplicacion','ml__normas_procedimientos','ml__normas_tipos',
           'ml__obligaciones','ml__obligaciones_jurisdicciones','ml__obligaciones_raci',
           'ml__obligaciones_tipos_entidades','ml__procedimientos','ml__procedimientos_instrucciones',
           'ml__procedimientos_obligaciones','ml__raci_tipos','ml__roles_organizacionales',
           'ml__tareas','ml__tareas_ciclos','ml__tareas_dependencias','ml__tareas_evidencias'));

SET @v1_ajenas = (
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

SET @v1_no_innodb = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name LIKE 'ml\_\_%'
       AND (engine IS NULL OR engine <> 'InnoDB'));

-- Huella: lista ordenada de nombres de columnas de cada tabla del prototipo
-- según el volcado (144 columnas; idéntica en los dos volcados). No alcanza
-- con contar columnas: 10 de los 23 nombres se repiten en el modelo nuevo.
SET @v1_estructura_distinta = (
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

SELECT t.table_name       AS tabla,
       t.table_type       AS tipo,
       t.engine           AS motor,
       t.table_collation  AS collation_tabla,
       CASE WHEN t.table_name IN ('ml__ambitos_organizacionales','ml__ambitos_tipos','ml__entidades',
           'ml__entidades_tipos','ml__jurisdicciones','ml__jurisdicciones_tipos','ml__normas',
           'ml__normas_ambitos_aplicacion','ml__normas_procedimientos','ml__normas_tipos',
           'ml__obligaciones','ml__obligaciones_jurisdicciones','ml__obligaciones_raci',
           'ml__obligaciones_tipos_entidades','ml__procedimientos','ml__procedimientos_instrucciones',
           'ml__procedimientos_obligaciones','ml__raci_tipos','ml__roles_organizacionales',
           'ml__tareas','ml__tareas_ciclos','ml__tareas_dependencias','ml__tareas_evidencias')
            THEN 'del prototipo' ELSE 'NO ESPERADA' END AS clasificacion
  FROM information_schema.tables t
 WHERE t.table_schema = DATABASE()
   AND t.table_name LIKE 'ml\_\_%'
 ORDER BY t.table_name;

SELECT @v1_presentes AS prototipo_presentes_de_23,
       @v1_ajenas AS ml_no_esperadas,
       @v1_no_innodb AS ml_no_innodb,
       @v1_estructura_distinta AS ml_con_otras_columnas;

-- ---------------------------------------------------------------------
-- V2) Datos reales con COUNT(*) (no table_rows), una fila por tabla con lo
--     esperado al lado. Tabla ausente = NULL (no es falla por sí sola: se
--     evalúa con V1 y V4). Falla si una tabla presente difiere de lo esperado.
-- ---------------------------------------------------------------------
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

-- Detalle visible: 23 filas.
SET @v2_ver = CONCAT(
    'SELECT tabla, filas, esperado, ',
    'CASE WHEN filas IS NULL THEN ''tabla ausente'' WHEN filas = esperado THEN ''ok'' ELSE ''FALLA'' END AS resultado ',
    'FROM (', @v2_sql, ') v2 ORDER BY tabla');
PREPARE m0_v2_ver FROM @v2_ver;
EXECUTE m0_v2_ver;
DEALLOCATE PREPARE m0_v2_ver;

-- Resumen a variables, para el resultado final.
SET @v2_resumen = CONCAT(
    'SELECT SUM(CASE WHEN filas IS NOT NULL AND filas <> esperado THEN 1 ELSE 0 END), ',
    'GROUP_CONCAT(CASE WHEN filas IS NOT NULL AND filas <> esperado ',
    'THEN CONCAT(tabla, '' tiene '', filas, '' filas, esperado '', esperado) END ORDER BY tabla SEPARATOR ''|''), ',
    'SUM(filas), SUM(CASE WHEN filas IS NOT NULL THEN esperado ELSE 0 END) ',
    'INTO @v2_cant_mal, @v2_detalle, @v2_filas, @v2_esperadas ',
    'FROM (', @v2_sql, ') v2');
PREPARE m0_v2_res FROM @v2_resumen;
EXECUTE m0_v2_res;
DEALLOCATE PREPARE m0_v2_res;

SELECT @v2_filas AS filas_en_tablas_presentes,
       @v2_esperadas AS esperadas_en_tablas_presentes,
       22 AS esperadas_con_las_23_presentes,
       @v2_cant_mal AS tablas_con_diferencias;

-- ---------------------------------------------------------------------
-- V3) Dependencias fuera del prototipo.
--     Falla si: una FK cruza el límite ml__ (o viene de otro esquema); hay
--     una FK ml__ que no es una de las 35 del volcado; con las 23 tablas
--     presentes no están las 35; o un trigger, vista, rutina o evento menciona
--     ml__. Ojo: ROUTINE_DEFINITION sale NULL sin privilegios sobre la rutina,
--     y en MySQL 8 triggers y eventos solo se listan si el usuario (V0,
--     columna usuario) tiene privilegio sobre ellos: con un usuario sin esos
--     privilegios el 0 no prueba nada (08:124). Correr con el usuario de DDL.
-- ---------------------------------------------------------------------
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

SELECT rc.constraint_schema       AS esquema,
       rc.constraint_name         AS fk,
       rc.table_name              AS tabla,
       rc.unique_constraint_schema AS esquema_referenciado,
       rc.referenced_table_name   AS tabla_referenciada
  FROM information_schema.referential_constraints rc
 WHERE (rc.constraint_schema = DATABASE() AND (rc.table_name LIKE 'ml\_\_%' OR rc.referenced_table_name LIKE 'ml\_\_%'))
    OR (rc.unique_constraint_schema = DATABASE() AND rc.referenced_table_name LIKE 'ml\_\_%')
 ORDER BY rc.table_name, rc.constraint_name;

SELECT 'trigger' AS tipo, trigger_name AS nombre FROM information_schema.triggers
 WHERE trigger_schema = DATABASE() AND (event_object_table LIKE 'ml\_\_%' OR action_statement LIKE '%ml\_\_%')
UNION ALL SELECT 'vista', table_name FROM information_schema.views
 WHERE table_schema = DATABASE() AND view_definition LIKE '%ml\_\_%'
UNION ALL SELECT 'rutina', routine_name FROM information_schema.routines
 WHERE routine_schema = DATABASE() AND routine_definition LIKE '%ml\_\_%'
UNION ALL SELECT 'evento', event_name FROM information_schema.events
 WHERE event_schema = DATABASE() AND event_definition LIKE '%ml\_\_%';

SELECT @v3_fk_internas AS fk_internas_esperado_35_con_las_23,
       @v3_fk_cruzan AS fk_que_cruzan_esperado_0,
       @v3_fk_desconocidas AS fk_desconocidas_esperado_0,
       @v3_objetos AS objetos_que_mencionan_ml_esperado_0;

-- ---------------------------------------------------------------------
-- V4) Referencias en conf__. Filas del prototipo comparadas EXACTAMENTE
--     (id + tabla_nombre + modulo_id 3; id + tabla_id + estado_registro_id).
--     Estados válidos: 23 y 14 (antes de 01) o 0 y 0 (después). Cualquier
--     otra combinación es un estado a medias y falla.
-- ---------------------------------------------------------------------
SET @v4_tablas_proto = (
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

SET @v4_tablas_ajenas_49_71 = (SELECT COUNT(*) FROM conf__tablas WHERE tabla_id BETWEEN 49 AND 71) - @v4_tablas_proto;

SET @v4_otras_m3 = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE (modulo_id = 3 OR tabla_nombre LIKE 'ml\_\_%')
       AND tabla_id NOT BETWEEN 49 AND 71);

SET @v4_estados_proto = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros
     WHERE (tabla_estado_registro_id, tabla_id, estado_registro_id) IN (
           (83,49,1),(84,49,2),(85,50,1),(86,50,2),(87,51,1),(88,51,2),(89,52,1),
           (90,52,2),(91,53,1),(92,53,2),(93,55,1),(94,55,2),(95,66,1),(96,66,2)));

SET @v4_estados_ajenos = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros
     WHERE tabla_estado_registro_id BETWEEN 83 AND 96
        OR tabla_id BETWEEN 49 AND 71) - @v4_estados_proto;

SET @v4_paginas_tabla_proto = (SELECT COUNT(*) FROM conf__paginas WHERE tabla_id BETWEEN 49 AND 71);
SET @v4_paginas_m3          = (SELECT COUNT(*) FROM conf__paginas WHERE modulo_id = 3);
SET @v4_paginas_63_64       = (SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (63, 64) AND modulo_id = 3);
SET @v4_hijas_63_64         = (SELECT COUNT(*) FROM conf__paginas WHERE padre_id IN (63, 64));
SET @v4_funciones_m3        = (SELECT COUNT(*) FROM conf__paginas_funciones pf
                                 JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
                                WHERE p.modulo_id = 3);
SET @v4_perfiles_m3         = (SELECT COUNT(*) FROM conf__perfiles WHERE modulo_id = 3);
SET @v4_emp_perfiles_m3     = (SELECT COUNT(*) FROM conf__empresas_perfiles WHERE modulo_id = 3);
SET @v4_subgrupos_conf      = (SELECT COUNT(*) FROM conf__tablas_comprobantes_subgrupos WHERE tabla_id BETWEEN 49 AND 71);
SET @v4_subgrupos_gestion   = (SELECT COUNT(*) FROM gestion__comprobantes_subgrupos WHERE tabla_id BETWEEN 49 AND 71);

          SELECT 'tablas 49-71 del prototipo (exactas)' AS control, @v4_tablas_proto AS n, '23 o 0' AS esperado
UNION ALL SELECT 'otras filas en conf__tablas 49-71', @v4_tablas_ajenas_49_71, '0'
UNION ALL SELECT 'otras tablas del modulo 3 o ml__', @v4_otras_m3, '0'
UNION ALL SELECT 'estados 83-96 del prototipo (exactos)', @v4_estados_proto, '14 o 0'
UNION ALL SELECT 'otros estados en ids 83-96 o tablas 49-71', @v4_estados_ajenos, '0'
UNION ALL SELECT 'paginas con tabla del prototipo', @v4_paginas_tabla_proto, '0'
UNION ALL SELECT 'paginas del modulo 3', @v4_paginas_m3, '2'
UNION ALL SELECT 'paginas 63 y 64 en el modulo 3', @v4_paginas_63_64, '2'
UNION ALL SELECT 'hijas de 63/64', @v4_hijas_63_64, '0'
UNION ALL SELECT 'funciones del modulo 3', @v4_funciones_m3, '0'
UNION ALL SELECT 'perfiles del modulo 3', @v4_perfiles_m3, '0'
UNION ALL SELECT 'perfiles de empresa del modulo 3', @v4_emp_perfiles_m3, '0'
UNION ALL SELECT 'subgrupos conf', @v4_subgrupos_conf, '0'
UNION ALL SELECT 'subgrupos gestion', @v4_subgrupos_gestion, '0';

-- ---------------------------------------------------------------------
-- V6) Ids y códigos libres (bloques de 07:86-98, códigos de 07:62-79).
--     Falla si algún id de un bloque está ocupado, si ya existe alguno de los
--     12 codigo_estandar nuevos o si ya existe la columna es_final.
-- ---------------------------------------------------------------------
SET @v6_estados        = (SELECT COUNT(*) FROM conf__estados_registros WHERE estado_registro_id BETWEEN 16 AND 27);
SET @v6_codigos        = (SELECT COUNT(*) FROM conf__estados_registros WHERE codigo_estandar IN
                             ('EN_ESPERA','PENDIENTE','EN_CURSO','EN_REVISION','VENCIDO','CUMPLIDO',
                              'CUMPLIDO_FUERA_PLAZO','INCUMPLIDO','EXIMIDO','RECHAZADO','CANCELADO','CON_ERROR'));
SET @v6_tablas         = (SELECT COUNT(*) FROM conf__tablas WHERE tabla_id BETWEEN 201 AND 269);
SET @v6_estados_tabla  = (SELECT COUNT(*) FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id BETWEEN 2001 AND 2200);
SET @v6_perfiles       = (SELECT COUNT(*) FROM conf__perfiles WHERE perfil_id BETWEEN 301 AND 320);
SET @v6_emp_perfiles   = (SELECT COUNT(*) FROM conf__empresas_perfiles WHERE empresa_perfil_id BETWEEN 301 AND 340);
SET @v6_paginas        = (SELECT COUNT(*) FROM conf__paginas WHERE pagina_id BETWEEN 300 AND 399);
SET @v6_funciones      = (SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3001 AND 3999);
SET @v6_es_final       = (SELECT COUNT(*) FROM information_schema.columns
                           WHERE table_schema = DATABASE()
                             AND table_name = 'conf__tablas_estados_registros'
                             AND column_name = 'es_final');

          SELECT 'conf__estados_registros 16-27' AS control, @v6_estados AS ocupados
UNION ALL SELECT 'codigo_estandar nuevos ya usados', @v6_codigos
UNION ALL SELECT 'conf__tablas 201-269', @v6_tablas
UNION ALL SELECT 'conf__tablas_estados_registros 2001-2200', @v6_estados_tabla
UNION ALL SELECT 'conf__perfiles 301-320', @v6_perfiles
UNION ALL SELECT 'conf__empresas_perfiles 301-340', @v6_emp_perfiles
UNION ALL SELECT 'conf__paginas 300-399', @v6_paginas
UNION ALL SELECT 'conf__paginas_funciones 3001-3999', @v6_funciones
UNION ALL SELECT 'columna es_final ya existe', @v6_es_final;

-- ---------------------------------------------------------------------
-- Estado del prototipo (para el mensaje final).
-- ---------------------------------------------------------------------
SET @m0_estado = CASE
    WHEN @v1_presentes = 23 AND @v4_tablas_proto = 23 AND @v4_estados_proto = 14
        THEN 'prototipo completo: correr 01'
    WHEN @v1_presentes = 23 AND @v4_tablas_proto = 0 AND @v4_estados_proto = 0
        THEN 'filas conf__ ya borradas (E3), faltan los DROP: correr 01'
    WHEN @v1_presentes BETWEEN 1 AND 22 AND @v4_tablas_proto = 0 AND @v4_estados_proto = 0
        THEN 'DROP a medias (E4 cortado): volver a correr 01'
    WHEN @v1_presentes = 0 AND @v4_tablas_proto = 0 AND @v4_estados_proto = 0
        THEN 'prototipo ya dado de baja: seguir con 02'
    ELSE 'estado no previsto' END;

-- =====================================================================
-- RESULTADO ÚNICO: "OK para seguir - <estado>" o una fila por falla.
-- =====================================================================
SELECT COALESCE(f.falla, CONCAT('OK para seguir - ', @m0_estado)) AS resultado
  FROM (SELECT 1 AS uno) base
  LEFT JOIN (
              SELECT 1 AS orden, 'V0: no hay base seleccionada' AS falla FROM DUAL WHERE @v0_base IS NULL
    UNION ALL SELECT 2, 'V0: no existe el modulo 3 con modulo_url ml en conf__modulos' FROM DUAL WHERE @v0_modulo3 <> 1
    -- V1
    UNION ALL SELECT 10, CONCAT('V1: hay ', @v1_ajenas, ' tablas ml__ que no son del prototipo (ya se corrio 02?)') FROM DUAL WHERE @v1_ajenas > 0
    UNION ALL SELECT 11, CONCAT('V1: ', @v1_no_innodb, ' tablas ml__ no son InnoDB (o son vistas)') FROM DUAL WHERE @v1_no_innodb > 0
    UNION ALL SELECT 12, CONCAT('V1: ', @v1_estructura_distinta, ' tablas del prototipo con otras columnas (nombre u orden) que en el volcado') FROM DUAL WHERE @v1_estructura_distinta > 0
    UNION ALL SELECT 13, CONCAT('V1: faltan ', 23 - @v1_presentes, ' tablas del prototipo pero siguen sus filas conf__: estado no previsto, revisar a mano') FROM DUAL WHERE @v1_presentes < 23 AND (@v4_tablas_proto > 0 OR @v4_estados_proto > 0)
    -- V2 (una fila por tabla con diferencias)
    UNION ALL SELECT 20 + n.i, CONCAT('V2: ', SUBSTRING_INDEX(SUBSTRING_INDEX(@v2_detalle, '|', n.i), '|', -1))
                FROM (          SELECT 1 AS i UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
                      UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
                      UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
                      UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
                      UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23) n
               WHERE n.i <= @v2_cant_mal
    -- V3
    UNION ALL SELECT 50, CONCAT('V3: ', @v3_fk_cruzan, ' FK cruzan el limite ml__ (o vienen de otro esquema)') FROM DUAL WHERE @v3_fk_cruzan > 0
    UNION ALL SELECT 51, CONCAT('V3: ', @v3_fk_desconocidas, ' FK ml__ que no son de las 35 del volcado') FROM DUAL WHERE @v3_fk_desconocidas > 0
    UNION ALL SELECT 52, CONCAT('V3: con las 23 tablas hay ', @v3_fk_internas, ' FK internas, se esperaban 35') FROM DUAL WHERE @v1_presentes = 23 AND @v3_fk_internas <> 35
    UNION ALL SELECT 53, CONCAT('V3: ', @v3_objetos, ' triggers/vistas/rutinas/eventos mencionan ml__') FROM DUAL WHERE @v3_objetos > 0
    -- V4
    UNION ALL SELECT 60, CONCAT('V4: filas conf__ del prototipo a medias: ', @v4_tablas_proto, ' de 23 tablas y ', @v4_estados_proto, ' de 14 estados')
                FROM DUAL WHERE NOT ((@v4_tablas_proto = 23 AND @v4_estados_proto = 14) OR (@v4_tablas_proto = 0 AND @v4_estados_proto = 0))
    UNION ALL SELECT 61, CONCAT('V4: ', @v4_tablas_ajenas_49_71, ' filas de conf__tablas 49-71 no coinciden con el prototipo') FROM DUAL WHERE @v4_tablas_ajenas_49_71 <> 0
    UNION ALL SELECT 62, CONCAT('V4: ', @v4_otras_m3, ' filas de conf__tablas del modulo 3 o ml__ fuera de 49-71') FROM DUAL WHERE @v4_otras_m3 <> 0
    UNION ALL SELECT 63, CONCAT('V4: ', @v4_estados_ajenos, ' estados de tabla en ids 83-96 o tablas 49-71 no coinciden con el prototipo') FROM DUAL WHERE @v4_estados_ajenos <> 0
    UNION ALL SELECT 64, CONCAT('V4: ', @v4_paginas_tabla_proto, ' paginas apuntan a tablas 49-71') FROM DUAL WHERE @v4_paginas_tabla_proto <> 0
    UNION ALL SELECT 65, CONCAT('V4: el modulo 3 tiene ', @v4_paginas_m3, ' paginas (', @v4_paginas_63_64, ' de 63/64); se esperaban solo 63 y 64') FROM DUAL WHERE @v4_paginas_m3 <> 2 OR @v4_paginas_63_64 <> 2
    UNION ALL SELECT 66, CONCAT('V4: ', @v4_hijas_63_64, ' paginas cuelgan de 63/64') FROM DUAL WHERE @v4_hijas_63_64 <> 0
    UNION ALL SELECT 67, CONCAT('V4: ', @v4_funciones_m3, ' funciones en paginas del modulo 3') FROM DUAL WHERE @v4_funciones_m3 <> 0
    UNION ALL SELECT 68, CONCAT('V4: ', @v4_perfiles_m3, ' perfiles del modulo 3') FROM DUAL WHERE @v4_perfiles_m3 <> 0
    UNION ALL SELECT 69, CONCAT('V4: ', @v4_emp_perfiles_m3, ' perfiles de empresa del modulo 3') FROM DUAL WHERE @v4_emp_perfiles_m3 <> 0
    UNION ALL SELECT 70, CONCAT('V4: ', @v4_subgrupos_conf, ' filas de conf__tablas_comprobantes_subgrupos con tabla 49-71') FROM DUAL WHERE @v4_subgrupos_conf <> 0
    UNION ALL SELECT 71, CONCAT('V4: ', @v4_subgrupos_gestion, ' filas de gestion__comprobantes_subgrupos con tabla 49-71') FROM DUAL WHERE @v4_subgrupos_gestion <> 0
    -- V6
    UNION ALL SELECT 80, CONCAT('V6: ', @v6_estados, ' ids ocupados en conf__estados_registros 16-27') FROM DUAL WHERE @v6_estados <> 0
    UNION ALL SELECT 81, CONCAT('V6: ', @v6_codigos, ' codigo_estandar nuevos ya existen') FROM DUAL WHERE @v6_codigos <> 0
    UNION ALL SELECT 82, CONCAT('V6: ', @v6_tablas, ' ids ocupados en conf__tablas 201-269') FROM DUAL WHERE @v6_tablas <> 0
    UNION ALL SELECT 83, CONCAT('V6: ', @v6_estados_tabla, ' ids ocupados en conf__tablas_estados_registros 2001-2200') FROM DUAL WHERE @v6_estados_tabla <> 0
    UNION ALL SELECT 84, CONCAT('V6: ', @v6_perfiles, ' ids ocupados en conf__perfiles 301-320') FROM DUAL WHERE @v6_perfiles <> 0
    UNION ALL SELECT 85, CONCAT('V6: ', @v6_emp_perfiles, ' ids ocupados en conf__empresas_perfiles 301-340') FROM DUAL WHERE @v6_emp_perfiles <> 0
    UNION ALL SELECT 86, CONCAT('V6: ', @v6_paginas, ' ids ocupados en conf__paginas 300-399') FROM DUAL WHERE @v6_paginas <> 0
    UNION ALL SELECT 87, CONCAT('V6: ', @v6_funciones, ' ids ocupados en conf__paginas_funciones 3001-3999') FROM DUAL WHERE @v6_funciones <> 0
    UNION ALL SELECT 88, 'V6: la columna es_final ya existe en conf__tablas_estados_registros' FROM DUAL WHERE @v6_es_final <> 0
    -- Garantía explícita: un estado no previsto nunca sale como "OK".
    UNION ALL SELECT 99, 'Estado no previsto: revisar a mano' FROM DUAL WHERE @m0_estado = 'estado no previsto'
  ) f ON 1 = 1
 ORDER BY f.orden;

-- Restaurar la sesión.
SET SESSION sql_mode = @m0_old_sql_mode;
SET SESSION group_concat_max_len = @m0_old_gcml;
