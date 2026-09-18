<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;

/**
 * Lecturas de configuración desde la base compartida con el sistema de
 * administración (módulos, perfiles, empresas). Permiten que el storefront
 * descubra sus IDs solo, en vez de exigir que se carguen a mano en el .env.
 */
final class ParametrosRepository extends Repository
{
    public function moduloIdPorNombre(string $nombre): ?int
    {
        return $this->idOnull(
            'SELECT modulo_id FROM conf__modulos
              WHERE modulo = :nombre AND tabla_estado_registro_id = 1
              ORDER BY modulo_id ASC LIMIT 1',
            ['nombre' => $nombre]
        );
    }

    /**
     * Señal principal: la empresa que tiene el módulo habilitado en
     * conf__empresas_modulos. Es la relación que usa el sistema de
     * administración para decidir qué módulos ve cada empresa, así que es la
     * fuente de verdad natural. Si hay más de una, no se adivina.
     */
    public function empresaPorModuloHabilitado(int $moduloId): ?int
    {
        $filas = $this->all(
            'SELECT DISTINCT empresa_id
               FROM conf__empresas_modulos
              WHERE modulo_id = :modulo_id
                AND tabla_estado_registro_id = 1
              LIMIT 2',
            ['modulo_id' => $moduloId]
        );

        return count($filas) === 1 ? (int) $filas[0]['empresa_id'] : null;
    }

    /**
     * Empresa del storefront, por evidencia de uso: la única con usuarios
     * vigentes asignados a un perfil del módulo. Es la señal más fuerte,
     * porque la migración crea el perfil "Cliente Web" en todas las empresas.
     */
    public function empresaPorUsuariosDelModulo(int $moduloId): ?int
    {
        $filas = $this->all(
            'SELECT DISTINCT ep.empresa_id
               FROM conf__usuarios_perfiles up
         INNER JOIN conf__empresas_perfiles ep
                 ON ep.empresa_perfil_id = up.empresa_perfil_id
                AND ep.modulo_id = :modulo_id
                AND ep.tabla_estado_registro_id = 1
              WHERE up.tabla_estado_registro_id = 1
                AND up.fecha_inicio <= CURDATE()
                AND up.fecha_fin >= CURDATE()
              LIMIT 2',
            ['modulo_id' => $moduloId]
        );

        return count($filas) === 1 ? (int) $filas[0]['empresa_id'] : null;
    }

    /**
     * Fallback: la única empresa con un perfil activo del módulo. Si hay más de
     * una, devuelve null y obliga a definirla en el .env: adivinar la empresa
     * sería exponer precios y pedidos cruzados entre compañías.
     */
    public function empresaUnicaDelModulo(int $moduloId): ?int
    {
        $filas = $this->all(
            'SELECT DISTINCT empresa_id
               FROM conf__empresas_perfiles
              WHERE modulo_id = :modulo_id
                AND tabla_estado_registro_id = 1
              LIMIT 2',
            ['modulo_id' => $moduloId]
        );

        return count($filas) === 1 ? (int) $filas[0]['empresa_id'] : null;
    }

    /** Primera sucursal activa de la empresa que tenga punto de venta. */
    public function sucursalConPuntoVenta(int $empresaId): ?int
    {
        return $this->idOnull(
            'SELECT s.sucursal_id
               FROM gestion__sucursales s
         INNER JOIN gestion__puntos_venta pv
                 ON pv.sucursal_id = s.sucursal_id
                AND pv.empresa_id = s.empresa_id
                AND pv.tabla_estado_registro_id = 1
              WHERE s.empresa_id = :empresa_id
                AND s.tabla_estado_registro_id = 1
           ORDER BY s.sucursal_id ASC
              LIMIT 1',
            ['empresa_id' => $empresaId]
        );
    }

    public function monedaBase(int $empresaId): ?int
    {
        return $this->idOnull(
            'SELECT moneda_id FROM gestion__monedas
              WHERE empresa_id = :empresa_id
                AND tabla_estado_registro_id = 1
           ORDER BY es_moneda_base DESC, moneda_id ASC
              LIMIT 1',
            ['empresa_id' => $empresaId]
        );
    }

    /** Tipo de comprobante "Pedido de Cliente" (código PC en el ERP). */
    public function comprobanteTipoPedido(int $empresaId): ?int
    {
        return $this->idOnull(
            "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos
              WHERE empresa_id = :empresa_id
                AND tabla_estado_registro_id = 1
                AND (codigo = 'PC' OR comprobante_tipo LIKE 'Pedido de Cliente%')
           ORDER BY comprobante_tipo_id ASC
              LIMIT 1",
            ['empresa_id' => $empresaId]
        );
    }

    /** ID de gestion__ventas_pedidos en conf__tablas (origen del comprobante). */
    public function tablaOrigenPedidos(): ?int
    {
        return $this->idOnull(
            "SELECT tabla_id FROM conf__tablas
              WHERE tabla_nombre = 'gestion__ventas_pedidos'
           ORDER BY tabla_id ASC LIMIT 1"
        );
    }

    /** Lista de precios por defecto: la única activa, o null si hay varias. */
    public function listaPrecioUnica(int $empresaId): ?int
    {
        $filas = $this->all(
            'SELECT lista_precio_id FROM gestion__listas_precios
              WHERE empresa_id = :empresa_id
                AND tabla_estado_registro_id = 1
              LIMIT 2',
            ['empresa_id' => $empresaId]
        );

        return count($filas) === 1 ? (int) $filas[0]['lista_precio_id'] : null;
    }

    /** @param array<string,mixed> $params */
    private function idOnull(string $sql, array $params = []): ?int
    {
        $valor = $this->scalar($sql, $params);
        return $valor === null ? null : (int) $valor;
    }
}
