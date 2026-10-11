<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/facturas_proveedores_model.php';

$est = obtenerEstadoInicialPagina($conn, 557);
echo "Estado inicial pagina 557: $est\n";
$est57 = obtenerEstadoInicialPagina($conn, 57);
echo "Estado inicial pagina 57: $est57\n";

$r = mysqli_query($conn, "SELECT * FROM conf__estados_registros WHERE estado_registro_id IN (1, 2, 3, 4, 5, 6, 7)");
while ($row = mysqli_fetch_assoc($r)) {
    echo "ID: {$row['estado_registro_id']} | Codigo: {$row['codigo_estandar']} | Estado: {$row['estado_registro']}\n";
}
