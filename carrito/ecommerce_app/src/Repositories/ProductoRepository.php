<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;

/**
 * Consultas del catálogo. Todos los filtros se traducen a placeholders:
 * no se concatena NINGÚN valor de usuario en el SQL. Los únicos fragmentos
 * dinámicos son nombres de placeholders generados por el propio código.
 */
final class ProductoRepository extends Repository
{
    /**
     * Subconsulta de precio vigente para la lista del cliente.
     * Índice de apoyo: idx_listas_precios_productos_lista_producto_vigencia.
     */
    private const PRECIO_VIGENTE = '(SELECT lpp.precio_final
                                       FROM gestion__listas_precios_productos lpp
                                      WHERE lpp.producto_id = p.producto_id
                                        AND lpp.empresa_id = p.empresa_id
                                        AND lpp.lista_precio_id = :lista_precio_id
                                        AND lpp.tabla_estado_registro_id = 1
                                        AND lpp.precio_final > 0
                                        AND lpp.f_desde <= CURDATE()
                                        AND (lpp.f_hasta IS NULL OR lpp.f_hasta >= CURDATE())
                                      ORDER BY lpp.f_desde DESC, lpp.lista_precio_producto_id DESC
                                      LIMIT 1)';

    /**
     * @param array{terminos?:string[],categorias?:int[],marca_id?:int,modelo_id?:int,submodelo_id?:int} $filtros
     * @return array<int,array<string,mixed>>
     */
    public function buscar(array $filtros, int $empresaId, int $listaPrecioId, int $limit, int $offset): array
    {
        [$where, $params] = $this->condiciones($filtros, $empresaId);
        $params['lista_precio_id'] = $listaPrecioId;
        $params['lista_precio_filtro'] = $listaPrecioId;

        $sql = 'SELECT p.producto_id,
                       p.producto_codigo,
                       p.producto_nombre,
                       p.producto_descripcion,
                       p.compatibilidad_texto,
                       p.producto_categoria_id,
                       c.producto_categoria_nombre,
                       p.iva_alicuota_id,
                       COALESCE(iva.porcentaje, 0) AS iva_porcentaje,
                       ' . self::PRECIO_VIGENTE . ' AS precio_neto,
                       (SELECT pi.imagen_id
                          FROM gestion__productos_imagenes pi
                         WHERE pi.producto_id = p.producto_id
                           AND pi.empresa_id = p.empresa_id
                           AND pi.tabla_estado_registro_id = 1
                         ORDER BY pi.es_principal DESC, pi.orden ASC, pi.producto_imagen_id ASC
                         LIMIT 1) AS imagen_id
                  FROM gestion__productos p
             LEFT JOIN gestion__productos_categorias c
                    ON c.producto_categoria_id = p.producto_categoria_id
             LEFT JOIN gestion__impuestos__iva_alicuotas iva
                    ON iva.iva_alicuota_id = p.iva_alicuota_id
                   AND iva.empresa_id = p.empresa_id
                 WHERE ' . $where . '
                   AND ' . $this->existePrecio() . '
              ORDER BY p.producto_nombre ASC, p.producto_id ASC
                 LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        // LIMIT/OFFSET requieren binding entero explícito con prepares reales.
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @param array{terminos?:string[],categorias?:int[],marca_id?:int,modelo_id?:int,submodelo_id?:int} $filtros */
    public function contar(array $filtros, int $empresaId, int $listaPrecioId): int
    {
        [$where, $params] = $this->condiciones($filtros, $empresaId);
        $params['lista_precio_filtro'] = $listaPrecioId;

        return (int) $this->scalar(
            'SELECT COUNT(*)
               FROM gestion__productos p
              WHERE ' . $where . '
                AND ' . $this->existePrecio(),
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarPorId(int $productoId, int $empresaId, int $listaPrecioId): ?array
    {
        return $this->first(
            'SELECT p.producto_id,
                    p.producto_codigo,
                    p.producto_nombre,
                    p.producto_descripcion,
                    p.compatibilidad_texto,
                    p.producto_categoria_id,
                    c.producto_categoria_nombre,
                    p.material, p.color, p.dimensiones, p.garantia, p.peso,
                    p.iva_alicuota_id,
                    COALESCE(iva.porcentaje, 0) AS iva_porcentaje,
                    ' . self::PRECIO_VIGENTE . ' AS precio_neto,
                    (SELECT pi.imagen_id
                       FROM gestion__productos_imagenes pi
                      WHERE pi.producto_id = p.producto_id
                        AND pi.empresa_id = p.empresa_id
                        AND pi.tabla_estado_registro_id = 1
                      ORDER BY pi.es_principal DESC, pi.orden ASC, pi.producto_imagen_id ASC
                      LIMIT 1) AS imagen_id
               FROM gestion__productos p
          LEFT JOIN gestion__productos_categorias c
                 ON c.producto_categoria_id = p.producto_categoria_id
          LEFT JOIN gestion__impuestos__iva_alicuotas iva
                 ON iva.iva_alicuota_id = p.iva_alicuota_id
                AND iva.empresa_id = p.empresa_id
              WHERE p.producto_id = :producto_id
                AND p.empresa_id = :empresa_id
                AND p.tabla_estado_registro_id = 1
              LIMIT 1',
            [
                'producto_id'     => $productoId,
                'empresa_id'      => $empresaId,
                'lista_precio_id' => $listaPrecioId,
            ]
        );
    }

    /**
     * Datos fiscales + precio vigente de varios productos, en UNA consulta
     * (evita N+1 al valorizar el carrito).
     *
     * @param int[] $productoIds
     * @return array<int,array<string,mixed>> indexado por producto_id
     */
    public function datosParaValorizar(array $productoIds, int $empresaId, int $listaPrecioId): array
    {
        if ($productoIds === []) {
            return [];
        }

        [$in, $inParams] = $this->inPlaceholders($productoIds, 'pid');

        $rows = $this->all(
            'SELECT p.producto_id,
                    p.producto_codigo,
                    p.producto_nombre,
                    p.iva_alicuota_id,
                    COALESCE(iva.porcentaje, 0) AS iva_porcentaje,
                    ' . self::PRECIO_VIGENTE . ' AS precio_neto
               FROM gestion__productos p
          LEFT JOIN gestion__impuestos__iva_alicuotas iva
                 ON iva.iva_alicuota_id = p.iva_alicuota_id
                AND iva.empresa_id = p.empresa_id
              WHERE p.producto_id IN (' . $in . ')
                AND p.empresa_id = :empresa_id
                AND p.tabla_estado_registro_id = 1',
            $inParams + ['empresa_id' => $empresaId, 'lista_precio_id' => $listaPrecioId]
        );

        $indexado = [];
        foreach ($rows as $row) {
            $indexado[(int) $row['producto_id']] = $row;
        }

        return $indexado;
    }

    /** @return array<int,array<string,mixed>> categorías con productos publicables */
    public function categorias(int $empresaId): array
    {
        return $this->all(
            'SELECT c.producto_categoria_id, c.producto_categoria_nombre, COUNT(p.producto_id) AS total
               FROM gestion__productos_categorias c
         INNER JOIN gestion__productos p
                 ON p.producto_categoria_id = c.producto_categoria_id
                AND p.empresa_id = c.empresa_id
                AND p.tabla_estado_registro_id = 1
              WHERE c.empresa_id = :empresa_id
                AND c.tabla_estado_registro_id = 1
           GROUP BY c.producto_categoria_id, c.producto_categoria_nombre
             HAVING total > 0
           ORDER BY c.producto_categoria_nombre ASC',
            ['empresa_id' => $empresaId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function marcas(int $empresaId): array
    {
        return $this->all(
            'SELECT marca_id, marca_nombre
               FROM gestion__marcas
              WHERE empresa_id = :empresa_id
                AND tabla_estado_registro_id = 1
           ORDER BY marca_nombre ASC',
            ['empresa_id' => $empresaId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function modelos(int $empresaId, int $marcaId): array
    {
        return $this->all(
            'SELECT modelo_id, modelo_nombre
               FROM gestion__modelos
              WHERE empresa_id = :empresa_id
                AND marca_id = :marca_id
                AND tabla_estado_registro_id = 1
           ORDER BY modelo_nombre ASC',
            ['empresa_id' => $empresaId, 'marca_id' => $marcaId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function submodelos(int $empresaId, int $modeloId): array
    {
        return $this->all(
            'SELECT submodelo_id, submodelo_nombre
               FROM gestion__submodelos
              WHERE empresa_id = :empresa_id
                AND modelo_id = :modelo_id
                AND tabla_estado_registro_id = 1
           ORDER BY submodelo_nombre ASC',
            ['empresa_id' => $empresaId, 'modelo_id' => $modeloId]
        );
    }

    private function existePrecio(): string
    {
        return 'EXISTS (SELECT 1
                          FROM gestion__listas_precios_productos lpf
                         WHERE lpf.producto_id = p.producto_id
                           AND lpf.empresa_id = p.empresa_id
                           AND lpf.lista_precio_id = :lista_precio_filtro
                           AND lpf.tabla_estado_registro_id = 1
                           AND lpf.precio_final > 0
                           AND lpf.f_desde <= CURDATE()
                           AND (lpf.f_hasta IS NULL OR lpf.f_hasta >= CURDATE()))';
    }

    /**
     * @param array{terminos?:string[],categorias?:int[],marca_id?:int,modelo_id?:int,submodelo_id?:int} $filtros
     * @return array{0:string,1:array<string,mixed>}
     */
    private function condiciones(array $filtros, int $empresaId): array
    {
        $where = [
            'p.empresa_id = :empresa_id',
            'p.tabla_estado_registro_id = 1',
        ];
        $params = ['empresa_id' => $empresaId];

        // Cada término acota más el resultado: se combinan con AND.
        $terminos = array_slice(array_values($filtros['terminos'] ?? []), 0, 6);
        foreach ($terminos as $i => $termino) {
            $termino = trim((string) $termino);
            if ($termino !== '') {
                // Un placeholder por campo: los prepares reales no admiten
                // reutilizar el mismo nombre en varias posiciones.
                $nombre = "q_nom_$i";
                $codigo = "q_cod_$i";
                $descripcion = "q_desc_$i";
                $compat = "q_comp_$i";

                $where[] = "(p.producto_nombre LIKE :$nombre
                             OR p.producto_codigo LIKE :$codigo
                             OR p.producto_descripcion LIKE :$descripcion
                             OR p.compatibilidad_busqueda LIKE :$compat)";

                $like = '%' . $this->escaparLike($termino) . '%';
                $params[$nombre] = $like;
                $params[$codigo] = $like;
                $params[$descripcion] = $like;
                $params[$compat] = $like;
            }
        }

        $categorias = array_values(array_filter(
            array_map('intval', $filtros['categorias'] ?? []),
            static fn (int $id): bool => $id > 0
        ));
        if ($categorias !== []) {
            [$in, $inParams] = $this->inPlaceholders($categorias, 'cat');
            $where[] = "p.producto_categoria_id IN ($in)";
            $params += $inParams;
        }

        $marcaId = (int) ($filtros['marca_id'] ?? 0);
        if ($marcaId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pcm
                                 WHERE pcm.producto_id = p.producto_id
                                   AND pcm.marca_id = :marca_id
                                   AND pcm.tabla_estado_registro_id = 1)';
            $params['marca_id'] = $marcaId;
        }

        $modeloId = (int) ($filtros['modelo_id'] ?? 0);
        if ($modeloId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pcmo
                                 WHERE pcmo.producto_id = p.producto_id
                                   AND pcmo.modelo_id = :modelo_id
                                   AND pcmo.tabla_estado_registro_id = 1)';
            $params['modelo_id'] = $modeloId;
        }

        $submodeloId = (int) ($filtros['submodelo_id'] ?? 0);
        if ($submodeloId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pcsm
                                 WHERE pcsm.producto_id = p.producto_id
                                   AND pcsm.submodelo_id = :submodelo_id
                                   AND pcsm.tabla_estado_registro_id = 1)';
            $params['submodelo_id'] = $submodeloId;
        }

        return [implode(' AND ', $where), $params];
    }

    /** Neutraliza los comodines de LIKE dentro del término buscado. */
    private function escaparLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($value, 0, 50));
    }
}
