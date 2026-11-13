<?php
require_once __DIR__ . '/../../db.php';

$sqlRanking = "
SELECT 
    p.modulo,
    p.archivo,
    p.ejecutable_dinamico,
    ROUND(
        (LEAST(100, GREATEST(0, (COALESCE(d.ms_promedio, 0) - 20) / 10)) * 0.35) +
        (LEAST(100, (e.consultas_en_bucle * 35) + (e.cant_consultas * 3) + (COALESCE(d.cant_consultas, 0) * 2)) * 0.25) +
        (LEAST(100, GREATEST(0, COALESCE(d.ms_mysql, 0) * 2)) * 0.15) +
        (LEAST(100, GREATEST(0, (COALESCE(d.kb_html, e.kb_archivo) / 300) * 100)) * 0.15) +
        (LEAST(100, GREATEST(0, (COALESCE(d.memoria_pico_mb, 2) / 16) * 100)) * 0.10)
    , 2) AS puntaje_carga,
    CASE 
        WHEN (
            (LEAST(100, GREATEST(0, (COALESCE(d.ms_promedio, 0) - 20) / 10)) * 0.35) +
            (LEAST(100, (e.consultas_en_bucle * 35) + (e.cant_consultas * 3) + (COALESCE(d.cant_consultas, 0) * 2)) * 0.25) +
            (LEAST(100, GREATEST(0, COALESCE(d.ms_mysql, 0) * 2)) * 0.15) +
            (LEAST(100, GREATEST(0, (COALESCE(d.kb_html, e.kb_archivo) / 300) * 100)) * 0.15) +
            (LEAST(100, GREATEST(0, (COALESCE(d.memoria_pico_mb, 2) / 16) * 100)) * 0.10)
        ) > 60 THEN 'PESADA'
        WHEN (
            (LEAST(100, GREATEST(0, (COALESCE(d.ms_promedio, 0) - 20) / 10)) * 0.35) +
            (LEAST(100, (e.consultas_en_bucle * 35) + (e.cant_consultas * 3) + (COALESCE(d.cant_consultas, 0) * 2)) * 0.25) +
            (LEAST(100, GREATEST(0, COALESCE(d.ms_mysql, 0) * 2)) * 0.15) +
            (LEAST(100, GREATEST(0, (COALESCE(d.kb_html, e.kb_archivo) / 300) * 100)) * 0.15) +
            (LEAST(100, GREATEST(0, (COALESCE(d.memoria_pico_mb, 2) / 16) * 100)) * 0.10)
        ) > 30 THEN 'MEDIA'
        ELSE 'LIVIANA'
    END AS clasificacion,
    COALESCE(d.ms_promedio, 0) AS ms_promedio,
    COALESCE(d.kb_html, e.kb_archivo) AS kb_html,
    (e.cant_consultas + COALESCE(d.cant_consultas, 0)) AS consultas_totales,
    e.consultas_en_bucle AS consultas_n1,
    e.observaciones
FROM perf_paginas p
INNER JOIN perf_estatico e ON e.pagina_id = p.pagina_id AND e.corrida_id = 2
LEFT JOIN perf_dinamico d ON d.pagina_id = p.pagina_id AND d.corrida_id = 2
ORDER BY p.modulo, puntaje_carga DESC;
";

$res = mysqli_query($conn, $sqlRanking);
$byMod = [];
while ($row = mysqli_fetch_assoc($res)) {
    $byMod[$row['modulo']][] = $row;
}

foreach ($byMod as $mod => $rows) {
    echo "========================================================\n";
    echo "TOP 10 PAGINAS - MODULO: $mod\n";
    echo "========================================================\n";
    $top10 = array_slice($rows, 0, 10);
    foreach ($top10 as $i => $r) {
        printf("%2d. %-32s | Score: %5.2f [%-7s] | %5.1f ms | HTML: %6.1f KB | Q: %3d (N+1: %2d)\n",
            $i + 1, $r['archivo'], $r['puntaje_carga'], $r['clasificacion'], $r['ms_promedio'], $r['kb_html'], $r['consultas_totales'], $r['consultas_n1']);
    }
}
