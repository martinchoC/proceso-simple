<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT pf.*, o.estado_registro as origen, d.estado_registro as destino 
FROM conf__paginas_funciones pf
LEFT JOIN conf__estados_registros o ON pf.tabla_estado_registro_origen_id = o.estado_registro_id
LEFT JOIN conf__estados_registros d ON pf.tabla_estado_registro_destino_id = d.estado_registro_id
WHERE pf.pagina_id = 557");
while ($row = mysqli_fetch_assoc($r)) {
    echo "{$row['pagina_funcion_id']} | {$row['nombre_funcion']} ({$row['accion_js']}) | Origen: {$row['origen']} ({$row['tabla_estado_registro_origen_id']}) -> Destino: {$row['destino']} ({$row['tabla_estado_registro_destino_id']})\n";
}
