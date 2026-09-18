<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Logger de archivo con rotación por tamaño. No registra datos sensibles:
 * el contexto lo arma el llamador y nunca debe incluir contraseñas ni tokens.
 */
final class Logger
{
    private const MAX_BYTES = 10_485_760; // 10 MB

    public function __construct(private readonly string $file)
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0o750, true);
        }
    }

    /** @param array<string,scalar|null> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string,scalar|null> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string,scalar|null> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string,scalar|null> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (is_file($this->file) && filesize($this->file) > self::MAX_BYTES) {
            @rename($this->file, $this->file . '.' . date('YmdHis'));
        }

        $line = sprintf(
            "[%s] %s: %s %s%s",
            date('c'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );

        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
