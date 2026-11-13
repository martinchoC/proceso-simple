-- Estudio contable: función propia para el "Alta con asistente" del padrón (página 402).
-- El asistente crea una empresa del ERP (tenant nuevo), registra la vinculación y deja a quien hace el alta como
-- Administrador de esa empresa; por eso no alcanza con la función "agregar" del padrón y, como toda función que
-- concede acceso o configura empresas, se asigna solo a los perfiles de administración del estudio.
-- Id 3554: siguiente libre después de 3551–3553 (Inspecciones). Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3554, 402, 42, 1, 1, 'Alta con asistente', 'alta_asistente',
       'Crear la empresa del cliente en el ERP, vincularla al estudio y registrar plantillas de alta', 0, 0, 5, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = 402 AND accion_js = 'alta_asistente');

-- Perfil base "Administrador Estudio Contable" del módulo 4
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT p.perfil_id, f.pagina_funcion_id, 1, NOW()
FROM conf__perfiles p
JOIN conf__paginas_funciones f ON f.pagina_id = 402 AND f.accion_js = 'alta_asistente'
WHERE p.modulo_id = 4 AND p.perfil_nombre LIKE 'Administrador%'
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = f.pagina_funcion_id);

-- Perfiles de administración de cada empresa con el módulo 4
INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT ep.empresa_id, ep.empresa_perfil_id, f.pagina_funcion_id, 1, NOW()
FROM conf__empresas_perfiles ep
JOIN conf__paginas_funciones f ON f.pagina_id = 402 AND f.accion_js = 'alta_asistente'
WHERE ep.modulo_id = 4 AND ep.tabla_estado_registro_id = 1
  AND (ep.empresa_perfil_nombre LIKE 'Administrador%' OR ep.empresa_perfil_nombre = 'Acceso total')
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = f.pagina_funcion_id);
