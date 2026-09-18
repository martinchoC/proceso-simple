<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\LineaCarrito;
use App\Domain\ResumenCarrito;
use App\Http\Exceptions\ValidationException;
use App\Repositories\CarritoRepository;
use App\Repositories\ProductoRepository;
use App\Support\Config;

/**
 * Carrito del lado servidor.
 *
 * Regla central: el cliente sólo envía producto_id y cantidad. Precio, IVA y
 * descuento se resuelven en el servidor en cada lectura, por lo que ni un
 * precio manipulado ni uno desactualizado pueden llegar al pedido.
 */
final class CarritoService
{
    private const MAX_LINEAS = 100;
    private const MAX_CANTIDAD = 99999.0;

    public function __construct(
        private readonly CarritoRepository $carritos,
        private readonly ProductoRepository $productos,
        private readonly PrecioService $precios,
    ) {
    }

    public function agregar(int $usuarioId, int $entidadId, int $productoId, float $cantidad): void
    {
        $this->validarCantidad($cantidad);
        $this->asegurarProductoVendible($entidadId, $productoId);

        $carritoId = $this->carritoId($usuarioId, $entidadId);

        if ($this->carritos->cantidadItems($carritoId) >= self::MAX_LINEAS) {
            throw new ValidationException('El carrito alcanzó el máximo de ' . self::MAX_LINEAS . ' productos.');
        }

        $this->carritos->agregar($carritoId, $productoId, $cantidad);
    }

    public function actualizarCantidad(int $usuarioId, int $entidadId, int $productoId, float $cantidad): void
    {
        $this->validarCantidad($cantidad);
        $this->carritos->actualizarCantidad($this->carritoId($usuarioId, $entidadId), $productoId, $cantidad);
    }

    public function fijarCantidad(int $usuarioId, int $entidadId, int $productoId, float $cantidad): string
    {
        $carritoId = $this->carritoId($usuarioId, $entidadId);

        if ($cantidad <= 0) {
            $this->carritos->eliminar($carritoId, $productoId);
            return 'Producto quitado del carrito.';
        }

        if ($cantidad > self::MAX_CANTIDAD) {
            throw new ValidationException('La cantidad no puede superar ' . self::MAX_CANTIDAD . '.');
        }

        $this->asegurarProductoVendible($entidadId, $productoId);

        $yaExiste = $this->carritos->tieneProducto($carritoId, $productoId);
        if (!$yaExiste && $this->carritos->cantidadItems($carritoId) >= self::MAX_LINEAS) {
            throw new ValidationException('El carrito alcanzó el máximo de ' . self::MAX_LINEAS . ' productos.');
        }

        $this->carritos->fijarCantidad($carritoId, $productoId, $cantidad);

        return $yaExiste ? 'Cantidad actualizada en el carrito.' : 'Producto agregado al carrito.';
    }

    public function eliminar(int $usuarioId, int $entidadId, int $productoId): void
    {
        $this->carritos->eliminar($this->carritoId($usuarioId, $entidadId), $productoId);
    }

    public function vaciar(int $usuarioId, int $entidadId): void
    {
        $this->carritos->vaciar($this->carritoId($usuarioId, $entidadId));
    }

    /** Valoriza el carrito completo con una sola consulta de productos. */
    public function resumen(int $usuarioId, int $entidadId): ResumenCarrito
    {
        $carritoId = $this->carritoId($usuarioId, $entidadId);
        $items = $this->carritos->items($carritoId);

        if ($items === []) {
            return $this->crearResumen($carritoId, [], []);
        }

        $ids = array_map(static fn (array $i): int => (int) $i['producto_id'], $items);
        $datos = $this->productos->datosParaValorizar(
            $ids,
            Config::int('ecom.empresa_id'),
            $this->precios->listaPara($entidadId)
        );

        $descuento = $this->precios->descuentoGeneral($entidadId);
        $lineas = [];
        $noDisponibles = [];

        foreach ($items as $item) {
            $productoId = (int) $item['producto_id'];
            $producto = $datos[$productoId] ?? null;

            if ($producto === null || (float) ($producto['precio_neto'] ?? 0) <= 0) {
                $noDisponibles[] = $productoId;
                continue;
            }

            $lineas[] = new LineaCarrito(
                productoId: $productoId,
                codigo: (string) $producto['producto_codigo'],
                nombre: (string) $producto['producto_nombre'],
                cantidad: (float) $item['cantidad'],
                precioListaNeto: (float) $producto['precio_neto'],
                descuentoPct: $descuento,
                ivaAlicuotaId: (int) $producto['iva_alicuota_id'],
                ivaPorcentaje: (float) $producto['iva_porcentaje'],
            );
        }

        return $this->crearResumen($carritoId, $lineas, $noDisponibles);
    }

    /**
     * @param LineaCarrito[] $lineas
     * @param int[] $noDisponibles
     */
    private function crearResumen(int $carritoId, array $lineas, array $noDisponibles): ResumenCarrito
    {
        try {
            return new ResumenCarrito($carritoId, $lineas, $noDisponibles);
        } catch (\TypeError) {
            // Compatibilidad si el servidor aún tiene la versión anterior de ResumenCarrito($lineas, $noDisponibles)
            /** @phpstan-ignore-next-line */
            return new ResumenCarrito($lineas, $noDisponibles);
        }
    }

    public function carritoId(int $usuarioId, int $entidadId): int
    {
        return $this->carritos->obtenerOCrear($usuarioId, Config::int('ecom.empresa_id'), $entidadId);
    }

    public function vaciarPorId(int $carritoId): void
    {
        $this->carritos->vaciar($carritoId);
    }

    private function validarCantidad(float $cantidad): void
    {
        if ($cantidad <= 0 || $cantidad > self::MAX_CANTIDAD) {
            throw new ValidationException('La cantidad debe ser mayor a 0 y menor a ' . self::MAX_CANTIDAD . '.');
        }
    }

    /** El producto debe existir en la empresa y tener precio vigente en la lista del cliente. */
    private function asegurarProductoVendible(int $entidadId, int $productoId): void
    {
        $producto = $this->productos->buscarPorId(
            $productoId,
            Config::int('ecom.empresa_id'),
            $this->precios->listaPara($entidadId)
        );

        if ($producto === null || (float) ($producto['precio_neto'] ?? 0) <= 0) {
            throw new ValidationException('El producto no está disponible para tu cuenta.');
        }
    }
}
