<?php
// config.php en la raíz del proyecto
define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/gestion_multipyme_cp'); // Ajusta 'tu_proyecto' según tu estructura

function asset($path) {
    return BASE_URL . '/templates/adminlte/' . ltrim($path, '/');
}

function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

require_once "modules/conf/conexion.php";