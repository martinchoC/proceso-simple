-- Registrar página de gestión de accesos por perfil en conf__paginas
INSERT INTO `conf__paginas` 
  (`pagina_id`, `modulo_id`, `padre_id`, `pagina`, `url`, `orden`, `tabla_id`, `icono_id`, `tabla_estado_registro_id`)
SELECT 72, 2, 37, 'Sucursales - Accesos por Perfil', 'accesos_perfiles.php', 418, 0, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `conf__paginas` WHERE `pagina_id` = 72 OR `url` = 'accesos_perfiles.php');

-- Dar permiso a los perfiles Administrador (base 3) sobre la página 72 si aplica
INSERT INTO `conf__empresas_perfiles_funciones` (`empresa_perfil_id`, `pagina_funcion_id`, `asignado`, `tabla_estado_registro_id`)
SELECT ep.empresa_perfil_id, 0, 1, 1
FROM conf__empresas_perfiles ep
WHERE ep.modulo_id = 2 AND ep.perfil_id_base = 3
ON DUPLICATE KEY UPDATE asignado = 1;
