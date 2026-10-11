-- =====================================================================
-- Tesorería - script 04: ESTADOS GENÉRICOS + CORRECCIÓN DE ORIGEN
-- 2026-10-07
--
-- Decisión (2026-10-07, reemplaza §1/§381 de 08_proc_tesoreria_v3_11.docx):
--   tabla_estado_registro_id de las tablas de tesorería guarda el id GENÉRICO
--   de conf__estados_registros, como ventas/compras y la decisión 2 de
--   docs/matriz-legal/03-plan-de-modulos.md. conf__tablas_estados_registros
--   queda como catálogo de etiquetas por tabla (tabla_id, estado_registro_id).
--   Hasta acá pagos, cheques, impuestos, rendiciones, movimientos e
--   imputaciones guardaban la PK de conf__tablas_estados_registros (2121-2180).
--
-- Decisión (2026-10-07): estado derivado del saldo en gestion__comprobantes
--   con dos estados genéricos nuevos: 39 Cancelado parcial (CANC_PARCIAL) y
--   40 Cancelado total (CANC_TOTAL). Los asigna recalcularEstadoDestino()
--   (tesoreria_core_model.php) solo sobre comprobantes en Confirmado /
--   Cancelado parcial / Cancelado total; syncComprobante() los conserva.
--
-- Qué hace:
--   0) Controles: base seleccionada, ids 28-38 y 39-40 coherentes con el
--      código (que usa ids fijos), filas 2121-2180 presentes si hay datos.
--   1) Estados 39 y 40 en conf__estados_registros (ids explícitos).
--   2) gestion__comprobantes.tabla_origen_id de I_LIQ, I_PAG, RG_REND,
--      RG_ANT y RG_REEM: el código usaba tabla_id corridos (277..281); se
--      corrige cruzando por comprobante_id con cada tabla origen.
--   3) gestion__tesoreria_movimientos.tabla_origen_id: misma corrección.
--   4) Estados derivados ya escritos en gestion__comprobantes:
--      2122 -> 39 y 2123 -> 40 (recalcularEstadoDestino los usaba como
--      "cancelado parcial/total"), salvo comprobantes de cobranzas (275),
--      donde 2122/2123 eran Confirmado/Anulado de esa tabla.
--   5) PK 2121-2180 -> id genérico en todas las tablas de tesorería y en
--      gestion__comprobantes (JOIN a conf__tablas_estados_registros).
--   6) gestion__cont_asientos anulados con 2127 -> 2 (Inactivo), como
--      anulan pagos y cobranzas y como excluye el cierre de ejercicio.
--   7) Estado derivado de I_LIQ y RG_REND cuyo comprobante quedó Cancelado
--      total: Pagada (35) / Reembolsada (36). recalcularEstadoDestino() no
--      los reconocía por el tabla_origen_id corrido del paso 2.
--   8) Verificación: no queda ningún valor 2121-2180.
--
-- NO recalcula estados por saldo: importe_pendiente está en 0 en casi todos
-- los comprobantes anteriores a tesorería (nunca se inicializó) y un
-- recálculo global los marcaría como Cancelado total.
--
-- ORDEN: después de 20261003_tesoreria_01/02/03. Idempotente: se puede
-- volver a correr. Requiere el código de la misma fecha (constantes con id
-- genérico); con el código anterior las pantallas de tesorería dejan de
-- encontrar sus estados.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción). Sin parámetros.
-- BACKUP ANTES (al menos las tablas tocadas).
-- =====================================================================

SET @t04_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP PROCEDURE IF EXISTS `_t04_assert`;
DELIMITER $$
CREATE PROCEDURE `_t04_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Controles previos
-- ---------------------------------------------------------------------
CALL `_t04_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

-- El código usa estos ids fijos: tienen que existir exactamente así.
CALL `_t04_assert`((
    SELECT COUNT(*) FROM conf__estados_registros er
    JOIN (
                  SELECT 28 AS id, 'PRESENTADO' AS cod
        UNION ALL SELECT 29, 'EN_CARTERA'
        UNION ALL SELECT 30, 'APROBADO'
        UNION ALL SELECT 31, 'EMITIDO'
        UNION ALL SELECT 32, 'DEPOSITADO'
        UNION ALL SELECT 33, 'ENDOSADO'
        UNION ALL SELECT 34, 'DEBITADO'
        UNION ALL SELECT 35, 'PAGADO'
        UNION ALL SELECT 36, 'REEMBOLSADO'
        UNION ALL SELECT 37, 'DEVUELTO'
        UNION ALL SELECT 38, 'ANULADO'
        UNION ALL SELECT 25, 'RECHAZADO'
    ) v ON v.id = er.estado_registro_id AND v.cod = er.codigo_estandar COLLATE utf8mb4_unicode_ci
) = 12, 'conf__estados_registros: los ids 25 y 28-38 no tienen los códigos esperados (correr antes ml_m0_03 y tesoreria_01, en ese orden)');

-- 39 y 40 libres o ya con el código correcto.
CALL `_t04_assert`((
    SELECT COUNT(*) FROM conf__estados_registros
    WHERE (estado_registro_id = 39 AND codigo_estandar <> 'CANC_PARCIAL')
       OR (estado_registro_id = 40 AND codigo_estandar <> 'CANC_TOTAL')
       OR (codigo_estandar = 'CANC_PARCIAL' AND estado_registro_id <> 39)
       OR (codigo_estandar = 'CANC_TOTAL' AND estado_registro_id <> 40)
) = 0, 'conf__estados_registros: 39/40 ocupados por otro estado');

-- El mapeo PK -> genérico necesita las filas de tesorería del script 01.
CALL `_t04_assert`((
    SELECT COUNT(*) FROM conf__tablas_estados_registros
    WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180 AND tabla_id BETWEEN 275 AND 288
) >= 59, 'conf__tablas_estados_registros: faltan las filas 2121-2180 de tesorería (correr tesoreria_01)');

-- ---------------------------------------------------------------------
-- 1) Estados genéricos nuevos (ids explícitos)
-- ---------------------------------------------------------------------
INSERT INTO conf__estados_registros (estado_registro_id, estado_registro, codigo_estandar, valor_estandar, color_id, orden_estandar)
SELECT v.id, v.nombre, v.cod, v.valor, 1, v.valor
FROM (
              SELECT 39 AS id, 'Cancelado parcial' AS nombre, 'CANC_PARCIAL' AS cod, 82 AS valor
    UNION ALL SELECT 40, 'Cancelado total', 'CANC_TOTAL', 84
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__estados_registros er WHERE er.estado_registro_id = v.id);

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 2) gestion__comprobantes.tabla_origen_id (orden elegido para no chocar con
--    el UNIQUE (tabla_origen_id, registro_origen_id) durante el cambio)
-- ---------------------------------------------------------------------
UPDATE gestion__comprobantes c
JOIN gestion__obligaciones_impositivas o ON o.comprobante_id = c.comprobante_id
SET c.tabla_origen_id = 286 WHERE c.tabla_origen_id <> 286;

UPDATE gestion__comprobantes c
JOIN gestion__rendiciones r ON r.comprobante_id = c.comprobante_id
SET c.tabla_origen_id = 287 WHERE c.tabla_origen_id <> 287;

UPDATE gestion__comprobantes c
JOIN gestion__rendiciones_reembolsos r ON r.comprobante_id = c.comprobante_id
SET c.tabla_origen_id = 282 WHERE c.tabla_origen_id <> 282;

UPDATE gestion__comprobantes c
JOIN gestion__rendiciones_anticipos a ON a.comprobante_id = c.comprobante_id
SET c.tabla_origen_id = 281 WHERE c.tabla_origen_id <> 281;

UPDATE gestion__comprobantes c
JOIN gestion__pagos_impuestos p ON p.comprobante_id = c.comprobante_id
SET c.tabla_origen_id = 277 WHERE c.tabla_origen_id <> 277;

-- ---------------------------------------------------------------------
-- 3) gestion__tesoreria_movimientos.tabla_origen_id (mismo corrimiento)
-- ---------------------------------------------------------------------
UPDATE gestion__tesoreria_movimientos tm
JOIN gestion__rendiciones_reembolsos r ON r.comprobante_id = tm.comprobante_id
SET tm.tabla_origen_id = 282 WHERE tm.tabla_origen_id = 281;

UPDATE gestion__tesoreria_movimientos tm
JOIN gestion__rendiciones_anticipos a ON a.comprobante_id = tm.comprobante_id
SET tm.tabla_origen_id = 281 WHERE tm.tabla_origen_id = 280;

UPDATE gestion__tesoreria_movimientos tm
JOIN gestion__pagos_impuestos p ON p.comprobante_id = tm.comprobante_id
SET tm.tabla_origen_id = 277 WHERE tm.tabla_origen_id = 278;

-- ---------------------------------------------------------------------
-- 4) Estados derivados ya escritos por recalcularEstadoDestino()
-- ---------------------------------------------------------------------
UPDATE gestion__comprobantes SET tabla_estado_registro_id = 39
WHERE tabla_estado_registro_id = 2122 AND tabla_origen_id <> 275;

UPDATE gestion__comprobantes SET tabla_estado_registro_id = 40
WHERE tabla_estado_registro_id = 2123 AND tabla_origen_id <> 275;

-- ---------------------------------------------------------------------
-- 5) PK de conf__tablas_estados_registros -> id genérico
-- ---------------------------------------------------------------------
UPDATE gestion__comprobantes x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__cobranzas x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__pagos x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__pagos_impuestos x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__transferencias x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__movimientos_caja x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__cheques_operaciones x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__rendiciones_anticipos x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__rendiciones_reembolsos x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__comprobantes_imputaciones x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__tesoreria_movimientos x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__rendiciones_items x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__obligaciones_impositivas x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__rendiciones x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;
UPDATE gestion__cheques x JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = x.tabla_estado_registro_id
SET x.tabla_estado_registro_id = ter.estado_registro_id WHERE x.tabla_estado_registro_id BETWEEN 2121 AND 2180;

-- ---------------------------------------------------------------------
-- 6) Asientos anulados con la PK de pagos
-- ---------------------------------------------------------------------
UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2 WHERE tabla_estado_registro_id = 2127;

-- ---------------------------------------------------------------------
-- 7) Estado derivado de I_LIQ y RG_REND con comprobante Cancelado total
-- ---------------------------------------------------------------------
UPDATE gestion__obligaciones_impositivas o
JOIN gestion__comprobantes c ON c.comprobante_id = o.comprobante_id
SET o.tabla_estado_registro_id = 35
WHERE o.tabla_estado_registro_id = 5 AND c.tabla_estado_registro_id = 40;

UPDATE gestion__rendiciones r
JOIN gestion__comprobantes c ON c.comprobante_id = r.comprobante_id
SET r.tabla_estado_registro_id = 36
WHERE r.tabla_estado_registro_id = 5 AND c.tabla_estado_registro_id = 40;

-- ---------------------------------------------------------------------
-- 8) Verificación: ningún valor 2121-2180 en las tablas migradas
-- ---------------------------------------------------------------------
CALL `_t04_assert`((
      (SELECT COUNT(*) FROM gestion__comprobantes               WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__cobranzas                  WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__pagos                      WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__pagos_impuestos            WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__transferencias             WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__movimientos_caja           WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__cheques_operaciones        WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__rendiciones_anticipos      WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__rendiciones_reembolsos     WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__comprobantes_imputaciones  WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__tesoreria_movimientos      WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__rendiciones_items          WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__obligaciones_impositivas   WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__rendiciones                WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__cheques                    WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
    + (SELECT COUNT(*) FROM gestion__cont_asientos              WHERE tabla_estado_registro_id BETWEEN 2121 AND 2180)
) = 0, 'Quedaron valores 2121-2180 sin migrar: se revierte');

COMMIT;

DROP PROCEDURE IF EXISTS `_t04_assert`;
SET SESSION sql_mode = @t04_old_sql_mode;

-- Resumen (solo lectura)
SELECT 'comprobantes' AS tabla, tabla_origen_id, tabla_estado_registro_id, COUNT(*) AS filas
FROM gestion__comprobantes WHERE tabla_origen_id BETWEEN 264 AND 288
GROUP BY tabla_origen_id, tabla_estado_registro_id ORDER BY tabla_origen_id, tabla_estado_registro_id;
