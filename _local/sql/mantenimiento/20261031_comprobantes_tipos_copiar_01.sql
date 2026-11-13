-- =====================================================================================================
-- Función "Copiar" en Tipos de Comprobante (páginas 45 / 545)
-- -----------------------------------------------------------------------------------------------------
-- Abre el formulario con los datos de un tipo activo para crear otro a partir de él (se edita antes de guardar y
-- queda como un tipo más, editable). Opcionalmente copia sus relaciones (cancelaciones y dependencias, en los dos
-- sentidos) y lo habilita en los mismos puntos de venta. Lógica: copiarComprobanteTipo() de comprobantes_tipos_model.php.
-- Funciones 3447 (página 45) / 8447 (página 545); se ejecuta sobre tipos activos (1 -> 1), no cambia el estado.
-- Permisos: perfil base Administrador (201 / 501) y, en cada empresa, los perfiles de administración.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

START TRANSACTION;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES (3447, 45, 145, 1, 1, 'Copiar', 'copiar', 'Crea un tipo nuevo a partir de este', 1, 1, 12, 1),
       (8447, 545, 145, 1, 1, 'Copiar', 'copiar', 'Crea un tipo nuevo a partir de este', 1, 1, 12, 1)
ON DUPLICATE KEY UPDATE pagina_id = VALUES(pagina_id), nombre_funcion = VALUES(nombre_funcion), accion_js = VALUES(accion_js),
                        descripcion = VALUES(descripcion), tabla_estado_registro_origen_id = 1, tabla_estado_registro_destino_id = 1,
                        orden = VALUES(orden), tabla_estado_registro_id = 1;

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT p.perfil_id, pf.pagina_funcion_id, 1
FROM conf__perfiles p
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = 3447 AND p.perfil_id = 201
                                OR pf.pagina_funcion_id = 8447 AND p.perfil_id = 501
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM conf__empresas_perfiles ep
JOIN conf__paginas pg ON pg.modulo_id = ep.modulo_id AND pg.pagina_id IN (45, 545)
JOIN conf__paginas_funciones pf ON pf.pagina_id = pg.pagina_id AND pf.accion_js = 'copiar'
WHERE ep.tabla_estado_registro_id = 1
  AND (ep.empresa_perfil_nombre LIKE 'Administrador%' OR ep.empresa_perfil_nombre = 'Acceso total')
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

COMMIT;

-- Control (solo lectura)
SELECT pf.pagina_funcion_id, pf.pagina_id, COUNT(epf.empresa_perfil_id) perfiles_con_permiso
FROM conf__paginas_funciones pf
LEFT JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = pf.pagina_funcion_id
WHERE pf.pagina_funcion_id IN (3447, 8447)
GROUP BY pf.pagina_funcion_id, pf.pagina_id;
