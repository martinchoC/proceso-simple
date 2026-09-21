<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Wrapper de la sesión PHP con cookie endurecida y control de expiración
 * (idle + absoluta). No guarda datos de negocio: sólo identidad y metadatos.
 */
final class Session
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) Config::get('session.name', 'ECOMSID'));

        // La cookie se limita al directorio de la aplicación. Importante cuando
        // el dominio aloja además el sistema de administración: así ninguna de
        // las dos aplicaciones recibe la cookie de sesión de la otra.
        $base = base_path_uri();
        $cookiePath = $base === '' ? '/' : $base . '/';

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => (bool) Config::get('session.secure', true),
            'httponly' => true,
            'samesite' => (string) Config::get('session.samesite', 'Lax'),
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key][] = $message;
    }

    /** @return array<string,string[]> */
    public function pullFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    /** Rotación de ID: obligatoria en login y en cambios de privilegio. */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    /**
     * Cierra la sesión y escribe los datos liberando el cerrojo en disco para
     * no bloquear peticiones concurrentes del mismo usuario (ej: descarga paralela de imágenes).
     */
    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
