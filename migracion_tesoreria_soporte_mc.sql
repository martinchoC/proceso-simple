-- =============================================================================
-- Comprobante soporte de Tesorería (MC) con ciclo de vida completo:
-- Borrador (3) -> Pendiente de Aprobación (4) -> Aprobar/Confirmar (5) -> Anular (38)
-- Compatible con MySQL y MariaDB (idempotente).
-- =============================================================================

SET NAMES utf8mb4;

-- 1. Tabla soporte de comprobantes MC (almacena borrador/aprobación antes de impactar caja/contabilidad)
CREATE TABLE IF NOT EXISTS `gestion__tesoreria_comprobantes_soporte` (
  `soporte_id` INT(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` INT(11) NOT NULL,
  `comprobante_id` BIGINT(20) UNSIGNED NOT NULL,
  `tipo_movimiento` ENUM('INGRESO','EGRESO') NOT NULL,
  `tesoreria_cuenta_id` INT(11) NOT NULL,
  `cont_cuenta_contrapartida_id` INT(11) NOT NULL,
  `entidad_id` INT(11) DEFAULT NULL,
  `usuario_aprobacion_id` INT(11) DEFAULT NULL,
  `fecha_aprobacion` DATETIME DEFAULT NULL,
  `observacion_aprobacion` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`soporte_id`),
  UNIQUE KEY `uq_teso_soporte_comp` (`comprobante_id`),
  KEY `idx_teso_soporte_emp` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Funciones del ciclo de vida en tesoreria_movimientos.php (páginas 108 y 608)
INSERT INTO `conf__paginas_funciones` (
  `pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`,
  `nombre_funcion`, `accion_js`, `descripcion`,
  `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`
)
SELECT p.pagina_id, f.icono_id, f.color_id, f.funcion_estandar_id,
       f.nombre_funcion, f.accion_js, f.descripcion,
       f.origen, f.destino, f.orden, 1
FROM (SELECT 108 AS pagina_id UNION ALL SELECT 608) p
CROSS JOIN (
  SELECT 21 AS icono_id, 1 AS color_id, 1 AS funcion_estandar_id, 'Visualizar / Imprimir' AS nombre_funcion, 'visualizar' AS accion_js, 'Ver e imprimir comprobante soporte' AS descripcion, 0 AS origen, 0 AS destino, 10 AS orden
  UNION ALL SELECT 21, 1, 1, 'Editar Borrador',      'editar',    'Modificar comprobante en borrador',       3, 3, 20
  UNION ALL SELECT 21, 1, 1, 'Confirmar Movimiento', 'confirmar', 'Confirmar borrador e impactar tesorería', 3, 5, 30
  UNION ALL SELECT 21, 1, 1, 'Aprobar Movimiento',   'aprobar',   'Aprobar comprobante pendiente',           4, 5, 40
  UNION ALL SELECT 21, 1, 1, 'Rechazar Movimiento',  'rechazar',  'Devolver comprobante a borrador',         4, 3, 50
  UNION ALL SELECT 21, 1, 1, 'Eliminar Borrador',    'eliminar',  'Eliminar comprobante en borrador',        3, 6, 60
) f
WHERE EXISTS (SELECT 1 FROM `conf__paginas` cp WHERE cp.pagina_id = p.pagina_id)
  AND NOT EXISTS (
    SELECT 1 FROM `conf__paginas_funciones` pf
    WHERE pf.pagina_id = p.pagina_id AND pf.accion_js = f.accion_js AND pf.tabla_estado_registro_id = 1
  );

-- 3. Asignar automáticamente las nuevas funciones a los perfiles que ya tienen 'agregar' en tesoreria_movimientos
INSERT IGNORE INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT DISTINCT epf.empresa_id, epf.empresa_perfil_id, pf_new.pagina_funcion_id, 1
FROM `conf__empresas_perfiles_funciones` epf
JOIN `conf__paginas_funciones` pf_old ON pf_old.pagina_funcion_id = epf.pagina_funcion_id
JOIN `conf__paginas_funciones` pf_new ON pf_new.pagina_id = pf_old.pagina_id
WHERE pf_old.pagina_id IN (108, 608)
  AND pf_old.accion_js = 'agregar'
  AND epf.asignado = 1
  AND pf_new.accion_js IN ('visualizar', 'editar', 'confirmar', 'aprobar', 'rechazar', 'eliminar')
  AND pf_new.tabla_estado_registro_id = 1;
