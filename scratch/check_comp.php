<?php
require_once __DIR__ . '/../db.php';
$r = $conn->query("SHOW COLUMNS FROM gestion__comprobantes");
while ($c = $r->fetch_assoc()) {
    echo $c['Field'] . "\n";
}
