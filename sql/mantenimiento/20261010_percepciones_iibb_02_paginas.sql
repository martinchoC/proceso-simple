-- =====================================================================
-- Percepciones de IIBB practicadas - script 02: TABLAS, ESTADOS, PÁGINAS, FUNCIONES Y PERMISOS
-- 2026-10-10 — docs/gestion/09_percepciones_iibb.md
--
-- Requiere 20261010_percepciones_iibb_01_estructura.sql.
--
-- conf__tablas (módulo 2), tipo 1 Diccionario:
--   432 gestion__padrones_iibb_importaciones     (1 Vigente / 2 Reemplazada)
--   433 gestion__entidades_iibb_exclusiones       (1 Activo / 2 Inactivo)
--   434 gestion__empresas_impuestos_config_situaciones (1 Activo / 2 Inactivo)
-- conf__tablas_estados_registros 2189-2194 (hueco 2183-2212).
--
-- conf__paginas (menú Tesorería):
--   módulo 2: 115 Padrones IIBB (padrones_iibb.php), 116 Percepciones IIBB (percepciones_iibb.php)  padre 100
--   módulo 5: 615 / 616, padre 600 (misma convención +500 de gestion_normal)
-- conf__paginas_funciones (3362-3366 y 8362-8366 ya ocupados: se usan 3367-3370 y 8367-8370):
--   115/615: 3367/8367 Importar padrón (importar 0->1), 3368/8368 Visualizar (visualizar 1->1)
--   116/616: 3369/8369 Exportar DDJJ (exportar 0->1), 3370/8370 Liquidar (liquidar 0->1)
-- Permisos: copiados de "Agregar" de Impuestos Adic. - Conf (262 módulo 2 / 5262 módulo 5) en
-- conf__perfiles_funciones y conf__empresas_perfiles_funciones (la que lee core/permisos.php).
--
-- conf__integraciones_operaciones: wsfe_tipos_tributos (FEParamGetTiposTributos) para el log de ARCA.
--
-- Idempotente. Corta si los ids están ocupados por otra cosa. Ambos motores.
-- =====================================================================

DROP PROCEDURE IF EXISTS `_pi02_assert`;
DELIMITER $$
CREATE PROCEDURE `_pi02_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

CALL `_pi02_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');
CALL `_pi02_assert`((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME IN ('gestion__padrones_iibb_importaciones', 'gestion__entidades_iibb_exclusiones',
                                        'gestion__empresas_impuestos_config_situaciones')) = 3,
                    'Faltan tablas: correr antes 20261010_percepciones_iibb_01_estructura.sql');
CALL `_pi02_assert`((SELECT COUNT(*) FROM conf__tablas
                     WHERE (tabla_id = 432 AND tabla_nombre <> 'gestion__padrones_iibb_importaciones')
                        OR (tabla_id = 433 AND tabla_nombre <> 'gestion__entidades_iibb_exclusiones')
                        OR (tabla_id = 434 AND tabla_nombre <> 'gestion__empresas_impuestos_config_situaciones')) = 0,
                    'conf__tablas: los ids 432-434 están ocupados por otra tabla');
CALL `_pi02_assert`((SELECT COUNT(*) FROM conf__tablas_estados_registros
                     WHERE tabla_estado_registro_id BETWEEN 2189 AND 2194 AND tabla_id NOT IN (432, 433, 434)) = 0,
                    'conf__tablas_estados_registros: los ids 2189-2194 están ocupados por otra tabla');
CALL `_pi02_assert`((SELECT COUNT(*) FROM conf__paginas
                     WHERE (pagina_id IN (115, 615) AND url <> 'padrones_iibb.php')
                        OR (pagina_id IN (116, 616) AND url <> 'percepciones_iibb.php')) = 0,
                    'conf__paginas: los ids 115/116/615/616 están ocupados por otra página');
CALL `_pi02_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                     WHERE (pagina_funcion_id BETWEEN 3367 AND 3370 AND pagina_id NOT IN (115, 116))
                        OR (pagina_funcion_id BETWEEN 8367 AND 8370 AND pagina_id NOT IN (615, 616))) = 0,
                    'conf__paginas_funciones: los ids 3367-3370 / 8367-8370 están ocupados');
CALL `_pi02_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (100, 600)) = 2,
                    'conf__paginas: falta el menú Tesorería (100 / 600)');
CALL `_pi02_assert`((SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id IN (262, 5262)) = 2,
                    'conf__paginas_funciones: faltan 262 / 5262 (Agregar de Impuestos Adic. - Conf) para copiar permisos');

START TRANSACTION;

INSERT INTO conf__tablas (tabla_id, tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_patron_id, tabla_estado_registro_id)
SELECT v.tabla_id, v.tabla_nombre, v.descripcion, 2, 1, 1, 1
FROM (
              SELECT 432 AS tabla_id, 'gestion__padrones_iibb_importaciones' AS tabla_nombre, 'Importaciones de padrones de percepción IIBB' AS descripcion
    UNION ALL SELECT 433, 'gestion__entidades_iibb_exclusiones', 'Certificados de no percepción IIBB de clientes'
    UNION ALL SELECT 434, 'gestion__empresas_impuestos_config_situaciones', 'Alícuota de percepción por situación del cliente'
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas t WHERE t.tabla_id = v.tabla_id);

INSERT INTO conf__tablas_estados_registros
    (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, es_final, orden)
SELECT v.id, v.tabla_id, v.estado_id, v.nombre, v.color_id, v.es_inicial, 0, v.orden
FROM (
              SELECT 2189 AS id, 432 AS tabla_id, 1 AS estado_id, 'Vigente' AS nombre, 3 AS color_id, 1 AS es_inicial, 1 AS orden
    UNION ALL SELECT 2190, 432, 2, 'Reemplazada', 2, 0, 10
    UNION ALL SELECT 2191, 433, 1, 'Activo',      3, 1, 1
    UNION ALL SELECT 2192, 433, 2, 'Inactivo',    2, 0, 10
    UNION ALL SELECT 2193, 434, 1, 'Activo',      3, 1, 1
    UNION ALL SELECT 2194, 434, 2, 'Inactivo',    2, 0, 10
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros x WHERE x.tabla_estado_registro_id = v.id);

INSERT INTO conf__paginas
    (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
     pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT v.pagina_id, v.modulo_id, v.pagina, v.url, v.icono_id, v.descripcion, v.padre_id, v.orden, v.tabla_id, 1, 1, 0, NULL
FROM (
              SELECT 115 AS pagina_id, 2 AS modulo_id, 'Padrones IIBB' AS pagina, 'padrones_iibb.php' AS url, 136 AS icono_id,
                     'Importación y consulta de padrones de alícuotas de percepción IIBB (SIRCIP)' AS descripcion,
                     100 AS padre_id, 51 AS orden, 432 AS tabla_id
    UNION ALL SELECT 116, 2, 'Percepciones IIBB', 'percepciones_iibb.php', 136,
                     'Percepciones de IIBB practicadas: DDJJ por jurisdicción y período, exportación y liquidación', 100, 52, 0
    UNION ALL SELECT 615, 5, 'Padrones IIBB', 'padrones_iibb.php', 136,
                     'Importación y consulta de padrones de alícuotas de percepción IIBB (SIRCIP)', 600, 51, 432
    UNION ALL SELECT 616, 5, 'Percepciones IIBB', 'percepciones_iibb.php', 136,
                     'Percepciones de IIBB practicadas: DDJJ por jurisdicción y período, exportación y liquidación', 600, 52, 0
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina_id);

INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina_id, v.icono_id, v.color_id, 1, v.nombre, v.accion_js, v.descripcion, v.origen, v.destino, v.orden, 1
FROM (
              SELECT 3367 AS id, 115 AS pagina_id, 42 AS icono_id, 3 AS color_id, 'Importar padrón' AS nombre, 'importar' AS accion_js,
                     'Importar el archivo de padrón de una jurisdicción' AS descripcion, 0 AS origen, 1 AS destino, 1 AS orden
    UNION ALL SELECT 3368, 115, 37, 1, 'Visualizar', 'visualizar', 'Ver el resumen de la importación', 1, 1, 1
    UNION ALL SELECT 3369, 116, 37, 1, 'Exportar DDJJ', 'exportar', 'Generar el archivo de la DDJJ del período', 0, 1, 1
    UNION ALL SELECT 3370, 116, 42, 3, 'Liquidar', 'liquidar', 'Generar la liquidación (I_LIQ) del período', 0, 1, 2
    UNION ALL SELECT 8367, 615, 42, 3, 'Importar padrón', 'importar', 'Importar el archivo de padrón de una jurisdicción', 0, 1, 1
    UNION ALL SELECT 8368, 615, 37, 1, 'Visualizar', 'visualizar', 'Ver el resumen de la importación', 1, 1, 1
    UNION ALL SELECT 8369, 616, 37, 1, 'Exportar DDJJ', 'exportar', 'Generar el archivo de la DDJJ del período', 0, 1, 1
    UNION ALL SELECT 8370, 616, 42, 3, 'Liquidar', 'liquidar', 'Generar la liquidación (I_LIQ) del período', 0, 1, 2
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = v.id);

-- Permisos: módulo 2 desde 262, módulo 5 desde 5262
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, n.id, 1
FROM (          SELECT 3367 AS id, 262 AS origen UNION ALL SELECT 3368, 262 UNION ALL SELECT 3369, 262 UNION ALL SELECT 3370, 262
      UNION ALL SELECT 8367, 5262 UNION ALL SELECT 8368, 5262 UNION ALL SELECT 8369, 5262 UNION ALL SELECT 8370, 5262) n
JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = n.origen AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, n.id, 1
FROM (          SELECT 3367 AS id, 262 AS origen UNION ALL SELECT 3368, 262 UNION ALL SELECT 3369, 262 UNION ALL SELECT 3370, 262
      UNION ALL SELECT 8367, 5262 UNION ALL SELECT 8368, 5262 UNION ALL SELECT 8369, 5262 UNION ALL SELECT 8370, 5262) n
JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = n.origen AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

-- Operación de ARCA para el log de FEParamGetTiposTributos (integración WSFE = 1)
INSERT INTO conf__integraciones_operaciones (integracion_id, codigo, nombre, descripcion, activo)
SELECT 1, 'wsfe_tipos_tributos', 'WSFE - FEParamGetTiposTributos', 'Tipos de tributo (percepciones IIBB en <Tributos>)', 1
FROM DUAL
WHERE EXISTS (SELECT 1 FROM conf__integraciones_operaciones WHERE integracion_id = 1)
  AND NOT EXISTS (SELECT 1 FROM conf__integraciones_operaciones WHERE integracion_id = 1 AND codigo = 'wsfe_tipos_tributos');

COMMIT;

DROP PROCEDURE IF EXISTS `_pi02_assert`;

-- Resumen (solo lectura)
SELECT p.pagina_id, p.modulo_id, p.pagina, p.url, p.padre_id,
       (SELECT COUNT(*) FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id) AS funciones,
       (SELECT COUNT(DISTINCT e.empresa_perfil_id) FROM conf__empresas_perfiles_funciones e
          JOIN conf__paginas_funciones f ON f.pagina_funcion_id = e.pagina_funcion_id
         WHERE f.pagina_id = p.pagina_id AND e.asignado = 1) AS perfiles_con_acceso
FROM conf__paginas p WHERE p.pagina_id IN (115, 116, 615, 616);
