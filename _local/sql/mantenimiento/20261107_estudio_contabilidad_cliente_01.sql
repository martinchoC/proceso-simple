-- Estudio contable: página "Contabilidad del Cliente" (423, cliente_contabilidad.php) en la sección Gestión de Clientes
-- (421): balance, sumas y saldos, mayores y asientos de la empresa del cliente en el ERP. Solo lectura.
-- Función 3561 "Acceder" (ver): la heredan los perfiles que tienen la ficha Gestión del Cliente (3559).
-- Ids libres verificados el 2026-11-07: página 423, función 3561 (siguiente libre después de 3560).
-- Requiere 20261107_estudio_gestion_clientes_01.sql. Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT 423, 4, 'Contabilidad del Cliente', 'cliente_contabilidad.php', 6,
       'Balance, sumas y saldos, mayores y asientos de la contabilidad del cliente (solo lectura)',
       421, 6, 0, 0, 1, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE url = 'cliente_contabilidad.php' AND modulo_id = 4);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3561, 423, 0, 1, 1, 'Acceder', 'ver', 'Ver la contabilidad del cliente', 0, 0, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = 423 AND accion_js = 'ver')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 3561);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, 3561, 1, NOW()
FROM conf__perfiles_funciones pf
WHERE pf.pagina_funcion_id = 3559 AND pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3561 AND f.pagina_id = 423)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = 3561);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, 3561, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
WHERE epf.pagina_funcion_id = 3559 AND epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3561 AND f.pagina_id = 423)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = 3561);
