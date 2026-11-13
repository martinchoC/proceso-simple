<?php
require_once __DIR__ . '/../../db.php';
$res = mysqli_query($conn, "DESCRIBE conf__tablas");
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";
