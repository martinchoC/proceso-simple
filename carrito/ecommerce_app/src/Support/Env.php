<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Lector de .env sin dependencias externas.
 * Las variables NO se publican en $_ENV/$_SERVER para que no aparezcan en
 * volcados de depuración ni en phpinfo().
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        if (!is_readable($path)) {
            throw new RuntimeException('No se encontró el archivo .env. Copiar .env.example y completarlo.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Comentario al final de línea (sólo si no está entre comillas)
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $value = trim(preg_replace('/\s+[;#].*$/', '', $value) ?? $value);
            } elseif ($value !== '') {
                $quote = $value[0];
                $end = strpos($value, $quote, 1);
                $value = $end !== false ? substr($value, 1, $end - 1) : substr($value, 1);
            }

            self::$vars[$key] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::$vars[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null ? $default : (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }
}
