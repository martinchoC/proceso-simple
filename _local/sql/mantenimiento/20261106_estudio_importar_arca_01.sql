-- Estudio contable: página "Importar Mis Comprobantes" (419) para clientes con empresa en el ERP (nivel FISCAL / CONTABLE
-- / GESTION). Importa Emitidos y Recibidos de ARCA en la empresa del cliente con los motores de Gestión Normal
-- (vfiImportar / fpImportarComprobantesArca) y confirma en lote las compras en Borrador.
-- Funciones 3556 Acceder, 3557 Importar, 3558 Confirmar compras (siguientes libres después de 3555).
-- Los perfiles que hoy tienen Acceder / Cargar en Mini-Gestión (3448 / 3449) las heredan. Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT 419, 4, 'Importar Mis Comprobantes', 'importar_arca.php', 83,
       'Importa Mis Comprobantes Emitidos y Recibidos de ARCA en la empresa del cliente y confirma las compras', 400, 11,
       0, 0, 1, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE url = 'importar_arca.php' AND modulo_id = 4);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, 419, 0, 1, 1, v.nombre, v.accion, v.descr, 0, 0, v.orden, 1
FROM (
    SELECT 3556 AS id, 'Acceder' AS nombre, 'consultar' AS accion, 'Acceso a la página' AS descr, 1 AS orden
    UNION ALL SELECT 3557, 'Importar comprobantes', 'importar_arca', 'Preparar la empresa del cliente e importar Emitidos / Recibidos', 2
    UNION ALL SELECT 3558, 'Confirmar compras', 'confirmar_compras', 'Confirmar en lote las facturas de compra en Borrador del cliente', 3
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = 419 AND f.accion_js = v.accion)
  -- 20261107_estudio_importar_en_minigestion_01 movió 3557 / 3558 a la 417: sin esto, volver a correrlo choca con el id
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f2 WHERE f2.pagina_funcion_id = v.id);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, m.nueva, 1, NOW()
FROM conf__perfiles_funciones pf
JOIN (SELECT 3448 AS vieja, 3556 AS nueva UNION ALL SELECT 3449, 3557 UNION ALL SELECT 3449, 3558) m ON m.vieja = pf.pagina_funcion_id
WHERE pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.nueva AND f.pagina_id = 419)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = m.nueva);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, m.nueva, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
JOIN (SELECT 3448 AS vieja, 3556 AS nueva UNION ALL SELECT 3449, 3557 UNION ALL SELECT 3449, 3558) m ON m.vieja = epf.pagina_funcion_id
WHERE epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = m.nueva AND f.pagina_id = 419)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = m.nueva);
