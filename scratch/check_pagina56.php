<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM conf__estados_registros WHERE estado_registro_id IN (5, 39, 40)");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}

// And check how vfEstadoInicial and transitions work in ventas_facturas
echo "vfEstadoInicial(56): " . vfEstadoInicial($conn, 56) . "\n";
$fRes = mysqli_query($conn, "SELECT * FROM conf__paginas_funciones WHERE pagina_id = 56");
while ($r = mysqli_fetch_assoc($fRes)) {
    echo "Funcion: {$r['funcion']} | Accion JS: {$r['accion_js']} | Origen: {$r['tabla_estado_registro_origen_id']} | Destino: {$r['tabla_estado_registro_destino_id']}\n";
}

function vfEstadoInicial($conexion, $pagina_id) {
    require_once __DIR__ . '/../modules/gestion_normal/ventas_facturas_model.php';
    return vfEstadoInicial($conexion, $pagina_id);
}
