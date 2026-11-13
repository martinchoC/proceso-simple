<?php
require_once __DIR__ . '/../../db.php';
mysqli_query($conn, "UPDATE gestion__puntos_venta SET codigo_fiscal = 2, nombre = 'Punto de Venta 2', descripcion = 'Facturación Electrónica ARCA PV 00002' WHERE punto_venta_id = 8 AND empresa_id = 6");
echo "Punto de venta 8 actualizado a codigo_fiscal = 2\n";
