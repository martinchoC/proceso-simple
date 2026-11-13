<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../../db.php';

$sql = file_get_contents(__DIR__ . '/../sql/mantenimiento/20261017_perf_01_indices_core.sql');
$qs = array_filter(array_map('trim', explode(';', $sql)));

foreach ($qs as $q) {
    // Quitar comentarios
    $lineas = explode("\n", $q);
    $lineasLimpias = [];
    foreach ($lineas as $l) {
        $lt = trim($l);
        if (str_starts_with($lt, '--') || str_starts_with($lt, '/*')) continue;
        $lineasLimpias[] = $l;
    }
    $qLimpia = trim(implode("\n", $lineasLimpias));
    if (empty($qLimpia)) continue;

    if (!mysqli_query($conn, $qLimpia)) {
        echo "Error: " . mysqli_error($conn) . "\nSQL: $qLimpia\n";
    } else {
        echo "OK: " . substr(preg_replace('/\s+/', ' ', $qLimpia), 0, 50) . "...\n";
    }
}
echo "Script de índices core ejecutado correctamente.\n";
