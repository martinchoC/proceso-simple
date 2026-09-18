<?php

declare(strict_types=1);

namespace App\View;

use App\Support\Csrf;
use App\Support\Session;
use RuntimeException;

/**
 * Motor de plantillas PHP plano.
 * El nombre de la plantilla NUNCA proviene del request: se valida contra una
 * lista blanca de caracteres y se resuelve con realpath dentro del directorio
 * de vistas (defensa en profundidad contra path traversal).
 */
final class View
{
    /**
     * No recibe AuthService a propósito: las vistas públicas (login, errores)
     * deben poder renderizarse aunque la base de datos no esté disponible.
     * Las vistas autenticadas reciben $auth como dato desde su controlador.
     */
    public function __construct(
        private readonly string $path,
        private readonly Session $session,
        private readonly Csrf $csrf,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = []): string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#i', $template)) {
            throw new RuntimeException('Nombre de vista inválido.');
        }

        $file = realpath($this->path . '/' . $template . '.php');
        if ($file === false || !str_starts_with($file, realpath($this->path) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Vista no encontrada: $template");
        }

        $data['csrf'] = $this->csrf;
        $data['flash'] = $this->session->pullFlash();

        return $this->capturar($file, $data);
    }

    /** @param array<string,mixed> $data */
    private function capturar(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Fragmento reutilizable (layout, tarjeta, paginador).
     * No consume los mensajes flash: eso es responsabilidad de render().
     *
     * @param array<string,mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#i', $template)) {
            throw new RuntimeException('Nombre de vista inválido.');
        }

        $file = realpath($this->path . '/' . $template . '.php');
        if ($file === false || !str_starts_with($file, realpath($this->path) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Vista no encontrada: $template");
        }

        return $this->capturar($file, $data + ['csrf' => $this->csrf]);
    }
}
