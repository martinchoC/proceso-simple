<?php
require 'db.php';
$res = mysqli_query($conn, "DESCRIBE conf___perfiles_paginas");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} | {$r['Type']}\n";
}
$rows = mysqli_query($conn, "SELECT * FROM conf___perfiles_paginas WHERE pagina_id = 110");
while ($r = mysqli_fetch_assoc($rows)) print_r($r);
