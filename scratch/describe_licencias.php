<?php
require_once __DIR__ . '/../db.php';

foreach (['estudio__licencias', 'estudio__licencias_delegaciones', 'gestion__activos_fijos', 'gestion__activos_fijos_amortizaciones'] as $t) {
    echo "== $t ==\n";
    $res = mysqli_query($conn, "DESCRIBE $t");
    while ($r = mysqli_fetch_assoc($res)) {
        echo "{$r['Field']} | {$r['Type']} | {$r['Null']}\n";
    }
}
