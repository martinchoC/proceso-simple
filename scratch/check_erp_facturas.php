<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE gestion__ventas_facturas");
echo "=== VENTAS FACTURAS ===\n";
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";

$res = mysqli_query($conn, "DESCRIBE gestion__facturas_proveedores");
echo "=== FACTURAS PROVEEDORES ===\n";
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";
