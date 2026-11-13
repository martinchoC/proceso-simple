-- Asistente de inicio de empresa (páginas 121 / 621): botón "Nueva empresa desde constancia de CUIT".
-- Lee con IA la Constancia de Inscripción de ARCA (core/constancia_cuit_ia.php, acción leer_constancia), el usuario
-- revisa los datos y alta_empresa_constancia crea la empresa con el módulo, los perfiles predefinidos, el usuario como
-- Administrador y, opcional, la autorización de copia desde la empresa activa (inicioAltaEmpresaConstancia).
-- Función nueva "alta_empresa" (data-funcion + permisos_exigir_accion en empresa_inicio_ajax.php): sin ella el botón
-- no se ve y las acciones leer_constancia / alta_empresa_constancia devuelven 403.
-- Ids libres verificados el 2026-11-11: 3594 (página 121) y 8594 (página 621).
-- Permisos: los mismos perfiles que tienen "Ejecutar Copia Inicial" (copiar, 3413 / 8413), que son solo los de
-- administración (base 201 / 501 y Administrador / Acceso total de cada empresa). Reanudable.
SET NAMES utf8mb4;

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion,
       accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT n.id, f.pagina_id, f.icono_id, f.color_id, f.funcion_estandar_id, 'Alta de Empresa desde Constancia',
       'alta_empresa', 'Dar de alta una empresa nueva con los datos de la constancia de CUIT leídos con IA', 0, 0, f.orden + 1, 1
FROM (SELECT 3594 AS id, 3413 AS base UNION ALL SELECT 8594, 8413) n
JOIN conf__paginas_funciones f ON f.pagina_funcion_id = n.base
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_id = f.pagina_id AND x.accion_js = 'alta_empresa');

INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT pf.perfil_id, n.id, 1, NOW()
FROM (SELECT 3594 AS id, 3413 AS base UNION ALL SELECT 8594, 8413) n
JOIN conf__perfiles_funciones pf ON pf.pagina_funcion_id = n.base AND pf.asignado = 1
WHERE EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pf.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado, fecha_asignacion)
SELECT epf.empresa_id, epf.empresa_perfil_id, n.id, 1, NOW()
FROM (SELECT 3594 AS id, 3413 AS base UNION ALL SELECT 8594, 8413) n
JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = n.base AND epf.asignado = 1
WHERE EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = epf.empresa_perfil_id AND x.pagina_funcion_id = n.id);
