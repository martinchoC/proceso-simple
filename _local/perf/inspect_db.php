<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../../db.php';

echo "=== ESTRUCTURA conf__modulos ===" . PHP_EOL;
$res = mysqli_query($conn, "DESCRIBE conf__modulos");
while ($row = mysqli_fetch_assoc($res)) {
    echo "{$row['Field']} ({$row['Type']})\n";
}

echo PHP_EOL . "=== DATOS conf__modulos ===" . PHP_EOL;
$res = mysqli_query($conn, "SELECT * FROM conf__modulos");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}

echo PHP_EOL . "=== ESTRUCTURA conf__paginas ===" . PHP_EOL;
$res = mysqli_query($conn, "DESCRIBE conf__paginas");
while ($row = mysqli_fetch_assoc($res)) {
    echo "{$row['Field']} ({$row['Type']})\n";
}
