<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("DESCRIBE gestion__entidades");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . " | " . $r['Type'] . "\n";
}
