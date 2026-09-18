<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\SessionGuard;
use App\Support\Session;

/**
 * Exige sesión válida y aplica expiración por inactividad y absoluta.
 * También verifica que el usuario siga activo en BD (revocación inmediata).
 *
 * El AuthService llega como fábrica perezosa: los rechazos por falta de sesión
 * o por expiración no necesitan tocar la base de datos.
 */
final class AuthMiddleware implements Middleware
{
    /** @param callable():AuthService $authFactory */
    public function __construct(
        private readonly SessionGuard $guard,
        private readonly mixed $authFactory,
        private readonly Session $session,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        if (!$this->guard->check()) {
            return $this->rechazar($request, 'Debés iniciar sesión.');
        }

        if (!$this->guard->sesionVigente()) {
            $this->guard->cerrar();
            return $this->rechazar($request, 'Tu sesión expiró. Ingresá nuevamente.');
        }

        /** @var AuthService $auth */
        $auth = ($this->authFactory)();

        if (!$auth->usuarioSigueHabilitado()) {
            $auth->logout();
            return $this->rechazar($request, 'Tu usuario ya no está habilitado.');
        }

        $this->guard->tocar();

        return null;
    }

    private function rechazar(Request $request, string $mensaje): Response
    {
        if ($request->expectsJson()) {
            return Response::json(['ok' => false, 'error' => $mensaje], 401);
        }

        $this->session->flash('error', $mensaje);
        return Response::redirect(url('/login'));
    }
}
