<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

interface Middleware
{
    /** Devuelve Response para cortar la cadena, o null para continuar. */
    public function handle(Request $request): ?Response;
}
