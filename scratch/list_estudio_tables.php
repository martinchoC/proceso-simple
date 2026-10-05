<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SHOW TABLES LIKE 'estudio__%'");
echo "=== TABLAS ESTUDIO ===\n";
while ($r = mysqli_fetch_row($res)) echo $r[0] . "\n";

$res = mysqli_query($conn, "SHOW TABLES LIKE 'gestion__activos%'");
echo "=== TABLAS ACTIVOS ===\n";
while ($r = mysqli_fetch_row($res)) echo $r[0] . "\n";

$res = mysqli_query($conn, "SHOW TABLES LIKE 'gestion__sync%'");
echo "=== TABLAS SYNC ===\n";
while ($r = mysqli_fetch_row($res)) echo $r[0] . "\n";
