<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("DESCRIBE gestion__cobranzas");
while ($r = $res->fetch_assoc()) {
    echo "{$r['Field']} ({$r['Type']}) Default: {$r['Default']}\n";
}
