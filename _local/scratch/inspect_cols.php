<?php
require_once __DIR__ . '/../../db.php';
$res = mysqli_query($conexion, "DESCRIBE ml__obligaciones_asignadas");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} ({$r['Type']})\n";
}
