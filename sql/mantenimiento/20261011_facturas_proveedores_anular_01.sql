-- =====================================================================================================
-- Facturas de proveedores: ANULAR un comprobante confirmado (5 -> 38)
-- -----------------------------------------------------------------------------------------------------
-- Una factura / NC / ND de proveedor confirmada no tenía transición de salida. "Anular" la pasa a 38 y revierte:
--   - stock (estado 38 sin aplica_stock en la tabla 84: stockSincronizarComprobante devuelve lo ingresado),
--   - asiento (queda anulado, estado 2), comprobante universal (38, saldo 0),
--   - cuenta corriente: anula las imputaciones vigentes del comprobante (OP / NC que lo cancelaban quedan con
--     saldo a favor; si el anulado es una NC aplicada, la factura destino recupera su saldo),
--   - la OP automática que se emitió al confirmarlo (contado o débito en cuenta) se anula entera (revierte caja / banco).
--     Se identifica por gestion__facturas_proveedores.pago_automatico_id.
--
--   1) columna pago_automatico_id
--   2) estado 38 "Anulada" en conf__tablas_estados_registros de la tabla 84 (id 223, sin stock, final)
--   3) función "Anular" (accion_js anular, 5 -> 38): 3371 en la página 57 (gestion), 9041 en la 557 (gestion_normal)
--   4) permisos: los mismos perfiles que tienen "Confirmar" de cada página (230 / 5230), en
--      conf__perfiles_funciones y conf__empresas_perfiles_funciones
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

DROP PROCEDURE IF EXISTS `_fa01_anular`;
DELIMITER $$
CREATE PROCEDURE `_fa01_anular`()
BEGIN
    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seleccionar la base (USE ...) antes de correr el script';
    END IF;
    IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'pago_automatico_id') = 0 THEN
        ALTER TABLE gestion__facturas_proveedores
            ADD COLUMN pago_automatico_id INT UNSIGNED NULL DEFAULT NULL
            COMMENT 'OP emitida automáticamente al confirmar (contado / débito en cuenta): se anula con el comprobante'
            AFTER comprobante_asociado_id;
    END IF;
    -- Ids fijos: si en esta base están ocupados por otra cosa, frenar (para que local y producción coincidan)
    IF EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 3371 AND NOT (pagina_id = 57 AND accion_js = 'anular'))
       OR EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 9041 AND NOT (pagina_id = 557 AND accion_js = 'anular')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los ids 3371 / 9041 de conf__paginas_funciones ya están usados: elegir otros';
    END IF;
    IF EXISTS (SELECT 1 FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id = 223 AND NOT (tabla_id = 84 AND estado_registro_id = 38)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El id 223 de conf__tablas_estados_registros ya está usado: elegir otro';
    END IF;
END$$
DELIMITER ;
CALL `_fa01_anular`();
DROP PROCEDURE IF EXISTS `_fa01_anular`;

-- 2) Estado 38 en la tabla 84
INSERT INTO conf__tablas_estados_registros
    (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, es_final, orden, aplica_stock)
SELECT 223, 84, 38, 'Anulada', e.color_id, 0, 1, 90, 0
FROM conf__tablas_estados_registros e
WHERE e.tabla_id = 84 AND e.estado_registro_id = 6
  AND NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros x WHERE x.tabla_id = 84 AND x.estado_registro_id = 38);

-- 3) Función Anular desde Confirmada (copia ícono/color de "Eliminar" de la misma página)
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT n.id, pf.pagina_id, pf.icono_id, pf.color_id, pf.funcion_estandar_id, 'Anular', 'anular',
       'Anular el comprobante confirmado: revierte stock, asiento, imputaciones y la OP automática', 5, 38, 50, 1
FROM (SELECT 3371 AS id, 57 AS pagina UNION ALL SELECT 9041, 557) n
INNER JOIN conf__paginas_funciones pf ON pf.pagina_id = n.pagina AND pf.accion_js = 'eliminar' AND pf.tabla_estado_registro_origen_id = 3
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = n.id);

-- 4) Permisos copiados de "Confirmar" de la misma página
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT p.perfil_id, n.id, p.asignado
FROM (SELECT 3371 AS id, 57 AS pagina UNION ALL SELECT 9041, 557) n
INNER JOIN conf__paginas_funciones base ON base.pagina_id = n.pagina AND base.accion_js = 'confirmar'
INNER JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = base.pagina_funcion_id
WHERE EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT p.empresa_id, p.empresa_perfil_id, n.id, p.asignado
FROM (SELECT 3371 AS id, 57 AS pagina UNION ALL SELECT 9041, 557) n
INNER JOIN conf__paginas_funciones base ON base.pagina_id = n.pagina AND base.accion_js = 'confirmar'
INNER JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = base.pagina_funcion_id
WHERE EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

-- Control (solo lectura)
SELECT f.pagina_funcion_id, f.pagina_id, f.nombre_funcion, f.tabla_estado_registro_origen_id AS origen,
       f.tabla_estado_registro_destino_id AS destino,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones e WHERE e.pagina_funcion_id = f.pagina_funcion_id) AS permisos_empresa
FROM conf__paginas_funciones f
WHERE f.pagina_funcion_id IN (3371, 9041);
