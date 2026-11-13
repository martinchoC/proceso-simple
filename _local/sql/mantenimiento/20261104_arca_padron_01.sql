-- =====================================================================================================
-- Consulta de padrón de ARCA por CUIT (2026-11-04)
-- -----------------------------------------------------------------------------------------------------
-- Web service "Constancia de Inscripción" (WSAA servicio ws_sr_constancia_inscripcion, operación getPersona_v2 de
-- personaServiceA5): con el CUIT trae denominación, tipo de persona, domicilio fiscal, condición frente al IVA
-- (régimen general / monotributo / exento) y actividades. Lo usan el botón "Buscar en ARCA" de Clientes, Proveedores
-- y Entidades (assets/js/arca-padron.js) y la herramienta consultar_padron_arca del Asistente IA.
-- Código: arca/arca_padron.php (SOAP) + core/arca_padron_service.php (normalización y cruce con el ERP).
--
-- Integración aparte de la de facturación (otro servicio WSAA, otras URLs): integracion_id 2 "ARCA_PADRON", ambientes
-- 3 (homologación) y 4 (producción), operaciones 10–13. Cada empresa la da de alta en Integraciones ARCA
-- (página 99 / 599) con su certificado y servicio ws_sr_constancia_inscripcion. En ARCA, el certificado tiene que
-- estar asociado a ese servicio (Administrador de Relaciones de Clave Fiscal > "ws_sr_constancia_inscripcion").
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO conf__integraciones (integracion_id, codigo, nombre, descripcion, activo)
SELECT 2, 'ARCA_PADRON', 'ARCA - Padrón', 'Constancia de Inscripción (ws_sr_constancia_inscripcion): datos de un CUIT', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__integraciones WHERE integracion_id = 2);

INSERT INTO conf__integraciones_ambientes (integracion_ambiente_id, integracion_id, codigo, nombre, url_wsaa, url_servicio, activo)
SELECT 3, 2, 'homologacion', 'Homologación', 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
       'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5', 1 FROM DUAL
WHERE EXISTS (SELECT 1 FROM conf__integraciones WHERE integracion_id = 2)
  AND NOT EXISTS (SELECT 1 FROM conf__integraciones_ambientes WHERE integracion_ambiente_id = 3);

INSERT INTO conf__integraciones_ambientes (integracion_ambiente_id, integracion_id, codigo, nombre, url_wsaa, url_servicio, activo)
SELECT 4, 2, 'produccion', 'Producción', 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
       'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5', 1 FROM DUAL
WHERE EXISTS (SELECT 1 FROM conf__integraciones WHERE integracion_id = 2)
  AND NOT EXISTS (SELECT 1 FROM conf__integraciones_ambientes WHERE integracion_ambiente_id = 4);

-- arca_registrar_resultado busca la operación por integracion_id: la de WSAA va repetida para esta integración
INSERT INTO conf__integraciones_operaciones (integracion_operacion_id, integracion_id, codigo, nombre, descripcion, activo)
SELECT x.id, 2, x.codigo, x.nombre, x.descripcion, 1
FROM (SELECT 10 AS id, 'wsaa_login_cms' AS codigo, 'WSAA - LoginCms' AS nombre, 'Solicitud de Ticket de Acceso (Token/Sign) para el padrón' AS descripcion
      UNION ALL SELECT 11, 'wsaa_importar_ta', 'WSAA - Importar TA', 'Carga de un TA vigente desde una respuesta loginCms guardada en arca/'
      UNION ALL SELECT 12, 'padron_dummy', 'Padrón - dummy', 'Estado de los servidores del padrón'
      UNION ALL SELECT 13, 'padron_get_persona', 'Padrón - getPersona_v2', 'Constancia de inscripción de un CUIT') x
WHERE EXISTS (SELECT 1 FROM conf__integraciones WHERE integracion_id = 2)
  AND NOT EXISTS (SELECT 1 FROM conf__integraciones_operaciones o WHERE o.integracion_operacion_id = x.id);

COMMIT;

SELECT a.integracion_ambiente_id, i.codigo, a.codigo AS ambiente, a.url_servicio
FROM conf__integraciones_ambientes a JOIN conf__integraciones i ON i.integracion_id = a.integracion_id ORDER BY 1;
