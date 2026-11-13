-- =====================================================================================================
-- Remitos de Pedidos (páginas 88 / 588) y Remitos Pendientes Facturación (364 / 864)
-- -----------------------------------------------------------------------------------------------------
-- 1. Estado 38 Anulado en el catálogo de remitos (tabla 81), id 261, final, sin stock.
-- 2. Función "Anular" 13 Pend. de Facturación -> 38 en las páginas 88 / 588 (3523 / 8523), más "Consultar"
--    e "Imprimir" en 38 (3524-3525 / 8524-8525). Al anular, ejecutarTransicionEstadoRemito() (código
--    ANULADO) devuelve las cantidades al pedido como pendientes de entrega, recalcula el estado del pedido
--    y devuelve el stock; no se puede si alguna factura (también un borrador) usa líneas del remito.
--    Solo desde 13: con facturación parcial ya hay una factura.
-- 3. "Facturar" (facturado_parcial) con ícono de comprobante (90 bi-receipt) en vez del de persona (8).
-- 4. "Facturado" (facturar) pasa a "Consultar" (visualizar, destino = origen) en 13; en 15 ya existía
--    "Visualizar", así que la de 15 se desactiva. "Visualizar" de estas páginas se llama "Consultar".
-- Permisos de las funciones nuevas: Anular = perfiles con "Confirmar" del remito; Consultar / Imprimir
-- en 38 = perfiles con "Visualizar" del remito (base y por empresa).
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

START TRANSACTION;

-- 1. Estado Anulado de la tabla de remitos
INSERT INTO conf__tablas_estados_registros (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro,
                                            color_id, es_inicial, es_final, orden, aplica_stock)
SELECT 261, 81, 38, 'Anulado', 1, 0, 1, 90, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros WHERE tabla_id = 81 AND estado_registro_id = 38);

-- 2. Anular / Consultar / Imprimir
INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
VALUES (3523, 88, 45, 4, 1, 'Anular', 'anular', 'Anula el remito: los productos vuelven al pedido como pendientes de entrega y se devuelve el stock', 13, 38, 90, 1),
       (3524, 88, 37, 7, 1, 'Consultar', 'visualizar', '', 38, 38, 50, 1),
       (3525, 88, 36, 8, 1, 'Imprimir', 'imprimir', '', 38, 38, 58, 1),
       (8523, 588, 45, 4, 1, 'Anular', 'anular', 'Anula el remito: los productos vuelven al pedido como pendientes de entrega y se devuelve el stock', 13, 38, 90, 1),
       (8524, 588, 37, 7, 1, 'Consultar', 'visualizar', '', 38, 38, 50, 1),
       (8525, 588, 36, 8, 1, 'Imprimir', 'imprimir', '', 38, 38, 58, 1)
ON DUPLICATE KEY UPDATE pagina_id = VALUES(pagina_id), icono_id = VALUES(icono_id), color_id = VALUES(color_id),
                        nombre_funcion = VALUES(nombre_funcion), accion_js = VALUES(accion_js), descripcion = VALUES(descripcion),
                        tabla_estado_registro_origen_id = VALUES(tabla_estado_registro_origen_id),
                        tabla_estado_registro_destino_id = VALUES(tabla_estado_registro_destino_id),
                        orden = VALUES(orden), tabla_estado_registro_id = 1;

-- 3. Facturar: ícono de comprobante
UPDATE conf__paginas_funciones SET icono_id = 90
WHERE pagina_id IN (88, 364, 588, 864) AND accion_js = 'facturado_parcial';

-- 4. Facturado -> Consultar (en 13) y fuera el duplicado de 15
UPDATE conf__paginas_funciones
SET nombre_funcion = 'Consultar', accion_js = 'visualizar', tabla_estado_registro_destino_id = 13, icono_id = 37, color_id = 7
WHERE pagina_id IN (88, 364, 588, 864) AND accion_js = 'facturar' AND tabla_estado_registro_origen_id = 13;

UPDATE conf__paginas_funciones SET tabla_estado_registro_id = 2
WHERE pagina_id IN (88, 364, 588, 864) AND accion_js = 'facturar' AND tabla_estado_registro_origen_id = 15;

UPDATE conf__paginas_funciones SET nombre_funcion = 'Consultar'
WHERE pagina_id IN (88, 364, 588, 864) AND accion_js = 'visualizar';

-- Permisos: Anular como "Confirmar"; Consultar / Imprimir en 38 como "Visualizar"
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfa.perfil_id, pf.pagina_funcion_id, 1
FROM conf__perfiles_funciones pfa
JOIN conf__paginas_funciones ref ON ref.pagina_funcion_id = pfa.pagina_funcion_id AND ref.pagina_id IN (88, 588)
JOIN conf__paginas_funciones pf ON pf.pagina_id = ref.pagina_id AND pf.pagina_funcion_id IN (3523, 3524, 3525, 8523, 8524, 8525)
     AND ((pf.accion_js = 'anular' AND ref.accion_js = 'confirmar') OR (pf.accion_js <> 'anular' AND ref.accion_js = 'visualizar'))
WHERE pfa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = pfa.perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
FROM conf__empresas_perfiles_funciones epa
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epa.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
JOIN conf__paginas_funciones ref ON ref.pagina_funcion_id = epa.pagina_funcion_id AND ref.pagina_id IN (88, 588)
JOIN conf__paginas_funciones pf ON pf.pagina_id = ref.pagina_id AND pf.pagina_funcion_id IN (3523, 3524, 3525, 8523, 8524, 8525)
     AND ((pf.accion_js = 'anular' AND ref.accion_js = 'confirmar') OR (pf.accion_js <> 'anular' AND ref.accion_js = 'visualizar'))
WHERE epa.asignado = 1
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.pagina_funcion_id = pf.pagina_funcion_id);

COMMIT;

-- Control (solo lectura)
SELECT pf.pagina_id, pf.pagina_funcion_id, pf.nombre_funcion, pf.accion_js, pf.tabla_estado_registro_origen_id AS origen,
       pf.tabla_estado_registro_destino_id AS destino, pf.icono_id, pf.tabla_estado_registro_id AS activa,
       COUNT(epf.empresa_perfil_id) AS perfiles_con_permiso
FROM conf__paginas_funciones pf
LEFT JOIN conf__empresas_perfiles_funciones epf ON epf.pagina_funcion_id = pf.pagina_funcion_id AND epf.asignado = 1
WHERE pf.pagina_id IN (88, 364, 588, 864)
GROUP BY pf.pagina_id, pf.pagina_funcion_id, pf.nombre_funcion, pf.accion_js, pf.tabla_estado_registro_origen_id,
         pf.tabla_estado_registro_destino_id, pf.icono_id, pf.tabla_estado_registro_id
ORDER BY pf.pagina_id, pf.tabla_estado_registro_origen_id, pf.orden;
