<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Token CSRF por sesión (sincronizador). Se valida en todo método no seguro.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function isValid(?string $candidate): bool
    {
        $token = $this->session->get(self::KEY);
        return is_string($token) && is_string($candidate) && hash_equals($token, $candidate);
    }

    /** Rotar tras login/logout para evitar fijación del token. */
    public function rotate(): void
    {
        $this->session->forget(self::KEY);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e($this->token()) . '">';
    }
}
