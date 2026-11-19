-- 20261118_gestion_normal_perfiles_operativos_01.sql
-- Ajuste de perfiles base del módulo 5 (gestion_normal) detectado en la simulación integral (empresa 1000):
--  * 506 "Depósito y stock" no podía preparar pedidos (ventas_pedidos_gestion, 591) ni armar hojas de ruta
--    (distribucion_hojas, 649): el depósito es justamente quien arma, ajusta cantidades y entrega.
--  * 508 "Consulta" tenía transiciones que cambian estado (abrir pedido 5>9 y 42>9 lo pasa a "En preparación")
--    y el ABM completo de hojas de ruta: un perfil de solo consulta no debe modificar nada.
--  * Mostrador (650) solo lo tenía Administrador: se agrega a Ventas (502) y Facturación y cobranzas (507).

-- 506 Depósito y stock: preparación de pedidos + hojas de ruta
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT 506, f.id, 1
FROM (SELECT 5402 AS id UNION ALL SELECT 5403 UNION ALL SELECT 5404 UNION ALL SELECT 5406 UNION ALL SELECT 5407
      UNION ALL SELECT 5450 UNION ALL SELECT 5452 UNION ALL SELECT 8609
      UNION ALL SELECT 8614 UNION ALL SELECT 8615 UNION ALL SELECT 8616 UNION ALL SELECT 8618 UNION ALL SELECT 8619) f
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = 506 AND x.pagina_funcion_id = f.id);

-- 508 Consulta: fuera las funciones que modifican
DELETE FROM conf__perfiles_funciones
WHERE perfil_id = 508 AND pagina_funcion_id IN (5404, 8609, 8615, 8616, 8617, 8618, 8619);

-- Mostrador para Ventas y Facturación y cobranzas
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT p.perfil_id, f.id, 1
FROM (SELECT 502 AS perfil_id UNION ALL SELECT 507) p
JOIN (SELECT 8620 AS id UNION ALL SELECT 8621) f
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = f.id);

-- Empresa 1000 (GINESI MAURO NICOLAS): sacar del perfil derivado de 508 las funciones que modifican.
-- El resto de las funciones nuevas se copia con perfil_base_copiar_a_empresa() (_local/scratch/sync_perfiles_emp1000.php).
DELETE epf FROM conf__empresas_perfiles_funciones epf
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id
WHERE ep.empresa_id = 1000 AND ep.perfil_id_base = 508
  AND epf.pagina_funcion_id IN (5404, 8609, 8615, 8616, 8617, 8618, 8619);
