<?php
require_once __DIR__ . '/../../db.php';

$mods = ['conf', 'estudio_contable', 'gestion_normal'];
foreach ($mods as $mod) {
    echo "=== MODULO: $mod ===\n";
    $res = mysqli_query($conn, "SELECT archivo, ejecutable_dinamico, motivo_no_ejecutada FROM perf_paginas WHERE modulo = '$mod' ORDER BY archivo");
    while ($r = mysqli_fetch_assoc($res)) {
        printf(" - %-35s %s %s\n", $r['archivo'], $r['ejecutable_dinamico'] ? '[EJECUTABLE]' : '[NO EJECUTAR]', $r['motivo_no_ejecutada']);
    }
}
