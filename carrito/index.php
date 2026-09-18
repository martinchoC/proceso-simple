<?php

declare(strict_types=1);

use App\Http\Request;

/**
 * Único punto de entrada público.
 *
 * Estructura esperada (proyecto en una subcarpeta de public_html):
 *
 *   public_html/ps_ecommerce/
 *       ├── index.php          ← este archivo
 *       ├── .htaccess
 *       ├── assets/
 *       └── ecommerce_app/     ← código, .env y storage (bloqueado por .htaccess)
 *
 * También funciona con ecommerce_app fuera del directorio público, que es la
 * ubicación más segura si el hosting lo permite. Se prueban las dos.
 */
$rutasPosibles = [
    __DIR__ . '/ecommerce_app/bootstrap/app.php',            // dentro de la carpeta del proyecto
    dirname(__DIR__) . '/ecommerce_app/bootstrap/app.php',   // un nivel arriba
    dirname(__DIR__, 2) . '/ecommerce_app/bootstrap/app.php',// fuera de public_html
];

$bootstrap = null;
foreach ($rutasPosibles as $ruta) {
    if (is_file($ruta)) {
        $bootstrap = $ruta;
        break;
    }
}

if ($bootstrap === null) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Instalación incompleta</title>'
        . '<h1>No se encontró la aplicación</h1>'
        . '<p>Falta la carpeta <code>ecommerce_app</code>. Debe estar junto a este '
        . '<code>index.php</code>, o un nivel más arriba.</p>';
    exit;
}

$kernel = require $bootstrap;

$kernel->handle(Request::fromGlobals())->send();
