<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ValidationException;
use App\Repositories\EntidadRepository;
use App\Repositories\PedidoRepository;
use App\Support\Config;
use App\Support\Logger;
use PDO;
use Throwable;

/**
 * Confirmación del pedido.
 *
 * Garantías:
 *  - Todo ocurre en UNA transacción: comprobante, pedido, detalles, puente
 *    del storefront y vaciado del carrito. Si algo falla, no queda nada a medias.
 *  - Los importes se recalculan en el servidor desde el carrito valorizado.
 *  - La numeración usa UPSERT + SELECT ... FOR UPDATE sobre el numerador.
 */
final class PedidoService
{
    private readonly PDO $pdo;
    private readonly PedidoRepository $pedidos;
    private readonly ?EntidadRepository $entidades;
    private readonly CarritoService $carrito;
    private readonly Logger $logger;

    public function __construct(
        PDO $pdo,
        PedidoRepository $pedidos,
        mixed ...$args
    ) {
        $this->pdo = $pdo;
        $this->pedidos = $pedidos;

        $entidades = null;
        $carrito = null;
        $logger = null;

        foreach ($args as $arg) {
            if ($arg instanceof EntidadRepository) {
                $entidades = $arg;
            } elseif ($arg instanceof CarritoService) {
                $carrito = $arg;
            } elseif ($arg instanceof Logger) {
                $logger = $arg;
            }
        }

        $this->entidades = $entidades;
        $this->carrito = $carrito ?? throw new \InvalidArgumentException('CarritoService es requerido en PedidoService');
        $this->logger = $logger ?? new Logger('ecommerce', 'php://stdout');
    }

    /**
     * @return array{venta_pedido_id:int,comprobante_nro:int,total:float}
     */
    public function confirmar(
        int $usuarioId,
        int $entidadId,
        string $observaciones = '',
        int $entidadSucursalId = 0,
        int $sucursalCompraId = 0
    ): array {
        $resumen = $this->carrito->resumen($usuarioId, $entidadId);

        if ($resumen->vacio()) {
            throw new ValidationException('El carrito está vacío.');
        }
        if ($resumen->noDisponibles !== []) {
            throw new ValidationException('Hay productos sin precio vigente en tu carrito. Revisalo antes de confirmar.');
        }

        $empresaId = Config::int('ecom.empresa_id');
        $comprobanteTipoId = Config::int('ecom.comprobante_tipo_pedido', 1);
        $estadoConfirmado = Config::int('estados.confirmado', 5);

        // 1. Resolver sucursal de compra (de la empresa) asignada a la entidad
        $sucursalId = $sucursalCompraId;
        if ($sucursalId <= 0 && $this->entidades !== null) {
            $sucursalId = $this->entidades->sucursalCompraPrincipal($entidadId, $empresaId);
        }
        if ($sucursalId <= 0) {
            $sucursalId = Config::int('ecom.sucursal_id', 1);
        }

        // 2. Resolver sucursal de entrega (domicilio del cliente)
        $sucursalEntrega = null;
        if ($entidadSucursalId > 0 && $this->entidades !== null) {
            $sucursalEntrega = $this->entidades->buscarSucursalEntrega($entidadSucursalId, $entidadId, $empresaId);
            if ($sucursalEntrega === null) {
                $entidadSucursalId = 0;
            }
        }

        if ($entidadSucursalId <= 0 && $this->entidades !== null) {
            $entidadSucursalId = $this->entidades->sucursalPrincipal($entidadId, $empresaId);
            if ($entidadSucursalId > 0) {
                $sucursalEntrega = $this->entidades->buscarSucursalEntrega($entidadSucursalId, $entidadId, $empresaId);
            }
        }

        $direccionEntrega = '';
        if ($sucursalEntrega !== null && !empty($sucursalEntrega['sucursal_direccion'])) {
            $direccionEntrega = mb_substr((string) $sucursalEntrega['sucursal_direccion'], 0, 50);
        }

        $this->pdo->beginTransaction();

        try {
            $puntoVentaId = $this->pedidos->puntoVentaPorSucursal($empresaId, $sucursalId, $comprobanteTipoId);
            $numero = $this->pedidos->siguienteNumero($empresaId, $puntoVentaId, $comprobanteTipoId);

            $subtotal = $resumen->subtotalNeto();
            $iva = $resumen->iva();
            $total = $resumen->total();
            $fecha = date('Y-m-d');

            $comprobanteId = $this->pedidos->crearComprobante([
                'tabla_origen_id'     => Config::int('ecom.tabla_origen_pedidos_id'),
                'modulo'              => 'ecommerce',
                'empresa_id'          => $empresaId,
                'sucursal_id'         => $sucursalId,
                'comprobante_tipo_id' => $comprobanteTipoId,
                'punto_venta_id'      => $puntoVentaId,
                'comprobante_nro'     => $numero,
                'entidad_id'          => $entidadId,
                'entidad_sucursal_id' => $entidadSucursalId,
                'f_emision'           => $fecha,
                'moneda_id'           => Config::int('ecom.moneda_id', 1),
                'tipo_cambio'         => 1.0,
                'importe_bruto'       => $subtotal + $resumen->descuentos(),
                'descuento_general'   => $resumen->descuentos(),
                'importe_neto'        => $subtotal,
                'importe_iva'         => $iva,
                'importe_total'       => $total,
                'importe_pendiente'   => $total,
                'estado_id'           => $estadoConfirmado,
                'usuario_id'          => $usuarioId,
            ]);

            $ventaPedidoId = $this->pedidos->crearPedido([
                'empresa_id'            => $empresaId,
                'sucursal_id'           => $sucursalId,
                'comprobante_tipo_id'   => $comprobanteTipoId,
                'comprobante_id'        => $comprobanteId,
                'punto_venta_id'        => $puntoVentaId,
                'comprobante_nro'       => $numero,
                'entidad_id'            => $entidadId,
                'entidad_sucursal_id'   => $entidadSucursalId,
                'f_emision'             => $fecha,
                'condicion_pago_id'     => $this->entidades?->condicionPagoVigente($entidadId) ?? 0,
                'direccion_entrega'     => $direccionEntrega,
                'moneda_id'             => Config::int('ecom.moneda_id', 1),
                'tipo_cambio'           => 1.0,
                'subtotal'              => $subtotal,
                'descuento_general_pct' => 0,
                'descuentos'            => $resumen->descuentos(),
                'impuestos'             => $iva,
                'total'                 => $total,
                'observaciones'         => mb_substr($observaciones, 0, 500),
                'estado_id'             => $estadoConfirmado,
            ]);

            $this->pedidos->vincularComprobanteConPedido($comprobanteId, $ventaPedidoId);

            foreach ($resumen->lineas as $linea) {
                $this->pedidos->crearDetalle([
                    'venta_pedido_id'       => $ventaPedidoId,
                    'producto_id'           => $linea->productoId,
                    'cantidad'              => $linea->cantidad,
                    'precio_unitario'       => $linea->precioListaNeto,
                    'descuento_general_pct' => $linea->descuentoPct,
                    'descuento_general'     => $linea->descuentoUnitario(),
                    'precio_unitario_bruto' => $linea->precioListaNeto,
                    'precio_unitario_neto'  => $linea->precioUnitarioNeto(),
                    'neto_gravado'          => $linea->netoGravado(),
                    'iva_alicuota_id'       => $linea->ivaAlicuotaId,
                    'iva_porcentaje'        => $linea->ivaPorcentaje,
                    'iva_importe'           => $linea->ivaImporte(),
                    'total_linea'           => $linea->total(),
                    'estado_id'             => $estadoConfirmado,
                ]);
            }

            $this->pedidos->registrarOrigenStorefront($ventaPedidoId, $empresaId, $usuarioId, $entidadId);
            $this->carrito->vaciarPorId($resumen->carritoId);

            $this->pdo->commit();

            $this->logger->info('Pedido confirmado', [
                'venta_pedido_id' => $ventaPedidoId,
                'usuario_id'      => $usuarioId,
                'entidad_id'      => $entidadId,
                'total'           => $total,
            ]);

            return [
                'venta_pedido_id' => $ventaPedidoId,
                'comprobante_nro' => $numero,
                'total'           => $total,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listar(int $entidadId, int $pagina, int $porPagina = 20): array
    {
        $pagina = max(1, $pagina);
        return $this->pedidos->listarPorEntidad(
            $entidadId,
            Config::int('ecom.empresa_id'),
            $porPagina,
            ($pagina - 1) * $porPagina
        );
    }

    /** @return array{cabecera:array<string,mixed>,detalles:array<int,array<string,mixed>>,remitos:array<int,array<string,mixed>>}|null */
    public function detalle(int $ventaPedidoId, int $entidadId): ?array
    {
        $cabecera = $this->pedidos->buscarDeEntidad($ventaPedidoId, $entidadId, Config::int('ecom.empresa_id'));
        if ($cabecera === null) {
            return null;
        }

        return [
            'cabecera' => $cabecera,
            'detalles' => $this->pedidos->detalles($ventaPedidoId),
            'remitos'  => $this->pedidos->remitosPorPedido($ventaPedidoId),
        ];
    }
}
