<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SHOW TABLES LIKE 'gestion__cont%'");
while ($r = mysqli_fetch_row($res)) {
    echo $r[0] . "\n";
}
