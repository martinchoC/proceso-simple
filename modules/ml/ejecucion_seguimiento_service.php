<?php
/**
 * Servicio Central: Ejecución y Seguimiento (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/ejecucion_seguimiento_service.php
 * Casos Cubiertos: C04, C05, C12, C13, C16, C17, C29, C30, C31, C34
 */

declare(strict_types=1);

require_once __DIR__ . '/ciclos_generador_service.php';

/**
 * -------------------------------------------------------------------------
 * SECCIÓN 1: GESTIÓN DE EVIDENCIAS Y COMPROBANTES (C12, C16, C30)
 * -------------------------------------------------------------------------
 */

/**
 * Registrar y almacenar archivo probatorio / evidencia con hash SHA-256
 */
function evidencias_registrar(
    mysqli $conexion,
    int $ciclo_tarea_id,
    string $titulo,
    string $descripcion,
    string $nombre_original,
    string $ruta_temporal,
    int $es_comprobante,
    ?string $valida_hasta,
    ?string $retener_hasta,
    int $confidencial,
    int $empresa_id,
    int $usuario_id
): array {
    // Validar tarea
    $sql_t = "SELECT ct.ciclo_tarea_id, ct.ciclo_id, ct.empresa_id, ct.tabla_estado_registro_id,
                     o.retencion_evidencias_meses, o.codigo as obligacion_codigo
              FROM ml__ciclos_tareas ct
              INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
              INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
              INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
              WHERE ct.ciclo_tarea_id = ? AND ct.empresa_id = ?
              LIMIT 1";
    $stmt_t = mysqli_prepare($conexion, $sql_t);
    mysqli_stmt_bind_param($stmt_t, "ii", $ciclo_tarea_id, $empresa_id);
    mysqli_stmt_execute($stmt_t);
    $res_t = mysqli_stmt_get_result($stmt_t);
    $tarea = mysqli_fetch_assoc($res_t);
    mysqli_stmt_close($stmt_t);

    if (!$tarea) {
        return ['success' => false, 'message' => 'Tarea operativa no encontrada o no pertenece a la empresa.'];
    }

    if (!file_exists($ruta_temporal)) {
        return ['success' => false, 'message' => 'Archivo temporal no encontrado para procesamiento.'];
    }

    // Calcular hash SHA-256 de integridad (Caso C12)
    $hash_sha256 = hash_file('sha256', $ruta_temporal);

    // Definir directorio destino: uploads/ml_evidencias/{empresa_id}/
    $base_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ml_evidencias' . DIRECTORY_SEPARATOR . $empresa_id;
    if (!is_dir($base_dir)) {
        @mkdir($base_dir, 0755, true);
    }

    $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
    $nombre_guardado = 'evi_' . $ciclo_tarea_id . '_' . time() . '_' . substr($hash_sha256, 0, 8) . ($extension ? '.' . $extension : '');
    $destino_final = $base_dir . DIRECTORY_SEPARATOR . $nombre_guardado;
    $archivo_ref = 'uploads/ml_evidencias/' . $empresa_id . '/' . $nombre_guardado;

    if (!@copy($ruta_temporal, $destino_final)) {
        return ['success' => false, 'message' => 'Error al guardar el archivo físico en el servidor.'];
    }

    // Si no se especificó retención y la obligación define retención de evidencias (ej: 60 meses), calcular automáticamente
    if (empty($retener_hasta) && !empty($tarea['retencion_evidencias_meses'])) {
        $meses = (int)$tarea['retencion_evidencias_meses'];
        $dt_ret = new DateTime();
        $dt_ret->modify("+{$meses} months");
        $retener_hasta = $dt_ret->format('Y-m-d');
    }

    $valida_hasta_db = !empty($valida_hasta) ? $valida_hasta : null;
    $retener_hasta_db = !empty($retener_hasta) ? $retener_hasta : null;

    $sql_ins = "INSERT INTO ml__evidencias 
                (ciclo_tarea_id, titulo, descripcion, archivo_ref, hash, es_comprobante, 
                 valida_hasta, retener_hasta, confidencial, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql_ins);
    mysqli_stmt_bind_param($stmt_ins, "issssissiii", 
        $ciclo_tarea_id, $titulo, $descripcion, $archivo_ref, $hash_sha256, 
        $es_comprobante, $valida_hasta_db, $retener_hasta_db, $confidencial, $empresa_id, $usuario_id
    );
    $ok = mysqli_stmt_execute($stmt_ins);
    $evidencia_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_ins);

    if (!$ok || $evidencia_id <= 0) {
        @unlink($destino_final);
        return ['success' => false, 'message' => 'Error al registrar la evidencia en base de datos.'];
    }

    return [
        'success' => true,
        'evidencia_id' => $evidencia_id,
        'hash' => $hash_sha256,
        'archivo_ref' => $archivo_ref,
        'valida_hasta' => $valida_hasta_db,
        'retener_hasta' => $retener_hasta_db,
        'message' => "Evidencia #{$evidencia_id} cargada exitosamente con firma digital SHA-256."
    ];
}

/**
 * Anular evidencia
 */
function evidencias_anular(mysqli $conexion, int $evidencia_id, string $motivo, int $empresa_id, int $usuario_id): array {
    $obs = "\n[Anulada por usuario #{$usuario_id}] Motivo: {$motivo}";
    $sql = "UPDATE ml__evidencias 
            SET tabla_estado_registro_id = 2,
                descripcion = CONCAT(COALESCE(descripcion, ''), ?),
                actualizado_en = CURRENT_TIMESTAMP,
                actualizado_por = ?
            WHERE evidencia_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "siii", $obs, $usuario_id, $evidencia_id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Evidencia anulada con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al anular la evidencia.'];
}

/**
 * -------------------------------------------------------------------------
 * SECCIÓN 2: HALLAZGOS E INCIDENCIAS DE AUDITORÍA (C13, C29)
 * -------------------------------------------------------------------------
 */

/**
 * Registrar incidencia u observación de auditoría
 */
function incidencias_registrar(
    mysqli $conexion, 
    int $ciclo_id, 
    string $descripcion, 
    int $empresa_id, 
    int $usuario_id
): array {
    // Validar ciclo
    $res_c = mysqli_query($conexion, "SELECT ciclo_id, empresa_id FROM ml__ciclos WHERE ciclo_id = {$ciclo_id} AND empresa_id = {$empresa_id} LIMIT 1");
    if (!mysqli_fetch_assoc($res_c)) {
        return ['success' => false, 'message' => 'Ciclo operativo no encontrado o no pertenece a la empresa.'];
    }

    $ahora = date('Y-m-d H:i:s');
    $sql = "INSERT INTO ml__incidencias 
            (ciclo_id, descripcion, abierta_en, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
            VALUES (?, ?, ?, 1, ?, CURRENT_TIMESTAMP, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "issii", $ciclo_id, $descripcion, $ahora, $empresa_id, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $incidencia_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok && $incidencia_id > 0) {
        return [
            'success' => true,
            'incidencia_id' => $incidencia_id,
            'message' => "Incidencia #{$incidencia_id} registrada con éxito."
        ];
    }

    return ['success' => false, 'message' => 'Error al registrar la incidencia.'];
}

/**
 * Vincular plan de acción y generar ciclo de seguimiento para la incidencia (Caso C29)
 */
function incidencias_vincular_seguimiento(
    mysqli $conexion, 
    int $incidencia_id, 
    string $plan_accion, 
    int $empresa_id, 
    int $usuario_id
): array {
    $sql = "SELECT i.*, c.ciclo_id 
            FROM ml__incidencias i
            INNER JOIN ml__ciclos c ON c.ciclo_id = i.ciclo_id
            WHERE i.incidencia_id = ? AND i.empresa_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $incidencia_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $inc = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$inc) {
        return ['success' => false, 'message' => 'Incidencia no encontrada.'];
    }

    // Crear ciclo de seguimiento
    $res_seg = ciclos_crear_seguimiento($conexion, (int)$inc['ciclo_id'], $plan_accion, $usuario_id);
    if (!$res_seg['success']) {
        return $res_seg;
    }

    $nuevo_ciclo_id = (int)$res_seg['ciclo_id'];

    // Actualizar incidencia
    $sql_up = "UPDATE ml__incidencias 
               SET ciclo_seguimiento_id = ?,
                   actualizado_en = CURRENT_TIMESTAMP,
                   actualizado_por = ?
               WHERE incidencia_id = ?";
    $stmt_up = mysqli_prepare($conexion, $sql_up);
    mysqli_stmt_bind_param($stmt_up, "iii", $nuevo_ciclo_id, $usuario_id, $incidencia_id);
    mysqli_stmt_execute($stmt_up);
    mysqli_stmt_close($stmt_up);

    return [
        'success' => true,
        'ciclo_seguimiento_id' => $nuevo_ciclo_id,
        'message' => "Ciclo de seguimiento #{$nuevo_ciclo_id} generado y vinculado exitosamente a la Incidencia #{$incidencia_id}."
    ];
}

/**
 * -------------------------------------------------------------------------
 * SECCIÓN 3: CONSOLA DE ALERTAS PREVENTIVAS Y NOTIFICACIONES (C04, C05)
 * -------------------------------------------------------------------------
 */

/**
 * Generar alertas preventivas automáticas para tareas y evidencias próximas a vencer
 */
function alertas_generar_preventivas(mysqli $conexion, int $empresa_id): array {
    $alertas_generadas = 0;
    $hoy = date('Y-m-d H:i:s');

    // 1. Alertas por tareas operativas próximas a vencer (dentro de los próximos 7 días)
    $sql_t = "SELECT ct.ciclo_tarea_id, ct.ciclo_id, ct.titulo, ct.fecha_vencimiento,
                     ctr.usuario_id
              FROM ml__ciclos_tareas ct
              INNER JOIN ml__ciclos_tareas_raci ctr ON ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1
              WHERE ct.empresa_id = {$empresa_id}
                AND ct.tabla_estado_registro_id IN (2078, 2079) -- Pendientes o En curso
                AND ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM ml__alertas a 
                    WHERE a.ciclo_tarea_id = ct.ciclo_tarea_id 
                      AND a.usuario_id = ctr.usuario_id 
                      AND DATE(a.programada_para) = CURRENT_DATE
                )";
    $res_t = mysqli_query($conexion, $sql_t);
    if ($res_t) {
        while ($t = mysqli_fetch_assoc($res_t)) {
            $sql_ins = "INSERT INTO ml__alertas 
                        (ciclo_id, ciclo_tarea_id, usuario_id, canal, programada_para, tabla_estado_registro_id, empresa_id, creado_en)
                        VALUES (?, ?, ?, 'SISTEMA', NOW(), 1, ?, CURRENT_TIMESTAMP)";
            $stmt = mysqli_prepare($conexion, $sql_ins);
            mysqli_stmt_bind_param($stmt, "iiii", $t['ciclo_id'], $t['ciclo_tarea_id'], $t['usuario_id'], $empresa_id);
            if (mysqli_stmt_execute($stmt)) {
                $alertas_generadas++;
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 2. Alertas por evidencias / permisos con fecha de validez próxima a expirar (dentro de 30 días - Caso C30)
    $sql_evi = "SELECT e.evidencia_id, e.ciclo_tarea_id, e.titulo, e.valida_hasta,
                       ct.ciclo_id, ctr.usuario_id
                FROM ml__evidencias e
                INNER JOIN ml__ciclos_tareas ct ON ct.ciclo_tarea_id = e.ciclo_tarea_id
                INNER JOIN ml__ciclos_tareas_raci ctr ON ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1
                WHERE e.empresa_id = {$empresa_id}
                  AND e.tabla_estado_registro_id = 1
                  AND e.valida_hasta IS NOT NULL
                  AND e.valida_hasta BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
                  AND NOT EXISTS (
                      SELECT 1 FROM ml__alertas a 
                      WHERE a.evidencia_id = e.evidencia_id 
                        AND a.usuario_id = ctr.usuario_id
                        AND DATE(a.programada_para) = CURRENT_DATE
                  )";
    $res_evi = mysqli_query($conexion, $sql_evi);
    if ($res_evi) {
        while ($ev = mysqli_fetch_assoc($res_evi)) {
            $sql_ins = "INSERT INTO ml__alertas 
                        (ciclo_id, ciclo_tarea_id, evidencia_id, usuario_id, canal, programada_para, tabla_estado_registro_id, empresa_id, creado_en)
                        VALUES (?, ?, ?, ?, 'EMAIL', NOW(), 1, ?, CURRENT_TIMESTAMP)";
            $stmt = mysqli_prepare($conexion, $sql_ins);
            mysqli_stmt_bind_param($stmt, "iiiii", $ev['ciclo_id'], $ev['ciclo_tarea_id'], $ev['evidencia_id'], $ev['usuario_id'], $empresa_id);
            if (mysqli_stmt_execute($stmt)) {
                $alertas_generadas++;
            }
            mysqli_stmt_close($stmt);
        }
    }

    return [
        'success' => true,
        'alertas_generadas' => $alertas_generadas,
        'message' => "Proceso de alertas ejecutado: {$alertas_generadas} nuevas notificaciones programadas."
    ];
}

/**
 * Procesar cola de alertas pendientes (simular o despachar notificación)
 */
function alertas_procesar_cola(mysqli $conexion, int $empresa_id): array {
    $sql = "UPDATE ml__alertas 
            SET enviada_en = CURRENT_TIMESTAMP, 
                tabla_estado_registro_id = 2 -- Enviada / Leída
            WHERE empresa_id = {$empresa_id} 
              AND enviada_en IS NULL 
              AND programada_para <= NOW()";
    $res = mysqli_query($conexion, $sql);
    $cant = mysqli_affected_rows($conexion);

    return [
        'success' => true,
        'alertas_despachadas' => max(0, $cant),
        'message' => "Se despacharon {$cant} alertas pendientes por canal correspondiente."
    ];
}

/**
 * -------------------------------------------------------------------------
 * SECCIÓN 4: TABLERO DE INDICADORES DE CUMPLIMIENTO Y CIERRES (C34)
 * -------------------------------------------------------------------------
 */

/**
 * Calcular y consolidar indicadores de cumplimiento para un período de cierre
 */
function indicadores_calcular_cierre(
    mysqli $conexion, 
    int $empresa_id, 
    string $periodo_desde, 
    string $periodo_hasta, 
    int $usuario_id
): array {
    $pd = mysqli_real_escape_string($conexion, $periodo_desde);
    $ph = mysqli_real_escape_string($conexion, $periodo_hasta);

    // Obtener grupo_id de la empresa
    $res_eg = mysqli_query($conexion, "SELECT grupo_id FROM ml__empresas_grupo WHERE empresa_id = {$empresa_id} LIMIT 1");
    $r_eg = mysqli_fetch_assoc($res_eg);
    $grupo_id = $r_eg ? (int)$r_eg['grupo_id'] : 1;

    // Métricas de ciclos que vencen en el período
    $sql = "SELECT 
                COUNT(*) as total_ciclos,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2072 THEN 1 ELSE 0 END) as cumplidos_tiempo,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2073 THEN 1 ELSE 0 END) as cumplidos_plazo,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2074 THEN 1 ELSE 0 END) as incumplidos,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2075 THEN 1 ELSE 0 END) as eximidos,
                SUM(CASE WHEN c.tabla_estado_registro_id IN (2069, 2070, 2071) THEN 1 ELSE 0 END) as abiertos_por_vencer,
                -- Ponderación por criticidad
                SUM(CASE WHEN c.tabla_estado_registro_id = 2072 THEN COALESCE(crit.peso, 1) ELSE 0 END) as puntos_obtenidos,
                SUM(COALESCE(crit.peso, 1)) as puntos_totales
            FROM ml__ciclos c
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__criticidades crit ON crit.criticidad_id = o.criticidad_id
            WHERE c.empresa_id = {$empresa_id}
              AND c.fecha_vencimiento BETWEEN '{$pd} 00:00:00' AND '{$ph} 23:59:59'";

    $res = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($res);

    $total = (int)($row['total_ciclos'] ?? 0);
    $tiempo = (int)($row['cumplidos_tiempo'] ?? 0);
    $plazo = (int)($row['cumplidos_plazo'] ?? 0);
    $incumplidos = (int)($row['incumplidos'] ?? 0);
    $eximidos = (int)($row['eximidos'] ?? 0);
    $por_vencer = (int)($row['abiertos_por_vencer'] ?? 0);

    $pts_obt = (float)($row['puntos_obtenidos'] ?? 0);
    $pts_tot = (float)($row['puntos_totales'] ?? 0);

    $porcentaje_cumplimiento = $total > 0 ? round((($tiempo + $plazo) / $total) * 100, 2) : 100.0;
    $porcentaje_a_tiempo = $total > 0 ? round(($tiempo / $total) * 100, 2) : 100.0;
    $porcentaje_ponderado = $pts_tot > 0 ? round(($pts_obt / $pts_tot) * 100, 2) : 100.0;

    // Registrar o actualizar en ml__indicadores_cierres
    $ahora = date('Y-m-d H:i:s');
    $sql_ins = "INSERT INTO ml__indicadores_cierres 
                (grupo_id, periodo_desde, periodo_hasta, empresa_id, ciclos_contados,
                 cumplidos_a_tiempo, cumplidos_fuera_plazo, incumplidos, eximidos, por_vencer,
                 calculado_en, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";
    $stmt = mysqli_prepare($conexion, $sql_ins);
    mysqli_stmt_bind_param($stmt, "issiiiiiiisi", 
        $grupo_id, $periodo_desde, $periodo_hasta, $empresa_id, $total,
        $tiempo, $plazo, $incumplidos, $eximidos, $por_vencer, $ahora, $usuario_id
    );
    mysqli_stmt_execute($stmt);
    $indicador_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return [
        'success' => true,
        'indicador_cierre_id' => $indicador_id,
        'total_ciclos' => $total,
        'cumplidos_a_tiempo' => $tiempo,
        'cumplidos_fuera_plazo' => $plazo,
        'incumplidos' => $incumplidos,
        'eximidos' => $eximidos,
        'por_vencer' => $por_vencer,
        'porcentaje_cumplimiento' => $porcentaje_cumplimiento,
        'porcentaje_a_tiempo' => $porcentaje_a_tiempo,
        'porcentaje_ponderado' => $porcentaje_ponderado,
        'calculado_en' => $ahora,
        'message' => "Indicadores calculados con éxito para el período {$periodo_desde} al {$periodo_hasta}."
    ];
}
