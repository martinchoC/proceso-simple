-- =====================================================================
-- Matriz legal - M0 (instalación del módulo 3), script 02: DDL DE LAS 69 TABLAS ml__
-- 2026-10-03
-- =====================================================================
-- Qué hace (docs/matriz-legal/08-diseno-m0.md, sección 6; modelo
-- docs/matriz-legal/04-modelo-de-datos.dbml v0.3):
--   0) Controles previos (solo lectura): base seleccionada, tipos y motor de
--      las PK conf__ que se referencian, ninguna tabla ml__ ajena a las 69
--      (p. ej. del prototipo) y, si alguna de las 69 ya existe (corrida
--      anterior cortada), que tenga exactamente las columnas esperadas.
--   A) 69 CREATE TABLE IF NOT EXISTS, SIN FK, con PK, UNIQUE, índices y CHECK.
--   B) Las 149 FK del DBML (99 entre tablas ml__ y 50 hacia conf__: 26 a
--      conf__empresas, 21 a conf__usuarios y 3 a conf__empresas_perfiles),
--      una por CALL al helper _m0_add_fk, que solo la agrega si no existe.
--   V) Verificación final con asserts (ver al final del archivo).
-- No carga datos (sin semillas). No toca ninguna tabla conf__.
--
-- ---------------------------------------------------------------------
-- QUÉ ASUME
-- ---------------------------------------------------------------------
-- - Corre DESPUÉS de 20261002_ml_m0_01_baja_prototipo.sql: no existe
--   ninguna tabla ml__ del prototipo. Si existe alguna tabla ml__ que no es
--   de las 69, el control previo corta sin crear nada.
-- - Si las tablas ml__ del prototipo se borraron a mano (sin el script 01),
--   pueden quedar filas huérfanas en conf__tablas (49-71) y en
--   conf__tablas_estados_registros (83-96). Este script NO las toca ni las
--   mira: solo crea tablas. Se limpian con el 01 (su E3) antes del 03.
-- - conf__empresas.empresa_id smallint unsigned, conf__usuarios.usuario_id
--   int (con signo) y conf__empresas_perfiles.empresa_perfil_id int unsigned,
--   las tres InnoDB (casalucho_produccion.sql:166, :27991 y :211; iguales en
--   casalucho.sql:161, :24822 y :204). Se controla antes de empezar.
--   conf__empresas es latin1: no afecta a FK sobre enteros.
-- - conf__estados_registros NO se referencia (decisión 2 del DBML, 04:9):
--   tabla_estado_registro_id es smallint, sin FK.
--
-- Decisiones de modelado que aplica este script (todas salen del DBML):
-- - Tipos exactos del DBML. INT sin ancho; booleanos TINYINT(1) NOT NULL
--   DEFAULT 0/1; empresa_id SMALLINT UNSIGNED; usuario_id INT;
--   empresa_perfil_id INT UNSIGNED; PK propias INT (con signo) AUTO_INCREMENT.
-- - Auditoría (04:13-16): creado_en TIMESTAMP NOT NULL DEFAULT
--   CURRENT_TIMESTAMP; actualizado_en TIMESTAMP NOT NULL DEFAULT
--   CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP; creado_por y
--   actualizado_por INT NULL sin FK. 66 tablas las tienen. Sin
--   auditoría, como dibuja el DBML: ml__log_cambios y ml__auditoria_accesos
--   (excepción de 04:16) y ml__recalculos_pendientes (04:885-898: creado_en
--   DATETIME NOT NULL sin DEFAULT y sin las otras tres; 04:16 no la nombra
--   como excepción: ver el informe).
-- - Las dos TIMESTAMP con DEFAULT CURRENT_TIMESTAMP en la misma tabla son
--   válidas en MySQL >= 5.6.5 y MariaDB >= 10.0. Como todo se declara
--   explícito (NOT NULL DEFAULT ...), creado_en no recibe el ON UPDATE
--   automático de explicit_defaults_for_timestamp = OFF (MariaDB 10.4).
--   La verificación final lo controla.
-- - tabla_estado_registro_id SMALLINT: procesos NOT NULL sin DEFAULT;
--   diccionarios con ABM NOT NULL DEFAULT 1; relaciones y catálogos fijos
--   sin columna (04:9-12, 07).
-- - Columnas TEXT sin DEFAULT. Las de texto no declaran collation: heredan
--   utf8mb4_unicode_ci de la tabla.
-- - CHECK de vigencia (vigente_hasta IS NULL OR vigente_hasta >=
--   vigente_desde) en las 26 tablas que tienen las dos columnas.
-- - CHECK de ámbito en ml__usuarios_ambitos, ml__normas y
--   ml__accesos_confidenciales: exactamente una de grupo_id,
--   region_grupo_id, empresa_id y territorio_id con valor. El DBML no fija
--   los ids de ml__ambitos_tipos (04:338-346: PK increment, sin semilla), así
--   que el CHECK NO cruza ambito_tipo_id con la columna: esa correspondencia
--   (GRUPO -> grupo_id, REGION_GRUPO -> region_grupo_id, EMPRESA ->
--   empresa_id, TERRITORIO -> territorio_id, 04:340) la valida la aplicación.
--   Las FK de esas columnas no tienen acciones referenciales (MySQL 8 no
--   admite CHECK sobre columnas con FK ON ... CASCADE / SET NULL).
-- - "Una sola abierta" (04:40-41): columna generada STORED con UNIQUE, sin
--   NULL ni NOT NULL, expresión CASE (determinista, portable):
--     ml__territorios_pertenencias.principal_abierta_id = territorio_id si
--       es_principal = 1 y vigente_hasta IS NULL (una principal abierta por
--       territorio).
--     ml__entidades_empresas.titular_abierta_id = entidad_id si
--       es_titular = 1 y vigente_hasta IS NULL (una titular abierta por
--       entidad).
--   Las dos columnas no están dibujadas en el DBML: van después de
--   vigente_hasta. El no solapamiento de vigencias NO se resuelve acá
--   (trigger o aplicación, en el módulo dueño).
-- - Índices: PK, los UNIQUE y los índices del DBML, y uno por cada columna
--   FK que no sea la primera columna de otro índice (InnoDB lo crearía solo,
--   con nombre automático). Ninguna clave supera 767 bytes, así que no
--   depende de innodb_default_row_format (la mayor: uk_obl_norma_codigo,
--   (norma_id, codigo varchar(80)) = 4 + 80*4 + 2 = 326 bytes).
-- - Sin acciones referenciales en ninguna FK (RESTRICT / NO ACTION).
--
-- ---------------------------------------------------------------------
-- NOMBRES (únicos en todo el esquema, <= 64 caracteres)
-- ---------------------------------------------------------------------
--   fk_<abrev>_<columna>        FK (columna completa)
--   ck_<abrev>_<regla>          CHECK (vigencia, ambito)
--   uk_<abrev>_<columnas>       UNIQUE
--   ix_<abrev>_<columnas>       índice no único
-- En uk_/ix_ cada columna se escribe sin el sufijo _id y sin el prefijo
-- vigente_ (p. ej. ix_ereg_empresa_hasta = (empresa_id, vigente_hasta)).
-- Controlado fuera de la base: sin duplicados, ninguno > 64 y ninguno
-- choca con los constraints de los volcados (fk_*, FK_esc_*, *_ibfk_*,
-- gestion__logs_chk_*), comparando sin distinguir mayúsculas.
--
-- Abreviaturas (en el orden de creación):
--   #   tabla                                  abrev
--    1  ml__grupos                             grp
--    2  ml__regiones_grupo                     rgr
--    3  ml__empresas_regiones                  ereg
--    4  ml__empresas_nombres                   enom
--    5  ml__empresas_eventos_tipos             eevt
--    6  ml__empresas_eventos                   eev
--    7  ml__territorios_tipos                  tert
--    8  ml__territorios                        ter
--    9  ml__territorios_pertenencias           terp
--   10  ml__feriados                           fer
--   11  ml__entidades_tipos                    entt
--   12  ml__entidades                          ent
--   13  ml__empresas_grupo                     egr
--   14  ml__entidades_empresas                 ente
--   15  ml__entidades_ubicaciones              entu
--   16  ml__datos_tipos                        datt
--   17  ml__entidades_datos                    entd
--   18  ml__ambitos_tipos                      ambt
--   19  ml__usuarios_ambitos                   usam
--   20  ml__delegaciones                       dele
--   21  ml__opciones                           opc
--   22  ml__opciones_valores                   opcv
--   23  ml__configuracion_grupo                cfgg
--   24  ml__raci_roles                         racr
--   25  ml__organismos                         org
--   26  ml__normas_tipos                       nort
--   27  ml__normas_jerarquias                  norj
--   28  ml__normas                             nor
--   29  ml__normas_versiones                   norv
--   30  ml__normas_relaciones_tipos            nrlt
--   31  ml__normas_relaciones                  nrl
--   32  ml__normas_conflictos                  ncf
--   33  ml__procedimientos                     prc
--   34  ml__procedimientos_versiones           prcv
--   35  ml__procedimientos_instrucciones       prci
--   36  ml__accesos_confidenciales             acco
--   37  ml__auditoria_accesos                  auda
--   38  ml__criticidades                       crit
--   39  ml__periodicidades                     peri
--   40  ml__vencimientos_reglas                venr
--   41  ml__obligaciones                       obl
--   42  ml__obligaciones_tipos_entidades       oblt
--   43  ml__obligaciones_jurisdicciones        oblj
--   44  ml__obligaciones_dependencias          obld
--   45  ml__obligaciones_alertas               obla
--   46  ml__tareas_plantillas                  tpl
--   47  ml__tareas_plantillas_dependencias     tpld
--   48  ml__obligaciones_asignadas             oas
--   49  ml__obligaciones_asignadas_origenes    oaso
--   50  ml__excepciones                        exc
--   51  ml__recalculos_pendientes              recp
--   52  ml__ciclos_vinculos_tipos              cvt
--   53  ml__ciclos                             cic
--   54  ml__ciclos_tareas                      cta
--   55  ml__ciclos_tareas_dependencias         ctad
--   56  ml__obligaciones_asignadas_raci        oasr
--   57  ml__ciclos_raci                        cicr
--   58  ml__ciclos_tareas_raci                 ctar
--   59  ml__reasignaciones                     reas
--   60  ml__evidencias                         evi
--   61  ml__alertas                            ale
--   62  ml__incidencias                        inci
--   63  ml__indicadores_cierres                indc
--   64  ml__cargas_tipos                       cart
--   65  ml__cargas                             car
--   66  ml__cargas_filas                       carf
--   67  ml__cambios_masivos                    cmas
--   68  ml__cambios_masivos_detalles           cmad
--   69  ml__log_cambios                        logc
--
-- ---------------------------------------------------------------------
-- CÓMO SE CORRE
-- ---------------------------------------------------------------------
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261003_ml_m0_02_ddl_tablas.sql
-- Con el cliente mysql (decisión 13 de 03): en modo lote corta en el primer
-- error. NUNCA con --force. No usar HeidiSQL con "continuar ante errores":
-- si un control previo falla y el cliente sigue, la fase A crearía tablas
-- (no destruye nada, pero deja un estado mezclado); la fase B sí queda
-- bloqueada (_m0_add_fk exige @m0_02_ok = 1).
-- No hay USE: trabaja sobre DATABASE(). Sin base seleccionada, el primer
-- DROP PROCEDURE IF EXISTS falla con ERROR 1046 y no se toca nada.
-- Privilegios: CREATE, ALTER, INDEX, REFERENCES (FK hacia conf__),
-- CREATE ROUTINE, ALTER ROUTINE, EXECUTE (por si automatic_sp_privileges
-- está en OFF) y CREATE TEMPORARY TABLES.
-- Orden de M0: 00 -> respaldo -> 01 -> 02 -> 03 -> 04 -> 05.
--
-- REANUDABLE: CREATE TABLE IF NOT EXISTS (una sentencia por tabla) y
-- _m0_add_fk (consulta information_schema y solo agrega si falta). Si se
-- corta a mitad, se vuelve a correr completo: lo ya creado se saltea (con
-- una nota 1050 de "table already exists", que no es error) y lo que
-- falta se crea. Una segunda corrida completa no da errores ni cambia nada.
-- Si una FK con el mismo nombre ya existe con otra definición (otras
-- columnas, otra tabla referenciada o con acciones referenciales),
-- _m0_add_fk corta con un mensaje claro en vez de saltearla.
--
-- CÓMO SE PRUEBA (detalle en 08, sección 6, "Cómo se prueba"): en una base
-- NUEVA importada de un volcado fresco, nunca en una base en uso. Local:
-- MariaDB 10.4.22 de XAMPP. Producción: esquema de prueba en el MySQL 8.0.46.
-- Correr 01 si hace falta, después este script dos veces (la segunda debe
-- terminar igual, sin errores) y las consultas de control.
--
-- Compatibilidad MySQL 8.0.46 / MariaDB 10.4.22: sin USE, sin IF NOT EXISTS
-- en columnas o índices, sin CREATE OR REPLACE, sin JSON, sin CHECK en la
-- definición de la columna (todos a nivel tabla con nombre), sin CTE ni
-- funciones de ventana. Columnas generadas STORED sin NULL/NOT NULL.
-- information_schema se lee convirtiendo cada campo con CONVERT(... USING
-- utf8mb4) y comparando en utf8mb4_bin (sus collations difieren por motor).
-- Mensajes de los asserts sin tildes y de menos de 128 caracteres.
--
-- Firmas que compara la verificación (tabla TEMPORARY _m0_esperado):
--   columnas: "<columna> <tipo>[ unsigned] NN|N[ D=<default>][ AI][ OU][ G]"
--             en orden de posición, separadas por coma. <tipo> es DATA_TYPE
--             (varchar(n), decimal(p,s) y tinyint(1) con su largo); D= solo
--             en columnas NOT NULL; AI auto_increment; OU on update; G generada.
--   índices:  "<nombre>[!](<col>,...)" ordenados por nombre (binario); ! = único.
--   fks:      "<nombre>=<columna>><tabla>.<columna>" ordenadas por nombre.
--   checks:   nombres ordenados.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @m0_old_sql_mode = @@SESSION.sql_mode;
SET @m0_old_fk       = @@SESSION.foreign_key_checks;
SET @m0_old_gcml     = @@SESSION.group_concat_max_len;
SET @m0_old_lwt      = @@SESSION.lock_wait_timeout;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET SESSION group_concat_max_len = 1048576;
-- Con FOREIGN_KEY_CHECKS = 1 el motor valida tipos y referencias al crear
-- cada FK (las tablas están vacías: el costo es nulo).
SET SESSION foreign_key_checks = 1;
-- Un ALTER bloqueado por otra conexión falla a los 60 s (ERROR 1205,
-- reanudable) en vez de quedar colgado. Mientras espera, el ALTER también
-- frena a los lectores nuevos de la tabla padre (en la fase B se baja a 10 s).
SET SESSION lock_wait_timeout = 60;

-- Variables de control en cero/NULL: si el script se repite en la misma
-- sesión, un SET que falle no deja el valor de la corrida anterior.
SET @m0_02_ok = 0,
    @m0_conf_pk = NULL, @m0_conf_pri = NULL, @m0_conf_innodb = NULL,
    @m0_ajenas = NULL, @m0_ajenas_det = NULL, @m0_no_innodb = NULL,
    @m0_existentes = NULL, @m0_pre_mal = NULL, @m0_pre_det = NULL,
    @f_tablas = NULL, @f_motor = NULL, @f_cols_coll = NULL, @f_fk = NULL,
    @f_fk_ml = NULL, @f_fk_conf = NULL, @f_fk_reglas = NULL, @f_ck = NULL,
    @f_gen = NULL, @f_creado_ou = NULL, @f_actualizado_ou = NULL,
    @f_faltan = NULL, @f_cols_mal = NULL, @f_idx_mal = NULL, @f_fks_mal = NULL,
    @f_cks_mal = NULL, @f_detalle = NULL;

-- ---------------------------------------------------------------------
-- Helpers temporarios (se borran al final; si el script corta, la próxima
-- corrida los recrea). Si no se va a volver a correr, borrarlos a mano para
-- que no salgan en los mysqldump --routines:
--   DROP PROCEDURE IF EXISTS `_m0_assert`;
--   DROP PROCEDURE IF EXISTS `_m0_add_column`;
--   DROP PROCEDURE IF EXISTS `_m0_add_fk`;
-- _m0_assert(condición, mensaje): si la condición es falsa o NULL, corta con
--   SIGNAL SQLSTATE '45000' y el mensaje (igual que en 01).
-- _m0_add_column(tabla, columna, definición): ADD COLUMN solo si no existe
--   (modelo: _sync_add_column, 20260927_sync_local_a_produccion.sql:41).
--   Este script no lo usa: queda por pedido del diseño (08, sección 6) para
--   correcciones posteriores dentro de M0.
-- _m0_add_fk(tabla, fk, definición): ADD CONSTRAINT solo si no existe una FK
--   con ese nombre (modelo: _sync_add_fk, mismo archivo :54). Si existe,
--   verifica que esté en esa tabla, con la misma definición y sin acciones
--   referenciales; si no, corta. La definición se escribe siempre con la
--   forma exacta 'FOREIGN KEY (`col`) REFERENCES `tabla` (`col`)', que es la
--   que el helper reconstruye desde information_schema para comparar.
--   Exige @m0_02_ok = 1 (barrera: solo vale 1 si pasaron los controles).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;
DROP PROCEDURE IF EXISTS `_m0_add_column`;
DROP PROCEDURE IF EXISTS `_m0_add_fk`;

DELIMITER $$

CREATE PROCEDURE `_m0_assert`(IN p_condicion INT, IN p_mensaje VARCHAR(128) CHARACTER SET utf8mb4)
BEGIN
    IF p_condicion IS NULL OR p_condicion = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_mensaje;
    END IF;
END$$

CREATE PROCEDURE `_m0_add_column`(IN p_tabla VARCHAR(64) CHARACTER SET utf8mb4, IN p_columna VARCHAR(64) CHARACTER SET utf8mb4,
                                   IN p_definicion TEXT CHARACTER SET utf8mb4)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND CONVERT(TABLE_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_tabla COLLATE utf8mb4_bin
           AND CONVERT(COLUMN_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_columna COLLATE utf8mb4_bin
    ) THEN
        SET @m0_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD COLUMN `', p_columna, '` ', p_definicion);
        PREPARE m0_stmt FROM @m0_sql;
        EXECUTE m0_stmt;
        DEALLOCATE PREPARE m0_stmt;
    END IF;
END$$

CREATE PROCEDURE `_m0_add_fk`(IN p_tabla VARCHAR(64) CHARACTER SET utf8mb4, IN p_fk VARCHAR(64) CHARACTER SET utf8mb4,
                              IN p_definicion VARCHAR(512) CHARACTER SET utf8mb4)
BEGIN
    DECLARE v_tabla  VARCHAR(64)  CHARACTER SET utf8mb4 DEFAULT NULL;
    DECLARE v_def    VARCHAR(512) CHARACTER SET utf8mb4 DEFAULT NULL;
    DECLARE v_reglas INT DEFAULT 0;
    DECLARE v_dup    INT DEFAULT 0;
    DECLARE v_msg    VARCHAR(128) CHARACTER SET utf8mb4 DEFAULT NULL;

    IF IFNULL(@m0_02_ok, 0) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'M0-02 B: FK bloqueada (@m0_02_ok <> 1: fallo un control previo)';
    END IF;

    -- ¿Hay una FK con ese nombre en el esquema (en cualquier tabla)?
    SELECT MIN(CONVERT(tc.TABLE_NAME USING utf8mb4)) INTO v_tabla
      FROM information_schema.TABLE_CONSTRAINTS tc
     WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
       AND CONVERT(tc.CONSTRAINT_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_fk COLLATE utf8mb4_bin
       AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY';

    -- ¿Hay otra FK igual (misma columna y referencia) con otro nombre, p. ej.
    -- una *_ibfk_N creada a mano? Las 149 FK son de una sola columna.
    SELECT COUNT(*) INTO v_dup
      FROM information_schema.KEY_COLUMN_USAGE k
     WHERE k.CONSTRAINT_SCHEMA = DATABASE()
       AND CONVERT(k.TABLE_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_tabla COLLATE utf8mb4_bin
       AND CONVERT(k.CONSTRAINT_NAME USING utf8mb4) COLLATE utf8mb4_bin <> p_fk COLLATE utf8mb4_bin
       AND k.REFERENCED_TABLE_NAME IS NOT NULL
       AND CONCAT('FOREIGN KEY (`', CONVERT(k.COLUMN_NAME USING utf8mb4), '`) REFERENCES `',
                  CONVERT(k.REFERENCED_TABLE_NAME USING utf8mb4), '` (`',
                  CONVERT(k.REFERENCED_COLUMN_NAME USING utf8mb4), '`)') COLLATE utf8mb4_bin = p_definicion COLLATE utf8mb4_bin;
    IF v_dup > 0 THEN
        SET v_msg = LEFT(CONCAT('M0-02 B: ya hay otra FK igual a ', p_fk, ' con otro nombre en ', p_tabla), 128);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    IF v_tabla IS NULL THEN
        SET @m0_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD CONSTRAINT `', p_fk, '` ', p_definicion);
        PREPARE m0_stmt FROM @m0_sql;
        EXECUTE m0_stmt;
        DEALLOCATE PREPARE m0_stmt;
    ELSE
        IF v_tabla COLLATE utf8mb4_bin <> p_tabla COLLATE utf8mb4_bin THEN
            SET v_msg = LEFT(CONCAT('M0-02 B: la FK ', p_fk, ' ya existe en otra tabla: ', v_tabla), 128);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        SELECT CONCAT('FOREIGN KEY (`',
                      GROUP_CONCAT(CONVERT(k.COLUMN_NAME USING utf8mb4) ORDER BY k.ORDINAL_POSITION SEPARATOR '`, `'),
                      '`) REFERENCES `', MIN(CONVERT(k.REFERENCED_TABLE_NAME USING utf8mb4)), '` (`',
                      GROUP_CONCAT(CONVERT(k.REFERENCED_COLUMN_NAME USING utf8mb4) ORDER BY k.ORDINAL_POSITION SEPARATOR '`, `'),
                      '`)')
          INTO v_def
          FROM information_schema.KEY_COLUMN_USAGE k
         WHERE k.CONSTRAINT_SCHEMA = DATABASE()
           AND CONVERT(k.TABLE_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_tabla COLLATE utf8mb4_bin
           AND CONVERT(k.CONSTRAINT_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_fk COLLATE utf8mb4_bin
           AND k.REFERENCED_TABLE_NAME IS NOT NULL;

        SELECT COUNT(*) INTO v_reglas
          FROM information_schema.REFERENTIAL_CONSTRAINTS r
         WHERE r.CONSTRAINT_SCHEMA = DATABASE()
           AND CONVERT(r.TABLE_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_tabla COLLATE utf8mb4_bin
           AND CONVERT(r.CONSTRAINT_NAME USING utf8mb4) COLLATE utf8mb4_bin = p_fk COLLATE utf8mb4_bin
           AND r.DELETE_RULE IN ('RESTRICT', 'NO ACTION')
           AND r.UPDATE_RULE IN ('RESTRICT', 'NO ACTION');

        IF v_def IS NULL
           OR v_def COLLATE utf8mb4_bin <> p_definicion COLLATE utf8mb4_bin
           OR v_reglas <> 1 THEN
            SET v_msg = LEFT(CONCAT('M0-02 B: la FK ', p_fk, ' ya existe con otra definicion o con acciones'), 128);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        -- Misma definición: ya estaba aplicada (reanudación), no se hace nada.
    END IF;
END$$

DELIMITER ;

CALL `_m0_assert`(DATABASE() IS NOT NULL, 'M0-02: no hay base seleccionada (DATABASE() es NULL)');

-- ---------------------------------------------------------------------
-- Valores esperados por tabla (generados a partir del DBML v0.3; ver
-- "Firmas" en la cabecera). Tabla TEMPORARY de la sesion: se usa en el
-- control previo y en la verificacion final, y se borra al terminar.
-- ---------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `_m0_esperado`;
CREATE TEMPORARY TABLE `_m0_esperado` (
  `tabla`    VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `columnas` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `indices`  TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `fks`      TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `checks`   TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`tabla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

INSERT INTO `_m0_esperado` (`tabla`, `columnas`, `indices`, `fks`, `checks`) VALUES
('ml__grupos',
 'grupo_id int NN AI,empresa_titular_id smallint unsigned NN,nombre varchar(150) NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(grupo_id);ix_grp_empresa_titular(empresa_titular_id)',
 'fk_grp_empresa_titular_id=empresa_titular_id>conf__empresas.empresa_id',
 ''),
('ml__regiones_grupo',
 'region_grupo_id int NN AI,grupo_id int NN,nombre varchar(150) NN,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(region_grupo_id);ix_rgr_grupo(grupo_id)',
 'fk_rgr_grupo_id=grupo_id>ml__grupos.grupo_id',
 'ck_rgr_vigencia'),
('ml__empresas_regiones',
 'empresa_region_id int NN AI,empresa_id smallint unsigned NN,region_grupo_id int NN,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(empresa_region_id);ix_ereg_empresa_hasta(empresa_id,vigente_hasta);ix_ereg_region_grupo(region_grupo_id)',
 'fk_ereg_empresa_id=empresa_id>conf__empresas.empresa_id;fk_ereg_region_grupo_id=region_grupo_id>ml__regiones_grupo.region_grupo_id',
 'ck_ereg_vigencia'),
('ml__empresas_nombres',
 'empresa_nombre_id int NN AI,empresa_id smallint unsigned NN,razon_social varchar(200) NN,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(empresa_nombre_id);ix_enom_empresa_hasta(empresa_id,vigente_hasta)',
 'fk_enom_empresa_id=empresa_id>conf__empresas.empresa_id',
 'ck_enom_vigencia'),
('ml__empresas_eventos_tipos',
 'empresa_evento_tipo_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(empresa_evento_tipo_id);uk_eevt_codigo!(codigo)',
 '',
 ''),
('ml__empresas_eventos',
 'empresa_evento_id int NN AI,empresa_evento_tipo_id int NN,empresa_origen_id smallint unsigned NN,empresa_destino_id smallint unsigned N,fecha date NN,observaciones text N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(empresa_evento_id);ix_eev_empresa_destino(empresa_destino_id);ix_eev_empresa_evento_tipo(empresa_evento_tipo_id);ix_eev_empresa_origen(empresa_origen_id)',
 'fk_eev_empresa_destino_id=empresa_destino_id>conf__empresas.empresa_id;fk_eev_empresa_evento_tipo_id=empresa_evento_tipo_id>ml__empresas_eventos_tipos.empresa_evento_tipo_id;fk_eev_empresa_origen_id=empresa_origen_id>conf__empresas.empresa_id',
 ''),
('ml__territorios_tipos',
 'territorio_tipo_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(territorio_tipo_id);uk_tert_codigo!(codigo)',
 '',
 ''),
('ml__territorios',
 'territorio_id int NN AI,territorio_tipo_id int NN,codigo varchar(50) N,nombre varchar(150) NN,huso_horario varchar(64) N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(territorio_id);ix_ter_territorio_tipo(territorio_tipo_id)',
 'fk_ter_territorio_tipo_id=territorio_tipo_id>ml__territorios_tipos.territorio_tipo_id',
 ''),
('ml__territorios_pertenencias',
 'territorio_pertenencia_id int NN AI,territorio_id int NN,territorio_superior_id int NN,es_principal tinyint(1) NN D=0,vigente_desde date NN,vigente_hasta date N,principal_abierta_id int N G,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(territorio_pertenencia_id);ix_terp_territorio_hasta(territorio_id,vigente_hasta);ix_terp_territorio_superior_hasta(territorio_superior_id,vigente_hasta);uk_terp_principal_abierta!(principal_abierta_id)',
 'fk_terp_territorio_id=territorio_id>ml__territorios.territorio_id;fk_terp_territorio_superior_id=territorio_superior_id>ml__territorios.territorio_id',
 'ck_terp_vigencia'),
('ml__feriados',
 'feriado_id int NN AI,territorio_id int NN,fecha date NN,nombre varchar(150) NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(feriado_id);uk_fer_territorio_fecha!(territorio_id,fecha)',
 'fk_fer_territorio_id=territorio_id>ml__territorios.territorio_id',
 ''),
('ml__entidades_tipos',
 'entidad_tipo_id int NN AI,grupo_id int NN,empresa_id smallint unsigned N,codigo varchar(50) NN,nombre varchar(150) NN,es_fisica tinyint(1) NN D=1,es_logica tinyint(1) NN D=0,es_movil tinyint(1) NN D=0,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(entidad_tipo_id);ix_entt_empresa(empresa_id);ix_entt_grupo_empresa_codigo(grupo_id,empresa_id,codigo)',
 'fk_entt_empresa_id=empresa_id>conf__empresas.empresa_id;fk_entt_grupo_id=grupo_id>ml__grupos.grupo_id',
 ''),
('ml__entidades',
 'entidad_id int NN AI,entidad_tipo_id int NN,entidad_padre_id int N,codigo varchar(80) NN,nombre varchar(200) NN,descripcion text N,es_raiz tinyint(1) NN D=0,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(entidad_id);ix_ent_codigo(codigo);ix_ent_entidad_padre(entidad_padre_id);ix_ent_entidad_tipo(entidad_tipo_id)',
 'fk_ent_entidad_padre_id=entidad_padre_id>ml__entidades.entidad_id;fk_ent_entidad_tipo_id=entidad_tipo_id>ml__entidades_tipos.entidad_tipo_id',
 'ck_ent_vigencia'),
('ml__empresas_grupo',
 'empresa_grupo_id int NN AI,empresa_id smallint unsigned NN,grupo_id int NN,pais_territorio_id int NN,entidad_raiz_id int N,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(empresa_grupo_id);ix_egr_empresa(empresa_id);ix_egr_entidad_raiz(entidad_raiz_id);ix_egr_grupo(grupo_id);ix_egr_pais_territorio(pais_territorio_id)',
 'fk_egr_empresa_id=empresa_id>conf__empresas.empresa_id;fk_egr_entidad_raiz_id=entidad_raiz_id>ml__entidades.entidad_id;fk_egr_grupo_id=grupo_id>ml__grupos.grupo_id;fk_egr_pais_territorio_id=pais_territorio_id>ml__territorios.territorio_id',
 'ck_egr_vigencia'),
('ml__entidades_empresas',
 'entidad_empresa_id int NN AI,entidad_id int NN,empresa_id smallint unsigned NN,es_titular tinyint(1) NN D=1,vigente_desde date NN,vigente_hasta date N,titular_abierta_id int N G,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(entidad_empresa_id);ix_ente_empresa_hasta(empresa_id,vigente_hasta);ix_ente_entidad_hasta(entidad_id,vigente_hasta);uk_ente_titular_abierta!(titular_abierta_id)',
 'fk_ente_empresa_id=empresa_id>conf__empresas.empresa_id;fk_ente_entidad_id=entidad_id>ml__entidades.entidad_id',
 'ck_ente_vigencia'),
('ml__entidades_ubicaciones',
 'entidad_ubicacion_id int NN AI,entidad_id int NN,territorio_id int NN,es_base tinyint(1) NN D=1,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(entidad_ubicacion_id);ix_entu_entidad_es_base_hasta(entidad_id,es_base,vigente_hasta);ix_entu_territorio_hasta(territorio_id,vigente_hasta)',
 'fk_entu_entidad_id=entidad_id>ml__entidades.entidad_id;fk_entu_territorio_id=territorio_id>ml__territorios.territorio_id',
 'ck_entu_vigencia'),
('ml__datos_tipos',
 'dato_tipo_id int NN AI,codigo varchar(50) NN,nombre varchar(150) NN,tipo_valor varchar(10) NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(dato_tipo_id);uk_datt_codigo!(codigo)',
 '',
 ''),
('ml__entidades_datos',
 'entidad_dato_id int NN AI,entidad_id int NN,dato_tipo_id int NN,valor_numero decimal(18,4) N,valor_texto varchar(255) N,valor_fecha date N,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(entidad_dato_id);ix_entd_dato_tipo(dato_tipo_id);ix_entd_entidad_dato_tipo_hasta(entidad_id,dato_tipo_id,vigente_hasta)',
 'fk_entd_dato_tipo_id=dato_tipo_id>ml__datos_tipos.dato_tipo_id;fk_entd_entidad_id=entidad_id>ml__entidades.entidad_id',
 'ck_entd_vigencia'),
('ml__ambitos_tipos',
 'ambito_tipo_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(ambito_tipo_id);uk_ambt_codigo!(codigo)',
 '',
 ''),
('ml__usuarios_ambitos',
 'usuario_ambito_id int NN AI,usuario_id int NN,empresa_perfil_id int unsigned NN,ambito_tipo_id int NN,grupo_id int N,region_grupo_id int N,empresa_id smallint unsigned N,territorio_id int N,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(usuario_ambito_id);ix_usam_ambito_tipo(ambito_tipo_id);ix_usam_empresa(empresa_id);ix_usam_empresa_perfil(empresa_perfil_id);ix_usam_grupo(grupo_id);ix_usam_region_grupo(region_grupo_id);ix_usam_territorio(territorio_id);ix_usam_usuario_hasta(usuario_id,vigente_hasta)',
 'fk_usam_ambito_tipo_id=ambito_tipo_id>ml__ambitos_tipos.ambito_tipo_id;fk_usam_empresa_id=empresa_id>conf__empresas.empresa_id;fk_usam_empresa_perfil_id=empresa_perfil_id>conf__empresas_perfiles.empresa_perfil_id;fk_usam_grupo_id=grupo_id>ml__grupos.grupo_id;fk_usam_region_grupo_id=region_grupo_id>ml__regiones_grupo.region_grupo_id;fk_usam_territorio_id=territorio_id>ml__territorios.territorio_id;fk_usam_usuario_id=usuario_id>conf__usuarios.usuario_id',
 'ck_usam_ambito;ck_usam_vigencia'),
('ml__delegaciones',
 'delegacion_id int NN AI,usuario_id int NN,usuario_suplente_id int NN,empresa_perfil_id int unsigned N,motivo varchar(255) N,vigente_desde date NN,vigente_hasta date NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(delegacion_id);ix_dele_empresa_perfil(empresa_perfil_id);ix_dele_usuario(usuario_id);ix_dele_usuario_suplente(usuario_suplente_id)',
 'fk_dele_empresa_perfil_id=empresa_perfil_id>conf__empresas_perfiles.empresa_perfil_id;fk_dele_usuario_id=usuario_id>conf__usuarios.usuario_id;fk_dele_usuario_suplente_id=usuario_suplente_id>conf__usuarios.usuario_id',
 'ck_dele_vigencia'),
('ml__opciones',
 'opcion_id int NN AI,codigo varchar(50) NN,nombre varchar(150) NN,nivel varchar(20) NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(opcion_id);uk_opc_codigo!(codigo)',
 '',
 ''),
('ml__opciones_valores',
 'opcion_valor_id int NN AI,opcion_id int NN,codigo varchar(50) NN,nombre varchar(200) NN,es_defecto tinyint(1) NN D=0,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(opcion_valor_id);uk_opcv_opcion_codigo!(opcion_id,codigo)',
 'fk_opcv_opcion_id=opcion_id>ml__opciones.opcion_id',
 ''),
('ml__configuracion_grupo',
 'configuracion_grupo_id int NN AI,grupo_id int NN,opcion_id int NN,opcion_valor_id int NN,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(configuracion_grupo_id);ix_cfgg_grupo_opcion_hasta(grupo_id,opcion_id,vigente_hasta);ix_cfgg_opcion(opcion_id);ix_cfgg_opcion_valor(opcion_valor_id)',
 'fk_cfgg_grupo_id=grupo_id>ml__grupos.grupo_id;fk_cfgg_opcion_id=opcion_id>ml__opciones.opcion_id;fk_cfgg_opcion_valor_id=opcion_valor_id>ml__opciones_valores.opcion_valor_id',
 'ck_cfgg_vigencia'),
('ml__raci_roles',
 'raci_rol_id int NN AI,codigo varchar(1) NN,nombre varchar(100) NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(raci_rol_id);uk_racr_codigo!(codigo)',
 '',
 ''),
('ml__organismos',
 'organismo_id int NN AI,nombre varchar(200) NN,territorio_id int N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(organismo_id);ix_org_territorio(territorio_id)',
 'fk_org_territorio_id=territorio_id>ml__territorios.territorio_id',
 ''),
('ml__normas_tipos',
 'norma_tipo_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,es_legal tinyint(1) NN D=0,genera_obligaciones tinyint(1) NN D=1,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(norma_tipo_id);uk_nort_codigo!(codigo)',
 '',
 ''),
('ml__normas_jerarquias',
 'norma_jerarquia_id int NN AI,nombre varchar(100) NN,nivel int NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(norma_jerarquia_id)',
 '',
 ''),
('ml__normas',
 'norma_id int NN AI,norma_tipo_id int NN,norma_jerarquia_id int N,organismo_id int N,codigo varchar(80) NN,titulo varchar(255) NN,descripcion text N,idioma_original varchar(10) N,titulo_traducido varchar(255) N,idioma_traducido varchar(10) N,ambito_tipo_id int NN,grupo_id int N,region_grupo_id int N,empresa_id smallint unsigned N,territorio_id int N,requiere_norma_local tinyint(1) NN D=0,confidencial tinyint(1) NN D=0,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(norma_id);ix_nor_ambito_tipo_territorio(ambito_tipo_id,territorio_id);ix_nor_codigo(codigo);ix_nor_empresa(empresa_id);ix_nor_grupo(grupo_id);ix_nor_norma_jerarquia(norma_jerarquia_id);ix_nor_norma_tipo(norma_tipo_id);ix_nor_organismo(organismo_id);ix_nor_region_grupo(region_grupo_id);ix_nor_territorio(territorio_id)',
 'fk_nor_ambito_tipo_id=ambito_tipo_id>ml__ambitos_tipos.ambito_tipo_id;fk_nor_empresa_id=empresa_id>conf__empresas.empresa_id;fk_nor_grupo_id=grupo_id>ml__grupos.grupo_id;fk_nor_norma_jerarquia_id=norma_jerarquia_id>ml__normas_jerarquias.norma_jerarquia_id;fk_nor_norma_tipo_id=norma_tipo_id>ml__normas_tipos.norma_tipo_id;fk_nor_organismo_id=organismo_id>ml__organismos.organismo_id;fk_nor_region_grupo_id=region_grupo_id>ml__regiones_grupo.region_grupo_id;fk_nor_territorio_id=territorio_id>ml__territorios.territorio_id',
 'ck_nor_ambito;ck_nor_vigencia'),
('ml__normas_versiones',
 'norma_version_id int NN AI,norma_id int NN,version varchar(50) NN,fecha_publicacion date N,archivo_ref varchar(500) N,hash varchar(128) N,es_vigente tinyint(1) NN D=0,confidencial tinyint(1) NN D=0,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(norma_version_id);uk_norv_norma_version!(norma_id,version)',
 'fk_norv_norma_id=norma_id>ml__normas.norma_id',
 'ck_norv_vigencia'),
('ml__normas_relaciones_tipos',
 'norma_relacion_tipo_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(norma_relacion_tipo_id);uk_nrlt_codigo!(codigo)',
 '',
 ''),
('ml__normas_relaciones',
 'norma_relacion_id int NN AI,norma_origen_id int NN,norma_destino_id int NN,norma_relacion_tipo_id int NN,descripcion text N,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(norma_relacion_id);ix_nrl_norma_destino(norma_destino_id);ix_nrl_norma_origen(norma_origen_id);ix_nrl_norma_relacion_tipo(norma_relacion_tipo_id)',
 'fk_nrl_norma_destino_id=norma_destino_id>ml__normas.norma_id;fk_nrl_norma_origen_id=norma_origen_id>ml__normas.norma_id;fk_nrl_norma_relacion_tipo_id=norma_relacion_tipo_id>ml__normas_relaciones_tipos.norma_relacion_tipo_id',
 'ck_nrl_vigencia'),
('ml__normas_conflictos',
 'norma_conflicto_id int NN AI,norma_a_id int NN,norma_b_id int NN,descripcion text NN,escalado_a int N,decision text N,decidido_por int N,decidido_en datetime N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(norma_conflicto_id);ix_ncf_decidido_por(decidido_por);ix_ncf_escalado_a(escalado_a);ix_ncf_norma_a(norma_a_id);ix_ncf_norma_b(norma_b_id)',
 'fk_ncf_decidido_por=decidido_por>conf__usuarios.usuario_id;fk_ncf_escalado_a=escalado_a>conf__usuarios.usuario_id;fk_ncf_norma_a_id=norma_a_id>ml__normas.norma_id;fk_ncf_norma_b_id=norma_b_id>ml__normas.norma_id',
 ''),
('ml__procedimientos',
 'procedimiento_id int NN AI,empresa_id smallint unsigned N,norma_id int N,codigo varchar(80) NN,nombre varchar(255) NN,descripcion text N,confidencial tinyint(1) NN D=0,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(procedimiento_id);ix_prc_empresa(empresa_id);ix_prc_norma(norma_id)',
 'fk_prc_empresa_id=empresa_id>conf__empresas.empresa_id;fk_prc_norma_id=norma_id>ml__normas.norma_id',
 ''),
('ml__procedimientos_versiones',
 'procedimiento_version_id int NN AI,procedimiento_id int NN,version varchar(50) NN,archivo_ref varchar(500) N,hash varchar(128) N,resumen_cambios text N,aprobado_por int N,aprobado_en datetime N,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(procedimiento_version_id);ix_prcv_aprobado_por(aprobado_por);uk_prcv_procedimiento_version!(procedimiento_id,version)',
 'fk_prcv_aprobado_por=aprobado_por>conf__usuarios.usuario_id;fk_prcv_procedimiento_id=procedimiento_id>ml__procedimientos.procedimiento_id',
 'ck_prcv_vigencia'),
('ml__procedimientos_instrucciones',
 'instruccion_id int NN AI,procedimiento_version_id int NN,orden int NN,titulo varchar(255) NN,descripcion text N,es_obligatoria tinyint(1) NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(instruccion_id);uk_prci_procedimiento_version_orden!(procedimiento_version_id,orden)',
 'fk_prci_procedimiento_version_id=procedimiento_version_id>ml__procedimientos_versiones.procedimiento_version_id',
 ''),
('ml__accesos_confidenciales',
 'acceso_confidencial_id int NN AI,objeto_tabla varchar(64) NN,objeto_id int NN,empresa_perfil_id int unsigned NN,ambito_tipo_id int NN,grupo_id int N,region_grupo_id int N,empresa_id smallint unsigned N,territorio_id int N,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(acceso_confidencial_id);ix_acco_ambito_tipo(ambito_tipo_id);ix_acco_empresa(empresa_id);ix_acco_empresa_perfil(empresa_perfil_id);ix_acco_grupo(grupo_id);ix_acco_objeto_tabla_objeto(objeto_tabla,objeto_id);ix_acco_region_grupo(region_grupo_id);ix_acco_territorio(territorio_id)',
 'fk_acco_ambito_tipo_id=ambito_tipo_id>ml__ambitos_tipos.ambito_tipo_id;fk_acco_empresa_id=empresa_id>conf__empresas.empresa_id;fk_acco_empresa_perfil_id=empresa_perfil_id>conf__empresas_perfiles.empresa_perfil_id;fk_acco_grupo_id=grupo_id>ml__grupos.grupo_id;fk_acco_region_grupo_id=region_grupo_id>ml__regiones_grupo.region_grupo_id;fk_acco_territorio_id=territorio_id>ml__territorios.territorio_id',
 'ck_acco_ambito;ck_acco_vigencia'),
('ml__auditoria_accesos',
 'auditoria_acceso_id int NN AI,usuario_id int NN,objeto_tabla varchar(64) NN,objeto_id int NN,accedido_en datetime NN,motivo varchar(255) N',
 'PRIMARY!(auditoria_acceso_id);ix_auda_objeto_tabla_objeto(objeto_tabla,objeto_id);ix_auda_usuario(usuario_id)',
 'fk_auda_usuario_id=usuario_id>conf__usuarios.usuario_id',
 ''),
('ml__criticidades',
 'criticidad_id int NN AI,nombre varchar(50) NN,peso int NN,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(criticidad_id)',
 '',
 ''),
('ml__periodicidades',
 'periodicidad_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,intervalo_cantidad int N,intervalo_unidad varchar(10) N,es_evento tinyint(1) NN D=0,fecha_fija_autoridad tinyint(1) NN D=0,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(periodicidad_id);uk_peri_codigo!(codigo)',
 '',
 ''),
('ml__vencimientos_reglas',
 'vencimiento_regla_id int NN AI,codigo varchar(50) NN,nombre varchar(150) NN,tipo varchar(30) NN,dato_tipo_id int N,desplazamiento_dias int N,parametros text N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(vencimiento_regla_id);ix_venr_dato_tipo(dato_tipo_id);uk_venr_codigo!(codigo)',
 'fk_venr_dato_tipo_id=dato_tipo_id>ml__datos_tipos.dato_tipo_id',
 ''),
('ml__obligaciones',
 'obligacion_id int NN AI,norma_id int NN,empresa_id smallint unsigned N,articulo_referencia varchar(100) N,codigo varchar(80) NN,descripcion text NN,periodicidad_id int NN,vencimiento_regla_id int NN,criticidad_id int N,requiere_revision tinyint(1) NN D=1,requiere_evidencia tinyint(1) NN D=1,retencion_evidencias_meses int N,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_id);ix_obl_criticidad(criticidad_id);ix_obl_empresa(empresa_id);ix_obl_norma_hasta(norma_id,vigente_hasta);ix_obl_periodicidad(periodicidad_id);ix_obl_vencimiento_regla(vencimiento_regla_id);uk_obl_norma_codigo!(norma_id,codigo)',
 'fk_obl_criticidad_id=criticidad_id>ml__criticidades.criticidad_id;fk_obl_empresa_id=empresa_id>conf__empresas.empresa_id;fk_obl_norma_id=norma_id>ml__normas.norma_id;fk_obl_periodicidad_id=periodicidad_id>ml__periodicidades.periodicidad_id;fk_obl_vencimiento_regla_id=vencimiento_regla_id>ml__vencimientos_reglas.vencimiento_regla_id',
 'ck_obl_vigencia'),
('ml__obligaciones_tipos_entidades',
 'obligacion_tipo_entidad_id int NN AI,obligacion_id int NN,entidad_tipo_id int NN,regla_json text N,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_tipo_entidad_id);ix_oblt_entidad_tipo(entidad_tipo_id);ix_oblt_obligacion_entidad_tipo_desde(obligacion_id,entidad_tipo_id,vigente_desde)',
 'fk_oblt_entidad_tipo_id=entidad_tipo_id>ml__entidades_tipos.entidad_tipo_id;fk_oblt_obligacion_id=obligacion_id>ml__obligaciones.obligacion_id',
 'ck_oblt_vigencia'),
('ml__obligaciones_jurisdicciones',
 'obligacion_jurisdiccion_id int NN AI,obligacion_id int NN,territorio_id int NN,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_jurisdiccion_id);ix_oblj_obligacion(obligacion_id);ix_oblj_territorio(territorio_id)',
 'fk_oblj_obligacion_id=obligacion_id>ml__obligaciones.obligacion_id;fk_oblj_territorio_id=territorio_id>ml__territorios.territorio_id',
 'ck_oblj_vigencia'),
('ml__obligaciones_dependencias',
 'obligacion_dependencia_id int NN AI,obligacion_id int NN,obligacion_predecesora_id int NN,vigente_desde date NN,vigente_hasta date N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_dependencia_id);ix_obld_obligacion_obligacion_predecesora(obligacion_id,obligacion_predecesora_id);ix_obld_obligacion_predecesora(obligacion_predecesora_id)',
 'fk_obld_obligacion_id=obligacion_id>ml__obligaciones.obligacion_id;fk_obld_obligacion_predecesora_id=obligacion_predecesora_id>ml__obligaciones.obligacion_id',
 'ck_obld_vigencia'),
('ml__obligaciones_alertas',
 'obligacion_alerta_id int NN AI,obligacion_id int NN,dias_antes int NN,destinatario varchar(20) NN,canal varchar(20) NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_alerta_id);ix_obla_obligacion(obligacion_id)',
 'fk_obla_obligacion_id=obligacion_id>ml__obligaciones.obligacion_id',
 ''),
('ml__tareas_plantillas',
 'tarea_plantilla_id int NN AI,obligacion_id int NN,orden int NN,titulo varchar(255) NN,descripcion text N,offset_dias_inicio int N,offset_dias_vencimiento int N,procedimiento_id int N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(tarea_plantilla_id);ix_tpl_procedimiento(procedimiento_id);uk_tpl_obligacion_orden!(obligacion_id,orden)',
 'fk_tpl_obligacion_id=obligacion_id>ml__obligaciones.obligacion_id;fk_tpl_procedimiento_id=procedimiento_id>ml__procedimientos.procedimiento_id',
 ''),
('ml__tareas_plantillas_dependencias',
 'tarea_plantilla_dependencia_id int NN AI,tarea_plantilla_id int NN,tarea_plantilla_predecesora_id int NN,estado_activador_id smallint NN,obligatoria tinyint(1) NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(tarea_plantilla_dependencia_id);ix_tpld_tarea_plantilla_predecesora(tarea_plantilla_predecesora_id);uk_tpld_tarea_plantilla_tarea_plantilla_predecesora!(tarea_plantilla_id,tarea_plantilla_predecesora_id)',
 'fk_tpld_tarea_plantilla_id=tarea_plantilla_id>ml__tareas_plantillas.tarea_plantilla_id;fk_tpld_tarea_plantilla_predecesora_id=tarea_plantilla_predecesora_id>ml__tareas_plantillas.tarea_plantilla_id',
 ''),
('ml__obligaciones_asignadas',
 'obligacion_asignada_id int NN AI,obligacion_prevaleciente_id int NN,entidad_id int NN,empresa_id smallint unsigned NN,vigente_desde date NN,vigente_hasta date N,tabla_estado_registro_id smallint NN D=1,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_asignada_id);ix_oas_empresa_hasta(empresa_id,vigente_hasta);ix_oas_entidad_obligacion_prevaleciente_desde(entidad_id,obligacion_prevaleciente_id,vigente_desde);ix_oas_obligacion_prevaleciente(obligacion_prevaleciente_id)',
 'fk_oas_empresa_id=empresa_id>conf__empresas.empresa_id;fk_oas_entidad_id=entidad_id>ml__entidades.entidad_id;fk_oas_obligacion_prevaleciente_id=obligacion_prevaleciente_id>ml__obligaciones.obligacion_id',
 'ck_oas_vigencia'),
('ml__obligaciones_asignadas_origenes',
 'obligacion_asignada_origen_id int NN AI,obligacion_asignada_id int NN,obligacion_id int NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_asignada_origen_id);ix_oaso_obligacion(obligacion_id);uk_oaso_obligacion_asignada_obligacion!(obligacion_asignada_id,obligacion_id)',
 'fk_oaso_obligacion_asignada_id=obligacion_asignada_id>ml__obligaciones_asignadas.obligacion_asignada_id;fk_oaso_obligacion_id=obligacion_id>ml__obligaciones.obligacion_id',
 ''),
('ml__excepciones',
 'excepcion_id int NN AI,obligacion_asignada_id int NN,motivo text NN,fundamento text N,fundamento_ref varchar(500) N,solicitada_por int N,aprobada_por int N,aprobada_legal_por int N,aprobada_en datetime N,vigente_desde date NN,vigente_hasta date NN,tabla_estado_registro_id smallint NN,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(excepcion_id);ix_exc_aprobada_legal_por(aprobada_legal_por);ix_exc_aprobada_por(aprobada_por);ix_exc_empresa(empresa_id);ix_exc_obligacion_asignada_hasta(obligacion_asignada_id,vigente_hasta);ix_exc_solicitada_por(solicitada_por)',
 'fk_exc_aprobada_legal_por=aprobada_legal_por>conf__usuarios.usuario_id;fk_exc_aprobada_por=aprobada_por>conf__usuarios.usuario_id;fk_exc_empresa_id=empresa_id>conf__empresas.empresa_id;fk_exc_obligacion_asignada_id=obligacion_asignada_id>ml__obligaciones_asignadas.obligacion_asignada_id;fk_exc_solicitada_por=solicitada_por>conf__usuarios.usuario_id',
 'ck_exc_vigencia'),
('ml__recalculos_pendientes',
 'recalculo_pendiente_id int NN AI,causa varchar(40) NN,objeto_tabla varchar(64) NN,objeto_id int NN,fecha_efecto date NN,creado_en datetime NN,procesado_en datetime N,tabla_estado_registro_id smallint NN',
 'PRIMARY!(recalculo_pendiente_id);ix_recp_tabla_estado_registro_fecha_efecto(tabla_estado_registro_id,fecha_efecto)',
 '',
 ''),
('ml__ciclos_vinculos_tipos',
 'ciclo_vinculo_tipo_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(ciclo_vinculo_tipo_id);uk_cvt_codigo!(codigo)',
 '',
 ''),
('ml__ciclos',
 'ciclo_id int NN AI,obligacion_asignada_id int NN,empresa_id smallint unsigned NN,entidad_id int NN,norma_version_id int N,ciclo_origen_id int N,ciclo_vinculo_tipo_id int N,periodo_desde date NN,periodo_hasta date NN,fecha_inicio datetime N,fecha_vencimiento datetime NN,fecha_cierre datetime N,observaciones text N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(ciclo_id);ix_cic_ciclo_origen(ciclo_origen_id);ix_cic_ciclo_vinculo_tipo(ciclo_vinculo_tipo_id);ix_cic_empresa_fecha_vencimiento(empresa_id,fecha_vencimiento);ix_cic_entidad(entidad_id);ix_cic_norma_version(norma_version_id);ix_cic_obligacion_asignada_periodo_desde(obligacion_asignada_id,periodo_desde)',
 'fk_cic_ciclo_origen_id=ciclo_origen_id>ml__ciclos.ciclo_id;fk_cic_ciclo_vinculo_tipo_id=ciclo_vinculo_tipo_id>ml__ciclos_vinculos_tipos.ciclo_vinculo_tipo_id;fk_cic_empresa_id=empresa_id>conf__empresas.empresa_id;fk_cic_entidad_id=entidad_id>ml__entidades.entidad_id;fk_cic_norma_version_id=norma_version_id>ml__normas_versiones.norma_version_id;fk_cic_obligacion_asignada_id=obligacion_asignada_id>ml__obligaciones_asignadas.obligacion_asignada_id',
 ''),
('ml__ciclos_tareas',
 'ciclo_tarea_id int NN AI,ciclo_id int NN,tarea_plantilla_id int N,titulo varchar(255) NN,descripcion text N,procedimiento_version_id int N,requiere_revision tinyint(1) NN,requiere_evidencia tinyint(1) NN,fecha_activacion datetime N,fecha_inicio datetime N,fecha_vencimiento datetime N,fecha_cierre datetime N,tabla_estado_registro_id smallint NN,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(ciclo_tarea_id);ix_cta_ciclo(ciclo_id);ix_cta_empresa_tabla_estado_registro_fecha_vencimiento(empresa_id,tabla_estado_registro_id,fecha_vencimiento);ix_cta_procedimiento_version(procedimiento_version_id);ix_cta_tarea_plantilla(tarea_plantilla_id)',
 'fk_cta_ciclo_id=ciclo_id>ml__ciclos.ciclo_id;fk_cta_empresa_id=empresa_id>conf__empresas.empresa_id;fk_cta_procedimiento_version_id=procedimiento_version_id>ml__procedimientos_versiones.procedimiento_version_id;fk_cta_tarea_plantilla_id=tarea_plantilla_id>ml__tareas_plantillas.tarea_plantilla_id',
 ''),
('ml__ciclos_tareas_dependencias',
 'ciclo_tarea_dependencia_id int NN AI,ciclo_tarea_id int NN,ciclo_tarea_predecesora_id int NN,estado_activador_id smallint NN,obligatoria tinyint(1) NN D=1,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(ciclo_tarea_dependencia_id);ix_ctad_ciclo_tarea_predecesora(ciclo_tarea_predecesora_id);ix_ctad_empresa(empresa_id);uk_ctad_ciclo_tarea_ciclo_tarea_predecesora!(ciclo_tarea_id,ciclo_tarea_predecesora_id)',
 'fk_ctad_ciclo_tarea_id=ciclo_tarea_id>ml__ciclos_tareas.ciclo_tarea_id;fk_ctad_ciclo_tarea_predecesora_id=ciclo_tarea_predecesora_id>ml__ciclos_tareas.ciclo_tarea_id;fk_ctad_empresa_id=empresa_id>conf__empresas.empresa_id',
 ''),
('ml__obligaciones_asignadas_raci',
 'obligacion_asignada_raci_id int NN AI,obligacion_asignada_id int NN,raci_rol_id int NN,usuario_id int NN,vigente_desde date NN,vigente_hasta date N,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(obligacion_asignada_raci_id);ix_oasr_empresa(empresa_id);ix_oasr_raci_rol(raci_rol_id);ix_oasr_usuario_hasta(usuario_id,vigente_hasta);uk_oasr_obligacion_asignada_raci_rol_usuario_desde!(obligacion_asignada_id,raci_rol_id,usuario_id,vigente_desde)',
 'fk_oasr_empresa_id=empresa_id>conf__empresas.empresa_id;fk_oasr_obligacion_asignada_id=obligacion_asignada_id>ml__obligaciones_asignadas.obligacion_asignada_id;fk_oasr_raci_rol_id=raci_rol_id>ml__raci_roles.raci_rol_id;fk_oasr_usuario_id=usuario_id>conf__usuarios.usuario_id',
 'ck_oasr_vigencia'),
('ml__ciclos_raci',
 'ciclo_raci_id int NN AI,ciclo_id int NN,raci_rol_id int NN,usuario_id int NN,vigente_desde date NN,vigente_hasta date N,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(ciclo_raci_id);ix_cicr_empresa(empresa_id);ix_cicr_raci_rol(raci_rol_id);ix_cicr_usuario_hasta(usuario_id,vigente_hasta);uk_cicr_ciclo_raci_rol_usuario_desde!(ciclo_id,raci_rol_id,usuario_id,vigente_desde)',
 'fk_cicr_ciclo_id=ciclo_id>ml__ciclos.ciclo_id;fk_cicr_empresa_id=empresa_id>conf__empresas.empresa_id;fk_cicr_raci_rol_id=raci_rol_id>ml__raci_roles.raci_rol_id;fk_cicr_usuario_id=usuario_id>conf__usuarios.usuario_id',
 'ck_cicr_vigencia'),
('ml__ciclos_tareas_raci',
 'ciclo_tarea_raci_id int NN AI,ciclo_tarea_id int NN,raci_rol_id int NN,usuario_id int NN,vigente_desde date NN,vigente_hasta date N,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(ciclo_tarea_raci_id);ix_ctar_empresa(empresa_id);ix_ctar_raci_rol(raci_rol_id);ix_ctar_usuario_hasta(usuario_id,vigente_hasta);uk_ctar_ciclo_tarea_raci_rol_usuario_desde!(ciclo_tarea_id,raci_rol_id,usuario_id,vigente_desde)',
 'fk_ctar_ciclo_tarea_id=ciclo_tarea_id>ml__ciclos_tareas.ciclo_tarea_id;fk_ctar_empresa_id=empresa_id>conf__empresas.empresa_id;fk_ctar_raci_rol_id=raci_rol_id>ml__raci_roles.raci_rol_id;fk_ctar_usuario_id=usuario_id>conf__usuarios.usuario_id',
 'ck_ctar_vigencia'),
('ml__reasignaciones',
 'reasignacion_id int NN AI,usuario_origen_id int NN,usuario_destino_id int NN,fecha_efecto date NN,motivo varchar(255) N,aplicada_por int N,aplicada_en datetime N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(reasignacion_id);ix_reas_aplicada_por(aplicada_por);ix_reas_usuario_destino(usuario_destino_id);ix_reas_usuario_origen(usuario_origen_id)',
 'fk_reas_aplicada_por=aplicada_por>conf__usuarios.usuario_id;fk_reas_usuario_destino_id=usuario_destino_id>conf__usuarios.usuario_id;fk_reas_usuario_origen_id=usuario_origen_id>conf__usuarios.usuario_id',
 ''),
('ml__evidencias',
 'evidencia_id int NN AI,ciclo_tarea_id int NN,titulo varchar(255) NN,descripcion text N,archivo_ref varchar(500) NN,hash varchar(128) N,es_comprobante tinyint(1) NN D=0,valida_hasta date N,retener_hasta date N,confidencial tinyint(1) NN D=0,tabla_estado_registro_id smallint NN D=1,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(evidencia_id);ix_evi_ciclo_tarea(ciclo_tarea_id);ix_evi_empresa(empresa_id);ix_evi_valida_hasta(valida_hasta)',
 'fk_evi_ciclo_tarea_id=ciclo_tarea_id>ml__ciclos_tareas.ciclo_tarea_id;fk_evi_empresa_id=empresa_id>conf__empresas.empresa_id',
 ''),
('ml__alertas',
 'alerta_id int NN AI,ciclo_id int N,ciclo_tarea_id int N,evidencia_id int N,usuario_id int NN,canal varchar(20) NN,programada_para datetime NN,enviada_en datetime N,tabla_estado_registro_id smallint NN,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(alerta_id);ix_ale_ciclo(ciclo_id);ix_ale_ciclo_tarea(ciclo_tarea_id);ix_ale_empresa(empresa_id);ix_ale_evidencia(evidencia_id);ix_ale_programada_para_enviada_en(programada_para,enviada_en);ix_ale_usuario(usuario_id)',
 'fk_ale_ciclo_id=ciclo_id>ml__ciclos.ciclo_id;fk_ale_ciclo_tarea_id=ciclo_tarea_id>ml__ciclos_tareas.ciclo_tarea_id;fk_ale_empresa_id=empresa_id>conf__empresas.empresa_id;fk_ale_evidencia_id=evidencia_id>ml__evidencias.evidencia_id;fk_ale_usuario_id=usuario_id>conf__usuarios.usuario_id',
 ''),
('ml__incidencias',
 'incidencia_id int NN AI,ciclo_id int NN,descripcion text N,abierta_en datetime NN,ciclo_seguimiento_id int N,tabla_estado_registro_id smallint NN,empresa_id smallint unsigned NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(incidencia_id);ix_inci_ciclo(ciclo_id);ix_inci_ciclo_seguimiento(ciclo_seguimiento_id);ix_inci_empresa(empresa_id)',
 'fk_inci_ciclo_id=ciclo_id>ml__ciclos.ciclo_id;fk_inci_ciclo_seguimiento_id=ciclo_seguimiento_id>ml__ciclos.ciclo_id;fk_inci_empresa_id=empresa_id>conf__empresas.empresa_id',
 ''),
('ml__indicadores_cierres',
 'indicador_cierre_id int NN AI,grupo_id int NN,periodo_desde date NN,periodo_hasta date NN,empresa_id smallint unsigned N,region_grupo_id int N,territorio_id int N,ciclos_contados int NN,cumplidos_a_tiempo int NN,cumplidos_fuera_plazo int NN,incumplidos int NN,eximidos int NN,por_vencer int NN,ponderacion_opcion_valor_id int N,calculado_en datetime NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(indicador_cierre_id);ix_indc_empresa(empresa_id);ix_indc_grupo_periodo_desde_periodo_hasta(grupo_id,periodo_desde,periodo_hasta);ix_indc_ponderacion_opcion_valor(ponderacion_opcion_valor_id);ix_indc_region_grupo(region_grupo_id);ix_indc_territorio(territorio_id)',
 'fk_indc_empresa_id=empresa_id>conf__empresas.empresa_id;fk_indc_grupo_id=grupo_id>ml__grupos.grupo_id;fk_indc_ponderacion_opcion_valor_id=ponderacion_opcion_valor_id>ml__opciones_valores.opcion_valor_id;fk_indc_region_grupo_id=region_grupo_id>ml__regiones_grupo.region_grupo_id;fk_indc_territorio_id=territorio_id>ml__territorios.territorio_id',
 ''),
('ml__cargas_tipos',
 'carga_tipo_id int NN AI,codigo varchar(30) NN,nombre varchar(100) NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(carga_tipo_id);uk_cart_codigo!(codigo)',
 '',
 ''),
('ml__cargas',
 'carga_id int NN AI,carga_tipo_id int NN,empresa_id smallint unsigned N,archivo_ref varchar(500) NN,validada_en datetime N,confirmada_por int N,confirmada_en datetime N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(carga_id);ix_car_carga_tipo(carga_tipo_id);ix_car_confirmada_por(confirmada_por);ix_car_empresa(empresa_id)',
 'fk_car_carga_tipo_id=carga_tipo_id>ml__cargas_tipos.carga_tipo_id;fk_car_confirmada_por=confirmada_por>conf__usuarios.usuario_id;fk_car_empresa_id=empresa_id>conf__empresas.empresa_id',
 ''),
('ml__cargas_filas',
 'carga_fila_id int NN AI,carga_id int NN,nro_fila int NN,contenido text N,errores text N,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(carga_fila_id);uk_carf_carga_nro_fila!(carga_id,nro_fila)',
 'fk_carf_carga_id=carga_id>ml__cargas.carga_id',
 ''),
('ml__cambios_masivos',
 'cambio_masivo_id int NN AI,norma_version_id int N,descripcion text NN,solicitado_por int N,requiere_aprobacion tinyint(1) NN D=1,aprobado_por int N,aprobado_en datetime N,aplicado_en datetime N,tabla_estado_registro_id smallint NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(cambio_masivo_id);ix_cmas_aprobado_por(aprobado_por);ix_cmas_norma_version(norma_version_id);ix_cmas_solicitado_por(solicitado_por)',
 'fk_cmas_aprobado_por=aprobado_por>conf__usuarios.usuario_id;fk_cmas_norma_version_id=norma_version_id>ml__normas_versiones.norma_version_id;fk_cmas_solicitado_por=solicitado_por>conf__usuarios.usuario_id',
 ''),
('ml__cambios_masivos_detalles',
 'cambio_masivo_detalle_id int NN AI,cambio_masivo_id int NN,entidad_id int NN,ciclos_afectados int NN,tareas_afectadas int NN,creado_en timestamp NN D=CURRENT_TIMESTAMP,creado_por int N,actualizado_en timestamp NN D=CURRENT_TIMESTAMP OU,actualizado_por int N',
 'PRIMARY!(cambio_masivo_detalle_id);ix_cmad_cambio_masivo(cambio_masivo_id);ix_cmad_entidad(entidad_id)',
 'fk_cmad_cambio_masivo_id=cambio_masivo_id>ml__cambios_masivos.cambio_masivo_id;fk_cmad_entidad_id=entidad_id>ml__entidades.entidad_id',
 ''),
('ml__log_cambios',
 'log_cambio_id int NN AI,tabla varchar(64) NN,registro_id int NN,accion varchar(20) NN,antes_json text N,despues_json text N,usuario_id int N,fecha datetime NN',
 'PRIMARY!(log_cambio_id);ix_logc_fecha(fecha);ix_logc_tabla_registro(tabla,registro_id);ix_logc_usuario(usuario_id)',
 'fk_logc_usuario_id=usuario_id>conf__usuarios.usuario_id',
 '');

-- =====================================================================
-- 0) CONTROLES PREVIOS (solo lectura). Si alguno falla, el script corta
--    antes de crear nada.
-- =====================================================================

-- PK de las tablas conf__ referenciadas: tipo exacto, PK de una sola
-- columna e InnoDB (si no, la fase B fallaría con errno 150 / 3780).
SET @m0_conf_pk = (
    SELECT COUNT(*) FROM information_schema.columns c
     WHERE c.table_schema = DATABASE()
       AND c.column_key = 'PRI'
       AND (   (c.table_name = 'conf__empresas' AND c.column_name = 'empresa_id'
                AND c.data_type = 'smallint' AND LOWER(c.column_type) LIKE '%unsigned%')
            OR (c.table_name = 'conf__usuarios' AND c.column_name = 'usuario_id'
                AND c.data_type = 'int' AND LOWER(c.column_type) NOT LIKE '%unsigned%')
            OR (c.table_name = 'conf__empresas_perfiles' AND c.column_name = 'empresa_perfil_id'
                AND c.data_type = 'int' AND LOWER(c.column_type) LIKE '%unsigned%')));
SET @m0_conf_pri = (
    SELECT COUNT(*) FROM information_schema.columns c
     WHERE c.table_schema = DATABASE()
       AND c.column_key = 'PRI'
       AND c.table_name IN ('conf__empresas', 'conf__usuarios', 'conf__empresas_perfiles'));
SET @m0_conf_innodb = (
    SELECT COUNT(*) FROM information_schema.tables t
     WHERE t.table_schema = DATABASE()
       AND t.table_name IN ('conf__empresas', 'conf__usuarios', 'conf__empresas_perfiles')
       AND t.engine = 'InnoDB');

-- Tablas ml__ que no son de las 69 (prototipo u otras): no debe haber.
SELECT COUNT(*),
       GROUP_CONCAT(CONVERT(t.table_name USING utf8mb4) ORDER BY t.table_name SEPARATOR ' ')
  INTO @m0_ajenas, @m0_ajenas_det
  FROM information_schema.tables t
 WHERE t.table_schema = DATABASE()
   AND t.table_name LIKE 'ml\_\_%'
   AND CONVERT(t.table_name USING utf8mb4) COLLATE utf8mb4_bin NOT IN (SELECT e.tabla FROM `_m0_esperado` e);

SET @m0_no_innodb = (
    SELECT COUNT(*) FROM information_schema.tables t
     WHERE t.table_schema = DATABASE()
       AND t.table_name LIKE 'ml\_\_%'
       AND (t.engine IS NULL OR t.engine <> 'InnoDB'));

-- De las 69, las que ya existen (corrida anterior) tienen que tener
-- exactamente las columnas esperadas (nombre, orden, tipo, nulos, default),
-- los índices y los CHECK. Las FK no se comparan acá: en una reanudación
-- pueden faltar (las agrega la fase B).
SELECT COUNT(*),
       SUM(CASE WHEN a.columnas <> e.columnas
                  OR IFNULL(i.indices, '') <> e.indices
                  OR IFNULL(k.checks, '') <> e.checks THEN 1 ELSE 0 END),
       GROUP_CONCAT(CASE WHEN a.columnas <> e.columnas
                           OR IFNULL(i.indices, '') <> e.indices
                           OR IFNULL(k.checks, '') <> e.checks THEN e.tabla END ORDER BY e.tabla SEPARATOR ' ')
  INTO @m0_existentes, @m0_pre_mal, @m0_pre_det
  FROM `_m0_esperado` e
  JOIN (SELECT CONVERT(c.table_name USING utf8mb4) COLLATE utf8mb4_bin AS tabla,
               GROUP_CONCAT(
                   CONCAT(CONVERT(c.column_name USING utf8mb4), ' ',
                          CASE WHEN c.data_type = 'tinyint' THEN LOWER(CONVERT(c.column_type USING utf8mb4))
                               WHEN c.data_type = 'varchar' THEN CONCAT('varchar(', c.character_maximum_length, ')')
                               WHEN c.data_type = 'decimal' THEN CONCAT('decimal(', c.numeric_precision, ',', c.numeric_scale, ')')
                               ELSE LOWER(CONVERT(c.data_type USING utf8mb4)) END,
                          CASE WHEN LOWER(CONVERT(c.column_type USING utf8mb4)) LIKE '%unsigned%' THEN ' unsigned' ELSE '' END,
                          CASE WHEN c.is_nullable = 'NO' THEN ' NN' ELSE ' N' END,
                          CASE WHEN c.is_nullable = 'NO' AND c.column_default IS NOT NULL
                               THEN CONCAT(' D=', UPPER(REPLACE(CONVERT(c.column_default USING utf8mb4), '()', ''))) ELSE '' END,
                          CASE WHEN UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%AUTO_INCREMENT%' THEN ' AI' ELSE '' END,
                          CASE WHEN UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%ON UPDATE%' THEN ' OU' ELSE '' END,
                          CASE WHEN IFNULL(CONVERT(c.generation_expression USING utf8mb4), '') <> '' THEN ' G' ELSE '' END)
                   ORDER BY c.ordinal_position SEPARATOR ',') COLLATE utf8mb4_bin AS columnas
          FROM information_schema.columns c
         WHERE c.table_schema = DATABASE()
           AND c.table_name LIKE 'ml\_\_%'
         GROUP BY c.table_name) a
    ON a.tabla = e.tabla
  LEFT JOIN (SELECT x.tabla,
                    GROUP_CONCAT(CONCAT(x.idx, CASE WHEN x.nu = 0 THEN '!' ELSE '' END, '(', x.cols, ')')
                                 ORDER BY x.idx SEPARATOR ';') COLLATE utf8mb4_bin AS indices
               FROM (SELECT CONVERT(s.table_name USING utf8mb4) COLLATE utf8mb4_bin AS tabla,
                            CONVERT(s.index_name USING utf8mb4) COLLATE utf8mb4_bin AS idx,
                            MIN(s.non_unique) AS nu,
                            GROUP_CONCAT(CONVERT(s.column_name USING utf8mb4)
                                         ORDER BY s.seq_in_index SEPARATOR ',') COLLATE utf8mb4_bin AS cols
                       FROM information_schema.statistics s
                      WHERE s.table_schema = DATABASE()
                        AND s.table_name LIKE 'ml\_\_%'
                      GROUP BY s.table_name, s.index_name) x
              GROUP BY x.tabla) i
         ON i.tabla = e.tabla
  LEFT JOIN (SELECT z.tabla,
                    GROUP_CONCAT(z.nombre ORDER BY z.nombre SEPARATOR ';') COLLATE utf8mb4_bin AS checks
               FROM (SELECT CONVERT(tc.table_name USING utf8mb4) COLLATE utf8mb4_bin AS tabla,
                            CONVERT(tc.constraint_name USING utf8mb4) COLLATE utf8mb4_bin AS nombre
                       FROM information_schema.table_constraints tc
                      WHERE tc.constraint_schema = DATABASE()
                        AND tc.table_name LIKE 'ml\_\_%'
                        AND tc.constraint_type = 'CHECK') z
              GROUP BY z.tabla) k
         ON k.tabla = e.tabla;

SELECT 'M0-02 controles previos' AS paso,
       @m0_conf_pk     AS pk_conf_ok_de_3,
       @m0_conf_innodb AS conf_innodb_de_3,
       @m0_ajenas      AS tablas_ml_ajenas,
       @m0_ajenas_det  AS ajenas,
       @m0_no_innodb   AS ml_no_innodb,
       @m0_existentes  AS de_las_69_ya_existen,
       @m0_pre_mal     AS existentes_con_otra_estructura,
       @m0_pre_det     AS detalle;

CALL `_m0_assert`(@m0_conf_pk = 3 AND @m0_conf_pri = 3,
                  'M0-02: PK de conf__empresas/usuarios/empresas_perfiles con otro tipo o compuesta. No se creo nada');
CALL `_m0_assert`(@m0_conf_innodb = 3, 'M0-02: conf__empresas/usuarios/empresas_perfiles no son InnoDB. No se creo nada');
CALL `_m0_assert`(@m0_ajenas = 0,
                  LEFT(CONCAT('M0-02: hay tablas ml__ ajenas a las 69 (correr 01): ', IFNULL(@m0_ajenas_det, '')), 128));
CALL `_m0_assert`(@m0_no_innodb = 0, 'M0-02: hay tablas ml__ que no son InnoDB o son vistas. No se creo nada');
CALL `_m0_assert`(IFNULL(@m0_pre_mal, 0) = 0,
                  LEFT(CONCAT('M0-02: tablas ml__ existentes con otras columnas, indices o CHECK: ', IFNULL(@m0_pre_det, '')), 128));

-- Barrera de la fase B: _m0_add_fk exige este valor.
SET @m0_02_ok = IFNULL(@m0_conf_pk = 3 AND @m0_conf_pri = 3 AND @m0_conf_innodb = 3
                       AND @m0_ajenas = 0 AND @m0_no_innodb = 0 AND IFNULL(@m0_pre_mal, 0) = 0, 0);
-- =====================================================================
-- FASE A: 69 CREATE TABLE IF NOT EXISTS, sin FK (con PK, UNIQUE, indices
-- y CHECK). Una sentencia por tabla. Padres antes que hijos.
-- =====================================================================

-- A01. ml__grupos (grp) - DBML 04:71
CREATE TABLE IF NOT EXISTS `ml__grupos` (
  `grupo_id`                 INT NOT NULL AUTO_INCREMENT,
  `empresa_titular_id`       SMALLINT UNSIGNED NOT NULL,
  `nombre`                   VARCHAR(150) NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`grupo_id`),
  KEY `ix_grp_empresa_titular` (`empresa_titular_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A02. ml__regiones_grupo (rgr) - DBML 04:82
CREATE TABLE IF NOT EXISTS `ml__regiones_grupo` (
  `region_grupo_id`          INT NOT NULL AUTO_INCREMENT,
  `grupo_id`                 INT NOT NULL,
  `nombre`                   VARCHAR(150) NOT NULL,
  `vigente_desde`            DATE NOT NULL,
  `vigente_hasta`            DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`region_grupo_id`),
  KEY `ix_rgr_grupo` (`grupo_id`),
  CONSTRAINT `ck_rgr_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A03. ml__empresas_regiones (ereg) - DBML 04:112
CREATE TABLE IF NOT EXISTS `ml__empresas_regiones` (
  `empresa_region_id` INT NOT NULL AUTO_INCREMENT,
  `empresa_id`        SMALLINT UNSIGNED NOT NULL,
  `region_grupo_id`   INT NOT NULL,
  `vigente_desde`     DATE NOT NULL,
  `vigente_hasta`     DATE NULL DEFAULT NULL,
  `creado_en`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`        INT NULL DEFAULT NULL,
  `actualizado_en`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`   INT NULL DEFAULT NULL,
  PRIMARY KEY (`empresa_region_id`),
  KEY `ix_ereg_empresa_hasta` (`empresa_id`, `vigente_hasta`),
  KEY `ix_ereg_region_grupo` (`region_grupo_id`),
  CONSTRAINT `ck_ereg_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A04. ml__empresas_nombres (enom) - DBML 04:127
CREATE TABLE IF NOT EXISTS `ml__empresas_nombres` (
  `empresa_nombre_id` INT NOT NULL AUTO_INCREMENT,
  `empresa_id`        SMALLINT UNSIGNED NOT NULL,
  `razon_social`      VARCHAR(200) NOT NULL,
  `vigente_desde`     DATE NOT NULL,
  `vigente_hasta`     DATE NULL DEFAULT NULL,
  `creado_en`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`        INT NULL DEFAULT NULL,
  `actualizado_en`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`   INT NULL DEFAULT NULL,
  PRIMARY KEY (`empresa_nombre_id`),
  KEY `ix_enom_empresa_hasta` (`empresa_id`, `vigente_hasta`),
  CONSTRAINT `ck_enom_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A05. ml__empresas_eventos_tipos (eevt) - DBML 04:143
CREATE TABLE IF NOT EXISTS `ml__empresas_eventos_tipos` (
  `empresa_evento_tipo_id`   INT NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(30) NOT NULL,
  `nombre`                   VARCHAR(100) NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`empresa_evento_tipo_id`),
  UNIQUE KEY `uk_eevt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A06. ml__empresas_eventos (eev) - DBML 04:154
CREATE TABLE IF NOT EXISTS `ml__empresas_eventos` (
  `empresa_evento_id`        INT NOT NULL AUTO_INCREMENT,
  `empresa_evento_tipo_id`   INT NOT NULL,
  `empresa_origen_id`        SMALLINT UNSIGNED NOT NULL,
  `empresa_destino_id`       SMALLINT UNSIGNED NULL DEFAULT NULL,
  `fecha`                    DATE NOT NULL,
  `observaciones`            TEXT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`empresa_evento_id`),
  KEY `ix_eev_empresa_evento_tipo` (`empresa_evento_tipo_id`),
  KEY `ix_eev_empresa_origen` (`empresa_origen_id`),
  KEY `ix_eev_empresa_destino` (`empresa_destino_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A07. ml__territorios_tipos (tert) - DBML 04:168
CREATE TABLE IF NOT EXISTS `ml__territorios_tipos` (
  `territorio_tipo_id`       INT NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(30) NOT NULL,
  `nombre`                   VARCHAR(100) NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`territorio_tipo_id`),
  UNIQUE KEY `uk_tert_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A08. ml__territorios (ter) - DBML 04:179
CREATE TABLE IF NOT EXISTS `ml__territorios` (
  `territorio_id`            INT NOT NULL AUTO_INCREMENT,
  `territorio_tipo_id`       INT NOT NULL,
  `codigo`                   VARCHAR(50) NULL DEFAULT NULL,
  `nombre`                   VARCHAR(150) NOT NULL,
  `huso_horario`             VARCHAR(64) NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`territorio_id`),
  KEY `ix_ter_territorio_tipo` (`territorio_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A09. ml__territorios_pertenencias (terp) - DBML 04:193
CREATE TABLE IF NOT EXISTS `ml__territorios_pertenencias` (
  `territorio_pertenencia_id` INT NOT NULL AUTO_INCREMENT,
  `territorio_id`             INT NOT NULL,
  `territorio_superior_id`    INT NOT NULL,
  `es_principal`              TINYINT(1) NOT NULL DEFAULT 0,
  `vigente_desde`             DATE NOT NULL,
  `vigente_hasta`             DATE NULL DEFAULT NULL,
  `principal_abierta_id`      INT AS (CASE WHEN `es_principal` = 1 AND `vigente_hasta` IS NULL THEN `territorio_id` ELSE NULL END) STORED,
  `creado_en`                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                INT NULL DEFAULT NULL,
  `actualizado_en`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`           INT NULL DEFAULT NULL,
  PRIMARY KEY (`territorio_pertenencia_id`),
  KEY `ix_terp_territorio_hasta` (`territorio_id`, `vigente_hasta`),
  KEY `ix_terp_territorio_superior_hasta` (`territorio_superior_id`, `vigente_hasta`),
  UNIQUE KEY `uk_terp_principal_abierta` (`principal_abierta_id`),
  CONSTRAINT `ck_terp_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A10. ml__feriados (fer) - DBML 04:211
CREATE TABLE IF NOT EXISTS `ml__feriados` (
  `feriado_id`               INT NOT NULL AUTO_INCREMENT,
  `territorio_id`            INT NOT NULL,
  `fecha`                    DATE NOT NULL,
  `nombre`                   VARCHAR(150) NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`feriado_id`),
  UNIQUE KEY `uk_fer_territorio_fecha` (`territorio_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A11. ml__entidades_tipos (entt) - DBML 04:227
CREATE TABLE IF NOT EXISTS `ml__entidades_tipos` (
  `entidad_tipo_id`          INT NOT NULL AUTO_INCREMENT,
  `grupo_id`                 INT NOT NULL,
  `empresa_id`               SMALLINT UNSIGNED NULL DEFAULT NULL,
  `codigo`                   VARCHAR(50) NOT NULL,
  `nombre`                   VARCHAR(150) NOT NULL,
  `es_fisica`                TINYINT(1) NOT NULL DEFAULT 1,
  `es_logica`                TINYINT(1) NOT NULL DEFAULT 0,
  `es_movil`                 TINYINT(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`entidad_tipo_id`),
  KEY `ix_entt_grupo_empresa_codigo` (`grupo_id`, `empresa_id`, `codigo`),
  KEY `ix_entt_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A12. ml__entidades (ent) - DBML 04:247
CREATE TABLE IF NOT EXISTS `ml__entidades` (
  `entidad_id`               INT NOT NULL AUTO_INCREMENT,
  `entidad_tipo_id`          INT NOT NULL,
  `entidad_padre_id`         INT NULL DEFAULT NULL,
  `codigo`                   VARCHAR(80) NOT NULL,
  `nombre`                   VARCHAR(200) NOT NULL,
  `descripcion`              TEXT NULL,
  `es_raiz`                  TINYINT(1) NOT NULL DEFAULT 0,
  `vigente_desde`            DATE NOT NULL,
  `vigente_hasta`            DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`entidad_id`),
  KEY `ix_ent_codigo` (`codigo`),
  KEY `ix_ent_entidad_padre` (`entidad_padre_id`),
  KEY `ix_ent_entidad_tipo` (`entidad_tipo_id`),
  CONSTRAINT `ck_ent_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A13. ml__empresas_grupo (egr) - DBML 04:96
CREATE TABLE IF NOT EXISTS `ml__empresas_grupo` (
  `empresa_grupo_id`         INT NOT NULL AUTO_INCREMENT,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `grupo_id`                 INT NOT NULL,
  `pais_territorio_id`       INT NOT NULL,
  `entidad_raiz_id`          INT NULL DEFAULT NULL,
  `vigente_desde`            DATE NOT NULL,
  `vigente_hasta`            DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`empresa_grupo_id`),
  KEY `ix_egr_empresa` (`empresa_id`),
  KEY `ix_egr_grupo` (`grupo_id`),
  KEY `ix_egr_pais_territorio` (`pais_territorio_id`),
  KEY `ix_egr_entidad_raiz` (`entidad_raiz_id`),
  CONSTRAINT `ck_egr_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A14. ml__entidades_empresas (ente) - DBML 04:268
CREATE TABLE IF NOT EXISTS `ml__entidades_empresas` (
  `entidad_empresa_id` INT NOT NULL AUTO_INCREMENT,
  `entidad_id`         INT NOT NULL,
  `empresa_id`         SMALLINT UNSIGNED NOT NULL,
  `es_titular`         TINYINT(1) NOT NULL DEFAULT 1,
  `vigente_desde`      DATE NOT NULL,
  `vigente_hasta`      DATE NULL DEFAULT NULL,
  `titular_abierta_id` INT AS (CASE WHEN `es_titular` = 1 AND `vigente_hasta` IS NULL THEN `entidad_id` ELSE NULL END) STORED,
  `creado_en`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`         INT NULL DEFAULT NULL,
  `actualizado_en`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`    INT NULL DEFAULT NULL,
  PRIMARY KEY (`entidad_empresa_id`),
  KEY `ix_ente_entidad_hasta` (`entidad_id`, `vigente_hasta`),
  KEY `ix_ente_empresa_hasta` (`empresa_id`, `vigente_hasta`),
  UNIQUE KEY `uk_ente_titular_abierta` (`titular_abierta_id`),
  CONSTRAINT `ck_ente_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A15. ml__entidades_ubicaciones (entu) - DBML 04:286
CREATE TABLE IF NOT EXISTS `ml__entidades_ubicaciones` (
  `entidad_ubicacion_id` INT NOT NULL AUTO_INCREMENT,
  `entidad_id`           INT NOT NULL,
  `territorio_id`        INT NOT NULL,
  `es_base`              TINYINT(1) NOT NULL DEFAULT 1,
  `vigente_desde`        DATE NOT NULL,
  `vigente_hasta`        DATE NULL DEFAULT NULL,
  `creado_en`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`           INT NULL DEFAULT NULL,
  `actualizado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`      INT NULL DEFAULT NULL,
  PRIMARY KEY (`entidad_ubicacion_id`),
  KEY `ix_entu_entidad_es_base_hasta` (`entidad_id`, `es_base`, `vigente_hasta`),
  KEY `ix_entu_territorio_hasta` (`territorio_id`, `vigente_hasta`),
  CONSTRAINT `ck_entu_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A16. ml__datos_tipos (datt) - DBML 04:303
CREATE TABLE IF NOT EXISTS `ml__datos_tipos` (
  `dato_tipo_id`             INT NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(50) NOT NULL,
  `nombre`                   VARCHAR(150) NOT NULL,
  `tipo_valor`               VARCHAR(10) NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`dato_tipo_id`),
  UNIQUE KEY `uk_datt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A17. ml__entidades_datos (entd) - DBML 04:315
CREATE TABLE IF NOT EXISTS `ml__entidades_datos` (
  `entidad_dato_id` INT NOT NULL AUTO_INCREMENT,
  `entidad_id`      INT NOT NULL,
  `dato_tipo_id`    INT NOT NULL,
  `valor_numero`    DECIMAL(18,4) NULL DEFAULT NULL,
  `valor_texto`     VARCHAR(255) NULL DEFAULT NULL,
  `valor_fecha`     DATE NULL DEFAULT NULL,
  `vigente_desde`   DATE NOT NULL,
  `vigente_hasta`   DATE NULL DEFAULT NULL,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`entidad_dato_id`),
  KEY `ix_entd_entidad_dato_tipo_hasta` (`entidad_id`, `dato_tipo_id`, `vigente_hasta`),
  KEY `ix_entd_dato_tipo` (`dato_tipo_id`),
  CONSTRAINT `ck_entd_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A18. ml__ambitos_tipos (ambt) - DBML 04:338
CREATE TABLE IF NOT EXISTS `ml__ambitos_tipos` (
  `ambito_tipo_id`  INT NOT NULL AUTO_INCREMENT,
  `codigo`          VARCHAR(30) NOT NULL,
  `nombre`          VARCHAR(100) NOT NULL,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`ambito_tipo_id`),
  UNIQUE KEY `uk_ambt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A19. ml__usuarios_ambitos (usam) - DBML 04:348
CREATE TABLE IF NOT EXISTS `ml__usuarios_ambitos` (
  `usuario_ambito_id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id`        INT NOT NULL,
  `empresa_perfil_id` INT UNSIGNED NOT NULL,
  `ambito_tipo_id`    INT NOT NULL,
  `grupo_id`          INT NULL DEFAULT NULL,
  `region_grupo_id`   INT NULL DEFAULT NULL,
  `empresa_id`        SMALLINT UNSIGNED NULL DEFAULT NULL,
  `territorio_id`     INT NULL DEFAULT NULL,
  `vigente_desde`     DATE NOT NULL,
  `vigente_hasta`     DATE NULL DEFAULT NULL,
  `creado_en`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`        INT NULL DEFAULT NULL,
  `actualizado_en`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`   INT NULL DEFAULT NULL,
  PRIMARY KEY (`usuario_ambito_id`),
  KEY `ix_usam_usuario_hasta` (`usuario_id`, `vigente_hasta`),
  KEY `ix_usam_empresa_perfil` (`empresa_perfil_id`),
  KEY `ix_usam_ambito_tipo` (`ambito_tipo_id`),
  KEY `ix_usam_grupo` (`grupo_id`),
  KEY `ix_usam_region_grupo` (`region_grupo_id`),
  KEY `ix_usam_empresa` (`empresa_id`),
  KEY `ix_usam_territorio` (`territorio_id`),
  CONSTRAINT `ck_usam_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`),
  CONSTRAINT `ck_usam_ambito` CHECK ((`grupo_id` IS NOT NULL) + (`region_grupo_id` IS NOT NULL) + (`empresa_id` IS NOT NULL) + (`territorio_id` IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A20. ml__delegaciones (dele) - DBML 04:369
CREATE TABLE IF NOT EXISTS `ml__delegaciones` (
  `delegacion_id`       INT NOT NULL AUTO_INCREMENT,
  `usuario_id`          INT NOT NULL,
  `usuario_suplente_id` INT NOT NULL,
  `empresa_perfil_id`   INT UNSIGNED NULL DEFAULT NULL,
  `motivo`              VARCHAR(255) NULL DEFAULT NULL,
  `vigente_desde`       DATE NOT NULL,
  `vigente_hasta`       DATE NOT NULL,
  `creado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`          INT NULL DEFAULT NULL,
  `actualizado_en`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`     INT NULL DEFAULT NULL,
  PRIMARY KEY (`delegacion_id`),
  KEY `ix_dele_usuario` (`usuario_id`),
  KEY `ix_dele_usuario_suplente` (`usuario_suplente_id`),
  KEY `ix_dele_empresa_perfil` (`empresa_perfil_id`),
  CONSTRAINT `ck_dele_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A21. ml__opciones (opc) - DBML 04:383
CREATE TABLE IF NOT EXISTS `ml__opciones` (
  `opcion_id`       INT NOT NULL AUTO_INCREMENT,
  `codigo`          VARCHAR(50) NOT NULL,
  `nombre`          VARCHAR(150) NOT NULL,
  `nivel`           VARCHAR(20) NOT NULL,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`opcion_id`),
  UNIQUE KEY `uk_opc_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A22. ml__opciones_valores (opcv) - DBML 04:395
CREATE TABLE IF NOT EXISTS `ml__opciones_valores` (
  `opcion_valor_id` INT NOT NULL AUTO_INCREMENT,
  `opcion_id`       INT NOT NULL,
  `codigo`          VARCHAR(50) NOT NULL,
  `nombre`          VARCHAR(200) NOT NULL,
  `es_defecto`      TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`opcion_valor_id`),
  UNIQUE KEY `uk_opcv_opcion_codigo` (`opcion_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A23. ml__configuracion_grupo (cfgg) - DBML 04:410
CREATE TABLE IF NOT EXISTS `ml__configuracion_grupo` (
  `configuracion_grupo_id` INT NOT NULL AUTO_INCREMENT,
  `grupo_id`               INT NOT NULL,
  `opcion_id`              INT NOT NULL,
  `opcion_valor_id`        INT NOT NULL,
  `vigente_desde`          DATE NOT NULL,
  `vigente_hasta`          DATE NULL DEFAULT NULL,
  `creado_en`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`             INT NULL DEFAULT NULL,
  `actualizado_en`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`        INT NULL DEFAULT NULL,
  PRIMARY KEY (`configuracion_grupo_id`),
  KEY `ix_cfgg_grupo_opcion_hasta` (`grupo_id`, `opcion_id`, `vigente_hasta`),
  KEY `ix_cfgg_opcion` (`opcion_id`),
  KEY `ix_cfgg_opcion_valor` (`opcion_valor_id`),
  CONSTRAINT `ck_cfgg_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A24. ml__raci_roles (racr) - DBML 04:427
CREATE TABLE IF NOT EXISTS `ml__raci_roles` (
  `raci_rol_id`     INT NOT NULL AUTO_INCREMENT,
  `codigo`          VARCHAR(1) NOT NULL,
  `nombre`          VARCHAR(100) NOT NULL,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`raci_rol_id`),
  UNIQUE KEY `uk_racr_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A25. ml__organismos (org) - DBML 04:441
CREATE TABLE IF NOT EXISTS `ml__organismos` (
  `organismo_id`             INT NOT NULL AUTO_INCREMENT,
  `nombre`                   VARCHAR(200) NOT NULL,
  `territorio_id`            INT NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`organismo_id`),
  KEY `ix_org_territorio` (`territorio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A26. ml__normas_tipos (nort) - DBML 04:452
CREATE TABLE IF NOT EXISTS `ml__normas_tipos` (
  `norma_tipo_id`            INT NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(30) NOT NULL,
  `nombre`                   VARCHAR(100) NOT NULL,
  `es_legal`                 TINYINT(1) NOT NULL DEFAULT 0,
  `genera_obligaciones`      TINYINT(1) NOT NULL DEFAULT 1,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`norma_tipo_id`),
  UNIQUE KEY `uk_nort_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A27. ml__normas_jerarquias (norj) - DBML 04:466
CREATE TABLE IF NOT EXISTS `ml__normas_jerarquias` (
  `norma_jerarquia_id`       INT NOT NULL AUTO_INCREMENT,
  `nombre`                   VARCHAR(100) NOT NULL,
  `nivel`                    INT NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`norma_jerarquia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A28. ml__normas (nor) - DBML 04:477
CREATE TABLE IF NOT EXISTS `ml__normas` (
  `norma_id`                 INT NOT NULL AUTO_INCREMENT,
  `norma_tipo_id`            INT NOT NULL,
  `norma_jerarquia_id`       INT NULL DEFAULT NULL,
  `organismo_id`             INT NULL DEFAULT NULL,
  `codigo`                   VARCHAR(80) NOT NULL,
  `titulo`                   VARCHAR(255) NOT NULL,
  `descripcion`              TEXT NULL,
  `idioma_original`          VARCHAR(10) NULL DEFAULT NULL,
  `titulo_traducido`         VARCHAR(255) NULL DEFAULT NULL,
  `idioma_traducido`         VARCHAR(10) NULL DEFAULT NULL,
  `ambito_tipo_id`           INT NOT NULL,
  `grupo_id`                 INT NULL DEFAULT NULL,
  `region_grupo_id`          INT NULL DEFAULT NULL,
  `empresa_id`               SMALLINT UNSIGNED NULL DEFAULT NULL,
  `territorio_id`            INT NULL DEFAULT NULL,
  `requiere_norma_local`     TINYINT(1) NOT NULL DEFAULT 0,
  `confidencial`             TINYINT(1) NOT NULL DEFAULT 0,
  `vigente_desde`            DATE NOT NULL,
  `vigente_hasta`            DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`norma_id`),
  KEY `ix_nor_codigo` (`codigo`),
  KEY `ix_nor_ambito_tipo_territorio` (`ambito_tipo_id`, `territorio_id`),
  KEY `ix_nor_norma_tipo` (`norma_tipo_id`),
  KEY `ix_nor_norma_jerarquia` (`norma_jerarquia_id`),
  KEY `ix_nor_organismo` (`organismo_id`),
  KEY `ix_nor_grupo` (`grupo_id`),
  KEY `ix_nor_region_grupo` (`region_grupo_id`),
  KEY `ix_nor_empresa` (`empresa_id`),
  KEY `ix_nor_territorio` (`territorio_id`),
  CONSTRAINT `ck_nor_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`),
  CONSTRAINT `ck_nor_ambito` CHECK ((`grupo_id` IS NOT NULL) + (`region_grupo_id` IS NOT NULL) + (`empresa_id` IS NOT NULL) + (`territorio_id` IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A29. ml__normas_versiones (norv) - DBML 04:509
CREATE TABLE IF NOT EXISTS `ml__normas_versiones` (
  `norma_version_id`         INT NOT NULL AUTO_INCREMENT,
  `norma_id`                 INT NOT NULL,
  `version`                  VARCHAR(50) NOT NULL,
  `fecha_publicacion`        DATE NULL DEFAULT NULL,
  `archivo_ref`              VARCHAR(500) NULL DEFAULT NULL,
  `hash`                     VARCHAR(128) NULL DEFAULT NULL,
  `es_vigente`               TINYINT(1) NOT NULL DEFAULT 0,
  `confidencial`             TINYINT(1) NOT NULL DEFAULT 0,
  `vigente_desde`            DATE NOT NULL,
  `vigente_hasta`            DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`norma_version_id`),
  UNIQUE KEY `uk_norv_norma_version` (`norma_id`, `version`),
  CONSTRAINT `ck_norv_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A30. ml__normas_relaciones_tipos (nrlt) - DBML 04:531
CREATE TABLE IF NOT EXISTS `ml__normas_relaciones_tipos` (
  `norma_relacion_tipo_id`   INT NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(30) NOT NULL,
  `nombre`                   VARCHAR(100) NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`norma_relacion_tipo_id`),
  UNIQUE KEY `uk_nrlt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A31. ml__normas_relaciones (nrl) - DBML 04:542
CREATE TABLE IF NOT EXISTS `ml__normas_relaciones` (
  `norma_relacion_id`      INT NOT NULL AUTO_INCREMENT,
  `norma_origen_id`        INT NOT NULL,
  `norma_destino_id`       INT NOT NULL,
  `norma_relacion_tipo_id` INT NOT NULL,
  `descripcion`            TEXT NULL,
  `vigente_desde`          DATE NOT NULL,
  `vigente_hasta`          DATE NULL DEFAULT NULL,
  `creado_en`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`             INT NULL DEFAULT NULL,
  `actualizado_en`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`        INT NULL DEFAULT NULL,
  PRIMARY KEY (`norma_relacion_id`),
  KEY `ix_nrl_norma_origen` (`norma_origen_id`),
  KEY `ix_nrl_norma_destino` (`norma_destino_id`),
  KEY `ix_nrl_norma_relacion_tipo` (`norma_relacion_tipo_id`),
  CONSTRAINT `ck_nrl_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A32. ml__normas_conflictos (ncf) - DBML 04:556
CREATE TABLE IF NOT EXISTS `ml__normas_conflictos` (
  `norma_conflicto_id`       INT NOT NULL AUTO_INCREMENT,
  `norma_a_id`               INT NOT NULL,
  `norma_b_id`               INT NOT NULL,
  `descripcion`              TEXT NOT NULL,
  `escalado_a`               INT NULL DEFAULT NULL,
  `decision`                 TEXT NULL,
  `decidido_por`             INT NULL DEFAULT NULL,
  `decidido_en`              DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`norma_conflicto_id`),
  KEY `ix_ncf_norma_a` (`norma_a_id`),
  KEY `ix_ncf_norma_b` (`norma_b_id`),
  KEY `ix_ncf_escalado_a` (`escalado_a`),
  KEY `ix_ncf_decidido_por` (`decidido_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A33. ml__procedimientos (prc) - DBML 04:573
CREATE TABLE IF NOT EXISTS `ml__procedimientos` (
  `procedimiento_id`         INT NOT NULL AUTO_INCREMENT,
  `empresa_id`               SMALLINT UNSIGNED NULL DEFAULT NULL,
  `norma_id`                 INT NULL DEFAULT NULL,
  `codigo`                   VARCHAR(80) NOT NULL,
  `nombre`                   VARCHAR(255) NOT NULL,
  `descripcion`              TEXT NULL,
  `confidencial`             TINYINT(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`procedimiento_id`),
  KEY `ix_prc_empresa` (`empresa_id`),
  KEY `ix_prc_norma` (`norma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A34. ml__procedimientos_versiones (prcv) - DBML 04:589
CREATE TABLE IF NOT EXISTS `ml__procedimientos_versiones` (
  `procedimiento_version_id` INT NOT NULL AUTO_INCREMENT,
  `procedimiento_id`         INT NOT NULL,
  `version`                  VARCHAR(50) NOT NULL,
  `archivo_ref`              VARCHAR(500) NULL DEFAULT NULL,
  `hash`                     VARCHAR(128) NULL DEFAULT NULL,
  `resumen_cambios`          TEXT NULL,
  `aprobado_por`             INT NULL DEFAULT NULL,
  `aprobado_en`              DATETIME NULL DEFAULT NULL,
  `vigente_desde`            DATE NOT NULL,
  `vigente_hasta`            DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`procedimiento_version_id`),
  UNIQUE KEY `uk_prcv_procedimiento_version` (`procedimiento_id`, `version`),
  KEY `ix_prcv_aprobado_por` (`aprobado_por`),
  CONSTRAINT `ck_prcv_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A35. ml__procedimientos_instrucciones (prci) - DBML 04:610
CREATE TABLE IF NOT EXISTS `ml__procedimientos_instrucciones` (
  `instruccion_id`           INT NOT NULL AUTO_INCREMENT,
  `procedimiento_version_id` INT NOT NULL,
  `orden`                    INT NOT NULL,
  `titulo`                   VARCHAR(255) NOT NULL,
  `descripcion`              TEXT NULL,
  `es_obligatoria`           TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`instruccion_id`),
  UNIQUE KEY `uk_prci_procedimiento_version_orden` (`procedimiento_version_id`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A36. ml__accesos_confidenciales (acco) - DBML 04:626
CREATE TABLE IF NOT EXISTS `ml__accesos_confidenciales` (
  `acceso_confidencial_id` INT NOT NULL AUTO_INCREMENT,
  `objeto_tabla`           VARCHAR(64) NOT NULL,
  `objeto_id`              INT NOT NULL,
  `empresa_perfil_id`      INT UNSIGNED NOT NULL,
  `ambito_tipo_id`         INT NOT NULL,
  `grupo_id`               INT NULL DEFAULT NULL,
  `region_grupo_id`        INT NULL DEFAULT NULL,
  `empresa_id`             SMALLINT UNSIGNED NULL DEFAULT NULL,
  `territorio_id`          INT NULL DEFAULT NULL,
  `vigente_desde`          DATE NOT NULL,
  `vigente_hasta`          DATE NULL DEFAULT NULL,
  `creado_en`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`             INT NULL DEFAULT NULL,
  `actualizado_en`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`        INT NULL DEFAULT NULL,
  PRIMARY KEY (`acceso_confidencial_id`),
  KEY `ix_acco_objeto_tabla_objeto` (`objeto_tabla`, `objeto_id`),
  KEY `ix_acco_empresa_perfil` (`empresa_perfil_id`),
  KEY `ix_acco_ambito_tipo` (`ambito_tipo_id`),
  KEY `ix_acco_grupo` (`grupo_id`),
  KEY `ix_acco_region_grupo` (`region_grupo_id`),
  KEY `ix_acco_empresa` (`empresa_id`),
  KEY `ix_acco_territorio` (`territorio_id`),
  CONSTRAINT `ck_acco_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`),
  CONSTRAINT `ck_acco_ambito` CHECK ((`grupo_id` IS NOT NULL) + (`region_grupo_id` IS NOT NULL) + (`empresa_id` IS NOT NULL) + (`territorio_id` IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A37. ml__auditoria_accesos (auda) - DBML 04:648
CREATE TABLE IF NOT EXISTS `ml__auditoria_accesos` (
  `auditoria_acceso_id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id`          INT NOT NULL,
  `objeto_tabla`        VARCHAR(64) NOT NULL,
  `objeto_id`           INT NOT NULL,
  `accedido_en`         DATETIME NOT NULL,
  `motivo`              VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`auditoria_acceso_id`),
  KEY `ix_auda_objeto_tabla_objeto` (`objeto_tabla`, `objeto_id`),
  KEY `ix_auda_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A38. ml__criticidades (crit) - DBML 04:665
CREATE TABLE IF NOT EXISTS `ml__criticidades` (
  `criticidad_id`            INT NOT NULL AUTO_INCREMENT,
  `nombre`                   VARCHAR(50) NOT NULL,
  `peso`                     INT NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`criticidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A39. ml__periodicidades (peri) - DBML 04:676
CREATE TABLE IF NOT EXISTS `ml__periodicidades` (
  `periodicidad_id`          INT NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(30) NOT NULL,
  `nombre`                   VARCHAR(100) NOT NULL,
  `intervalo_cantidad`       INT NULL DEFAULT NULL,
  `intervalo_unidad`         VARCHAR(10) NULL DEFAULT NULL,
  `es_evento`                TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_fija_autoridad`     TINYINT(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`periodicidad_id`),
  UNIQUE KEY `uk_peri_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A40. ml__vencimientos_reglas (venr) - DBML 04:691
CREATE TABLE IF NOT EXISTS `ml__vencimientos_reglas` (
  `vencimiento_regla_id`     INT NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(50) NOT NULL,
  `nombre`                   VARCHAR(150) NOT NULL,
  `tipo`                     VARCHAR(30) NOT NULL,
  `dato_tipo_id`             INT NULL DEFAULT NULL,
  `desplazamiento_dias`      INT NULL DEFAULT NULL,
  `parametros`               TEXT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`vencimiento_regla_id`),
  UNIQUE KEY `uk_venr_codigo` (`codigo`),
  KEY `ix_venr_dato_tipo` (`dato_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A41. ml__obligaciones (obl) - DBML 04:706
CREATE TABLE IF NOT EXISTS `ml__obligaciones` (
  `obligacion_id`              INT NOT NULL AUTO_INCREMENT,
  `norma_id`                   INT NOT NULL,
  `empresa_id`                 SMALLINT UNSIGNED NULL DEFAULT NULL,
  `articulo_referencia`        VARCHAR(100) NULL DEFAULT NULL,
  `codigo`                     VARCHAR(80) NOT NULL,
  `descripcion`                TEXT NOT NULL,
  `periodicidad_id`            INT NOT NULL,
  `vencimiento_regla_id`       INT NOT NULL,
  `criticidad_id`              INT NULL DEFAULT NULL,
  `requiere_revision`          TINYINT(1) NOT NULL DEFAULT 1,
  `requiere_evidencia`         TINYINT(1) NOT NULL DEFAULT 1,
  `retencion_evidencias_meses` INT NULL DEFAULT NULL,
  `vigente_desde`              DATE NOT NULL,
  `vigente_hasta`              DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id`   SMALLINT NOT NULL,
  `creado_en`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                 INT NULL DEFAULT NULL,
  `actualizado_en`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`            INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_id`),
  UNIQUE KEY `uk_obl_norma_codigo` (`norma_id`, `codigo`),
  KEY `ix_obl_norma_hasta` (`norma_id`, `vigente_hasta`),
  KEY `ix_obl_empresa` (`empresa_id`),
  KEY `ix_obl_periodicidad` (`periodicidad_id`),
  KEY `ix_obl_vencimiento_regla` (`vencimiento_regla_id`),
  KEY `ix_obl_criticidad` (`criticidad_id`),
  CONSTRAINT `ck_obl_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A42. ml__obligaciones_tipos_entidades (oblt) - DBML 04:733
CREATE TABLE IF NOT EXISTS `ml__obligaciones_tipos_entidades` (
  `obligacion_tipo_entidad_id` INT NOT NULL AUTO_INCREMENT,
  `obligacion_id`              INT NOT NULL,
  `entidad_tipo_id`            INT NOT NULL,
  `regla_json`                 TEXT NULL,
  `vigente_desde`              DATE NOT NULL,
  `vigente_hasta`              DATE NULL DEFAULT NULL,
  `creado_en`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                 INT NULL DEFAULT NULL,
  `actualizado_en`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`            INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_tipo_entidad_id`),
  KEY `ix_oblt_obligacion_entidad_tipo_desde` (`obligacion_id`, `entidad_tipo_id`, `vigente_desde`),
  KEY `ix_oblt_entidad_tipo` (`entidad_tipo_id`),
  CONSTRAINT `ck_oblt_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A43. ml__obligaciones_jurisdicciones (oblj) - DBML 04:749
CREATE TABLE IF NOT EXISTS `ml__obligaciones_jurisdicciones` (
  `obligacion_jurisdiccion_id` INT NOT NULL AUTO_INCREMENT,
  `obligacion_id`              INT NOT NULL,
  `territorio_id`              INT NOT NULL,
  `vigente_desde`              DATE NOT NULL,
  `vigente_hasta`              DATE NULL DEFAULT NULL,
  `creado_en`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                 INT NULL DEFAULT NULL,
  `actualizado_en`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`            INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_jurisdiccion_id`),
  KEY `ix_oblj_obligacion` (`obligacion_id`),
  KEY `ix_oblj_territorio` (`territorio_id`),
  CONSTRAINT `ck_oblj_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A44. ml__obligaciones_dependencias (obld) - DBML 04:762
CREATE TABLE IF NOT EXISTS `ml__obligaciones_dependencias` (
  `obligacion_dependencia_id` INT NOT NULL AUTO_INCREMENT,
  `obligacion_id`             INT NOT NULL,
  `obligacion_predecesora_id` INT NOT NULL,
  `vigente_desde`             DATE NOT NULL,
  `vigente_hasta`             DATE NULL DEFAULT NULL,
  `creado_en`                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                INT NULL DEFAULT NULL,
  `actualizado_en`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`           INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_dependencia_id`),
  KEY `ix_obld_obligacion_obligacion_predecesora` (`obligacion_id`, `obligacion_predecesora_id`),
  KEY `ix_obld_obligacion_predecesora` (`obligacion_predecesora_id`),
  CONSTRAINT `ck_obld_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A45. ml__obligaciones_alertas (obla) - DBML 04:778
CREATE TABLE IF NOT EXISTS `ml__obligaciones_alertas` (
  `obligacion_alerta_id` INT NOT NULL AUTO_INCREMENT,
  `obligacion_id`        INT NOT NULL,
  `dias_antes`           INT NOT NULL,
  `destinatario`         VARCHAR(20) NOT NULL,
  `canal`                VARCHAR(20) NOT NULL,
  `creado_en`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`           INT NULL DEFAULT NULL,
  `actualizado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`      INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_alerta_id`),
  KEY `ix_obla_obligacion` (`obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A46. ml__tareas_plantillas (tpl) - DBML 04:790
CREATE TABLE IF NOT EXISTS `ml__tareas_plantillas` (
  `tarea_plantilla_id`       INT NOT NULL AUTO_INCREMENT,
  `obligacion_id`            INT NOT NULL,
  `orden`                    INT NOT NULL,
  `titulo`                   VARCHAR(255) NOT NULL,
  `descripcion`              TEXT NULL,
  `offset_dias_inicio`       INT NULL DEFAULT NULL,
  `offset_dias_vencimiento`  INT NULL DEFAULT NULL,
  `procedimiento_id`         INT NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`tarea_plantilla_id`),
  UNIQUE KEY `uk_tpl_obligacion_orden` (`obligacion_id`, `orden`),
  KEY `ix_tpl_procedimiento` (`procedimiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A47. ml__tareas_plantillas_dependencias (tpld) - DBML 04:810
CREATE TABLE IF NOT EXISTS `ml__tareas_plantillas_dependencias` (
  `tarea_plantilla_dependencia_id` INT NOT NULL AUTO_INCREMENT,
  `tarea_plantilla_id`             INT NOT NULL,
  `tarea_plantilla_predecesora_id` INT NOT NULL,
  `estado_activador_id`            SMALLINT NOT NULL,
  `obligatoria`                    TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en`                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                     INT NULL DEFAULT NULL,
  `actualizado_en`                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`                INT NULL DEFAULT NULL,
  PRIMARY KEY (`tarea_plantilla_dependencia_id`),
  UNIQUE KEY `uk_tpld_tarea_plantilla_tarea_plantilla_predecesora` (`tarea_plantilla_id`, `tarea_plantilla_predecesora_id`),
  KEY `ix_tpld_tarea_plantilla_predecesora` (`tarea_plantilla_predecesora_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A48. ml__obligaciones_asignadas (oas) - DBML 04:829
CREATE TABLE IF NOT EXISTS `ml__obligaciones_asignadas` (
  `obligacion_asignada_id`      INT NOT NULL AUTO_INCREMENT,
  `obligacion_prevaleciente_id` INT NOT NULL,
  `entidad_id`                  INT NOT NULL,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `vigente_desde`               DATE NOT NULL,
  `vigente_hasta`               DATE NULL DEFAULT NULL,
  `tabla_estado_registro_id`    SMALLINT NOT NULL DEFAULT 1,
  `creado_en`                   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                  INT NULL DEFAULT NULL,
  `actualizado_en`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`             INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_asignada_id`),
  KEY `ix_oas_entidad_obligacion_prevaleciente_desde` (`entidad_id`, `obligacion_prevaleciente_id`, `vigente_desde`),
  KEY `ix_oas_empresa_hasta` (`empresa_id`, `vigente_hasta`),
  KEY `ix_oas_obligacion_prevaleciente` (`obligacion_prevaleciente_id`),
  CONSTRAINT `ck_oas_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A49. ml__obligaciones_asignadas_origenes (oaso) - DBML 04:848
CREATE TABLE IF NOT EXISTS `ml__obligaciones_asignadas_origenes` (
  `obligacion_asignada_origen_id` INT NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id`        INT NOT NULL,
  `obligacion_id`                 INT NOT NULL,
  `creado_en`                     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                    INT NULL DEFAULT NULL,
  `actualizado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`               INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_asignada_origen_id`),
  UNIQUE KEY `uk_oaso_obligacion_asignada_obligacion` (`obligacion_asignada_id`, `obligacion_id`),
  KEY `ix_oaso_obligacion` (`obligacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A50. ml__excepciones (exc) - DBML 04:861
CREATE TABLE IF NOT EXISTS `ml__excepciones` (
  `excepcion_id`             INT NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id`   INT NOT NULL,
  `motivo`                   TEXT NOT NULL,
  `fundamento`               TEXT NULL,
  `fundamento_ref`           VARCHAR(500) NULL DEFAULT NULL,
  `solicitada_por`           INT NULL DEFAULT NULL,
  `aprobada_por`             INT NULL DEFAULT NULL,
  `aprobada_legal_por`       INT NULL DEFAULT NULL,
  `aprobada_en`              DATETIME NULL DEFAULT NULL,
  `vigente_desde`            DATE NOT NULL,
  `vigente_hasta`            DATE NOT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`excepcion_id`),
  KEY `ix_exc_obligacion_asignada_hasta` (`obligacion_asignada_id`, `vigente_hasta`),
  KEY `ix_exc_empresa` (`empresa_id`),
  KEY `ix_exc_solicitada_por` (`solicitada_por`),
  KEY `ix_exc_aprobada_por` (`aprobada_por`),
  KEY `ix_exc_aprobada_legal_por` (`aprobada_legal_por`),
  CONSTRAINT `ck_exc_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A51. ml__recalculos_pendientes (recp) - DBML 04:885
CREATE TABLE IF NOT EXISTS `ml__recalculos_pendientes` (
  `recalculo_pendiente_id`   INT NOT NULL AUTO_INCREMENT,
  `causa`                    VARCHAR(40) NOT NULL,
  `objeto_tabla`             VARCHAR(64) NOT NULL,
  `objeto_id`                INT NOT NULL,
  `fecha_efecto`             DATE NOT NULL,
  `creado_en`                DATETIME NOT NULL,
  `procesado_en`             DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  PRIMARY KEY (`recalculo_pendiente_id`),
  KEY `ix_recp_tabla_estado_registro_fecha_efecto` (`tabla_estado_registro_id`, `fecha_efecto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A52. ml__ciclos_vinculos_tipos (cvt) - DBML 04:904
CREATE TABLE IF NOT EXISTS `ml__ciclos_vinculos_tipos` (
  `ciclo_vinculo_tipo_id` INT NOT NULL AUTO_INCREMENT,
  `codigo`                VARCHAR(30) NOT NULL,
  `nombre`                VARCHAR(100) NOT NULL,
  `creado_en`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`            INT NULL DEFAULT NULL,
  `actualizado_en`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`       INT NULL DEFAULT NULL,
  PRIMARY KEY (`ciclo_vinculo_tipo_id`),
  UNIQUE KEY `uk_cvt_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A53. ml__ciclos (cic) - DBML 04:914
CREATE TABLE IF NOT EXISTS `ml__ciclos` (
  `ciclo_id`                 INT NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id`   INT NOT NULL,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `entidad_id`               INT NOT NULL,
  `norma_version_id`         INT NULL DEFAULT NULL,
  `ciclo_origen_id`          INT NULL DEFAULT NULL,
  `ciclo_vinculo_tipo_id`    INT NULL DEFAULT NULL,
  `periodo_desde`            DATE NOT NULL,
  `periodo_hasta`            DATE NOT NULL,
  `fecha_inicio`             DATETIME NULL DEFAULT NULL,
  `fecha_vencimiento`        DATETIME NOT NULL,
  `fecha_cierre`             DATETIME NULL DEFAULT NULL,
  `observaciones`            TEXT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`ciclo_id`),
  KEY `ix_cic_obligacion_asignada_periodo_desde` (`obligacion_asignada_id`, `periodo_desde`),
  KEY `ix_cic_empresa_fecha_vencimiento` (`empresa_id`, `fecha_vencimiento`),
  KEY `ix_cic_entidad` (`entidad_id`),
  KEY `ix_cic_ciclo_origen` (`ciclo_origen_id`),
  KEY `ix_cic_norma_version` (`norma_version_id`),
  KEY `ix_cic_ciclo_vinculo_tipo` (`ciclo_vinculo_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A54. ml__ciclos_tareas (cta) - DBML 04:942
CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas` (
  `ciclo_tarea_id`           INT NOT NULL AUTO_INCREMENT,
  `ciclo_id`                 INT NOT NULL,
  `tarea_plantilla_id`       INT NULL DEFAULT NULL,
  `titulo`                   VARCHAR(255) NOT NULL,
  `descripcion`              TEXT NULL,
  `procedimiento_version_id` INT NULL DEFAULT NULL,
  `requiere_revision`        TINYINT(1) NOT NULL,
  `requiere_evidencia`       TINYINT(1) NOT NULL,
  `fecha_activacion`         DATETIME NULL DEFAULT NULL,
  `fecha_inicio`             DATETIME NULL DEFAULT NULL,
  `fecha_vencimiento`        DATETIME NULL DEFAULT NULL,
  `fecha_cierre`             DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`ciclo_tarea_id`),
  KEY `ix_cta_ciclo` (`ciclo_id`),
  KEY `ix_cta_empresa_tabla_estado_registro_fecha_vencimiento` (`empresa_id`, `tabla_estado_registro_id`, `fecha_vencimiento`),
  KEY `ix_cta_tarea_plantilla` (`tarea_plantilla_id`),
  KEY `ix_cta_procedimiento_version` (`procedimiento_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A55. ml__ciclos_tareas_dependencias (ctad) - DBML 04:968
CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas_dependencias` (
  `ciclo_tarea_dependencia_id` INT NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id`             INT NOT NULL,
  `ciclo_tarea_predecesora_id` INT NOT NULL,
  `estado_activador_id`        SMALLINT NOT NULL,
  `obligatoria`                TINYINT(1) NOT NULL DEFAULT 1,
  `empresa_id`                 SMALLINT UNSIGNED NOT NULL,
  `creado_en`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                 INT NULL DEFAULT NULL,
  `actualizado_en`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`            INT NULL DEFAULT NULL,
  PRIMARY KEY (`ciclo_tarea_dependencia_id`),
  UNIQUE KEY `uk_ctad_ciclo_tarea_ciclo_tarea_predecesora` (`ciclo_tarea_id`, `ciclo_tarea_predecesora_id`),
  KEY `ix_ctad_ciclo_tarea_predecesora` (`ciclo_tarea_predecesora_id`),
  KEY `ix_ctad_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A56. ml__obligaciones_asignadas_raci (oasr) - DBML 04:984
CREATE TABLE IF NOT EXISTS `ml__obligaciones_asignadas_raci` (
  `obligacion_asignada_raci_id` INT NOT NULL AUTO_INCREMENT,
  `obligacion_asignada_id`      INT NOT NULL,
  `raci_rol_id`                 INT NOT NULL,
  `usuario_id`                  INT NOT NULL,
  `vigente_desde`               DATE NOT NULL,
  `vigente_hasta`               DATE NULL DEFAULT NULL,
  `empresa_id`                  SMALLINT UNSIGNED NOT NULL,
  `creado_en`                   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                  INT NULL DEFAULT NULL,
  `actualizado_en`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`             INT NULL DEFAULT NULL,
  PRIMARY KEY (`obligacion_asignada_raci_id`),
  UNIQUE KEY `uk_oasr_obligacion_asignada_raci_rol_usuario_desde` (`obligacion_asignada_id`, `raci_rol_id`, `usuario_id`, `vigente_desde`),
  KEY `ix_oasr_usuario_hasta` (`usuario_id`, `vigente_hasta`),
  KEY `ix_oasr_raci_rol` (`raci_rol_id`),
  KEY `ix_oasr_empresa` (`empresa_id`),
  CONSTRAINT `ck_oasr_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A57. ml__ciclos_raci (cicr) - DBML 04:1002
CREATE TABLE IF NOT EXISTS `ml__ciclos_raci` (
  `ciclo_raci_id`   INT NOT NULL AUTO_INCREMENT,
  `ciclo_id`        INT NOT NULL,
  `raci_rol_id`     INT NOT NULL,
  `usuario_id`      INT NOT NULL,
  `vigente_desde`   DATE NOT NULL,
  `vigente_hasta`   DATE NULL DEFAULT NULL,
  `empresa_id`      SMALLINT UNSIGNED NOT NULL,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`ciclo_raci_id`),
  UNIQUE KEY `uk_cicr_ciclo_raci_rol_usuario_desde` (`ciclo_id`, `raci_rol_id`, `usuario_id`, `vigente_desde`),
  KEY `ix_cicr_usuario_hasta` (`usuario_id`, `vigente_hasta`),
  KEY `ix_cicr_raci_rol` (`raci_rol_id`),
  KEY `ix_cicr_empresa` (`empresa_id`),
  CONSTRAINT `ck_cicr_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A58. ml__ciclos_tareas_raci (ctar) - DBML 04:1020
CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas_raci` (
  `ciclo_tarea_raci_id` INT NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id`      INT NOT NULL,
  `raci_rol_id`         INT NOT NULL,
  `usuario_id`          INT NOT NULL,
  `vigente_desde`       DATE NOT NULL,
  `vigente_hasta`       DATE NULL DEFAULT NULL,
  `empresa_id`          SMALLINT UNSIGNED NOT NULL,
  `creado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`          INT NULL DEFAULT NULL,
  `actualizado_en`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`     INT NULL DEFAULT NULL,
  PRIMARY KEY (`ciclo_tarea_raci_id`),
  UNIQUE KEY `uk_ctar_ciclo_tarea_raci_rol_usuario_desde` (`ciclo_tarea_id`, `raci_rol_id`, `usuario_id`, `vigente_desde`),
  KEY `ix_ctar_usuario_hasta` (`usuario_id`, `vigente_hasta`),
  KEY `ix_ctar_raci_rol` (`raci_rol_id`),
  KEY `ix_ctar_empresa` (`empresa_id`),
  CONSTRAINT `ck_ctar_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A59. ml__reasignaciones (reas) - DBML 04:1038
CREATE TABLE IF NOT EXISTS `ml__reasignaciones` (
  `reasignacion_id`          INT NOT NULL AUTO_INCREMENT,
  `usuario_origen_id`        INT NOT NULL,
  `usuario_destino_id`       INT NOT NULL,
  `fecha_efecto`             DATE NOT NULL,
  `motivo`                   VARCHAR(255) NULL DEFAULT NULL,
  `aplicada_por`             INT NULL DEFAULT NULL,
  `aplicada_en`              DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`reasignacion_id`),
  KEY `ix_reas_usuario_origen` (`usuario_origen_id`),
  KEY `ix_reas_usuario_destino` (`usuario_destino_id`),
  KEY `ix_reas_aplicada_por` (`aplicada_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A60. ml__evidencias (evi) - DBML 04:1058
CREATE TABLE IF NOT EXISTS `ml__evidencias` (
  `evidencia_id`             INT NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id`           INT NOT NULL,
  `titulo`                   VARCHAR(255) NOT NULL,
  `descripcion`              TEXT NULL,
  `archivo_ref`              VARCHAR(500) NOT NULL,
  `hash`                     VARCHAR(128) NULL DEFAULT NULL,
  `es_comprobante`           TINYINT(1) NOT NULL DEFAULT 0,
  `valida_hasta`             DATE NULL DEFAULT NULL,
  `retener_hasta`            DATE NULL DEFAULT NULL,
  `confidencial`             TINYINT(1) NOT NULL DEFAULT 0,
  `tabla_estado_registro_id` SMALLINT NOT NULL DEFAULT 1,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`evidencia_id`),
  KEY `ix_evi_ciclo_tarea` (`ciclo_tarea_id`),
  KEY `ix_evi_valida_hasta` (`valida_hasta`),
  KEY `ix_evi_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A61. ml__alertas (ale) - DBML 04:1081
CREATE TABLE IF NOT EXISTS `ml__alertas` (
  `alerta_id`                INT NOT NULL AUTO_INCREMENT,
  `ciclo_id`                 INT NULL DEFAULT NULL,
  `ciclo_tarea_id`           INT NULL DEFAULT NULL,
  `evidencia_id`             INT NULL DEFAULT NULL,
  `usuario_id`               INT NOT NULL,
  `canal`                    VARCHAR(20) NOT NULL,
  `programada_para`          DATETIME NOT NULL,
  `enviada_en`               DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`alerta_id`),
  KEY `ix_ale_programada_para_enviada_en` (`programada_para`, `enviada_en`),
  KEY `ix_ale_usuario` (`usuario_id`),
  KEY `ix_ale_ciclo` (`ciclo_id`),
  KEY `ix_ale_ciclo_tarea` (`ciclo_tarea_id`),
  KEY `ix_ale_evidencia` (`evidencia_id`),
  KEY `ix_ale_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A62. ml__incidencias (inci) - DBML 04:1102
CREATE TABLE IF NOT EXISTS `ml__incidencias` (
  `incidencia_id`            INT NOT NULL AUTO_INCREMENT,
  `ciclo_id`                 INT NOT NULL,
  `descripcion`              TEXT NULL,
  `abierta_en`               DATETIME NOT NULL,
  `ciclo_seguimiento_id`     INT NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`incidencia_id`),
  KEY `ix_inci_ciclo` (`ciclo_id`),
  KEY `ix_inci_ciclo_seguimiento` (`ciclo_seguimiento_id`),
  KEY `ix_inci_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A63. ml__indicadores_cierres (indc) - DBML 04:1120
CREATE TABLE IF NOT EXISTS `ml__indicadores_cierres` (
  `indicador_cierre_id`         INT NOT NULL AUTO_INCREMENT,
  `grupo_id`                    INT NOT NULL,
  `periodo_desde`               DATE NOT NULL,
  `periodo_hasta`               DATE NOT NULL,
  `empresa_id`                  SMALLINT UNSIGNED NULL DEFAULT NULL,
  `region_grupo_id`             INT NULL DEFAULT NULL,
  `territorio_id`               INT NULL DEFAULT NULL,
  `ciclos_contados`             INT NOT NULL,
  `cumplidos_a_tiempo`          INT NOT NULL,
  `cumplidos_fuera_plazo`       INT NOT NULL,
  `incumplidos`                 INT NOT NULL,
  `eximidos`                    INT NOT NULL,
  `por_vencer`                  INT NOT NULL,
  `ponderacion_opcion_valor_id` INT NULL DEFAULT NULL,
  `calculado_en`                DATETIME NOT NULL,
  `creado_en`                   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                  INT NULL DEFAULT NULL,
  `actualizado_en`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`             INT NULL DEFAULT NULL,
  PRIMARY KEY (`indicador_cierre_id`),
  KEY `ix_indc_grupo_periodo_desde_periodo_hasta` (`grupo_id`, `periodo_desde`, `periodo_hasta`),
  KEY `ix_indc_empresa` (`empresa_id`),
  KEY `ix_indc_region_grupo` (`region_grupo_id`),
  KEY `ix_indc_territorio` (`territorio_id`),
  KEY `ix_indc_ponderacion_opcion_valor` (`ponderacion_opcion_valor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A64. ml__cargas_tipos (cart) - DBML 04:1150
CREATE TABLE IF NOT EXISTS `ml__cargas_tipos` (
  `carga_tipo_id`   INT NOT NULL AUTO_INCREMENT,
  `codigo`          VARCHAR(30) NOT NULL,
  `nombre`          VARCHAR(100) NOT NULL,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`carga_tipo_id`),
  UNIQUE KEY `uk_cart_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A65. ml__cargas (car) - DBML 04:1160
CREATE TABLE IF NOT EXISTS `ml__cargas` (
  `carga_id`                 INT NOT NULL AUTO_INCREMENT,
  `carga_tipo_id`            INT NOT NULL,
  `empresa_id`               SMALLINT UNSIGNED NULL DEFAULT NULL,
  `archivo_ref`              VARCHAR(500) NOT NULL,
  `validada_en`              DATETIME NULL DEFAULT NULL,
  `confirmada_por`           INT NULL DEFAULT NULL,
  `confirmada_en`            DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`carga_id`),
  KEY `ix_car_carga_tipo` (`carga_tipo_id`),
  KEY `ix_car_empresa` (`empresa_id`),
  KEY `ix_car_confirmada_por` (`confirmada_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A66. ml__cargas_filas (carf) - DBML 04:1176
CREATE TABLE IF NOT EXISTS `ml__cargas_filas` (
  `carga_fila_id`   INT NOT NULL AUTO_INCREMENT,
  `carga_id`        INT NOT NULL,
  `nro_fila`        INT NOT NULL,
  `contenido`       TEXT NULL,
  `errores`         TEXT NULL,
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT NULL DEFAULT NULL,
  `actualizado_en`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por` INT NULL DEFAULT NULL,
  PRIMARY KEY (`carga_fila_id`),
  UNIQUE KEY `uk_carf_carga_nro_fila` (`carga_id`, `nro_fila`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A67. ml__cambios_masivos (cmas) - DBML 04:1191
CREATE TABLE IF NOT EXISTS `ml__cambios_masivos` (
  `cambio_masivo_id`         INT NOT NULL AUTO_INCREMENT,
  `norma_version_id`         INT NULL DEFAULT NULL,
  `descripcion`              TEXT NOT NULL,
  `solicitado_por`           INT NULL DEFAULT NULL,
  `requiere_aprobacion`      TINYINT(1) NOT NULL DEFAULT 1,
  `aprobado_por`             INT NULL DEFAULT NULL,
  `aprobado_en`              DATETIME NULL DEFAULT NULL,
  `aplicado_en`              DATETIME NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`cambio_masivo_id`),
  KEY `ix_cmas_norma_version` (`norma_version_id`),
  KEY `ix_cmas_solicitado_por` (`solicitado_por`),
  KEY `ix_cmas_aprobado_por` (`aprobado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A68. ml__cambios_masivos_detalles (cmad) - DBML 04:1207
CREATE TABLE IF NOT EXISTS `ml__cambios_masivos_detalles` (
  `cambio_masivo_detalle_id` INT NOT NULL AUTO_INCREMENT,
  `cambio_masivo_id`         INT NOT NULL,
  `entidad_id`               INT NOT NULL,
  `ciclos_afectados`         INT NOT NULL,
  `tareas_afectadas`         INT NOT NULL,
  `creado_en`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`               INT NULL DEFAULT NULL,
  `actualizado_en`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`          INT NULL DEFAULT NULL,
  PRIMARY KEY (`cambio_masivo_detalle_id`),
  KEY `ix_cmad_cambio_masivo` (`cambio_masivo_id`),
  KEY `ix_cmad_entidad` (`entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A69. ml__log_cambios (logc) - DBML 04:1227
CREATE TABLE IF NOT EXISTS `ml__log_cambios` (
  `log_cambio_id` INT NOT NULL AUTO_INCREMENT,
  `tabla`         VARCHAR(64) NOT NULL,
  `registro_id`   INT NOT NULL,
  `accion`        VARCHAR(20) NOT NULL,
  `antes_json`    TEXT NULL,
  `despues_json`  TEXT NULL,
  `usuario_id`    INT NULL DEFAULT NULL,
  `fecha`         DATETIME NOT NULL,
  PRIMARY KEY (`log_cambio_id`),
  KEY `ix_logc_tabla_registro` (`tabla`, `registro_id`),
  KEY `ix_logc_fecha` (`fecha`),
  KEY `ix_logc_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- FASE B: las 149 FK, una por CALL (idempotente). Sin ON DELETE ni
-- ON UPDATE: queda el comportamiento por defecto (RESTRICT / NO ACTION).
--
-- Cada ADD FOREIGN KEY hacia conf__empresas, conf__usuarios o
-- conf__empresas_perfiles toma un lock de metadatos sobre la tabla padre:
-- si otra conexión tiene una transacción abierta que la leyó, el ALTER
-- espera, y mientras espera frena el login (login.php) y los permisos
-- (core/permisos.php). Antes de esta fase, a mano (requiere PROCESS):
--   SELECT trx_mysql_thread_id, trx_started FROM information_schema.innodb_trx
--    WHERE trx_started < NOW() - INTERVAL 10 SECOND;   -> debe dar 0 filas
-- y que no esté corriendo ningún mysqldump (por ejemplo el respaldo E2).
-- Por eso el timeout corto: un ALTER bloqueado falla a los 10 s (ERROR
-- 1205) y se vuelve a correr el script completo.
-- =====================================================================
SET SESSION lock_wait_timeout = 10;

-- ml__grupos
CALL `_m0_add_fk`('ml__grupos', 'fk_grp_empresa_titular_id', 'FOREIGN KEY (`empresa_titular_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__regiones_grupo
CALL `_m0_add_fk`('ml__regiones_grupo', 'fk_rgr_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');

-- ml__empresas_regiones
CALL `_m0_add_fk`('ml__empresas_regiones', 'fk_ereg_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__empresas_regiones', 'fk_ereg_region_grupo_id', 'FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`)');

-- ml__empresas_nombres
CALL `_m0_add_fk`('ml__empresas_nombres', 'fk_enom_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__empresas_eventos
CALL `_m0_add_fk`('ml__empresas_eventos', 'fk_eev_empresa_evento_tipo_id', 'FOREIGN KEY (`empresa_evento_tipo_id`) REFERENCES `ml__empresas_eventos_tipos` (`empresa_evento_tipo_id`)');
CALL `_m0_add_fk`('ml__empresas_eventos', 'fk_eev_empresa_origen_id', 'FOREIGN KEY (`empresa_origen_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__empresas_eventos', 'fk_eev_empresa_destino_id', 'FOREIGN KEY (`empresa_destino_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__territorios
CALL `_m0_add_fk`('ml__territorios', 'fk_ter_territorio_tipo_id', 'FOREIGN KEY (`territorio_tipo_id`) REFERENCES `ml__territorios_tipos` (`territorio_tipo_id`)');

-- ml__territorios_pertenencias
CALL `_m0_add_fk`('ml__territorios_pertenencias', 'fk_terp_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');
CALL `_m0_add_fk`('ml__territorios_pertenencias', 'fk_terp_territorio_superior_id', 'FOREIGN KEY (`territorio_superior_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__feriados
CALL `_m0_add_fk`('ml__feriados', 'fk_fer_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__entidades_tipos
CALL `_m0_add_fk`('ml__entidades_tipos', 'fk_entt_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');
CALL `_m0_add_fk`('ml__entidades_tipos', 'fk_entt_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__entidades
CALL `_m0_add_fk`('ml__entidades', 'fk_ent_entidad_tipo_id', 'FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`)');
CALL `_m0_add_fk`('ml__entidades', 'fk_ent_entidad_padre_id', 'FOREIGN KEY (`entidad_padre_id`) REFERENCES `ml__entidades` (`entidad_id`)');

-- ml__empresas_grupo
CALL `_m0_add_fk`('ml__empresas_grupo', 'fk_egr_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__empresas_grupo', 'fk_egr_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');
CALL `_m0_add_fk`('ml__empresas_grupo', 'fk_egr_pais_territorio_id', 'FOREIGN KEY (`pais_territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');
CALL `_m0_add_fk`('ml__empresas_grupo', 'fk_egr_entidad_raiz_id', 'FOREIGN KEY (`entidad_raiz_id`) REFERENCES `ml__entidades` (`entidad_id`)');

-- ml__entidades_empresas
CALL `_m0_add_fk`('ml__entidades_empresas', 'fk_ente_entidad_id', 'FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`)');
CALL `_m0_add_fk`('ml__entidades_empresas', 'fk_ente_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__entidades_ubicaciones
CALL `_m0_add_fk`('ml__entidades_ubicaciones', 'fk_entu_entidad_id', 'FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`)');
CALL `_m0_add_fk`('ml__entidades_ubicaciones', 'fk_entu_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__entidades_datos
CALL `_m0_add_fk`('ml__entidades_datos', 'fk_entd_entidad_id', 'FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`)');
CALL `_m0_add_fk`('ml__entidades_datos', 'fk_entd_dato_tipo_id', 'FOREIGN KEY (`dato_tipo_id`) REFERENCES `ml__datos_tipos` (`dato_tipo_id`)');

-- ml__usuarios_ambitos
CALL `_m0_add_fk`('ml__usuarios_ambitos', 'fk_usam_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__usuarios_ambitos', 'fk_usam_empresa_perfil_id', 'FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`)');
CALL `_m0_add_fk`('ml__usuarios_ambitos', 'fk_usam_ambito_tipo_id', 'FOREIGN KEY (`ambito_tipo_id`) REFERENCES `ml__ambitos_tipos` (`ambito_tipo_id`)');
CALL `_m0_add_fk`('ml__usuarios_ambitos', 'fk_usam_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');
CALL `_m0_add_fk`('ml__usuarios_ambitos', 'fk_usam_region_grupo_id', 'FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`)');
CALL `_m0_add_fk`('ml__usuarios_ambitos', 'fk_usam_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__usuarios_ambitos', 'fk_usam_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__delegaciones
CALL `_m0_add_fk`('ml__delegaciones', 'fk_dele_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__delegaciones', 'fk_dele_usuario_suplente_id', 'FOREIGN KEY (`usuario_suplente_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__delegaciones', 'fk_dele_empresa_perfil_id', 'FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`)');

-- ml__opciones_valores
CALL `_m0_add_fk`('ml__opciones_valores', 'fk_opcv_opcion_id', 'FOREIGN KEY (`opcion_id`) REFERENCES `ml__opciones` (`opcion_id`)');

-- ml__configuracion_grupo
CALL `_m0_add_fk`('ml__configuracion_grupo', 'fk_cfgg_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');
CALL `_m0_add_fk`('ml__configuracion_grupo', 'fk_cfgg_opcion_id', 'FOREIGN KEY (`opcion_id`) REFERENCES `ml__opciones` (`opcion_id`)');
CALL `_m0_add_fk`('ml__configuracion_grupo', 'fk_cfgg_opcion_valor_id', 'FOREIGN KEY (`opcion_valor_id`) REFERENCES `ml__opciones_valores` (`opcion_valor_id`)');

-- ml__organismos
CALL `_m0_add_fk`('ml__organismos', 'fk_org_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__normas
CALL `_m0_add_fk`('ml__normas', 'fk_nor_norma_tipo_id', 'FOREIGN KEY (`norma_tipo_id`) REFERENCES `ml__normas_tipos` (`norma_tipo_id`)');
CALL `_m0_add_fk`('ml__normas', 'fk_nor_norma_jerarquia_id', 'FOREIGN KEY (`norma_jerarquia_id`) REFERENCES `ml__normas_jerarquias` (`norma_jerarquia_id`)');
CALL `_m0_add_fk`('ml__normas', 'fk_nor_organismo_id', 'FOREIGN KEY (`organismo_id`) REFERENCES `ml__organismos` (`organismo_id`)');
CALL `_m0_add_fk`('ml__normas', 'fk_nor_ambito_tipo_id', 'FOREIGN KEY (`ambito_tipo_id`) REFERENCES `ml__ambitos_tipos` (`ambito_tipo_id`)');
CALL `_m0_add_fk`('ml__normas', 'fk_nor_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');
CALL `_m0_add_fk`('ml__normas', 'fk_nor_region_grupo_id', 'FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`)');
CALL `_m0_add_fk`('ml__normas', 'fk_nor_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__normas', 'fk_nor_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__normas_versiones
CALL `_m0_add_fk`('ml__normas_versiones', 'fk_norv_norma_id', 'FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`)');

-- ml__normas_relaciones
CALL `_m0_add_fk`('ml__normas_relaciones', 'fk_nrl_norma_origen_id', 'FOREIGN KEY (`norma_origen_id`) REFERENCES `ml__normas` (`norma_id`)');
CALL `_m0_add_fk`('ml__normas_relaciones', 'fk_nrl_norma_destino_id', 'FOREIGN KEY (`norma_destino_id`) REFERENCES `ml__normas` (`norma_id`)');
CALL `_m0_add_fk`('ml__normas_relaciones', 'fk_nrl_norma_relacion_tipo_id', 'FOREIGN KEY (`norma_relacion_tipo_id`) REFERENCES `ml__normas_relaciones_tipos` (`norma_relacion_tipo_id`)');

-- ml__normas_conflictos
CALL `_m0_add_fk`('ml__normas_conflictos', 'fk_ncf_norma_a_id', 'FOREIGN KEY (`norma_a_id`) REFERENCES `ml__normas` (`norma_id`)');
CALL `_m0_add_fk`('ml__normas_conflictos', 'fk_ncf_norma_b_id', 'FOREIGN KEY (`norma_b_id`) REFERENCES `ml__normas` (`norma_id`)');
CALL `_m0_add_fk`('ml__normas_conflictos', 'fk_ncf_escalado_a', 'FOREIGN KEY (`escalado_a`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__normas_conflictos', 'fk_ncf_decidido_por', 'FOREIGN KEY (`decidido_por`) REFERENCES `conf__usuarios` (`usuario_id`)');

-- ml__procedimientos
CALL `_m0_add_fk`('ml__procedimientos', 'fk_prc_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__procedimientos', 'fk_prc_norma_id', 'FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`)');

-- ml__procedimientos_versiones
CALL `_m0_add_fk`('ml__procedimientos_versiones', 'fk_prcv_procedimiento_id', 'FOREIGN KEY (`procedimiento_id`) REFERENCES `ml__procedimientos` (`procedimiento_id`)');
CALL `_m0_add_fk`('ml__procedimientos_versiones', 'fk_prcv_aprobado_por', 'FOREIGN KEY (`aprobado_por`) REFERENCES `conf__usuarios` (`usuario_id`)');

-- ml__procedimientos_instrucciones
CALL `_m0_add_fk`('ml__procedimientos_instrucciones', 'fk_prci_procedimiento_version_id', 'FOREIGN KEY (`procedimiento_version_id`) REFERENCES `ml__procedimientos_versiones` (`procedimiento_version_id`)');

-- ml__accesos_confidenciales
CALL `_m0_add_fk`('ml__accesos_confidenciales', 'fk_acco_empresa_perfil_id', 'FOREIGN KEY (`empresa_perfil_id`) REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`)');
CALL `_m0_add_fk`('ml__accesos_confidenciales', 'fk_acco_ambito_tipo_id', 'FOREIGN KEY (`ambito_tipo_id`) REFERENCES `ml__ambitos_tipos` (`ambito_tipo_id`)');
CALL `_m0_add_fk`('ml__accesos_confidenciales', 'fk_acco_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');
CALL `_m0_add_fk`('ml__accesos_confidenciales', 'fk_acco_region_grupo_id', 'FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`)');
CALL `_m0_add_fk`('ml__accesos_confidenciales', 'fk_acco_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__accesos_confidenciales', 'fk_acco_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__auditoria_accesos
CALL `_m0_add_fk`('ml__auditoria_accesos', 'fk_auda_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');

-- ml__vencimientos_reglas
CALL `_m0_add_fk`('ml__vencimientos_reglas', 'fk_venr_dato_tipo_id', 'FOREIGN KEY (`dato_tipo_id`) REFERENCES `ml__datos_tipos` (`dato_tipo_id`)');

-- ml__obligaciones
CALL `_m0_add_fk`('ml__obligaciones', 'fk_obl_norma_id', 'FOREIGN KEY (`norma_id`) REFERENCES `ml__normas` (`norma_id`)');
CALL `_m0_add_fk`('ml__obligaciones', 'fk_obl_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__obligaciones', 'fk_obl_periodicidad_id', 'FOREIGN KEY (`periodicidad_id`) REFERENCES `ml__periodicidades` (`periodicidad_id`)');
CALL `_m0_add_fk`('ml__obligaciones', 'fk_obl_vencimiento_regla_id', 'FOREIGN KEY (`vencimiento_regla_id`) REFERENCES `ml__vencimientos_reglas` (`vencimiento_regla_id`)');
CALL `_m0_add_fk`('ml__obligaciones', 'fk_obl_criticidad_id', 'FOREIGN KEY (`criticidad_id`) REFERENCES `ml__criticidades` (`criticidad_id`)');

-- ml__obligaciones_tipos_entidades
CALL `_m0_add_fk`('ml__obligaciones_tipos_entidades', 'fk_oblt_obligacion_id', 'FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');
CALL `_m0_add_fk`('ml__obligaciones_tipos_entidades', 'fk_oblt_entidad_tipo_id', 'FOREIGN KEY (`entidad_tipo_id`) REFERENCES `ml__entidades_tipos` (`entidad_tipo_id`)');

-- ml__obligaciones_jurisdicciones
CALL `_m0_add_fk`('ml__obligaciones_jurisdicciones', 'fk_oblj_obligacion_id', 'FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');
CALL `_m0_add_fk`('ml__obligaciones_jurisdicciones', 'fk_oblj_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');

-- ml__obligaciones_dependencias
CALL `_m0_add_fk`('ml__obligaciones_dependencias', 'fk_obld_obligacion_id', 'FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');
CALL `_m0_add_fk`('ml__obligaciones_dependencias', 'fk_obld_obligacion_predecesora_id', 'FOREIGN KEY (`obligacion_predecesora_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');

-- ml__obligaciones_alertas
CALL `_m0_add_fk`('ml__obligaciones_alertas', 'fk_obla_obligacion_id', 'FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');

-- ml__tareas_plantillas
CALL `_m0_add_fk`('ml__tareas_plantillas', 'fk_tpl_obligacion_id', 'FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');
CALL `_m0_add_fk`('ml__tareas_plantillas', 'fk_tpl_procedimiento_id', 'FOREIGN KEY (`procedimiento_id`) REFERENCES `ml__procedimientos` (`procedimiento_id`)');

-- ml__tareas_plantillas_dependencias
CALL `_m0_add_fk`('ml__tareas_plantillas_dependencias', 'fk_tpld_tarea_plantilla_id', 'FOREIGN KEY (`tarea_plantilla_id`) REFERENCES `ml__tareas_plantillas` (`tarea_plantilla_id`)');
CALL `_m0_add_fk`('ml__tareas_plantillas_dependencias', 'fk_tpld_tarea_plantilla_predecesora_id', 'FOREIGN KEY (`tarea_plantilla_predecesora_id`) REFERENCES `ml__tareas_plantillas` (`tarea_plantilla_id`)');

-- ml__obligaciones_asignadas
CALL `_m0_add_fk`('ml__obligaciones_asignadas', 'fk_oas_obligacion_prevaleciente_id', 'FOREIGN KEY (`obligacion_prevaleciente_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');
CALL `_m0_add_fk`('ml__obligaciones_asignadas', 'fk_oas_entidad_id', 'FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`)');
CALL `_m0_add_fk`('ml__obligaciones_asignadas', 'fk_oas_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__obligaciones_asignadas_origenes
CALL `_m0_add_fk`('ml__obligaciones_asignadas_origenes', 'fk_oaso_obligacion_asignada_id', 'FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`)');
CALL `_m0_add_fk`('ml__obligaciones_asignadas_origenes', 'fk_oaso_obligacion_id', 'FOREIGN KEY (`obligacion_id`) REFERENCES `ml__obligaciones` (`obligacion_id`)');

-- ml__excepciones
CALL `_m0_add_fk`('ml__excepciones', 'fk_exc_obligacion_asignada_id', 'FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`)');
CALL `_m0_add_fk`('ml__excepciones', 'fk_exc_solicitada_por', 'FOREIGN KEY (`solicitada_por`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__excepciones', 'fk_exc_aprobada_por', 'FOREIGN KEY (`aprobada_por`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__excepciones', 'fk_exc_aprobada_legal_por', 'FOREIGN KEY (`aprobada_legal_por`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__excepciones', 'fk_exc_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__ciclos
CALL `_m0_add_fk`('ml__ciclos', 'fk_cic_obligacion_asignada_id', 'FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`)');
CALL `_m0_add_fk`('ml__ciclos', 'fk_cic_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__ciclos', 'fk_cic_entidad_id', 'FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`)');
CALL `_m0_add_fk`('ml__ciclos', 'fk_cic_norma_version_id', 'FOREIGN KEY (`norma_version_id`) REFERENCES `ml__normas_versiones` (`norma_version_id`)');
CALL `_m0_add_fk`('ml__ciclos', 'fk_cic_ciclo_origen_id', 'FOREIGN KEY (`ciclo_origen_id`) REFERENCES `ml__ciclos` (`ciclo_id`)');
CALL `_m0_add_fk`('ml__ciclos', 'fk_cic_ciclo_vinculo_tipo_id', 'FOREIGN KEY (`ciclo_vinculo_tipo_id`) REFERENCES `ml__ciclos_vinculos_tipos` (`ciclo_vinculo_tipo_id`)');

-- ml__ciclos_tareas
CALL `_m0_add_fk`('ml__ciclos_tareas', 'fk_cta_ciclo_id', 'FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas', 'fk_cta_tarea_plantilla_id', 'FOREIGN KEY (`tarea_plantilla_id`) REFERENCES `ml__tareas_plantillas` (`tarea_plantilla_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas', 'fk_cta_procedimiento_version_id', 'FOREIGN KEY (`procedimiento_version_id`) REFERENCES `ml__procedimientos_versiones` (`procedimiento_version_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas', 'fk_cta_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__ciclos_tareas_dependencias
CALL `_m0_add_fk`('ml__ciclos_tareas_dependencias', 'fk_ctad_ciclo_tarea_id', 'FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas_dependencias', 'fk_ctad_ciclo_tarea_predecesora_id', 'FOREIGN KEY (`ciclo_tarea_predecesora_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas_dependencias', 'fk_ctad_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__obligaciones_asignadas_raci
CALL `_m0_add_fk`('ml__obligaciones_asignadas_raci', 'fk_oasr_obligacion_asignada_id', 'FOREIGN KEY (`obligacion_asignada_id`) REFERENCES `ml__obligaciones_asignadas` (`obligacion_asignada_id`)');
CALL `_m0_add_fk`('ml__obligaciones_asignadas_raci', 'fk_oasr_raci_rol_id', 'FOREIGN KEY (`raci_rol_id`) REFERENCES `ml__raci_roles` (`raci_rol_id`)');
CALL `_m0_add_fk`('ml__obligaciones_asignadas_raci', 'fk_oasr_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__obligaciones_asignadas_raci', 'fk_oasr_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__ciclos_raci
CALL `_m0_add_fk`('ml__ciclos_raci', 'fk_cicr_ciclo_id', 'FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`)');
CALL `_m0_add_fk`('ml__ciclos_raci', 'fk_cicr_raci_rol_id', 'FOREIGN KEY (`raci_rol_id`) REFERENCES `ml__raci_roles` (`raci_rol_id`)');
CALL `_m0_add_fk`('ml__ciclos_raci', 'fk_cicr_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__ciclos_raci', 'fk_cicr_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__ciclos_tareas_raci
CALL `_m0_add_fk`('ml__ciclos_tareas_raci', 'fk_ctar_ciclo_tarea_id', 'FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas_raci', 'fk_ctar_raci_rol_id', 'FOREIGN KEY (`raci_rol_id`) REFERENCES `ml__raci_roles` (`raci_rol_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas_raci', 'fk_ctar_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__ciclos_tareas_raci', 'fk_ctar_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__reasignaciones
CALL `_m0_add_fk`('ml__reasignaciones', 'fk_reas_usuario_origen_id', 'FOREIGN KEY (`usuario_origen_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__reasignaciones', 'fk_reas_usuario_destino_id', 'FOREIGN KEY (`usuario_destino_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__reasignaciones', 'fk_reas_aplicada_por', 'FOREIGN KEY (`aplicada_por`) REFERENCES `conf__usuarios` (`usuario_id`)');

-- ml__evidencias
CALL `_m0_add_fk`('ml__evidencias', 'fk_evi_ciclo_tarea_id', 'FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`)');
CALL `_m0_add_fk`('ml__evidencias', 'fk_evi_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__alertas
CALL `_m0_add_fk`('ml__alertas', 'fk_ale_ciclo_id', 'FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`)');
CALL `_m0_add_fk`('ml__alertas', 'fk_ale_ciclo_tarea_id', 'FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`)');
CALL `_m0_add_fk`('ml__alertas', 'fk_ale_evidencia_id', 'FOREIGN KEY (`evidencia_id`) REFERENCES `ml__evidencias` (`evidencia_id`)');
CALL `_m0_add_fk`('ml__alertas', 'fk_ale_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__alertas', 'fk_ale_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__incidencias
CALL `_m0_add_fk`('ml__incidencias', 'fk_inci_ciclo_id', 'FOREIGN KEY (`ciclo_id`) REFERENCES `ml__ciclos` (`ciclo_id`)');
CALL `_m0_add_fk`('ml__incidencias', 'fk_inci_ciclo_seguimiento_id', 'FOREIGN KEY (`ciclo_seguimiento_id`) REFERENCES `ml__ciclos` (`ciclo_id`)');
CALL `_m0_add_fk`('ml__incidencias', 'fk_inci_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');

-- ml__indicadores_cierres
CALL `_m0_add_fk`('ml__indicadores_cierres', 'fk_indc_grupo_id', 'FOREIGN KEY (`grupo_id`) REFERENCES `ml__grupos` (`grupo_id`)');
CALL `_m0_add_fk`('ml__indicadores_cierres', 'fk_indc_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__indicadores_cierres', 'fk_indc_region_grupo_id', 'FOREIGN KEY (`region_grupo_id`) REFERENCES `ml__regiones_grupo` (`region_grupo_id`)');
CALL `_m0_add_fk`('ml__indicadores_cierres', 'fk_indc_territorio_id', 'FOREIGN KEY (`territorio_id`) REFERENCES `ml__territorios` (`territorio_id`)');
CALL `_m0_add_fk`('ml__indicadores_cierres', 'fk_indc_ponderacion_opcion_valor_id', 'FOREIGN KEY (`ponderacion_opcion_valor_id`) REFERENCES `ml__opciones_valores` (`opcion_valor_id`)');

-- ml__cargas
CALL `_m0_add_fk`('ml__cargas', 'fk_car_carga_tipo_id', 'FOREIGN KEY (`carga_tipo_id`) REFERENCES `ml__cargas_tipos` (`carga_tipo_id`)');
CALL `_m0_add_fk`('ml__cargas', 'fk_car_empresa_id', 'FOREIGN KEY (`empresa_id`) REFERENCES `conf__empresas` (`empresa_id`)');
CALL `_m0_add_fk`('ml__cargas', 'fk_car_confirmada_por', 'FOREIGN KEY (`confirmada_por`) REFERENCES `conf__usuarios` (`usuario_id`)');

-- ml__cargas_filas
CALL `_m0_add_fk`('ml__cargas_filas', 'fk_carf_carga_id', 'FOREIGN KEY (`carga_id`) REFERENCES `ml__cargas` (`carga_id`)');

-- ml__cambios_masivos
CALL `_m0_add_fk`('ml__cambios_masivos', 'fk_cmas_norma_version_id', 'FOREIGN KEY (`norma_version_id`) REFERENCES `ml__normas_versiones` (`norma_version_id`)');
CALL `_m0_add_fk`('ml__cambios_masivos', 'fk_cmas_solicitado_por', 'FOREIGN KEY (`solicitado_por`) REFERENCES `conf__usuarios` (`usuario_id`)');
CALL `_m0_add_fk`('ml__cambios_masivos', 'fk_cmas_aprobado_por', 'FOREIGN KEY (`aprobado_por`) REFERENCES `conf__usuarios` (`usuario_id`)');

-- ml__cambios_masivos_detalles
CALL `_m0_add_fk`('ml__cambios_masivos_detalles', 'fk_cmad_cambio_masivo_id', 'FOREIGN KEY (`cambio_masivo_id`) REFERENCES `ml__cambios_masivos` (`cambio_masivo_id`)');
CALL `_m0_add_fk`('ml__cambios_masivos_detalles', 'fk_cmad_entidad_id', 'FOREIGN KEY (`entidad_id`) REFERENCES `ml__entidades` (`entidad_id`)');

-- ml__log_cambios
CALL `_m0_add_fk`('ml__log_cambios', 'fk_logc_usuario_id', 'FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)');

-- =====================================================================
-- VERIFICACIÓN FINAL
--   69 tablas ml__, InnoDB y utf8mb4_unicode_ci; 0 columnas de texto con
--   otra collation; 149 FK (99 a ml__, 50 a conf__), todas sin acciones
--   referenciales; 29 CHECK (26 de vigencia y 3 de ámbito); las 2
--   columnas generadas STORED; ON UPDATE solo en actualizado_en (66);
--   y, por tabla, columnas, índices, FK y CHECK idénticos a lo esperado.
-- =====================================================================
SET @f_tablas = (
    SELECT COUNT(*) FROM information_schema.tables t
     WHERE t.table_schema = DATABASE() AND t.table_name LIKE 'ml\_\_%'
       AND CONVERT(t.table_name USING utf8mb4) COLLATE utf8mb4_bin IN (SELECT e.tabla FROM `_m0_esperado` e));
SET @f_motor = (
    SELECT COUNT(*) FROM information_schema.tables t
     WHERE t.table_schema = DATABASE() AND t.table_name LIKE 'ml\_\_%'
       AND t.engine = 'InnoDB' AND t.table_collation = 'utf8mb4_unicode_ci');
SET @f_cols_coll = (
    SELECT COUNT(*) FROM information_schema.columns c
     WHERE c.table_schema = DATABASE() AND c.table_name LIKE 'ml\_\_%'
       AND c.collation_name IS NOT NULL AND c.collation_name <> 'utf8mb4_unicode_ci');
SET @f_fk = (
    SELECT COUNT(*) FROM information_schema.referential_constraints r
     WHERE r.constraint_schema = DATABASE() AND r.table_name LIKE 'ml\_\_%');
SET @f_fk_ml = (
    SELECT COUNT(*) FROM information_schema.referential_constraints r
     WHERE r.constraint_schema = DATABASE() AND r.table_name LIKE 'ml\_\_%'
       AND r.referenced_table_name LIKE 'ml\_\_%');
SET @f_fk_conf = (
    SELECT COUNT(*) FROM information_schema.referential_constraints r
     WHERE r.constraint_schema = DATABASE() AND r.table_name LIKE 'ml\_\_%'
       AND r.referenced_table_name IN ('conf__empresas', 'conf__usuarios', 'conf__empresas_perfiles'));
SET @f_fk_reglas = (
    SELECT COUNT(*) FROM information_schema.referential_constraints r
     WHERE r.constraint_schema = DATABASE() AND r.table_name LIKE 'ml\_\_%'
       AND (r.delete_rule NOT IN ('RESTRICT', 'NO ACTION') OR r.update_rule NOT IN ('RESTRICT', 'NO ACTION')));
SET @f_ck = (
    SELECT COUNT(*) FROM information_schema.table_constraints tc
     WHERE tc.constraint_schema = DATABASE() AND tc.table_name LIKE 'ml\_\_%'
       AND tc.constraint_type = 'CHECK');
SET @f_gen = (
    SELECT COUNT(*) FROM information_schema.columns c
     WHERE c.table_schema = DATABASE()
       AND ((c.table_name = 'ml__territorios_pertenencias' AND c.column_name = 'principal_abierta_id')
         OR (c.table_name = 'ml__entidades_empresas' AND c.column_name = 'titular_abierta_id'))
       AND IFNULL(CONVERT(c.generation_expression USING utf8mb4), '') <> ''
       AND (UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%STORED%' OR UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%PERSISTENT%'));
SET @f_creado_ou = (
    SELECT COUNT(*) FROM information_schema.columns c
     WHERE c.table_schema = DATABASE() AND c.table_name LIKE 'ml\_\_%'
       AND c.column_name <> 'actualizado_en'
       AND UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%ON UPDATE%');
SET @f_actualizado_ou = (
    SELECT COUNT(*) FROM information_schema.columns c
     WHERE c.table_schema = DATABASE() AND c.table_name LIKE 'ml\_\_%'
       AND c.column_name = 'actualizado_en'
       AND c.data_type = 'timestamp' AND c.is_nullable = 'NO'
       AND UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%ON UPDATE%');

-- Firmas por tabla contra _m0_esperado (columnas, índices, FK y CHECK).
SELECT SUM(CASE WHEN a.tabla IS NULL THEN 1 ELSE 0 END),
       SUM(CASE WHEN a.columnas <> e.columnas THEN 1 ELSE 0 END),
       SUM(CASE WHEN IFNULL(i.indices, '') <> e.indices THEN 1 ELSE 0 END),
       SUM(CASE WHEN IFNULL(f.fks, '') <> e.fks THEN 1 ELSE 0 END),
       SUM(CASE WHEN IFNULL(k.checks, '') <> e.checks THEN 1 ELSE 0 END),
       GROUP_CONCAT(CASE WHEN a.tabla IS NULL OR a.columnas <> e.columnas
                              OR IFNULL(i.indices, '') <> e.indices
                              OR IFNULL(f.fks, '') <> e.fks
                              OR IFNULL(k.checks, '') <> e.checks
                         THEN e.tabla END ORDER BY e.tabla SEPARATOR ' ')
  INTO @f_faltan, @f_cols_mal, @f_idx_mal, @f_fks_mal, @f_cks_mal, @f_detalle
  FROM `_m0_esperado` e
  LEFT JOIN (SELECT CONVERT(c.table_name USING utf8mb4) COLLATE utf8mb4_bin AS tabla,
                    GROUP_CONCAT(
                        CONCAT(CONVERT(c.column_name USING utf8mb4), ' ',
                               CASE WHEN c.data_type = 'tinyint' THEN LOWER(CONVERT(c.column_type USING utf8mb4))
                                    WHEN c.data_type = 'varchar' THEN CONCAT('varchar(', c.character_maximum_length, ')')
                                    WHEN c.data_type = 'decimal' THEN CONCAT('decimal(', c.numeric_precision, ',', c.numeric_scale, ')')
                                    ELSE LOWER(CONVERT(c.data_type USING utf8mb4)) END,
                               CASE WHEN LOWER(CONVERT(c.column_type USING utf8mb4)) LIKE '%unsigned%' THEN ' unsigned' ELSE '' END,
                               CASE WHEN c.is_nullable = 'NO' THEN ' NN' ELSE ' N' END,
                               CASE WHEN c.is_nullable = 'NO' AND c.column_default IS NOT NULL
                                    THEN CONCAT(' D=', UPPER(REPLACE(CONVERT(c.column_default USING utf8mb4), '()', ''))) ELSE '' END,
                               CASE WHEN UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%AUTO_INCREMENT%' THEN ' AI' ELSE '' END,
                               CASE WHEN UPPER(CONVERT(c.extra USING utf8mb4)) LIKE '%ON UPDATE%' THEN ' OU' ELSE '' END,
                               CASE WHEN IFNULL(CONVERT(c.generation_expression USING utf8mb4), '') <> '' THEN ' G' ELSE '' END)
                        ORDER BY c.ordinal_position SEPARATOR ',') COLLATE utf8mb4_bin AS columnas
               FROM information_schema.columns c
              WHERE c.table_schema = DATABASE()
                AND c.table_name LIKE 'ml\_\_%'
              GROUP BY c.table_name) a
         ON a.tabla = e.tabla
  LEFT JOIN (SELECT x.tabla,
                    GROUP_CONCAT(CONCAT(x.idx, CASE WHEN x.nu = 0 THEN '!' ELSE '' END, '(', x.cols, ')')
                                 ORDER BY x.idx SEPARATOR ';') COLLATE utf8mb4_bin AS indices
               FROM (SELECT CONVERT(s.table_name USING utf8mb4) COLLATE utf8mb4_bin AS tabla,
                            CONVERT(s.index_name USING utf8mb4) COLLATE utf8mb4_bin AS idx,
                            MIN(s.non_unique) AS nu,
                            GROUP_CONCAT(CONVERT(s.column_name USING utf8mb4)
                                         ORDER BY s.seq_in_index SEPARATOR ',') COLLATE utf8mb4_bin AS cols
                       FROM information_schema.statistics s
                      WHERE s.table_schema = DATABASE()
                        AND s.table_name LIKE 'ml\_\_%'
                      GROUP BY s.table_name, s.index_name) x
              GROUP BY x.tabla) i
         ON i.tabla = e.tabla
  LEFT JOIN (SELECT y.tabla,
                    GROUP_CONCAT(y.sig ORDER BY y.nombre SEPARATOR ';') COLLATE utf8mb4_bin AS fks
               FROM (SELECT CONVERT(u.table_name USING utf8mb4) COLLATE utf8mb4_bin AS tabla,
                            CONVERT(u.constraint_name USING utf8mb4) COLLATE utf8mb4_bin AS nombre,
                            CONCAT(CONVERT(u.constraint_name USING utf8mb4), '=',
                                   CONVERT(u.column_name USING utf8mb4), '>',
                                   CONVERT(u.referenced_table_name USING utf8mb4), '.',
                                   CONVERT(u.referenced_column_name USING utf8mb4)) COLLATE utf8mb4_bin AS sig
                       FROM information_schema.key_column_usage u
                      WHERE u.table_schema = DATABASE()
                        AND u.table_name LIKE 'ml\_\_%'
                        AND u.referenced_table_name IS NOT NULL) y
              GROUP BY y.tabla) f
         ON f.tabla = e.tabla
  LEFT JOIN (SELECT z.tabla,
                    GROUP_CONCAT(z.nombre ORDER BY z.nombre SEPARATOR ';') COLLATE utf8mb4_bin AS checks
               FROM (SELECT CONVERT(tc.table_name USING utf8mb4) COLLATE utf8mb4_bin AS tabla,
                            CONVERT(tc.constraint_name USING utf8mb4) COLLATE utf8mb4_bin AS nombre
                       FROM information_schema.table_constraints tc
                      WHERE tc.constraint_schema = DATABASE()
                        AND tc.table_name LIKE 'ml\_\_%'
                        AND tc.constraint_type = 'CHECK') z
              GROUP BY z.tabla) k
         ON k.tabla = e.tabla;

SELECT 'M0-02 verificacion final' AS paso,
       @f_tablas         AS tablas_de_69,
       @f_motor          AS innodb_unicode_ci_de_69,
       @f_cols_coll      AS columnas_otra_collation,
       @f_fk             AS fk_de_149,
       @f_fk_ml          AS fk_a_ml_de_99,
       @f_fk_conf        AS fk_a_conf_de_50,
       @f_fk_reglas      AS fk_con_acciones,
       @f_ck             AS checks_de_29,
       @f_gen            AS generadas_stored_de_2,
       @f_creado_ou      AS on_update_fuera_de_actualizado_en,
       @f_actualizado_ou AS actualizado_en_ok_de_66,
       @f_faltan         AS tablas_faltantes,
       @f_cols_mal       AS tablas_columnas_distintas,
       @f_idx_mal        AS tablas_indices_distintos,
       @f_fks_mal        AS tablas_fk_distintas,
       @f_cks_mal        AS tablas_checks_distintos,
       @f_detalle        AS tablas_con_diferencias;

CALL `_m0_assert`(@f_tablas = 69,       'M0-02 final: no estan las 69 tablas ml__');
CALL `_m0_assert`(@f_motor = 69,        'M0-02 final: hay tablas ml__ que no son InnoDB/utf8mb4_unicode_ci (o sobran tablas ml__)');
CALL `_m0_assert`(@f_cols_coll = 0,     'M0-02 final: hay columnas de texto ml__ con collation distinta de utf8mb4_unicode_ci');
CALL `_m0_assert`(@f_fk = 149,          'M0-02 final: la cantidad de FK de las tablas ml__ no es 149');
CALL `_m0_assert`(@f_fk_ml = 99 AND @f_fk_conf = 50, 'M0-02 final: FK por destino distinto de 99 a ml__ y 50 a conf__');
CALL `_m0_assert`(@f_fk_reglas = 0,     'M0-02 final: hay FK ml__ con acciones referenciales (CASCADE/SET NULL)');
CALL `_m0_assert`(@f_ck = 29,           'M0-02 final: la cantidad de CHECK de las tablas ml__ no es 29');
CALL `_m0_assert`(@f_gen = 2,           'M0-02 final: faltan las columnas generadas STORED (principal_abierta_id / titular_abierta_id)');
CALL `_m0_assert`(@f_creado_ou = 0,     'M0-02 final: hay columnas con ON UPDATE fuera de actualizado_en (creado_en?)');
CALL `_m0_assert`(@f_actualizado_ou = 66, 'M0-02 final: actualizado_en sin ON UPDATE o no es TIMESTAMP NOT NULL');
CALL `_m0_assert`(@f_faltan = 0 AND @f_cols_mal = 0 AND @f_idx_mal = 0 AND @f_fks_mal = 0 AND @f_cks_mal = 0,
                  LEFT(CONCAT('M0-02 final: firmas distintas en: ', IFNULL(@f_detalle, '')), 128));

-- ---------------------------------------------------------------------
-- Limpieza: helpers, tabla temporaria y variables de sesión.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;
DROP PROCEDURE IF EXISTS `_m0_add_column`;
DROP PROCEDURE IF EXISTS `_m0_add_fk`;
DROP TEMPORARY TABLE IF EXISTS `_m0_esperado`;

SET @m0_02_ok = 0;
SET SESSION foreign_key_checks = @m0_old_fk;
SET SESSION sql_mode = @m0_old_sql_mode;
SET SESSION group_concat_max_len = @m0_old_gcml;
SET SESSION lock_wait_timeout = @m0_old_lwt;

SELECT 'M0-02 terminado: 69 tablas ml__ con sus 149 FK y 29 CHECK. Seguir con 03' AS resultado;
