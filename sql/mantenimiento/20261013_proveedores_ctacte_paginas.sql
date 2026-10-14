-- =====================================================================
-- Cuenta Corriente de Proveedores - script 01: PÁGINAS Y PERMISOS
-- 2026-10-13
--
-- Páginas nuevas:
--   Módulo 2 (Gestión):
--     118 Cuenta Corriente Proveedores modules/gestion/proveedores_ctacte.php
--         menú Compras (47), tabla 37 = gestion__entidades
--   Módulo 5 (Gestión Normal):
--     618 Cuenta Corriente Proveedores modules/gestion_normal/proveedores_ctacte.php
--         menú Compras (547), tabla 37 = gestion__entidades
--
-- Funciones: una sola, "Visualizar", sin transición (origen = destino = 0):
--   3402 -> página 118 (Módulo 2)
--   8402 -> página 618 (Módulo 5)
--
-- Permisos:
--   - 3402 se copia de "Visualizar" de Proveedores (pagina_funcion_id 415)
--   - 8402 se copia de "Visualizar" de Proveedores (pagina_funcion_id 5415)
--   en conf__perfiles_funciones y conf__empresas_perfiles_funciones.
--
-- Idempotente.
-- =====================================================================

DROP PROCEDURE IF EXISTS `_cc02_assert`;
DELIMITER $$
CREATE PROCEDURE `_cc02_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Controles previos
-- ---------------------------------------------------------------------
CALL `_cc02_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_cc02_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE (pagina_id = 118 AND url <> 'proveedores_ctacte.php')
                        OR (pagina_id = 618 AND url <> 'proveedores_ctacte.php')) = 0,
                    'conf__paginas: los ids 118/618 están ocupados por otra página');

CALL `_cc02_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE (url = 'proveedores_ctacte.php' AND pagina_id NOT IN (118, 618))) = 0,
                    'conf__paginas: proveedores_ctacte.php ya registrada con otro id');

CALL `_cc02_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                     WHERE (pagina_funcion_id = 3402 AND pagina_id <> 118)
                        OR (pagina_funcion_id = 8402 AND pagina_id <> 618)) = 0,
                    'conf__paginas_funciones: los ids 3402/8402 están ocupados por otra página');

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Páginas
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas
    (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
     pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT v.pagina_id, v.modulo_id, v.pagina, v.url, v.icono_id, v.descripcion, v.padre_id, v.orden, v.tabla_id, 1, 1, 0, NULL
FROM (
              SELECT 118 AS pagina_id, 2 AS modulo_id, 'Cuenta Corriente Proveedores' AS pagina, 'proveedores_ctacte.php' AS url,
                     14 AS icono_id, 'Saldos, deuda y movimientos de cuenta corriente de proveedores' AS descripcion,
                     47 AS padre_id, 55 AS orden, 37 AS tabla_id
    UNION ALL SELECT 618, 5, 'Cuenta Corriente Proveedores', 'proveedores_ctacte.php',
                     14, 'Saldos, deuda y movimientos de cuenta corriente de proveedores',
                     547, 55, 37
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
              SELECT 3402 AS id, 118 AS pagina_id, 'Ver cuenta corriente de proveedores' AS descripcion
    UNION ALL SELECT 8402, 618, 'Ver cuenta corriente de proveedores'
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = v.id);

-- ---------------------------------------------------------------------
-- 3) Permisos copiados de Visualizar de Proveedores (415 para Mod 2, 5415 para Mod 5)
-- ---------------------------------------------------------------------
-- Modulo 2
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 3402, 1
FROM conf__perfiles_funciones p
WHERE p.pagina_funcion_id = 415 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 3402);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 3402, 1
FROM conf__empresas_perfiles_funciones p
WHERE p.pagina_funcion_id = 415 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 3402);

-- Modulo 5
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 8402, 1
FROM conf__perfiles_funciones p
WHERE p.pagina_funcion_id = 5415 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 8402);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 8402, 1
FROM conf__empresas_perfiles_funciones p
WHERE p.pagina_funcion_id = 5415 AND p.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 8402);

COMMIT;

DROP PROCEDURE IF EXISTS `_cc02_assert`;

-- Resumen (solo lectura)
SELECT p.pagina_id, p.modulo_id, p.pagina, p.url, p.padre_id,
       (SELECT COUNT(*) FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id) AS funciones,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones e
          JOIN conf__paginas_funciones f ON f.pagina_funcion_id = e.pagina_funcion_id
         WHERE f.pagina_id = p.pagina_id AND e.asignado = 1) AS perfiles_con_acceso
FROM conf__paginas p WHERE p.pagina_id IN (118, 618);
