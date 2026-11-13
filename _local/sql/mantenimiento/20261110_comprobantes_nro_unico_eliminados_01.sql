-- ==============================================================================
-- Excluir comprobantes en estado Eliminado (6) de la clave UNIQUE uk_comp_numeracion
-- (2026-11-10)
-- Modifica la columna generada comprobante_nro_unico de gestion__comprobantes:
--   Antes: nullif(comprobante_nro, 0)
--   Ahora: case when tabla_estado_registro_id = 6 or comprobante_nro = 0 then NULL else comprobante_nro end
-- Esto permite que si una factura de proveedor (o cualquier comprobante) fue eliminada,
-- se pueda volver a cargar un comprobante con el mismo número sin violar la restricción UNIQUE.
-- ==============================================================================

SET NAMES utf8mb4;

ALTER TABLE `gestion__comprobantes` 
  MODIFY COLUMN `comprobante_nro_unico` int(11) 
  GENERATED ALWAYS AS (case when `tabla_estado_registro_id` = 6 or `comprobante_nro` = 0 then NULL else `comprobante_nro` end) 
  STORED COMMENT 'NULL en Borrador (nro 0) o Eliminado (estado 6): excluido del UNIQUE';
