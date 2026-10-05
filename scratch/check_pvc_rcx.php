<?php
require 'db.php';
$res = $conn->query('
    SELECT pvc.punto_venta_id, pv.nombre AS pv_nombre, pv.boca_id, b.boca_nombre, ct.codigo, ct.comprobante_tipo
    FROM gestion__puntos_venta_comprobantes pvc
    JOIN gestion__puntos_venta pv ON pv.punto_venta_id = pvc.punto_venta_id
    LEFT JOIN gestion__bocas b ON b.boca_id = pv.boca_id
    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = pvc.comprobante_tipo_id
    WHERE ct.codigo = "RCX"
');
if ($res) {
    while($r = $res->fetch_assoc()) {
        print_r($r);
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n--- Numeradores para RCX (tipo 24) ---\n";
$res2 = $conn->query('SELECT * FROM gestion__comprobantes_numeradores WHERE comprobante_tipo_id = 24');
while($r = $res2->fetch_assoc()) {
    print_r($r);
}
