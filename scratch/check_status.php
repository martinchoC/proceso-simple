<?php
require_once __DIR__ . '/../db.php';

echo "--- CATEGORIAS MORA ---\n";
$res = $conn->query("SELECT * FROM sindi__categorias_mora");
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['categoria_mora_id']} | Codigo: {$row['codigo']} | Nombre: {$row['nombre']} | Dias: {$row['dias_desde']} - {$row['dias_hasta']}\n";
}

echo "\n--- PLANES TIPOS EXISTENTES? ---\n";
$res = $conn->query("SHOW TABLES LIKE 'sindi__planes%'");
while ($row = $res->fetch_row()) {
    echo "Tabla: {$row[0]}\n";
}

echo "\n--- ACTAS EXISTENTES? ---\n";
$res = $conn->query("SHOW TABLES LIKE 'sindi__actas%'");
while ($row = $res->fetch_row()) {
    echo "Tabla: {$row[0]}\n";
}
