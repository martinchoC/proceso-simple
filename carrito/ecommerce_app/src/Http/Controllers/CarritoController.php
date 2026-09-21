<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Support\Config;

final class CarritoController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = Config::int('ecom.empresa_id');
        $entidadId = $this->app->guard()->entidadId();
        $sucursalesEntrega = $this->app->entidades()->sucursalesEntrega($entidadId, $empresaId);
        $sucursalesCompra = $this->app->entidades()->sucursalesCompra($entidadId, $empresaId);
        $descuentoCliente = $this->app->precios()->descuentoGeneral($entidadId);

        $resumen = $this->app->carrito()->resumen(
            $this->app->guard()->usuarioId(),
            $entidadId
        );
        $productoIds = array_map(static fn ($linea): int => $linea->productoId, $resumen->lineas);
        $compatibilidades = $this->app->productos()->compatibilidadesPorProducto($productoIds, $empresaId);

        return $this->view('carrito/index', [
            'resumen'           => $resumen,
            'sucursalesEntrega' => $sucursalesEntrega,
            'sucursalesCompra'  => $sucursalesCompra,
            'descuentoCliente'  => $descuentoCliente,
            'compatibilidades'  => $compatibilidades,
        ]);
    }

    public function agregar(Request $request): Response
    {
        return $this->ejecutar($request, function (Request $req): string {
            $this->app->carrito()->agregar(
                $this->app->guard()->usuarioId(),
                $this->app->guard()->entidadId(),
                $this->productoId($req),
                $req->inputFloat('cantidad', 1)
            );
            return 'Producto agregado al carrito.';
        });
    }

    public function actualizar(Request $request): Response
    {
        return $this->ejecutar($request, function (Request $req): string {
            $this->app->carrito()->actualizarCantidad(
                $this->app->guard()->usuarioId(),
                $this->app->guard()->entidadId(),
                $this->productoId($req),
                $req->inputFloat('cantidad', 1)
            );
            return 'Cantidad actualizada.';
        });
    }

    public function fijar(Request $request): Response
    {
        return $this->ejecutar($request, function (Request $req): string {
            return $this->app->carrito()->fijarCantidad(
                $this->app->guard()->usuarioId(),
                $this->app->guard()->entidadId(),
                $this->productoId($req),
                $req->inputFloat('cantidad', 0)
            );
        });
    }

    public function eliminar(Request $request): Response
    {
        return $this->ejecutar($request, function (Request $req): string {
            $this->app->carrito()->eliminar(
                $this->app->guard()->usuarioId(),
                $this->app->guard()->entidadId(),
                $this->productoId($req)
            );
            return 'Producto eliminado del carrito.';
        });
    }

    public function vaciar(Request $request): Response
    {
        return $this->ejecutar($request, function (): string {
            $this->app->carrito()->vaciar(
                $this->app->guard()->usuarioId(),
                $this->app->guard()->entidadId()
            );
            return 'Carrito vaciado.';
        });
    }

    private function productoId(Request $request): int
    {
        $id = $request->inputInt('producto_id');
        if ($id <= 0) {
            throw new ValidationException('Producto inválido.');
        }
        return $id;
    }

    /**
     * Respuesta dual: JSON para el front asíncrono, redirect + flash para
     * navegación sin JavaScript (el sitio sigue siendo usable sin JS).
     *
     * @param callable(Request):string $accion
     */
    private function ejecutar(Request $request, callable $accion): Response
    {
        try {
            $mensaje = $accion($request);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $this->app->session()->flash('error', $e->getMessage());
            return $this->redirect('/carrito');
        }

        if ($request->expectsJson()) {
            $resumen = $this->app->carrito()->resumen(
                $this->app->guard()->usuarioId(),
                $this->app->guard()->entidadId()
            );

            $descuentoPct = $this->app->precios()->descuentoGeneral($this->app->guard()->entidadId());
            if ($descuentoPct <= 0 && count($resumen->lineas) > 0) {
                $descuentoPct = $resumen->lineas[0]->descuentoPct;
            }
            $descuentos = $resumen->descuentos();
            $ivaPct = count($resumen->lineas) > 0 ? $resumen->lineas[0]->ivaPorcentaje : 21.0;

            return Response::json([
                'ok'      => true,
                'mensaje' => $mensaje,
                'carrito' => [
                    'lineas'             => $resumen->cantidadLineas(),
                    'subtotal_bruto'     => $resumen->subtotalBruto(),
                    'subtotal_bruto_fmt' => money($resumen->subtotalBruto()),
                    'descuento_pct'      => $descuentoPct,
                    'descuento_importe'  => $descuentos,
                    'descuento_fmt'      => money($descuentos),
                    'subtotal_neto'      => $resumen->subtotalNeto(),
                    'subtotal_neto_fmt'  => money($resumen->subtotalNeto()),
                    'subtotal'           => $resumen->subtotalNeto(),
                    'subtotal_fmt'       => money($resumen->subtotalNeto()),
                    'iva_pct'            => $ivaPct,
                    'iva'                => $resumen->iva(),
                    'iva_fmt'            => money($resumen->iva()),
                    'total'              => $resumen->total(),
                    'total_fmt'          => money($resumen->total()),
                    'items'              => array_map(static fn (\App\Domain\LineaCarrito $l): array => [
                        'producto_id'                => $l->productoId,
                        'codigo'                     => $l->codigo,
                        'nombre'                     => $l->nombre,
                        'cantidad'                   => $l->cantidad,
                        'precio_lista_unit_fmt'      => money($l->precioListaNeto),
                        'precio_lista_total_fmt'     => money($l->precioListaTotal()),
                        'descuento_pct'              => (string) round($l->descuentoPct, 0),
                        'descuento_total_fmt'        => money($l->descuentoTotal()),
                        'neto_fmt'                   => money($l->netoGravado()),
                        'precio_lista_iva_unit_fmt'  => money($l->precioListaConIva()),
                        'precio_lista_iva_total_fmt' => money($l->precioListaConIvaTotal()),
                    ], $resumen->lineas),
                ],
            ]);
        }

        $this->app->session()->flash('exito', $mensaje);
        return $this->redirect('/carrito');
    }
}
