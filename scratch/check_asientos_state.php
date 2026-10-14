<?php
require_once __DIR__ . '/../db.php';
$q = mysqli_query($conn, 'SELECT DISTINCT tabla_estado_registro_id, COUNT(*) as c FROM gestion__cont_asientos GROUP BY tabla_estado_registro_id');
while ($r = mysqli_fetch_assoc($q)) {
    echo json_encode($r) . "\n";
}
