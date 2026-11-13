-- Estudio contable: seguimiento de inspecciones / fiscalizaciones de los clientes (F3).
-- Cabecera por inspección + hitos (notificaciones, requerimientos, plazos, resoluciones, pagos).
-- Un hito con plazo puede generar una tarea RACI (estudio__tareas.tarea_id guardado en el hito).
-- El estado del proceso es propio (estado_proceso); tabla_estado_registro_id solo distingue vigente (1) de anulada (38).
-- Reanudable.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `estudio__inspecciones` (
  `inspeccion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant del estudio',
  `cliente_id` bigint(20) unsigned NOT NULL COMMENT 'FK estudio__clientes',
  `organismo` enum('ARCA','COMARB','PROVINCIAL','MUNICIPAL','SINDICATO','TRABAJO','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ARCA',
  `tipo` enum('FISCALIZACION','VERIFICACION','REQUERIMIENTO','INTIMACION','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FISCALIZACION',
  `numero_acta` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Acta, orden de intervención o expediente',
  `impuesto_obligacion_id` int(10) unsigned DEFAULT NULL COMMENT 'FK estudio__impuestos_obligaciones (si es de un impuesto puntual)',
  `periodo_desde` int(10) unsigned DEFAULT NULL COMMENT 'YYYYMM',
  `periodo_hasta` int(10) unsigned DEFAULT NULL COMMENT 'YYYYMM',
  `f_inicio` date NOT NULL,
  `f_cierre` date DEFAULT NULL,
  `estado_proceso` enum('ABIERTA','EN_DESCARGO','EN_APELACION','PLAN_PAGOS','CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ABIERTA',
  `monto_reclamado` decimal(16,2) NOT NULL DEFAULT 0.00,
  `monto_aceptado` decimal(16,2) NOT NULL DEFAULT 0.00,
  `monto_pagado` decimal(16,2) NOT NULL DEFAULT 0.00,
  `usuario_r_id` int(10) unsigned DEFAULT NULL COMMENT 'Responsable del estudio',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '1 vigente, 38 anulada',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`inspeccion_id`),
  KEY `idx_empresa_cliente` (`empresa_id`,`cliente_id`),
  KEY `idx_empresa_estado` (`empresa_id`,`estado_proceso`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__inspecciones_hitos` (
  `hito_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `inspeccion_id` bigint(20) unsigned NOT NULL,
  `tipo_hito` enum('NOTIFICACION','REQUERIMIENTO','PRESENTACION','AUDIENCIA','RESOLUCION','PAGO','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OTRO',
  `f_hito` date NOT NULL,
  `f_limite` date DEFAULT NULL COMMENT 'Plazo a cumplir (respuesta, presentación, pago)',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `importe` decimal(16,2) DEFAULT NULL,
  `cumplido` tinyint(1) NOT NULL DEFAULT 0,
  `tarea_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK estudio__tareas si se generó la tarea',
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`hito_id`),
  KEY `idx_inspeccion` (`empresa_id`,`inspeccion_id`,`f_hito`),
  KEY `idx_plazo` (`empresa_id`,`cumplido`,`f_limite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Página 418 "Inspecciones" bajo "Clientes y Vencimientos" (400); funciones 3551 Acceder (3450–3452 los usa la página 131), 3552 Cargar, 3553 Modificar.
-- VERIFICAR ANTES que 418 y 3551–3553 estén libres:
--   SELECT pagina_id, url FROM conf__paginas WHERE pagina_id = 418;
--   SELECT pagina_funcion_id, pagina_id FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3551 AND 3553;
-- Si alguno está ocupado por otra cosa el INSERT falla por clave duplicada (a propósito: no se pisa nada); en ese caso
-- cambiar los ids acá y en inspecciones.php / inspecciones_ajax.php.
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT 418, 4, 'Inspecciones', 'inspecciones.php', 83,
       'Seguimiento de inspecciones y fiscalizaciones de los clientes: actas, plazos, descargos, montos y tareas', 400, 12,
       0, 0, 1, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE url = 'inspecciones.php');

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, 418, 0, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (
    SELECT 3551 AS id, 'Acceder' AS nombre, 'consultar' AS accion, 'Acceso a la página' AS descr, 1 AS orden
    UNION ALL SELECT 3552, 'Cargar inspecciones', 'agregar', 'Alta de inspecciones y de hitos', 2
    UNION ALL SELECT 3553, 'Modificar inspecciones', 'editar', 'Editar, cerrar, anular y marcar hitos como cumplidos', 3
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = 418 AND f.accion_js = v.accion);

-- Perfiles que hoy tienen Acceder / Cargar en Mini-Gestión (3448 / 3449) heredan las nuevas funciones
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, m.nueva, 1, NOW()
FROM conf__perfiles_funciones pf
JOIN (SELECT 3448 AS vieja, 3551 AS nueva UNION ALL SELECT 3449, 3552 UNION ALL SELECT 3449, 3553) m ON m.vieja = pf.pagina_funcion_id
WHERE pf.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = m.nueva);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, m.nueva, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
JOIN (SELECT 3448 AS vieja, 3551 AS nueva UNION ALL SELECT 3449, 3552 UNION ALL SELECT 3449, 3553) m ON m.vieja = epf.pagina_funcion_id
WHERE epf.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = m.nueva);
