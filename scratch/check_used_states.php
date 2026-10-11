<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT ter.tabla_estado_registro_id, t.tabla_nombre, er.estado_registro 
FROM conf__tablas_estados_registros ter
LEFT JOIN conf__tablas t ON ter.tabla_id = t.tabla_id
LEFT JOIN conf__estados_registros er ON ter.estado_registro_id = er.estado_registro_id
WHERE ter.tabla_estado_registro_id IN (40, 1, 2, 3, 6, 129, 130, 131, 149)");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['tabla_estado_registro_id']} | Tabla: {$r['tabla_nombre']} | Estado: {$r['estado_registro']}\n";
}

$res2 = mysqli_query($conn, "SELECT DISTINCT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE empresa_id = 6");
echo "Estados usados en ventas_facturas empresa 6:\n";
while ($r = mysqli_fetch_assoc($res2)) echo "  {$r['tabla_estado_registro_id']}\n";

$res3 = mysqli_query($conn, "SELECT DISTINCT tabla_estado_registro_id FROM gestion__comprobantes WHERE empresa_id = 6");
echo "Estados usados en comprobantes empresa 6:\n";
while ($r = mysqli_fetch_assoc($res3)) echo "  {$r['tabla_estado_registro_id']}\n";
