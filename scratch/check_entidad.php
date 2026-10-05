<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT entidad_id, entidad_nombre, empresa_id FROM gestion__entidades WHERE empresa_id = 2 LIMIT 5");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['entidad_id']} - Nombre: {$r['entidad_nombre']}\n";
}
