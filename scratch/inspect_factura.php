<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT * FROM gestion__ventas_facturas WHERE empresa_id = 6 LIMIT 1");
$row = mysqli_fetch_assoc($r);
print_r($row);
