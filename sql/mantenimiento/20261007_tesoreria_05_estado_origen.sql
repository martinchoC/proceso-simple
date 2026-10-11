-- =====================================================================
-- Tesorería - script 05: ESTADO DERIVADO DEL COMPROBANTE -> TABLA DE ORIGEN
-- 2026-10-07
--
-- Decisión (2026-10-07): cuando recalcularEstadoDestino() mueve un
--   comprobante entre Confirmado (5) / Cancelado parcial (39) / Cancelado
--   total (40), el registro de la tabla de origen pasa al mismo estado
--   (tesoreriaSincronizarEstadoOrigen() en tesoreria_core_model.php), solo
--   si ese estado figura en conf__tablas_estados_registros de la tabla de
--   origen. Hoy eso aplica a gestion__ventas_facturas (tabla 80).
--
-- Qué hace:
--   1) Página 56 (Facturación): Visualizar e Imprimir también desde 39 y 40
--      (ids 3345-3348), para que una factura cancelada no se quede sin botones.
--   2) Permisos de esas funciones: los mismos perfiles que tienen 366
--      (Visualizar desde 5) y 367 (Imprimir desde 5), en
--      conf__perfiles_funciones y conf__empresas_perfiles_funciones.
--   3) Datos ya existentes: ventas_facturas confirmadas cuyo comprobante
--      ya está en 39/40 toman ese estado.
--
-- Reanudable: cada paso controla si ya se aplicó.
-- =====================================================================

-- 1) Funciones de la página 56 para los estados 39 y 40
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT n.id, pf.pagina_id, pf.icono_id, pf.color_id, pf.funcion_estandar_id, pf.nombre_funcion, pf.accion_js,
       pf.descripcion, n.estado, n.estado, pf.orden, pf.tabla_estado_registro_id
FROM (
          SELECT 3345 AS id, 366 AS base, 39 AS estado
    UNION SELECT 3346, 367, 39
    UNION SELECT 3347, 366, 40
    UNION SELECT 3348, 367, 40
) n
JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = n.base
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = n.id);

-- 2) Permisos copiados de la función base
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT p.perfil_id, n.id, p.asignado
FROM (
          SELECT 3345 AS id, 366 AS base
    UNION SELECT 3346, 367
    UNION SELECT 3347, 366
    UNION SELECT 3348, 367
) n
JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = n.base
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT p.empresa_id, p.empresa_perfil_id, n.id, p.asignado
FROM (
          SELECT 3345 AS id, 366 AS base
    UNION SELECT 3346, 367
    UNION SELECT 3347, 366
    UNION SELECT 3348, 367
) n
JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = n.base
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

-- 3) Facturas de venta cuyo comprobante ya quedó cancelado
UPDATE gestion__ventas_facturas f
JOIN gestion__comprobantes c ON c.tabla_origen_id = 80 AND c.registro_origen_id = f.venta_factura_id
SET f.tabla_estado_registro_id = c.tabla_estado_registro_id
WHERE c.tabla_estado_registro_id IN (39, 40)
  AND f.tabla_estado_registro_id IN (5, 39, 40)
  AND f.tabla_estado_registro_id <> c.tabla_estado_registro_id;

-- Verificación
SELECT f.tabla_estado_registro_id AS estado_factura, c.tabla_estado_registro_id AS estado_comprobante, COUNT(*) AS n
FROM gestion__ventas_facturas f
JOIN gestion__comprobantes c ON c.tabla_origen_id = 80 AND c.registro_origen_id = f.venta_factura_id
GROUP BY f.tabla_estado_registro_id, c.tabla_estado_registro_id;
