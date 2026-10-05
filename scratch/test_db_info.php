<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db.php';

echo "Connected to Host: " . IP_HOST . "\n";
echo "Current DB: " . DB_NAME . "\n";

$res = mysqli_query($conn, "SHOW DATABASES");
echo "Databases in MySQL:\n";
while ($row = mysqli_fetch_row($res)) {
    echo " - " . $row[0] . "\n";
}

echo "\nTables with 'ml__' in " . DB_NAME . ":\n";
$resT = mysqli_query($conn, "SHOW TABLES LIKE 'ml_%'");
while ($row = mysqli_fetch_row($resT)) {
    echo " - " . $row[0] . "\n";
}

echo "\nNormas in " . DB_NAME . ":\n";
$resN = mysqli_query($conn, "SELECT norma_id, codigo, titulo, empresa_id FROM ml__normas");
while ($row = mysqli_fetch_assoc($resN)) {
    echo " ID: {$row['norma_id']} | Cod: {$row['codigo']} | Titulo: {$row['titulo']} | Empresa: {$row['empresa_id']}\n";
}

echo "\nEntidades in " . DB_NAME . ":\n";
$resE = mysqli_query($conn, "SELECT entidad_id, codigo, nombre FROM ml__entidades");
while ($row = mysqli_fetch_assoc($resE)) {
    echo " ID: {$row['entidad_id']} | Cod: {$row['codigo']} | Nombre: {$row['nombre']}\n";
}

echo "\nCiclos in " . DB_NAME . ":\n";
$resC = mysqli_query($conn, "SELECT ciclo_id, codigo, anio, fecha_inicio, fecha_vencimiento FROM ml__ciclos");
while ($row = mysqli_fetch_assoc($resC)) {
    echo " ID: {$row['ciclo_id']} | Cod: {$row['codigo']} | Año: {$row['anio']} | Vto: {$row['fecha_vencimiento']}\n";
}

echo "\nTareas in " . DB_NAME . ":\n";
$resTa = mysqli_query($conn, "SELECT ciclo_tarea_id, ciclo_id, nombre, usuario_responsable_id FROM ml__ciclos_tareas");
while ($row = mysqli_fetch_assoc($resTa)) {
    echo " ID: {$row['ciclo_tarea_id']} | Ciclo: {$row['ciclo_id']} | Nombre: {$row['nombre']} | Resp: {$row['usuario_responsable_id']}\n";
}
