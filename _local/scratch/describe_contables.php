<?php
require_once __DIR__ . '/../../db.php';

foreach (['gestion__cont_asientos', 'gestion__cont_asientos_detalles', 'gestion__cont_cuentas'] as $t) {
    echo "== $t ==\n";
    $res = mysqli_query($conn, "DESCRIBE $t");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            echo "{$r['Field']} | {$r['Type']} | {$r['Null']}\n";
        }
    } else {
        echo "Tabla $t no existe o no se puede describir.\n";
    }
}
