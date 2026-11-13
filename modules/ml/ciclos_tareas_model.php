<?php
/**
 * Modelo de Datos: Tareas Operativas (Página 343 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_tareas_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ciclos_generador_service.php';

/**
 * Listar tareas operativas con filtros avanzados
 */
function ciclos_tareas_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    if ($empresa_id === 1) {
        $where = ["(ct.empresa_id IN (1, 2, 3))"];
    } else {
        $where = ["ct.empresa_id = {$empresa_id}"];
    }

    if (!empty($filtros['ciclo_id'])) {
        $ciclo_id = (int)$filtros['ciclo_id'];
        $where[] = "ct.ciclo_id = {$ciclo_id}";
    }

    if (!empty($filtros['entidad_id'])) {
        $entidad_id = (int)$filtros['entidad_id'];
        $where[] = "c.entidad_id = {$entidad_id}";
    }

    if (!empty($filtros['territorio_id'])) {
        $territorio_id = (int)$filtros['territorio_id'];
        $where[] = "EXISTS (
            SELECT 1 FROM ml__entidades_ubicaciones eu2 
            WHERE eu2.entidad_id = c.entidad_id 
              AND (eu2.territorio_id = {$territorio_id} OR eu2.territorio_id IN (
                  SELECT territorio_id FROM ml__territorios_pertenencias WHERE territorio_superior_id = {$territorio_id}
              ))
        )";
    }

    if (!empty($filtros['estado_id'])) {
        if ($filtros['estado_id'] === 'vencidas') {
            $where[] = "ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083)";
        } elseif ($filtros['estado_id'] === 'proximas') {
            $where[] = "ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083)";
        } else {
            $estado_id = (int)$filtros['estado_id'];
            $where[] = "ct.tabla_estado_registro_id = {$estado_id}";
        }
    }

    if (!empty($filtros['usuario_id'])) {
        $usuario_id = (int)$filtros['usuario_id'];
        $where[] = "EXISTS (SELECT 1 FROM ml__ciclos_tareas_raci ctr WHERE ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.usuario_id = {$usuario_id})";
    }

    if (!empty($filtros['fecha_desde'])) {
        $fd = mysqli_real_escape_string($conexion, $filtros['fecha_desde']);
        $where[] = "ct.fecha_vencimiento >= '{$fd} 00:00:00'";
    }

    if (!empty($filtros['fecha_hasta'])) {
        $fh = mysqli_real_escape_string($conexion, $filtros['fecha_hasta']);
        $where[] = "ct.fecha_vencimiento <= '{$fh} 23:59:59'";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                ct.ciclo_tarea_id,
                ct.ciclo_id,
                ct.tarea_plantilla_id,
                ct.titulo,
                ct.descripcion,
                ct.procedimiento_version_id,
                ct.requiere_revision,
                ct.requiere_evidencia,
                DATE_FORMAT(ct.fecha_inicio, '%d/%m/%Y %H:%i') as fecha_inicio_fmt,
                DATE_FORMAT(ct.fecha_vencimiento, '%d/%m/%Y %H:%i') as fecha_vencimiento_fmt,
                DATE_FORMAT(ct.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_fmt,
                ct.fecha_vencimiento,
                ct.tabla_estado_registro_id as estado_id,
                est.tabla_estado_registro as estado_nombre,
                c.periodo_desde,
                c.periodo_hasta,
                DATE_FORMAT(c.periodo_desde, '%d/%m/%Y') as ciclo_periodo_desde_fmt,
                DATE_FORMAT(c.periodo_hasta, '%d/%m/%Y') as ciclo_periodo_hasta_fmt,
                o.codigo as obligacion_codigo,
                COALESCE(o.sigla, o.codigo) as obligacion_sigla,
                COALESCE(o.icono, 'fas fa-balance-scale') as obligacion_icono,
                COALESCE(o.color_hex, '#0d6efd') as obligacion_color,
                o.imagen_ref as obligacion_imagen,
                (SELECT COUNT(*) FROM ml__ciclos_tareas_mensajes tm WHERE tm.ciclo_tarea_id = ct.ciclo_tarea_id AND tm.tabla_estado_registro_id = 1) as total_mensajes,
                o.descripcion as obligacion_titulo,
                e.nombre as entidad_nombre,
                et.nombre as entidad_tipo_nombre,
                tp.titulo as plantilla_titulo,
                (SELECT u.usuario_nombre as nombre_completo 
                 FROM ml__ciclos_tareas_raci ctr 
                 INNER JOIN conf__usuarios u ON u.usuario_id = ctr.usuario_id 
                 WHERE ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1 
                 LIMIT 1) as responsable_nombre,
                (SELECT ctr.usuario_id 
                 FROM ml__ciclos_tareas_raci ctr 
                 WHERE ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1 
                 LIMIT 1) as responsable_usuario_id,
                (SELECT COUNT(*) FROM ml__evidencias ev WHERE ev.ciclo_tarea_id = ct.ciclo_tarea_id AND ev.tabla_estado_registro_id IN (1, 2039)) as total_evidencias,
                COALESCE(crit.nombre, 'Media') as criticidad_nombre,
                COALESCE(crit.peso, 2) as criticidad_peso,
                e.entidad_id,
                o.obligacion_id,
                (SELECT COUNT(*) FROM ml__ciclos_tareas_dependencias dep WHERE dep.ciclo_tarea_id = ct.ciclo_tarea_id) as total_predecesoras,
                COALESCE(t.nombre, 'Argentina') as pais_nombre,
                COALESCE(t.codigo, 'ARG') as pais_codigo,
                COALESCE(t.territorio_id, 2) as pais_id
            FROM ml__ciclos_tareas ct
            INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__criticidades crit ON crit.criticidad_id = o.criticidad_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            LEFT JOIN ml__entidades_ubicaciones eu ON eu.entidad_id = e.entidad_id
            LEFT JOIN ml__territorios t ON t.territorio_id = eu.territorio_id
            INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = ct.tabla_estado_registro_id
            LEFT JOIN ml__tareas_plantillas tp ON tp.tarea_plantilla_id = ct.tarea_plantilla_id
            WHERE {$where_sql}
            GROUP BY ct.ciclo_tarea_id
            ORDER BY ct.fecha_vencimiento ASC, ct.ciclo_tarea_id ASC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar tareas operativas: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener detalle completo de una tarea operativa
 */
function ciclos_tareas_obtener_por_id(mysqli $conexion, int $ciclo_tarea_id, int $empresa_id): ?array {
    $sql = "SELECT 
                ct.*,
                DATE_FORMAT(ct.fecha_activacion, '%d/%m/%Y %H:%i') as fecha_activacion_fmt,
                DATE_FORMAT(ct.fecha_inicio, '%d/%m/%Y %H:%i') as fecha_inicio_fmt,
                DATE_FORMAT(ct.fecha_vencimiento, '%d/%m/%Y %H:%i') as fecha_vencimiento_fmt,
                DATE_FORMAT(ct.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_fmt,
                est.tabla_estado_registro as estado_nombre,
                c.ciclo_id,
                DATE_FORMAT(c.periodo_desde, '%d/%m/%Y') as periodo_desde_fmt,
                DATE_FORMAT(c.periodo_hasta, '%d/%m/%Y') as periodo_hasta_fmt,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y %H:%i') as ciclo_vencimiento_fmt,
                o.codigo as obligacion_codigo,
                COALESCE(o.sigla, o.codigo) as obligacion_sigla,
                COALESCE(o.icono, 'fas fa-balance-scale') as obligacion_icono,
                COALESCE(o.color_hex, '#0d6efd') as obligacion_color,
                o.imagen_ref as obligacion_imagen,
                o.descripcion as obligacion_titulo,
                e.nombre as entidad_nombre,
                et.nombre as entidad_tipo_nombre,
                pv.version as procedimiento_version,
                pv.archivo_ref as procedimiento_archivo_ref,
                pv.archivo_nombre_original as procedimiento_archivo_nombre,
                COALESCE(p.descripcion, '') as instructivo_contenido,
                COALESCE(p.descripcion, '') as instructivo,
                p.nombre as procedimiento_nombre,
                p.codigo as procedimiento_codigo,
                (SELECT u.usuario_nombre 
                 FROM ml__ciclos_tareas_raci ctr 
                 INNER JOIN conf__usuarios u ON u.usuario_id = ctr.usuario_id 
                 WHERE ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1 
                 LIMIT 1) as responsable_nombre,
                (SELECT ctr.usuario_id 
                 FROM ml__ciclos_tareas_raci ctr 
                 WHERE ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1 
                 LIMIT 1) as responsable_usuario_id
            FROM ml__ciclos_tareas ct
            INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = ct.tabla_estado_registro_id
            LEFT JOIN ml__procedimientos_versiones pv ON pv.procedimiento_version_id = ct.procedimiento_version_id
            LEFT JOIN ml__procedimientos p ON p.procedimiento_id = pv.procedimiento_id
            WHERE ct.ciclo_tarea_id = ? AND (ct.empresa_id = ? OR ? = 1)
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $ciclo_tarea_id, $empresa_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tarea = mysqli_fetch_assoc($res);

    if (!$tarea) {
        return null;
    }

    // Predecesoras
    $sql_dep = "SELECT 
                    dep.ciclo_tarea_dependencia_id,
                    dep.ciclo_tarea_predecesora_id,
                    dep.obligatoria,
                    tp.titulo as predecesora_titulo,
                    est.tabla_estado_registro as predecesora_estado,
                    tp.tabla_estado_registro_id as predecesora_estado_id
                FROM ml__ciclos_tareas_dependencias dep
                INNER JOIN ml__ciclos_tareas tp ON tp.ciclo_tarea_id = dep.ciclo_tarea_predecesora_id
                INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = tp.tabla_estado_registro_id
                WHERE dep.ciclo_tarea_id = ?";
    $stmt_dep = mysqli_prepare($conexion, $sql_dep);
    mysqli_stmt_bind_param($stmt_dep, "i", $ciclo_tarea_id);
    mysqli_stmt_execute($stmt_dep);
    $res_dep = mysqli_stmt_get_result($stmt_dep);
    $predecesoras = [];
    while ($d = mysqli_fetch_assoc($res_dep)) {
        $predecesoras[] = $d;
    }
    $tarea['predecesoras'] = $predecesoras;

    // RACI Asignados
    $sql_raci = "SELECT 
                    ctr.ciclo_tarea_raci_id,
                    ctr.raci_rol_id,
                    ctr.usuario_id,
                    u.usuario_nombre as nombre_completo,
                    u.email,
                    DATE_FORMAT(ctr.vigente_desde, '%d/%m/%Y') as vigente_desde_fmt,
                    CASE ctr.raci_rol_id
                        WHEN 1 THEN 'R - Responsable'
                        WHEN 2 THEN 'A - Aprobador / Accountable'
                        WHEN 3 THEN 'C - Consultado'
                        WHEN 4 THEN 'I - Informado'
                    END as raci_rol_nombre
                 FROM ml__ciclos_tareas_raci ctr
                 INNER JOIN conf__usuarios u ON u.usuario_id = ctr.usuario_id
                 WHERE ctr.ciclo_tarea_id = ?
                 ORDER BY ctr.raci_rol_id ASC";
    $stmt_raci = mysqli_prepare($conexion, $sql_raci);
    mysqli_stmt_bind_param($stmt_raci, "i", $ciclo_tarea_id);
    mysqli_stmt_execute($stmt_raci);
    $res_raci = mysqli_stmt_get_result($stmt_raci);
    $raci = [];
    while ($r = mysqli_fetch_assoc($res_raci)) {
        $raci[] = $r;
    }
    $tarea['raci'] = $raci;

    // Evidencias adjuntas
    $sql_evi = "SELECT 
                    evidencia_id,
                    titulo,
                    descripcion,
                    archivo_ref,
                    hash,
                    es_comprobante,
                    DATE_FORMAT(valida_hasta, '%d/%m/%Y') as valida_hasta_fmt,
                    DATE_FORMAT(creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt
                FROM ml__evidencias
                WHERE ciclo_tarea_id = ? AND tabla_estado_registro_id IN (1, 2039)
                ORDER BY evidencia_id DESC";
    $stmt_evi = mysqli_prepare($conexion, $sql_evi);
    mysqli_stmt_bind_param($stmt_evi, "i", $ciclo_tarea_id);
    mysqli_stmt_execute($stmt_evi);
    $res_evi = mysqli_stmt_get_result($stmt_evi);
    $evidencias = [];
    while ($ev = mysqli_fetch_assoc($res_evi)) {
        $evidencias[] = $ev;
    }
    $tarea['evidencias'] = $evidencias;
    mysqli_stmt_close($stmt_evi);

    // Hilo de mensajes / conversación
    $tarea['mensajes'] = ciclos_tareas_mensajes_listar($conexion, $ciclo_tarea_id);

    return $tarea;
}

/**
 * Transición de estado con reglas de revisión y activación en cascada
 */
function ciclos_tareas_ejecutar_transicion(
    mysqli $conexion, 
    int $ciclo_tarea_id, 
    int $nuevo_estado_id, 
    int $empresa_id, 
    int $usuario_id, 
    string $observaciones = ''
): array {
    $tarea = ciclos_tareas_obtener_por_id($conexion, $ciclo_tarea_id, $empresa_id);
    if (!$tarea) {
        return ['ok' => false, 'error' => 'Tarea no encontrada.'];
    }

    $estado_actual = (int)$tarea['tabla_estado_registro_id'];

    // Si la tarea está inactiva (2077), validar si sus predecesoras obligatorias están cumplidas
    if ($estado_actual === 2077 && in_array($nuevo_estado_id, [2078, 2079, 2081], true)) {
        foreach ($tarea['predecesoras'] as $pred) {
            if ($pred['obligatoria'] == 1 && (int)$pred['predecesora_estado_id'] !== 2081) {
                return ['ok' => false, 'error' => "No se puede iniciar la tarea: la predecesora obrigaria '{$pred['predecesora_titulo']}' no está cumplida."];
            }
        }
    }

    // Si se intenta cumplir directamente y requiere revisión (Caso de flujo con Aprobador)
    if ($nuevo_estado_id === 2081 && $tarea['requiere_revision'] == 1 && $estado_actual !== 2080) {
        // Enviar a revisión
        $nuevo_estado_id = 2080; // En revisión
        $mensaje = 'Tarea enviada a revisión técnica satisfactoriamente.';
    } else {
        $mensaje = 'Estado de tarea actualizado correctamente.';
    }

    // Invocar servicio central
    $res_serv = ciclos_tarea_cambiar_estado($conexion, $ciclo_tarea_id, $nuevo_estado_id, $usuario_id, $observaciones);
    if (!$res_serv) {
        return ['ok' => false, 'error' => 'Error al persistir el nuevo estado de la tarea.'];
    }

    return ['ok' => true, 'mensaje' => $mensaje, 'nuevo_estado_id' => $nuevo_estado_id];
}

/**
 * Listar mensajes / hilo de conversación de una tarea
 */
function ciclos_tareas_mensajes_listar(mysqli $conexion, int $ciclo_tarea_id): array {
    $sql = "SELECT 
                tm.tarea_mensaje_id,
                tm.ciclo_tarea_id,
                tm.usuario_id,
                tm.mensaje,
                tm.tipo,
                DATE_FORMAT(tm.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                tm.creado_en,
                u.usuario,
                u.usuario_nombre,
                COALESCE(u.usuario_nombre, u.usuario) as autor_nombre
            FROM ml__ciclos_tareas_mensajes tm
            INNER JOIN conf__usuarios u ON u.usuario_id = tm.usuario_id
            WHERE tm.ciclo_tarea_id = ? AND tm.tabla_estado_registro_id = 1
            ORDER BY tm.creado_en ASC, tm.tarea_mensaje_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $ciclo_tarea_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Agregar mensaje al hilo de conversación de una tarea
 */
function ciclos_tareas_mensajes_agregar(mysqli $conexion, int $ciclo_tarea_id, int $usuario_id, string $mensaje, int $empresa_id): array {
    $mensaje = trim($mensaje);
    if (empty($mensaje)) {
        return ['success' => false, 'message' => 'El mensaje no puede estar vacío.'];
    }

    $sql = "INSERT INTO ml__ciclos_tareas_mensajes (ciclo_tarea_id, usuario_id, mensaje, tipo, empresa_id, creado_en)
            VALUES (?, ?, ?, 'COMENTARIO', ?, NOW())";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iisi", $ciclo_tarea_id, $usuario_id, $mensaje, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => 'Error al guardar el mensaje: ' . mysqli_error($conexion)];
    }

    return [
        'success' => true,
        'tarea_mensaje_id' => $id,
        'mensaje' => 'Mensaje publicado correctamente.'
    ];
}
