<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;

/**
 * Resuelve permisos efectivos recorriendo el modelo existente:
 *
 *   conf__usuarios_perfiles (vigencia)
 *     -> conf__empresas_perfiles (empresa + módulo)
 *     -> conf__empresas_perfiles_funciones (asignado = 1)
 *     -> conf__paginas_funciones (codigo_funcion)
 */
final class PermisoRepository extends Repository
{
    private ?bool $soportaCodigo = null;

    /**
     * true si el ERP tiene la columna sobre la que se apoya el permiso por
     * función. Se consulta una vez por request.
     */
    public function soportaCodigoFuncion(): bool
    {
        return $this->soportaCodigo ??= (int) $this->scalar(
            'SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = :tabla AND column_name = :columna',
            ['tabla' => 'conf__paginas_funciones', 'columna' => 'codigo_funcion']
        ) > 0;
    }

    /** @return string[] códigos de función habilitados */
    public function codigosPorUsuario(int $usuarioId, int $empresaId, int $moduloId): array
    {
        $sql = 'SELECT DISTINCT pf.codigo_funcion
                  FROM conf__usuarios_perfiles up
            INNER JOIN conf__empresas_perfiles ep
                    ON ep.empresa_perfil_id = up.empresa_perfil_id
                   AND ep.empresa_id = :empresa_perfil
                   AND ep.modulo_id = :modulo_perfil
                   AND ep.tabla_estado_registro_id = 1
            INNER JOIN conf__empresas_perfiles_funciones epf
                    ON epf.empresa_perfil_id = ep.empresa_perfil_id
                   AND epf.empresa_id = :empresa_funcion
                   AND epf.asignado = 1
            INNER JOIN conf__paginas_funciones pf
                    ON pf.pagina_funcion_id = epf.pagina_funcion_id
                   AND pf.tabla_estado_registro_id = 1
            INNER JOIN conf__paginas pg
                    ON pg.pagina_id = pf.pagina_id
                   AND pg.modulo_id = :modulo_pagina
                   AND pg.tabla_estado_registro_id = 1
                 WHERE up.usuario_id = :usuario
                   AND up.tabla_estado_registro_id = 1
                   AND up.fecha_inicio <= CURDATE()
                   AND up.fecha_fin    >= CURDATE()
                   AND pf.codigo_funcion IS NOT NULL';

        $rows = $this->all($sql, [
            'usuario'         => $usuarioId,
            'empresa_perfil'  => $empresaId,
            'empresa_funcion' => $empresaId,
            'modulo_perfil'   => $moduloId,
            'modulo_pagina'   => $moduloId,
        ]);

        return array_map(static fn (array $r): string => (string) $r['codigo_funcion'], $rows);
    }
}
