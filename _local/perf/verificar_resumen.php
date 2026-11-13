<?php
require_once __DIR__ . '/../../db.php';

echo "=== RESUMEN POR MODULO EN BASE DE DATOS (CORRIDA 2) ===\n";
$res = mysqli_query($conn, "SELECT modulo, cant_paginas, ms_promedio, kb_html_total, consultas_total, score_modulo 
                            FROM perf_resumen_modulo 
                            WHERE corrida_id = 2 
                            ORDER BY score_modulo DESC");
while ($r = mysqli_fetch_assoc($res)) {
    printf("%-18s | Pags: %3d | ms prom: %6.2f | html total KB: %8.2f | queries total: %5d | SCORE: %5.2f\n",
        $r['modulo'], $r['cant_paginas'], $r['ms_promedio'], $r['kb_html_total'], $r['consultas_total'], $r['score_modulo']);
}

echo "\n=== CONTEO REGISTROS CORRIDA 2 ===\n";
foreach (['perf_corridas', 'perf_paginas', 'perf_estatico', 'perf_dinamico', 'perf_consultas', 'perf_resumen_modulo'] as $tbl) {
    $col = ($tbl === 'perf_paginas') ? '1=1' : 'corrida_id = 2';
    $resC = mysqli_query($conn, "SELECT COUNT(*) FROM $tbl WHERE $col");
    $cnt = mysqli_fetch_row($resC)[0];
    echo sprintf("%-22s: %d filas\n", $tbl, $cnt);
}
