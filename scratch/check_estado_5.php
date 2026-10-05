<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT ter.*, er.* 
    FROM conf__tablas_estados_registros ter
    LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
    WHERE ter.tabla_estado_registro_id = 5
");
print_r($res->fetch_assoc());

echo "\n--- Estados para tabla gestion__comprobantes (tabla_id = 70 o similar) o tabla_id = 80 ---\n";
$res2 = $conexion->query("
    SELECT ter.tabla_estado_registro_id, ter.tabla_id, t.tabla, er.codigo_estandar, er.estado_registro
    FROM conf__tablas_estados_registros ter
    JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
    LEFT JOIN conf__tablas t ON t.tabla_id = ter.tabla_id
    WHERE ter.tabla_id IN (70, 80, 82, 85, 275) OR ter.tabla_estado_registro_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 137, 138, 139)
");
while ($r = $res2->fetch_assoc()) {
    echo "TER ID: {$r['tabla_estado_registro_id']} | Tabla ID: {$r['tabla_id']} ({$r['tabla']}) | Codigo: {$r['codigo_estandar']} | Nombre: {$r['estado_registro']}\n";
}
