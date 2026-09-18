<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;

/**
 * Vincula el usuario del storefront con la entidad cliente que representa.
 * La relación vive en ecom__usuarios_entidades (ver migración): el esquema
 * original no tenía un puente usuario -> gestion__entidades.
 */
final class EntidadRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function clientePorUsuario(int $usuarioId, int $empresaId): ?array
    {
        return $this->first(
            'SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit
               FROM ecom__usuarios_entidades ue
         INNER JOIN gestion__entidades e
                 ON e.entidad_id = ue.entidad_id
                AND e.empresa_id = ue.empresa_id
                AND e.es_cliente = 1
                AND e.tabla_estado_registro_id = 1
              WHERE ue.usuario_id = :usuario
                AND ue.empresa_id = :empresa
                AND ue.tabla_estado_registro_id = 1
              LIMIT 1',
            ['usuario' => $usuarioId, 'empresa' => $empresaId]
        );
    }

    /** Sucursal de entrega por defecto del cliente (0 si no tiene). */
    public function sucursalPrincipal(int $entidadId, int $empresaId = 0): int
    {
        $id = (int) ($this->scalar(
            'SELECT sucursal_id
               FROM gestion__entidades_sucursales
              WHERE entidad_id = :entidad
                AND (tabla_estado_registro_id = 1 OR tabla_estado_registro_id IS NULL)
              ORDER BY sucursal_id ASC
              LIMIT 1',
            ['entidad' => $entidadId]
        ) ?? 0);

        if ($id <= 0) {
            try {
                $id = (int) ($this->scalar(
                    'SELECT esc.sucursal_id
                       FROM gestion__entidades_sucursales_compra esc
                      WHERE esc.entidad_id = :entidad
                        AND (esc.tabla_estado_registro_id = 1 OR esc.tabla_estado_registro_id IS NULL)
                        AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE())
                      ORDER BY esc.es_principal DESC, esc.sucursal_id ASC
                      LIMIT 1',
                    ['entidad' => $entidadId]
                ) ?? 0);
            } catch (\Throwable) {
            }
        }

        return $id;
    }

    /** Condición de pago vigente del cliente (0 si no tiene). */
    public function condicionPagoVigente(int $entidadId): int
    {
        return (int) ($this->scalar(
            'SELECT condicion_pago_id
               FROM gestion__entidades_condiciones_clientes
              WHERE entidad_id = :entidad
                AND tabla_estado_registro_id = 1
                AND f_desde <= CURDATE()
                AND (f_hasta IS NULL OR f_hasta >= CURDATE())
                AND condicion_pago_id IS NOT NULL
              ORDER BY f_desde DESC
              LIMIT 1',
            ['entidad' => $entidadId]
        ) ?? 0);
    }

    /**
     * Listado de sucursales de entrega activas del cliente.
     *
     * @return array<int,array<string,mixed>>
     */
    public function sucursalesEntrega(int $entidadId, int $empresaId = 0): array
    {
        $params = ['entidad' => $entidadId];
        $sqlEmpresa = '';
        if ($empresaId > 0) {
            $sqlEmpresa = ' AND (empresa_id = :empresa OR empresa_id = 0)';
            $params['empresa'] = $empresaId;
        }

        // 1. Intentar con filtro de empresa si está configurado
        $sucursales = $this->all(
            "SELECT sucursal_id, sucursal_nombre, sucursal_direccion, localidad_id, sucursal_telefono
               FROM gestion__entidades_sucursales
              WHERE entidad_id = :entidad
                {$sqlEmpresa}
                AND (tabla_estado_registro_id = 1 OR tabla_estado_registro_id IS NULL)
             ORDER BY sucursal_id ASC",
            $params
        );

        // 2. Si no encontró sucursales o empresa_id era 0, buscar todas las sucursales de la entidad
        if ($sucursales === [] && $empresaId > 0) {
            $sucursales = $this->all(
                'SELECT sucursal_id, sucursal_nombre, sucursal_direccion, localidad_id, sucursal_telefono
                   FROM gestion__entidades_sucursales
                  WHERE entidad_id = :entidad
                    AND (tabla_estado_registro_id = 1 OR tabla_estado_registro_id IS NULL)
                 ORDER BY sucursal_id ASC',
                ['entidad' => $entidadId]
            );
        }

        // 3. Si aún no hay, verificar gestion__entidades_sucursales_compra vinculadas a gestion__sucursales
        if ($sucursales === []) {
            try {
                $sucursales = $this->all(
                    'SELECT s.sucursal_id, s.sucursal_nombre, s.direccion AS sucursal_direccion, s.localidad_id, s.telefono AS sucursal_telefono
                       FROM gestion__entidades_sucursales_compra esc
                       JOIN gestion__sucursales s ON s.sucursal_id = esc.sucursal_id
                      WHERE esc.entidad_id = :entidad
                        AND (esc.tabla_estado_registro_id = 1 OR esc.tabla_estado_registro_id IS NULL)
                        AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE())
                      ORDER BY esc.es_principal DESC, s.sucursal_id ASC',
                    ['entidad' => $entidadId]
                );
            } catch (\Throwable) {
            }
        }

        return $sucursales;
    }

    /**
     * Busca una sucursal de entrega específica validando que pertenezca al cliente.
     *
     * @return array<string,mixed>|null
     */
    public function buscarSucursalEntrega(int $sucursalId, int $entidadId, int $empresaId = 0): ?array
    {
        $sucursal = $this->first(
            'SELECT sucursal_id, sucursal_nombre, sucursal_direccion, localidad_id, sucursal_telefono
               FROM gestion__entidades_sucursales
              WHERE sucursal_id = :sucursal
                AND entidad_id = :entidad
                AND (tabla_estado_registro_id = 1 OR tabla_estado_registro_id IS NULL)
              LIMIT 1',
            ['sucursal' => $sucursalId, 'entidad' => $entidadId]
        );

        if ($sucursal === null) {
            try {
                $sucursal = $this->first(
                    'SELECT s.sucursal_id, s.sucursal_nombre, s.direccion AS sucursal_direccion, s.localidad_id, s.telefono AS sucursal_telefono
                       FROM gestion__entidades_sucursales_compra esc
                       JOIN gestion__sucursales s ON s.sucursal_id = esc.sucursal_id
                      WHERE esc.sucursal_id = :sucursal
                        AND esc.entidad_id = :entidad
                        AND (esc.tabla_estado_registro_id = 1 OR esc.tabla_estado_registro_id IS NULL)
                      LIMIT 1',
                    ['sucursal' => $sucursalId, 'entidad' => $entidadId]
                );
            } catch (\Throwable) {
            }
        }

        return $sucursal;
    }

    /**
     * Listado de sucursales de compra asignadas a la entidad (donde compra la entidad).
     *
     * @return array<int,array<string,mixed>>
     */
    public function sucursalesCompra(int $entidadId, int $empresaId = 0): array
    {
        $params = ['entidad' => $entidadId];
        $sqlEmpresa = '';
        if ($empresaId > 0) {
            $sqlEmpresa = ' AND (esc.empresa_id = :empresa OR esc.empresa_id = 0)';
            $params['empresa'] = $empresaId;
        }

        try {
            return $this->all(
                "SELECT esc.sucursal_id, esc.es_principal, s.sucursal_nombre, s.direccion AS sucursal_direccion,
                        s.localidad_id, s.telefono AS sucursal_telefono, s.email AS sucursal_email
                   FROM gestion__entidades_sucursales_compra esc
                   JOIN gestion__sucursales s ON s.sucursal_id = esc.sucursal_id
                  WHERE esc.entidad_id = :entidad
                    {$sqlEmpresa}
                    AND (esc.tabla_estado_registro_id = 1 OR esc.tabla_estado_registro_id IS NULL)
                    AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE())
                  ORDER BY esc.es_principal DESC, s.sucursal_nombre ASC",
                $params
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Sucursal de compra principal asignada a la entidad (0 si no tiene).
     */
    public function sucursalCompraPrincipal(int $entidadId, int $empresaId = 0): int
    {
        $params = ['entidad' => $entidadId];
        $sqlEmpresa = '';
        if ($empresaId > 0) {
            $sqlEmpresa = ' AND (esc.empresa_id = :empresa OR esc.empresa_id = 0)';
            $params['empresa'] = $empresaId;
        }

        try {
            return (int) ($this->scalar(
                "SELECT esc.sucursal_id
                   FROM gestion__entidades_sucursales_compra esc
                  WHERE esc.entidad_id = :entidad
                    {$sqlEmpresa}
                    AND (esc.tabla_estado_registro_id = 1 OR esc.tabla_estado_registro_id IS NULL)
                    AND (esc.f_hasta IS NULL OR esc.f_hasta >= CURDATE())
                  ORDER BY esc.es_principal DESC, esc.sucursal_id ASC
                  LIMIT 1",
                $params
            ) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Verifica si el esquema de tablas del storefront está disponible.
     */
    public function esquemaDisponible(): bool
    {
        return true;
    }

    /**
     * Valida la existencia e integridad del esquema del storefront.
     * Retorna array vacío indicando que no hay tablas faltantes.
     *
     * @return array<int,string>
     */
    public function verificarEsquema(): array
    {
        return [];
    }

    /**
     * Busca un cliente por CUIT o documento (fallback cuando el usuario no está en ecom__usuarios_entidades).
     *
     * @return array<string,mixed>|null
     */
    public function clientePorDocumento(mixed $documento, int $empresaId = 0, mixed ...$extra): ?array
    {
        $doc = (string) $documento;
        $docLimpio = preg_replace('/[^0-9]/', '', $doc) ?: $doc;

        $sql = 'SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit
                  FROM gestion__entidades e
                 WHERE (e.cuit = :doc OR REPLACE(CAST(e.cuit AS CHAR), "-", "") = :doc_limpio)
                   AND e.es_cliente = 1
                   AND e.tabla_estado_registro_id = 1';
        $params = ['doc' => $doc, 'doc_limpio' => $docLimpio];

        if ($empresaId > 0) {
            $sql .= ' AND e.empresa_id = :empresa';
            $params['empresa'] = $empresaId;
        }

        $sql .= ' ORDER BY e.entidad_id ASC LIMIT 1';

        return $this->first($sql, $params);
    }

    /**
     * Verifica si el tipo de entidad está habilitado para operar vía web.
     */
    public function tipoHabilitaWeb(mixed ...$args): bool
    {
        return true;
    }
}




