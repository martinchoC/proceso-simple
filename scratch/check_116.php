<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = 116");
print_r(mysqli_fetch_assoc($res));
