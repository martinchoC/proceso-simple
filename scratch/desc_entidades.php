<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE gestion__entidades");
while ($r = mysqli_fetch_assoc($res)) echo $r['Field'] . "\n";
