-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================
-- Migracion de PRODUCCION Casa Lucho al formato actual (servidor nuevo)
-- Script 04: LIMPIEZA DE COLUMNAS VIEJAS
-- 2026-10-31
-- =====================================================================
-- Requiere el 03 (que carga gestion__entidades_roles desde estas columnas).
-- Equivale a 20261019_entidades_roles_02_drop_columnas.sql: borra
-- gestion__entidades.es_cliente / es_proveedor / cont_cuenta_id_cliente /
-- cont_cuenta_id_proveedor y sus indices es_cliente_id / es_proveedor_idx. El
-- codigo actual no las usa (los roles salen solo de gestion__entidades_roles).
--
-- Se corta si alguna entidad marcada como cliente / proveedor no tiene su rol
-- (el 03 no se corrio o fallo). Reanudable.
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261031_migracion_casalucho_04_limpieza.sql
-- =====================================================================

SET NAMES utf8mb4;
-- Sin USE: la base se indica en la linea de comando.

DROP PROCEDURE IF EXISTS `_mig_drop_roles_viejos`;

DELIMITER $$

CREATE PROCEDURE `_mig_drop_roles_viejos`()
BEGIN
    DECLARE v_faltan INT DEFAULT 0;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND COLUMN_NAME = 'es_cliente') THEN
        SELECT COUNT(*) INTO v_faltan
        FROM gestion__entidades e
        WHERE (e.es_cliente = 1 AND NOT EXISTS (SELECT 1 FROM gestion__entidades_roles er
                                                JOIN gestion__roles_entidades r ON r.rol_entidad_id = er.rol_entidad_id
                                                WHERE er.entidad_id = e.entidad_id AND r.rol_entidad_codigo = 'CLI'))
           OR (e.es_proveedor = 1 AND NOT EXISTS (SELECT 1 FROM gestion__entidades_roles er
                                                  JOIN gestion__roles_entidades r ON r.rol_entidad_id = er.rol_entidad_id
                                                  WHERE er.entidad_id = e.entidad_id AND r.rol_entidad_codigo = 'PRO'));
        IF v_faltan > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay entidades cliente / proveedor sin su fila en gestion__entidades_roles: correr el script 03 antes';
        END IF;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND INDEX_NAME = 'es_cliente_id') THEN
        ALTER TABLE gestion__entidades DROP INDEX es_cliente_id;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND INDEX_NAME = 'es_proveedor_idx') THEN
        ALTER TABLE gestion__entidades DROP INDEX es_proveedor_idx;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND COLUMN_NAME = 'es_cliente') THEN
        ALTER TABLE gestion__entidades DROP COLUMN es_cliente;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND COLUMN_NAME = 'es_proveedor') THEN
        ALTER TABLE gestion__entidades DROP COLUMN es_proveedor;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND COLUMN_NAME = 'cont_cuenta_id_cliente') THEN
        ALTER TABLE gestion__entidades DROP COLUMN cont_cuenta_id_cliente;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__entidades' AND COLUMN_NAME = 'cont_cuenta_id_proveedor') THEN
        ALTER TABLE gestion__entidades DROP COLUMN cont_cuenta_id_proveedor;
    END IF;
END$$

DELIMITER ;

CALL `_mig_drop_roles_viejos`();
DROP PROCEDURE IF EXISTS `_mig_drop_roles_viejos`;
