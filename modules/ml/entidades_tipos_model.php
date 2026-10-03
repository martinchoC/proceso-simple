<?php
/**
 * Modelo de Datos para Tipos de Entidad y Tipos de Datos Complementarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades_tipos_model.php
 */

declare(strict_types=1);

/**
 * Listar tipos de entidad.
 */
function tipos_entidad_listar(mysqli $conexion, int $grupo_id = 0, int $estado_id = 0): array
{
    $sql = "SELECT et.*, g.nombre AS grupo_nombre,
                   (SELECT COUNT(*) FROM ml__entidades e WHERE e.entidad_tipo_id = et.entidad_tipo_id) AS total_entidades
            FROM ml__entidades_tipos et
            INNER JOIN ml__grupos g ON g.grupo_id = et.grupo_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($grupo_id > 0) {
        $sql .= " AND et.grupo_id = ?";
        $params[] = $grupo_id;
        $types .= "i";
    }

    if ($estado_id > 0) {
        $sql .= " AND et.tabla_estado_registro_id = ?";
        $params[] = $estado_id;
        $types .= "i";
    }

    $sql .= " ORDER BY et.nombre ASC";

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
 * Obtener tipo de entidad por ID.
 */
function tipos_entidad_obtener_por_id(mysqli $conexion, int $id): ?array
{
    $sql = "SELECT et.*, g.nombre AS grupo_nombre
            FROM ml__entidades_tipos et
            INNER JOIN ml__grupos g ON g.grupo_id = et.grupo_id
            WHERE et.entidad_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear tipo de entidad.
 */
function tipos_entidad_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $grupo_id = intval($datos['grupo_id'] ?? 1);
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $es_fisica = !empty($datos['es_fisica']) ? 1 : 0;
    $es_logica = !empty($datos['es_logica']) ? 1 : 0;
    $es_movil = !empty($datos['es_movil']) ? 1 : 0;

    if (empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'Código y nombre son obligatorios.'];
    }

    $sql = "INSERT INTO ml__entidades_tipos (grupo_id, codigo, nombre, es_fisica, es_logica, es_movil, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "issiiii", $grupo_id, $codigo, $nombre, $es_fisica, $es_logica, $es_movil, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Tipo de entidad creado con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al crear tipo de entidad: ' . mysqli_error($conexion)];
}

/**
 * Actualizar tipo de entidad.
 */
function tipos_entidad_actualizar(mysqli $conexion, int $id, array $datos, int $usuario_id): array
{
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $es_fisica = !empty($datos['es_fisica']) ? 1 : 0;
    $es_logica = !empty($datos['es_logica']) ? 1 : 0;
    $es_movil = !empty($datos['es_movil']) ? 1 : 0;

    if ($id <= 0 || empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'Datos inválidos para actualizar tipo de entidad.'];
    }

    $sql = "UPDATE ml__entidades_tipos 
            SET codigo = ?, nombre = ?, es_fisica = ?, es_logica = ?, es_movil = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE entidad_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ssiiii", $codigo, $nombre, $es_fisica, $es_logica, $es_movil, $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Tipo de entidad actualizado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar tipo: ' . mysqli_error($conexion)];
}

/**
 * Cambiar estado de tipo de entidad.
 */
function tipos_entidad_cambiar_estado(mysqli $conexion, int $id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__entidades_tipos SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE entidad_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado modificado con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al cambiar estado: ' . mysqli_error($conexion)];
}

// =============================================================================
// TIPOS DE DATOS COMPLEMENTARIOS (ml__datos_tipos)
// =============================================================================

/**
 * Listar tipos de datos complementarios.
 */
function datos_tipos_listar(mysqli $conexion, int $estado_id = 0): array
{
    $sql = "SELECT dt.* FROM ml__datos_tipos dt WHERE 1=1";
    if ($estado_id > 0) {
        $sql .= " AND dt.tabla_estado_registro_id = $estado_id";
    }
    $sql .= " ORDER BY dt.nombre ASC";
    $res = mysqli_query($conexion, $sql);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener tipo de dato por ID.
 */
function datos_tipos_obtener_por_id(mysqli $conexion, int $id): ?array
{
    $sql = "SELECT dt.* FROM ml__datos_tipos dt WHERE dt.dato_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear tipo de dato.
 */
function datos_tipos_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $tipo_valor = strtoupper(trim($datos['tipo_valor'] ?? 'TEXTO'));

    if (empty($codigo) || empty($nombre) || empty($tipo_valor)) {
        return ['success' => false, 'message' => 'Código, nombre y tipo de valor son requeridos.'];
    }

    $sql = "INSERT INTO ml__datos_tipos (codigo, nombre, tipo_valor, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, 1, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sssi", $codigo, $nombre, $tipo_valor, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Tipo de dato creado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al crear tipo de dato: ' . mysqli_error($conexion)];
}

/**
 * Actualizar tipo de dato.
 */
function datos_tipos_actualizar(mysqli $conexion, int $id, array $datos, int $usuario_id): array
{
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $tipo_valor = strtoupper(trim($datos['tipo_valor'] ?? 'TEXTO'));

    if ($id <= 0 || empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'Datos inválidos para actualizar.'];
    }

    $sql = "UPDATE ml__datos_tipos SET codigo = ?, nombre = ?, tipo_valor = ?, actualizado_en = NOW(), actualizado_por = ? WHERE dato_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sssii", $codigo, $nombre, $tipo_valor, $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Tipo de dato actualizado con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar tipo de dato: ' . mysqli_error($conexion)];
}

/**
 * Cambiar estado de tipo de dato.
 */
function datos_tipos_cambiar_estado(mysqli $conexion, int $id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__datos_tipos SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE dato_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado modificado con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al modificar estado: ' . mysqli_error($conexion)];
}
