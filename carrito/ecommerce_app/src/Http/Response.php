<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @param array<string,string> $headers */
    private function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    /** @param array<string,string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, $headers + ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    /** @param array<string,string> $headers */
    public static function raw(string $body, string $contentType = '', array $headers = [], int $status = 200): self
    {
        $h = $headers;
        if ($contentType !== '') {
            $h['Content-Type'] = $contentType;
        }
        return new self($body, $status, $h);
    }

    public function send(): void
    {
        http_response_code($this->status);

        // Cabeceras de seguridad por defecto. La CSP no permite inline scripts:
        // todo el JS vive en archivos bajo /assets.
        $security = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'Referrer-Policy'         => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; "
                . "style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'",
        ];

        // $this->headers tiene prioridad sobre los valores por defecto.
        foreach (array_merge($security, $this->headers) as $name => $value) {
            header("$name: $value");
        }

        echo $this->body;
    }
}
