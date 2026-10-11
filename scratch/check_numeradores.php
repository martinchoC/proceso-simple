<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM gestion__comprobantes_numeradores WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
