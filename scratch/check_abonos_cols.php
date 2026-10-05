<?php
require_once __DIR__ . '/../config.php';
$r = mysqli_query($conexion, "DESCRIBE estudio__clientes_abonos");
while($row = mysqli_fetch_assoc($r)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
