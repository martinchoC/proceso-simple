<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\SessionGuard;

/** Evita que un usuario con sesión activa vuelva al login. Sin acceso a BD. */
final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly SessionGuard $guard)
    {
    }

    public function handle(Request $request): ?Response
    {
        if ($this->guard->check() && $this->guard->sesionVigente()) {
            return Response::redirect(url('/catalogo'));
        }
        return null;
    }
}
