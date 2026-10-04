<?php
/**
 * Modelo de Datos: Hallazgos e Incidencias (Página 353 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/incidencias_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ejecucion_seguimiento_service.php';

/**
 * Listar incidencias con filtros
 */
function incidencias_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    $where = ["inc.empresa_id = {$empresa_id}"];

    if (!empty($filtros['entidad_id'])) {
        $entidad_id = (int)$filtros['entidad_id'];
        $where[] = "c.entidad_id = {$entidad_id}";
    }

    if (!empty($filtros['ciclo_id'])) {
        $ciclo_id = (int)$filtros['ciclo_id'];
        $where[] = "inc.ciclo_id = {$ciclo_id}";
    }

    if (!empty($filtros['estado_id'])) {
        $estado_id = (int)$filtros['estado_id'];
        $where[] = "inc.tabla_estado_registro_id = {$estado_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                inc.incidencia_id,
                inc.ciclo_id,
                inc.descripcion,
                DATE_FORMAT(inc.abierta_en, '%d/%m/%Y %H:%i') as abierta_en_fmt,
                inc.ciclo_seguimiento_id,
                inc.tabla_estado_registro_id as estado_id,
                CASE inc.tabla_estado_registro_id
                    WHEN 1 THEN 'Abierta'
                    WHEN 2 THEN 'Cerrada / Subsanada'
                    ELSE 'Inactiva'
                END as estado_nombre,
                DATE_FORMAT(inc.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                u.usuario_nombre as reportado_por_nombre,
                c.fecha_vencimiento as ciclo_vencimiento,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y %H:%i') as ciclo_vencimiento_fmt,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                e.nombre as entidad_nombre,
                cs.ciclo_id as seguimiento_codigo,
                DATE_FORMAT(cs.fecha_vencimiento, '%d/%m/%Y %H:%i') as seguimiento_vencimiento_fmt
            FROM ml__incidencias inc
            INNER JOIN ml__ciclos c ON c.ciclo_id = inc.ciclo_id
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            LEFT JOIN ml__ciclos cs ON cs.ciclo_id = inc.ciclo_seguimiento_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = inc.creado_por
            WHERE {$where_sql}
            ORDER BY inc.incidencia_id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar incidencias: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener detalle de una incidencia
 */
function incidencias_obtener_por_id(mysqli $conexion, int $incidencia_id, int $empresa_id): ?array {
    $sql = "SELECT 
                inc.*,
                DATE_FORMAT(inc.abierta_en, '%d/%m/%Y %H:%i') as abierta_en_fmt,
                DATE_FORMAT(inc.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                u.usuario_nombre as reportado_por_nombre,
                c.ciclo_id,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y %H:%i') as ciclo_vencimiento_fmt,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                e.nombre as entidad_nombre,
                cs.ciclo_id as seguimiento_codigo,
                DATE_FORMAT(cs.fecha_vencimiento, '%d/%m/%Y %H:%i') as seguimiento_vencimiento_fmt
            FROM ml__incidencias inc
            INNER JOIN ml__ciclos c ON c.ciclo_id = inc.ciclo_id
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            LEFT JOIN ml__ciclos cs ON cs.ciclo_id = inc.ciclo_seguimiento_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = inc.creado_por
            WHERE inc.incidencia_id = ? AND inc.empresa_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $incidencia_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $incidencia = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $incidencia ?: null;
}

/**
 * Cerrar incidencia
 */
function incidencias_cerrar(mysqli $conexion, int $incidencia_id, string $motivo, int $empresa_id, int $usuario_id): array {
    $obs = "\n[Cerrada por usuario #{$usuario_id}] " . $motivo;
    $sql = "UPDATE ml__incidencias 
            SET tabla_estado_registro_id = 2,
                descripcion = CONCAT(COALESCE(descripcion, ''), ?),
                actualizado_en = CURRENT_TIMESTAMP,
                actualizado_por = ?
            WHERE incidencia_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "siii", $obs, $usuario_id, $incidencia_id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Incidencia cerrada y registrada como subsanada.'];
    }
    return ['success' => false, 'message' => 'Error al cerrar la incidencia.'];
}
