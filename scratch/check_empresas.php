<?php
require __DIR__ . '/../db.php';
$res = $conn->query("SHOW COLUMNS FROM conf__empresas");
while ($r = $res->fetch_assoc()) echo $r['Field'] . ", ";
echo "\n";
$res2 = $conn->query("SELECT * FROM conf__empresas");
while ($r = $res2->fetch_assoc()) {
    print_r($r);
}
