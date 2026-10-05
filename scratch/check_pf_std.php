<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_id BETWEEN 400 AND 411");
$r = mysqli_fetch_row($res);
echo "Funciones en conf__paginas_funciones para paginas 400..411: " . $r[0] . "\n";

$res = mysqli_query($conn, "SELECT * FROM conf__paginas_funciones_estandar LIMIT 5");
while ($r = mysqli_fetch_assoc($res)) print_r($r);
