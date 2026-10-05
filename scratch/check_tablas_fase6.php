<?php
require_once __DIR__ . '/../db.php';
$r = $conn->query("SHOW TABLES LIKE '%impuest%'");
while ($row = $r->fetch_row()) echo "T: {$row[0]}\n";

$r = $conn->query("SHOW TABLES LIKE '%oblig%'");
while ($row = $r->fetch_row()) echo "T: {$row[0]}\n";

$r = $conn->query("SHOW TABLES LIKE '%rend%'");
while ($row = $r->fetch_row()) echo "T: {$row[0]}\n";

$r = $conn->query("SHOW TABLES LIKE '%gasto%'");
while ($row = $r->fetch_row()) echo "T: {$row[0]}\n";
