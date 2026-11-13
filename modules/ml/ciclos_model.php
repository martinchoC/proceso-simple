<?php
/**
 * Modelo de Datos: Calendario y Ciclos (Página 342 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ciclos_generador_service.php';

/**
 * Listar ciclos con filtros y progreso de tareas
 */
function ciclos_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    if ($empresa_id === 1) {
        $where = ["(c.empresa_id IN (1, 2, 3))"];
    } else {
        $where = ["c.empresa_id = {$empresa_id}"];
    }

    if (!empty($filtros['entidad_id'])) {
        $entidad_id = (int)$filtros['entidad_id'];
        $where[] = "c.entidad_id = {$entidad_id}";
    }

    if (!empty($filtros['obligacion_id'])) {
        $obligacion_id = (int)$filtros['obligacion_id'];
        $where[] = "oa.obligacion_prevaleciente_id = {$obligacion_id}";
    }

    if (!empty($filtros['estado_id'])) {
        $estado_id = (int)$filtros['estado_id'];
        $where[] = "c.tabla_estado_registro_id = {$estado_id}";
    }

    if (!empty($filtros['fecha_desde'])) {
        $fd = mysqli_real_escape_string($conexion, $filtros['fecha_desde']);
        $where[] = "c.fecha_vencimiento >= '{$fd} 00:00:00'";
    }

    if (!empty($filtros['fecha_hasta'])) {
        $fh = mysqli_real_escape_string($conexion, $filtros['fecha_hasta']);
        $where[] = "c.fecha_vencimiento <= '{$fh} 23:59:59'";
    }

    if (!empty($filtros['vinculo_tipo_id'])) {
        $vinculo_tipo_id = (int)$filtros['vinculo_tipo_id'];
        $where[] = "c.ciclo_vinculo_tipo_id = {$vinculo_tipo_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                c.ciclo_id,
                c.obligacion_asignada_id,
                c.empresa_id,
                c.entidad_id,
                c.ciclo_origen_id,
                c.ciclo_vinculo_tipo_id,
                DATE_FORMAT(c.periodo_desde, '%d/%m/%Y') as periodo_desde_fmt,
                DATE_FORMAT(c.periodo_hasta, '%d/%m/%Y') as periodo_hasta_fmt,
                c.periodo_desde,
                c.periodo_hasta,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y %H:%i') as fecha_vencimiento_fmt,
                c.fecha_vencimiento,
                DATE_FORMAT(c.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_fmt,
                c.observaciones,
                c.tabla_estado_registro_id as estado_id,
                est.tabla_estado_registro as estado_nombre,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                cr.nombre as periodicidad_nombre,
                crit.nombre as criticidad_nombre,
                '#6c757d' as criticidad_color,
                e.nombre as entidad_nombre,
                et.nombre as entidad_tipo_nombre,
                cvt.nombre as vinculo_tipo_nombre,
                (SELECT COUNT(*) FROM ml__ciclos_tareas ct WHERE ct.ciclo_id = c.ciclo_id) as total_tareas,
                (SELECT COUNT(*) FROM ml__ciclos_tareas ct WHERE ct.ciclo_id = c.ciclo_id AND ct.tabla_estado_registro_id = 2081) as tareas_cumplidas
            FROM ml__ciclos c
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__periodicidades cr ON cr.periodicidad_id = o.periodicidad_id
            LEFT JOIN ml__criticidades crit ON crit.criticidad_id = o.criticidad_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN ml__ciclos_vinculos_tipos cvt ON cvt.ciclo_vinculo_tipo_id = c.ciclo_vinculo_tipo_id
            WHERE {$where_sql}
            ORDER BY c.fecha_vencimiento ASC, c.ciclo_id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar ciclos: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $total = (int)$row['total_tareas'];
        $cumplidas = (int)$row['tareas_cumplidas'];
        $porc = $total > 0 ? round(($cumplidas / $total) * 100) : 0;
        $row['progreso_porcentaje'] = $porc;
        $lista[] = $row;
    }

    return $lista;
}

/**
 * Obtener detalle de un ciclo con sus tareas y datos vinculados
 */
function ciclos_obtener_por_id(mysqli $conexion, int $ciclo_id, int $empresa_id): ?array {
    $sql = "SELECT 
                c.*,
                DATE_FORMAT(c.periodo_desde, '%d/%m/%Y') as periodo_desde_fmt,
                DATE_FORMAT(c.periodo_hasta, '%d/%m/%Y') as periodo_hasta_fmt,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y %H:%i') as fecha_vencimiento_fmt,
                DATE_FORMAT(c.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_fmt,
                est.tabla_estado_registro as estado_nombre,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                o.descripcion as obligacion_descripcion,
                cr.nombre as periodicidad_nombre,
                crit.nombre as criticidad_nombre,
                '#6c757d' as criticidad_color,
                e.nombre as entidad_nombre,
                et.nombre as entidad_tipo_nombre,
                cvt.nombre as vinculo_tipo_nombre,
                co.ciclo_id as ciclo_origen_codigo
            FROM ml__ciclos c
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__periodicidades cr ON cr.periodicidad_id = o.periodicidad_id
            LEFT JOIN ml__criticidades crit ON crit.criticidad_id = o.criticidad_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN ml__ciclos_vinculos_tipos cvt ON cvt.ciclo_vinculo_tipo_id = c.ciclo_vinculo_tipo_id
            LEFT JOIN ml__ciclos co ON co.ciclo_id = c.ciclo_origen_id
            WHERE c.ciclo_id = ? AND c.empresa_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $ciclo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ciclo = mysqli_fetch_assoc($res);

    if (!$ciclo) {
        return null;
    }

    // Tareas del ciclo
    $sql_t = "SELECT 
                ct.*,
                DATE_FORMAT(ct.fecha_vencimiento, '%d/%m/%Y %H:%i') as fecha_vencimiento_fmt,
                DATE_FORMAT(ct.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_fmt,
                est.tabla_estado_registro as estado_nombre,
                tp.titulo as plantilla_titulo
              FROM ml__ciclos_tareas ct
              INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = ct.tabla_estado_registro_id
              LEFT JOIN ml__tareas_plantillas tp ON tp.tarea_plantilla_id = ct.tarea_plantilla_id
              WHERE ct.ciclo_id = ?
              ORDER BY ct.ciclo_tarea_id ASC";
    $stmt_t = mysqli_prepare($conexion, $sql_t);
    mysqli_stmt_bind_param($stmt_t, "i", $ciclo_id);
    mysqli_stmt_execute($stmt_t);
    $res_t = mysqli_stmt_get_result($stmt_t);
    $tareas = [];
    while ($t = mysqli_fetch_assoc($res_t)) {
        $tareas[] = $t;
    }
    $ciclo['tareas'] = $tareas;

    return $ciclo;
}

/**
 * Cerrar ciclo manualmente o forzar cierre verificado
 */
function ciclos_cerrar(mysqli $conexion, int $ciclo_id, int $empresa_id, int $usuario_id, string $observaciones = ''): array {
    $ciclo = ciclos_obtener_por_id($conexion, $ciclo_id, $empresa_id);
    if (!$ciclo) {
        return ['ok' => false, 'error' => 'Ciclo no encontrado'];
    }

    // Verificar si hay tareas pendientes no cumplidas
    $pendientes = 0;
    foreach ($ciclo['tareas'] as $t) {
        if (!in_array((int)$t['tabla_estado_registro_id'], [2081, 2083], true)) {
            $pendientes++;
        }
    }

    if ($pendientes > 0) {
        return ['ok' => false, 'error' => "No se puede cerrar el ciclo: existen {$pendientes} tareas pendientes o sin cumplir."];
    }

    $ahora = date('Y-m-d H:i:s');
    $estado_cierre = ($ahora <= $ciclo['fecha_vencimiento']) ? 2072 : 2073; // 2072=Cumplido, 2073=Fuera de plazo

    $obs_final = trim($ciclo['observaciones'] . "\n[Cierre Manual] " . $observaciones);

    $sql = "UPDATE ml__ciclos 
            SET tabla_estado_registro_id = ?,
                fecha_cierre = ?,
                observaciones = ?,
                actualizado_por = ?,
                actualizado_en = NOW()
            WHERE ciclo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "issiii", $estado_cierre, $ahora, $obs_final, $usuario_id, $ciclo_id, $empresa_id);
    if (!mysqli_stmt_execute($stmt)) {
        return ['ok' => false, 'error' => 'Error al cerrar ciclo: ' . mysqli_error($conexion)];
    }

    return ['ok' => true, 'mensaje' => ($estado_cierre === 2072 ? 'Ciclo cumplido en plazo exitosamente.' : 'Ciclo cerrado fuera de plazo.')];
}

/**
 * Eximir ciclo (ej. por resolución de autoridad o excepción aprobada)
 */
function ciclos_eximir(mysqli $conexion, int $ciclo_id, int $empresa_id, int $usuario_id, string $motivo): array {
    if (empty(trim($motivo))) {
        return ['ok' => false, 'error' => 'Debe ingresar un motivo para eximir el ciclo.'];
    }

    $ciclo = ciclos_obtener_por_id($conexion, $ciclo_id, $empresa_id);
    if (!$ciclo) {
        return ['ok' => false, 'error' => 'Ciclo no encontrado'];
    }

    $ahora = date('Y-m-d H:i:s');
    $obs_final = trim($ciclo['observaciones'] . "\n[Eximición] " . $motivo);

    // Cancelar tareas abiertas y marcar ciclo 2075 Eximido
    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE ml__ciclos 
                SET tabla_estado_registro_id = 2075,
                    fecha_cierre = ?,
                    observaciones = ?,
                    actualizado_por = ?,
                    actualizado_en = NOW()
                WHERE ciclo_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssiii", $ahora, $obs_final, $usuario_id, $ciclo_id, $empresa_id);
        mysqli_stmt_execute($stmt);

        // Cancelar tareas que no estén cumplidas
        $sql_t = "UPDATE ml__ciclos_tareas 
                  SET tabla_estado_registro_id = 2083,
                      fecha_cierre = ?,
                      actualizado_por = ?,
                      actualizado_en = NOW()
                  WHERE ciclo_id = ? AND tabla_estado_registro_id != 2081";
        $stmt_t = mysqli_prepare($conexion, $sql_t);
        mysqli_stmt_bind_param($stmt_t, "sii", $ahora, $usuario_id, $ciclo_id);
        mysqli_stmt_execute($stmt_t);

        mysqli_commit($conexion);
        return ['ok' => true, 'mensaje' => 'Ciclo eximido correctamente.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Cancelar ciclo
 */
function ciclos_cancelar(mysqli $conexion, int $ciclo_id, int $empresa_id, int $usuario_id, string $motivo): array {
    if (empty(trim($motivo))) {
        return ['ok' => false, 'error' => 'Debe ingresar un motivo para cancelar el ciclo.'];
    }

    $ciclo = ciclos_obtener_por_id($conexion, $ciclo_id, $empresa_id);
    if (!$ciclo) {
        return ['ok' => false, 'error' => 'Ciclo no encontrado'];
    }

    $ahora = date('Y-m-d H:i:s');
    $obs_final = trim($ciclo['observaciones'] . "\n[Cancelación] " . $motivo);

    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE ml__ciclos 
                SET tabla_estado_registro_id = 2076,
                    fecha_cierre = ?,
                    observaciones = ?,
                    actualizado_por = ?,
                    actualizado_en = NOW()
                WHERE ciclo_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssiii", $ahora, $obs_final, $usuario_id, $ciclo_id, $empresa_id);
        mysqli_stmt_execute($stmt);

        // Cancelar tareas no cumplidas
        $sql_t = "UPDATE ml__ciclos_tareas 
                  SET tabla_estado_registro_id = 2083,
                      fecha_cierre = ?,
                      actualizado_por = ?,
                      actualizado_en = NOW()
                  WHERE ciclo_id = ? AND tabla_estado_registro_id != 2081";
        $stmt_t = mysqli_prepare($conexion, $sql_t);
        mysqli_stmt_bind_param($stmt_t, "sii", $ahora, $usuario_id, $ciclo_id);
        mysqli_stmt_execute($stmt_t);

        mysqli_commit($conexion);
        return ['ok' => true, 'mensaje' => 'Ciclo cancelado correctamente.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
