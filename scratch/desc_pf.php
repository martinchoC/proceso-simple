<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE conf__paginas_funciones");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} | {$r['Type']}\n";
}
echo "Ejemplo de filas:\n";
$res2 = mysqli_query($conn, "SELECT * FROM conf__paginas_funciones LIMIT 3");
while ($r = mysqli_fetch_assoc($res2)) {
    print_r($r);
}
