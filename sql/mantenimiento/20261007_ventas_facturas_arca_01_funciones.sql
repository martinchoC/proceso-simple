-- =====================================================================
-- Facturas de Clientes: CAE de ARCA al aprobar - script 01: FUNCIONES
-- 2026-10-07
--
-- Una factura fiscal (tipo con comprobante_fiscal_id > 0) queda en 4 "Pendiente de ARCA" al
-- confirmarla, sin número. La acción existente "Aprobada" (365, 4 -> 5) pide el CAE con la
-- integración WSFE activa de la empresa (arca_integraciones.php) y recién con el CAE pasa a 5,
-- con el número que asignó ARCA (vfArcaAutorizarFactura en ventas_facturas_model.php).
-- Sin tablas nuevas: usa las columnas CAE / f_vto_CAE que ya tiene gestion__ventas_facturas.
--
-- Función nueva "Volver a Borrador" (accion_js 'borrador', 4 -> 3) para corregir una factura
-- que ARCA rechazó (CUIT, fecha, importes...). vfVolverABorrador() la permite solo en fiscales
-- sin CAE.
--   3353 -> página 56, 4 -> 3
--
-- Permisos: se copian de "Aprobada" (365) en conf__perfiles_funciones y
-- conf__empresas_perfiles_funciones.
--
-- Idempotente (se puede volver a correr). Corta si el id está ocupado por otra cosa.
-- Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_vfa01_assert`;
DELIMITER $$
CREATE PROCEDURE `_vfa01_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Controles previos
-- ---------------------------------------------------------------------
CALL `_vfa01_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_vfa01_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id = 56 AND url = 'ventas_facturas.php') = 1,
                     'conf__paginas: se esperaba 56 = ventas_facturas.php');

CALL `_vfa01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                      WHERE pagina_funcion_id = 3353 AND NOT (pagina_id = 56 AND accion_js = 'borrador')) = 0,
                     'conf__paginas_funciones: el id 3353 está ocupado por otra función');

CALL `_vfa01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                      WHERE pagina_funcion_id = 365 AND pagina_id = 56 AND accion_js = 'aprobada'
                        AND tabla_estado_registro_origen_id = 4 AND tabla_estado_registro_destino_id = 5) = 1,
                     'conf__paginas_funciones: no está la función 365 (Aprobada 4 -> 5 de Facturas)');

CALL `_vfa01_assert`((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ventas_facturas'
                        AND COLUMN_NAME IN ('CAE', 'f_vto_CAE')) = 2,
                     'gestion__ventas_facturas: faltan las columnas CAE / f_vto_CAE');

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Función: Volver a Borrador (4 -> 3)
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3353, 56, f.icono_id, 5, 1, 'Volver a Borrador', 'borrador',
       'Devolver a Borrador una factura sin CAE (p. ej. rechazada por ARCA) para corregirla', 4, 3, 3, 1
FROM conf__paginas_funciones f
WHERE f.pagina_funcion_id = 225   -- mismo ícono que Editar
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = 3353);

-- ---------------------------------------------------------------------
-- 2) Permisos copiados de Aprobada (365)
-- ---------------------------------------------------------------------
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 3353, 1
FROM conf__perfiles_funciones p
WHERE p.pagina_funcion_id = 365 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 3353);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 3353, 1
FROM conf__empresas_perfiles_funciones p
WHERE p.pagina_funcion_id = 365 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 3353);

COMMIT;

DROP PROCEDURE IF EXISTS `_vfa01_assert`;

-- Resumen (solo lectura)
SELECT f.pagina_funcion_id, f.nombre_funcion, f.accion_js,
       f.tabla_estado_registro_origen_id AS origen, f.tabla_estado_registro_destino_id AS destino,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones e
         WHERE e.pagina_funcion_id = f.pagina_funcion_id AND e.asignado = 1) AS perfiles_con_acceso
FROM conf__paginas_funciones f WHERE f.pagina_id = 56 AND f.tabla_estado_registro_origen_id = 4;
