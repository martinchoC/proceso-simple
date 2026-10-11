<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM gestion__facturas_proveedores");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} - {$r['Type']}\n";
}
