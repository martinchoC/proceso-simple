<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 mínimo (App\ => src/). Evita depender de Composer para
 * desplegar; si más adelante se agregan librerías externas, reemplazar por
 * vendor/autoload.php sin cambiar el resto del código.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require_once dirname(__DIR__) . '/src/Support/helpers.php';
