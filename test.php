<?php
// Forzar reporte de errores al máximo
ini_set('display_errors', 1);
error_reporting(E_ALL);
$host = 'localhost';
$user = 'u368960646_gestion';
$pass = 'Coco3948';
$db = 'u368960646_gestion';
try {
    echo "Intentando conectar con el usuario: $user ...<br>";
    $conn = mysqli_connect($host, $user, $pass, $db);
    echo "¡CONEXIÓN EXITOSA!";
} catch (Exception $e) {
    echo "Error capturado: " . $e->getMessage();
}