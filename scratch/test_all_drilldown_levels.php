<?php
session_start();
$_SESSION['usuario_id'] = 1;
$_SESSION['empresa_id'] = 2;
$_SESSION['modulo_id'] = 3;

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/ml/tablero_cumplimiento_model.php';

$niveles = ['global', 'region', 'pais', 'empresa'];

foreach ($niveles as $niv) {
    echo "=== NIVEL: $niv ===\n";
    $res = tablero_obtener_drilldown($conn, $niv, ['empresa_id' => 0]);
    echo "Total items: " . count($res['items']) . "\n";
    foreach ($res['items'] as $it) {
        echo " - [{$it['tipo_etiqueta']}] {$it['nombre']} | Semaforo: {$it['semaforo_texto']} | Vencidas: {$it['vencidas']} | Proximas: {$it['proximas']} | Total: {$it['total_tareas']}\n";
    }
    echo "Tareas críticas encontradas: " . count($res['tareas_criticas']) . "\n";
    echo "Ciclos activos encontrados: " . count($res['ciclos_activos']) . "\n\n";
}
