-- =====================================================================
-- Stock mínimo / máximo por boca + alertas de reposición - script 02:
-- TABLAS, ESTADOS, PÁGINAS, FUNCIONES Y PERMISOS
-- 2026-10-08
--
-- Requiere 20261008_stock_niveles_01_ddl.sql.
--
-- conf__tablas (módulo 2):
--   429 gestion__stock_niveles  tipo 1 Diccionario
--   430 gestion__stock_alertas  tipo 2 Proceso Corto
--
-- conf__tablas_estados_registros (hueco libre 2183-2212, entre tesorería y
-- estudio). estado_registro_id = id GENÉRICO, como el resto de gestion:
--   2183 429/1  Activo       inicial
--   2184 429/2  Inactivo
--   2185 430/17 Pendiente    inicial
--   2186 430/18 En gestión
--   2187 430/21 Repuesta     final (solo automático: stock >= mínimo)
--   2188 430/26 Descartada   final
--
-- conf__paginas (menú Stock = 44):
--   112 Stock - Mínimos y Máximos   stock_niveles.php   tabla 429
--   113 Stock - Alertas Reposición  stock_alertas.php   tabla 430
--
-- conf__paginas_funciones:
--   112: 3354 Agregar 0->1, 3355 Copiar de otra boca 0->1, 3356 Editar 1->1,
--        3357 Inhabilitar 1->2, 3358 Habilitar 2->1
--   113: 3359 Tomar 17->18, 3360 Descartar 17->26, 3361 Descartar 18->26
--   (21 Repuesta no tiene botón: la asigna stockEvaluarNivel()).
--
-- Permisos: se copian de "Agregar" de Ajustes de Stock (pagina_funcion_id 282)
-- en conf__perfiles_funciones y conf__empresas_perfiles_funciones (la que lee
-- core/permisos.php). Ajustar después desde funciones por perfil.
--
-- Idempotente. Corta si los ids están ocupados por otra cosa. Ambos motores.
-- =====================================================================

DROP PROCEDURE IF EXISTS `_sn02_assert`;
DELIMITER $$
CREATE PROCEDURE `_sn02_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 0) Controles previos
-- ---------------------------------------------------------------------
CALL `_sn02_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_sn02_assert`((SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME IN ('gestion__stock_niveles', 'gestion__stock_alertas')) = 2,
                    'Faltan las tablas: correr antes 20261008_stock_niveles_01_ddl.sql');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__tablas
                     WHERE (tabla_id = 429 AND tabla_nombre <> 'gestion__stock_niveles')
                        OR (tabla_id = 430 AND tabla_nombre <> 'gestion__stock_alertas')) = 0,
                    'conf__tablas: los ids 429/430 están ocupados por otra tabla');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__tablas_estados_registros
                     WHERE tabla_estado_registro_id BETWEEN 2183 AND 2188
                       AND tabla_id NOT IN (429, 430)) = 0,
                    'conf__tablas_estados_registros: los ids 2183-2188 están ocupados por otra tabla');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE (pagina_id = 112 AND url <> 'stock_niveles.php')
                        OR (pagina_id = 113 AND url <> 'stock_alertas.php')) = 0,
                    'conf__paginas: los ids 112/113 están ocupados por otra página');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE (url = 'stock_niveles.php' AND pagina_id <> 112)
                        OR (url = 'stock_alertas.php' AND pagina_id <> 113)) = 0,
                    'conf__paginas: stock_niveles.php / stock_alertas.php ya registradas con otro id');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                     WHERE pagina_funcion_id BETWEEN 3354 AND 3361
                       AND pagina_id NOT IN (112, 113)) = 0,
                    'conf__paginas_funciones: los ids 3354-3361 están ocupados por otra página');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__estados_registros
                     WHERE estado_registro_id IN (1, 2, 17, 18, 21, 26)) = 6,
                    'conf__estados_registros: faltan los estados genéricos 1/2/17/18/21/26');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id = 44) = 1,
                    'conf__paginas: falta el menú Stock (44)');

CALL `_sn02_assert`((SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id = 282 AND pagina_id = 77) = 1,
                    'conf__paginas_funciones: no está la función 282 (Agregar de Ajustes de Stock) para copiar permisos');

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Tablas
-- ---------------------------------------------------------------------
INSERT INTO conf__tablas (tabla_id, tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_patron_id, tabla_estado_registro_id)
SELECT v.tabla_id, v.tabla_nombre, v.descripcion, 2, v.tipo, 1, 1
FROM (
              SELECT 429 AS tabla_id, 'gestion__stock_niveles' AS tabla_nombre,
                     'Stock mínimo y máximo por producto y boca' AS descripcion, 1 AS tipo
    UNION ALL SELECT 430, 'gestion__stock_alertas',
                     'Alertas de reposición por stock bajo el mínimo', 2
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas t WHERE t.tabla_id = v.tabla_id);

-- ---------------------------------------------------------------------
-- 2) Estados por tabla
-- ---------------------------------------------------------------------
INSERT INTO conf__tablas_estados_registros
    (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, es_final, orden)
SELECT v.id, v.tabla_id, v.estado_id, v.nombre, v.color_id, v.es_inicial, v.es_final, v.orden
FROM (
              SELECT 2183 AS id, 429 AS tabla_id, 1 AS estado_id, 'Activo' AS nombre, 3 AS color_id, 1 AS es_inicial, 0 AS es_final, 1 AS orden
    UNION ALL SELECT 2184, 429, 2,  'Inactivo',   2, 0, 0, 10
    UNION ALL SELECT 2185, 430, 17, 'Pendiente',  5, 1, 0, 1
    UNION ALL SELECT 2186, 430, 18, 'En gestión', 1, 0, 0, 10
    UNION ALL SELECT 2187, 430, 21, 'Repuesta',   3, 0, 1, 20
    UNION ALL SELECT 2188, 430, 26, 'Descartada', 2, 0, 1, 30
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros x WHERE x.tabla_estado_registro_id = v.id);

-- ---------------------------------------------------------------------
-- 3) Páginas (menú Stock = 44)
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas
    (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
     pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT v.pagina_id, 2, v.pagina, v.url, v.icono_id, v.descripcion, 44, v.orden, v.tabla_id, 1, 1, 0, NULL
FROM (
              SELECT 112 AS pagina_id, 'Stock - Mínimos y Máximos' AS pagina, 'stock_niveles.php' AS url,
                     107 AS icono_id, 'Stock mínimo y máximo por producto y boca de expendio' AS descripcion,
                     110 AS orden, 429 AS tabla_id
    UNION ALL SELECT 113, 'Stock - Alertas de Reposición', 'stock_alertas.php',
                     48, 'Productos con stock por debajo del mínimo en cada boca',
                     120, 430
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina_id);

-- ---------------------------------------------------------------------
-- 4) Funciones (motor de botones)
-- ---------------------------------------------------------------------
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina_id, v.icono_id, v.color_id, 1, v.nombre, v.accion_js, v.descripcion, v.origen, v.destino, v.orden, 1
FROM (
              SELECT 3354 AS id, 112 AS pagina_id, 42 AS icono_id, 3 AS color_id, 'Agregar' AS nombre, 'agregar' AS accion_js,
                     'Cargar mínimo y máximo de un producto en una boca' AS descripcion, 0 AS origen, 1 AS destino, 1 AS orden
    UNION ALL SELECT 3355, 112, 107, 6, 'Copiar de otra boca', 'copiar_boca',
                     'Copiar los niveles de una boca a otra', 0, 1, 2
    UNION ALL SELECT 3356, 112, 40,  1, 'Editar', 'editar', 'Editar mínimo y máximo', 1, 1, 1
    UNION ALL SELECT 3357, 112, 45,  4, 'Inhabilitar', 'inhabilitar', 'Dejar de controlar este producto en la boca', 1, 2, 10
    UNION ALL SELECT 3358, 112, 46,  3, 'Habilitar', 'habilitar', 'Volver a controlar este producto en la boca', 2, 1, 1
    UNION ALL SELECT 3359, 113, 8,   1, 'Tomar', 'tomar', 'Marcar la alerta como en gestión', 17, 18, 1
    UNION ALL SELECT 3360, 113, 45,  4, 'Descartar', 'descartar', 'Descartar la alerta sin reponer', 17, 26, 10
    UNION ALL SELECT 3361, 113, 45,  4, 'Descartar', 'descartar', 'Descartar la alerta sin reponer', 18, 26, 10
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = v.id);

-- ---------------------------------------------------------------------
-- 5) Permisos copiados de Agregar de Ajustes de Stock (282)
-- ---------------------------------------------------------------------
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, n.id, 1
FROM (          SELECT 3354 AS id UNION ALL SELECT 3355 UNION ALL SELECT 3356 UNION ALL SELECT 3357
      UNION ALL SELECT 3358 UNION ALL SELECT 3359 UNION ALL SELECT 3360 UNION ALL SELECT 3361) n
JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = 282 AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, n.id, 1
FROM (          SELECT 3354 AS id UNION ALL SELECT 3355 UNION ALL SELECT 3356 UNION ALL SELECT 3357
      UNION ALL SELECT 3358 UNION ALL SELECT 3359 UNION ALL SELECT 3360 UNION ALL SELECT 3361) n
JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = 282 AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

COMMIT;

DROP PROCEDURE IF EXISTS `_sn02_assert`;

-- Resumen (solo lectura)
SELECT p.pagina_id, p.pagina, p.url, p.tabla_id,
       (SELECT COUNT(*) FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id) AS funciones,
       (SELECT COUNT(*) FROM conf__tablas_estados_registros t WHERE t.tabla_id = p.tabla_id) AS estados,
       (SELECT COUNT(DISTINCT e.empresa_perfil_id) FROM conf__empresas_perfiles_funciones e
          JOIN conf__paginas_funciones f ON f.pagina_funcion_id = e.pagina_funcion_id
         WHERE f.pagina_id = p.pagina_id AND e.asignado = 1) AS perfiles_con_acceso
FROM conf__paginas p WHERE p.pagina_id IN (112, 113);
