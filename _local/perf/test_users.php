<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../../db.php';
$r = mysqli_query($conn, "SELECT * FROM conf__usuarios LIMIT 1");
while ($row = mysqli_fetch_assoc($r)) {
    print_r($row);
}

$r2 = mysqli_query($conn, "SELECT * FROM conf__empresas LIMIT 2");
while ($row = mysqli_fetch_assoc($r2)) {
    print_r($row);
}
