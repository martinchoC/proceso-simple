<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT ter.*, t.tabla_nombre, er.estado_registro 
FROM conf__tablas_estados_registros ter 
JOIN conf__tablas t ON ter.tabla_id = t.tabla_id
JOIN conf__estados_registros er ON ter.estado_registro_id = er.estado_registro_id
WHERE ter.tabla_estado_registro_id IN (5, 39, 40)");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['tabla_estado_registro_id']} | Tabla: {$r['tabla_nombre']} | Estado: {$r['estado_registro']}\n";
}
