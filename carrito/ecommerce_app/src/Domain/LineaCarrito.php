<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Línea valorizada del carrito. Inmutable: se construye desde datos de BD,
 * nunca desde el request.
 */
final class LineaCarrito
{
    public function __construct(
        public readonly int $productoId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly float $cantidad,
        public readonly float $precioListaNeto,
        public readonly float $descuentoPct,
        public readonly int $ivaAlicuotaId,
        public readonly float $ivaPorcentaje,
        public readonly array $compatibilidad = [],
    ) {
    }

    public function precioListaTotal(): float
    {
        return round($this->cantidad * $this->precioListaNeto, 2);
    }

    public function precioListaConIva(): float
    {
        return round($this->precioListaNeto * (1 + $this->ivaPorcentaje / 100), 2);
    }

    public function precioListaConIvaTotal(): float
    {
        return round($this->cantidad * $this->precioListaConIva(), 2);
    }

    public function precioUnitarioNeto(): float
    {
        return round($this->precioListaNeto * (1 - $this->descuentoPct / 100), 2);
    }

    public function descuentoUnitario(): float
    {
        return round($this->precioListaNeto - $this->precioUnitarioNeto(), 2);
    }

    public function descuentoTotal(): float
    {
        return round($this->cantidad * $this->descuentoUnitario(), 2);
    }

    public function netoGravado(): float
    {
        return round($this->cantidad * $this->precioUnitarioNeto(), 2);
    }

    public function ivaImporte(): float
    {
        return round($this->netoGravado() * $this->ivaPorcentaje / 100, 2);
    }

    public function total(): float
    {
        return round($this->netoGravado() + $this->ivaImporte(), 2);
    }
}
