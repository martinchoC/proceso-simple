<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT funcion_id, pagina_id, funcion_nombre, accion_js, icono_id, color_id, orden FROM conf__paginas_funciones WHERE pagina_id IN (402, 410) LIMIT 10");
while ($r = mysqli_fetch_assoc($res)) {
    echo json_encode($r) . "\n";
}
