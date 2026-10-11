<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT * FROM gestion__condiciones_pago WHERE empresa_id IN (0, 6) LIMIT 5");
while ($row = mysqli_fetch_assoc($r)) {
    print_r($row);
}
