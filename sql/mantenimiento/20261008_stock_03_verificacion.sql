-- =====================================================================
-- Movimientos de stock por boca — ETAPA 3: verificación y reconstrucción
-- Fecha: 2026-10-08
--
-- Se puede correr cuando se quiera. La parte 1 solo lee; la parte 2 rearma gestion__stock.cantidad
-- desde el libro (el costo promedio no se toca). Equivale a stockRecalcular() de stock_core_model.php.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Diferencias entre el saldo materializado y el libro (debe devolver 0 filas)
-- ---------------------------------------------------------------------
SELECT x.empresa_id, x.boca_id, x.producto_id,
       COALESCE(s.cantidad, 0) AS materializado,
       x.libro,
       x.libro - COALESCE(s.cantidad, 0) AS diferencia
  FROM (SELECT empresa_id, boca_id, producto_id,
               SUM(CASE tipo_movimiento WHEN 'INGRESO' THEN cantidad ELSE -cantidad END) AS libro
          FROM gestion__stock_movimientos
         GROUP BY empresa_id, boca_id, producto_id) x
  LEFT JOIN gestion__stock s
         ON s.empresa_id = x.empresa_id AND s.boca_id = x.boca_id AND s.producto_id = x.producto_id
 WHERE COALESCE(s.cantidad, 0) <> x.libro
UNION ALL
SELECT s.empresa_id, s.boca_id, s.producto_id, s.cantidad, 0, -s.cantidad
  FROM gestion__stock s
 WHERE s.cantidad <> 0
   AND NOT EXISTS (SELECT 1 FROM gestion__stock_movimientos m
                    WHERE m.empresa_id = s.empresa_id AND m.boca_id = s.boca_id AND m.producto_id = s.producto_id);

-- ---------------------------------------------------------------------
-- 2) Reconstrucción (descomentar para aplicar)
-- ---------------------------------------------------------------------
-- START TRANSACTION;
-- INSERT INTO gestion__stock (empresa_id, boca_id, producto_id, sucursal_id, cantidad)
-- SELECT m.empresa_id, m.boca_id, m.producto_id, MAX(m.sucursal_id), 0
--   FROM gestion__stock_movimientos m
--  GROUP BY m.empresa_id, m.boca_id, m.producto_id
-- ON DUPLICATE KEY UPDATE cantidad = gestion__stock.cantidad;
--
-- UPDATE gestion__stock s
--   LEFT JOIN (SELECT empresa_id, boca_id, producto_id,
--                     SUM(CASE tipo_movimiento WHEN 'INGRESO' THEN cantidad ELSE -cantidad END) AS libro,
--                     MAX(f_movimiento) AS f_ultimo
--                FROM gestion__stock_movimientos
--               GROUP BY empresa_id, boca_id, producto_id) x
--          ON x.empresa_id = s.empresa_id AND x.boca_id = s.boca_id AND x.producto_id = s.producto_id
--    SET s.cantidad = COALESCE(x.libro, 0),
--        s.f_ultimo_movimiento = x.f_ultimo;
-- COMMIT;

-- ---------------------------------------------------------------------
-- 3) Documentos con stock aplicado según configuración pero sin movimientos en el libro
--    (p. ej. remitos/facturas confirmados antes de este módulo). Informativo.
-- ---------------------------------------------------------------------
SELECT c.tabla_origen_id, c.registro_origen_id, c.comprobante_id, ct.codigo, c.tabla_estado_registro_id
  FROM gestion__comprobantes c
  JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id AND ct.impacta_stock = 1
  JOIN conf__tablas_estados_registros ter
       ON ter.tabla_id = c.tabla_origen_id AND ter.estado_registro_id = c.tabla_estado_registro_id AND ter.aplica_stock = 1
 WHERE NOT EXISTS (SELECT 1 FROM gestion__stock_movimientos m WHERE m.comprobante_id = c.comprobante_id);
