<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../../db.php';

$sql = file_get_contents(__DIR__ . '/schema.sql');
$queries = array_filter(array_map('trim', explode(';', $sql)));

foreach ($queries as $q) {
    if (empty($q)) continue;
    if (!mysqli_query($conn, $q)) {
        die("Error ejecutando DDL: " . mysqli_error($conn) . "\nSQL: $q\n");
    }
}

echo "Tablas de performance creadas o verificadas correctamente.\n";
