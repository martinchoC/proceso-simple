<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT * FROM sindi__ddjj_empleados WHERE ddjj_id = 20");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
