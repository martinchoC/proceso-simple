<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db.php';

echo "--- 1. PROBANDO AJAX NORMAS (lo que ve la grilla de normas.php) ---\n";
// Simular lo que hace normas_ajax.php o normas_listar
$sqlN = "SELECT n.norma_id, n.codigo, n.titulo, o.nombre as organismo, nt.nombre as tipo, ter.estado_registro
         FROM ml__normas n
         LEFT JOIN ml__organismos o ON o.organismo_id = n.organismo_id
         LEFT JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
         LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = n.tabla_estado_registro_id
         WHERE (n.empresa_id = 2 OR n.empresa_id = 0)";
$resN = mysqli_query($conn, $sqlN);
while ($r = mysqli_fetch_assoc($resN)) {
    echo "Norma: [ID {$r['norma_id']}] {$r['codigo']} - {$r['titulo']} | Estado: {$r['estado_registro']}\n";
}

echo "\n--- 2. PROBANDO AJAX CICLOS (lo que ve ciclos.php) ---\n";
$sqlC = "SELECT c.ciclo_id, e.nombre as entidad, o.codigo as obligacion_codigo, c.periodo_desde, c.periodo_hasta, c.fecha_vencimiento, ter.estado_registro
         FROM ml__ciclos c
         LEFT JOIN ml__entidades e ON e.entidad_id = c.entidad_id
         LEFT JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
         LEFT JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
         LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = c.tabla_estado_registro_id
         WHERE c.empresa_id = 2";
$resC = mysqli_query($conn, $sqlC);
while ($r = mysqli_fetch_assoc($resC)) {
    echo "Ciclo [ID {$r['ciclo_id']}]: Entidad: {$r['entidad']} | Oblig: {$r['obligacion_codigo']} | Vto: {$r['fecha_vencimiento']} | Estado: {$r['estado_registro']}\n";
}

echo "\n--- 3. PROBANDO AJAX TAREAS (lo que ve ciclos_tareas.php) ---\n";
$sqlT = "SELECT ct.ciclo_tarea_id, ct.ciclo_id, ct.nombre, u.usuario as responsable, ct.fecha_vencimiento, ter.estado_registro
         FROM ml__ciclos_tareas ct
         LEFT JOIN conf__usuarios u ON u.usuario_id = ct.usuario_responsable_id
         LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = ct.tabla_estado_registro_id
         WHERE ct.ciclo_id = 4";
$resT = mysqli_query($conn, $sqlT);
while ($r = mysqli_fetch_assoc($resT)) {
    echo "Tarea [ID {$r['ciclo_tarea_id']}]: {$r['nombre']} | Resp: {$r['responsable']} | Vto: {$r['fecha_vencimiento']} | Estado: {$r['estado_registro']}\n";
}
