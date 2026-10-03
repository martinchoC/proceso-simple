-- =====================================================================
-- Matriz legal - M0 (instalación del módulo 3), script 99: ROLLBACK / REVERSIÓN
-- 2026-10-03
-- =====================================================================
-- Qué hace (docs/matriz-legal/08-diseno-m0.md, sección 6; "Rollback"):
--   Script manual de reversión completa de M0 en caso de contingencia:
--   1) Control previo: verifica que ningún módulo ajeno (gestión o conf) haya
--      empezado a usar los estados 16 a 27. Si existen usos externos, el script
--      aborta para no romper referencias de otros módulos.
--   2) DML en transacción:
--      - Elimina las filas de `conf__empresas_perfiles` del módulo 3 (301 a 340).
--      - Elimina las filas de `conf__perfiles` del módulo 3 (301 a 320).
--      - Elimina las filas de `conf__tablas_estados_registros` (2001 a 2200).
--      - Elimina las filas de `conf__tablas` del módulo 3 (201 a 269).
--      - Elimina los estados genéricos de `conf__estados_registros` (16 a 27).
--      - Restablece `layout_nombre = ''` en `conf__modulos` para modulo_id = 3.
--   3) DDL:
--      - Elimina la columna `es_final` de `conf__tablas_estados_registros`
--        (siempre que ningún registro fuera del módulo 3 la use con valor 1).
--      - DROP TABLE IF EXISTS de las 69 tablas `ml__` en orden inverso de
--        dependencias (de hijas a padres).
--
-- ---------------------------------------------------------------------
-- CÓMO SE CORRE (SOLO A MANO)
-- ---------------------------------------------------------------------
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261003_ml_m0_99_rollback.sql
-- =====================================================================

SET NAMES utf8mb4;
SET @m0_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------------------
-- HELPER: Assert
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;
DELIMITER $$
CREATE PROCEDURE `_m0_assert`(IN p_cond BOOLEAN, IN p_msg VARCHAR(128))
BEGIN
  IF NOT (p_cond IS TRUE) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- HELPER: Eliminar columna si existe (idempotente)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_drop_column_if_exists`;
DELIMITER $$
CREATE PROCEDURE `_m0_drop_column_if_exists`(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64)
)
BEGIN
    DECLARE v_exists INT;
    DECLARE v_in_use INT DEFAULT 0;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = p_table_name
       AND column_name = p_column_name;

    IF v_exists > 0 THEN
        IF p_table_name = 'conf__tablas_estados_registros' AND p_column_name = 'es_final' THEN
            SELECT COUNT(*) INTO v_in_use
              FROM conf__tablas_estados_registros
             WHERE es_final = 1;
        END IF;

        IF v_in_use = 0 THEN
            SET @sql_drop_col = CONCAT('ALTER TABLE `', p_table_name, '` DROP COLUMN `', p_column_name, '`');
            PREPARE stmt_drop_col FROM @sql_drop_col;
            EXECUTE stmt_drop_col;
            DEALLOCATE PREPARE stmt_drop_col;
        END IF;
    END IF;
END$$
DELIMITER ;

-- =====================================================================
-- 1) CONTROLES PREVIOS
-- =====================================================================
-- Verificar que ningún módulo fuera del 3 esté usando los estados 16 a 27
SET @usos_estados_externos = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros ter
      JOIN conf__tablas t ON t.tabla_id = ter.tabla_id
     WHERE ter.estado_registro_id BETWEEN 16 AND 27
       AND t.modulo_id <> 3
);
CALL `_m0_assert`(@usos_estados_externos = 0, 'M0-99 abortado: hay tablas fuera del módulo 3 usando estados del rango 16-27');

SET @usos_funciones_externas = (
    SELECT COUNT(*) FROM conf__paginas_funciones pf
      JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
     WHERE p.modulo_id <> 3
       AND (pf.tabla_estado_registro_origen_id BETWEEN 16 AND 27
         OR pf.tabla_estado_registro_destino_id BETWEEN 16 AND 27)
);
CALL `_m0_assert`(@usos_funciones_externas = 0, 'M0-99 abortado: hay páginas_funciones fuera del módulo 3 usando transiciones con estados 16-27');

-- =====================================================================
-- 2) DML: LIMPIEZA DE TABLAS CONF__ (EN TRANSACCIÓN)
-- =====================================================================
START TRANSACTION;

-- Eliminar perfiles asignados a empresas para módulo 3
DELETE FROM `conf__empresas_perfiles`
 WHERE `modulo_id` = 3
   AND `empresa_perfil_id` BETWEEN 301 AND 340;

-- Eliminar perfiles base del módulo 3
DELETE FROM `conf__perfiles`
 WHERE `modulo_id` = 3
   AND `perfil_id` BETWEEN 301 AND 320;

-- Eliminar estados configurados para las tablas ml__
DELETE FROM `conf__tablas_estados_registros`
 WHERE `tabla_id` BETWEEN 201 AND 269
    OR `tabla_estado_registro_id` BETWEEN 2001 AND 2200;

-- Eliminar las tablas ml__ de conf__tablas
DELETE FROM `conf__tablas`
 WHERE `modulo_id` = 3
   AND `tabla_id` BETWEEN 201 AND 269;

-- Eliminar los 12 estados genéricos si ningún registro los usa
DELETE FROM `conf__estados_registros`
 WHERE `estado_registro_id` BETWEEN 16 AND 27;

-- Restablecer layout_nombre en conf__modulos
UPDATE `conf__modulos`
   SET `layout_nombre` = ''
 WHERE `modulo_id` = 3;

COMMIT;

-- =====================================================================
-- 3) DDL: ELIMINAR COLUMNA ES_FINAL (SI NO LA USA OTRO MÓDULO)
-- =====================================================================
CALL `_m0_drop_column_if_exists`('conf__tablas_estados_registros', 'es_final');

-- =====================================================================
-- 4) DDL: DROP DE LAS 69 TABLAS ml__ EN ORDEN INVERSO
-- =====================================================================
-- Desactivar temporalmente foreign_key_checks para un drop limpio y seguro
SET @old_fk_checks = @@SESSION.foreign_key_checks;
SET SESSION foreign_key_checks = 0;

DROP TABLE IF EXISTS `ml__log_cambios`;
DROP TABLE IF EXISTS `ml__cambios_masivos_detalles`;
DROP TABLE IF EXISTS `ml__cambios_masivos`;
DROP TABLE IF EXISTS `ml__cargas_filas`;
DROP TABLE IF EXISTS `ml__cargas`;
DROP TABLE IF EXISTS `ml__cargas_tipos`;
DROP TABLE IF EXISTS `ml__indicadores_cierres`;
DROP TABLE IF EXISTS `ml__incidencias`;
DROP TABLE IF EXISTS `ml__alertas`;
DROP TABLE IF EXISTS `ml__evidencias`;
DROP TABLE IF EXISTS `ml__reasignaciones`;
DROP TABLE IF EXISTS `ml__ciclos_tareas_raci`;
DROP TABLE IF EXISTS `ml__ciclos_raci`;
DROP TABLE IF EXISTS `ml__obligaciones_asignadas_raci`;
DROP TABLE IF EXISTS `ml__ciclos_tareas_dependencias`;
DROP TABLE IF EXISTS `ml__ciclos_tareas`;
DROP TABLE IF EXISTS `ml__ciclos`;
DROP TABLE IF EXISTS `ml__ciclos_vinculos_tipos`;
DROP TABLE IF EXISTS `ml__recalculos_pendientes`;
DROP TABLE IF EXISTS `ml__excepciones`;
DROP TABLE IF EXISTS `ml__obligaciones_asignadas_origenes`;
DROP TABLE IF EXISTS `ml__obligaciones_asignadas`;
DROP TABLE IF EXISTS `ml__tareas_plantillas_dependencias`;
DROP TABLE IF EXISTS `ml__tareas_plantillas`;
DROP TABLE IF EXISTS `ml__obligaciones_alertas`;
DROP TABLE IF EXISTS `ml__obligaciones_dependencias`;
DROP TABLE IF EXISTS `ml__obligaciones_jurisdicciones`;
DROP TABLE IF EXISTS `ml__obligaciones_tipos_entidades`;
DROP TABLE IF EXISTS `ml__obligaciones`;
DROP TABLE IF EXISTS `ml__vencimientos_reglas`;
DROP TABLE IF EXISTS `ml__periodicidades`;
DROP TABLE IF EXISTS `ml__criticidades`;
DROP TABLE IF EXISTS `ml__auditoria_accesos`;
DROP TABLE IF EXISTS `ml__accesos_confidenciales`;
DROP TABLE IF EXISTS `ml__procedimientos_instrucciones`;
DROP TABLE IF EXISTS `ml__procedimientos_versiones`;
DROP TABLE IF EXISTS `ml__procedimientos`;
DROP TABLE IF EXISTS `ml__normas_conflictos`;
DROP TABLE IF EXISTS `ml__normas_relaciones`;
DROP TABLE IF EXISTS `ml__normas_relaciones_tipos`;
DROP TABLE IF EXISTS `ml__normas_versiones`;
DROP TABLE IF EXISTS `ml__normas`;
DROP TABLE IF EXISTS `ml__normas_jerarquias`;
DROP TABLE IF EXISTS `ml__normas_tipos`;
DROP TABLE IF EXISTS `ml__organismos`;
DROP TABLE IF EXISTS `ml__raci_roles`;
DROP TABLE IF EXISTS `ml__configuracion_grupo`;
DROP TABLE IF EXISTS `ml__opciones_valores`;
DROP TABLE IF EXISTS `ml__opciones`;
DROP TABLE IF EXISTS `ml__delegaciones`;
DROP TABLE IF EXISTS `ml__usuarios_ambitos`;
DROP TABLE IF EXISTS `ml__ambitos_tipos`;
DROP TABLE IF EXISTS `ml__entidades_datos`;
DROP TABLE IF EXISTS `ml__datos_tipos`;
DROP TABLE IF EXISTS `ml__entidades_ubicaciones`;
DROP TABLE IF EXISTS `ml__entidades_empresas`;
DROP TABLE IF EXISTS `ml__empresas_grupo`;
DROP TABLE IF EXISTS `ml__entidades`;
DROP TABLE IF EXISTS `ml__entidades_tipos`;
DROP TABLE IF EXISTS `ml__feriados`;
DROP TABLE IF EXISTS `ml__territorios_pertenencias`;
DROP TABLE IF EXISTS `ml__territorios`;
DROP TABLE IF EXISTS `ml__territorios_tipos`;
DROP TABLE IF EXISTS `ml__empresas_eventos`;
DROP TABLE IF EXISTS `ml__empresas_eventos_tipos`;
DROP TABLE IF EXISTS `ml__empresas_nombres`;
DROP TABLE IF EXISTS `ml__empresas_regiones`;
DROP TABLE IF EXISTS `ml__regiones_grupo`;
DROP TABLE IF EXISTS `ml__grupos`;

SET SESSION foreign_key_checks = @old_fk_checks;

-- ---------------------------------------------------------------------
-- Limpieza de helpers y sesión
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;
DROP PROCEDURE IF EXISTS `_m0_drop_column_if_exists`;

SET SESSION sql_mode = @m0_old_sql_mode;

SELECT 'M0-99 Rollback completado: 69 tablas ml__ eliminadas y configuración en conf__ revertida.' AS resultado;
