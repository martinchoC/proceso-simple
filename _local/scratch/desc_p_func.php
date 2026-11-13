<?php
require 'db.php';
$res = mysqli_query($conn, "DESCRIBE conf__paginas_funciones");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} | {$r['Type']} | Null:{$r['Null']}\n";
}
echo "ERROR: " . mysqli_error($conn) . "\n";
