<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT * FROM gestion__comprobantes_tipos");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
