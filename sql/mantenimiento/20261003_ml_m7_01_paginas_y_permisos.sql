-- =====================================================================
-- Matriz Legal - M7 (Ejecución y Seguimiento)
-- Script 01: Páginas, Funciones y Permisos
-- 2026-10-03
-- =====================================================================

SET NAMES utf8mb4;

-- 1. PÁGINAS DEL MÓDULO 3 (Agrupador 351: Ejecución y Seguimiento)
INSERT INTO conf__paginas 
(pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
VALUES
-- Agrupador en menú
(351, 3, 'Ejecución y Seguimiento', '#', 18, 'Gestión de evidencias, hallazgos, alertas y tablero de cumplimiento', 0, 50, 1, 1, 1, 0),
-- Páginas operativas
(352, 3, 'Bandeja de Evidencias', 'evidencias.php', 24, 'Repositorio de evidencias digitales, comprobantes y validez legal', 351, 10, 1, 1, 1, 0),
(353, 3, 'Hallazgos e Incidencias', 'incidencias.php', 22, 'Desviaciones operativas, auditorías y enlace a planes de acción', 351, 20, 1, 1, 1, 0),
(354, 3, 'Consola de Alertas', 'alertas.php', 19, 'Monitoreo de notificaciones y vencimientos preventivos', 351, 30, 1, 1, 1, 0),
(355, 3, 'Tablero de Cumplimiento', 'tablero_cumplimiento.php', 21, 'Indicadores de cierre consolidados y métricas por empresa/región', 351, 40, 1, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    pagina = VALUES(pagina),
    url = VALUES(url),
    icono_id = VALUES(icono_id),
    pagina_descripcion = VALUES(pagina_descripcion),
    padre_id = VALUES(padre_id),
    orden = VALUES(orden),
    tabla_id = VALUES(tabla_id);

-- 2. FUNCIONES DE PÁGINA (Bloque 3221 a 3260)
INSERT INTO conf__paginas_funciones
(pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES
-- Página 352: Bandeja de Evidencias
(3221, 352, 1, 1, 1, 'Consultar Evidencias', 'consultar_evidencias', 'Ver repositorio de evidencias adjuntas', 0, 1, 10, 1),
(3222, 352, 1, 1, 1, 'Subir Evidencia', 'subir_evidencia', 'Adjuntar comprobante o archivo probatorio (SHA-256)', 0, 1, 20, 1),
(3223, 352, 1, 1, 1, 'Descargar Evidencia', 'descargar_evidencia', 'Descargar archivo probatorio', 0, 1, 30, 1),
(3224, 352, 1, 1, 1, 'Anular Evidencia', 'anular_evidencia', 'Dar de baja evidencia errónea', 1, 2, 40, 1),

-- Página 353: Hallazgos e Incidencias
(3231, 353, 1, 1, 1, 'Consultar Incidencias', 'consultar_incidencias', 'Ver hallazgos y no conformidades registradas', 0, 1, 10, 1),
(3232, 353, 1, 1, 1, 'Registrar Incidencia', 'registrar_incidencia', 'Reportar desviación operativa o hallazgo de auditoría', 0, 1, 20, 1),
(3233, 353, 1, 1, 1, 'Cerrar Incidencia', 'cerrar_incidencia', 'Dar por cerrada y subsanada la incidencia', 1, 2, 30, 1),
(3234, 353, 1, 1, 1, 'Vincular Ciclo Seguimiento', 'vincular_seguimiento', 'Asociar plan de acción correctivo y ciclo derivado (C29)', 0, 1, 40, 1),

-- Página 354: Consola de Alertas
(3241, 354, 1, 1, 1, 'Consultar Alertas', 'consultar_alertas', 'Ver historial y cola de notificaciones preventivas', 0, 1, 10, 1),
(3242, 354, 1, 1, 1, 'Disparar Alertas Preventivas', 'disparar_alertas', 'Generar y enviar alertas preventivas automáticas (C04/C05)', 0, 1, 20, 1),
(3243, 354, 1, 1, 1, 'Reenviar Alerta', 'reenviar_alerta', 'Reintentar notificación por canal', 0, 1, 30, 1),

-- Página 355: Tablero de Cumplimiento
(3251, 355, 1, 1, 1, 'Consultar Tablero', 'consultar_tablero', 'Ver KPIs de cumplimiento y cierres consolidados', 0, 1, 10, 1),
(3252, 355, 1, 1, 1, 'Calcular Cierre Período', 'calcular_cierre_periodo', 'Calcular consolidado mensual/trimestral ponderado (C34)', 0, 1, 20, 1),
(3253, 355, 1, 1, 1, 'Exportar Reporte', 'exportar_reporte', 'Exportar consolidado gerencial', 0, 1, 30, 1)
ON DUPLICATE KEY UPDATE 
    nombre_funcion = VALUES(nombre_funcion),
    accion_js = VALUES(accion_js),
    descripcion = VALUES(descripcion),
    orden = VALUES(orden);

-- 3. PERMISOS PARA PERFILES BASE (301 a 305)
-- 301 Administrador de Matriz (acceso total a funciones de M7)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 301, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3221 AND 3260;

-- 302 Gestor Normativo (evidencias, alertas, tablero)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 302, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3221, 3222, 3223, 3231, 3241, 3251, 3253);

-- 303 Área Legal (evidencias, incidencias, tablero)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 303, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3221, 3223, 3231, 3232, 3233, 3234, 3251, 3253);

-- 304 Responsable de Empresa
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 304, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id BETWEEN 3221 AND 3260;

-- 305 Responsable de Tareas (subir evidencias, ver alertas)
INSERT IGNORE INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id)
SELECT 305, pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3221, 3222, 3223, 3241);

-- 4. COPIAR PERMISOS A EMPRESAS 2 Y 3 (conf__empresas_perfiles_funciones)
INSERT IGNORE INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id)
SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id
FROM conf__empresas_perfiles ep
INNER JOIN conf__perfiles_funciones pf ON pf.perfil_id = ep.perfil_id_base
WHERE ep.empresa_id IN (2, 3) 
  AND pf.pagina_funcion_id BETWEEN 3221 AND 3260;
