<?php
require_once __DIR__ . '/../../db.php';

$res = mysqli_query($conn, "SHOW TABLES LIKE 'gestion__%'");
$tables = [];
while ($r = mysqli_fetch_row($res)) {
    $tables[] = $r[0];
}
echo "Total gestion tables: " . count($tables) . "\n";

$tablesWithEmpresa = [];
foreach ($tables as $t) {
    $cRes = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE 'empresa_id'");
    if (mysqli_num_rows($cRes) > 0) {
        $tablesWithEmpresa[] = $t;
        $cntRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM `$t` WHERE empresa_id = 6");
        if ($cntRes) {
            $c = mysqli_fetch_assoc($cntRes)['c'];
            if ($c > 0) {
                echo "$t: $c rows\n";
            }
        }
    }
}
echo "Total tables with empresa_id column: " . count($tablesWithEmpresa) . "\n";
