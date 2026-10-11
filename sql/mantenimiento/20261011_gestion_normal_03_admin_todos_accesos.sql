-- Módulo 5 (gestion_normal): el perfil Administrador recibe todas las funciones de todas las páginas.
--
-- 1) Las páginas hoja del módulo que no tenían ninguna función (Páginas, Tablas, Estados, Funciones,
--    Mayores, Balance, Órdenes de Pago, Cartera de Cheques, Movimientos Internos, Obligaciones y
--    Rendiciones, Accesos por Perfil) quedaban solo para el superadmin: core/permisos.php da acceso a
--    una página únicamente si el perfil tiene asignada alguna de sus funciones. Se les agrega una
--    función de acceso 'consultar' (0 → 0), mismo patrón que los dashboards 604 / 614.
-- 2) Todo perfil de módulo 5 con perfil_id_base = 3 (Administrador) recibe en
--    conf__empresas_perfiles_funciones todas las funciones activas del módulo (las que tenía con
--    asignado = 0 pasan a 1).
--
-- Reejecutable: los INSERT no duplican.

START TRANSACTION;

-- 1) Funciones de acceso para las páginas sin funciones
INSERT INTO `conf__paginas_funciones` (`pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`) VALUES
(8371, 529, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8372, 531, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8373, 534, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8374, 535, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8375, 585, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8376, 586, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8377, 606, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8378, 607, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8379, 608, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8380, 609, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1),
(8381, 941, 0, 1, 1, 'Acceder', 'consultar', 'Acceso a la página', 0, 0, 1, 1)
ON DUPLICATE KEY UPDATE `pagina_id` = VALUES(`pagina_id`), `nombre_funcion` = VALUES(`nombre_funcion`), `accion_js` = VALUES(`accion_js`);

-- 2a) Reactivar las asignaciones existentes en 0
UPDATE `conf__empresas_perfiles_funciones` epf
JOIN `conf__empresas_perfiles` ep ON ep.empresa_perfil_id = epf.empresa_perfil_id
JOIN `conf__paginas_funciones` pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
JOIN `conf__paginas` p ON p.pagina_id = pf.pagina_id
SET epf.asignado = 1
WHERE ep.modulo_id = 5 AND ep.perfil_id_base = 3
  AND p.modulo_id = 5
  AND (epf.asignado IS NULL OR epf.asignado <> 1);

-- 2b) Agregar las que faltan (la tabla no tiene UNIQUE: se evita el duplicado con NOT EXISTS)
INSERT INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM `conf__empresas_perfiles` ep
JOIN `conf__paginas` p ON p.modulo_id = 5
JOIN `conf__paginas_funciones` pf ON pf.pagina_id = p.pagina_id AND pf.tabla_estado_registro_id = 1
WHERE ep.modulo_id = 5 AND ep.perfil_id_base = 3
  AND NOT EXISTS (
      SELECT 1 FROM `conf__empresas_perfiles_funciones` x
      WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id
  );

COMMIT;
