<?php
/**
 * modules/sindicatos/planes_model.php
 * Modelo para la pantalla de Planes de Pago y Refinanciación (Página 1042).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_planes_core_model.php';

class PlanesModel {
    private mysqli $c;

    public function __construct(mysqli $conn) {
        $this->c = $conn;
    }

    /**
     * Lista planes de pago registrados con filtros.
     */
    public function listar(int $empresa_id, array $filtros = [], int $usuario_id = 0): array {
        $where = "WHERE p.empresa_id = ?";
        // Solo los empleadores a los que el usuario tiene acceso (interno: todos)
        if ($usuario_id > 0) {
            $where .= " AND " . AccesosEmpleadoresHelper::filtroSql($this->c, $usuario_id, $empresa_id, 'p.empleador_id');
        }
        $params = [$empresa_id];
        $types = "i";

        if (!empty($filtros['empleador_id'])) {
            $where .= " AND p.empleador_id = ?";
            $params[] = (int)$filtros['empleador_id'];
            $types .= "i";
        }

        if (!empty($filtros['estado_id'])) {
            $where .= " AND p.tabla_estado_registro_id = ?";
            $params[] = (int)$filtros['estado_id'];
            $types .= "i";
        }

        if (!empty($filtros['busqueda'])) {
            $like = '%' . trim($filtros['busqueda']) . '%';
            $where .= " AND (p.numero_plan LIKE ? OR ent.entidad_nombre LIKE ? OR ent.cuit LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= "sss";
        }

        $sql = "SELECT p.*, ent.entidad_nombre AS razon_social, ent.cuit, pt.nombre AS tipo_plan_nombre,
                       er.tabla_estado_registro AS estado_nombre,
                       (SELECT COUNT(*) FROM sindi__planes_cuotas pc WHERE pc.plan_id = p.plan_id) AS cuotas_totales,
                       (SELECT COUNT(*) FROM sindi__planes_cuotas pc WHERE pc.plan_id = p.plan_id AND pc.tabla_estado_registro_id = 40) AS cuotas_pagadas
                FROM sindi__planes p
                JOIN sindi__empleadores e ON e.empleador_id = p.empleador_id
                JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
                JOIN sindi__planes_tipos pt ON pt.plan_tipo_id = p.plan_tipo_id
                LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 538 AND er.estado_registro_id = p.tabla_estado_registro_id
                $where
                ORDER BY p.plan_id DESC
                LIMIT 100";

        $stmt = $this->c->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Obtiene el detalle completo de un plan y sus cuotas.
     */
    public function obtener(int $plan_id): ?array {
        return sindiPlanObtener($this->c, $plan_id);
    }

    /**
     * Simula un plan de pagos.
     */
    public function simular(int $empresa_id, int $plan_tipo_id, float $capital, int $cuotas, ?float $anticipo_pct = null, ?string $f_primer_vto = null): array {
        return sindiPlanSimular($this->c, $empresa_id, $plan_tipo_id, $capital, $cuotas, $anticipo_pct, $f_primer_vto);
    }

    /**
     * Crea y confirma un plan de pagos refinanciando comprobantes.
     */
    public function crearYConfirmar(int $empresa_id, array $datos, array $comprobantes_origen_ids, int $usuario_id): array {
        return sindiPlanCrearYConfirmar($this->c, $empresa_id, $datos, $comprobantes_origen_ids, $usuario_id);
    }

    /**
     * Anula un plan de pagos.
     */
    public function anular(int $plan_id, int $usuario_id, string $motivo = ''): bool {
        return sindiPlanAnular($this->c, $plan_id, $usuario_id, $motivo);
    }

    /**
     * Obtiene tipos de planes activos para la empresa.
     */
    public function listarTiposPlanes(int $empresa_id): array {
        $sql = "SELECT * FROM sindi__planes_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY nombre ASC";
        $stmt = $this->c->prepare($sql);
        $stmt->bind_param("i", $empresa_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Obtiene deudas exigibles pendientes de un empleador (comprobantes de DDJJ o Actas con saldo > 0).
     */
    public function listarDeudasPendientes(int $empresa_id, int $empleador_id): array {
        $sql = "SELECT c.comprobante_id, c.numero, ct.codigo AS tipo_codigo, ct.comprobante_tipo,
                       c.f_emision, c.f_vto, c.importe_total, c.importe_pendiente,
                       c.tabla_origen_id, c.registro_origen_id
                FROM gestion__comprobantes c
                JOIN sindi__empleadores e ON e.entidad_id = c.entidad_id
                JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                WHERE c.empresa_id = ? AND e.empleador_id = ?
                  AND c.importe_pendiente > 0 
                  AND c.tabla_estado_registro_id = 5
                  AND ct.codigo IN ('DFS', 'DCS', 'NDI', 'ACT')
                ORDER BY c.f_vto ASC";
        $stmt = $this->c->prepare($sql);
        $stmt->bind_param("ii", $empresa_id, $empleador_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Lista empleadores para selector.
     */
    public function listarEmpleadores(int $empresa_id): array {
        $sql = "SELECT e.empleador_id, ent.entidad_nombre AS razon_social, ent.cuit 
                FROM sindi__empleadores e
                JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
                WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1
                ORDER BY ent.entidad_nombre ASC";
        $stmt = $this->c->prepare($sql);
        $stmt->bind_param("i", $empresa_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    }
}
