<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE conf__perfiles_funciones");
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['Field'] . "\n";
}
$res = mysqli_query($conn, "SELECT * FROM conf__perfiles_funciones WHERE pagina_funcion_id IN (3291, 3292, 3293, 3294) LIMIT 5");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
