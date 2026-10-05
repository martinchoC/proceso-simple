<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE tabla_id = 426");
$r = mysqli_fetch_assoc($res);
echo $r ? "426 Occupied: " . $r['tabla_nombre'] : "426 is Free!\n";
