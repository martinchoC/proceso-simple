<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT * FROM conf__empresas");
echo "Total empresas: " . mysqli_num_rows($res) . "\n";
while ($r = mysqli_fetch_assoc($res)) {
    echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
