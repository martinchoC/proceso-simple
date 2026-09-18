<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use RuntimeException;

/** Error con semántica HTTP: el mensaje SIEMPRE es apto para el usuario final. */
class HttpException extends RuntimeException
{
    public function __construct(private readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
