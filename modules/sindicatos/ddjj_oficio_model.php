<?php
/**
 * modules/sindicatos/ddjj_oficio_model.php
 * Modelo para la pantalla de Generación de DDJJ de Oficio (Página 1022).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_fiscalizacion_core_model.php';

class DdjjOficioModel {
    private mysqli $c;

    public function __construct(mysqli $conn) {
        $this->c = $conn;
    }

    /**
     * Lista empleadores omisos o en mora para un período determinado.
     */
    public function listarOmisos(int $empresa_id, int $periodo): array {
        $sql = "SELECT e.empleador_id, ent.entidad_nombre AS razon_social, ent.cuit, 
                       cm.nombre AS mora_nombre, cm.color AS mora_color,
                       ep.situacion, ep.f_vto, ep.ddjj_vigente_id,
                       (SELECT COUNT(*) FROM sindi__relaciones_laborales rl WHERE rl.empleador_id = e.empleador_id AND rl.empresa_id = e.empresa_id AND rl.tabla_estado_registro_id = 1 AND rl.f_baja IS NULL) AS empleados_activos
                FROM sindi__empleadores e
                JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
                LEFT JOIN sindi__categorias_mora cm ON cm.categoria_mora_id = e.categoria_mora_id
                LEFT JOIN sindi__empleadores_periodos ep ON ep.empleador_id = e.empleador_id AND ep.empresa_id = e.empresa_id AND ep.periodo = ?
                WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1
                  AND (ep.situacion IS NULL OR ep.situacion IN ('PENDIENTE', 'FUERA_DE_TERMINO'))
                ORDER BY ent.entidad_nombre ASC";

        $stmt = $this->c->prepare($sql);
        $stmt->bind_param("ii", $periodo, $empresa_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Genera la DDJJ de oficio para un empleador y período.
     */
    public function generarOficio(int $empresa_id, int $empleador_id, int $periodo, int $usuario_id, bool $confirmar = false): array {
        return sindiFiscalizacionGenerarDdjjOficio($this->c, $empresa_id, $empleador_id, $periodo, $usuario_id, $confirmar);
    }

    /**
     * Lista las DDJJ de oficio ya emitidas.
     */
    public function listarEmitidas(int $empresa_id, ?int $periodo = null): array {
        $where = "WHERE d.empresa_id = ? AND d.tipo = 'OFICIO'";
        $params = [$empresa_id];
        $types = "i";

        if ($periodo !== null && $periodo > 0) {
            $where .= " AND d.periodo = ?";
            $params[] = $periodo;
            $types .= "i";
        }

        $sql = "SELECT d.*, ent.entidad_nombre AS razon_social, ent.cuit, er.tabla_estado_registro AS estado_nombre,
                       (SELECT COUNT(*) FROM sindi__ddjj_empleados de WHERE de.ddjj_id = d.ddjj_id) AS cantidad_empleados
                FROM sindi__ddjj d
                JOIN sindi__empleadores e ON e.empleador_id = d.empleador_id
                JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
                LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 521 AND er.estado_registro_id = d.tabla_estado_registro_id
                $where
                ORDER BY d.ddjj_id DESC LIMIT 50";

        $stmt = $this->c->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}
