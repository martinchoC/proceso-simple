<?php
require_once __DIR__ . '/../../db.php';

$res = mysqli_query($conn, "SHOW TABLES LIKE 'gestion__%'");
$counts = [];
while ($r = mysqli_fetch_row($res)) {
    $t = $r[0];
    $cRes = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE 'empresa_id'");
    if (mysqli_num_rows($cRes) > 0) {
        $q = mysqli_query($conn, "SELECT COUNT(*) as c FROM `$t` WHERE empresa_id = 6");
        $row = mysqli_fetch_assoc($q);
        if ($row['c'] > 0) {
            $counts[$t] = $row['c'];
        }
    }
}

foreach ($counts as $t => $c) {
    echo "$t: $c\n";
}
