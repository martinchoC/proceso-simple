-- =====================================================================================================
-- 20261107_stock_ajustes_config_01.sql
-- Tipos de comprobante de Ajustes de Stock (páginas 77 / 577, subgrupo CEF "Ajuste de Stock" de cada empresa).
--
-- 1) Alta del tipo S_AJ "Ajuste por inventario (+/-)" en cada empresa que tiene el subgrupo CEF activo:
--    stock_signo '+/-' = cada línea suma o resta según su diferencia (conteo físico). Desde el 2026-11-07
--    los tipos '+' (Sobrante, S_AP) y '-' (Faltante, S_AN) rechazan líneas con el signo contrario
--    (stockAjusteValidarSignos en stock_ajustes_model.php), así que un conteo con sobrantes y faltantes
--    a la vez necesita este tipo. Se copia de S_AP de la misma empresa (grupo, subgrupo, flags).
-- 2) Inactiva (estado 2) los tipos de remito que estaban en el subgrupo de ajustes y se ofrecían en el combo
--    de Ajustes de Stock: S_RCPe "Remito de Salida Pedidos" y S_RE "Remito de Entrada". No los usa ningún
--    comprobante, ajuste ni punto de venta (verificado en local el 2026-11-07); los remitos de venta reales
--    son S_RSA / S_RSX del subgrupo "Remito de Salida" (tabla 81). Se reactivan desde Tipos de Comprobante.
--
-- Va junto con el código que lee el subgrupo CEF (antes AST, inactivo y sin tipos). Se puede reanudar.
-- =====================================================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Control previo: si algún remito a inactivar está en uso, revisar antes (debería devolver 0 filas)
SELECT * FROM (
    SELECT t.empresa_id, t.comprobante_tipo_id, t.codigo,
           (SELECT COUNT(*) FROM gestion__comprobantes c WHERE c.comprobante_tipo_id = t.comprobante_tipo_id) AS comprobantes,
           (SELECT COUNT(*) FROM gestion__stock_ajustes a WHERE a.comprobante_tipo_id = t.comprobante_tipo_id) AS ajustes,
           (SELECT COUNT(*) FROM gestion__puntos_venta_comprobantes p WHERE p.comprobante_tipo_id = t.comprobante_tipo_id) AS puntos_venta
    FROM gestion__comprobantes_tipos t
    JOIN gestion__comprobantes_subgrupos s ON s.comprobante_subgrupo_id = t.comprobante_subgrupo_id AND s.empresa_id = t.empresa_id
    WHERE s.codigo = 'CEF' AND t.codigo IN ('S_RCPe', 'S_RE')
) uso
WHERE uso.comprobantes + uso.ajustes + uso.puntos_venta > 0;

-- 1) Ajuste por inventario (+/-)
INSERT INTO gestion__comprobantes_tipos
    (empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id,
     impacta_stock, stock_signo, impacta_contabilidad, impacta_ctacte, requiere_debito_cuenta,
     comprobante_tipo, codigo, letra, signo, comentario, orden, tabla_estado_registro_id)
SELECT t.empresa_id, t.comprobante_grupo_id, t.comprobante_subgrupo_id, 0,
       1, '+/-', t.impacta_contabilidad, 0, 0,
       'Ajuste por inventario (+/-)', 'S_AJ', t.letra, '+/-',
       'Conteo físico: cada línea suma o resta según su diferencia', t.orden + 1, 1
FROM gestion__comprobantes_tipos t
JOIN gestion__comprobantes_subgrupos s ON s.comprobante_subgrupo_id = t.comprobante_subgrupo_id
                                      AND s.empresa_id = t.empresa_id
                                      AND s.codigo = 'CEF' AND s.tabla_estado_registro_id = 1
WHERE t.codigo = 'S_AP'
  AND NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos x WHERE x.empresa_id = t.empresa_id AND x.codigo = 'S_AJ');

-- 2) Remitos fuera del combo de ajustes
UPDATE gestion__comprobantes_tipos t
JOIN gestion__comprobantes_subgrupos s ON s.comprobante_subgrupo_id = t.comprobante_subgrupo_id AND s.empresa_id = t.empresa_id
SET t.tabla_estado_registro_id = 2
WHERE s.codigo = 'CEF' AND t.codigo IN ('S_RCPe', 'S_RE') AND t.tabla_estado_registro_id = 1;

-- Verificación: tipos activos del subgrupo de ajustes por empresa (S_AN -, S_AP +, S_AJ +/-)
SELECT t.empresa_id, t.comprobante_tipo_id, t.codigo, t.comprobante_tipo, t.stock_signo, t.tabla_estado_registro_id
FROM gestion__comprobantes_tipos t
JOIN gestion__comprobantes_subgrupos s ON s.comprobante_subgrupo_id = t.comprobante_subgrupo_id AND s.empresa_id = t.empresa_id
WHERE s.codigo = 'CEF'
ORDER BY t.empresa_id, t.tabla_estado_registro_id, t.codigo;
