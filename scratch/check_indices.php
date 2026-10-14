<?php
require __DIR__ . '/../db.php';

echo "=== INDICES gestion__entidades_roles ===\n";
$res = $conn->query("SHOW INDEX FROM gestion__entidades_roles");
while ($r = $res->fetch_assoc()) {
    echo "Key_name: {$r['Key_name']} | Column_name: {$r['Column_name']} | Non_unique: {$r['Non_unique']}\n";
}

echo "\n=== INDICES gestion__roles_entidades ===\n";
$res = $conn->query("SHOW INDEX FROM gestion__roles_entidades");
while ($r = $res->fetch_assoc()) {
    echo "Key_name: {$r['Key_name']} | Column_name: {$r['Column_name']} | Non_unique: {$r['Non_unique']}\n";
}
