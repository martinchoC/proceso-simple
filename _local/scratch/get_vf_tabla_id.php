<?php
require_once __DIR__ . '/../../db.php';
$r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE tabla_nombre = 'gestion__ventas_facturas'"));
print_r($r);
