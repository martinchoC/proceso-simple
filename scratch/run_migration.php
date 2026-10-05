<?php
require 'db.php';
$sql = file_get_contents('sql/mantenimiento/20261004_habilitar_rcx_puntos_venta_bocas.sql');
if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "SQL ejecutado correctamente.\n";
} else {
    echo "Error ejecutando SQL: " . $conn->error . "\n";
}
