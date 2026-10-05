<?php
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = tesoreriaObtenerComprobantesCancelables($conexion, 2, 'RCX');
echo "Comprobantes cancelables para RCX en empresa 2: " . count($res) . "\n";
if (count($res) > 0) {
    print_r($res[0]);
}
