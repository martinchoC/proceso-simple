<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Logger;
use App\Support\Session;

/**
 * Throttling de login sin tabla propia: los intentos se cuentan en la sesión
 * y cada intento fallido queda auditado en storage/logs/app.log.
 *
 * LIMITACIÓN CONOCIDA, a tener presente:
 * la sesión vive en una cookie, así que un atacante que la descarte entre
 * intentos evita el contador. Esto frena el caso real (alguien probando
 * claves desde un navegador) pero NO un ataque automatizado.
 *
 * La defensa fuerte contra fuerza bruta acá es doble y sigue en pie:
 * bcrypt con coste 12 en conf__usuarios (cada verificación cuesta ~250 ms) y
 * el log de intentos fallidos, que deja el rastro para bloquear la IP en el
 * servidor web. Si más adelante se admite una tabla propia, volver a contar
 * por (usuario, IP) en base de datos es la mejora natural.
 */
final class LoginIntentoRepository
{
    private const CLAVE = 'login_intentos';

    /** Tope de entradas guardadas: evita que la sesión crezca sin control. */
    private const MAX_ENTRADAS = 20;

    public function __construct(
        private readonly Session $session,
        private readonly Logger $logger,
    ) {
    }

    public function registrar(string $usuario, string $ip, bool $exito, string $userAgent): void
    {
        $usuario = mb_substr($usuario, 0, 20);

        if ($exito) {
            $this->session->set(self::CLAVE, []);
            return;
        }

        // Auditoría: el log es lo que queda aunque el atacante tire la cookie.
        $this->logger->warning('Intento de login fallido', [
            'usuario'    => $usuario,
            'ip'         => mb_substr($ip, 0, 45),
            'user_agent' => mb_substr($userAgent, 0, 255),
        ]);

        $intentos = $this->leer();
        $intentos[] = ['usuario' => $usuario, 'ts' => time()];

        if (count($intentos) > self::MAX_ENTRADAS) {
            $intentos = array_slice($intentos, -self::MAX_ENTRADAS);
        }

        $this->session->set(self::CLAVE, $intentos);
    }

    /** Intentos fallidos de ese usuario dentro de la ventana, en esta sesión. */
    public function fallidosRecientes(string $usuario, string $ip, int $ventanaMinutos): int
    {
        $usuario = mb_substr($usuario, 0, 20);
        $desde = time() - ($ventanaMinutos * 60);
        $total = 0;

        foreach ($this->leer() as $intento) {
            if ((string) ($intento['usuario'] ?? '') === $usuario
                && (int) ($intento['ts'] ?? 0) >= $desde) {
                $total++;
            }
        }

        return $total;
    }

    public function limpiarPorUsuario(string $usuario): void
    {
        $usuario = mb_substr($usuario, 0, 20);

        $restantes = array_values(array_filter(
            $this->leer(),
            static fn (array $i): bool => (string) ($i['usuario'] ?? '') !== $usuario
        ));

        $this->session->set(self::CLAVE, $restantes);
    }

    /** @return array<int,array{usuario:string,ts:int}> */
    private function leer(): array
    {
        $intentos = [];

        foreach ((array) $this->session->get(self::CLAVE, []) as $intento) {
            if (is_array($intento) && isset($intento['usuario'], $intento['ts'])) {
                $intentos[] = ['usuario' => (string) $intento['usuario'], 'ts' => (int) $intento['ts']];
            }
        }

        return $intentos;
    }
}
