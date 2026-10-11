-- =====================================================================
-- Facturas de Clientes: cobro desde la solapa "Medios de Pago" - script 01: FUNCIONES
-- 2026-10-07
--
-- La solapa "Medios de Pago" de ventas_facturas.php emite un Recibo de Cobro (RCX) con
-- punto de venta, confirmado e imputado a la factura (reusa guardarCobranzaBorrador() +
-- confirmarCobranza() de cobranzas_model.php). Sin tablas ni estados nuevos.
--
-- Función "Cobrar" (accion_js 'cobrar'), sin transición de estado: el cambio de estado de
-- la factura (39 Cancelado parcial / 40 Cancelado total) lo hace recalcularEstadoDestino()
-- al confirmar el recibo. Solo desde los estados con saldo cancelable (los mismos que lista
-- tesoreriaObtenerComprobantesCancelables): 5 Confirmado y 39 Cancelado parcial.
--   3351 -> página 56, 5 -> 5
--   3352 -> página 56, 39 -> 39
-- vfEmitirReciboFactura() exige que exista la función para el estado actual de la factura.
--
-- Permisos: se copian de "Aprobada" de Facturas (pagina_funcion_id 365) en
-- conf__perfiles_funciones y conf__empresas_perfiles_funciones.
--
-- Idempotente (se puede volver a correr). Corta si los ids están ocupados por otra cosa.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_vfc01_assert`;
DELIMITER $$
CREATE PROCEDURE `_vfc01_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Controles previos
-- ---------------------------------------------------------------------
CALL `_vfc01_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_vfc01_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id = 56 AND url = 'ventas_facturas.php') = 1,
                     'conf__paginas: se esperaba 56 = ventas_facturas.php');

CALL `_vfc01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                      WHERE pagina_funcion_id IN (3351, 3352)
                        AND NOT (pagina_id = 56 AND accion_js = 'cobrar')) = 0,
                     'conf__paginas_funciones: los ids 3351/3352 están ocupados por otra función');

CALL `_vfc01_assert`((SELECT COUNT(*) FROM conf__tablas_estados_registros
                      WHERE tabla_id = 80 AND estado_registro_id IN (5, 39)) = 2,
                     'conf__tablas_estados_registros: faltan los estados 5 / 39 de ventas_facturas (tabla 80)');

CALL `_vfc01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id = 365 AND pagina_id = 56) = 1,
                     'conf__paginas_funciones: no está la función 365 (Aprobada de Facturas) para copiar permisos');

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Funciones: Cobrar, sin transición de estado
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, 56, 79, 3, 1, 'Cobrar', 'cobrar', 'Emitir recibo de cobro imputado a la factura',
       v.estado, v.estado, 3, 1
FROM (
              SELECT 3351 AS id, 5 AS estado
    UNION ALL SELECT 3352, 39
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = v.id);

-- ---------------------------------------------------------------------
-- 2) Permisos copiados de Aprobada (365)
-- ---------------------------------------------------------------------
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, n.id, 1
FROM (SELECT 3351 AS id UNION ALL SELECT 3352) n
JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = 365 AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, n.id, 1
FROM (SELECT 3351 AS id UNION ALL SELECT 3352) n
JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = 365 AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

COMMIT;

DROP PROCEDURE IF EXISTS `_vfc01_assert`;

-- Resumen (solo lectura)
SELECT f.pagina_funcion_id, f.nombre_funcion, f.accion_js,
       f.tabla_estado_registro_origen_id AS origen, f.tabla_estado_registro_destino_id AS destino,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones e
         WHERE e.pagina_funcion_id = f.pagina_funcion_id AND e.asignado = 1) AS perfiles_con_acceso
FROM conf__paginas_funciones f WHERE f.pagina_funcion_id IN (3351, 3352);
