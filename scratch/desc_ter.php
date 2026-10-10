<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE conf__tablas_estados_registros");
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";
