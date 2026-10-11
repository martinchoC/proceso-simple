<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT producto_id, producto_codigo, producto_nombre, es_servicio, controla_stock FROM gestion__productos WHERE empresa_id = 6");
while ($row = mysqli_fetch_assoc($r)) {
    echo "{$row['producto_id']} | {$row['producto_codigo']} | {$row['producto_nombre']} | Servicio: {$row['es_servicio']} | Stock: {$row['controla_stock']}\n";
}
