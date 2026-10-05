<?php
require_once __DIR__ . '/../db.php';
$r = $conn->query("SHOW CREATE TABLE gestion__comprobantes")->fetch_assoc();
echo $r['Create Table'];
