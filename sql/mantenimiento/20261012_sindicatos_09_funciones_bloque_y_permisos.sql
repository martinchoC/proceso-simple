-- ==============================================================================
-- 20261012_sindicatos_09_funciones_bloque_y_permisos.sql
-- Módulo 6 (Sindicatos): bloque propio de funciones y permiso "Ver todos los empleadores".
--
-- 1. Mueve todas las funciones de las páginas 1000–1099 al bloque 16001–16999.
--    El bloque 9001–9999 choca con el módulo 5 (que numera "id del módulo 2 + 5000"
--    y ya usa 9041–9043), y 3402/3403 caen en el rango del módulo 2.
--    Regla: 9xxx -> 9xxx + 7000; 3402 -> 16201; 3403 -> 16202; 100xx -> 162xx.
--    Actualiza las referencias en conf__perfiles_funciones y conf__empresas_perfiles_funciones.
-- 2. Corrige los nombres de las funciones de la página 1010 (estaban cruzados).
-- 3. Función 16100 "Ver todos los empleadores" (página 1010): quien la tiene ve y opera
--    todos los empleadores de la empresa (core/accesos_empleadores.php). Se asigna a los
--    perfiles internos del sindicato (601 Administrador, 602 Operador, 603 Inspector) y a
--    los administradores generales (1, 2) de las empresas con el módulo 6.
--
-- Correr DESPUÉS de los scripts 01–08. Reanudable: solo mueve ids que no estén ya en 16xxx.
-- ==============================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- ------------------------------------------------------------------------------
-- 1. Renumeración
-- ------------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_sindi_func_map;
CREATE TEMPORARY TABLE tmp_sindi_func_map (
    viejo INT NOT NULL PRIMARY KEY,
    nuevo INT NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO tmp_sindi_func_map (viejo, nuevo)
SELECT pf.pagina_funcion_id,
       CASE
           WHEN pf.pagina_funcion_id BETWEEN 9001 AND 9999 THEN pf.pagina_funcion_id + 7000
           WHEN pf.pagina_funcion_id = 3402 THEN 16201
           WHEN pf.pagina_funcion_id = 3403 THEN 16202
           WHEN pf.pagina_funcion_id BETWEEN 10000 AND 10099 THEN pf.pagina_funcion_id + 6200
       END
FROM conf__paginas_funciones pf
WHERE pf.pagina_id BETWEEN 1000 AND 1099
  AND pf.pagina_funcion_id NOT BETWEEN 16001 AND 16999;

-- Si quedara alguna función del módulo sin regla, se corta acá (nuevo NULL viola NOT NULL).

UPDATE conf__empresas_perfiles_funciones epf
INNER JOIN tmp_sindi_func_map m ON m.viejo = epf.pagina_funcion_id
SET epf.pagina_funcion_id = m.nuevo;

UPDATE conf__perfiles_funciones pf
INNER JOIN tmp_sindi_func_map m ON m.viejo = pf.pagina_funcion_id
SET pf.pagina_funcion_id = m.nuevo;

UPDATE conf__paginas_funciones pf
INNER JOIN tmp_sindi_func_map m ON m.viejo = pf.pagina_funcion_id
SET pf.pagina_funcion_id = m.nuevo;

-- ------------------------------------------------------------------------------
-- 2. Nombres de la página 1010 (Empleadores)
-- ------------------------------------------------------------------------------
UPDATE conf__paginas_funciones SET nombre_funcion = 'Agregar'
WHERE pagina_id = 1010 AND accion_js = 'agregar';
UPDATE conf__paginas_funciones SET nombre_funcion = 'Editar'
WHERE pagina_id = 1010 AND accion_js = 'editar';

-- ------------------------------------------------------------------------------
-- 3. Función "Ver todos los empleadores" (no es una transición: origen = destino = 0
--    y accion_js distinto de 'agregar', así que el motor no la muestra como botón)
-- ------------------------------------------------------------------------------
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 16100, 1010, NULL, 1, 1, 'Ver todos los empleadores', 'ver_todos_empleadores',
       'Permiso: el usuario ve y opera todos los empleadores de la empresa (perfiles internos del sindicato). Sin él, solo los asignados en sindi__usuarios_empleadores.',
       0, 0, 900, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 16100);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT p.perfil_id, 16100, 1
FROM conf__perfiles p
WHERE p.perfil_id IN (601, 602, 603)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = 16100);

-- En las empresas con el módulo 6: perfiles internos del sindicato y los administradores
-- generales (1, 2), que antes tenían este acceso por id fijo en el código.
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT ep.empresa_id, ep.empresa_perfil_id, 16100, 1
FROM conf__empresas_perfiles ep
WHERE ep.perfil_id_base IN (1, 2, 601, 602, 603)
  AND ep.tabla_estado_registro_id = 1
  AND EXISTS (SELECT 1 FROM conf__empresas_modulos em WHERE em.empresa_id = ep.empresa_id AND em.modulo_id = 6)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = 16100);

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_sindi_func_map;

-- ------------------------------------------------------------------------------
-- Verificación
-- ------------------------------------------------------------------------------
SELECT 'funciones del módulo 6 fuera del bloque 16001-16999' AS control, COUNT(*) AS cantidad
FROM conf__paginas_funciones WHERE pagina_id BETWEEN 1000 AND 1099 AND pagina_funcion_id NOT BETWEEN 16001 AND 16999
UNION ALL
SELECT 'permisos que apuntan a funciones inexistentes', COUNT(*)
FROM conf__empresas_perfiles_funciones epf
LEFT JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
WHERE pf.pagina_funcion_id IS NULL
UNION ALL
SELECT 'perfiles con Ver todos los empleadores', COUNT(*)
FROM conf__empresas_perfiles_funciones WHERE pagina_funcion_id = 16100;
