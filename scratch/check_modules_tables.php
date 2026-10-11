<?php
require_once __DIR__ . '/../db.php';

echo "=== SHOW TABLES LIKE '%factura%' ===\n";
$res = mysqli_query($conn, "SHOW TABLES LIKE '%factura%'");
while ($r = mysqli_fetch_row($res)) {
    echo $r[0] . "\n";
}

echo "=== SHOW TABLES LIKE '%proveedor%' ===\n";
$res = mysqli_query($conn, "SHOW TABLES LIKE '%proveedor%'");
while ($r = mysqli_fetch_row($res)) {
    echo $r[0] . "\n";
}

echo "=== SHOW TABLES LIKE '%compra%' ===\n";
$res = mysqli_query($conn, "SHOW TABLES LIKE '%compra%'");
while ($r = mysqli_fetch_row($res)) {
    echo $r[0] . "\n";
}

echo "=== SHOW TABLES LIKE '%cliente%' ===\n";
$res = mysqli_query($conn, "SHOW TABLES LIKE '%cliente%'");
while ($r = mysqli_fetch_row($res)) {
    echo $r[0] . "\n";
}
