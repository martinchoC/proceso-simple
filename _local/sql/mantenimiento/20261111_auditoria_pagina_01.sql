-- -----------------------------------------------------------------------------------------------------------------
-- 20261111_auditoria_pagina_01.sql
-- Página "Logs de Auditoría" (modules/conf/auditoria.php, core/auditoria.php) en el menú de conf (módulo 1).
--
-- Se dio de alta a mano el 2026-11-09 como página 1055 (bloque 1000–1099 de sindicatos), sin función ni permisos.
-- Pasa a la 142 (bloque conf/gestion) bajo "Empresas y accesos", con la función 3574 "Acceder" (consultar) y
-- permiso solo para los perfiles de administración que ya tienen Usuarios (regla de 20261014_vinculaciones_02).
-- Las tablas conf__auditoria_AAAA las crea el código (auditoria_asegurar_tabla); este script no las toca.
--
-- Reanudable e idempotente (MariaDB 10.4 / MySQL 8.0).
-- -----------------------------------------------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Página: renumerar la 1055 si existe; si no, alta
UPDATE `conf__paginas` p
LEFT JOIN `conf__paginas` libre ON libre.`pagina_id` = 142
SET p.`pagina_id` = 142, p.`padre_id` = 1, p.`orden` = 540, p.`es_acceso_directo` = 0
WHERE p.`pagina_id` = 1055 AND p.`modulo_id` = 1 AND p.`url` = 'auditoria.php'
  AND libre.`pagina_id` IS NULL;

INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `padre_id`, `orden`, `tabla_id`,
                             `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`)
SELECT 142, 1, 'Logs de Auditoría', 'auditoria.php', 1, 1, 540, NULL, 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `conf__paginas` WHERE `pagina_id` = 142);

-- 2. Función de acceso
INSERT INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`,
                                       `nombre_funcion`, `accion_js`, `descripcion`,
                                       `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
SELECT 3574, 142, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `conf__paginas_funciones` WHERE `pagina_funcion_id` = 3574);

-- 3. Permisos: los perfiles de administración que acceden a Usuarios (página 18, función 3376)
INSERT INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT epf.`empresa_id`, epf.`empresa_perfil_id`, 3574, 1
FROM `conf__empresas_perfiles_funciones` epf
INNER JOIN `conf__empresas_perfiles` ep ON ep.`empresa_perfil_id` = epf.`empresa_perfil_id`
WHERE epf.`pagina_funcion_id` = 3376 AND epf.`asignado` = 1
  AND (ep.`empresa_perfil_nombre` LIKE 'Administrador%' OR ep.`empresa_perfil_nombre` = 'Acceso total')
  AND NOT EXISTS (SELECT 1 FROM `conf__empresas_perfiles_funciones` x
                  WHERE x.`empresa_perfil_id` = epf.`empresa_perfil_id` AND x.`pagina_funcion_id` = 3574);

SELECT pagina_id, pagina, url, padre_id FROM conf__paginas WHERE url = 'auditoria.php' AND modulo_id = 1;
SELECT COUNT(*) AS perfiles_con_auditoria FROM conf__empresas_perfiles_funciones WHERE pagina_funcion_id = 3574;
