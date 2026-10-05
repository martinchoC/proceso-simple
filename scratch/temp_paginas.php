<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT * FROM conf__paginas WHERE pagina_id IN (87, 88, 89, 91, 105)");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
