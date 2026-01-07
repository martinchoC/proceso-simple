<?php
$host = 'localhost';
$user = 'u368960646_gestion';
$pass = 'Coco3948';
$db = 'u368960646_gestion';
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    echo "Fallo total: " . mysqli_connect_error();
} else {
    echo "¡CONEXIÓN EXITOSA! El problema estaba en el archivo .env";
}