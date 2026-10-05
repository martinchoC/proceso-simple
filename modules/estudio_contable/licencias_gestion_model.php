<?php
/**
 * Modelo de Datos: Wizard de Licencias y Delegación Automática de Tareas
 * Módulo 4: Estudio Contable (licencias_gestion_model.php)
 * Tablas: estudio__licencias (421), estudio__licencias_delegaciones (422), estudio__tareas (419)
 */

declare(strict_types=1);

/**
 * Combos para formulario de licencias
 */
function licencias_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'usuarios' => [],
        'tipos' => ['VACACIONES', 'MEDICA', 'ESTUDIO', 'OTRA'],
        'estados' => []
    ];

    $res_u = mysqli_query($conexion, "SELECT usuario_id, usuario, usuario_nombre FROM conf__usuarios WHERE tabla_estado_registro_id = 1 ORDER BY usuario_nombre");
    while ($r = mysqli_fetch_assoc($res_u)) {
        $combos['usuarios'][] = $r;
    }

    $res_e = mysqli_query($conexion, "SELECT estado_registro_id, tabla_estado_registro FROM conf__tablas_estados_registros WHERE tabla_id = 421 ORDER BY orden");
    while ($r = mysqli_fetch_assoc($res_e)) {
        $combos['estados'][] = $r;
    }

    return $combos;
}

/**
 * Listar licencias registradas
 */
function licencias_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $sql = "SELECT l.licencia_id, l.empresa_id, l.usuario_id, l.tipo_licencia,
                   l.f_desde, l.f_hasta, l.usuario_suplente_r_id, l.usuario_suplente_a_id,
                   l.observaciones, l.tabla_estado_registro_id, l.created_at,
                   u.usuario_nombre AS titular_nombre, u.usuario AS titular_login,
                   sr.usuario_nombre AS suplente_r_nombre,
                   sa.usuario_nombre AS suplente_a_nombre,
                   ter.tabla_estado_registro,
                   (SELECT COUNT(*) FROM estudio__licencias_delegaciones ld WHERE ld.licencia_id = l.licencia_id AND ld.empresa_id = l.empresa_id) AS cant_delegaciones
            FROM estudio__licencias l
            INNER JOIN conf__usuarios u ON u.usuario_id = l.usuario_id
            LEFT JOIN conf__usuarios sr ON sr.usuario_id = l.usuario_suplente_r_id
            LEFT JOIN conf__usuarios sa ON sa.usuario_id = l.usuario_suplente_a_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 421 AND ter.estado_registro_id = l.tabla_estado_registro_id
            WHERE l.empresa_id = ?";

    $params = [$empresa_id];
    $types = "i";

    if (!empty($filtros['usuario_id'])) {
        $sql .= " AND l.usuario_id = ?";
        $params[] = intval($filtros['usuario_id']);
        $types .= "i";
    }

    if (!empty($filtros['tipo_licencia'])) {
        $sql .= " AND l.tipo_licencia = ?";
        $params[] = trim($filtros['tipo_licencia']);
        $types .= "s";
    }

    $sql .= " ORDER BY l.f_desde DESC, l.licencia_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
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
 * Previsualizar tareas afectadas por una eventual licencia
 */
function licencias_preview_tareas_afectadas(mysqli $conexion, int $empresa_id, int $usuario_id, string $f_desde, string $f_hasta): array
{
    $sql = "SELECT t.tarea_id, t.titulo, t.f_limite, t.usuario_r_id, t.usuario_a_id,
                   c.razon_social AS cliente_nombre,
                   CASE 
                        WHEN t.usuario_r_id = ? AND t.usuario_a_id = ? THEN 'R y A'
                        WHEN t.usuario_r_id = ? THEN 'R'
                        ELSE 'A'
                   END AS rol_afectado
            FROM estudio__tareas t
            INNER JOIN estudio__clientes c ON c.cliente_id = t.cliente_id
            WHERE t.empresa_id = ?
              AND t.tabla_estado_registro_id IN (4, 5, 6)
              AND (t.usuario_r_id = ? OR t.usuario_a_id = ?)
              AND t.f_limite >= ? AND t.f_inicio <= ?
            ORDER BY t.f_limite ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiiiiiss", $usuario_id, $usuario_id, $usuario_id, $empresa_id, $usuario_id, $usuario_id, $f_desde, $f_hasta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tareas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $tareas[] = $r;
    }
    mysqli_stmt_close($stmt);

    return $tareas;
}

/**
 * Registrar licencia y delegar tareas operativas automáticamente
 */
function licencia_registrar_y_delegar(mysqli $conexion, int $empresa_id, array $datos): array
{
    $usuario_id = intval($datos['usuario_id'] ?? 0);
    $tipo_licencia = trim($datos['tipo_licencia'] ?? 'VACACIONES');
    $f_desde = trim($datos['f_desde'] ?? '');
    $f_hasta = trim($datos['f_hasta'] ?? '');
    $suplente_r_id = !empty($datos['usuario_suplente_r_id']) ? intval($datos['usuario_suplente_r_id']) : null;
    $suplente_a_id = !empty($datos['usuario_suplente_a_id']) ? intval($datos['usuario_suplente_a_id']) : null;
    $observaciones = trim($datos['observaciones'] ?? '');

    if ($usuario_id <= 0 || empty($f_desde) || empty($f_hasta)) {
        return ['success' => false, 'message' => 'El titular y las fechas desde/hasta son requeridos.', 'data' => null, 'error' => 'VALIDATION'];
    }

    if ($f_desde > $f_hasta) {
        return ['success' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha de finalización.', 'data' => null, 'error' => 'INVALID_DATES'];
    }

    mysqli_begin_transaction($conexion);

    try {
        // 1. Insertar licencia
        $sql = "INSERT INTO estudio__licencias (
                    empresa_id, usuario_id, tipo_licencia, f_desde, f_hasta,
                    usuario_suplente_r_id, usuario_suplente_a_id, observaciones,
                    tabla_estado_registro_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iisssiis", $empresa_id, $usuario_id, $tipo_licencia, $f_desde, $f_hasta, $suplente_r_id, $suplente_a_id, $observaciones);
        mysqli_stmt_execute($stmt);
        $licencia_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        // 2. Buscar tareas afectadas activas
        $afectadas = licencias_preview_tareas_afectadas($conexion, $empresa_id, $usuario_id, $f_desde, $f_hasta);
        $delegaciones_creadas = 0;

        foreach ($afectadas as $t) {
            $tarea_id = intval($t['tarea_id']);

            // Traspaso de rol R
            if (intval($t['usuario_r_id']) === $usuario_id && $suplente_r_id && $suplente_r_id !== $usuario_id) {
                // Registrar log de delegación
                $sql_d = "INSERT INTO estudio__licencias_delegaciones (
                            empresa_id, licencia_id, tarea_id, rol_delegado,
                            usuario_titular_id, usuario_suplente_id, f_traspaso, tabla_estado_registro_id
                          ) VALUES (?, ?, ?, 'R', ?, ?, NOW(), 1)";
                $stmt_d = mysqli_prepare($conexion, $sql_d);
                mysqli_stmt_bind_param($stmt_d, "iiiiii", $empresa_id, $licencia_id, $tarea_id, $usuario_id, $suplente_r_id);
                mysqli_stmt_execute($stmt_d);
                mysqli_stmt_close($stmt_d);

                // Reasignar titular en estudio__tareas
                $sql_ut = "UPDATE estudio__tareas SET usuario_r_id = ? WHERE empresa_id = ? AND tarea_id = ?";
                $stmt_ut = mysqli_prepare($conexion, $sql_ut);
                mysqli_stmt_bind_param($stmt_ut, "iii", $suplente_r_id, $empresa_id, $tarea_id);
                mysqli_stmt_execute($stmt_ut);
                mysqli_stmt_close($stmt_ut);

                $delegaciones_creadas++;
            }

            // Traspaso de rol A
            if (intval($t['usuario_a_id']) === $usuario_id && $suplente_a_id && $suplente_a_id !== $usuario_id) {
                $sql_d = "INSERT INTO estudio__licencias_delegaciones (
                            empresa_id, licencia_id, tarea_id, rol_delegado,
                            usuario_titular_id, usuario_suplente_id, f_traspaso, tabla_estado_registro_id
                          ) VALUES (?, ?, ?, 'A', ?, ?, NOW(), 1)";
                $stmt_d = mysqli_prepare($conexion, $sql_d);
                mysqli_stmt_bind_param($stmt_d, "iiiiii", $empresa_id, $licencia_id, $tarea_id, $usuario_id, $suplente_a_id);
                mysqli_stmt_execute($stmt_d);
                mysqli_stmt_close($stmt_d);

                $sql_ut = "UPDATE estudio__tareas SET usuario_a_id = ? WHERE empresa_id = ? AND tarea_id = ?";
                $stmt_ut = mysqli_prepare($conexion, $sql_ut);
                mysqli_stmt_bind_param($stmt_ut, "iii", $suplente_a_id, $empresa_id, $tarea_id);
                mysqli_stmt_execute($stmt_ut);
                mysqli_stmt_close($stmt_ut);

                $delegaciones_creadas++;
            }
        }

        mysqli_commit($conexion);
        return [
            'success' => true,
            'message' => "Licencia registrada exitosamente. Se delegaron {$delegaciones_creadas} asignaciones de tareas.",
            'data' => [
                'licencia_id' => $licencia_id,
                'delegaciones_creadas' => $delegaciones_creadas
            ],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al registrar licencia: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Reversión automática de delegaciones (al regresar el titular o cancelar licencia)
 */
function licencia_revertir_delegaciones(mysqli $conexion, int $empresa_id, int $licencia_id): array
{
    mysqli_begin_transaction($conexion);
    try {
        // Obtener delegaciones activas
        $sql = "SELECT delegacion_id, tarea_id, rol_delegado, usuario_titular_id, usuario_suplente_id
                FROM estudio__licencias_delegaciones
                WHERE empresa_id = ? AND licencia_id = ? AND tabla_estado_registro_id = 1
                FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $licencia_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $delegaciones = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $delegaciones[] = $r;
        }
        mysqli_stmt_close($stmt);

        $revertidas = 0;
        foreach ($delegaciones as $d) {
            $tarea_id = intval($d['tarea_id']);
            $titular_id = intval($d['usuario_titular_id']);
            $rol = $d['rol_delegado'];

            if ($rol === 'R') {
                $sql_u = "UPDATE estudio__tareas SET usuario_r_id = ? WHERE empresa_id = ? AND tarea_id = ?";
            } else {
                $sql_u = "UPDATE estudio__tareas SET usuario_a_id = ? WHERE empresa_id = ? AND tarea_id = ?";
            }
            $stmt_u = mysqli_prepare($conexion, $sql_u);
            mysqli_stmt_bind_param($stmt_u, "iii", $titular_id, $empresa_id, $tarea_id);
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_close($stmt_u);

            // Marcar delegación como finalizada / revertida
            $sql_d = "UPDATE estudio__licencias_delegaciones SET tabla_estado_registro_id = 2 WHERE delegacion_id = ?";
            $stmt_d = mysqli_prepare($conexion, $sql_d);
            mysqli_stmt_bind_param($stmt_d, "i", $d['delegacion_id']);
            mysqli_stmt_execute($stmt_d);
            mysqli_stmt_close($stmt_d);

            $revertidas++;
        }

        // Marcar licencia como finalizada
        $sql_lic = "UPDATE estudio__licencias SET tabla_estado_registro_id = 2 WHERE empresa_id = ? AND licencia_id = ?";
        $stmt_lic = mysqli_prepare($conexion, $sql_lic);
        mysqli_stmt_bind_param($stmt_lic, "ii", $empresa_id, $licencia_id);
        mysqli_stmt_execute($stmt_lic);
        mysqli_stmt_close($stmt_lic);

        mysqli_commit($conexion);
        return [
            'success' => true,
            'message' => "Licencia finalizada y {$revertidas} delegaciones revertidas con éxito.",
            'data' => ['revertidas' => $revertidas],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}
