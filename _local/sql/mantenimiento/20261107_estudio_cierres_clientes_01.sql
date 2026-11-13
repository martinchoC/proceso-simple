-- Estudio contable: Cierres Contables por cliente (página 413) y retiro de Parámetros Contables (412).
--  1) Concepto contable RESULTADO_EJERCICIO (Gestión > Tesorería > Conceptos Contables) en cada empresa que ya usa
--     conceptos, con la cuenta de gestion__cont_parametros.cuenta_resultado_ejercicio_id si la tenía (si no, NULL hasta
--     configurarla). Es la única cuenta de Parámetros Contables sin concepto equivalente; la usa el cierre de ejercicio.
--  2) Función 3560 "Reabrir mes" (reabrir) de la 413 (siguiente libre después de 3559, verificado 2026-11-07), para los
--     perfiles que tienen "Ejecutar cierre" (3541).
--  3) La 413 pasa a la sección Gestión de Clientes (421) con el nombre "Cierres Contables".
--  4) Se inactivan la 412 y sus funciones (3331–3334): el resto de sus cuentas son respaldo de los Conceptos Contables.
--     Revertir: estado 1. No se borran datos (gestion__cont_parametros sigue como respaldo de emp_cuenta_id()).
-- Requiere 20261107_estudio_gestion_clientes_01.sql. Reanudable.
SET NAMES utf8mb4;

-- 1) Concepto RESULTADO_EJERCICIO
INSERT INTO gestion__empresas_cuentas_conceptos (empresa_id, concepto, cont_cuenta_id, tabla_estado_registro_id, fecha_creacion)
SELECT e.empresa_id, 'RESULTADO_EJERCICIO',
       (SELECT c.cont_cuenta_id FROM gestion__cont_parametros p
          JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = p.cuenta_resultado_ejercicio_id AND c.empresa_id = p.empresa_id
         WHERE p.empresa_id = e.empresa_id AND c.es_imputable = 1 AND c.tabla_estado_registro_id = 1 LIMIT 1),
       1, NOW()
FROM (SELECT DISTINCT empresa_id FROM gestion__empresas_cuentas_conceptos) e
WHERE NOT EXISTS (SELECT 1 FROM gestion__empresas_cuentas_conceptos x
                  WHERE x.empresa_id = e.empresa_id AND x.concepto = 'RESULTADO_EJERCICIO');

-- 2) Función Reabrir mes
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3560, 413, 0, 1, 1, 'Reabrir mes', 'reabrir', 'Reabrir un mes cerrado de la contabilidad del cliente (con motivo)', 0, 0, 3, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = 413 AND accion_js = 'reabrir')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 3560);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, 3560, 1, NOW()
FROM conf__perfiles_funciones pf
WHERE pf.pagina_funcion_id = 3541 AND pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3560 AND f.pagina_id = 413)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = 3560);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, 3560, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
WHERE epf.pagina_funcion_id = 3541 AND epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3560 AND f.pagina_id = 413)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = 3560);

-- 3) La 413 a Gestión de Clientes
UPDATE conf__paginas
SET padre_id = 421, orden = 5, pagina = 'Cierres Contables',
    pagina_descripcion = 'Cierre de meses y de ejercicio de la contabilidad de cada cliente, según su mes de cierre'
WHERE pagina_id = 413 AND padre_id = 400
  AND EXISTS (SELECT 1 FROM (SELECT pagina_id FROM conf__paginas WHERE pagina_id = 421) x);

-- 4) Retiro de Parámetros Contables
UPDATE conf__paginas_funciones SET tabla_estado_registro_id = 2 WHERE pagina_id = 412 AND tabla_estado_registro_id = 1;
UPDATE conf__paginas SET tabla_estado_registro_id = 2 WHERE pagina_id = 412 AND modulo_id = 4 AND tabla_estado_registro_id = 1;
