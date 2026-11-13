<?php
require_once __DIR__ . '/../../db.php';
mysqli_query($conn, "SET profiling = 1");
mysqli_query($conn, "SELECT * FROM conf__usuarios WHERE usuario_id = 1");
mysqli_query($conn, "SELECT COUNT(*) FROM conf__modulos");
$res = mysqli_query($conn, "SHOW PROFILES");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
