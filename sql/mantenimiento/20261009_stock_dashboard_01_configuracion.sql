-- =====================================================================
-- Dashboard de stock - script 01: PÁGINA, FUNCIÓN Y PERMISOS
-- 2026-10-09
--
-- Requiere 20261008_stock_0{1,2}_*.sql (gestion__stock / _movimientos) y
-- 20261008_stock_niveles_0{1,2}_*.sql (niveles/alertas, que el dashboard lee).
--
-- Página de solo lectura, sin tabla propia (tabla_id 0, como el Dashboard
-- Financiero 104): no lleva filas en conf__tablas ni en
-- conf__tablas_estados_registros.
--
-- conf__paginas (menú Stock = 44):
--   114 Stock - Dashboard   stock_dashboard.php   orden 90 (primera del menú)
--
-- conf__paginas_funciones:
--   3366 Ver Dashboard de Stock  'consultar'  0 -> 0  (solo da acceso a la página;
--        core/permisos.php concede la página a quien tenga alguna función asignada)
--
-- Permisos: se copian de "Agregar" de Ajustes de Stock (pagina_funcion_id 282)
-- en conf__perfiles_funciones y conf__empresas_perfiles_funciones (la que lee
-- core/permisos.php). Ajustar después desde funciones por perfil.
--
-- Idempotente. Corta si los ids están ocupados por otra cosa. Ambos motores.
-- =====================================================================

DROP PROCEDURE IF EXISTS `_sd01_assert`;
DELIMITER $$
CREATE PROCEDURE `_sd01_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Controles previos
-- ---------------------------------------------------------------------
CALL `_sd01_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_sd01_assert`((SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME IN ('gestion__stock', 'gestion__stock_movimientos',
                                          'gestion__stock_niveles', 'gestion__stock_alertas')) = 4,
                    'Faltan tablas de stock: correr antes 20261008_stock_01_ddl.sql y 20261008_stock_niveles_01_ddl.sql');

CALL `_sd01_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE pagina_id = 114 AND url <> 'stock_dashboard.php') = 0,
                    'conf__paginas: el id 114 está ocupado por otra página');

CALL `_sd01_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE url = 'stock_dashboard.php' AND pagina_id <> 114) = 0,
                    'conf__paginas: stock_dashboard.php ya registrada con otro id');

CALL `_sd01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                     WHERE pagina_funcion_id = 3366 AND pagina_id <> 114) = 0,
                    'conf__paginas_funciones: el id 3366 está ocupado por otra página');

CALL `_sd01_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id = 44) = 1,
                    'conf__paginas: falta el menú Stock (44)');

CALL `_sd01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id = 282 AND pagina_id = 77) = 1,
                    'conf__paginas_funciones: no está la función 282 (Agregar de Ajustes de Stock) para copiar permisos');

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Página (menú Stock = 44)
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas
    (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
     pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 114, 2, 'Stock - Dashboard', 'stock_dashboard.php', 103,
       'Stock actual por producto y boca, movimientos y kardex', 44, 90, 0, 1, 1, 0, NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = 114);

-- ---------------------------------------------------------------------
-- 2) Función de acceso
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3366, 114, NULL, 1, 1, 'Ver Dashboard de Stock', 'consultar',
       'Consultar stock, movimientos y kardex', 0, 0, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = 3366);

-- ---------------------------------------------------------------------
-- 3) Permisos copiados de Agregar de Ajustes de Stock (282)
-- ---------------------------------------------------------------------
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 3366, 1
FROM conf__perfiles_funciones p
WHERE p.pagina_funcion_id = 282 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 3366);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 3366, 1
FROM conf__empresas_perfiles_funciones p
WHERE p.pagina_funcion_id = 282 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 3366);

COMMIT;

DROP PROCEDURE IF EXISTS `_sd01_assert`;

-- Resumen (solo lectura)
SELECT p.pagina_id, p.pagina, p.url, p.padre_id, p.orden,
       (SELECT COUNT(*) FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id) AS funciones,
       (SELECT COUNT(DISTINCT e.empresa_perfil_id) FROM conf__empresas_perfiles_funciones e
          JOIN conf__paginas_funciones f ON f.pagina_funcion_id = e.pagina_funcion_id
         WHERE f.pagina_id = p.pagina_id AND e.asignado = 1) AS perfiles_con_acceso
FROM conf__paginas p WHERE p.pagina_id = 114;
