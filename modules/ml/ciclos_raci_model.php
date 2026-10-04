<?php
/**
 * Modelo de Datos: Matriz RACI y Asignaciones (Página 344 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_raci_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ciclos_generador_service.php';

/**
 * Listar asignaciones RACI con filtros
 */
function ciclos_raci_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    $where = ["ctr.empresa_id = {$empresa_id}"];

    if (!empty($filtros['usuario_id'])) {
        $usuario_id = (int)$filtros['usuario_id'];
        $where[] = "ctr.usuario_id = {$usuario_id}";
    }

    if (!empty($filtros['raci_rol_id'])) {
        $raci_rol_id = (int)$filtros['raci_rol_id'];
        $where[] = "ctr.raci_rol_id = {$raci_rol_id}";
    }

    if (!empty($filtros['ciclo_id'])) {
        $ciclo_id = (int)$filtros['ciclo_id'];
        $where[] = "ct.ciclo_id = {$ciclo_id}";
    }

    if (!empty($filtros['entidad_id'])) {
        $entidad_id = (int)$filtros['entidad_id'];
        $where[] = "c.entidad_id = {$entidad_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                ctr.ciclo_tarea_raci_id,
                ctr.ciclo_tarea_id,
                ctr.raci_rol_id,
                ctr.usuario_id,
                DATE_FORMAT(ctr.vigente_desde, '%d/%m/%Y') as vigente_desde_fmt,
                DATE_FORMAT(ctr.vigente_hasta, '%d/%m/%Y') as vigente_hasta_fmt,
                u.usuario_nombre as usuario_nombre,
                u.email as usuario_email,
                ct.titulo as tarea_titulo,
                DATE_FORMAT(ct.fecha_vencimiento, '%d/%m/%Y %H:%i') as tarea_vencimiento_fmt,
                ct.tabla_estado_registro_id as tarea_estado_id,
                est.tabla_estado_registro as tarea_estado_nombre,
                c.ciclo_id,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                e.nombre as entidad_nombre,
                CASE ctr.raci_rol_id
                    WHEN 1 THEN 'R - Responsable'
                    WHEN 2 THEN 'A - Aprobador'
                    WHEN 3 THEN 'C - Consultado'
                    WHEN 4 THEN 'I - Informado'
                END as rol_nombre
            FROM ml__ciclos_tareas_raci ctr
            INNER JOIN conf__usuarios u ON u.usuario_id = ctr.usuario_id
            INNER JOIN ml__ciclos_tareas ct ON ct.ciclo_tarea_id = ctr.ciclo_tarea_id
            INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = ct.tabla_estado_registro_id
            INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            WHERE {$where_sql}
            ORDER BY ctr.ciclo_tarea_id DESC, ctr.raci_rol_id ASC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar matriz RACI: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Asignar o modificar rol RACI en una tarea
 */
function ciclos_raci_guardar(
    mysqli $conexion, 
    int $ciclo_tarea_id, 
    int $raci_rol_id, 
    int $usuario_id, 
    int $empresa_id, 
    int $usuario_accion_id
): array {
    if ($ciclo_tarea_id <= 0 || $raci_rol_id <= 0 || $usuario_id <= 0) {
        return ['ok' => false, 'error' => 'Parámetros incompletos para asignar rol RACI.'];
    }

    // Si es rol R (Responsable), normalmente hay uno solo por tarea: actualizamos o insertamos
    if ($raci_rol_id === 1) {
        $sql_check = "SELECT ciclo_tarea_raci_id FROM ml__ciclos_tareas_raci WHERE ciclo_tarea_id = ? AND raci_rol_id = 1 LIMIT 1";
        $stmt_check = mysqli_prepare($conexion, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "i", $ciclo_tarea_id);
        mysqli_stmt_execute($stmt_check);
        $res_check = mysqli_stmt_get_result($stmt_check);
        if ($r = mysqli_fetch_assoc($res_check)) {
            // Update
            $sql_up = "UPDATE ml__ciclos_tareas_raci SET usuario_id = ?, actualizado_por = ?, actualizado_en = NOW() WHERE ciclo_tarea_raci_id = ?";
            $stmt_up = mysqli_prepare($conexion, $sql_up);
            mysqli_stmt_bind_param($stmt_up, "iii", $usuario_id, $usuario_accion_id, $r['ciclo_tarea_raci_id']);
            mysqli_stmt_execute($stmt_up);
            return ['ok' => true, 'mensaje' => 'Responsable (R) actualizado correctamente.'];
        }
    }

    // Insertar nuevo registro
    $hoy = date('Y-m-d');
    $sql_ins = "INSERT INTO ml__ciclos_tareas_raci (ciclo_tarea_id, raci_rol_id, usuario_id, vigente_desde, empresa_id, creado_por)
                VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql_ins);
    mysqli_stmt_bind_param($stmt_ins, "iiisii", $ciclo_tarea_id, $raci_rol_id, $usuario_id, $hoy, $empresa_id, $usuario_accion_id);
    if (!mysqli_stmt_execute($stmt_ins)) {
        return ['ok' => false, 'error' => 'Error al asignar rol RACI: ' . mysqli_error($conexion)];
    }

    return ['ok' => true, 'mensaje' => 'Rol RACI asignado exitosamente.'];
}

/**
 * Eliminar rol RACI de una tarea
 */
function ciclos_raci_eliminar(mysqli $conexion, int $ciclo_tarea_raci_id, int $empresa_id): array {
    $sql = "DELETE FROM ml__ciclos_tareas_raci WHERE ciclo_tarea_raci_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $ciclo_tarea_raci_id, $empresa_id);
    if (!mysqli_stmt_execute($stmt)) {
        return ['ok' => false, 'error' => 'Error al eliminar rol RACI: ' . mysqli_error($conexion)];
    }
    return ['ok' => true, 'mensaje' => 'Asignación eliminada exitosamente.'];
}

/**
 * Listar historial de reasignaciones en bloque ejecutadas
 */
function ciclos_raci_listar_reasignaciones(mysqli $conexion): array {
    $sql = "SELECT 
                r.reasignacion_id,
                r.usuario_origen_id,
                r.usuario_destino_id,
                DATE_FORMAT(r.fecha_efecto, '%d/%m/%Y') as fecha_efecto_fmt,
                DATE_FORMAT(r.aplicada_en, '%d/%m/%Y %H:%i') as aplicada_en_fmt,
                r.motivo,
                uo.usuario_nombre as usuario_origen_nombre,
                ud.usuario_nombre as usuario_destino_nombre,
                ua.usuario_nombre as aplicada_por_nombre
            FROM ml__reasignaciones r
            INNER JOIN conf__usuarios uo ON uo.usuario_id = r.usuario_origen_id
            INNER JOIN conf__usuarios ud ON ud.usuario_id = r.usuario_destino_id
            LEFT JOIN conf__usuarios ua ON ua.usuario_id = r.aplicada_por
            ORDER BY r.reasignacion_id DESC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $lista[] = $row;
        }
    }
    return $lista;
}
