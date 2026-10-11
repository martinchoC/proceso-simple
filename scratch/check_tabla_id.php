<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE tabla_nombre IN ('gestion__ventas_facturas', 'gestion__facturas_proveedores')");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Tabla: {$r['tabla_nombre']} | tabla_id: {$r['tabla_id']}\n";
}
