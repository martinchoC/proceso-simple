<?php
/**
 * modules/sindicatos/actas_model.php
 * Modelo para la pantalla de Actas de Inspección (Página 1041).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_actas_core_model.php';

class ActasModel {
    private mysqli $c;

    public function __construct(mysqli $conn) {
        $this->c = $conn;
    }

    /**
     * Lista actas de inspección con filtros.
     */
    public function listar(int $empresa_id, array $filtros = [], int $usuario_id = 0): array {
        $where = "WHERE a.empresa_id = ?";
        // Solo los empleadores a los que el usuario tiene acceso (interno: todos)
        if ($usuario_id > 0) {
            $where .= " AND " . AccesosEmpleadoresHelper::filtroSql($this->c, $usuario_id, $empresa_id, 'a.empleador_id');
        }
        $params = [$empresa_id];
        $types = "i";

        if (!empty($filtros['empleador_id'])) {
            $where .= " AND a.empleador_id = ?";
            $params[] = (int)$filtros['empleador_id'];
            $types .= "i";
        }

        if (!empty($filtros['estado_id'])) {
            $where .= " AND a.tabla_estado_registro_id = ?";
            $params[] = (int)$filtros['estado_id'];
            $types .= "i";
        }

        if (!empty($filtros['busqueda'])) {
            $like = '%' . trim($filtros['busqueda']) . '%';
            $where .= " AND (a.numero_acta LIKE ? OR ent.entidad_nombre LIKE ? OR ent.cuit LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= "sss";
        }

        $sql = "SELECT a.*, ent.entidad_nombre AS razon_social, ent.cuit, u.usuario_nombre AS inspector_nombre,
                       er.tabla_estado_registro AS estado_nombre,
                       (SELECT COUNT(*) FROM sindi__actas_periodos ap WHERE ap.acta_id = a.acta_id) AS cantidad_periodos
                FROM sindi__actas a
                JOIN sindi__empleadores e ON e.empleador_id = a.empleador_id
                JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
                LEFT JOIN conf__usuarios u ON u.usuario_id = a.inspector_usuario_id
                LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 534 AND er.estado_registro_id = a.tabla_estado_registro_id
                $where
                ORDER BY a.acta_id DESC
                LIMIT 100";

        $stmt = $this->c->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Obtiene el detalle completo de un acta.
     */
    public function obtener(int $acta_id): ?array {
        return sindiActaObtener($this->c, $acta_id);
    }

    /**
     * Crea un acta nueva.
     */
    public function crear(int $empresa_id, array $datos, int $usuario_id): int {
        return sindiActaCrear($this->c, $empresa_id, $datos, $usuario_id);
    }

    /**
     * Agrega un período e ítems a un acta.
     */
    public function agregarPeriodo(int $acta_id, int $periodo, array $items = [], ?string $f_calculo = null): int {
        return sindiActaAgregarPeriodo($this->c, $acta_id, $periodo, $items, $f_calculo);
    }

    /**
     * Confirma un acta generando comprobante ACT y asientos.
     */
    public function confirmar(int $acta_id, int $usuario_id): array {
        return sindiActaConfirmar($this->c, $acta_id, $usuario_id);
    }

    /**
     * Anula un acta.
     */
    public function anular(int $acta_id, int $usuario_id, string $motivo = ''): bool {
        return sindiActaAnular($this->c, $acta_id, $usuario_id, $motivo);
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

    /**
     * Lista inspectores (usuarios con rol de inspector o perfil interno).
     */
    public function listarInspectores(int $empresa_id): array {
        $sql = "SELECT u.usuario_id, u.usuario_nombre, u.email 
                FROM conf__usuarios u
                WHERE u.tabla_estado_registro_id = 1
                ORDER BY u.usuario_nombre ASC LIMIT 50";
        $res = $this->c->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
