<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
echo "=== conf__estados_registros ===\n";
$res = $conexion->query("SELECT * FROM conf__estados_registros");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['estado_registro_id']} | Codigo: {$r['codigo_estandar']} | Nombre: {$r['estado_registro']}\n";
}

echo "\n=== conf__tablas_estados_registros para gestion__ventas_facturas o comprobantes ===\n";
$res2 = $conexion->query("
    SELECT ter.*, t.tabla 
    FROM conf__tablas_estados_registros ter 
    LEFT JOIN conf__tablas t ON t.tabla_id = ter.tabla_id 
    WHERE t.tabla LIKE '%factura%' OR t.tabla LIKE '%comprobante%' OR ter.tabla_id = 80
");
while ($r = $res2->fetch_assoc()) {
    echo "TER ID: {$r['tabla_estado_registro_id']} | Tabla ID: {$r['tabla_id']} ({$r['tabla']}) | Estado Reg ID: {$r['estado_registro_id']}\n";
}
