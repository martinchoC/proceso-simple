<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;

final class UsuarioRepository extends Repository
{
    /**
     * Busca por nombre de usuario. Devuelve también el hash: la comparación
     * se hace SIEMPRE con password_verify, nunca en SQL.
     *
     * @return array<string,mixed>|null
     */
    public function buscarPorUsuario(string $usuario): ?array
    {
        return $this->first(
            'SELECT usuario_id, usuario, usuario_nombre, email, password,
                    duracion_sid_minutos, tabla_estado_registro_id
               FROM conf__usuarios
              WHERE usuario = :usuario
              LIMIT 1',
            ['usuario' => $usuario]
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarActivoPorId(int $usuarioId): ?array
    {
        return $this->first(
            'SELECT usuario_id, usuario, usuario_nombre, email, duracion_sid_minutos
               FROM conf__usuarios
              WHERE usuario_id = :id
                AND tabla_estado_registro_id = 1
              LIMIT 1',
            ['id' => $usuarioId]
        );
    }

    /** Rehash transparente cuando cambia el algoritmo o el coste. */
    public function actualizarPassword(int $usuarioId, string $hash): void
    {
        $this->run(
            'UPDATE conf__usuarios SET password = :hash WHERE usuario_id = :id',
            ['hash' => $hash, 'id' => $usuarioId]
        );
    }
}
