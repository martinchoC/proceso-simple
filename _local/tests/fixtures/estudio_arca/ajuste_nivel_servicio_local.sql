-- Solo base local (datos de prueba de este directorio). Corrige el nivel de servicio / origen que dejó el backfill
-- automático de 20261106_estudio_clientes_nivel_servicio_01.sql (que los marcó PADRON por no tener empresa ni
-- comprobantes manuales). Se identifican por CUIT del tenant 2. Reanudable.
SET NAMES utf8mb4;

-- Clientes sin ERP: el estudio les lleva lo fiscal con los comprobantes de Mis Comprobantes de ARCA.
-- FISCAL / LOCAL: sus comprobantes van a una empresa SIMPLIFICADA (se crea desde la ficha del cliente).
UPDATE estudio__clientes SET nivel_servicio = 'FISCAL', origen_datos = 'LOCAL'
WHERE empresa_id = 2 AND cuit IN ('30718442148', '30709923818', '30716330571', '27314558206', '20287619341');

-- Clientes que usan gestion_multipyme en otra instancia (ver _local/docs/estudio_contable/02_clientes_con_erp_remoto.md).
UPDATE estudio__clientes SET nivel_servicio = 'CONTABLE', origen_datos = 'REMOTO'
WHERE empresa_id = 2 AND cuit IN ('30717022889', '30706554196', '30714886300');

SELECT cliente_id, razon_social, nivel_servicio, origen_datos FROM estudio__clientes WHERE empresa_id = 2 ORDER BY cliente_id;
