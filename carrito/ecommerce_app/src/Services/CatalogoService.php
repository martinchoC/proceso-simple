<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\HttpException;
use App\Repositories\ProductoRepository;
use App\Support\Config;

/**
 * Casos de uso del catálogo. Devuelve datos ya valorizados: la vista no
 * calcula precios ni impuestos.
 */
final class CatalogoService
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly PrecioService $precios,
    ) {
    }

    /**
     * @param array{terminos?:string[],categorias?:int[],marca_id?:int,modelo_id?:int} $filtros
     * @return array{items:array<int,array<string,mixed>>,total:int,pagina:int,paginas:int,por_pagina:int}
     */
    public function listar(int $entidadId, array $filtros, int $pagina, int $porPagina): array
    {
        $empresaId = Config::int('ecom.empresa_id');
        $listaId = $this->precios->listaPara($entidadId);

        $porPagina = max(1, min($porPagina, Config::int('ecom.max_page_size', 60)));
        $pagina = max(1, $pagina);

        $total = $this->productos->contar($filtros, $empresaId, $listaId);
        $paginas = (int) max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $paginas);

        $rows = $this->productos->buscar(
            $filtros,
            $empresaId,
            $listaId,
            $porPagina,
            ($pagina - 1) * $porPagina
        );

        $descuento = $this->precios->descuentoGeneral($entidadId);

        return [
            'items'      => array_map(fn (array $row): array => $this->valorizar($row, $descuento), $rows),
            'total'      => $total,
            'pagina'     => $pagina,
            'paginas'    => $paginas,
            'por_pagina' => $porPagina,
        ];
    }

    /** @return array<string,mixed> */
    public function detalle(int $entidadId, int $productoId): array
    {
        $listaId = $this->precios->listaPara($entidadId);

        $producto = $this->productos->buscarPorId($productoId, Config::int('ecom.empresa_id'), $listaId);
        if ($producto === null || (float) ($producto['precio_neto'] ?? 0) <= 0) {
            throw new HttpException(404, 'El producto no está disponible.');
        }

        return $this->valorizar($producto, $this->precios->descuentoGeneral($entidadId));
    }

    /** @return array{marcas:array<int,array<string,mixed>>,modelos:array<int,array<string,mixed>>,submodelos:array<int,array<string,mixed>>} */
    public function filtrosDisponibles(int $marcaId, int $modeloId = 0): array
    {
        $empresaId = Config::int('ecom.empresa_id');

        return [
            'marcas'     => $this->productos->marcas($empresaId),
            'modelos'    => $marcaId > 0 ? $this->productos->modelos($empresaId, $marcaId) : [],
            'submodelos' => $modeloId > 0 ? $this->productos->submodelos($empresaId, $modeloId) : [],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function modelos(int $marcaId): array
    {
        if ($marcaId <= 0) {
            return [];
        }
        return $this->productos->modelos(Config::int('ecom.empresa_id'), $marcaId);
    }

    /** @return array<int,array<string,mixed>> */
    public function submodelos(int $modeloId): array
    {
        if ($modeloId <= 0) {
            return [];
        }
        return $this->productos->submodelos(Config::int('ecom.empresa_id'), $modeloId);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function valorizar(array $row, float $descuentoPct): array
    {
        $neto = round((float) ($row['precio_neto'] ?? 0), 2);
        $netoConDescuento = round($neto * (1 - $descuentoPct / 100), 2);
        $ivaPct = (float) ($row['iva_porcentaje'] ?? 0);

        return $row + [
            'precio_neto_lista'   => $neto,
            'descuento_pct'       => $descuentoPct,
            'precio_neto_cliente' => $netoConDescuento,
            'precio_final'        => round($netoConDescuento * (1 + $ivaPct / 100), 2),
        ];
    }
}
