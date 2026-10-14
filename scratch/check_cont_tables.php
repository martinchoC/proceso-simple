<?php
require __DIR__ . '/../db.php';

function showCreate($conn, $table) {
    echo "====================================\n";
    echo "STRUCTURE: $table\n";
    echo "====================================\n";
    $res = $conn->query("SHOW CREATE TABLE $table");
    if ($res && $row = $res->fetch_array()) {
        echo $row[1] . "\n\n";
    }
}

showCreate($conn, 'gestion__cont_cuentas');
showCreate($conn, 'gestion__empresas_cuentas_conceptos');
showCreate($conn, 'gestion__entidades_roles');

echo "--- DATOS gestion__empresas_cuentas_conceptos ---\n";
$res = $conn->query("SELECT * FROM gestion__empresas_cuentas_conceptos LIMIT 10");
while ($r = $res->fetch_assoc()) print_r($r);
