<?php

declare(strict_types=1);

/**
 * Versión web del verificador, para planes SIN acceso SSH.
 *
 * USO:
 *   1. Copiar este archivo a public_html/verificar.php
 *   2. Abrir  https://tudominio.com/verificar.php?clave=XXXX
 *      donde XXXX son los primeros 16 caracteres de APP_KEY del .env
 *   3. BORRAR public_html/verificar.php cuando termines
 *
 * El token evita que el diagnóstico (nombres de tablas, estado de la BD) quede
 * expuesto a cualquiera. Aun así, este archivo NO debe quedar publicado.
 */

$rutasPosibles = [
    dirname(__DIR__, 2) . '/ecommerce_app',   // ejecutado desde bin/
    dirname(__DIR__) . '/ecommerce_app',      // copiado a public_html/
    __DIR__ . '/ecommerce_app',
];

$base = null;
foreach ($rutasPosibles as $ruta) {
    if (is_file($ruta . '/.env')) {
        $base = $ruta;
        break;
    }
}

header('Content-Type: text/plain; charset=utf-8');

if ($base === null) {
    http_response_code(500);
    exit("No se encontró la carpeta ecommerce_app con su .env.\n");
}

// --- Autorización -----------------------------------------------------------
$appKey = '';
foreach (file($base . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
    if (str_starts_with(trim($linea), 'APP_KEY=')) {
        $appKey = trim(substr(trim($linea), 8));
        break;
    }
}

$esperado = substr($appKey, 0, 16);
$recibido = (string) ($_GET['clave'] ?? '');

if ($esperado === '' || strlen($esperado) < 16 || !hash_equals($esperado, $recibido)) {
    http_response_code(404);
    exit("No encontrado.\n");
}

// --- Diagnóstico ------------------------------------------------------------
echo "Verificación de instalación — " . date('Y-m-d H:i:s') . "\n";
echo "Recordá borrar este archivo de public_html cuando termines.\n";

require $base . '/bin/verificar-instalacion.php';
