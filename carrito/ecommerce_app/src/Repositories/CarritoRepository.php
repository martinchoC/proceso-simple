<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Database\Repository;
use App\Support\Session;
use PDO;

/**
 * Carrito persistido en servidor. NO guarda precios: se recalculan siempre
 * desde la lista vigente, así el cliente no puede fijar un precio viejo ni
 * manipularlo desde el navegador.
 */
final class CarritoRepository extends Repository
{
    public function __construct(PDO|Session|null $pdoOrSession = null)
    {
        $pdo = ($pdoOrSession instanceof PDO) ? $pdoOrSession : Connection::get();
        parent::__construct($pdo);
    }

    /** Un carrito activo por (empresa, usuario). Idempotente y libre de carreras. */
    public function obtenerOCrear(int $usuarioId, int $empresaId, int $entidadId): int
    {
        $this->run(
            'INSERT INTO ecom__carritos (empresa_id, usuario_id, entidad_id)
             VALUES (:empresa_id, :usuario_id, :entidad_id)
             ON DUPLICATE KEY UPDATE entidad_id = VALUES(entidad_id),
                                     actualizado_en = NOW()',
            ['empresa_id' => $empresaId, 'usuario_id' => $usuarioId, 'entidad_id' => $entidadId]
        );

        return (int) $this->scalar(
            'SELECT carrito_id FROM ecom__carritos
              WHERE empresa_id = :empresa_id AND usuario_id = :usuario_id',
            ['empresa_id' => $empresaId, 'usuario_id' => $usuarioId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function items(int $carritoId): array
    {
        return $this->all(
            'SELECT carrito_item_id, producto_id, cantidad
               FROM ecom__carritos_items
              WHERE carrito_id = :carrito_id
           ORDER BY carrito_item_id ASC',
            ['carrito_id' => $carritoId]
        );
    }

    public function agregar(int $carritoId, int $productoId, float $cantidad): void
    {
        $this->run(
            'INSERT INTO ecom__carritos_items (carrito_id, producto_id, cantidad)
             VALUES (:carrito_id, :producto_id, :cantidad)
             ON DUPLICATE KEY UPDATE cantidad = LEAST(cantidad + VALUES(cantidad), 999999),
                                     actualizado_en = NOW()',
            ['carrito_id' => $carritoId, 'producto_id' => $productoId, 'cantidad' => $cantidad]
        );
    }

    /** El WHERE incluye carrito_id: evita IDOR sobre ítems de otro usuario. */
    public function actualizarCantidad(int $carritoId, int $productoId, float $cantidad): void
    {
        $this->run(
            'UPDATE ecom__carritos_items
                SET cantidad = :cantidad, actualizado_en = NOW()
              WHERE carrito_id = :carrito_id
                AND producto_id = :producto_id',
            ['cantidad' => $cantidad, 'carrito_id' => $carritoId, 'producto_id' => $productoId]
        );
    }

    public function eliminar(int $carritoId, int $productoId): void
    {
        $this->run(
            'DELETE FROM ecom__carritos_items
              WHERE carrito_id = :carrito_id AND producto_id = :producto_id',
            ['carrito_id' => $carritoId, 'producto_id' => $productoId]
        );
    }

    public function vaciar(int $carritoId): void
    {
        $this->run(
            'DELETE FROM ecom__carritos_items WHERE carrito_id = :carrito_id',
            ['carrito_id' => $carritoId]
        );
    }

    public function cantidadItems(int $carritoId): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM ecom__carritos_items WHERE carrito_id = :carrito_id',
            ['carrito_id' => $carritoId]
        );
    }

    public function tieneProducto(int $carritoId, int $productoId): bool
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM ecom__carritos_items
              WHERE carrito_id = :carrito_id AND producto_id = :producto_id',
            ['carrito_id' => $carritoId, 'producto_id' => $productoId]
        ) > 0;
    }

    public function fijarCantidad(int $carritoId, int $productoId, float $cantidad): void
    {
        if ($cantidad <= 0) {
            $this->eliminar($carritoId, $productoId);
            return;
        }

        $this->run(
            'INSERT INTO ecom__carritos_items (carrito_id, producto_id, cantidad)
             VALUES (:carrito_id, :producto_id, :cantidad)
             ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad),
                                     actualizado_en = NOW()',
            ['carrito_id' => $carritoId, 'producto_id' => $productoId, 'cantidad' => $cantidad]
        );
    }
}
