<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\HttpException;
use App\Repositories\ProductoRepository;
use App\Support\Config;
use App\Support\Session;

/**
 * Casos de uso del catálogo. Devuelve datos ya valorizados: la vista no
 * calcula precios ni impuestos.
 */
final class CatalogoService
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly PrecioService $precios,
        private readonly ?Session $session = null,
    ) {
    }

    /**
     * @param array{terminos?:string[],categorias?:int[],marca_id?:int,modelo_id?:int,submodelo_id?:int} $filtros
     * @return array{items:array<int,array<string,mixed>>,total:int,pagina:int,paginas:int,por_pagina:int}
     */
    public function listar(int $entidadId, array $filtros, int $pagina, int $porPagina): array
    {
        $empresaId = Config::int('ecom.empresa_id');
        $listaId = $this->precios->listaPara($entidadId);

        $porPagina = max(1, min($porPagina, Config::int('ecom.max_page_size', 60)));
        $pagina = max(1, $pagina);

        // Optimización: Si el usuario navega el catálogo sin filtros de búsqueda,
        // cacheamos el conteo total por 5 minutos en sesión para no evaluar el conteo pesado en cada recarga/paginación.
        $sinFiltros = empty($filtros['terminos'])
            && empty($filtros['marca_id'])
            && empty($filtros['modelo_id'])
            && empty($filtros['submodelo_id'])
            && empty($filtros['categorias']);

        $total = null;
        $cacheKey = "_cat_tot_{$empresaId}_{$listaId}";
        if ($sinFiltros && $this->session !== null) {
            $cached = $this->session->get($cacheKey);
            if (is_array($cached) && isset($cached['t'], $cached['exp']) && time() < $cached['exp']) {
                $total = (int) $cached['t'];
            }
        }

        if ($total === null) {
            $total = $this->productos->contar($filtros, $empresaId, $listaId);
            if ($sinFiltros && $this->session !== null) {
                $this->session->set($cacheKey, ['t' => $total, 'exp' => time() + 300]);
            }
        }

        $paginas = (int) max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $paginas);

        $rows = $this->productos->buscar(
            $filtros,
            $empresaId,
            $listaId,
            $porPagina,
            ($pagina - 1) * $porPagina
        );

        $productoIds = array_map(static fn (array $r): int => (int) $r['producto_id'], $rows);
        $compatibilidades = $this->productos->compatibilidadesPorProducto($productoIds, $empresaId);
        $imagenes = $this->productos->imagenesPorProducto($productoIds, $empresaId);

        $descuento = $this->precios->descuentoGeneral($entidadId);

        return [
            'items'      => array_map(function (array $row) use ($descuento, $compatibilidades, $imagenes): array {
                $pid = (int) $row['producto_id'];
                $row['imagen_id'] = $imagenes[$pid] ?? null;
                $row['compatibilidad'] = $compatibilidades[$pid] ?? [
                    'marcas'        => [],
                    'modelos'       => [],
                    'submodelos'    => [],
                    'anios'         => [],
                    'combinaciones' => [],
                ];
                return $this->valorizar($row, $descuento);
            }, $rows),
            'total'      => $total,
            'pagina'     => $pagina,
            'paginas'    => $paginas,
            'por_pagina' => $porPagina,
        ];
    }

    /** @return array<string,mixed> */
    public function detalle(int $entidadId, int $productoId): array
    {
        $empresaId = Config::int('ecom.empresa_id');
        $listaId = $this->precios->listaPara($entidadId);

        $producto = $this->productos->buscarPorId($productoId, $empresaId, $listaId);
        if ($producto === null || (float) ($producto['precio_neto'] ?? 0) <= 0) {
            throw new HttpException(404, 'El producto no está disponible.');
        }

        $compatibilidades = $this->productos->compatibilidadesPorProducto([$productoId], $empresaId);
        $producto['compatibilidad'] = $compatibilidades[$productoId] ?? [
            'marcas'        => [],
            'modelos'       => [],
            'submodelos'    => [],
            'anios'         => [],
            'combinaciones' => [],
        ];

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
            'precio_lista'        => $neto,
            'precio_neto_lista'   => $neto,
            'descuento_pct'       => $descuentoPct,
            'precio_neto_cliente' => $netoConDescuento,
            'precio_final'        => round($netoConDescuento * (1 + $ivaPct / 100), 2),
        ];
    }
}
