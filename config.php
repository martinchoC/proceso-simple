<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// config.php en la raíz del proyecto
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$project_folder = basename(__DIR__);

// BASE_URL, asset(), asset_local() y url()
require_once ROOT_PATH . "/core/assets.php";

require_once ROOT_PATH . "/db.php";
require_once ROOT_PATH . "/core/permisos.php";
$conexion = $conn;