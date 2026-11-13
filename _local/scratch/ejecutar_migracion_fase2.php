<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db.php';

$sql = file_get_contents(__DIR__ . '/../sql/mantenimiento/20261030_ml_posiciones_02_raci_posicion.sql');

if (!$sql) {
    echo "No se pudo leer el archivo SQL.\n";
    exit(1);
}

// Ejecutar con multi_query
if (mysqli_multi_query($conexion, $sql)) {
    do {
        if ($res = mysqli_store_result($conexion)) {
            mysqli_free_result($res);
        }
    } while (mysqli_more_results($conexion) && mysqli_next_result($conexion));

    if (mysqli_errno($conexion)) {
        echo "Error en la ejecución: " . mysqli_error($conexion) . "\n";
        exit(1);
    }
    echo "¡Migración Fase 2 ejecutada con éxito!\n";
} else {
    echo "Error inicial: " . mysqli_error($conexion) . "\n";
    exit(1);
}
