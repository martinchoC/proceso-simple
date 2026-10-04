<?php
/**
 * Modelo de Datos para Plantillas de Tareas y Dependencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/tareas_plantillas_model.php
 */

declare(strict_types=1);

/**
 * Listar plantillas de tareas operativas vinculadas a obligaciones.
 */
function tareas_plantillas_listar(mysqli $conexion, array $filtros = []): array
{
    $sql = "SELECT tp.tarea_plantilla_id, tp.obligacion_id, tp.orden, tp.titulo, tp.descripcion,
                   tp.offset_dias_inicio, tp.offset_dias_vencimiento,
                   tp.procedimiento_id,
                   p.codigo AS procedimiento_codigo, p.nombre AS procedimiento_nombre,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo,
                   tp.tabla_estado_registro_id,
                   CASE WHEN tp.tabla_estado_registro_id = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado_nombre,
                   (SELECT COUNT(*) 
                    FROM ml__tareas_plantillas_dependencias tpd 
                    WHERE tpd.tarea_plantilla_id = tp.tarea_plantilla_id) AS total_predecesoras,
                   (SELECT COUNT(*) 
                    FROM ml__tareas_plantillas_dependencias tpd2 
                    WHERE tpd2.tarea_plantilla_predecesora_id = tp.tarea_plantilla_id) AS total_sucesoras,
                   (SELECT GROUP_CONCAT(CONCAT('#', tp_pred.orden, ' ', tp_pred.titulo) ORDER BY tp_pred.orden SEPARATOR ', ')
                    FROM ml__tareas_plantillas_dependencias tpd3
                    INNER JOIN ml__tareas_plantillas tp_pred ON tp_pred.tarea_plantilla_id = tpd3.tarea_plantilla_predecesora_id
                    WHERE tpd3.tarea_plantilla_id = tp.tarea_plantilla_id) AS predecesoras_nombres
            FROM ml__tareas_plantillas tp
            INNER JOIN ml__obligaciones o ON o.obligacion_id = tp.obligacion_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            LEFT JOIN ml__procedimientos p ON p.procedimiento_id = tp.procedimiento_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filtros['obligacion_id'])) {
        $sql .= " AND tp.obligacion_id = ?";
        $params[] = intval($filtros['obligacion_id']);
        $types .= "i";
    }

    if (!empty($filtros['norma_id'])) {
        $sql .= " AND o.norma_id = ?";
        $params[] = intval($filtros['norma_id']);
        $types .= "i";
    }

    if (isset($filtros['tabla_estado_registro_id']) && $filtros['tabla_estado_registro_id'] !== '') {
        $sql .= " AND tp.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY o.codigo ASC, tp.orden ASC, tp.tarea_plantilla_id ASC";

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
 * Obtener detalle de una plantilla de tarea por ID.
 */
function tarea_plantilla_obtener(mysqli $conexion, int $id): ?array
{
    $sql = "SELECT tp.*,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo,
                   p.codigo AS procedimiento_codigo, p.nombre AS procedimiento_nombre
            FROM ml__tareas_plantillas tp
            INNER JOIN ml__obligaciones o ON o.obligacion_id = tp.obligacion_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            LEFT JOIN ml__procedimientos p ON p.procedimiento_id = tp.procedimiento_id
            WHERE tp.tarea_plantilla_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear o modificar una plantilla de tarea.
 */
function tarea_plantilla_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $id = intval($datos['tarea_plantilla_id'] ?? 0);
    $obligacion_id = intval($datos['obligacion_id'] ?? 0);
    $orden = intval($datos['orden'] ?? 0);
    $titulo = trim($datos['titulo'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $offset_inicio = isset($datos['offset_dias_inicio']) && $datos['offset_dias_inicio'] !== '' ? intval($datos['offset_dias_inicio']) : null;
    $offset_venc = isset($datos['offset_dias_vencimiento']) && $datos['offset_dias_vencimiento'] !== '' ? intval($datos['offset_dias_vencimiento']) : null;
    $procedimiento_id = !empty($datos['procedimiento_id']) ? intval($datos['procedimiento_id']) : null;
    $estado_id = isset($datos['tabla_estado_registro_id']) ? intval($datos['tabla_estado_registro_id']) : 1;

    if ($obligacion_id <= 0 || empty($titulo)) {
        return ['success' => false, 'message' => 'La obligación y el título de la tarea son obligatorios.'];
    }

    if ($orden <= 0) {
        // Calcular próximo orden automáticamente si no vino especificado
        $sql_ord = "SELECT COALESCE(MAX(orden), 0) + 1 AS prox_orden FROM ml__tareas_plantillas WHERE obligacion_id = ?";
        $stmt_ord = mysqli_prepare($conexion, $sql_ord);
        mysqli_stmt_bind_param($stmt_ord, "i", $obligacion_id);
        mysqli_stmt_execute($stmt_ord);
        $res_ord = mysqli_stmt_get_result($stmt_ord);
        $r_ord = mysqli_fetch_assoc($res_ord);
        $orden = intval($r_ord['prox_orden']);
        mysqli_stmt_close($stmt_ord);
    }

    // Validar unicidad de orden dentro de la misma obligacion (uk_tpl_obligacion_orden)
    $sql_chk = "SELECT tarea_plantilla_id FROM ml__tareas_plantillas WHERE obligacion_id = ? AND orden = ?";
    if ($id > 0) {
        $sql_chk .= " AND tarea_plantilla_id != ?";
    }
    $stmt_chk = mysqli_prepare($conexion, $sql_chk);
    if ($id > 0) {
        mysqli_stmt_bind_param($stmt_chk, "iii", $obligacion_id, $orden, $id);
    } else {
        mysqli_stmt_bind_param($stmt_chk, "ii", $obligacion_id, $orden);
    }
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);
    if (mysqli_fetch_assoc($res_chk)) {
        mysqli_stmt_close($stmt_chk);
        return ['success' => false, 'message' => "Ya existe otra tarea con el orden #{$orden} para esta obligación."];
    }
    mysqli_stmt_close($stmt_chk);

    if ($id > 0) {
        // Actualizar
        $sql = "UPDATE ml__tareas_plantillas 
                SET obligacion_id = ?, orden = ?, titulo = ?, descripcion = ?, 
                    offset_dias_inicio = ?, offset_dias_vencimiento = ?, procedimiento_id = ?, 
                    tabla_estado_registro_id = ?, actualizado_por = ?, actualizado_en = CURRENT_TIMESTAMP
                WHERE tarea_plantilla_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iissiiiiii", 
            $obligacion_id, $orden, $titulo, $descripcion,
            $offset_inicio, $offset_venc, $procedimiento_id,
            $estado_id, $usuario_id, $id
        );
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => "Error al actualizar plantilla de tarea: {$err}"];
        }
        return ['success' => true, 'message' => 'Plantilla de tarea actualizada con éxito.', 'tarea_plantilla_id' => $id];
    } else {
        // Crear
        $sql = "INSERT INTO ml__tareas_plantillas 
                (obligacion_id, orden, titulo, descripcion, offset_dias_inicio, offset_dias_vencimiento, 
                 procedimiento_id, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iissiiiii", 
            $obligacion_id, $orden, $titulo, $descripcion,
            $offset_inicio, $offset_venc, $procedimiento_id,
            $estado_id, $usuario_id
        );
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => "Error al crear plantilla de tarea: {$err}"];
        }
        return ['success' => true, 'message' => 'Plantilla de tarea creada con éxito.', 'tarea_plantilla_id' => $nuevo_id];
    }
}

/**
 * Eliminar una plantilla de tarea.
 */
function tarea_plantilla_eliminar(mysqli $conexion, int $id): array
{
    // Verificar si ya fue instanciada en ciclos operativos (ml__ciclos_tareas)
    $sql_ciclos = "SELECT COUNT(*) AS total FROM ml__ciclos_tareas WHERE tarea_plantilla_id = ?";
    $stmt_c = mysqli_prepare($conexion, $sql_ciclos);
    mysqli_stmt_bind_param($stmt_c, "i", $id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $r_c = mysqli_fetch_assoc($res_c);
    mysqli_stmt_close($stmt_c);

    if (!empty($r_c['total']) && intval($r_c['total']) > 0) {
        return [
            'success' => false, 
            'message' => "No se puede eliminar la plantilla porque ya tiene {$r_c['total']} tareas instanciadas en ciclos de cumplimiento. Puede desactivarla cambiando su estado."
        ];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Eliminar dependencias donde figure como sucesora o predecesora
        $stmt_dep = mysqli_prepare($conexion, "DELETE FROM ml__tareas_plantillas_dependencias WHERE tarea_plantilla_id = ? OR tarea_plantilla_predecesora_id = ?");
        mysqli_stmt_bind_param($stmt_dep, "ii", $id, $id);
        mysqli_stmt_execute($stmt_dep);
        mysqli_stmt_close($stmt_dep);

        // Eliminar plantilla
        $stmt_del = mysqli_prepare($conexion, "DELETE FROM ml__tareas_plantillas WHERE tarea_plantilla_id = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $id);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Plantilla de tarea y sus vínculos eliminados con éxito.'];
    } catch (\Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al eliminar plantilla: ' . $e->getMessage()];
    }
}

/**
 * Listar dependencias de una plantilla de tarea (tareas que deben cumplirse antes).
 */
function tarea_plantilla_dependencias_listar(mysqli $conexion, int $tarea_plantilla_id): array
{
    $sql = "SELECT tpd.tarea_plantilla_dependencia_id, tpd.tarea_plantilla_id, tpd.tarea_plantilla_predecesora_id,
                   tp_pred.orden AS predecesora_orden, tp_pred.titulo AS predecesora_titulo,
                   tpd.estado_activador_id,
                   ter.tabla_estado_registro AS estado_activador_nombre,
                   tpd.obligatoria, tpd.creado_en
            FROM ml__tareas_plantillas_dependencias tpd
            INNER JOIN ml__tareas_plantillas tp_pred ON tp_pred.tarea_plantilla_id = tpd.tarea_plantilla_predecesora_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = tpd.estado_activador_id
            WHERE tpd.tarea_plantilla_id = ?
            ORDER BY tp_pred.orden ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $tarea_plantilla_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Listar tareas que pueden actuar como predecesoras para una plantilla dada.
 */
function tarea_plantilla_candidatas_predecesoras(mysqli $conexion, int $tarea_plantilla_id, int $obligacion_id): array
{
    $sql = "SELECT tp.tarea_plantilla_id, tp.orden, tp.titulo
            FROM ml__tareas_plantillas tp
            WHERE tp.obligacion_id = ?
              AND tp.tarea_plantilla_id != ?
              AND tp.tarea_plantilla_id NOT IN (
                  SELECT tpd.tarea_plantilla_predecesora_id 
                  FROM ml__tareas_plantillas_dependencias tpd 
                  WHERE tpd.tarea_plantilla_id = ?
              )
            ORDER BY tp.orden ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $obligacion_id, $tarea_plantilla_id, $tarea_plantilla_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Vincular o actualizar una dependencia entre tareas plantillas.
 */
function tarea_plantilla_dependencia_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $tarea_id = intval($datos['tarea_plantilla_id'] ?? 0);
    $predecesora_id = intval($datos['tarea_plantilla_predecesora_id'] ?? 0);
    $estado_activador_id = intval($datos['estado_activador_id'] ?? 2081); // 2081 = Cumplida
    $obligatoria = isset($datos['obligatoria']) && intval($datos['obligatoria']) === 0 ? 0 : 1;

    if ($tarea_id <= 0 || $predecesora_id <= 0) {
        return ['success' => false, 'message' => 'Debe especificar tanto la tarea sucesora como la predecesora.'];
    }

    if ($tarea_id === $predecesora_id) {
        return ['success' => false, 'message' => 'Una tarea no puede depender de sí misma.'];
    }

    // Verificar que ambas tareas pertenezcan a la misma obligación
    $stmt_val = mysqli_prepare($conexion, "SELECT tarea_plantilla_id, obligacion_id FROM ml__tareas_plantillas WHERE tarea_plantilla_id IN (?, ?)");
    mysqli_stmt_bind_param($stmt_val, "ii", $tarea_id, $predecesora_id);
    mysqli_stmt_execute($stmt_val);
    $res_val = mysqli_stmt_get_result($stmt_val);
    $tasks = mysqli_fetch_all($res_val, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_val);

    if (count($tasks) !== 2) {
        return ['success' => false, 'message' => 'Una o ambas tareas plantillas no existen.'];
    }

    if ($tasks[0]['obligacion_id'] !== $tasks[1]['obligacion_id']) {
        return ['success' => false, 'message' => 'Ambas tareas deben pertenecer a la misma obligación para secuenciarse.'];
    }

    // Prevenir ciclo directo: comprobar si la predecesora ya depende de esta tarea
    $stmt_ciclo = mysqli_prepare($conexion, "SELECT tarea_plantilla_dependencia_id FROM ml__tareas_plantillas_dependencias WHERE tarea_plantilla_id = ? AND tarea_plantilla_predecesora_id = ?");
    mysqli_stmt_bind_param($stmt_ciclo, "ii", $predecesora_id, $tarea_id);
    mysqli_stmt_execute($stmt_ciclo);
    $res_ciclo = mysqli_stmt_get_result($stmt_ciclo);
    if (mysqli_fetch_assoc($res_ciclo)) {
        mysqli_stmt_close($stmt_ciclo);
        return ['success' => false, 'message' => 'Dependencia circular detectada: la tarea predecesora ya depende de la sucesora.'];
    }
    mysqli_stmt_close($stmt_ciclo);

    $sql = "INSERT INTO ml__tareas_plantillas_dependencias 
            (tarea_plantilla_id, tarea_plantilla_predecesora_id, estado_activador_id, obligatoria, creado_en, creado_por)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
            ON DUPLICATE KEY UPDATE 
                estado_activador_id = VALUES(estado_activador_id),
                obligatoria = VALUES(obligatoria),
                actualizado_en = CURRENT_TIMESTAMP,
                actualizado_por = VALUES(creado_por)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiiii", $tarea_id, $predecesora_id, $estado_activador_id, $obligatoria, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => "Error al guardar dependencia: {$err}"];
    }

    return ['success' => true, 'message' => 'Dependencia vinculada con éxito.'];
}

/**
 * Eliminar una dependencia entre plantillas.
 */
function tarea_plantilla_dependencia_eliminar(mysqli $conexion, int $dependencia_id): array
{
    $sql = "DELETE FROM ml__tareas_plantillas_dependencias WHERE tarea_plantilla_dependencia_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $dependencia_id);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => "Error al eliminar dependencia: {$err}"];
    }

    return ['success' => true, 'message' => 'Dependencia eliminada con éxito.'];
}

/**
 * Obtener combos para formularios y filtros de plantillas de tareas.
 */
function tareas_plantillas_combos(mysqli $conexion, ?int $empresa_id = null): array
{
    // Obligaciones activas (con norma asociada)
    $sql_obl = "SELECT o.obligacion_id, o.codigo, o.descripcion, n.codigo AS norma_codigo, n.titulo AS norma_titulo
                FROM ml__obligaciones o
                INNER JOIN ml__normas n ON n.norma_id = o.norma_id
                WHERE o.tabla_estado_registro_id = 2063 OR o.tabla_estado_registro_id = 1
                ORDER BY n.codigo ASC, o.codigo ASC";
    $res_obl = mysqli_query($conexion, $sql_obl);
    $obligaciones = $res_obl ? mysqli_fetch_all($res_obl, MYSQLI_ASSOC) : [];

    // Procedimientos de soporte
    $sql_prc = "SELECT procedimiento_id, codigo, nombre
                FROM ml__procedimientos
                WHERE tabla_estado_registro_id = 1 OR tabla_estado_registro_id = 2055
                ORDER BY codigo ASC";
    $res_prc = mysqli_query($conexion, $sql_prc);
    $procedimientos = $res_prc ? mysqli_fetch_all($res_prc, MYSQLI_ASSOC) : [];

    // Estados activadores de tareas (tabla 254: ml__ciclos_tareas)
    $sql_est = "SELECT tabla_estado_registro_id, tabla_estado_registro
                FROM conf__tablas_estados_registros
                WHERE tabla_id = 254
                ORDER BY orden ASC, tabla_estado_registro_id ASC";
    $res_est = mysqli_query($conexion, $sql_est);
    $estados_activadores = $res_est ? mysqli_fetch_all($res_est, MYSQLI_ASSOC) : [];

    return [
        'obligaciones' => $obligaciones,
        'procedimientos' => $procedimientos,
        'estados_activadores' => $estados_activadores
    ];
}
