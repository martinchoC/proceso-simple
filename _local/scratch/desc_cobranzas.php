<?php
require_once __DIR__ . '/../../db.php';
$res = mysqli_query($conn, "DESCRIBE gestion__cobranzas");
echo "=== COBRANZAS ===\n";
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";

$res = mysqli_query($conn, "DESCRIBE gestion__cobranzas_medios");
echo "=== COBRANZAS MEDIOS ===\n";
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";
