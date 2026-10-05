<?php
require_once __DIR__ . '/../config.php';
$r = mysqli_query($conexion, "SELECT * FROM conf__modulos");
while($row = mysqli_fetch_assoc($r)) {
    print_r($row);
}
