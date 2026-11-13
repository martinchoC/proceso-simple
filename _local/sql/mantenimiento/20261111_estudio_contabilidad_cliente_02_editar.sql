-- Estudio contable: Contabilidad del Cliente (423) permite editar un asiento de la empresa del cliente mientras su
-- período contable esté abierto (core/cont_periodos.php). Función 3575 "Editar asiento" (editar).
-- La heredan los perfiles que pueden cerrar en Cierres Contables (3541, agregar de la 413): los que ya escriben la
-- contabilidad de los clientes. Además hace falta la vinculación de contabilidad con escritura (salvo el propio estudio).
-- Ids libres verificados el 2026-11-11: función 3575 (siguiente libre después de 3574).
-- Requiere 20261107_estudio_contabilidad_cliente_01.sql. Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3575, 423, 42, 1, 1, 'Editar asiento', 'editar', 'Editar un asiento de la contabilidad del cliente con el período abierto', 0, 0, 2, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = 423 AND accion_js = 'editar')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 3575);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, 3575, 1, NOW()
FROM conf__perfiles_funciones pf
WHERE pf.pagina_funcion_id = 3541 AND pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3575 AND f.pagina_id = 423)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = 3575);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, 3575, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
WHERE epf.pagina_funcion_id = 3541 AND epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3575 AND f.pagina_id = 423)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = 3575);
