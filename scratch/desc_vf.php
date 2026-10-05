<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE gestion__ventas_facturas");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Def:{$r['Default']}\n";
}
