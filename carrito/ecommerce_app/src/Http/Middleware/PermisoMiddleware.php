<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\AutorizacionService;

/**
 * Control de acceso del lado servidor, por función de página
 * (conf__paginas_funciones.codigo_funcion) resuelta contra el perfil vigente
 * del usuario en la empresa del storefront.
 */
final class PermisoMiddleware implements Middleware
{
    public function __construct(
        private readonly AutorizacionService $autorizacion,
        private readonly string $codigoFuncion,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        if ($this->autorizacion->puede($this->codigoFuncion)) {
            return null;
        }

        $mensaje = 'No tenés permisos para realizar esta acción.';

        return $request->expectsJson()
            ? Response::json(['ok' => false, 'error' => $mensaje], 403)
            : Response::html('<h1>403</h1><p>' . e($mensaje) . '</p>', 403);
    }
}
