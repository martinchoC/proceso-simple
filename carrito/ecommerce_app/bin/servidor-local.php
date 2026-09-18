<?php

declare(strict_types=1);

/**
 * Router para el servidor embebido de PHP. SÓLO para probar en tu máquina:
 * en Hostinger el ruteo lo resuelve el .htaccess.
 *
 *     cd <carpeta que contiene ps_ecommerce>
 *     php -S 127.0.0.1:8000 -t . ps_ecommerce/ecommerce_app/bin/servidor-local.php
 *
 * Después abrir  http://127.0.0.1:8000/ps_ecommerce/login
 */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

// bin/ -> ecommerce_app/ -> carpeta pública del proyecto
$frontController = dirname(__DIR__, 2) . '/index.php';
if (!is_file($frontController)) {
    $frontController = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/index.php';
}

$docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$path = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// Los archivos existentes (assets) los sirve el servidor embebido.
if ($path !== '/' && $docRoot !== '' && is_file($docRoot . $path)) {
    return false;
}

// Simula el .htaccess: SCRIPT_NAME debe apuntar al front controller real para
// que la aplicación deduzca bien el prefijo de URL (/ps_ecommerce).
$publico = '/' . trim(str_replace(rtrim($docRoot, '/'), '', dirname($frontController)), '/');
$_SERVER['SCRIPT_NAME'] = rtrim($publico, '/') . '/index.php';

require $frontController;
