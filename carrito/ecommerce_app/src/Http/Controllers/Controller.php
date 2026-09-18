<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Response;
use App\Kernel;

abstract class Controller
{
    public function __construct(protected readonly Kernel $app)
    {
    }

    /**
     * Renderiza una vista. $auth se inyecta sólo si hay sesión iniciada: las
     * vistas públicas no deben forzar la conexión a la base de datos.
     *
     * @param array<string,mixed> $data
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        if ((int) $this->app->session()->get('usuario_id', 0) > 0) {
            $data['auth'] ??= $this->app->guard();
        }

        return Response::html($this->app->view()->render($template, $data), $status);
    }

    /**
     * Renderiza sólo un parcial, para las respuestas asíncronas.
     *
     * El fragmento lo arma el servidor a propósito: así el formato de
     * precios y el escapado de HTML viven en un único lugar y no se
     * reimplementan en JavaScript.
     *
     * @param array<string,mixed> $data
     */
    protected function fragmento(string $template, array $data = []): Response
    {
        $data['csrf'] ??= $this->app->csrf();

        return Response::html($this->app->view()->partial($template, $data));
    }

    /**
     * Datos de sucursales para el resumen del pedido.
     *
     * Se arma acá porque lo consumen el panel lateral del catálogo y la
     * página del carrito, y ambos deben mostrar exactamente lo mismo.
     *
     * @return array<string,mixed>
     */
    protected function datosSucursales(): array
    {
        $entidadId = $this->app->guard()->entidadId();
        $empresaId = \App\Support\Config::int('ecom.empresa_id');
        $entidades = $this->app->entidades();

        $compra  = $entidades->sucursalesDeCompra($entidadId, $empresaId);
        $entrega = $entidades->sucursalesDeEntrega($entidadId, $empresaId);

        // Por defecto la primera de cada lista (la principal, o la más
        // reciente). La elección real se hace en el checkout y el servidor la
        // revalida contra las asignadas al cliente antes de confirmar.
        return [
            'sucursalesCompra'   => $compra,
            'compraSeleccionada' => (int) ($compra[0]['sucursal_id'] ?? 0),
            'sucursalesEntrega'  => $entrega,
            'entregaSeleccionada' => (int) ($entrega[0]['sucursal_id'] ?? 0),
        ];
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }
}
