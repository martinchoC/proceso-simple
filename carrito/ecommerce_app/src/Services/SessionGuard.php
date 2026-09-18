<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use App\Support\Session;

/**
 * Estado de identidad en la sesión, SIN dependencias de base de datos.
 *
 * Separarlo de AuthService permite que las páginas públicas (login, errores)
 * y los rechazos por sesión expirada se resuelvan sin abrir una conexión, y
 * que un problema de BD no impida mostrar la pantalla de login.
 */
final class SessionGuard
{
    public function __construct(private readonly Session $session)
    {
    }

    public function check(): bool
    {
        return $this->usuarioId() > 0;
    }

    public function usuarioId(): int
    {
        return (int) $this->session->get('usuario_id', 0);
    }

    public function entidadId(): int
    {
        return (int) $this->session->get('entidad_id', 0);
    }

    public function nombre(): string
    {
        return (string) $this->session->get('usuario_nombre', '');
    }

    public function entidadNombre(): string
    {
        return (string) $this->session->get('entidad_nombre', '');
    }

    /** Expiración por inactividad (por usuario) y expiración absoluta. */
    public function sesionVigente(): bool
    {
        $ahora = time();
        $ultima = (int) $this->session->get('last_activity', 0);
        $inicio = (int) $this->session->get('login_time', 0);

        if ($ultima === 0 || $inicio === 0) {
            return false;
        }

        $idle = (int) $this->session->get('idle_minutes', Config::int('session.idle_minutes', 60));
        if (($ahora - $ultima) > ($idle * 60)) {
            return false;
        }

        return ($ahora - $inicio) <= (Config::int('session.absolute_minutes', 480) * 60);
    }

    public function tocar(): void
    {
        $this->session->set('last_activity', time());
    }

    /** @param array<string,scalar> $datos */
    public function iniciar(array $datos): void
    {
        foreach ($datos as $clave => $valor) {
            $this->session->set($clave, $valor);
        }
        $this->session->set('login_time', time());
        $this->session->set('last_activity', time());
    }

    public function cerrar(): void
    {
        $this->session->destroy();
    }
}
