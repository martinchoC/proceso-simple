<?php
require_once __DIR__ . '/../../db.php';
$res = mysqli_query($conn, "DESCRIBE conf__empresas_perfiles");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} | {$r['Type']}\n";
}
