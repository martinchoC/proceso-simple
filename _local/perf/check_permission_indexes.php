<?php
require_once __DIR__ . '/../../db.php';
foreach (['conf__paginas_funciones', 'conf__empresas_perfiles_funciones', 'conf__usuarios_perfiles'] as $t) {
    echo "=== INDICES $t ===\n";
    $r = mysqli_query($conn, "SHOW INDEX FROM $t");
    while ($row = mysqli_fetch_assoc($r)) {
        printf("%-35s %-25s %s\n", $row['Table'], $row['Key_name'], $row['Column_name']);
    }
}
