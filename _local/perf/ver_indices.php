<?php
require_once __DIR__ . '/../../db.php';
$r = mysqli_query($conn, 'SHOW INDEX FROM conf__paginas');
while($row = mysqli_fetch_assoc($r)) {
    printf("%-20s %-20s %-20s\n", $row['Table'], $row['Key_name'], $row['Column_name']);
}
