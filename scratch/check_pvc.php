<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT pvc.*, ct.comprobante_tipo, ct.letra, ct.comprobante_fiscal_id 
FROM gestion__puntos_venta_comprobantes pvc
JOIN gestion__comprobantes_tipos ct ON pvc.comprobante_tipo_id = ct.comprobante_tipo_id
WHERE pvc.empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    echo "PVC ID: {$r['punto_venta_comprobante_id']} | PV ID: {$r['punto_venta_id']} | Tipo ID: {$r['comprobante_tipo_id']} ({$r['comprobante_tipo']} Letra {$r['letra']} CodFiscal {$r['comprobante_fiscal_id']}) | Nro: {$r['ultimo_numero']}\n";
}
