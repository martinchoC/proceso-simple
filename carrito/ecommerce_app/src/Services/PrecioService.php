<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ValidationException;
use App\Repositories\PrecioRepository;
use App\Support\Config;

/**
 * Resolución de la lista de precios aplicable al cliente logueado.
 *
 * Orden de resolución (documentado a propósito: es una regla de negocio,
 * no un detalle de implementación):
 *   1. gestion__entidades_listas_precios vigente para la entidad.
 *   2. lista_precio_id de gestion__entidades_condiciones_clientes vigente.
 *   3. ECOM_LISTA_PRECIO_DEFAULT_ID.
 *
 * Si ninguna resuelve, se corta la operación: mostrar precios de una lista
 * arbitraria sería peor que no mostrar nada.
 */
final class PrecioService
{
    /** @var array<int,int> cache por entidad dentro de la request */
    private array $cache = [];

    public function __construct(private readonly PrecioRepository $precios)
    {
    }

    public function listaPara(int $entidadId): int
    {
        if (isset($this->cache[$entidadId])) {
            return $this->cache[$entidadId];
        }

        $empresaId = Config::int('ecom.empresa_id');

        $listaId = $this->precios->listaPorEntidad($entidadId, $empresaId)
            ?? $this->precios->listaPorCondicionesCliente($entidadId)
            ?? Config::int('ecom.lista_precio_default_id');

        if ($listaId <= 0 || !$this->precios->listaActiva($listaId, $empresaId)) {
            throw new ValidationException('No hay una lista de precios vigente para tu cuenta. Contactá a tu vendedor.');
        }

        return $this->cache[$entidadId] = $listaId;
    }

    public function descuentoGeneral(int $entidadId): float
    {
        $descuento = $this->precios->descuentoGeneral($entidadId);
        return ($descuento < 0 || $descuento > 100) ? 0.0 : $descuento;
    }
}
