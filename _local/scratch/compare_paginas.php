<?php
require_once __DIR__ . '/../../db.php';

echo "=== MODULO 2 (PADRE 48 Ventas) ===\n";
$res1 = mysqli_query($conn, "SELECT * FROM conf__paginas WHERE pagina_id = 48");
print_r(mysqli_fetch_assoc($res1));

echo "=== MODULO 2 (HIJO 56 Facturacion) ===\n";
$res2 = mysqli_query($conn, "SELECT * FROM conf__paginas WHERE pagina_id = 56");
print_r(mysqli_fetch_assoc($res2));

echo "=== MODULO 4 (PADRE 400) ===\n";
$res3 = mysqli_query($conn, "SELECT * FROM conf__paginas WHERE pagina_id = 400");
print_r(mysqli_fetch_assoc($res3));

echo "=== MODULO 4 (HIJO 402) ===\n";
$res4 = mysqli_query($conn, "SELECT * FROM conf__paginas WHERE pagina_id = 402");
print_r(mysqli_fetch_assoc($res4));
