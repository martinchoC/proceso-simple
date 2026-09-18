<?php

declare(strict_types=1);

namespace App\Support;

use ErrorException;
use Throwable;

/**
 * Convierte warnings/notices en excepciones y garantiza que en producción
 * nunca se filtren stack traces al cliente.
 */
final class ErrorHandler
{
    public static function register(Logger $logger, bool $debug): void
    {
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e) use ($logger, $debug): void {
            $logger->error($e->getMessage(), [
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $debug ? $e->getTraceAsString() : null,
            ]);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }

            if ($debug) {
                echo '<pre>' . e((string) $e) . '</pre>';
                return;
            }

            echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
                . '<h1>Se produjo un error</h1>'
                . '<p>El problema fue registrado. Intentá nuevamente en unos minutos.</p>';
        });
    }
}
