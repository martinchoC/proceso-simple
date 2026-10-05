-- =====================================================================
-- Habilitación de RCX en Puntos de Venta de Bocas y Numeradores
-- Fecha: 2026-10-04
-- Reglas de negocio:
--   1. Única caja de cobro por sucursal (gestion__tesoreria_cuentas cuenta_tipo='CAJA').
--   2. Cada boca numera sus propios recibos (gestion__puntos_venta de la boca con RCX habilitado).
-- =====================================================================

START TRANSACTION;

-- 1. Habilitar RCX (tipo 24) en los puntos de venta asociados a las bocas (PV 1, PV 2, PV 3)
INSERT INTO `gestion__puntos_venta_comprobantes` 
    (`empresa_id`, `punto_venta_id`, `comprobante_tipo_id`, `requiere_afip`, `tabla_estado_registro_id`, `created_at`, `updated_at`)
SELECT pv.empresa_id, pv.punto_venta_id, 24, 0, 1, NOW(), NOW()
FROM `gestion__puntos_venta` pv
WHERE pv.empresa_id = 2 
  AND pv.punto_venta_id IN (1, 2, 3)
  AND NOT EXISTS (
      SELECT 1 FROM `gestion__puntos_venta_comprobantes` pvc 
      WHERE pvc.punto_venta_id = pv.punto_venta_id 
        AND pvc.comprobante_tipo_id = 24
  );

-- 2. Asegurar existencia de los numeradores para cada PV de boca con tipo RCX (tipo 24)
INSERT INTO `gestion__comprobantes_numeradores`
    (`empresa_id`, `punto_venta_id`, `comprobante_tipo_id`, `ultimo_numero`, `created_at`, `updated_at`)
SELECT pv.empresa_id, pv.punto_venta_id, 24, 0, NOW(), NOW()
FROM `gestion__puntos_venta` pv
WHERE pv.empresa_id = 2 
  AND pv.punto_venta_id IN (1, 2, 3)
  AND NOT EXISTS (
      SELECT 1 FROM `gestion__comprobantes_numeradores` n 
      WHERE n.empresa_id = pv.empresa_id 
        AND n.punto_venta_id = pv.punto_venta_id 
        AND n.comprobante_tipo_id = 24
  );

COMMIT;
