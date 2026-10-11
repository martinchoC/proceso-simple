<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SHOW COLUMNS FROM gestion__productos");
while ($row = mysqli_fetch_assoc($r)) {
    echo $row['Field'] . "\n";
}
