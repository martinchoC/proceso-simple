<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Support\Csrf;

final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request): ?Response
    {
        if (!$request->isMutating()) {
            return null;
        }

        $token = $request->input('_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!$this->csrf->isValid(is_string($token) ? $token : null)) {
            return $request->expectsJson()
                ? Response::json(['ok' => false, 'error' => 'Token de seguridad inválido. Recargá la página.'], 419)
                : Response::html('<h1>419</h1><p>Token de seguridad inválido. Recargá la página.</p>', 419);
        }

        return null;
    }
}
