<?php
/**
 * Modelo de Datos: Matriz RACI y Time Tracking de Tareas
 * Módulo 4: Estudio Contable (tareas_raci_model.php)
 * Tablas: estudio__tareas (419, Proceso Largo Tipo 3), estudio__tareas_tiempos (420)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/formato.php';

/**
 * Combos para filtros y carga de tareas
 */
function tareas_raci_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'usuarios' => [],
        'clientes' => [],
        'vencimientos' => [],
        'estados' => []
    ];

    // Usuarios del sistema
    $res_u = mysqli_query($conexion, "SELECT usuario_id, usuario, usuario_nombre FROM conf__usuarios WHERE tabla_estado_registro_id = 1 ORDER BY usuario_nombre");
    while ($r = mysqli_fetch_assoc($res_u)) {
        $combos['usuarios'][] = $r;
    }

    // Clientes
    $sql_c = "SELECT cliente_id, razon_social, cuit FROM estudio__clientes WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY razon_social";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "i", $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    while ($r = mysqli_fetch_assoc($res_c)) {
        $combos['clientes'][] = $r;
    }
    mysqli_stmt_close($stmt_c);

    // Estados de tabla 419, ids genéricos (Pendiente 17, En Proceso 18, En Revisión 19, Finalizada 21, Cancelada 26)
    $res_e = mysqli_query($conexion, "SELECT estado_registro_id, tabla_estado_registro, color_id FROM conf__tablas_estados_registros WHERE tabla_id = 419 ORDER BY orden");
    while ($r = mysqli_fetch_assoc($res_e)) {
        $combos['estados'][] = $r;
    }

    return $combos;
}

/**
 * Listar tareas con filtros de matriz RACI
 */
function tareas_raci_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $sql = "SELECT t.tarea_id, t.empresa_id, t.cliente_id, t.vencimiento_id, t.titulo,
                   t.descripcion, t.f_inicio, t.f_limite, t.usuario_r_id, t.usuario_a_id,
                   t.contactos_c, t.contactos_i, t.horas_estimadas, t.horas_reales,
                   t.tabla_estado_registro_id, t.created_at,
                   c.razon_social AS cliente_nombre, c.cuit AS cliente_cuit,
                   ur.usuario_nombre AS responsable_r_nombre, ur.usuario AS responsable_r_login,
                   ua.usuario_nombre AS supervisor_a_nombre, ua.usuario AS supervisor_a_login,
                   ter.tabla_estado_registro,
                   DATEDIFF(t.f_limite, CURDATE()) AS dias_restantes
            FROM estudio__tareas t
            INNER JOIN estudio__clientes c ON c.cliente_id = t.cliente_id
            INNER JOIN conf__usuarios ur ON ur.usuario_id = t.usuario_r_id
            INNER JOIN conf__usuarios ua ON ua.usuario_id = t.usuario_a_id
            LEFT JOIN conf__tablas_estados_registros ter 
                ON ter.tabla_id = 419 AND ter.estado_registro_id = t.tabla_estado_registro_id
            WHERE t.empresa_id = ?";

    $params = [$empresa_id];
    $types = "i";

    if (!empty($filtros['usuario_r_id'])) {
        $sql .= " AND t.usuario_r_id = ?";
        $params[] = intval($filtros['usuario_r_id']);
        $types .= "i";
    }

    if (!empty($filtros['usuario_a_id'])) {
        $sql .= " AND t.usuario_a_id = ?";
        $params[] = intval($filtros['usuario_a_id']);
        $types .= "i";
    }

    if (!empty($filtros['cliente_id'])) {
        $sql .= " AND t.cliente_id = ?";
        $params[] = intval($filtros['cliente_id']);
        $types .= "i";
    }

    if (!empty($filtros['tabla_estado_registro_id'])) {
        $sql .= " AND t.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY t.f_limite ASC, t.tarea_id DESC";

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
 * Guardar o actualizar tarea con los 8 campos universales de proceso
 */
function tarea_raci_guardar(mysqli $conexion, int $empresa_id, array $datos, int $usuario_creador_id): array
{
    $tarea_id = intval($datos['tarea_id'] ?? 0);
    $cliente_id = intval($datos['cliente_id'] ?? 0);
    $vencimiento_id = !empty($datos['vencimiento_id']) ? intval($datos['vencimiento_id']) : null;
    $titulo = trim($datos['titulo'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $f_inicio = !empty($datos['f_inicio']) ? $datos['f_inicio'] : date('Y-m-d');
    $f_limite = !empty($datos['f_limite']) ? $datos['f_limite'] : date('Y-m-d', strtotime('+7 days'));
    $usuario_r_id = intval($datos['usuario_r_id'] ?? 0);
    $usuario_a_id = intval($datos['usuario_a_id'] ?? 0);
    $contactos_c = trim($datos['contactos_c'] ?? '');
    $contactos_i = trim($datos['contactos_i'] ?? '');
    $horas_estimadas = floatval($datos['horas_estimadas'] ?? 1.0);
    $tabla_estado_registro_id = intval($datos['tabla_estado_registro_id'] ?? 17);

    if ($cliente_id <= 0 || empty($titulo) || $usuario_r_id <= 0 || $usuario_a_id <= 0) {
        return ['success' => false, 'message' => 'Cliente, título y usuarios R y A son obligatorios.', 'data' => null, 'error' => 'VALIDATION'];
    }

    mysqli_begin_transaction($conexion);

    try {
        if ($tarea_id > 0) {
            $sql = "UPDATE estudio__tareas SET
                        cliente_id = ?, vencimiento_id = ?, titulo = ?, descripcion = ?,
                        f_inicio = ?, f_limite = ?, usuario_r_id = ?, usuario_a_id = ?,
                        contactos_c = ?, contactos_i = ?, horas_estimadas = ?,
                        tabla_estado_registro_id = ?
                    WHERE empresa_id = ? AND tarea_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iissssiissdiii",
                $cliente_id,
                $vencimiento_id,
                $titulo,
                $descripcion,
                $f_inicio,
                $f_limite,
                $usuario_r_id,
                $usuario_a_id,
                $contactos_c,
                $contactos_i,
                $horas_estimadas,
                $tabla_estado_registro_id,
                $empresa_id,
                $tarea_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            // Inserción con los 8 campos de proceso universales:
            // empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro, comprobante_id, entidad_id, f_emision
            $sql = "INSERT INTO estudio__tareas (
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv,
                        comprobante_nro, comprobante_id, entidad_id, f_emision,
                        cliente_id, vencimiento_id, titulo, descripcion,
                        f_inicio, f_limite, usuario_r_id, usuario_a_id,
                        contactos_c, contactos_i, horas_estimadas, horas_reales,
                        tabla_estado_registro_id
                    ) VALUES (
                        ?, 1, 0, 0,
                        0, NULL, 0, CURDATE(),
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, 0.00,
                        ?
                    )";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iiissssiissdi",
                $empresa_id,
                $cliente_id,
                $vencimiento_id,
                $titulo,
                $descripcion,
                $f_inicio,
                $f_limite,
                $usuario_r_id,
                $usuario_a_id,
                $contactos_c,
                $contactos_i,
                $horas_estimadas,
                $tabla_estado_registro_id
            );
            mysqli_stmt_execute($stmt);
            $tarea_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Tarea RACI guardada exitosamente.', 'data' => ['tarea_id' => $tarea_id], 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Transición de estado de tarea (17 Pendiente -> 18 En Proceso -> 19 En Revisión -> 21 Finalizada)
 */
function tarea_raci_cambiar_estado(mysqli $conexion, int $empresa_id, int $tarea_id, int $nuevo_estado_id): array
{
    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE estudio__tareas SET tabla_estado_registro_id = ? WHERE empresa_id = ? AND tarea_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado_id, $empresa_id, $tarea_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado de tarea actualizado.', 'data' => null, 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Registrar tiempo dedicado (Time Tracking)
 */
function tarea_tiempo_registrar(mysqli $conexion, int $empresa_id, int $tarea_id, int $usuario_id, int $minutos, string $descripcion): array
{
    if ($tarea_id <= 0 || $minutos <= 0) {
        return ['success' => false, 'message' => 'Tarea y cantidad de minutos válida (> 0) son requeridos.', 'data' => null, 'error' => 'VALIDATION'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sql = "INSERT INTO estudio__tareas_tiempos (empresa_id, tarea_id, usuario_id, f_registro, minutos, descripcion)
                VALUES (?, ?, ?, CURDATE(), ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iiiis", $empresa_id, $tarea_id, $usuario_id, $minutos, $descripcion);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Recalcular horas_reales de la tarea
        $sql_tot = "SELECT COALESCE(SUM(minutos), 0) / 60.0 AS total_horas FROM estudio__tareas_tiempos WHERE tarea_id = ?";
        $stmt_t = mysqli_prepare($conexion, $sql_tot);
        mysqli_stmt_bind_param($stmt_t, "i", $tarea_id);
        mysqli_stmt_execute($stmt_t);
        $res_t = mysqli_stmt_get_result($stmt_t);
        $row_t = mysqli_fetch_assoc($res_t);
        $total_horas = floatval($row_t['total_horas'] ?? 0.0);
        mysqli_stmt_close($stmt_t);

        $sql_up = "UPDATE estudio__tareas SET horas_reales = ? WHERE empresa_id = ? AND tarea_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_up);
        mysqli_stmt_bind_param($stmt_u, "dii", $total_horas, $empresa_id, $tarea_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => "Se registraron {$minutos} minutos. Horas acumuladas: " . fmt_numero($total_horas, 2), 'data' => ['total_horas' => $total_horas], 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Listar bitácora de tiempos de una tarea
 */
function tarea_tiempos_listar(mysqli $conexion, int $empresa_id, int $tarea_id): array
{
    $sql = "SELECT tt.*, u.usuario_nombre, u.usuario
            FROM estudio__tareas_tiempos tt
            INNER JOIN conf__usuarios u ON u.usuario_id = tt.usuario_id
            WHERE tt.empresa_id = ? AND tt.tarea_id = ?
            ORDER BY tt.tiempo_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $tarea_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}
