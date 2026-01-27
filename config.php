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
define('BASE_URL', $protocol . '://' . $host . '/' . $project_folder);

function asset($path)
{
    return BASE_URL . '/templates/adminlte/' . ltrim($path, '/');
}

function asset_local($path)
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

require_once ROOT_PATH . "/db.php";
$conexion = $conn;