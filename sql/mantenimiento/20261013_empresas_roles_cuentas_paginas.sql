-- =====================================================================
-- Roles de Entidades y Cuentas por Empresa - script 01: PÁGINAS Y PERMISOS
-- 2026-10-13
--
-- Páginas nuevas:
--   Módulo 2 (Gestión):
--     119 Entidades - Roles y Cuentas modules/gestion/empresas_roles_cuentas.php
--         menú Seteo Empresas (37), orden 431
--   Módulo 5 (Gestión Normal):
--     619 Entidades - Roles y Cuentas modules/gestion_normal/empresas_roles_cuentas.php
--         menú Seteo Empresas (537), orden 431
--
-- Funciones:
--   3403 / 8403 -> Visualizar
--   3404 / 8404 -> Agregar
--   3405 / 8405 -> Editar
--   3406 / 8406 -> Inhabilitar
--
-- Permisos copiados de Entidades Comerciales (página 50 en Mod 2, 550 en Mod 5).
-- Idempotente.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Páginas
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas
    (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
     pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT v.pagina_id, v.modulo_id, v.pagina, v.url, v.icono_id, v.descripcion, v.padre_id, v.orden, v.tabla_id, 1, 1, 0, NULL
FROM (
              SELECT 119 AS pagina_id, 2 AS modulo_id, 'Entidades - Roles y Cuentas' AS pagina, 'empresas_roles_cuentas.php' AS url,
                     18 AS icono_id, 'Definición de roles de entidades y cuentas contables genéricas por empresa' AS descripcion,
                     37 AS padre_id, 431 AS orden, 0 AS tabla_id
    UNION ALL SELECT 619, 5, 'Entidades - Roles y Cuentas', 'empresas_roles_cuentas.php',
                     18, 'Definición de roles de entidades y cuentas contables genéricas por empresa',
                     537, 431, 0
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina_id);

-- ---------------------------------------------------------------------
-- 2) Funciones
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina_id, v.icono_id, v.color_id, v.std_id, v.nombre, v.accion_js, v.descripcion, 0, 0, v.orden, 1
FROM (
              SELECT 3403 AS id, 119 AS pagina_id, 37 AS icono_id, 1 AS color_id, 1 AS std_id, 'Visualizar' AS nombre, 'visualizar' AS accion_js, 'Ver roles y cuentas de empresa' AS descripcion, 1 AS orden
    UNION ALL SELECT 3404, 119, 21, 2, 2, 'Agregar', 'agregar', 'Agregar rol y cuenta para empresa', 2
    UNION ALL SELECT 3405, 119, 22, 3, 3, 'Editar', 'editar', 'Editar cuenta o estado de rol de empresa', 3
    UNION ALL SELECT 3406, 119, 23, 4, 4, 'Inhabilitar', 'inactivar', 'Inhabilitar rol en la empresa', 4
    UNION ALL SELECT 8403, 619, 37, 1, 1, 'Visualizar', 'visualizar', 'Ver roles y cuentas de empresa', 1
    UNION ALL SELECT 8404, 619, 21, 2, 2, 'Agregar', 'agregar', 'Agregar rol y cuenta para empresa', 2
    UNION ALL SELECT 8405, 619, 22, 3, 3, 'Editar', 'editar', 'Editar cuenta o estado de rol de empresa', 3
    UNION ALL SELECT 8406, 619, 23, 4, 4, 'Inhabilitar', 'inactivar', 'Inhabilitar rol en la empresa', 4
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = v.id);

-- ---------------------------------------------------------------------
-- 3) Permisos copiados de Entidades (186..189 para Mod 2, 5186..5189 para Mod 5)
-- ---------------------------------------------------------------------
-- Mod 2: Visualizar (187 -> 3403)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 3403, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 187 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 3403);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 3403, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 187 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 3403);

-- Mod 2: Agregar (186 -> 3404)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 3404, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 186 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 3404);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 3404, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 186 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 3404);

-- Mod 2: Editar (188 -> 3405)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 3405, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 188 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 3405);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 3405, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 188 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 3405);

-- Mod 2: Inhabilitar (189 -> 3406)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 3406, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 189 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 3406);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 3406, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 189 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 3406);

-- Mod 5: Visualizar (5187 -> 8403)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 8403, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 5187 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 8403);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 8403, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 5187 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 8403);

-- Mod 5: Agregar (5186 -> 8404)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 8404, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 5186 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 8404);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 8404, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 5186 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 8404);

-- Mod 5: Editar (5188 -> 8405)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 8405, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 5188 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 8405);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 8405, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 5188 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 8405);

-- Mod 5: Inhabilitar (5189 -> 8406)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, 8406, 1 FROM conf__perfiles_funciones p WHERE p.pagina_funcion_id = 5189 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 8406);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, 8406, 1 FROM conf__empresas_perfiles_funciones p WHERE p.pagina_funcion_id = 5189 AND p.asignado = 1
AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = 8406);

COMMIT;
