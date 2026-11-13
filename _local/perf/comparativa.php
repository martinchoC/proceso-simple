<?php
require_once __DIR__ . '/../../db.php';

echo "=== COMPARATIVA MODULAR: CORRIDA 2 (ANTES) vs CORRIDA 7 (DESPUES) ===\n\n";
$sql = "
SELECT 
    r2.modulo,
    r2.score_modulo AS score_antes,
    r7.score_modulo AS score_despues,
    ROUND(r2.score_modulo - r7.score_modulo, 2) AS mejora_score,
    ROUND(((r2.score_modulo - r7.score_modulo) / r2.score_modulo) * 100, 1) AS pct_mejora_score,
    r2.ms_promedio AS ms_antes,
    r7.ms_promedio AS ms_despues,
    ROUND(((r2.ms_promedio - r7.ms_promedio) / r2.ms_promedio) * 100, 1) AS pct_mejora_tiempo
FROM perf_resumen_modulo r2
JOIN perf_resumen_modulo r7 ON r7.modulo = r2.modulo AND r7.corrida_id = 7
WHERE r2.corrida_id = 2
ORDER BY r2.score_modulo DESC
";

$res = mysqli_query($conn, $sql);
printf("%-18s | Score Antes | Score Después | Mejora Score | ms Antes | ms Después | Mejora Tiempo\n", "Módulo");
echo str_repeat("-", 95) . "\n";
while ($r = mysqli_fetch_assoc($res)) {
    printf("%-18s | %11.2f | %13.2f | %11.2f%% | %7.1f ms| %9.1f ms| %11.1f%%\n",
        $r['modulo'], $r['score_antes'], $r['score_despues'], $r['pct_mejora_score'],
        $r['ms_antes'], $r['ms_despues'], $r['pct_mejora_tiempo']);
}
