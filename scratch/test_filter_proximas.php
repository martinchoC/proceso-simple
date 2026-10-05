<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/ml/ciclos_tareas_model.php';

$res = ciclos_tareas_listar($conn, 2, ['estado_id' => 'proximas']);
echo "Total tareas próximas a vencer encontradas: " . count($res) . "\n";
foreach ($res as $t) {
    echo "ID: {$t['ciclo_tarea_id']} | {$t['titulo']} | Vence: {$t['fecha_vencimiento']} | Resp: {$t['responsable_nombre']} | Entidad: {$t['entidad_nombre']}\n";
}
