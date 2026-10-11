<?php
require_once __DIR__ . '/../db.php';

$q = $conexion->query("SELECT * FROM gestion__puntos_venta");
while ($r = $q->fetch_assoc()) echo json_encode($r) . "\n";
