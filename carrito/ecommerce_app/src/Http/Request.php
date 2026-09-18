<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,string> $params  parámetros de ruta ({id})
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $query,
        private array $post,
        private array $params = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');

        // Si la aplicación vive en una subcarpeta (ej. /ps_ecommerce), se quita
        // ese prefijo antes de enrutar: las rutas se declaran siempre desde la
        // raíz de la aplicación. El prefijo sale de SCRIPT_NAME, que lo genera
        // el servidor, no el cliente.
        $base = base_path_uri();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        // Petición directa al front controller (útil para diagnosticar cuando el
        // rewrite no está activo): /ps_ecommerce/index.php equivale a la raíz.
        if ($path === '/index.php' || str_ends_with($path, '/index.php')) {
            $path = substr($path, 0, -strlen('/index.php'));
        }

        $path = '/' . trim($path, '/');

        return new self($method, $path === '/' ? '/' : rtrim($path, '/'), $_GET, $_POST);
    }

    /** @param array<string,string> $params */
    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;
        return $clone;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    public function paramInt(string $key): int
    {
        return (int) filter_var($this->params[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $value = filter_var($this->query[$key] ?? null, FILTER_VALIDATE_INT);
        return $value === false || $value === null ? $default : $value;
    }

    /** @return int[] lista de enteros positivos, deduplicada y acotada */
    public function queryIntList(string $key, int $max = 50): array
    {
        $raw = $this->query[$key] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $ids = [];
        foreach ($raw as $item) {
            $value = filter_var($item, FILTER_VALIDATE_INT);
            if ($value !== false && $value > 0) {
                $ids[$value] = $value;
            }
            if (count($ids) >= $max) {
                break;
            }
        }
        return array_values($ids);
    }

    /**
     * Lista de términos de texto (ej. q[]=bisagra&q[]=capot).
     * Devuelve valores recortados, sin vacíos, sin repetidos y acotados en
     * cantidad y longitud: son entrada de usuario que llega al WHERE.
     *
     * @return string[]
     */
    public function queryStringList(string $key, int $max = 6, int $maxLen = 60): array
    {
        $raw = $this->query[$key] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $valores = [];
        foreach ($raw as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $texto = trim(mb_substr((string) $item, 0, $maxLen));
            if ($texto === '') {
                continue;
            }
            $valores[mb_strtolower($texto)] = $texto;
            if (count($valores) >= $max) {
                break;
            }
        }

        return array_values($valores);
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    public function inputInt(string $key, int $default = 0): int
    {
        $value = filter_var($this->post[$key] ?? null, FILTER_VALIDATE_INT);
        return $value === false || $value === null ? $default : $value;
    }

    public function inputFloat(string $key, float $default = 0.0): float
    {
        $value = filter_var($this->post[$key] ?? null, FILTER_VALIDATE_FLOAT);
        return $value === false || $value === null ? $default : $value;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->post;
    }

    public function isMutating(): bool
    {
        return !in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function expectsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    public function ip(): string
    {
        // Sin proxy de confianza configurado NO se lee X-Forwarded-For:
        // es falsificable y rompería el rate limiting.
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
