<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Support\Config;

final class CatalogoController extends Controller
{
    public function index(Request $request): Response
    {
        $entidadId = $this->app->guard()->entidadId();
        $marcaId = $request->queryInt('marca_id');
        $modeloId = $request->queryInt('modelo_id');
        $submodeloId = $request->queryInt('submodelo_id');

        $filtros = [
            'terminos'     => $request->queryStringList('q'),
            'marca_id'     => $marcaId,
            'modelo_id'    => $modeloId,
            'submodelo_id' => $submodeloId,
        ];

        $resultado = $this->app->catalogo()->listar(
            $entidadId,
            $filtros,
            $request->queryInt('pagina', 1),
            Config::int('ecom.page_size', 24)
        );

        $resumenCarrito = $this->app->carrito()->resumen(
            $this->app->guard()->usuarioId(),
            $entidadId
        );

        $cantidadesCarrito = [];
        foreach ($resumenCarrito->lineas as $linea) {
            $cantidadesCarrito[$linea->productoId] = (int) $linea->cantidad;
        }

        return $this->view('catalogo/index', [
            'resultado'          => $resultado,
            'filtros'            => $filtros,
            'opciones'           => $this->app->catalogo()->filtrosDisponibles($marcaId, $modeloId),
            'items_carrito'      => $resumenCarrito->cantidadLineas(),
            'carrito'            => $resumenCarrito,
            'cantidades_carrito' => $cantidadesCarrito,
            'descuento_cliente'  => $this->app->precios()->descuentoGeneral($entidadId),
        ]);
    }

    public function modelos(Request $request): Response
    {
        $marcaId = $request->queryInt('marca_id');
        $modelos = $this->app->catalogo()->modelos($marcaId);

        return Response::json(['modelos' => $modelos]);
    }

    public function submodelos(Request $request): Response
    {
        $modeloId = $request->queryInt('modelo_id');
        $submodelos = $this->app->catalogo()->submodelos($modeloId);

        return Response::json(['submodelos' => $submodelos]);
    }

    public function show(Request $request): Response
    {
        $entidadId = $this->app->guard()->entidadId();
        $usuarioId = $this->app->guard()->usuarioId();
        $productoId = $request->paramInt('id');

        $producto = $this->app->catalogo()->detalle(
            $entidadId,
            $productoId
        );

        $resumenCarrito = $this->app->carrito()->resumen($usuarioId, $entidadId);
        $cantEnCarrito = 0;
        foreach ($resumenCarrito->lineas as $linea) {
            if ($linea->productoId === $productoId) {
                $cantEnCarrito = (int) $linea->cantidad;
                break;
            }
        }

        return $this->view('catalogo/detalle', [
            'producto'         => $producto,
            'items_carrito'    => $resumenCarrito->cantidadLineas(),
            'cantidad_carrito' => $cantEnCarrito,
        ]);
    }
}
