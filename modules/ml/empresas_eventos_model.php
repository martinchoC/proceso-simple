<?php
/**
 * Modelo de Datos para Eventos Societarios y Cambios de Razón Social (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/empresas_eventos_model.php
 */

declare(strict_types=1);

/**
 * Listar eventos societarios con filtros.
 */
function empresas_eventos_listar(mysqli $conexion, int $empresa_id = 0, int $tipo_id = 0, int $estado_id = 0): array
{
    $sql = "SELECT ee.empresa_evento_id, ee.empresa_evento_tipo_id, ee.empresa_origen_id, ee.empresa_destino_id,
                   ee.fecha, ee.observaciones, ee.tabla_estado_registro_id,
                   et.nombre AS tipo_nombre, et.codigo AS tipo_codigo,
                   eo.empresa AS empresa_origen_nombre, CAST(eo.documento_numero AS UNSIGNED) AS empresa_origen_cuit,
                   ed.empresa AS empresa_destino_nombre,
                   CASE ee.tabla_estado_registro_id
                       WHEN 3 THEN 'Borrador'
                       WHEN 4 THEN 'Pendiente de Aprobación'
                       WHEN 5 THEN 'Confirmado'
                       WHEN 6 THEN 'Eliminado'
                       ELSE 'Otro'
                   END AS estado_nombre,
                   CASE ee.tabla_estado_registro_id
                       WHEN 3 THEN 'secondary'
                       WHEN 4 THEN 'warning text-dark'
                       WHEN 5 THEN 'success'
                       WHEN 6 THEN 'danger'
                       ELSE 'dark'
                   END AS estado_badge
            FROM ml__empresas_eventos ee
            INNER JOIN ml__empresas_eventos_tipos et ON et.empresa_evento_tipo_id = ee.empresa_evento_tipo_id
            INNER JOIN conf__empresas eo ON eo.empresa_id = ee.empresa_origen_id
            LEFT JOIN conf__empresas ed ON ed.empresa_id = ee.empresa_destino_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($empresa_id > 0) {
        $sql .= " AND (ee.empresa_origen_id = ? OR ee.empresa_destino_id = ?)";
        $params[] = $empresa_id;
        $params[] = $empresa_id;
        $types .= "ii";
    }

    if ($tipo_id > 0) {
        $sql .= " AND ee.empresa_evento_tipo_id = ?";
        $params[] = $tipo_id;
        $types .= "i";
    }

    if ($estado_id > 0) {
        $sql .= " AND ee.tabla_estado_registro_id = ?";
        $params[] = $estado_id;
        $types .= "i";
    } else {
        // Por defecto no mostrar los eliminados
        $sql .= " AND ee.tabla_estado_registro_id != 6";
    }

    $sql .= " ORDER BY ee.fecha DESC, ee.empresa_evento_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Obtener un evento por ID.
 */
function empresas_eventos_obtener_por_id(mysqli $conexion, int $evento_id): ?array
{
    $sql = "SELECT ee.*, et.nombre AS tipo_nombre, et.codigo AS tipo_codigo,
                   eo.empresa AS empresa_origen_nombre, ed.empresa AS empresa_destino_nombre
            FROM ml__empresas_eventos ee
            INNER JOIN ml__empresas_eventos_tipos et ON et.empresa_evento_tipo_id = ee.empresa_evento_tipo_id
            INNER JOIN conf__empresas eo ON eo.empresa_id = ee.empresa_origen_id
            LEFT JOIN conf__empresas ed ON ed.empresa_id = ee.empresa_destino_id
            WHERE ee.empresa_evento_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $evento_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Registrar un nuevo evento societario en estado Borrador (3).
 */
function empresas_eventos_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $tipo_id = intval($datos['empresa_evento_tipo_id'] ?? 0);
    $empresa_origen_id = intval($datos['empresa_origen_id'] ?? 0);
    $empresa_destino_id = !empty($datos['empresa_destino_id']) ? intval($datos['empresa_destino_id']) : null;
    $fecha = trim($datos['fecha'] ?? date('Y-m-d'));
    $observaciones = trim($datos['observaciones'] ?? '');

    if ($tipo_id <= 0 || $empresa_origen_id <= 0 || empty($fecha)) {
        return ['success' => false, 'message' => 'Tipo de evento, empresa y fecha son obligatorios.'];
    }

    $sql = "INSERT INTO ml__empresas_eventos (empresa_evento_tipo_id, empresa_origen_id, empresa_destino_id, fecha, observaciones, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, 3, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiissi", $tipo_id, $empresa_origen_id, $empresa_destino_id, $fecha, $observaciones, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Evento societario registrado en Borrador.'];
    }
    return ['success' => false, 'message' => 'Error al crear evento: ' . mysqli_error($conexion)];
}

/**
 * Actualizar evento (solo si está en Borrador = 3).
 */
function empresas_eventos_actualizar(mysqli $conexion, int $evento_id, array $datos, int $usuario_id): array
{
    $evento = empresas_eventos_obtener_por_id($conexion, $evento_id);
    if (!$evento) {
        return ['success' => false, 'message' => 'Evento no encontrado.'];
    }
    if (intval($evento['tabla_estado_registro_id']) !== 3) {
        return ['success' => false, 'message' => 'Solo se pueden modificar eventos en estado Borrador.'];
    }

    $tipo_id = intval($datos['empresa_evento_tipo_id'] ?? 0);
    $empresa_origen_id = intval($datos['empresa_origen_id'] ?? 0);
    $empresa_destino_id = !empty($datos['empresa_destino_id']) ? intval($datos['empresa_destino_id']) : null;
    $fecha = trim($datos['fecha'] ?? '');
    $observaciones = trim($datos['observaciones'] ?? '');

    $sql = "UPDATE ml__empresas_eventos 
            SET empresa_evento_tipo_id = ?, empresa_origen_id = ?, empresa_destino_id = ?, fecha = ?, observaciones = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE empresa_evento_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiissii", $tipo_id, $empresa_origen_id, $empresa_destino_id, $fecha, $observaciones, $usuario_id, $evento_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Evento actualizado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar evento: ' . mysqli_error($conexion)];
}

/**
 * Transición de estado del proceso corto de eventos (3 -> 4 -> 5 o 6).
 */
function empresas_eventos_transicionar(mysqli $conexion, int $evento_id, int $nuevo_estado, int $usuario_id, string $nueva_razon_social = ''): array
{
    $evento = empresas_eventos_obtener_por_id($conexion, $evento_id);
    if (!$evento) {
        return ['success' => false, 'message' => 'Evento no encontrado.'];
    }

    $estado_actual = intval($evento['tabla_estado_registro_id']);

    // Validar transición según máquina de estados
    $transicion_valida = false;
    if ($estado_actual === 3 && ($nuevo_estado === 4 || $nuevo_estado === 6)) {
        $transicion_valida = true; // Borrador -> Pendiente o Eliminado
    } elseif ($estado_actual === 4 && ($nuevo_estado === 5 || $nuevo_estado === 6)) {
        $transicion_valida = true; // Pendiente -> Confirmado o Eliminado
    }

    if (!$transicion_valida) {
        return ['success' => false, 'message' => "Transición no permitida del estado $estado_actual al estado $nuevo_estado."];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Actualizar estado del evento
        $sql = "UPDATE ml__empresas_eventos SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE empresa_evento_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $evento_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Si se confirma (5), ejecutar impactos automáticos
        if ($nuevo_estado === 5) {
            $tipo_codigo = $evento['tipo_codigo'];
            $fecha_evento = $evento['fecha'];
            $origen_id = intval($evento['empresa_origen_id']);
            $destino_id = intval($evento['empresa_destino_id'] ?? 0);

            // Impacto 1: Cambio de denominación (C15)
            if ($tipo_codigo === 'CAMBIO_NOMBRE' && !empty($nueva_razon_social)) {
                // Cerrar vigencia de nombre anterior
                $sql_c = "UPDATE ml__empresas_nombres SET vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
                          WHERE empresa_id = ? AND vigente_hasta IS NULL";
                $stmt_c = mysqli_prepare($conexion, $sql_c);
                mysqli_stmt_bind_param($stmt_c, "sii", $fecha_evento, $usuario_id, $origen_id);
                mysqli_stmt_execute($stmt_c);
                mysqli_stmt_close($stmt_c);

                // Insertar nueva razón social
                $sql_n = "INSERT INTO ml__empresas_nombres (empresa_id, razon_social, vigente_desde, creado_en, creado_por)
                          VALUES (?, ?, ?, NOW(), ?)";
                $stmt_n = mysqli_prepare($conexion, $sql_n);
                mysqli_stmt_bind_param($stmt_n, "issi", $origen_id, $nueva_razon_social, $fecha_evento, $usuario_id);
                mysqli_stmt_execute($stmt_n);
                mysqli_stmt_close($stmt_n);
            }

            // Impacto 2: Salida del grupo (C04)
            if ($tipo_codigo === 'BAJA_GRUPO') {
                $sql_b = "UPDATE ml__empresas_regiones SET vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
                          WHERE empresa_id = ? AND (vigente_hasta IS NULL OR vigente_hasta > ?)";
                $stmt_b = mysqli_prepare($conexion, $sql_b);
                mysqli_stmt_bind_param($stmt_b, "siis", $fecha_evento, $usuario_id, $origen_id, $fecha_evento);
                mysqli_stmt_execute($stmt_b);
                mysqli_stmt_close($stmt_b);
            }
        }

        mysqli_commit($conexion);
        $mensajes = [
            4 => 'Evento enviado a revisión y aprobación legal.',
            5 => 'Evento confirmado e impactos societarios consolidados con éxito.',
            6 => 'Evento desestimado / eliminado.'
        ];
        return ['success' => true, 'message' => $mensajes[$nuevo_estado] ?? 'Estado actualizado correctamente.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al procesar la transición: ' . $e->getMessage()];
    }
}

/**
 * Obtener tipos de eventos para combo.
 */
function empresas_eventos_tipos_combo(mysqli $conexion): array
{
    $sql = "SELECT empresa_evento_tipo_id, codigo, nombre FROM ml__empresas_eventos_tipos WHERE tabla_estado_registro_id = 1 ORDER BY empresa_evento_tipo_id ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Listar historial de razones sociales de una empresa (C15).
 */
function empresas_nombres_listar(mysqli $conexion, int $empresa_id): array
{
    $sql = "SELECT empresa_nombre_id, empresa_id, razon_social, vigente_desde, vigente_hasta
            FROM ml__empresas_nombres
            WHERE empresa_id = ?
            ORDER BY vigente_desde DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}
