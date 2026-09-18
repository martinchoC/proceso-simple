<?php

declare(strict_types=1);

/**
 * DIAGNÓSTICO DE UBICACIÓN — archivo temporal.
 *
 * Subir a  public_html/ps_ecommerce/diag.php  y abrir:
 *     https://tudominio.com/ps_ecommerce/diag.php
 *
 * Responde una sola pregunta: ¿los archivos están donde el servidor los busca?
 * No muestra credenciales ni contenido de archivos.
 *
 * >>> BORRAR ESTE ARCHIVO CUANDO TERMINES <<<
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$aqui = __DIR__;

echo "DIAGNÓSTICO — " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 62) . "\n\n";

echo "Carpeta de este archivo:\n  $aqui\n\n";
echo "SCRIPT_NAME : " . ($_SERVER['SCRIPT_NAME'] ?? '?') . "\n";
echo "REQUEST_URI : " . ($_SERVER['REQUEST_URI'] ?? '?') . "\n";
echo "PHP         : " . PHP_VERSION . (PHP_VERSION_ID >= 80100 ? '  (OK)' : '  (¡se requiere 8.1+!)') . "\n\n";

echo "-- ¿Está todo en esta carpeta? -------------------------------\n";

$requeridos = [
    'index.php'                          => 'archivo',
    '.htaccess'                          => 'archivo',
    'assets'                             => 'carpeta',
    'ecommerce_app'                      => 'carpeta',
    'ecommerce_app/bootstrap/app.php'    => 'archivo',
    'ecommerce_app/.env'                 => 'archivo',
    'ecommerce_app/storage/logs'         => 'carpeta',
    'ecommerce_app/storage/cache'        => 'carpeta',
    'assets/vendor/adminlte/css/adminlte.min.css' => 'archivo',
];

$faltan = [];
foreach ($requeridos as $rel => $tipo) {
    $ruta = $aqui . '/' . $rel;
    $existe = $tipo === 'carpeta' ? is_dir($ruta) : is_file($ruta);

    $detalle = '';
    if ($existe) {
        $perm = substr(sprintf('%o', fileperms($ruta)), -3);
        $detalle = "permisos $perm";
        if ($tipo === 'carpeta' && !is_writable($ruta) && str_contains($rel, 'storage')) {
            $detalle .= ' — NO ESCRIBIBLE';
        }
        if ($tipo === 'archivo' && !is_readable($ruta)) {
            $detalle .= ' — NO LEGIBLE';
        }
    } else {
        $faltan[] = $rel;
    }

    printf("  [%s] %-46s %s\n", $existe ? 'OK ' : 'NO ', $rel, $detalle);
}

echo "\n-- Contenido real de esta carpeta ----------------------------\n";
$items = @scandir($aqui) ?: [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }
    printf("  %-40s %s\n", $item, is_dir($aqui . '/' . $item) ? '<carpeta>' : '<archivo>');
}

echo "\n-- mod_rewrite -----------------------------------------------\n";
if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    echo in_array('mod_rewrite', $mods, true)
        ? "  mod_rewrite ACTIVO\n"
        : "  mod_rewrite NO aparece activo\n";
} else {
    echo "  No se puede consultar desde PHP (normal en LiteSpeed/FastCGI).\n";
    echo "  Probá abrir /ps_ecommerce/login : si da 404 y /ps_ecommerce/index.php\n";
    echo "  funciona, el problema es el rewrite (revisar RewriteBase).\n";
}

echo "\n" . str_repeat('=', 62) . "\n";

if ($faltan === []) {
    echo "Los archivos están en su lugar.\n";
    echo "Si igual ves 403, revisá permisos: carpetas 755, archivos 644.\n";
} else {
    echo "FALTAN en esta carpeta:\n";
    foreach ($faltan as $f) {
        echo "  - $f\n";
    }
    echo "\nCausa más frecuente: al extraer el ZIP quedó una carpeta de más,\n";
    echo "por ejemplo  public_html/ps_ecommerce/ps_ecommerce/index.php\n";
    echo "En el listado de arriba se ve dónde quedaron realmente los archivos.\n";
}

echo "\nBORRÁ ESTE ARCHIVO CUANDO TERMINES.\n";
