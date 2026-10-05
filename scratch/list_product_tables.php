<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SHOW TABLES LIKE 'gestion__productos_%'");
while ($row = mysqli_fetch_row($r)) {
    echo $row[0] . "\n";
}
