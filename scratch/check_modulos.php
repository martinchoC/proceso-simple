<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM conf__modulos");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['modulo_id']} - {$r['modulo']} - Carpeta: " . ($r['carpeta'] ?? $r['directorio'] ?? 'N/A') . "\n";
    print_r($r);
}
