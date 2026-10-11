-- =====================================================================
-- Consulta de Comprobantes + Cuenta Corriente de Clientes - script 01: PÁGINAS
-- 2026-10-07
--
-- Páginas nuevas (módulo 2, solo lectura, sin tablas ni estados nuevos):
--   102 Comprobantes - Consulta   modules/gestion/comprobantes_consulta.php
--       menú Tesorería (100), tabla 43 = gestion__comprobantes
--   103 Cuenta Corriente Clientes modules/gestion/clientes_ctacte.php
--       menú Ventas (48), tabla 37 = gestion__entidades
-- La solapa "Cuenta Corriente" de clientes.php usa clientes_ctacte_ajax.php,
-- que acepta a quien tenga acceso a clientes.php o a clientes_ctacte.php:
-- no necesita filas acá.
--
-- Funciones (motor de botones): una sola, "Visualizar", sin transición
-- (origen = destino = 0, como "Ver Recibo" de cobranzas, 3277). Sin función de
-- origen 0 -> 1, así que no hay botón Agregar.
--   3349 -> página 102, 3350 -> página 103
--
-- Permisos: se copian de "Visualizar" de Clientes (pagina_funcion_id 420) en
-- conf__perfiles_funciones y conf__empresas_perfiles_funciones (la que lee
-- core/permisos.php): quien ve Clientes ve las dos páginas nuevas. Ajustar
-- después desde la pantalla de funciones por perfil si hace falta.
--
-- Idempotente (se puede volver a correr). Corta si los ids están ocupados por
-- otra cosa. Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- =====================================================================

DROP PROCEDURE IF EXISTS `_cc01_assert`;
DELIMITER $$
CREATE PROCEDURE `_cc01_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Controles previos
-- ---------------------------------------------------------------------
CALL `_cc01_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_cc01_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE (pagina_id = 102 AND url <> 'comprobantes_consulta.php')
                        OR (pagina_id = 103 AND url <> 'clientes_ctacte.php')) = 0,
                    'conf__paginas: los ids 102/103 están ocupados por otra página');

CALL `_cc01_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE (url = 'comprobantes_consulta.php' AND pagina_id <> 102)
                        OR (url = 'clientes_ctacte.php' AND pagina_id <> 103)) = 0,
                    'conf__paginas: comprobantes_consulta.php / clientes_ctacte.php ya registradas con otro id');

CALL `_cc01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                     WHERE (pagina_funcion_id = 3349 AND pagina_id <> 102)
                        OR (pagina_funcion_id = 3350 AND pagina_id <> 103)) = 0,
                    'conf__paginas_funciones: los ids 3349/3350 están ocupados por otra página');

CALL `_cc01_assert`((SELECT COUNT(*) FROM conf__tablas
                     WHERE (tabla_id = 43 AND tabla_nombre = 'gestion__comprobantes')
                        OR (tabla_id = 37 AND tabla_nombre = 'gestion__entidades')) = 2,
                    'conf__tablas: se esperaba 43 = gestion__comprobantes y 37 = gestion__entidades');

CALL `_cc01_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (48, 100)) = 2,
                    'conf__paginas: faltan los menús Ventas (48) / Tesorería (100)');

CALL `_cc01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id = 420 AND pagina_id = 94) = 1,
                    'conf__paginas_funciones: no está la función 420 (Visualizar de Clientes) para copiar permisos');

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Páginas
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas
    (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
     pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT v.pagina_id, 2, v.pagina, v.url, v.icono_id, v.descripcion, v.padre_id, v.orden, v.tabla_id, 1, 1, 0, NULL
FROM (
              SELECT 102 AS pagina_id, 'Comprobantes - Consulta' AS pagina, 'comprobantes_consulta.php' AS url,
                     37 AS icono_id, 'Consulta de todos los comprobantes con filtros, solo lectura' AS descripcion,
                     100 AS padre_id, 60 AS orden, 43 AS tabla_id
    UNION ALL SELECT 103, 'Cuenta Corriente Clientes', 'clientes_ctacte.php',
                     14, 'Saldos, deuda y movimientos de cuenta corriente de clientes',
                     48, 55, 37
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina_id);

-- ---------------------------------------------------------------------
-- 2) Funciones: Visualizar, sin transición de estado
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina_id, 37, 1, 1, 'Visualizar', 'visualizar', v.descripcion, 0, 0, 1, 1
FROM (
              SELECT 3349 AS id, 102 AS pagina_id, 'Ver comprobantes y su detalle' AS descripcion
    UNION ALL SELECT 3350, 103, 'Ver cuenta corriente de clientes'
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = v.id);

-- ---------------------------------------------------------------------
-- 3) Permisos copiados de Visualizar de Clientes (420)
-- ---------------------------------------------------------------------
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, n.id, 1
FROM (SELECT 3349 AS id UNION ALL SELECT 3350) n
JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = 420 AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, n.id, 1
FROM (SELECT 3349 AS id UNION ALL SELECT 3350) n
JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = 420 AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

COMMIT;

DROP PROCEDURE IF EXISTS `_cc01_assert`;

-- Resumen (solo lectura)
SELECT p.pagina_id, p.pagina, p.url, p.padre_id,
       (SELECT COUNT(*) FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id) AS funciones,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones e
          JOIN conf__paginas_funciones f ON f.pagina_funcion_id = e.pagina_funcion_id
         WHERE f.pagina_id = p.pagina_id AND e.asignado = 1) AS perfiles_con_acceso
FROM conf__paginas p WHERE p.pagina_id IN (102, 103);
