<?php
declare(strict_types=1);

/**
 * Modelo de datos: Accesos por perfil (gestion__perfiles_sucursales)
 * Jerarquía: Empresa -> Sucursal -> Boca -> Punto de Venta -> Tipo Comprobante
 */
class AccesosPerfilesModel
{
    private mysqli $conexion;

    public function __construct(mysqli $conexion)
    {
        $this->conexion = $conexion;
    }

    /** Perfiles (empresa_perfil) activos de la empresa actual en módulo Gestión */
    public function listarPerfiles(int $empresaId): array
    {
        $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, p.perfil_nombre
                FROM conf__empresas_perfiles ep
                LEFT JOIN conf__perfiles p ON p.perfil_id = ep.perfil_id_base
                WHERE ep.empresa_id = ? 
                  AND ep.modulo_id = 5 
                  AND ep.tabla_estado_registro_id = 1
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

    public function listarBocas(int $sucursalId, int $empresaId): array
    {
        $sql = "SELECT boca_id, boca_nombre, codigo, es_deposito
                FROM gestion__bocas
                WHERE sucursal_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY es_principal DESC, orden ASC, boca_nombre ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ii', $sucursalId, $empresaId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function bocaPerteneceASucursal(int $bocaId, int $sucursalId, int $empresaId): bool
    {
        $sql = "SELECT 1 FROM gestion__bocas
                WHERE boca_id = ? AND sucursal_id = ? AND empresa_id = ? LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('iii', $bocaId, $sucursalId, $empresaId);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function listarPuntosVenta(int $sucursalId, ?int $bocaId = null): array
    {
        if ($bocaId !== null && $bocaId > 0) {
            $sql = "SELECT punto_venta_id, nombre, codigo_fiscal
                    FROM gestion__puntos_venta
                    WHERE sucursal_id = ? AND boca_id = ? AND tabla_estado_registro_id = 1
                    ORDER BY nombre";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param('ii', $sucursalId, $bocaId);
        } else {
            $sql = "SELECT punto_venta_id, nombre, codigo_fiscal
                    FROM gestion__puntos_venta
                    WHERE sucursal_id = ? AND tabla_estado_registro_id = 1
                    ORDER BY nombre";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param('i', $sucursalId);
        }
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

    public function listarTiposComprobante(int $puntoVentaId): array
    {
        $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.codigo, ct.letra
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

    public function listarGrants(?int $empresaPerfilId, ?int $empresaId = null): array
    {
        $params = [];
        $types = "";

        $sql = "SELECT
                    ps.perfil_sucursal_id,
                    ps.empresa_perfil_id,
                    ep.empresa_perfil_nombre,
                    ps.sucursal_id,
                    ps.boca_id,
                    ps.punto_venta_id,
                    ps.comprobante_tipo_id,
                    ps.es_predeterminado,
                    COALESCE(s.sucursal_nombre, 'TODAS (Acceso Total)') AS sucursal_nombre,
                    b.boca_nombre,
                    pv.nombre AS punto_venta_nombre,
                    ct.comprobante_tipo,
                    ct.codigo AS comprobante_codigo
                FROM gestion__perfiles_sucursales ps
                INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = ps.empresa_perfil_id
                LEFT JOIN gestion__sucursales s ON s.sucursal_id = ps.sucursal_id
                LEFT JOIN gestion__bocas b ON b.boca_id = ps.boca_id
                LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = ps.punto_venta_id
                LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = ps.comprobante_tipo_id
                WHERE ps.tabla_estado_registro_id = 1 ";

        if ($empresaPerfilId !== null && $empresaPerfilId > 0) {
            $sql .= " AND ps.empresa_perfil_id = ? ";
            $params[] = $empresaPerfilId;
            $types .= "i";
        } elseif ($empresaId !== null && $empresaId > 0) {
            $sql .= " AND ps.empresa_id = ? ";
            $params[] = $empresaId;
            $types .= "i";
        }

        $sql .= " ORDER BY ep.empresa_perfil_nombre, s.sucursal_nombre, b.boca_nombre, pv.nombre, ct.orden";

        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) return [];

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function agregarGrant(
        int $empresaPerfilId,
        ?int $sucursalId,
        ?int $bocaId,
        ?int $puntoVentaId,
        ?int $comprobanteTipoId,
        int $esPredeterminado,
        int $empresaId,
        int $usuarioId
    ): int {
        if (!$this->perfilPerteneceAEmpresa($empresaPerfilId, $empresaId)) {
            throw new InvalidArgumentException('El perfil no pertenece a la empresa actual');
        }

        if ($sucursalId !== null && $sucursalId > 0) {
            if (!$this->sucursalPerteneceAEmpresa($sucursalId, $empresaId)) {
                throw new InvalidArgumentException('La sucursal no pertenece a la empresa actual');
            }

            if ($bocaId !== null && $bocaId > 0) {
                if (!$this->bocaPerteneceASucursal($bocaId, $sucursalId, $empresaId)) {
                    throw new InvalidArgumentException('La boca no pertenece a la sucursal seleccionada');
                }
            } else {
                $bocaId = null;
            }

            if ($puntoVentaId !== null && $puntoVentaId > 0) {
                if (!$this->puntoVentaPerteneceASucursal($puntoVentaId, $sucursalId)) {
                    throw new InvalidArgumentException('El punto de venta no pertenece a la sucursal seleccionada');
                }
            } else {
                $puntoVentaId = null;
            }

            if ($comprobanteTipoId !== null && $comprobanteTipoId > 0) {
                if ($puntoVentaId === null) {
                    throw new InvalidArgumentException('Para restringir por comprobante primero debe seleccionar un punto de venta puntual');
                }
                if (!$this->tipoHabilitadoEnPuntoVenta($comprobanteTipoId, $puntoVentaId)) {
                    throw new InvalidArgumentException('El tipo de comprobante no está habilitado en ese punto de venta');
                }
            } else {
                $comprobanteTipoId = null;
            }
        } else {
            $sucursalId = null;
            $bocaId = null;
            $puntoVentaId = null;
            $comprobanteTipoId = null;
        }

        $sql = "INSERT INTO gestion__perfiles_sucursales
                    (empresa_id, empresa_perfil_id, sucursal_id, boca_id, punto_venta_id, comprobante_tipo_id, es_predeterminado, usuario_creacion_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param(
            'iiiiiiii',
            $empresaId,
            $empresaPerfilId,
            $sucursalId,
            $bocaId,
            $puntoVentaId,
            $comprobanteTipoId,
            $esPredeterminado,
            $usuarioId
        );

        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $stmt->close();
            if ($errno === 1062) {
                throw new InvalidArgumentException('Ya existe una regla idéntica asignada a este perfil');
            }
            throw new RuntimeException('Error al guardar el acceso: ' . $this->conexion->error);
        }

        $id = $this->conexion->insert_id;
        $stmt->close();
        return $id;
    }

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
