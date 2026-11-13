<?php
require_once __DIR__ . '/../../db.php';

echo "=== TOP CONSULTAS REPETIDAS O SIN INDICE EN CORRIDA 2 ===\n";
$res = mysqli_query($conn, "
    SELECT c.sql_normalizado, c.veces_ejecutada, c.ms_total, c.usa_indice, c.indice_sugerido, p.modulo, p.archivo
    FROM perf_consultas c
    JOIN perf_paginas p ON p.pagina_id = c.pagina_id
    WHERE c.corrida_id = 2 AND (c.usa_indice LIKE '%Full%' OR c.veces_ejecutada > 1 OR c.ms_total > 5)
    ORDER BY c.ms_total DESC, c.veces_ejecutada DESC
    LIMIT 20
");
while ($r = mysqli_fetch_assoc($res)) {
    printf("[%s / %s] Veces: %2d | ms: %5.2f | Indice: %s\nSQL: %s\nSugerencia: %s\n\n",
        $r['modulo'], $r['archivo'], $r['veces_ejecutada'], $r['ms_total'], $r['usa_indice'], substr($r['sql_normalizado'], 0, 120), $r['indice_sugerido'] ?: 'N/A');
}
