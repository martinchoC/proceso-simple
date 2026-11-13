-- =====================================================================================================
-- PRODUCCIÓN — cambios del 2026-11-03 en un solo script (Developsam y Casa Lucho)
-- -----------------------------------------------------------------------------------------------------
-- Junta, con protecciones para producción, lo de:
--   20261103_imputaciones_documento_recibo_01.sql  (recibo en $0 de la aplicación de NC: detalle + / -)
--   20261103_ventas_remitos_anular_01.sql          (anular remitos, Facturar con ícono de comprobante, Consultar)
--   20261103_facturas_proveedores_botones_01.sql   (íconos de Pasar a aprobación / Visualizar / Anular / Imprimir)
--   20261103_stock_consulta_01_pagina.sql          (página Stock y Movimientos 138 / 638)
--
-- ORDEN: 1) subir el código; 2) correr este script. (Los cambios de pantalla y de modelo sin script, como el logo
-- de la barra, las columnas de entidades o la cuenta corriente, son solo código.)
-- Casa Lucho: recién después de la migración (20261031_migracion_casalucho_01..04): necesita las páginas 88, 114
-- y la función 3366 que agrega esa migración. Si no están, las altas que dependen de ellas se saltean (ver control).
--
-- Protecciones:
--   * Si algún id que usa este script está ocupado por OTRA cosa, @choques > 0 y no se inserta nada (ver el
--     primer SELECT). Revisar y renumerar antes de volver a correrlo.
--   * Ninguna alta pisa filas existentes (sin ON DUPLICATE KEY UPDATE); cada una exige su página o función de referencia.
--   * Reanudable: el DDL (paso 1) no es transaccional; correrlo de nuevo no duplica nada.
-- MySQL 8 y MariaDB 10.4.
-- =====================================================================================================

-- -----------------------------------------------------------------------------------------------------
-- 0. Choques de ids (debe devolver 0 filas)
-- -----------------------------------------------------------------------------------------------------
SELECT 'conf__paginas_funciones' AS tabla, pagina_funcion_id AS id, pagina_id, nombre_funcion AS detalle
FROM conf__paginas_funciones
WHERE (pagina_funcion_id IN (3523, 3524, 3525) AND pagina_id <> 88)
   OR (pagina_funcion_id IN (8523, 8524, 8525) AND pagina_id <> 588)
   OR (pagina_funcion_id = 3526 AND pagina_id <> 138)
   OR (pagina_funcion_id = 8526 AND pagina_id <> 638)
UNION ALL
SELECT 'conf__tablas_estados_registros', tabla_estado_registro_id, tabla_id, tabla_estado_registro
FROM conf__tablas_estados_registros
WHERE tabla_estado_registro_id = 261 AND NOT (tabla_id = 81 AND estado_registro_id = 38)
UNION ALL
SELECT 'conf__paginas', pagina_id, modulo_id, url
FROM conf__paginas
WHERE pagina_id IN (138, 638) AND url <> 'stock_consulta.php';

SET @choques := (SELECT COUNT(*) FROM conf__paginas_funciones
                 WHERE (pagina_funcion_id IN (3523, 3524, 3525) AND pagina_id <> 88)
                    OR (pagina_funcion_id IN (8523, 8524, 8525) AND pagina_id <> 588)
                    OR (pagina_funcion_id = 3526 AND pagina_id <> 138)
                    OR (pagina_funcion_id = 8526 AND pagina_id <> 638))
              + (SELECT COUNT(*) FROM conf__tablas_estados_registros
                 WHERE tabla_estado_registro_id = 261 AND NOT (tabla_id = 81 AND estado_registro_id = 38))
              + (SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (138, 638) AND url <> 'stock_consulta.php');
SELECT @choques AS choques_de_ids;

-- -----------------------------------------------------------------------------------------------------
-- 1. Recibo en $0 de la aplicación de NC: columna que liga la imputación al recibo (DDL, fuera de transacción)
-- -----------------------------------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_imputaciones' AND COLUMN_NAME = 'documento_comprobante_id');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__comprobantes_imputaciones ADD COLUMN documento_comprobante_id INT NULL DEFAULT NULL COMMENT ''Recibo en $0 que documenta la imputación (aplicación de NC)'' AFTER importe_origen',
    'SELECT ''documento_comprobante_id ya existe''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_imputaciones' AND INDEX_NAME = 'idx_imp_documento');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__comprobantes_imputaciones ADD INDEX idx_imp_documento (documento_comprobante_id)',
    'SELECT ''idx_imp_documento ya existe''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

START TRANSACTION;

-- -----------------------------------------------------------------------------------------------------
-- 2. Remitos de Pedidos (88 / 588) y Remitos Pendientes Facturación (364 / 864)
-- -----------------------------------------------------------------------------------------------------
-- 2a. Estado Anulado en el catálogo de remitos (tabla 81)
INSERT INTO conf__tablas_estados_registros (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro,
                                            color_id, es_inicial, es_final, orden, aplica_stock)
SELECT 261, 81, 38, 'Anulado', 1, 0, 1, 90, 0 FROM DUAL
WHERE @choques = 0
  AND NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros WHERE tabla_id = 81 AND estado_registro_id = 38)
  AND NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id = 261);

-- 2b. Anular (13 -> 38), Consultar e Imprimir en 38
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT n.id, n.pagina_id, n.icono_id, n.color_id, 1, n.nombre, n.accion, n.descripcion, n.origen, n.destino, n.orden, 1
FROM (SELECT 3523 AS id, 88 AS pagina_id, 45 AS icono_id, 4 AS color_id, 'Anular' AS nombre, 'anular' AS accion,
             'Anula el remito: los productos vuelven al pedido como pendientes de entrega y se devuelve el stock' AS descripcion,
             13 AS origen, 38 AS destino, 90 AS orden
      UNION ALL SELECT 3524, 88, 37, 7, 'Consultar', 'visualizar', '', 38, 38, 50
      UNION ALL SELECT 3525, 88, 36, 8, 'Imprimir', 'imprimir', '', 38, 38, 58
      UNION ALL SELECT 8523, 588, 45, 4, 'Anular', 'anular',
             'Anula el remito: los productos vuelven al pedido como pendientes de entrega y se devuelve el stock', 13, 38, 90
      UNION ALL SELECT 8524, 588, 37, 7, 'Consultar', 'visualizar', '', 38, 38, 50
      UNION ALL SELECT 8525, 588, 36, 8, 'Imprimir', 'imprimir', '', 38, 38, 58) n
WHERE @choques = 0
  AND EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = n.pagina_id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = n.pagina_id AND f.accion_js = n.accion
                                                             AND f.tabla_estado_registro_origen_id = n.origen);

-- 2c. Facturar: ícono de comprobante (90 bi-receipt)
UPDATE conf__paginas_funciones SET icono_id = 90
WHERE @choques = 0 AND pagina_id IN (88, 364, 588, 864) AND accion_js = 'facturado_parcial'
  AND EXISTS (SELECT 1 FROM (SELECT icono_id FROM conf__iconos WHERE icono_id = 90 AND icono_clase = 'bi bi-receipt') i);

-- 2d. Facturado -> Consultar en 13; el de 15 duplicaba Visualizar y se desactiva; Visualizar se llama Consultar
UPDATE conf__paginas_funciones
SET nombre_funcion = 'Consultar', accion_js = 'visualizar', tabla_estado_registro_destino_id = 13, icono_id = 37, color_id = 7
WHERE @choques = 0 AND pagina_id IN (88, 364, 588, 864) AND accion_js = 'facturar' AND tabla_estado_registro_origen_id = 13;

UPDATE conf__paginas_funciones SET tabla_estado_registro_id = 2
WHERE @choques = 0 AND pagina_id IN (88, 364, 588, 864) AND accion_js = 'facturar' AND tabla_estado_registro_origen_id = 15;

UPDATE conf__paginas_funciones SET nombre_funcion = 'Consultar'
WHERE @choques = 0 AND pagina_id IN (88, 364, 588, 864) AND accion_js = 'visualizar';

-- 2e. Permisos: Anular como "Confirmar"; Consultar / Imprimir en 38 como "Visualizar" (base y por empresa)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfa.perfil_id, pf.pagina_funcion_id, 1
FROM conf__perfiles_funciones pfa
JOIN conf__paginas_funciones ref ON ref.pagina_funcion_id = pfa.pagina_funcion_id AND ref.pagina_id IN (88, 588)
JOIN conf__paginas_funciones pf ON pf.pagina_id = ref.pagina_id AND pf.pagina_funcion_id IN (3523, 3524, 3525, 8523, 8524, 8525)
     AND ((pf.accion_js = 'anular' AND ref.accion_js = 'confirmar') OR (pf.accion_js <> 'anular' AND ref.accion_js = 'visualizar'))
WHERE @choques = 0 AND pfa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pfa.perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM conf__empresas_perfiles_funciones epa
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epa.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones ref ON ref.pagina_funcion_id = epa.pagina_funcion_id AND ref.pagina_id IN (88, 588)
JOIN conf__paginas_funciones pf ON pf.pagina_id = ref.pagina_id AND pf.pagina_funcion_id IN (3523, 3524, 3525, 8523, 8524, 8525)
     AND ((pf.accion_js = 'anular' AND ref.accion_js = 'confirmar') OR (pf.accion_js <> 'anular' AND ref.accion_js = 'visualizar'))
WHERE @choques = 0 AND epa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

-- -----------------------------------------------------------------------------------------------------
-- 3. Facturas de Proveedores (57 / 557): botones distintos
-- -----------------------------------------------------------------------------------------------------
INSERT INTO conf__iconos (icono_id, icono_nombre, icono_clase, tabla_estado_registro_id)
SELECT GREATEST(146, (SELECT MAX(i.icono_id) + 1 FROM conf__iconos i)), 'Enviar a aprobación', 'bi bi-send-fill', 1 FROM DUAL
WHERE @choques = 0 AND NOT EXISTS (SELECT 1 FROM conf__iconos WHERE icono_clase = 'bi bi-send-fill');

SET @icono_enviar := (SELECT icono_id FROM conf__iconos WHERE icono_clase = 'bi bi-send-fill' ORDER BY icono_id LIMIT 1);

UPDATE conf__paginas_funciones SET icono_id = @icono_enviar, color_id = 1
WHERE @choques = 0 AND @icono_enviar IS NOT NULL AND pagina_id IN (57, 557) AND accion_js = 'aprobar';
UPDATE conf__paginas_funciones SET icono_id = 37, color_id = 7
WHERE @choques = 0 AND pagina_id IN (57, 557) AND accion_js = 'visualizar';
UPDATE conf__paginas_funciones SET icono_id = 45, color_id = 4
WHERE @choques = 0 AND pagina_id IN (57, 557) AND accion_js = 'anular';
UPDATE conf__paginas_funciones SET icono_id = 36, color_id = 8
WHERE @choques = 0 AND pagina_id IN (57, 557) AND accion_js = 'imprimir';

-- -----------------------------------------------------------------------------------------------------
-- 4. Página Stock y Movimientos (138 / 638) bajo el menú Stock (44 / 544)
-- -----------------------------------------------------------------------------------------------------
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT n.id, n.modulo, 'Stock - Stock y Movimientos', 'stock_consulta.php', 5,
       'Stock por producto y boca y movimientos, con filtros de sucursal, boca, producto y período', n.padre, 95, 0, 1, 1, 1, 0, 0
FROM (SELECT 138 AS id, 2 AS modulo, 44 AS padre UNION ALL SELECT 638, 5, 544) n
WHERE @choques = 0
  AND EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = n.padre)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = n.id);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT n.id, n.pagina_id, 37, 1, 1, 'Ver Stock y Movimientos', 'consultar', '', 0, 0, 1, 1
FROM (SELECT 3526 AS id, 138 AS pagina_id UNION ALL SELECT 8526, 638) n
WHERE @choques = 0
  AND EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = n.pagina_id)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = n.id);

-- Permisos: los perfiles que ven el Dashboard de stock (3366 / 8366)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfa.perfil_id, CASE pfa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END, 1
FROM conf__perfiles_funciones pfa
WHERE @choques = 0 AND pfa.pagina_funcion_id IN (3366, 8366) AND pfa.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = CASE pfa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END)
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = pfa.perfil_id AND x.pagina_funcion_id = CASE pfa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, CASE epa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END, 1
FROM conf__empresas_perfiles_funciones epa
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epa.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
WHERE @choques = 0 AND epa.pagina_funcion_id IN (3366, 8366) AND epa.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = CASE epa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END)
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id
                    AND x.pagina_funcion_id = CASE epa.pagina_funcion_id WHEN 3366 THEN 3526 ELSE 8526 END);

COMMIT;

-- -----------------------------------------------------------------------------------------------------
-- Control (solo lectura): qué quedó aplicado. Una fila con "NO" = faltaba algo de lo que depende.
-- -----------------------------------------------------------------------------------------------------
SELECT 'choques de ids' AS control, IF(@choques = 0, 'OK', CONCAT('NO: ', @choques, ' (no se aplicó nada)')) AS resultado
UNION ALL SELECT 'columna documento_comprobante_id',
       IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'gestion__comprobantes_imputaciones' AND COLUMN_NAME = 'documento_comprobante_id'), 'OK', 'NO')
UNION ALL SELECT 'remitos: estado Anulado (tabla 81)',
       IF(EXISTS (SELECT 1 FROM conf__tablas_estados_registros WHERE tabla_id = 81 AND estado_registro_id = 38), 'OK', 'NO')
UNION ALL SELECT 'remitos: Anular en 88 / 588',
       CONCAT((SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id IN (3523, 8523)), ' de 2 funciones, ',
              (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones WHERE pagina_funcion_id IN (3523, 8523) AND asignado = 1), ' perfiles de empresa')
UNION ALL SELECT 'facturas proveedores: ícono enviar', IF(@icono_enviar IS NOT NULL, CONCAT('OK (id ', @icono_enviar, ')'), 'NO')
UNION ALL SELECT 'Stock y Movimientos: páginas 138 / 638',
       CONCAT((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (138, 638)), ' de 2 páginas, ',
              (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones WHERE pagina_funcion_id IN (3526, 8526) AND asignado = 1), ' perfiles de empresa');
