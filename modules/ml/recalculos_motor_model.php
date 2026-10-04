<?php
/**
 * Modelo de Datos para la Consola del Motor de Herencia y Cola de Recálculos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/recalculos_motor_model.php
 * Casos Cubiertos: C01, C05, C06, C11, C13, C20, C25, C35.
 */

declare(strict_types=1);

require_once __DIR__ . '/motor_herencia_service.php';

/**
 * Listar eventos encolados en la tabla ml__recalculos_pendientes.
 */
function recalculos_pendientes_listar(mysqli $conexion, array $filtros = []): array
{
    $sql = "SELECT rp.recalculo_pendiente_id, rp.causa, rp.objeto_tabla, rp.objeto_id,
                   rp.fecha_efecto, rp.creado_en, rp.procesado_en,
                   rp.tabla_estado_registro_id, ter.tabla_estado_registro AS estado_nombre
            FROM ml__recalculos_pendientes rp
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = rp.tabla_estado_registro_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filtros['tabla_estado_registro_id'])) {
        $sql .= " AND rp.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    if (!empty($filtros['causa'])) {
        $sql .= " AND rp.causa = ?";
        $params[] = trim($filtros['causa']);
        $types .= "s";
    }

    $sql .= " ORDER BY rp.recalculo_pendiente_id DESC LIMIT 100";

    if (!empty($params)) {
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    $res = mysqli_query($conexion, $sql);
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

/**
 * Resumen de estadísticas globales del motor de herencia.
 */
function motor_obtener_estadisticas(mysqli $conexion, int $empresa_id): array
{
    // Total entidades
    $res_ent = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__entidades e INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id WHERE ee.empresa_id = {$empresa_id} AND e.tabla_estado_registro_id = 1");
    $r_ent = mysqli_fetch_assoc($res_ent);
    $total_entidades = intval($r_ent['total'] ?? 0);

    // Total obligaciones asignadas activas
    $res_asig = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__obligaciones_asignadas WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 1");
    $r_asig = mysqli_fetch_assoc($res_asig);
    $total_asignadas = intval($r_asig['total'] ?? 0);

    // Total solapamientos consolidados (orígenes > asignadas)
    $res_orig = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__obligaciones_asignadas_origenes oaso INNER JOIN ml__obligaciones_asignadas oas ON oas.obligacion_asignada_id = oaso.obligacion_asignada_id WHERE oas.empresa_id = {$empresa_id}");
    $r_orig = mysqli_fetch_assoc($res_orig);
    $total_origenes = intval($r_orig['total'] ?? 0);
    $total_solapamientos = max(0, $total_origenes - $total_asignadas);

    // Total excepciones vigentes
    $res_exc = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__excepciones WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 2089 AND CURRENT_DATE BETWEEN vigente_desde AND vigente_hasta");
    $r_exc = mysqli_fetch_assoc($res_exc);
    $total_excepciones = intval($r_exc['total'] ?? 0);

    // Recálculos pendientes en cola
    $res_pen = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__recalculos_pendientes WHERE tabla_estado_registro_id = 2092");
    $r_pen = mysqli_fetch_assoc($res_pen);
    $total_pendientes = intval($r_pen['total'] ?? 0);

    return [
        'total_entidades' => $total_entidades,
        'total_asignadas' => $total_asignadas,
        'total_solapamientos' => $total_solapamientos,
        'total_excepciones' => $total_excepciones,
        'total_pendientes' => $total_pendientes
    ];
}

/**
 * Combos para selector de empresa o entidad en la consola del motor.
 */
function recalculos_motor_combos(mysqli $conexion, int $empresa_id): array
{
    $sql_ent = "SELECT DISTINCT e.entidad_id, e.codigo, e.nombre, et.nombre AS tipo_nombre
                FROM ml__entidades e
                INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
                INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id AND ee.empresa_id = ?
                WHERE e.tabla_estado_registro_id = 1
                ORDER BY e.es_raiz DESC, e.nombre ASC";
    $stmt_e = mysqli_prepare($conexion, $sql_ent);
    mysqli_stmt_bind_param($stmt_e, "i", $empresa_id);
    mysqli_stmt_execute($stmt_e);
    $res_e = mysqli_stmt_get_result($stmt_e);
    $entidades = mysqli_fetch_all($res_e, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_e);

    return [
        'entidades' => $entidades
    ];
}
