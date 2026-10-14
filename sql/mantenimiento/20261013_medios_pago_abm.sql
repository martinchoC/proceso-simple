-- ==============================================================================
-- Mantenimiento: ABM de Medios de Pago (gestion__medios_pago)
-- Fecha: 2026-10-13
-- Módulos: 2 (Gestión) y 5 (Gestión Normal)
-- ==============================================================================

-- 1. Registrar página en Gestión (módulo 2)
INSERT INTO `conf__paginas` (
    `pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `tabla_estado_registro_id`
) VALUES (
    120, 2, 'Medios de Pago', 'medios_pago.php', 119, 'Catálogo y configuración de medios de cobro y pago por empresa', 100, 85, 272, 1
) ON DUPLICATE KEY UPDATE 
    `pagina` = VALUES(`pagina`),
    `url` = VALUES(`url`),
    `icono_id` = VALUES(`icono_id`),
    `pagina_descripcion` = VALUES(`pagina_descripcion`),
    `padre_id` = VALUES(`padre_id`),
    `orden` = VALUES(`orden`),
    `tabla_id` = VALUES(`tabla_id`),
    `tabla_estado_registro_id` = 1;

-- 2. Registrar página en Gestión Normal (módulo 5)
INSERT INTO `conf__paginas` (
    `pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `tabla_estado_registro_id`
) VALUES (
    620, 5, 'Medios de Pago', 'medios_pago.php', 119, 'Catálogo y configuración de medios de cobro y pago por empresa', 600, 85, 272, 1
) ON DUPLICATE KEY UPDATE 
    `pagina` = VALUES(`pagina`),
    `url` = VALUES(`url`),
    `icono_id` = VALUES(`icono_id`),
    `pagina_descripcion` = VALUES(`pagina_descripcion`),
    `padre_id` = VALUES(`padre_id`),
    `orden` = VALUES(`orden`),
    `tabla_id` = VALUES(`tabla_id`),
    `tabla_estado_registro_id` = 1;

-- 3. Funciones estándar para página 120 (Gestión)
INSERT IGNORE INTO `conf__paginas_funciones` (`pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`) VALUES
(120, 42, 3, 1, 'Agregar', 'agregar', 0, 1, 0, 1),
(120, 37, 1, 1, 'Visualizar', 'visualizar', 0, 1, 1, 1),
(120, 40, 1, 1, 'Editar', 'editar', 1, 1, 10, 1),
(120, 45, 4, 1, 'Inactivar', 'inactivar', 1, 2, 20, 1),
(120, 46, 3, 1, 'Habilitar', 'habilitar', 2, 1, 100, 1);

-- 4. Funciones estándar para página 620 (Gestión Normal)
INSERT IGNORE INTO `conf__paginas_funciones` (`pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`) VALUES
(620, 42, 3, 1, 'Agregar', 'agregar', 0, 1, 0, 1),
(620, 37, 1, 1, 'Visualizar', 'visualizar', 0, 1, 1, 1),
(620, 40, 1, 1, 'Editar', 'editar', 1, 1, 10, 1),
(620, 45, 4, 1, 'Inactivar', 'inactivar', 1, 2, 20, 1),
(620, 46, 3, 1, 'Habilitar', 'habilitar', 2, 1, 100, 1);

-- 5. Replicar asignaciones a perfiles de usuarios
INSERT IGNORE INTO `conf__empresas_perfiles_funciones` (`empresa_id`, `empresa_perfil_id`, `pagina_funcion_id`, `asignado`)
SELECT DISTINCT epf.empresa_id, epf.empresa_perfil_id, pf_nueva.pagina_funcion_id, 1
FROM conf__empresas_perfiles_funciones epf
JOIN conf__paginas_funciones pf_existente ON epf.pagina_funcion_id = pf_existente.pagina_funcion_id AND pf_existente.pagina_id IN (105, 106, 605, 606)
CROSS JOIN conf__paginas_funciones pf_nueva ON pf_nueva.pagina_id IN (120, 620)
WHERE epf.asignado = 1;
