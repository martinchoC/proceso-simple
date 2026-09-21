-- =====================================================================
-- Recalcula compatibilidad_texto y compatibilidad_busqueda para TODOS
-- los productos, usando el procedure existente
-- sp_recalcular_compatibilidad_producto (ver casalucho.sql ~línea 67242),
-- que es la misma rutina que ya usa productos_model.php
-- (recalcularCompatibilidadProducto(), función alrededor de la línea 1617)
-- cada vez que se agrega/edita/borra una compatibilidad de a uno.
--
-- Por qué reusar el procedure en vez de recalcular acá con SQL plano:
-- así el valor materializado queda IDÉNTICO al que arma la app en el
-- flujo normal (mismo orden, mismo formato "Marca Modelo Submodelo
-- (desde-hasta)", misma expansión de años tope año actual) — evita que
-- este script introduzca una segunda fuente de verdad que se desvíe de
-- nuevo con el tiempo.
--
-- Cubre DOS casos:
--   1) Productos con al menos una fila de compatibilidad ACTIVA
--      (tabla_estado_registro_id = 1): se recalculan con el procedure.
--   2) Productos que NO tienen ninguna fila activa pero les quedó
--      compatibilidad_texto/compatibilidad_busqueda con un valor viejo
--      (por ejemplo, se borraron/inhabilitaron todas sus filas de
--      compatibilidad en algún momento y esas columnas no se limpiaron):
--      se ponen en NULL.
--
-- Uso: correr este archivo completo (ej. `mysql gestion_multipyme < este_archivo.sql`
-- o pegarlo entero en phpMyAdmin/HeidiSQL). Es idempotente, se puede
-- correr las veces que haga falta.
-- =====================================================================

USE `gestion_multipyme`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_recalcular_compatibilidad_todos`//
CREATE PROCEDURE `sp_recalcular_compatibilidad_todos`()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_producto_id BIGINT UNSIGNED;

    -- Caso 1: todo producto con al menos una fila de compatibilidad activa.
    DECLARE cur CURSOR FOR
        SELECT DISTINCT producto_id
        FROM gestion__productos_compatibilidad
        WHERE tabla_estado_registro_id = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    loop_productos: LOOP
        FETCH cur INTO v_producto_id;
        IF v_done THEN LEAVE loop_productos; END IF;
        CALL sp_recalcular_compatibilidad_producto(v_producto_id);
    END LOOP;
    CLOSE cur;

    -- Caso 2: productos SIN ninguna fila activa pero con las columnas
    -- materializadas todavía cargadas (quedaron huérfanas/desactualizadas).
    UPDATE gestion__productos p
    SET p.compatibilidad_texto = NULL,
        p.compatibilidad_busqueda = NULL
    WHERE (p.compatibilidad_texto IS NOT NULL OR p.compatibilidad_busqueda IS NOT NULL)
      AND NOT EXISTS (
          SELECT 1
          FROM gestion__productos_compatibilidad pc
          WHERE pc.producto_id = p.producto_id
            AND pc.tabla_estado_registro_id = 1
      );
END//

DELIMITER ;

CALL sp_recalcular_compatibilidad_todos();

DROP PROCEDURE IF EXISTS `sp_recalcular_compatibilidad_todos`;
