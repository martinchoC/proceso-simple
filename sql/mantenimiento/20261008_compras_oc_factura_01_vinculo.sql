-- =====================================================================
-- Compras - script 01: VÍNCULO ORDEN DE COMPRA -> FACTURA DE PROVEEDOR
-- 2026-10-08
--
-- Circuito: OC -> factura de proveedor, o factura sola. Solo la factura
-- confirmada (estado 5, aplica_stock en tabla 84) mueve stock; la OC nunca.
--
-- Decisiones (2026-10-08):
--   * Recepciones parciales: varias facturas por OC. Estado genérico nuevo
--     41 "Recibido parcial" (RECIB_PARCIAL).
--   * La factura importa las líneas pendientes de UNA OC (cabecera
--     orden_compra_id) y cada línea guarda su ordenes_compra_detalle_id.
--     Puede traer más cantidad o productos que no están en la OC.
--   * gestion__ordenes_compra_detalle.cantidad_recibida = suma de las líneas
--     de facturas CONFIRMADAS vinculadas; la recalcula
--     ocRecalcularRecepcion() (facturas_proveedores_model.php) en cada
--     transición de la factura. Estado derivado sobre OC en 5/7/41:
--     todo cubierto -> 8 Recibido; algo recibido -> 41; nada -> sin cambio.
--   * "Recibido" manual (-> 8) queda como cierre manual, también desde 41.
--
-- Qué hace:
--   1) Columnas orden_compra_id (cabecera) y ordenes_compra_detalle_id
--      (detalle) en las facturas de proveedor, con índices.
--   2) Estado genérico 41 y su fila en el catálogo de la tabla 76 (id 222).
--   3) Página 51 (Órdenes de Compra): Visualizar, Imprimir y Recibido desde
--      41 (ids 3363-3365), con los permisos de sus equivalentes desde 7/8.
--
-- Reanudable: cada paso controla si ya se aplicó. MariaDB 10.4 / MySQL 8.0.
-- =====================================================================

-- 1) Columnas de vínculo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores'
                  AND COLUMN_NAME = 'orden_compra_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__facturas_proveedores` ADD COLUMN `orden_compra_id` INT(11) NULL DEFAULT NULL COMMENT ''gestion__ordenes_compra importada (NULL = factura sin OC)'' AFTER `comprobante_id`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores'
                  AND INDEX_NAME = 'idx_fp_orden_compra');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__facturas_proveedores` ADD INDEX `idx_fp_orden_compra` (`orden_compra_id`)',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle'
                  AND COLUMN_NAME = 'ordenes_compra_detalle_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__facturas_proveedores_detalle` ADD COLUMN `ordenes_compra_detalle_id` INT(11) NULL DEFAULT NULL COMMENT ''Línea de OC que recibe (NULL = línea sin OC)'' AFTER `producto_id`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__facturas_proveedores_detalle'
                  AND INDEX_NAME = 'idx_fpd_oc_detalle');
SET @sql := IF(@existe = 0,
    'ALTER TABLE `gestion__facturas_proveedores_detalle` ADD INDEX `idx_fpd_oc_detalle` (`ordenes_compra_detalle_id`)',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Estado genérico 41 "Recibido parcial" (mismo color que 8 Recibido)
INSERT INTO conf__estados_registros (estado_registro_id, estado_registro, codigo_estandar, valor_estandar, color_id, orden_estandar)
SELECT 41, 'Recibido parcial', 'RECIB_PARCIAL', NULL, er.color_id, er.orden_estandar
FROM conf__estados_registros er
WHERE er.estado_registro_id = 8
  AND NOT EXISTS (SELECT 1 FROM conf__estados_registros x WHERE x.estado_registro_id = 41);

INSERT INTO conf__tablas_estados_registros
    (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, es_final, orden, aplica_stock)
SELECT 222, 76, 41, 'Recibido parcial', ter.color_id, 0, 0, ter.orden, 0
FROM conf__tablas_estados_registros ter
WHERE ter.tabla_id = 76 AND ter.estado_registro_id = 8
  AND NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros x WHERE x.tabla_id = 76 AND x.estado_registro_id = 41);

-- 3) Funciones de la página 51 desde 41
--    3363 Visualizar (base 240, 7->7), 3364 Imprimir (base 238, 8->8), 3365 Recibido (base 232, 7->8)
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT n.id, pf.pagina_id, pf.icono_id, pf.color_id, pf.funcion_estandar_id, n.nombre, pf.accion_js,
       pf.descripcion, 41, n.destino, pf.orden, pf.tabla_estado_registro_id
FROM (
          SELECT 3363 AS id, 240 AS base, 'Visualizar' AS nombre, 41 AS destino
    UNION SELECT 3364, 238, 'Imprimir', 41
    UNION SELECT 3365, 232, 'Recibido (cerrar)', 8
) n
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = n.base
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = n.id);

-- Permisos copiados de la función base
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT p.perfil_id, n.id, p.asignado
FROM (
          SELECT 3363 AS id, 240 AS base
    UNION SELECT 3364, 238
    UNION SELECT 3365, 232
) n
JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = n.base
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT p.empresa_id, p.empresa_perfil_id, n.id, p.asignado
FROM (
          SELECT 3363 AS id, 240 AS base
    UNION SELECT 3364, 238
    UNION SELECT 3365, 232
) n
JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = n.base
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

-- Verificación
SELECT 'columnas' AS paso, COUNT(*) AS n FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME = 'gestion__facturas_proveedores' AND COLUMN_NAME = 'orden_compra_id')
    OR (TABLE_NAME = 'gestion__facturas_proveedores_detalle' AND COLUMN_NAME = 'ordenes_compra_detalle_id'))
UNION ALL SELECT 'estado 41', COUNT(*) FROM conf__estados_registros WHERE estado_registro_id = 41
UNION ALL SELECT 'catalogo 76/41', COUNT(*) FROM conf__tablas_estados_registros WHERE tabla_id = 76 AND estado_registro_id = 41
UNION ALL SELECT 'funciones', COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3363, 3364, 3365)
UNION ALL SELECT 'permisos empresa', COUNT(*) FROM conf__empresas_perfiles_funciones WHERE pagina_funcion_id IN (3363, 3364, 3365);
