-- =====================================================================================================
-- Usuarios jgili y gblanco: TODOS los permisos en todos los módulos activos de todas las empresas
-- -----------------------------------------------------------------------------------------------------
-- Pedido de Pablo (2026-10-11). Los usuarios existen en la base local (demo Developsam, empresa 6).
--
-- 1) Páginas hoja activas sin ninguna función (30: Usuarios, Perfiles, Mayores, Balances, Órdenes de Pago,
--    Cartera de Cheques, Movimientos Internos, Obligaciones y Rendiciones, Accesos por Perfil, Cierre de
--    ejercicio, Plan de cuentas, DDJJ de Sindicatos, etc.): core/permisos.php solo da acceso a una página si
--    el perfil tiene alguna de sus funciones, así que quedaban solo para el superadmin. Se les agrega la
--    función de acceso 'consultar' (0 -> 0), mismo patrón que 20261011_gestion_normal_03 (ids 3374..., en
--    orden de pagina_id). No le da acceso a nadie más: solo existe para poder asignarla.
-- 2) Por cada empresa y módulo ACTIVO de la empresa (conf__empresas_modulos.tabla_estado_registro_id = 1),
--    un perfil propio "Acceso total" (perfil_id_base = el administrador del módulo), para no cambiar los
--    permisos de los demás usuarios que comparten el perfil Administrador.
-- 3) Ese perfil recibe todas las funciones activas de todas las páginas del módulo
--    (conf__empresas_perfiles_funciones, la que lee core/permisos.php, y conf__perfiles_funciones no se toca).
-- 4) jgili y gblanco quedan asignados a todos esos perfiles (vigencia 2026-01-01 a 2050-12-31).
-- Reejecutable: los INSERT controlan duplicados; si se activa un módulo nuevo, volver a correrlo.
-- =====================================================================================================

START TRANSACTION;

-- 1) Función de acceso para las páginas hoja sin funciones
CREATE TEMPORARY TABLE IF NOT EXISTS _tmp_paginas_sin_func (pagina_id INT PRIMARY KEY, pagina_funcion_id INT);
DELETE FROM _tmp_paginas_sin_func;
INSERT INTO _tmp_paginas_sin_func (pagina_id, pagina_funcion_id)
SELECT p.pagina_id, 3373 + ROW_NUMBER() OVER (ORDER BY p.pagina_id)
FROM conf__paginas p
WHERE p.tabla_estado_registro_id = 1 AND p.url NOT IN ('#', '')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id AND f.tabla_estado_registro_id = 1)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas h WHERE h.padre_id = p.pagina_id);

INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT t.pagina_funcion_id, t.pagina_id, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1
FROM _tmp_paginas_sin_func t
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = t.pagina_funcion_id);
DROP TEMPORARY TABLE IF EXISTS _tmp_paginas_sin_func;

-- 2) Perfil "Acceso total" por empresa y módulo activo (base: el perfil administrador del módulo)
INSERT INTO conf__empresas_perfiles (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id)
SELECT em.empresa_id, em.modulo_id,
       (SELECT pr.perfil_id FROM conf__perfiles pr WHERE pr.modulo_id = IF(em.modulo_id = 5, 2, em.modulo_id) ORDER BY pr.perfil_id LIMIT 1),
       'Acceso total', 1
FROM conf__empresas_modulos em
WHERE em.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles ep
                  WHERE ep.empresa_id = em.empresa_id AND ep.modulo_id = em.modulo_id AND ep.empresa_perfil_nombre = 'Acceso total');

-- 3) Todas las funciones activas de las páginas del módulo
UPDATE conf__empresas_perfiles_funciones epf
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id AND ep.empresa_perfil_nombre = 'Acceso total'
SET epf.asignado = 1
WHERE epf.asignado IS NULL OR epf.asignado <> 1;

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM conf__empresas_perfiles ep
JOIN conf__empresas_modulos em ON em.empresa_id = ep.empresa_id AND em.modulo_id = ep.modulo_id AND em.tabla_estado_registro_id = 1
JOIN conf__paginas p ON p.modulo_id = ep.modulo_id AND p.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones pf ON pf.pagina_id = p.pagina_id AND pf.tabla_estado_registro_id = 1
WHERE ep.empresa_perfil_nombre = 'Acceso total' AND ep.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

-- 4) Asignación a los usuarios
INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, usuario_creacion, tabla_estado_registro_id)
SELECT u.usuario_id, ep.empresa_perfil_id, '2026-01-01', '2050-12-31', 1, 1
FROM conf__usuarios u
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_nombre = 'Acceso total' AND ep.tabla_estado_registro_id = 1
WHERE u.usuario IN ('jgili', 'gblanco')
  AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles x
                  WHERE x.usuario_id = u.usuario_id AND x.empresa_perfil_id = ep.empresa_perfil_id AND x.tabla_estado_registro_id = 1);

COMMIT;

-- Control (solo lectura): perfiles "Acceso total" con sus funciones y usuarios
SELECT ep.empresa_perfil_id, e.empresa, m.modulo, ep.perfil_id_base,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones f WHERE f.empresa_perfil_id = ep.empresa_perfil_id AND f.asignado = 1) AS funciones,
       (SELECT COUNT(*) FROM conf__paginas_funciones pf JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
         WHERE p.modulo_id = ep.modulo_id AND p.tabla_estado_registro_id = 1 AND pf.tabla_estado_registro_id = 1) AS funciones_del_modulo,
       (SELECT GROUP_CONCAT(u.usuario) FROM conf__usuarios_perfiles up JOIN conf__usuarios u ON u.usuario_id = up.usuario_id
         WHERE up.empresa_perfil_id = ep.empresa_perfil_id AND up.tabla_estado_registro_id = 1) AS usuarios
FROM conf__empresas_perfiles ep
JOIN conf__empresas e ON e.empresa_id = ep.empresa_id
JOIN conf__modulos m ON m.modulo_id = ep.modulo_id
WHERE ep.empresa_perfil_nombre = 'Acceso total'
ORDER BY ep.empresa_id, ep.modulo_id;
