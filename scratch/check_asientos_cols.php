<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM gestion__cont_asientos");
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} - {$r['Type']}\n";
}
$res2 = mysqli_query($conn, "SELECT * FROM gestion__cont_asientos WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res2)) {
    echo "ID: {$r['cont_asiento_id']} | Asiento Nro: {$r['nro_asiento']} | Fecha: {$r['fecha']} | Desc: {$r['descripcion']} | Debe: {$r['total_debe']} | Haber: {$r['total_haber']}\n";
}
