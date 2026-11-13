<?php
require_once __DIR__ . '/../../db.php';
$res = mysqli_query($conn, "DESCRIBE gestion__cont_asientos");
echo "=== ASIENTOS ===\n";
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";

$res = mysqli_query($conn, "DESCRIBE gestion__cont_asientos_detalles");
echo "=== ASIENTOS DETALLES ===\n";
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";
