<?php
require_once __DIR__ . '/../db.php';
$r = $conn->query("SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas WHERE cont_cuenta_id = 111");
while($row = $r->fetch_assoc()) echo json_encode($row) . "\n";
