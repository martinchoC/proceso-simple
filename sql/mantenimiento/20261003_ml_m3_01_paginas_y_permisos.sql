-- =====================================================================
-- Matriz Legal - M3: PÁGINAS, FUNCIONES, PERMISOS Y SEMILLAS NORMATIVAS
-- Archivo: sql/mantenimiento/20261003_ml_m3_01_paginas_y_permisos.sql
-- =====================================================================

START TRANSACTION;

-- 1. PÁGINAS DEL MÓDULO 3 (Agrupador 311: Normas y Soporte)
INSERT INTO conf__paginas 
(pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
VALUES
(311, 3, 'Normas y Soporte', '#', 20, 'Gestión del acervo normativo y procedimental', 0, 25, 228, 1, 1, 0),
(312, 3, 'Catálogos Normativos', 'normas_catalogos.php', 9, 'Organismos, tipos, jerarquías y relaciones', 311, 10, 226, 1, 1, 0),
(313, 3, 'Matriz de Normas', 'normas.php', 20, 'Registro de normas, versiones y vínculos', 311, 20, 228, 2, 1, 0),
(314, 3, 'Conflictos Normativos', 'normas_conflictos.php', 23, 'Tratamiento y dictamen de normas incompatibles', 311, 30, 232, 3, 1, 0),
(315, 3, 'Procedimientos y Soporte', 'procedimientos.php', 14, 'Manuales operativos e instrucciones de tareas', 311, 40, 233, 2, 1, 0),
(316, 3, 'Accesos Confidenciales', 'accesos_confidenciales.php', 30, 'Autorizaciones reservadas y log de auditoría', 311, 50, 236, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    pagina = VALUES(pagina),
    url = VALUES(url),
    icono_id = VALUES(icono_id),
    pagina_descripcion = VALUES(pagina_descripcion),
    padre_id = VALUES(padre_id),
    orden = VALUES(orden),
    tabla_id = VALUES(tabla_id);

-- 2. FUNCIONES DE PÁGINA (conf__paginas_funciones: Bloque 3071-3110)
INSERT INTO conf__paginas_funciones 
(pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES
-- Página 312: Catálogos Normativos
(3071, 312, 1, 1, 1, 'Guardar Organismo', 'guardar_organismo', 'Crear o modificar organismo emisor', 0, 1, 10, 1),
(3072, 312, 1, 1, 1, 'Guardar Tipo Norma', 'guardar_norma_tipo', 'Crear o modificar clasificación de norma', 0, 1, 20, 1),
(3073, 312, 1, 1, 1, 'Guardar Jerarquía', 'guardar_jerarquia', 'Crear o modificar nivel jerárquico', 0, 1, 30, 1),

-- Página 313: Matriz de Normas (Proceso Corto 2045-2048)
(3075, 313, 1, 1, 1, 'Crear Norma', 'crear_norma', 'Registrar nueva norma en borrador', 0, 2045, 10, 1),
(3076, 313, 1, 2, 2, 'Enviar a Revisión', 'enviar_revision_norma', 'Pasar norma a revisión legal', 2045, 2046, 20, 1),
(3077, 313, 1, 3, 2, 'Aprobar y Publicar', 'aprobar_norma', 'Confirmar vigencia oficial de la norma', 2046, 2047, 30, 1),
(3078, 313, 1, 4, 3, 'Derogar Norma', 'derogar_norma', 'Cesar vigencia por derogación', 2047, 2048, 40, 1),
(3079, 313, 1, 1, 1, 'Agregar Versión', 'agregar_version_norma', 'Cargar nueva versión documental o texto', 0, 2049, 50, 1),
(3080, 313, 1, 1, 1, 'Vincular Relación', 'vincular_relacion', 'Establecer vínculo con otra norma', 0, 1, 60, 1),

-- Página 314: Conflictos Normativos (Proceso Largo 2084-2086)
(3085, 314, 1, 1, 1, 'Registrar Conflicto', 'registrar_conflicto', 'Declarar incompatibilidad entre normas', 0, 2084, 10, 1),
(3086, 314, 1, 2, 2, 'Escalar a Legales', 'escalar_conflicto', 'Remitir a abogado responsable', 2084, 2085, 20, 1),
(3087, 314, 1, 3, 2, 'Resolver Conflicto', 'resolver_conflicto', 'Emitir dictamen vinculante', 2085, 2086, 30, 1),

-- Página 315: Procedimientos y Soporte
(3090, 315, 1, 1, 1, 'Guardar Procedimiento', 'guardar_procedimiento', 'Crear o modificar procedimiento de soporte', 0, 2053, 10, 1),
(3091, 315, 1, 3, 2, 'Aprobar Procedimiento', 'aprobar_procedimiento', 'Aprobar versión de procedimiento', 2054, 2055, 20, 1),

-- Página 316: Accesos Confidenciales
(3095, 316, 1, 1, 1, 'Autorizar Acceso', 'autorizar_acceso', 'Habilitar acceso reservado a perfil', 0, 1, 10, 1)
ON DUPLICATE KEY UPDATE 
    nombre_funcion = VALUES(nombre_funcion),
    accion_js = VALUES(accion_js),
    descripcion = VALUES(descripcion);

-- 3. PERMISOS EN PERFILES BASE (conf__perfiles_funciones)
-- 301 (Admin Matriz): Acceso total
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3071 AND 3095
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 302 (Gestor Normativo): Catalogación, carga de normas, versiones, relaciones y procedimientos
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(302, 3071, 1), (302, 3072, 1), (302, 3073, 1), (302, 3075, 1), (302, 3076, 1),
(302, 3079, 1), (302, 3080, 1), (302, 3085, 1), (302, 3086, 1), (302, 3090, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 303 (Área Legal): Aprobación, dictamen de conflictos, derogación
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
VALUES
(303, 3071, 1), (303, 3075, 1), (303, 3076, 1), (303, 3077, 1), (303, 3078, 1),
(303, 3080, 1), (303, 3085, 1), (303, 3086, 1), (303, 3087, 1)
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 4. PERMISOS EN PERFILES DE EMPRESA 2 (Casa Lucho)
INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 301, pagina_funcion_id, 1 FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3071 AND 3095
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 302, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 302 AND pf.pagina_funcion_id BETWEEN 3071 AND 3095
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
SELECT 303, pf.pagina_funcion_id, 1 
FROM conf__perfiles_funciones pf 
WHERE pf.perfil_id = 303 AND pf.pagina_funcion_id BETWEEN 3071 AND 3095
ON DUPLICATE KEY UPDATE asignado = VALUES(asignado);

-- 5. SEMILLAS DE CATÁLOGOS NORMATIVOS (M3)

-- 5.1 ml__normas_tipos
INSERT INTO ml__normas_tipos (norma_tipo_id, codigo, nombre, es_legal, genera_obligaciones, tabla_estado_registro_id, creado_en)
VALUES
(1, 'LEY', 'Ley Nacional / Federal', 1, 1, 1, NOW()),
(2, 'DECRETO', 'Decreto Reglamentario del Ejecutivo', 1, 1, 1, NOW()),
(3, 'RESOLUCION', 'Resolución Ministerial / Organismo', 1, 1, 1, NOW()),
(4, 'ORDENANZA', 'Ordenanza Municipal / Local', 1, 1, 1, NOW()),
(5, 'POLITICA', 'Política Corporativa del Grupo', 0, 1, 1, NOW()),
(6, 'ESTANDAR', 'Estándar Internacional (ISO, etc.)', 0, 1, 1, NOW()),
(7, 'CONVENIO', 'Convenio Internacional (OIT, OMI, etc.)', 1, 1, 1, NOW()),
(8, 'CONTRATO', 'Convenio Colectivo o Contrato Marco', 0, 1, 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), es_legal = VALUES(es_legal), genera_obligaciones = VALUES(genera_obligaciones);

-- 5.2 ml__normas_jerarquias
INSERT INTO ml__normas_jerarquias (norma_jerarquia_id, nombre, nivel, tabla_estado_registro_id, creado_en)
VALUES
(1, 'Tratados y Convenios Internacionales con Jerarquía Constitucional', 1, 1, NOW()),
(2, 'Leyes Nacionales y Códigos de Fondo', 2, 1, NOW()),
(3, 'Decretos Reglamentarios del Poder Ejecutivo Nacional', 3, 1, NOW()),
(4, 'Resoluciones y Disposiciones de Autoridades de Aplicación', 4, 1, NOW()),
(5, 'Leyes Provinciales, Decretos y Ordenanzas Municipales', 5, 1, NOW()),
(6, 'Políticas Corporativas y Estándares del Grupo', 6, 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), nivel = VALUES(nivel);

-- 5.3 ml__normas_relaciones_tipos
INSERT INTO ml__normas_relaciones_tipos (norma_relacion_tipo_id, codigo, nombre, tabla_estado_registro_id, creado_en)
VALUES
(1, 'MODIFICA', 'Modifica (artículos o texto parcial)', 1, NOW()),
(2, 'DEROGA', 'Deroga totalmente la norma previa', 1, NOW()),
(3, 'REGLAMENTA', 'Reglamenta aspectos operativos de la ley', 1, NOW()),
(4, 'COMPLEMENTA', 'Complementa exigencias sin alterar texto', 1, NOW()),
(5, 'REEMPLAZA', 'Reemplaza y sustituye en su totalidad', 1, NOW()),
(6, 'DERIVA_DE', 'Deriva de una norma de jerarquía superior', 1, NOW()),
(7, 'VERIFICA', 'Verifica o audita el cumplimiento', 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- 5.4 ml__organismos
INSERT INTO ml__organismos (organismo_id, nombre, territorio_id, tabla_estado_registro_id, creado_en)
VALUES
(1, 'OIT - Organización Internacional del Trabajo', 1, 1, NOW()),
(2, 'ISO - Organización Internacional de Normalización', 1, 1, NOW()),
(3, 'OMI - Organización Marítima Internacional', 1, 1, NOW()),
(4, 'Congreso de la Nación Argentina', 2, 1, NOW()),
(5, 'SRT - Superintendencia de Riesgos del Trabajo (Argentina)', 2, 1, NOW()),
(6, 'PNA - Prefectura Naval Argentina', 2, 1, NOW()),
(7, 'Poder Legislativo del Uruguay', 3, 1, NOW()),
(8, 'DINAMA - Dirección Nacional de Medio Ambiente (Uruguay)', 3, 1, NOW())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), territorio_id = VALUES(territorio_id);

COMMIT;
