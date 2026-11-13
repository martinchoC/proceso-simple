<?php
require_once __DIR__ . '/../../db.php';

$res = mysqli_query($conexion, "SELECT ct.ciclo_tarea_id, ct.ciclo_id, ct.tarea_plantilla_id, ct.titulo, c.obligacion_asignada_id 
                                FROM ml__ciclos_tareas ct 
                                JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id 
                                WHERE ct.ciclo_tarea_id BETWEEN 26 AND 31");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Tarea #{$r['ciclo_tarea_id']} | TPL: {$r['tarea_plantilla_id']} | Titulo: {$r['titulo']}\n";
}
