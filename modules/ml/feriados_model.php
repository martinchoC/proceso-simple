<?php
/**
 * Modelo de Datos para Feriados y Calendario Normativo (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/feriados_model.php
 */

declare(strict_types=1);

/**
 * Listar feriados con filtros opcionales de territorio, año y estado.
 */
function feriados_listar(mysqli $conexion, int $territorio_id = 0, int $anio = 0, int $estado_id = 0): array
{
    $sql = "SELECT f.feriado_id, f.territorio_id, f.fecha, f.nombre, f.tabla_estado_registro_id,
                   t.nombre AS territorio_nombre, t.codigo AS territorio_codigo,
                   tt.nombre AS territorio_tipo_nombre
            FROM ml__feriados f
            INNER JOIN ml__territorios t ON t.territorio_id = f.territorio_id
            INNER JOIN ml__territorios_tipos tt ON tt.territorio_tipo_id = t.territorio_tipo_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($territorio_id > 0) {
        $sql .= " AND f.territorio_id = ?";
        $params[] = $territorio_id;
        $types .= "i";
    }

    if ($anio > 0) {
        $sql .= " AND YEAR(f.fecha) = ?";
        $params[] = $anio;
        $types .= "i";
    }

    if ($estado_id > 0) {
        $sql .= " AND f.tabla_estado_registro_id = ?";
        $params[] = $estado_id;
        $types .= "i";
    }

    $sql .= " ORDER BY f.fecha ASC, t.nombre ASC";

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
 * Obtener un feriado específico por su ID.
 */
function feriados_obtener_por_id(mysqli $conexion, int $feriado_id): ?array
{
    $sql = "SELECT f.*, t.nombre AS territorio_nombre, t.codigo AS territorio_codigo
            FROM ml__feriados f
            INNER JOIN ml__territorios t ON t.territorio_id = f.territorio_id
            WHERE f.feriado_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $feriado_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear un nuevo registro de feriado.
 */
function feriados_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $territorio_id = intval($datos['territorio_id'] ?? 0);
    $fecha = trim($datos['fecha'] ?? '');
    $nombre = trim($datos['nombre'] ?? '');

    if ($territorio_id <= 0) {
        return ['success' => false, 'message' => 'Debe seleccionar un territorio válido.'];
    }
    if (empty($fecha)) {
        return ['success' => false, 'message' => 'La fecha del feriado es obligatoria.'];
    }
    if (empty($nombre)) {
        return ['success' => false, 'message' => 'La descripción o nombre del feriado es obligatoria.'];
    }

    // Verificar unicidad territorio + fecha
    $sql_check = "SELECT feriado_id FROM ml__feriados WHERE territorio_id = ? AND fecha = ?";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "is", $territorio_id, $fecha);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    if (mysqli_num_rows($res_c) > 0) {
        mysqli_stmt_close($stmt_c);
        return ['success' => false, 'message' => 'Ya existe un feriado registrado para este territorio en esa fecha.'];
    }
    mysqli_stmt_close($stmt_c);

    $sql = "INSERT INTO ml__feriados (territorio_id, fecha, nombre, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, 1, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "issi", $territorio_id, $fecha, $nombre, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $nuevo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $nuevo_id, 'message' => 'Feriado creado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al guardar el feriado: ' . mysqli_error($conexion)];
}

/**
 * Actualizar los datos de un feriado existente.
 */
function feriados_actualizar(mysqli $conexion, int $feriado_id, array $datos, int $usuario_id): array
{
    $territorio_id = intval($datos['territorio_id'] ?? 0);
    $fecha = trim($datos['fecha'] ?? '');
    $nombre = trim($datos['nombre'] ?? '');

    if ($feriado_id <= 0 || $territorio_id <= 0) {
        return ['success' => false, 'message' => 'Datos inválidos para actualizar el feriado.'];
    }
    if (empty($fecha) || empty($nombre)) {
        return ['success' => false, 'message' => 'Fecha y nombre son obligatorios.'];
    }

    // Verificar unicidad excluyendo el actual
    $sql_check = "SELECT feriado_id FROM ml__feriados WHERE territorio_id = ? AND fecha = ? AND feriado_id != ?";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "isi", $territorio_id, $fecha, $feriado_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    if (mysqli_num_rows($res_c) > 0) {
        mysqli_stmt_close($stmt_c);
        return ['success' => false, 'message' => 'Ya existe otro feriado registrado para este territorio en esa fecha.'];
    }
    mysqli_stmt_close($stmt_c);

    $sql = "UPDATE ml__feriados 
            SET territorio_id = ?, fecha = ?, nombre = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE feriado_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "issii", $territorio_id, $fecha, $nombre, $usuario_id, $feriado_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Feriado actualizado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar el feriado: ' . mysqli_error($conexion)];
}

/**
 * Cambiar estado de un feriado (Activo = 1, Inactivo / Eliminado = 2).
 */
function feriados_cambiar_estado(mysqli $conexion, int $feriado_id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__feriados SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE feriado_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $feriado_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado del feriado actualizado con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al modificar estado: ' . mysqli_error($conexion)];
}

/**
 * Listar territorios disponibles para combo/select.
 */
function feriados_obtener_territorios_combo(mysqli $conexion): array
{
    $sql = "SELECT t.territorio_id, t.codigo, t.nombre, tt.nombre AS tipo_nombre
            FROM ml__territorios t
            INNER JOIN ml__territorios_tipos tt ON tt.territorio_tipo_id = t.territorio_tipo_id
            WHERE t.tabla_estado_registro_id = 1
            ORDER BY tt.territorio_tipo_id ASC, t.nombre ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}
