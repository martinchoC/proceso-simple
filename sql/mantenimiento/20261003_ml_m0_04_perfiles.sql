-- =====================================================================
-- Matriz legal - M0 (instalación del módulo 3), script 04: PERFILES BASE
-- 2026-10-03
-- =====================================================================
-- Qué hace (docs/matriz-legal/08-diseno-m0.md, secciones 4 y 6;
-- docs/matriz-legal/03-plan-de-modulos.md, Decisión 8):
--   1) En una transacción:
--      A) Da de alta los 8 perfiles base del módulo 3 en `conf__perfiles`
--         (IDs 301 a 308), con modulo_id = 3 y estado 1 (Activo).
--      B) Copia los perfiles a `conf__empresas_perfiles` respetando la decisión 8:
--         - Empresa 2 (Casa Lucho - titular): recibe los 8 perfiles (301 a 308).
--         - Empresa 3 (CEC Patagones): recibe solo los 4 de empresa/operación
--           (304, 305, 306 y 308), asignando IDs 309 a 312.
--      C) Actualiza layout_nombre en `conf__modulos` para modulo_id = 3 (E9).
--   2) Verificación final con asserts y limpieza.
--
-- ---------------------------------------------------------------------
-- CÓMO SE CORRE
-- ---------------------------------------------------------------------
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261003_ml_m0_04_perfiles.sql
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

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Controles previos de bloque reservado
-- ---------------------------------------------------------------------
SET @colision_perfiles = (
    SELECT COUNT(*) FROM conf__perfiles
     WHERE perfil_id BETWEEN 301 AND 320
       AND modulo_id <> 3
);
CALL `_m0_assert`(@colision_perfiles = 0, 'M0-04: bloque conf__perfiles 301-320 ocupado por otro módulo');

SET @colision_empresas_perfiles = (
    SELECT COUNT(*) FROM conf__empresas_perfiles
     WHERE empresa_perfil_id BETWEEN 301 AND 340
       AND modulo_id <> 3
);
CALL `_m0_assert`(@colision_empresas_perfiles = 0, 'M0-04: bloque conf__empresas_perfiles 301-340 ocupado por otro módulo');

-- ---------------------------------------------------------------------
-- 2) Alta de los 8 perfiles base en `conf__perfiles`
-- ---------------------------------------------------------------------
-- perfil_id: 301 a 308
-- modulo_id: 3 (Matriz Legal)
-- tabla_estado_registro_id: 1 (Activo)
INSERT INTO `conf__perfiles`
    (`perfil_id`, `modulo_id`, `perfil_nombre`, `tabla_estado_registro_id`)
VALUES
    (301, 3, 'Administrador de matriz', 1),
    (302, 3, 'Gestor normativo',        1),
    (303, 3, 'Área legal',              1),
    (304, 3, 'Responsable de empresa',  1),
    (305, 3, 'Responsable de tareas',   1),
    (306, 3, 'Revisor y aprobador',     1),
    (307, 3, 'Auditor',                 1),
    (308, 3, 'Consulta',                1)
ON DUPLICATE KEY UPDATE
    `modulo_id`                = VALUES(`modulo_id`),
    `perfil_nombre`            = VALUES(`perfil_nombre`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- ---------------------------------------------------------------------
-- 3) Copia de perfiles a `conf__empresas_perfiles`
-- ---------------------------------------------------------------------
-- Empresa 2 (Casa Lucho): recibe los 8 perfiles base
INSERT INTO `conf__empresas_perfiles`
    (`empresa_perfil_id`, `empresa_id`, `modulo_id`, `perfil_id_base`, `empresa_perfil_nombre`, `tabla_estado_registro_id`)
VALUES
    (301, 2, 3, 301, 'Administrador de matriz', 1),
    (302, 2, 3, 302, 'Gestor normativo',        1),
    (303, 2, 3, 303, 'Área legal',              1),
    (304, 2, 3, 304, 'Responsable de empresa',  1),
    (305, 2, 3, 305, 'Responsable de tareas',   1),
    (306, 2, 3, 306, 'Revisor y aprobador',     1),
    (307, 2, 3, 307, 'Auditor',                 1),
    (308, 2, 3, 308, 'Consulta',                1)
ON DUPLICATE KEY UPDATE
    `empresa_id`               = VALUES(`empresa_id`),
    `modulo_id`                = VALUES(`modulo_id`),
    `perfil_id_base`           = VALUES(`perfil_id_base`),
    `empresa_perfil_nombre`    = VALUES(`empresa_perfil_nombre`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- Empresa 3 (CEC Patagones): recibe los 4 perfiles no exclusivos de matriz
INSERT INTO `conf__empresas_perfiles`
    (`empresa_perfil_id`, `empresa_id`, `modulo_id`, `perfil_id_base`, `empresa_perfil_nombre`, `tabla_estado_registro_id`)
VALUES
    (309, 3, 3, 304, 'Responsable de empresa',  1),
    (310, 3, 3, 305, 'Responsable de tareas',   1),
    (311, 3, 3, 306, 'Revisor y aprobador',     1),
    (312, 3, 3, 308, 'Consulta',                1)
ON DUPLICATE KEY UPDATE
    `empresa_id`               = VALUES(`empresa_id`),
    `modulo_id`                = VALUES(`modulo_id`),
    `perfil_id_base`           = VALUES(`perfil_id_base`),
    `empresa_perfil_nombre`    = VALUES(`empresa_perfil_nombre`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- ---------------------------------------------------------------------
-- 4) Paso E9: layout_nombre = 'default' en conf__modulos (modulo_id = 3)
-- ---------------------------------------------------------------------
UPDATE `conf__modulos`
   SET `layout_nombre` = 'default'
 WHERE `modulo_id` = 3;

-- ---------------------------------------------------------------------
-- 5) Verificación dentro de la transacción
-- ---------------------------------------------------------------------
SET @cnt_perfiles = (
    SELECT COUNT(*) FROM conf__perfiles
     WHERE modulo_id = 3 AND perfil_id BETWEEN 301 AND 308
);
CALL `_m0_assert`(@cnt_perfiles = 8, 'M0-04: no se crearon los 8 perfiles base del módulo 3');

SET @cnt_emp_perfiles_emp2 = (
    SELECT COUNT(*) FROM conf__empresas_perfiles
     WHERE modulo_id = 3 AND empresa_id = 2 AND empresa_perfil_id BETWEEN 301 AND 308
);
CALL `_m0_assert`(@cnt_emp_perfiles_emp2 = 8, 'M0-04: la empresa 2 no tiene los 8 perfiles asignados');

SET @cnt_emp_perfiles_emp3 = (
    SELECT COUNT(*) FROM conf__empresas_perfiles
     WHERE modulo_id = 3 AND empresa_id = 3 AND empresa_perfil_id BETWEEN 309 AND 312
);
CALL `_m0_assert`(@cnt_emp_perfiles_emp3 = 4, 'M0-04: la empresa 3 no tiene los 4 perfiles asignados');

COMMIT;

-- ---------------------------------------------------------------------
-- Limpieza
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `_m0_assert`;

SET SESSION sql_mode = @m0_old_sql_mode;

SELECT 'M0-04 terminado: 8 perfiles base creados y 12 copias en conf__empresas_perfiles (8 en empresa 2, 4 en empresa 3). Seguir con 05' AS resultado;
