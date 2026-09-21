<?php

declare(strict_types=1);

/**
 * Modelo de datos: Accesos por perfil (gestion__perfiles_sucursales)
 *
 * Reglas de negocio que este modelo garantiza porque MySQL/MariaDB no
 * las puede expresar en un CHECK constraint:
 *   - punto_venta_id (si no es NULL) debe pertenecer a sucursal_id
 *   - comprobante_tipo_id (si no es NULL) requiere punto_venta_id
 *     puntual, y debe estar habilitado ahí (gestion__puntos_venta_comprobantes)
 *   - toda operación queda acotada a la empresa de la sesión (aislamiento
 *     multi-tenant): nunca confiar en un ID recibido del cliente sin
 *     verificar que pertenece a la empresa actual
 */
class AccesosPerfilesModel
{
    private mysqli $conexion;

    public function __construct(mysqli $conexion)
    {
        $this->conexion = $conexion;
    }

    /** Perfiles (empresa_perfil) activos de la empresa actual */
    public function listarPerfiles(int $empresaId): array
    {
        $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, p.perfil_nombre
                FROM conf__empresas_perfiles ep
                LEFT JOIN conf__perfiles p ON p.perfil_id = ep.perfil_id_base
                WHERE ep.empresa_id = ? AND ep.tabla_estado_registro_id = 1
                ORDER BY ep.empresa_perfil_nombre";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $empresaId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function perfilPerteneceAEmpresa(int $empresaPerfilId, int $empresaId): bool
    {
        $sql = "SELECT 1 FROM conf__empresas_perfiles
                WHERE empresa_perfil_id = ? AND empresa_id = ? LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ii', $empresaPerfilId, $empresaId);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function listarSucursales(int $empresaId): array
    {
        $sql = "SELECT sucursal_id, sucursal_nombre
                FROM gestion__sucursales
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY sucursal_nombre";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $empresaId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function sucursalPerteneceAEmpresa(int $sucursalId, int $empresaId): bool
    {
        $sql = "SELECT 1 FROM gestion__sucursales
                WHERE sucursal_id = ? AND empresa_id = ? LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ii', $sucursalId, $empresaId);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function listarPuntosVenta(int $sucursalId): array
    {
        $sql = "SELECT punto_venta_id, nombre
                FROM gestion__puntos_venta
                WHERE sucursal_id = ? AND tabla_estado_registro_id = 1
                ORDER BY nombre";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $sucursalId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function puntoVentaPerteneceASucursal(int $puntoVentaId, int $sucursalId): bool
    {
        $sql = "SELECT 1 FROM gestion__puntos_venta
                WHERE punto_venta_id = ? AND sucursal_id = ? LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ii', $puntoVentaId, $sucursalId);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    /** Tipos de comprobante habilitados para un punto de venta puntual */
    public function listarTiposComprobante(int $puntoVentaId): array
    {
        $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.codigo
                FROM gestion__puntos_venta_comprobantes pvc
                INNER JOIN gestion__comprobantes_tipos ct
                    ON ct.comprobante_tipo_id = pvc.comprobante_tipo_id
                WHERE pvc.punto_venta_id = ?
                    AND pvc.tabla_estado_registro_id = 1
                    AND ct.tabla_estado_registro_id = 1
                ORDER BY ct.orden";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $puntoVentaId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function tipoHabilitadoEnPuntoVenta(int $comprobanteTipoId, int $puntoVentaId): bool
    {
        $sql = "SELECT 1 FROM gestion__puntos_venta_comprobantes
                WHERE comprobante_tipo_id = ? AND punto_venta_id = ?
                    AND tabla_estado_registro_id = 1 LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ii', $comprobanteTipoId, $puntoVentaId);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function listarGrants(int $empresaPerfilId): array
    {
        $sql = "SELECT
                    ps.perfil_sucursal_id,
                    s.sucursal_nombre,
                    pv.nombre AS punto_venta_nombre,
                    ct.comprobante_tipo,
                    ct.codigo AS comprobante_codigo
                FROM gestion__perfiles_sucursales ps
                INNER JOIN gestion__sucursales s ON s.sucursal_id = ps.sucursal_id
                LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = ps.punto_venta_id
                LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = ps.comprobante_tipo_id
                WHERE ps.empresa_perfil_id = ?
                ORDER BY s.sucursal_nombre, pv.nombre, ct.orden";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $empresaPerfilId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * @throws InvalidArgumentException si la combinación es incoherente o no
     *         pertenece a la empresa actual (nunca confiar en IDs del cliente)
     */
    public function agregarGrant(
        int $empresaPerfilId,
        int $sucursalId,
        ?int $puntoVentaId,
        ?int $comprobanteTipoId,
        int $empresaId
    ): int {
        if (!$this->perfilPerteneceAEmpresa($empresaPerfilId, $empresaId)) {
            throw new InvalidArgumentException('El perfil no pertenece a la empresa actual');
        }
        if (!$this->sucursalPerteneceAEmpresa($sucursalId, $empresaId)) {
            throw new InvalidArgumentException('La sucursal no pertenece a la empresa actual');
        }

        if ($puntoVentaId !== null) {
            if (!$this->puntoVentaPerteneceASucursal($puntoVentaId, $sucursalId)) {
                throw new InvalidArgumentException('El punto de venta no pertenece a la sucursal seleccionada');
            }
        } elseif ($comprobanteTipoId !== null) {
            // Regla de diseño (revisar con Pablo): restringir por tipo de comprobante sin
            // fijar un punto de venta puntual no tiene un conjunto único de tipos habilitados
            // contra el cual validar ("todos los PV" puede tener listas distintas de tipos
            // habilitados entre sí). Se bloquea en vez de resolverlo con una suposición.
            throw new InvalidArgumentException(
                'Para restringir por tipo de comprobante primero hay que elegir un punto de venta específico'
            );
        }

        if ($comprobanteTipoId !== null && $puntoVentaId !== null
            && !$this->tipoHabilitadoEnPuntoVenta($comprobanteTipoId, $puntoVentaId)) {
            throw new InvalidArgumentException('El tipo de comprobante no está habilitado en ese punto de venta');
        }

        $sql = "INSERT INTO gestion__perfiles_sucursales
                    (empresa_perfil_id, sucursal_id, punto_venta_id, comprobante_tipo_id)
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        // 4 placeholders, 4 tipos 'iiii' — verificado explícitamente (vector de bug
        // documentado del sistema: desalineación tipo/cantidad en bind_param)
        $stmt->bind_param('iiii', $empresaPerfilId, $sucursalId, $puntoVentaId, $comprobanteTipoId);

        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $stmt->close();
            if ($errno === 1062) { // duplicate entry sobre uk_grant
                throw new InvalidArgumentException('Ya existe un acceso idéntico cargado para este perfil');
            }
            throw new RuntimeException('Error al guardar el acceso');
        }

        $id = $this->conexion->insert_id;
        $stmt->close();
        return $id;
    }

    /** Filtra también por empresa_perfil_id: defensa en profundidad además del chequeo en AJAX */
    public function eliminarGrant(int $perfilSucursalId, int $empresaPerfilId): bool
    {
        $sql = "DELETE FROM gestion__perfiles_sucursales
                WHERE perfil_sucursal_id = ? AND empresa_perfil_id = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ii', $perfilSucursalId, $empresaPerfilId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
