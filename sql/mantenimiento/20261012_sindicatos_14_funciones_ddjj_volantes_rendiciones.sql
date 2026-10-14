-- ==============================================================================
-- 20261012_sindicatos_14_funciones_ddjj_volantes_rendiciones.sql
-- Módulo 6 (Sindicatos): funciones del motor de estados para DDJJ (1020), Volantes (1030)
-- y Rendiciones (1031).
--
-- Esas tres páginas solo tenían la función "Acceder" y sus botones estaban fijos en el
-- JavaScript (y se mostraban a cualquier usuario con acceso a la página). Ahora los arma el
-- motor (sindi_obtener_botones_acciones) según el estado del registro y los permisos del
-- usuario, y el servidor exige la función al ejecutar (sindi_exigir_funcion).
--
-- 1. Quita filas duplicadas de conf__tablas_estados_registros en las tablas del módulo (501-599).
-- 2. Funciones nuevas (bloque 16221-16259), una fila por estado de origen.
-- 3. Permisos: cada función nueva se asigna a los perfiles que ya tienen "Acceder" en su página
--    (los externos conservan DDJJ y volantes; rendiciones queda solo para los internos).
--
-- Reanudable.
-- ==============================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- 1. Estados duplicados (se conserva la fila de menor id)
DELETE t FROM conf__tablas_estados_registros t
INNER JOIN conf__tablas_estados_registros k
        ON k.tabla_id = t.tabla_id AND k.estado_registro_id = t.estado_registro_id
       AND k.tabla_estado_registro_id < t.tabla_estado_registro_id
WHERE t.tabla_id BETWEEN 501 AND 599;

-- 2. Funciones
DROP TEMPORARY TABLE IF EXISTS tmp_funciones_sindi;
CREATE TEMPORARY TABLE tmp_funciones_sindi (
    id INT NOT NULL PRIMARY KEY,
    pagina_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    accion_js VARCHAR(100) NOT NULL,
    origen INT NOT NULL,
    destino INT NOT NULL,
    orden INT NOT NULL,
    color_id INT NOT NULL,
    icono_clase VARCHAR(100) NULL
) ENGINE=MEMORY;

INSERT INTO tmp_funciones_sindi VALUES
-- DDJJ (1020): 3 Borrador, 28 Presentada, 2 Rectificada, 38 Anulada
(16221, 1020, 'Nueva DDJJ',        'agregar',    0,  3, 10, 1,  'bi bi-plus-circle-fill'),
(16222, 1020, 'Editar',            'editar',     3,  3, 20, 9,  NULL),
(16223, 1020, 'Presentar',         'confirmar',  3, 28, 30, 3,  'bi bi-check-circle-fill'),
(16224, 1020, 'Anular',            'anular',     3, 38, 40, 4,  'bi bi-trash-fill'),
(16225, 1020, 'Constancia',        'constancia', 28, 28, 50, 6, NULL),
(16226, 1020, 'Rectificar',        'rectificar', 28, 28, 60, 5, NULL),
(16227, 1020, 'Ver',               'ver',        2,  2, 70, 10, NULL),
(16228, 1020, 'Ver',               'ver',        38, 38, 71, 10, NULL),
-- Volantes (1030): 31 Emitido, 39/40 Cancelado parcial/total, 20 Vencido, 38 Anulado
(16240, 1030, 'Emitir volante',    'agregar',    0, 31, 10, 1,  'bi bi-plus-circle-fill'),
(16241, 1030, 'Ver / imprimir',    'ver',        31, 31, 20, 9, NULL),
(16242, 1030, 'Ver / imprimir',    'ver',        39, 39, 21, 9, NULL),
(16243, 1030, 'Ver / imprimir',    'ver',        40, 40, 22, 9, NULL),
(16244, 1030, 'Ver / imprimir',    'ver',        20, 20, 23, 9, NULL),
(16245, 1030, 'Ver / imprimir',    'ver',        38, 38, 24, 9, NULL),
(16246, 1030, 'Anular',            'anular',     31, 38, 30, 4, 'bi bi-trash-fill'),
-- Rendiciones (1031): 8 Recibido, 5 Conciliado, 27 Con error, 35 Procesado
(16250, 1031, 'Subir rendición',   'agregar',    0,  8, 10, 1,  'bi bi-upload'),
(16251, 1031, 'Ver líneas',        'ver',        8,  8, 20, 9,  'bi bi-list-check'),
(16252, 1031, 'Ver líneas',        'ver',        5,  5, 21, 9,  'bi bi-list-check'),
(16253, 1031, 'Ver líneas',        'ver',        27, 27, 22, 9, 'bi bi-list-check'),
(16254, 1031, 'Ver líneas',        'ver',        35, 35, 23, 9, 'bi bi-list-check'),
(16255, 1031, 'Procesar cobranzas','procesar',   8, 35, 30, 3,  'bi bi-check-circle-fill'),
(16256, 1031, 'Procesar cobranzas','procesar',   5, 35, 31, 3,  'bi bi-check-circle-fill'),
(16257, 1031, 'Procesar cobranzas','procesar',   27, 35, 32, 3, 'bi bi-check-circle-fill');

INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT t.id, t.pagina_id,
       (SELECT MIN(i.icono_id) FROM conf__iconos i WHERE i.icono_clase = t.icono_clase),
       t.color_id, 1, t.nombre, t.accion_js, NULL, t.origen, t.destino, t.orden, 1
FROM tmp_funciones_sindi t
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = t.id);

-- 3. Permisos: a quien ya tiene "Acceder" (accion_js consultar) en la misma página
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT epf.empresa_id, epf.empresa_perfil_id, t.id, 1
FROM tmp_funciones_sindi t
INNER JOIN conf__paginas_funciones acc ON acc.pagina_id = t.pagina_id AND acc.accion_js = 'consultar'
INNER JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = acc.pagina_funcion_id AND epf.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = t.id);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfu.perfil_id, t.id, 1
FROM tmp_funciones_sindi t
INNER JOIN conf__paginas_funciones acc ON acc.pagina_id = t.pagina_id AND acc.accion_js = 'consultar'
INNER JOIN conf__perfiles_funciones pfu ON pfu.pagina_funcion_id = acc.pagina_funcion_id
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pfu.perfil_id AND x.pagina_funcion_id = t.id);

DROP TEMPORARY TABLE IF EXISTS tmp_funciones_sindi;

COMMIT;

-- Verificación
SELECT pf.pagina_id, COUNT(DISTINCT pf.pagina_funcion_id) AS funciones,
       COUNT(DISTINCT epf.empresa_perfil_id) AS perfiles_con_permiso
FROM conf__paginas_funciones pf
LEFT JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = pf.pagina_funcion_id
WHERE pf.pagina_id IN (1020, 1030, 1031)
GROUP BY pf.pagina_id;

SELECT tabla_id, estado_registro_id, COUNT(*) AS filas
FROM conf__tablas_estados_registros WHERE tabla_id BETWEEN 501 AND 599
GROUP BY tabla_id, estado_registro_id HAVING COUNT(*) > 1;
