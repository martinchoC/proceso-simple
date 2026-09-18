<?php

declare(strict_types=1);

namespace App\Domain;

final class ResumenCarrito
{
    public readonly int $carritoId;
    /** @var LineaCarrito[] */
    public readonly array $lineas;
    /** @var int[] */
    public readonly array $noDisponibles;

    /**
     * Compatible tanto con ($carritoId, $lineas, $noDisponibles) como con ($lineas, $noDisponibles).
     *
     * @param int|LineaCarrito[] $carritoIdOLineas
     * @param LineaCarrito[]|array $lineasONoDisponibles
     * @param array $noDisponibles
     */
    public function __construct(
        int|array $carritoIdOLineas,
        array $lineasONoDisponibles = [],
        array $noDisponibles = [],
    ) {
        if (is_array($carritoIdOLineas)) {
            $this->carritoId = 0;
            $this->lineas = $carritoIdOLineas;
            $this->noDisponibles = $lineasONoDisponibles;
        } else {
            $this->carritoId = $carritoIdOLineas;
            $this->lineas = $lineasONoDisponibles;
            $this->noDisponibles = $noDisponibles;
        }
    }

    public function vacio(): bool
    {
        return $this->lineas === [];
    }

    public function cantidadLineas(): int
    {
        return count($this->lineas);
    }

    public function subtotalBruto(): float
    {
        return round($this->subtotalNeto() + $this->descuentos(), 2);
    }

    public function subtotalNeto(): float
    {
        return round(array_sum(array_map(
            static fn (LineaCarrito $l): float => $l->netoGravado(),
            $this->lineas
        )), 2);
    }

    public function descuentos(): float
    {
        return round(array_sum(array_map(
            static fn (LineaCarrito $l): float => $l->descuentoUnitario() * $l->cantidad,
            $this->lineas
        )), 2);
    }

    public function iva(): float
    {
        return round(array_sum(array_map(
            static fn (LineaCarrito $l): float => $l->ivaImporte(),
            $this->lineas
        )), 2);
    }

    public function total(): float
    {
        return round($this->subtotalNeto() + $this->iva(), 2);
    }
}
