-- Libros societarios: libros rubricados, actas y demás actos societarios, asistentes / firmantes e inscripción registral.
-- Código único en core/libros_societarios/ (service + ajax), vista templates/libros_societarios/, JS assets/js/libros-societarios.js.
-- Mismas tablas para los tres módulos:
--   Gestión (2, página 140) y Gestión Normal (5, página 640): libros propios de la empresa (estudio_cliente_id = 0).
--   Estudio Contable (4, página 424): libros de los clientes del estudio (estudio_cliente_id = estudio__clientes.cliente_id).
-- Sin motor de estados: el acta lleva estado_acta (BORRADOR / TRANSCRIPTA) e inscripcion_estado; tabla_estado_registro_id
-- 1 vigente / 38 anulada (actas) y 1 activo / 2 inactivo (libros). Botones por data-funcion + permisos_exigir_accion.
-- Ids libres verificados el 2026-11-08: tablas 453–455, páginas 140 / 640 / 424, funciones 3562–3569 y 8562–8565.
-- Reanudable (CREATE IF NOT EXISTS + WHERE NOT EXISTS). Corre en MariaDB 10.4 y MySQL 8.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `gestion__soc_libros` (
  `libro_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant',
  `estudio_cliente_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '0 = libros propios de la empresa; > 0 = cliente del estudio',
  `tipo_libro` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código de LSOC_TIPOS_LIBRO',
  `numero_libro` smallint(5) unsigned NOT NULL DEFAULT 1,
  `denominacion` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medio` enum('PAPEL','DIGITAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PAPEL',
  `organismo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Quien rubricó: IGJ, DPPJ, registro público',
  `rubrica_numero` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rubrica_fecha` date DEFAULT NULL,
  `folio_desde` int(10) unsigned DEFAULT 1,
  `folio_hasta` int(10) unsigned DEFAULT NULL COMMENT 'NULL en libros digitales',
  `f_inicio_uso` date DEFAULT NULL,
  `f_cierre` date DEFAULT NULL,
  `estado_libro` enum('EN_USO','COMPLETO','CERRADO','EXTRAVIADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EN_USO',
  `ubicacion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dónde se guarda el libro físico',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '1 activo, 2 inactivo',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`libro_id`),
  KEY `idx_soc_libros_ambito` (`empresa_id`,`estudio_cliente_id`,`tipo_libro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__soc_actas` (
  `acta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `estudio_cliente_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `tipo_acto` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código de LSOC_TIPOS_ACTO',
  `f_acto` date NOT NULL,
  `hora_acto` time DEFAULT NULL,
  `lugar` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_convocatoria` date DEFAULT NULL,
  `es_unanime` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Asamblea unánime sin convocatoria (art. 237 LGS)',
  `quorum_pct` decimal(7,4) DEFAULT NULL COMMENT '% del capital / votos presentes',
  `materias` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Códigos de LSOC_MATERIAS separados por coma',
  `orden_del_dia` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resoluciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ejercicio_cierre` date DEFAULT NULL COMMENT 'Cierre del ejercicio cuyos estados contables se aprueban',
  `mandato_hasta` date DEFAULT NULL COMMENT 'Vencimiento del mandato de las autoridades designadas',
  `estado_acta` enum('BORRADOR','TRANSCRIPTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR',
  `libro_id` int(10) unsigned DEFAULT NULL,
  `numero_acta` int(10) unsigned DEFAULT NULL COMMENT 'N° de acta o de asiento en el libro',
  `folio_desde` int(10) unsigned DEFAULT NULL,
  `folio_hasta` int(10) unsigned DEFAULT NULL,
  `f_transcripcion` date DEFAULT NULL,
  `inscripcion_estado` enum('NO_REQUIERE','PENDIENTE','EN_TRAMITE','OBSERVADA','INSCRIPTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NO_REQUIERE',
  `inscripcion_organismo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inscripcion_tramite` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_presentacion` date DEFAULT NULL,
  `f_inscripcion` date DEFAULT NULL,
  `inscripcion_datos` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N° correlativo, libro, tomo',
  `archivo_ruta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'uploads/libros_societarios/<empresa>/ (sin acceso web)',
  `archivo_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_anulacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_anulacion` datetime DEFAULT NULL,
  `usuario_anulacion_id` int(10) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '1 vigente, 38 anulada',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`acta_id`),
  KEY `idx_soc_actas_ambito` (`empresa_id`,`estudio_cliente_id`,`f_acto`),
  KEY `idx_soc_actas_libro` (`libro_id`,`numero_acta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gestion__soc_actas_personas` (
  `persona_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `acta_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caracter` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OTRO' COMMENT 'Código de LSOC_CARACTERES',
  `cargo_designado` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cargo para el que se lo designa en este acto',
  `participacion` decimal(18,4) DEFAULT NULL COMMENT 'Acciones / cuotas / votos',
  `participacion_pct` decimal(7,4) DEFAULT NULL,
  `firma` tinyint(1) NOT NULL DEFAULT 0,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`persona_id`),
  KEY `idx_soc_personas_acta` (`empresa_id`,`acta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de tablas (ids explícitos del bloque 423–452 → siguientes libres)
INSERT INTO conf__tablas (tabla_id, tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_patron_id, tabla_estado_registro_id)
SELECT v.id, v.nombre, v.descr, 2, v.tipo, 1, 1
FROM (
    SELECT 453 AS id, 'gestion__soc_libros' AS nombre, 'Libros societarios rubricados (propios o de clientes del estudio)' AS descr, 1 AS tipo
    UNION ALL SELECT 454, 'gestion__soc_actas', 'Actas y actos societarios: transcripción al libro e inscripción registral', 2
    UNION ALL SELECT 455, 'gestion__soc_actas_personas', 'Asistentes, firmantes y autoridades designadas de cada acta', 1
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas t WHERE t.tabla_nombre = v.nombre);

-- Páginas: Gestión 140 y Gestión Normal 640 bajo Contabilidad; Estudio 424 bajo Gestión de Clientes (421)
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT v.id, v.modulo, 'Libros Societarios', 'libros_societarios.php', 27, v.descr, v.padre, v.orden, 454, 0, 1, 1, 0
FROM (
    SELECT 140 AS id, 2 AS modulo, 83 AS padre, 180 AS orden,
           'Libros rubricados, actas de asamblea, directorio y reuniones de socios, autoridades e inscripciones' AS descr
    UNION ALL SELECT 640, 5, 583, 180,
           'Libros rubricados, actas de asamblea, directorio y reuniones de socios, autoridades e inscripciones'
    UNION ALL SELECT 424, 4, 421, 7,
           'Libros societarios de los clientes: actas, autoridades, vencimientos de mandatos e inscripciones'
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.url = 'libros_societarios.php' AND p.modulo_id = v.modulo);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina, 0, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (
              SELECT 3562 AS id, 140 AS pagina, 'Acceder' AS nombre, 'consultar' AS accion, 'Ver libros y actas' AS descr, 1 AS orden
    UNION ALL SELECT 3563, 140, 'Cargar', 'agregar', 'Alta de libros y de actas', 2
    UNION ALL SELECT 3564, 140, 'Modificar', 'editar', 'Editar, transcribir, inscripción y adjuntos', 3
    UNION ALL SELECT 3565, 140, 'Anular', 'inhabilitar', 'Anular actas e inactivar libros', 4
    UNION ALL SELECT 8562, 640, 'Acceder', 'consultar', 'Ver libros y actas', 1
    UNION ALL SELECT 8563, 640, 'Cargar', 'agregar', 'Alta de libros y de actas', 2
    UNION ALL SELECT 8564, 640, 'Modificar', 'editar', 'Editar, transcribir, inscripción y adjuntos', 3
    UNION ALL SELECT 8565, 640, 'Anular', 'inhabilitar', 'Anular actas e inactivar libros', 4
    UNION ALL SELECT 3566, 424, 'Acceder', 'consultar', 'Ver libros y actas de los clientes', 1
    UNION ALL SELECT 3567, 424, 'Cargar', 'agregar', 'Alta de libros y de actas', 2
    UNION ALL SELECT 3568, 424, 'Modificar', 'editar', 'Editar, transcribir, inscripción y adjuntos', 3
    UNION ALL SELECT 3569, 424, 'Anular', 'inhabilitar', 'Anular actas e inactivar libros', 4
) v
WHERE EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina AND p.url = 'libros_societarios.php')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = v.pagina AND f.accion_js = v.accion)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.id);

-- Perfiles base: Administrador y Contable e impositivo todo; Consulta solo Acceder.
-- Estudio: heredan de Inspecciones (3551 → Acceder, 3552 → Cargar, 3553 → Modificar y Anular).
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT v.perfil, v.funcion, 1, NOW()
FROM (
              SELECT 201 AS perfil, 3562 AS funcion UNION ALL SELECT 201, 3563 UNION ALL SELECT 201, 3564 UNION ALL SELECT 201, 3565
    UNION ALL SELECT 205, 3562 UNION ALL SELECT 205, 3563 UNION ALL SELECT 205, 3564 UNION ALL SELECT 205, 3565
    UNION ALL SELECT 208, 3562
    UNION ALL SELECT 501, 8562 UNION ALL SELECT 501, 8563 UNION ALL SELECT 501, 8564 UNION ALL SELECT 501, 8565
    UNION ALL SELECT 505, 8562 UNION ALL SELECT 505, 8563 UNION ALL SELECT 505, 8564 UNION ALL SELECT 505, 8565
    UNION ALL SELECT 508, 8562
) v
WHERE EXISTS (SELECT 1 FROM conf__perfiles p WHERE p.perfil_id = v.perfil)
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = v.funcion)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = v.perfil AND x.pagina_funcion_id = v.funcion);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT DISTINCT pf.perfil_id, m.nueva, 1, NOW()
FROM conf__perfiles_funciones pf
JOIN (SELECT 3551 AS vieja, 3566 AS nueva UNION ALL SELECT 3552, 3567 UNION ALL SELECT 3553, 3568 UNION ALL SELECT 3553, 3569) m
  ON m.vieja = pf.pagina_funcion_id
WHERE pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.nueva)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = m.nueva);

-- Perfiles de las empresas (la única fuente que lee core/permisos.php)
-- Gestión / Gestión Normal: perfiles de administración, los copiados de Administrador / Contable y los que cierran
-- períodos contables (3482 / 8482) reciben todo; los copiados de Consulta, solo Acceder.
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, f.pagina_funcion_id, 1, NOW()
FROM conf__empresas_perfiles ep
JOIN conf__paginas_funciones f ON f.pagina_id = IF(ep.modulo_id = 5, 640, 140)
WHERE ep.modulo_id IN (2, 5)
  AND ep.tabla_estado_registro_id = 1
  AND (ep.empresa_perfil_nombre LIKE 'Administrador%' OR ep.empresa_perfil_nombre = 'Acceso total'
       OR ep.perfil_id_base IN (201, 205, 501, 505)
       OR EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones c
                  WHERE c.empresa_perfil_id = ep.empresa_perfil_id AND c.pagina_funcion_id IN (3482, 8482) AND c.asignado = 1))
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = f.pagina_funcion_id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT ep.empresa_id, ep.empresa_perfil_id, IF(ep.modulo_id = 5, 8562, 3562), 1, NOW()
FROM conf__empresas_perfiles ep
WHERE ep.modulo_id IN (2, 5)
  AND ep.tabla_estado_registro_id = 1
  AND ep.perfil_id_base IN (208, 508)
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = IF(ep.modulo_id = 5, 8562, 3562))
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = IF(ep.modulo_id = 5, 8562, 3562));

-- Estudio: heredan de Inspecciones
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT DISTINCT epf.empresa_id, epf.empresa_perfil_id, m.nueva, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
JOIN (SELECT 3551 AS vieja, 3566 AS nueva UNION ALL SELECT 3552, 3567 UNION ALL SELECT 3553, 3568 UNION ALL SELECT 3553, 3569) m
  ON m.vieja = epf.pagina_funcion_id
WHERE epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.nueva)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = m.nueva);
