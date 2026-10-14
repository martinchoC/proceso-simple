<?php
require 'db.php';
require 'modules/gestion/cobranzas_model.php';

$empresa_idx = 2;
$medios_pago = $conexion->query("SELECT medio_pago_id, codigo, medio_pago, medio_pago_tipo, cont_cuenta_id, impuesto_tipo_id FROM gestion__medios_pago WHERE empresa_id = " . intval($empresa_idx) . " AND tabla_estado_registro_id = 1 AND aplica_pago = 1 ORDER BY orden ASC, medio_pago_id ASC")->fetch_all(MYSQLI_ASSOC);
$impuestos_ret = obtenerImpuestosRetencion($conexion, 'PAGO');

echo "=== MEDIOS DE PAGO (PAGO) ===\n";
print_r($medios_pago);

echo "=== IMPUESTOS RETENCION (PAGO) ===\n";
print_r($impuestos_ret);
