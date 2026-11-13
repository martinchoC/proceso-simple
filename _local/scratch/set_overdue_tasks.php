<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/../../db.php';

// Actualizar Tarea 9 para que esté VENCIDA (vencimiento 2026-09-30, siendo hoy 05/10/2026)
$res1 = mysqli_query($conn, "UPDATE ml__ciclos_tareas 
                             SET fecha_vencimiento = '2026-09-30 00:00:00',
                                 tabla_estado_registro_id = 2078 
                             WHERE ciclo_tarea_id = 9");

// Actualizar Tarea 8 para que esté PRÓXIMA A VENCER (vencimiento 2026-10-10, en 5 días)
$res2 = mysqli_query($conn, "UPDATE ml__ciclos_tareas 
                             SET fecha_vencimiento = '2026-10-10 00:00:00',
                                 tabla_estado_registro_id = 2079 
                             WHERE ciclo_tarea_id = 8");

// Tarea 10 la dejamos para fin de noviembre (vencimiento 2026-11-30)
$res3 = mysqli_query($conn, "UPDATE ml__ciclos_tareas 
                             SET fecha_vencimiento = '2026-11-30 00:00:00',
                                 tabla_estado_registro_id = 2078 
                             WHERE ciclo_tarea_id = 10");

echo "Actualización completada:\n";
$resT = mysqli_query($conn, "SELECT ct.ciclo_tarea_id, ct.titulo, ct.fecha_vencimiento, u.usuario as resp, ter.tabla_estado_registro
                            FROM ml__ciclos_tareas ct
                            JOIN conf__usuarios u ON u.usuario_id = ct.usuario_responsable_id
                            JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = ct.tabla_estado_registro_id
                            WHERE ct.ciclo_tarea_id IN (8, 9, 10)");
while ($r = mysqli_fetch_assoc($resT)) {
    echo " - Tarea #{$r['ciclo_tarea_id']}: {$r['titulo']} | Vence: {$r['fecha_vencimiento']} | Resp: {$r['resp']} | Estado: {$r['tabla_estado_registro']}\n";
}
