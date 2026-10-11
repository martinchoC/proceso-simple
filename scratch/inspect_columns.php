<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM gestion__cont_cuentas");
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['Field'] . "\n";
}

$res2 = mysqli_query($conn, "SELECT * FROM gestion__cont_cuentas WHERE empresa_id = 6 LIMIT 5");
while ($r = mysqli_fetch_assoc($res2)) {
    print_r($r);
}
