-- =============================================================================
-- Habilitar Asistente IA (páginas 139 y 639) en conf__paginas, funciones,
-- perfiles y parámetros de empresa. Compatible con MySQL y MariaDB.
-- =============================================================================

SET NAMES utf8mb4;

-- 1. Páginas en conf__paginas (módulo 2 = gestion, módulo 5 = gestion_normal)
INSERT INTO `conf__paginas` (
  `pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`,
  `padre_id`, `orden`, `tabla_id`, `requiere_bienes`, `requiere_gestion_completa`,
  `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`
) VALUES
(139, 2, 'Asistente IA', 'asistente_ia.php', 21, 'Asistente conversacional con IA: consultas del sistema, ventas, entidades, contabilidad y tesorería según perfil', 0, 50, NULL, 0, 0, 1, 1, 1, 0),
(639, 5, 'Asistente IA', 'asistente_ia.php', 21, 'Asistente conversacional con IA: consultas del sistema, ventas, entidades, contabilidad y tesorería según perfil', 0, 50, 0, 0, 0, 1, 1, 1, 0)
ON DUPLICATE KEY UPDATE
  `pagina` = VALUES(`pagina`),
  `url` = VALUES(`url`),
  `pagina_descripcion` = VALUES(`pagina_descripcion`),
  `padre_id` = VALUES(`padre_id`),
  `orden` = VALUES(`orden`),
  `tabla_estado_registro_id` = 1;

-- 2. Función de acceso en conf__paginas_funciones
INSERT INTO `conf__paginas_funciones` (
  `pagina_funcion_id`, `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`,
  `nombre_funcion`, `accion_js`, `descripcion`,
  `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`
) VALUES
(3527, 139, 21, 1, 1, 'Usar Asistente IA', 'consultar', '', 0, 0, 1, 1),
(8527, 639, 21, 1, 1, 'Usar Asistente IA', 'consultar', '', 0, 0, 1, 1)
ON DUPLICATE KEY UPDATE
  `nombre_funcion` = VALUES(`nombre_funcion`),
  `accion_js` = VALUES(`accion_js`),
  `tabla_estado_registro_id` = 1;

-- 3. Asignar la función a los perfiles activos de cada empresa (el asistente igual filtra
--    internamente las herramientas según los permisos reales de cada perfil)
INSERT IGNORE INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT ep.empresa_id, ep.empresa_perfil_id,
       CASE WHEN ep.modulo_id = 2 THEN 3527 ELSE 8527 END,
       1
FROM `conf__empresas_perfiles` ep
WHERE ep.modulo_id IN (2, 5) AND ep.tabla_estado_registro_id = 1;

-- 4. Habilitar el asistente IA en parámetros de empresa (empresa 6 - Developsam)
UPDATE `gestion__empresas_parametros`
SET `ia_asistente_habilitado` = 1
WHERE `empresa_id` = 6 AND `tabla_estado_registro_id` = 1;
