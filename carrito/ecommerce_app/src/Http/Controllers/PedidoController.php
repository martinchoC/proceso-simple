<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;

final class PedidoController extends Controller
{
    public function index(Request $request): Response
    {
        $pagina = max(1, $request->queryInt('pagina', 1));

        return $this->view('pedidos/index', [
            'pedidos' => $this->app->pedidoService()->listar($this->app->guard()->entidadId(), $pagina),
            'pagina'  => $pagina,
        ]);
    }

    public function show(Request $request): Response
    {
        $pedido = $this->app->pedidoService()->detalle(
            $request->paramInt('id'),
            $this->app->guard()->entidadId()
        );

        // 404 y no 403: no se confirma la existencia de pedidos ajenos.
        if ($pedido === null) {
            throw new HttpException(404, 'Pedido no encontrado.');
        }

        return $this->view('pedidos/detalle', $pedido);
    }

    public function store(Request $request): Response
    {
        try {
            $resultado = $this->app->pedidoService()->confirmar(
                $this->app->guard()->usuarioId(),
                $this->app->guard()->entidadId(),
                (string) $request->input('observaciones', ''),
                $request->inputInt('entidad_sucursal_id', 0),
                $request->inputInt('sucursal_compra_id', 0)
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $this->app->session()->flash('error', $e->getMessage());
            return $this->redirect('/carrito');
        }

        if ($request->expectsJson()) {
            return Response::json(['ok' => true] + $resultado);
        }

        $this->app->session()->flash('exito', 'Pedido N° ' . $resultado['comprobante_nro'] . ' registrado.');
        return $this->redirect('/pedidos/' . $resultado['venta_pedido_id']);
    }
}
