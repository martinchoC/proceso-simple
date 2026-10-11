-- =====================================================================
-- Proveedores: cuenta contable única y por defecto por empresa
-- 2026-10-08
--
-- Problema: los proveedores tenían "2.1.01.01 Proveedores Nacionales" en
-- cont_cuenta_id_cliente y cont_cuenta_id_proveedor en 0 / NULL (viene de la
-- carga de datos; el formulario guarda bien cada campo). Con eso
-- generarAsientoContableFactura() (facturas_proveedores_model.php) no generaba
-- el asiento de ninguna factura de proveedor. Encontrado en la simulación de
-- circuitos del 2026-10-08.
--
-- Decisión (Pablo, 2026-10-08): todos los proveedores usan la misma cuenta, y
-- esa cuenta queda como default de la empresa en
-- gestion__cont_parametros.cuenta_proveedores_id (que tenía 101 = Caja Pesos de
-- relleno). El asiento de compra usa ese default cuando el proveedor no tiene
-- cuenta propia, así que los proveedores nuevos quedan cubiertos.
-- La cuenta se busca por código (2.1.01.01) en el plan de cada empresa: no hay
-- ids fijos. Una empresa sin esa cuenta en su plan no se toca.
--
-- Reanudable.
-- =====================================================================

-- 1) Default de la empresa
UPDATE gestion__cont_parametros p
JOIN gestion__cont_cuentas cc ON cc.empresa_id = p.empresa_id AND cc.codigo = '2.1.01.01' AND cc.es_imputable = 1
SET p.cuenta_proveedores_id = cc.cont_cuenta_id
WHERE IFNULL(p.cuenta_proveedores_id, 0) <> cc.cont_cuenta_id;

-- 2) Todos los proveedores con la cuenta de la empresa
UPDATE gestion__entidades e
JOIN gestion__cont_parametros p ON p.empresa_id = e.empresa_id AND p.tabla_estado_registro_id = 1
SET e.cont_cuenta_id_proveedor = p.cuenta_proveedores_id
WHERE e.es_proveedor = 1
  AND p.cuenta_proveedores_id > 0
  AND IFNULL(e.cont_cuenta_id_proveedor, 0) <> p.cuenta_proveedores_id;

-- 3) La cuenta de proveedores cargada por error como cuenta de cliente en proveedores puros
UPDATE gestion__entidades e
JOIN gestion__cont_parametros p ON p.empresa_id = e.empresa_id AND p.tabla_estado_registro_id = 1
SET e.cont_cuenta_id_cliente = NULL
WHERE e.es_proveedor = 1
  AND e.es_cliente = 0
  AND e.cont_cuenta_id_cliente = p.cuenta_proveedores_id;

-- Verificación
SELECT p.empresa_id, p.cuenta_proveedores_id, cc.codigo, cc.nombre,
       (SELECT COUNT(*) FROM gestion__entidades e WHERE e.empresa_id = p.empresa_id AND e.es_proveedor = 1) AS proveedores,
       (SELECT COUNT(*) FROM gestion__entidades e WHERE e.empresa_id = p.empresa_id AND e.es_proveedor = 1
          AND e.cont_cuenta_id_proveedor = p.cuenta_proveedores_id) AS con_la_cuenta
FROM gestion__cont_parametros p
LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = p.cuenta_proveedores_id;
