<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

/** Error de negocio esperado (422). No se loguea como incidente. */
final class ValidationException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }
}
