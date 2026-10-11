<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT tabla_estado_registro_id, COUNT(*) as cnt FROM gestion__facturas_proveedores GROUP BY tabla_estado_registro_id");
while ($row = mysqli_fetch_assoc($r)) {
    echo "Estado: {$row['tabla_estado_registro_id']} => {$row['cnt']}\n";
}
