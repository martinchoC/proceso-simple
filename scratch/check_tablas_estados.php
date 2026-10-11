<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SHOW TABLES LIKE 'conf__%estado%'");
while ($r = mysqli_fetch_row($res)) echo $r[0] . "\n";
