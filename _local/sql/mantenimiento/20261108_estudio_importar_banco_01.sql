-- Estudio contable, Mini-Gestión (página 417): botón "Importar resumen bancario" (minigestion_banco.js +
-- importar_banco_ajax.php / _model.php). Carga los gastos del banco con su IVA y percepciones como comprobante de compra
-- "Resumen bancario" (C_RB, ARCA 039) en la empresa del cliente Fiscal / Contable, igual que Gastos Bancarios de Gestión
-- pero sin tesorería (core/bancos/bancos_gastos_service.php: bco_gastos_componer).
-- Función 3570 importar_banco (3562–3569 los reserva 20261108_libros_societarios_01.sql). Los perfiles que tienen
-- "Importar comprobantes" (3557, importar_arca) la heredan. Sin cambios de esquema. Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3570, 417, 0, 1, 1, 'Importar resumen bancario', 'importar_banco',
       'Cargar los gastos, IVA y percepciones del resumen de cuenta del banco como comprobante del Libro IVA del cliente', 0, 0, 6, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = 417 AND f.accion_js = 'importar_banco')
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3570);

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, 3570, 1, NOW()
FROM conf__perfiles_funciones pf
WHERE pf.pagina_funcion_id = 3557 AND pf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3570 AND f.pagina_id = 417)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = 3570);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, 3570, 1, NOW()
FROM conf__empresas_perfiles_funciones epf
WHERE epf.pagina_funcion_id = 3557 AND epf.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3570 AND f.pagina_id = 417)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = 3570);

-- Verificación
SELECT f.pagina_funcion_id, f.accion_js,
       (SELECT COUNT(*) FROM conf__perfiles_funciones x WHERE x.pagina_funcion_id = 3570) AS perfiles,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones x WHERE x.pagina_funcion_id = 3570) AS empresas_perfiles
FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = 3570;
