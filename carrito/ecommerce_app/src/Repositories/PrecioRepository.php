<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;

final class PrecioRepository extends Repository
{
    /**
     * Lista de precios asignada explícitamente a la entidad (vigente hoy).
     * Fuente principal: gestion__entidades_listas_precios.
     */
    public function listaPorEntidad(int $entidadId, int $empresaId): ?int
    {
        $id = $this->scalar(
            'SELECT elp.lista_precio_id
               FROM gestion__entidades_listas_precios elp
         INNER JOIN gestion__listas_precios lp
                 ON lp.lista_precio_id = elp.lista_precio_id
                AND lp.tabla_estado_registro_id = 1
              WHERE elp.entidad_id = :entidad_id
                AND elp.empresa_id = :empresa_id
                AND elp.tabla_estado_registro_id = 1
                AND elp.f_desde <= CURDATE()
                AND (elp.f_hasta IS NULL OR elp.f_hasta >= CURDATE())
           ORDER BY elp.f_desde DESC, elp.entidad_lista_precio_id DESC
              LIMIT 1',
            ['entidad_id' => $entidadId, 'empresa_id' => $empresaId]
        );

        return $id === null ? null : (int) $id;
    }

    /**
     * Fallback: lista definida en las condiciones comerciales del cliente.
     */
    public function listaPorCondicionesCliente(int $entidadId): ?int
    {
        $id = $this->scalar(
            'SELECT lista_precio_id
               FROM gestion__entidades_condiciones_clientes
              WHERE entidad_id = :entidad_id
                AND tabla_estado_registro_id = 1
                AND lista_precio_id IS NOT NULL
                AND f_desde <= CURDATE()
                AND (f_hasta IS NULL OR f_hasta >= CURDATE())
           ORDER BY f_desde DESC
              LIMIT 1',
            ['entidad_id' => $entidadId]
        );

        return $id === null ? null : (int) $id;
    }

    public function listaActiva(int $listaPrecioId, int $empresaId): bool
    {
        return (int) $this->scalar(
            'SELECT COUNT(*)
               FROM gestion__listas_precios
              WHERE lista_precio_id = :lista_id
                AND empresa_id = :empresa_id
                AND tabla_estado_registro_id = 1',
            ['lista_id' => $listaPrecioId, 'empresa_id' => $empresaId]
        ) > 0;
    }

    /** Descuento general pactado con el cliente (0 si no tiene). */
    public function descuentoGeneral(int $entidadId): float
    {
        return (float) ($this->scalar(
            'SELECT COALESCE(cliente_descuento_general, 0)
               FROM gestion__entidades_condiciones_clientes
              WHERE entidad_id = :entidad_id
                AND tabla_estado_registro_id = 1
                AND f_desde <= CURDATE()
                AND (f_hasta IS NULL OR f_hasta >= CURDATE())
           ORDER BY f_desde DESC
              LIMIT 1',
            ['entidad_id' => $entidadId]
        ) ?? 0);
    }
}
