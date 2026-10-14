<?php
require __DIR__ . '/../db.php';
$res = $conn->query("SELECT DISTINCT empresa_id, COUNT(*) as c FROM gestion__entidades GROUP BY empresa_id");
while ($r = $res->fetch_assoc()) {
    echo "empresa_id: {$r['empresa_id']} | cant: {$r['c']}\n";
}
