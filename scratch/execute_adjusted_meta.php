<?php
require_once __DIR__ . '/../db.php';

$sql = <<<SQL
-- =============================================================================
-- DEVELOP SAM SAS - SISTEMA DE GESTION (ERP gestion_multipyme)
-- SCRIPT DDL AJUSTADO: MODULO ESTUDIO CONTABLE Y AUDITORIA INTEGRADA
-- Version: 1.1 (Ajustada a la arquitectura real de gestion_multipyme)
-- Fecha: Octubre 2026
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1.0. Registro de Iconos Faltantes en conf__iconos
INSERT INTO `conf__iconos` (`icono_id`, `icono_nombre`, `icono_clase`, `tabla_estado_registro_id`) VALUES
(74, 'Balanza / Estudio FA', 'fas fa-balance-scale', 1),
(75, 'Usuario Ejecutivo FA', 'fas fa-user-tie', 1),
(76, 'Calendario Check FA', 'fas fa-calendar-check', 1),
(77, 'Engranajes FA', 'fas fa-cogs', 1),
(78, 'Factura Dolar FA', 'fas fa-file-invoice-dollar', 1),
(79, 'Recibo FA', 'fas fa-receipt', 1),
(80, 'Martillo Ley FA', 'fas fa-gavel', 1),
(81, 'Tareas FA', 'fas fa-tasks', 1),
(82, 'Reloj Usuario FA', 'fas fa-user-clock', 1),
(83, 'Calculadora FA', 'fas fa-calculator', 1),
(84, 'Buscar Dolar FA', 'fas fa-search-dollar', 1)
ON DUPLICATE KEY UPDATE `icono_nombre` = VALUES(`icono_nombre`), `icono_clase` = VALUES(`icono_clase`), `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.1. Modulo en conf__modulos (modulo_id = 4)
INSERT INTO `conf__modulos` (`modulo_id`, `modulo`, `base_datos`, `modulo_url`, `email_envio_modulo`, `layout_nombre`, `usuario_temp`, `session_temp`, `imagen_id`, `depende_id`, `tabla_estado_registro_id`)
VALUES (4, 'Estudio Contable', 'estudio__', 'estudio', 'info@developsam.com', 'default', NULL, NULL, 2, 0, 1)
ON DUPLICATE KEY UPDATE 
    `modulo` = VALUES(`modulo`),
    `base_datos` = VALUES(`base_datos`),
    `modulo_url` = VALUES(`modulo_url`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.2. Registro de Tablas Fisicas en conf__tablas
INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`) VALUES
(401, 'estudio__clientes_tipos', 'Catalogo de tipos de clientes del estudio', 4, 1, 1, 1),
(402, 'estudio__clientes', 'Padron maestro de clientes del estudio contable', 4, 1, 1, 1),
(403, 'estudio__vinculaciones', 'Autorizaciones y consentimientos cross-tenant con clientes ERP', 4, 2, 1, 1),
(404, 'estudio__honorarios_conceptos', 'Catalogo de conceptos y modalidades de honorarios', 4, 1, 1, 1),
(405, 'estudio__honorarios_tarifarios', 'Tarifarios y escalas de precios de honorarios', 4, 1, 1, 1),
(406, 'estudio__honorarios_tarifarios_detalles', 'Precios unitarios por concepto dentro del tarifario', 4, 1, 1, 1),
(407, 'estudio__clientes_abonos', 'Reglas de facturacion recurrente y abonos mensuales por cliente', 4, 1, 1, 1),
(408, 'estudio__honorarios_novedades', 'Cargos eventuales o adicionales del mes a facturar', 4, 2, 1, 1),
(409, 'estudio__impuestos_obligaciones', 'Catalogo maestro de obligaciones tributarias y previsionales', 4, 1, 1, 1),
(410, 'estudio__clientes_jurisdicciones', 'Jurisdicciones provinciales y coeficientes CM05 del cliente', 4, 1, 1, 1),
(411, 'estudio__tasas_municipales', 'Tasas municipales aplicables por cliente', 4, 1, 1, 1),
(412, 'estudio__clientes_obligaciones', 'Matriz de obligaciones asignadas a cada cliente', 4, 1, 1, 1),
(413, 'estudio__calendario_vencimientos', 'Cronograma oficial de vencimientos por organismo y CUIT', 4, 1, 1, 1),
(414, 'estudio__vencimientos_generados', 'Instancias concretas de vencimientos mensuales a presentar/pagar', 4, 3, 1, 1),
(415, 'estudio__normas', 'Registro de normas legales y resoluciones de organismos fiscales', 4, 2, 1, 1),
(416, 'estudio__normas_condiciones', 'Reglas de aplicabilidad automatica de normas a clientes', 4, 1, 1, 1),
(417, 'estudio__normas_clientes_impacto', 'Bitacora de clientes alcanzados por cada norma', 4, 1, 1, 1),
(418, 'estudio__tareas_plantillas', 'Plantillas de tareas tipo para liquidaciones fiscales', 4, 1, 1, 1),
(419, 'estudio__tareas', 'Tareas operativas y flujo de trabajo con matriz RACI', 4, 3, 1, 1),
(420, 'estudio__tareas_tiempos', 'Time tracking y registro de horas dedicadas por tarea', 4, 1, 1, 1),
(421, 'estudio__licencias', 'Programacion de vacaciones y ausencias del personal', 4, 2, 1, 1),
(422, 'estudio__licencias_delegaciones', 'Delegaciones temporales de roles RACI por ausencia', 4, 2, 1, 1),
(423, 'gestion__activos_fijos', 'Padron de bienes de uso y parametros de amortizacion', 2, 3, 1, 1),
(424, 'gestion__activos_fijos_amortizaciones', 'Cuotas devengadas de amortizacion y asientos contables asociados', 2, 3, 1, 1),
(425, 'gestion__sync_cola', 'Cola de sincronizacion API cross-tenant para clientes externos', 2, 2, 1, 1)
ON DUPLICATE KEY UPDATE 
    `tabla_nombre` = VALUES(`tabla_nombre`),
    `tabla_descripcion` = VALUES(`tabla_descripcion`),
    `modulo_id` = VALUES(`modulo_id`),
    `tabla_tipo_id` = VALUES(`tabla_tipo_id`),
    `tabla_patron_id` = VALUES(`tabla_patron_id`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.3. Estados de Registros Específicos por Tabla (conf__tablas_estados_registros)
DELETE FROM `conf__tablas_estados_registros` WHERE `tabla_id` BETWEEN 401 AND 425;
INSERT INTO `conf__tablas_estados_registros` (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`) VALUES
-- estudio__clientes (402)
(402, 1, 'Activo', 1, 1, 0, 1),
(402, 2, 'Inactivo', 1, 0, 0, 2),
(402, 3, 'Suspendido', 1, 0, 0, 3),
-- estudio__vinculaciones (403)
(403, 4, 'Pendiente de Aprobacion', 1, 1, 0, 1),
(403, 5, 'Aprobada / Vinculada', 1, 0, 0, 2),
(403, 6, 'Revocada / Rechazada', 1, 0, 1, 3),
-- estudio__vencimientos_generados (414)
(414, 3, 'Borrador / Sin Iniciar', 1, 1, 0, 1),
(414, 4, 'En Liquidacion', 1, 0, 0, 2),
(414, 5, 'Listo para Presentar', 1, 0, 0, 3),
(414, 6, 'Presentado', 1, 0, 0, 4),
(414, 7, 'Pagado', 1, 0, 1, 5),
(414, 8, 'Anulado', 1, 0, 1, 6),
-- estudio__normas (415)
(415, 3, 'Borrador', 1, 1, 0, 1),
(415, 4, 'Evaluada / Pendiente Impacto', 1, 0, 0, 2),
(415, 5, 'Vigente / Impactada', 1, 0, 0, 3),
(415, 6, 'Derogada / Vencida', 1, 0, 1, 4),
-- estudio__tareas (419)
(419, 4, 'Pendiente', 1, 1, 0, 1),
(419, 5, 'En Proceso', 1, 0, 0, 2),
(419, 6, 'En Revision (A)', 1, 0, 0, 3),
(419, 7, 'Finalizada', 1, 0, 1, 4),
(419, 8, 'Cancelada', 1, 0, 1, 5),
-- estudio__licencias (421)
(421, 4, 'Programada', 1, 1, 0, 1),
(421, 5, 'En Curso / Delegada', 1, 0, 0, 2),
(421, 6, 'Finalizada / Revertida', 1, 0, 1, 3),
(421, 7, 'Cancelada', 1, 0, 1, 4),
-- gestion__activos_fijos (423)
(423, 1, 'En Servicio / Activo', 1, 1, 0, 1),
(423, 2, 'Totalmente Amortizado', 1, 0, 0, 2),
(423, 3, 'Dado de Baja / Vendido', 1, 0, 1, 3),
-- gestion__activos_fijos_amortizaciones (424)
(424, 3, 'Borrador', 1, 1, 0, 1),
(424, 5, 'Contabilizado', 1, 0, 1, 2);

-- 1.4. Arbol de Paginas en conf__paginas ($modulo_id = 4)
INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`) VALUES
(400, 4, 'Estudio Contable', '#', 74, 'Modulo de Estudio Contable y Auditoria', 0, 1, 0, 1, 1, 0),
(401, 4, 'Dashboard Estudio', 'dashboard_estudio.php', 70, 'Panel de control principal del estudio contable', 400, 1, 0, 1, 1, 1),
(402, 4, 'Padron de Clientes', 'estudio_clientes.php', 75, 'Gestion y padron de clientes del estudio', 400, 2, 402, 1, 1, 0),
(403, 4, 'Tablero de Vencimientos', 'vencimientos_tablero.php', 76, 'Tablero de control de vencimientos fiscales y previsionales', 400, 3, 414, 1, 1, 0),
(404, 4, 'Generador de Vencimientos', 'vencimientos_generador.php', 77, 'Generador automatico de vencimientos por calendario', 400, 4, 413, 1, 1, 0),
(405, 4, 'Tarifarios y Honorarios', 'honorarios_conceptos.php', 78, 'Tarifarios y conceptos de honorarios profesionales', 400, 5, 405, 1, 1, 0),
(406, 4, 'Facturacion de Abonos', 'honorarios_facturacion.php', 79, 'Emision y facturacion de abonos mensuales', 400, 6, 407, 1, 1, 0),
(407, 4, 'Normas y Resoluciones', 'normas_gestion.php', 80, 'Registro de resoluciones fiscales y reglas de impacto', 400, 7, 415, 1, 1, 0),
(408, 4, 'Matriz RACI y Tareas', 'tareas_raci.php', 81, 'Flujo de trabajo de liquidaciones y matriz RACI', 400, 8, 419, 1, 1, 0),
(409, 4, 'Licencias y Delegaciones', 'licencias_gestion.php', 82, 'Gestion de ausencias y delegacion automatica de tareas', 400, 9, 421, 1, 1, 0),
(410, 4, 'Activos Fijos y Amortiz.', 'activos_amortizaciones.php', 83, 'Gestion de bienes de uso y amortizaciones contables', 400, 10, 423, 1, 1, 0),
(411, 4, 'Consola de Auditoria ERP', 'auditoria_cliente.php', 84, 'Auditoria y revision cross-tenant de clientes ERP', 400, 11, 403, 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    `modulo_id` = VALUES(`modulo_id`),
    `pagina` = VALUES(`pagina`),
    `url` = VALUES(`url`),
    `icono_id` = VALUES(`icono_id`),
    `pagina_descripcion` = VALUES(`pagina_descripcion`),
    `padre_id` = VALUES(`padre_id`),
    `orden` = VALUES(`orden`),
    `tabla_id` = VALUES(`tabla_id`),
    `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- 1.5. Habilitacion del Modulo para Empresa Activa (empresa_id = 2) y Perfil Administrador
INSERT INTO `conf__empresas_modulos` (`empresa_id`, `modulo_id`, `tabla_estado_registro_id`)
SELECT 2, 4, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `conf__empresas_modulos` WHERE `empresa_id` = 2 AND `modulo_id` = 4);

-- 1.6. Perfiles y Permisos en conf__perfiles y conf___perfiles_paginas
INSERT INTO `conf__perfiles` (`perfil_id`, `modulo_id`, `perfil_nombre`, `tabla_estado_registro_id`) VALUES
(401, 4, 'Administrador Estudio Contable', 1),
(402, 4, 'Contador / Liquidador', 1),
(403, 4, 'Auditor Fiscal', 1)
ON DUPLICATE KEY UPDATE `perfil_nombre` = VALUES(`perfil_nombre`), `tabla_estado_registro_id` = VALUES(`tabla_estado_registro_id`);

-- Asignar todas las paginas del modulo 4 a Perfil 1 (Superadmin General) y Perfil 401 (Admin Estudio)
INSERT IGNORE INTO `conf___perfiles_paginas` (`perfil_id`, `pagina_id`, `estado_registro_id`)
SELECT 1, `pagina_id`, 1 FROM `conf__paginas` WHERE `modulo_id` = 4;

INSERT IGNORE INTO `conf___perfiles_paginas` (`perfil_id`, `pagina_id`, `estado_registro_id`)
SELECT 401, `pagina_id`, 1 FROM `conf__paginas` WHERE `modulo_id` = 4;

SET FOREIGN_KEY_CHECKS = 1;
SQL;

if (mysqli_multi_query($conn, $sql)) {
    do {
        if ($res = mysqli_store_result($conn)) {
            mysqli_free_result($res);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    echo "SUCCESS: All queries executed successfully!\n";
} else {
    echo "ERROR: " . mysqli_error($conn) . "\n";
}
