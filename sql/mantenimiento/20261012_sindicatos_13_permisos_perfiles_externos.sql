-- ==============================================================================
-- 20261012_sindicatos_13_permisos_perfiles_externos.sql
-- Módulo 6 (Sindicatos): permisos de los perfiles externos del portal.
--
-- Los scripts 02/04 dieron a los perfiles Empleador (604), Estudio Contable (605) y Delegado (606)
-- todas las funciones de todas las páginas: podían crear y confirmar actas, planes, DDJJ de
-- oficio, procesar rendiciones, ver el tablero de fiscalización y dar de alta o inhabilitar
-- empleadores. Según la §8.4 de la especificación, el portal externo es:
--   - Empleadores (1010): solo Editar (sus datos; el acceso a cada empleador lo limita
--     core/accesos_empleadores.php). Sin Agregar, Inhabilitar, Habilitar ni "Ver todos".
--   - Estudios y vinculaciones (1011): Solicitar, Aprobar y Rechazar vínculo.
--   - DDJJ (1020), Volantes (1030) y Cuenta corriente (1032): todas sus funciones.
--   - Importación de DDJJ (1021): sí.
--   - Sin acceso: configuración del sindicato (1001-1006, incluye Medios de Cobro con los datos
--     de acceso a los bancos), Personas (1012, datos de todos los trabajadores), DDJJ de oficio
--     (1022), Rendiciones (1031), Fiscalización (1040, 1045), Actas (1041) y Planes (1042).
-- También corrige los nombres de las funciones ABM de la página 1011 (decían "Ver Volantes",
-- "Generar Volante"...).
--
-- Reanudable. Los perfiles internos no cambian.
-- ==============================================================================

SET NAMES utf8mb4;

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_funciones_no_externas;
CREATE TEMPORARY TABLE tmp_funciones_no_externas (pagina_funcion_id INT NOT NULL PRIMARY KEY) ENGINE=MEMORY;

INSERT INTO tmp_funciones_no_externas (pagina_funcion_id)
SELECT pf.pagina_funcion_id
FROM conf__paginas_funciones pf
WHERE pf.pagina_id IN (1001, 1002, 1003, 1004, 1005, 1006, 1012, 1022, 1031, 1040, 1041, 1042, 1045)
   OR (pf.pagina_id = 1010 AND pf.accion_js IN ('agregar', 'inactivar', 'habilitar', 'ver_todos_empleadores'))
   OR (pf.pagina_id = 1011 AND pf.accion_js IN ('agregar', 'editar', 'inactivar', 'habilitar'));

DELETE epf FROM conf__empresas_perfiles_funciones epf
INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id
INNER JOIN tmp_funciones_no_externas f ON f.pagina_funcion_id = epf.pagina_funcion_id
WHERE ep.perfil_id_base IN (604, 605, 606);

DELETE pfu FROM conf__perfiles_funciones pfu
INNER JOIN tmp_funciones_no_externas f ON f.pagina_funcion_id = pfu.pagina_funcion_id
WHERE pfu.perfil_id IN (604, 605, 606);

DROP TEMPORARY TABLE IF EXISTS tmp_funciones_no_externas;

-- Nombres de las funciones ABM de Estudios Contables (1011)
UPDATE conf__paginas_funciones SET nombre_funcion = 'Agregar'     WHERE pagina_id = 1011 AND accion_js = 'agregar';
UPDATE conf__paginas_funciones SET nombre_funcion = 'Editar'      WHERE pagina_id = 1011 AND accion_js = 'editar';
UPDATE conf__paginas_funciones SET nombre_funcion = 'Inhabilitar' WHERE pagina_id = 1011 AND accion_js = 'inactivar';
UPDATE conf__paginas_funciones SET nombre_funcion = 'Habilitar'   WHERE pagina_id = 1011 AND accion_js = 'habilitar';

COMMIT;

-- Verificación: páginas que ve cada perfil externo
SELECT ep.empresa_id, ep.perfil_id_base, pf.pagina_id, GROUP_CONCAT(pf.accion_js ORDER BY pf.pagina_funcion_id) AS funciones
FROM conf__empresas_perfiles_funciones epf
INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id
INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
WHERE ep.perfil_id_base IN (604, 605, 606) AND pf.pagina_id BETWEEN 1000 AND 1099
GROUP BY ep.empresa_id, ep.perfil_id_base, pf.pagina_id
ORDER BY ep.empresa_id, ep.perfil_id_base, pf.pagina_id;
