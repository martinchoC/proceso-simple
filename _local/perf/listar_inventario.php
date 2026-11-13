<?php
require_once __DIR__ . '/../../db.php';

$res = mysqli_query($conn, "SELECT modulo, archivo, ruta, ejecutable_dinamico, motivo_no_ejecutada FROM perf_paginas ORDER BY modulo, archivo");
$modulos = [];
while ($r = mysqli_fetch_assoc($res)) {
    $modulos[$r['modulo']][] = $r;
}

foreach ($modulos as $mod => $pags) {
    echo "========================================================\n";
    echo "MODULO: $mod (Total: " . count($pags) . " páginas)\n";
    echo "========================================================\n";
    foreach ($pags as $p) {
        $status = $p['ejecutable_dinamico'] ? "[EJECUTABLE DINAMICO]" : "[SOLO ESTATICO - NO EJECUTAR]";
        $motivo = $p['motivo_no_ejecutada'] ? " -> Motivo: " . $p['motivo_no_ejecutada'] : "";
        echo sprintf(" - %-35s %s%s\n", $p['archivo'], $status, $motivo);
    }
    echo "\n";
}
