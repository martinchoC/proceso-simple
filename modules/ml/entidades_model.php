<?php
/**
 * Modelo de Datos para Entidades Sujetas a Control (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades_model.php
 */

declare(strict_types=1);

/**
 * Listar entidades con filtros de empresa titular, tipo, estado y tipo físico/móvil.
 */
function entidades_listar(mysqli $conexion, int $empresa_id = 0, int $tipo_id = 0, int $estado_id = 0, int $es_movil = -1): array
{
    $sql = "SELECT e.entidad_id, e.entidad_tipo_id, e.entidad_padre_id, e.codigo, e.nombre,
                   e.descripcion, e.es_raiz, e.vigente_desde, e.vigente_hasta, e.tabla_estado_registro_id,
                   et.nombre AS tipo_nombre, et.es_fisica, et.es_logica, et.es_movil,
                   ep.nombre AS padre_nombre,
                   emp.empresa_id AS empresa_titular_id, emp.empresa AS empresa_titular_nombre,
                   t.territorio_id AS ubicacion_territorio_id, t.nombre AS ubicacion_territorio_nombre
            FROM ml__entidades e
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            LEFT JOIN ml__entidades ep ON ep.entidad_id = e.entidad_padre_id
            LEFT JOIN ml__entidades_empresas ee 
                ON ee.entidad_id = e.entidad_id AND ee.es_titular = 1 AND (ee.vigente_hasta IS NULL OR ee.vigente_hasta >= CURDATE())
            LEFT JOIN conf__empresas emp ON emp.empresa_id = ee.empresa_id
            LEFT JOIN ml__entidades_ubicaciones eu 
                ON eu.entidad_id = e.entidad_id AND eu.es_base = 1 AND (eu.vigente_hasta IS NULL OR eu.vigente_hasta >= CURDATE())
            LEFT JOIN ml__territorios t ON t.territorio_id = eu.territorio_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($empresa_id > 0) {
        $sql .= " AND ee.empresa_id = ?";
        $params[] = $empresa_id;
        $types .= "i";
    }

    if ($tipo_id > 0) {
        $sql .= " AND e.entidad_tipo_id = ?";
        $params[] = $tipo_id;
        $types .= "i";
    }

    if ($estado_id > 0) {
        $sql .= " AND e.tabla_estado_registro_id = ?";
        $params[] = $estado_id;
        $types .= "i";
    }

    if ($es_movil === 0 || $es_movil === 1) {
        $sql .= " AND et.es_movil = ?";
        $params[] = $es_movil;
        $types .= "i";
    }

    $sql .= " ORDER BY e.nombre ASC";

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
 * Obtener entidad por ID.
 */
function entidades_obtener_por_id(mysqli $conexion, int $entidad_id): ?array
{
    $sql = "SELECT e.*, et.nombre AS tipo_nombre, et.es_movil,
                   ep.nombre AS padre_nombre,
                   ee.empresa_id AS empresa_titular_id, emp.empresa AS empresa_titular_nombre,
                   eu.territorio_id AS ubicacion_territorio_id, t.nombre AS ubicacion_territorio_nombre
            FROM ml__entidades e
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            LEFT JOIN ml__entidades ep ON ep.entidad_id = e.entidad_padre_id
            LEFT JOIN ml__entidades_empresas ee 
                ON ee.entidad_id = e.entidad_id AND ee.es_titular = 1 AND (ee.vigente_hasta IS NULL OR ee.vigente_hasta >= CURDATE())
            LEFT JOIN conf__empresas emp ON emp.empresa_id = ee.empresa_id
            LEFT JOIN ml__entidades_ubicaciones eu 
                ON eu.entidad_id = e.entidad_id AND eu.es_base = 1 AND (eu.vigente_hasta IS NULL OR eu.vigente_hasta >= CURDATE())
            LEFT JOIN ml__territorios t ON t.territorio_id = eu.territorio_id
            WHERE e.entidad_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear nueva entidad con su titularidad inicial y ubicación base.
 */
function entidades_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $codigo = trim($datos['codigo'] ?? '');
    $nombre = trim($datos['nombre'] ?? '');
    $entidad_tipo_id = intval($datos['entidad_tipo_id'] ?? 0);
    $entidad_padre_id = !empty($datos['entidad_padre_id']) ? intval($datos['entidad_padre_id']) : null;
    $empresa_titular_id = intval($datos['empresa_titular_id'] ?? 0);
    $territorio_id = intval($datos['territorio_id'] ?? 0);
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $descripcion = trim($datos['descripcion'] ?? '');
    $es_raiz = empty($entidad_padre_id) ? 1 : 0;

    if (empty($codigo) || empty($nombre) || $entidad_tipo_id <= 0 || $empresa_titular_id <= 0) {
        return ['success' => false, 'message' => 'Código, nombre, tipo y empresa titular son obligatorios.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Insertar entidad
        $sql = "INSERT INTO ml__entidades (entidad_tipo_id, entidad_padre_id, codigo, nombre, descripcion, es_raiz, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iisssisi", $entidad_tipo_id, $entidad_padre_id, $codigo, $nombre, $descripcion, $es_raiz, $vigente_desde, $usuario_id);
        mysqli_stmt_execute($stmt);
        $entidad_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        // 2. Insertar pertenencia de empresa titular inicial (C16)
        $sql_e = "INSERT INTO ml__entidades_empresas (entidad_id, empresa_id, es_titular, vigente_desde, creado_en, creado_por)
                  VALUES (?, ?, 1, ?, NOW(), ?)";
        $stmt_e = mysqli_prepare($conexion, $sql_e);
        mysqli_stmt_bind_param($stmt_e, "iisi", $entidad_id, $empresa_titular_id, $vigente_desde, $usuario_id);
        mysqli_stmt_execute($stmt_e);
        mysqli_stmt_close($stmt_e);

        // 3. Insertar ubicación base inicial si se indicó territorio
        if ($territorio_id > 0) {
            $sql_u = "INSERT INTO ml__entidades_ubicaciones (entidad_id, territorio_id, es_base, vigente_desde, creado_en, creado_por)
                      VALUES (?, ?, 1, ?, NOW(), ?)";
            $stmt_u = mysqli_prepare($conexion, $sql_u);
            mysqli_stmt_bind_param($stmt_u, "iisi", $entidad_id, $territorio_id, $vigente_desde, $usuario_id);
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_close($stmt_u);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'id' => $entidad_id, 'message' => 'Entidad creada correctamente.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al crear entidad: ' . $e->getMessage()];
    }
}

/**
 * Actualizar datos principales de una entidad.
 */
function entidades_actualizar(mysqli $conexion, int $entidad_id, array $datos, int $usuario_id): array
{
    $codigo = trim($datos['codigo'] ?? '');
    $nombre = trim($datos['nombre'] ?? '');
    $entidad_tipo_id = intval($datos['entidad_tipo_id'] ?? 0);
    $entidad_padre_id = !empty($datos['entidad_padre_id']) ? intval($datos['entidad_padre_id']) : null;
    $descripcion = trim($datos['descripcion'] ?? '');
    $es_raiz = empty($entidad_padre_id) ? 1 : 0;

    if ($entidad_id <= 0 || empty($codigo) || empty($nombre) || $entidad_tipo_id <= 0) {
        return ['success' => false, 'message' => 'Datos inválidos para actualizar entidad.'];
    }

    // Evitar referencia circular consigo misma
    if ($entidad_padre_id === $entidad_id) {
        return ['success' => false, 'message' => 'Una entidad no puede ser padre de sí misma.'];
    }

    $sql = "UPDATE ml__entidades 
            SET entidad_tipo_id = ?, entidad_padre_id = ?, codigo = ?, nombre = ?, descripcion = ?, es_raiz = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE entidad_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iisssiii", $entidad_tipo_id, $entidad_padre_id, $codigo, $nombre, $descripcion, $es_raiz, $usuario_id, $entidad_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Entidad actualizada correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar entidad: ' . mysqli_error($conexion)];
}

/**
 * Transferir titularidad de la entidad a otra empresa (Caso C16).
 */
function entidades_transferir_titularidad(mysqli $conexion, int $entidad_id, int $nueva_empresa_id, string $fecha_transferencia, int $usuario_id): array
{
    if ($entidad_id <= 0 || $nueva_empresa_id <= 0 || empty($fecha_transferencia)) {
        return ['success' => false, 'message' => 'Parámetros inválidos para transferir la entidad.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Cerrar titularidad abierta actual
        $sql_close = "UPDATE ml__entidades_empresas 
                      SET vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
                      WHERE entidad_id = ? AND es_titular = 1 AND vigente_hasta IS NULL";
        $stmt_c = mysqli_prepare($conexion, $sql_close);
        mysqli_stmt_bind_param($stmt_c, "siii", $fecha_transferencia, $usuario_id, $entidad_id);
        mysqli_stmt_execute($stmt_c);
        mysqli_stmt_close($stmt_c);

        // 2. Insertar nueva titularidad
        $sql_new = "INSERT INTO ml__entidades_empresas (entidad_id, empresa_id, es_titular, vigente_desde, creado_en, creado_por)
                    VALUES (?, ?, 1, ?, NOW(), ?)";
        $stmt_n = mysqli_prepare($conexion, $sql_new);
        mysqli_stmt_bind_param($stmt_n, "iisi", $entidad_id, $nueva_empresa_id, $fecha_transferencia, $usuario_id);
        mysqli_stmt_execute($stmt_n);
        mysqli_stmt_close($stmt_n);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Titularidad transferida correctamente conservando el historial.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al transferir titularidad: ' . $e->getMessage()];
    }
}

/**
 * Cambiar o actualizar la ubicación base de una entidad (Caso C05 / C06).
 */
function entidades_cambiar_ubicacion(mysqli $conexion, int $entidad_id, int $nuevo_territorio_id, string $fecha_traslado, int $usuario_id): array
{
    if ($entidad_id <= 0 || $nuevo_territorio_id <= 0 || empty($fecha_traslado)) {
        return ['success' => false, 'message' => 'Parámetros inválidos para cambiar la ubicación.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Cerrar ubicación base anterior
        $sql_close = "UPDATE ml__entidades_ubicaciones 
                      SET vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
                      WHERE entidad_id = ? AND es_base = 1 AND vigente_hasta IS NULL";
        $stmt_c = mysqli_prepare($conexion, $sql_close);
        mysqli_stmt_bind_param($stmt_c, "siii", $fecha_traslado, $usuario_id, $entidad_id);
        mysqli_stmt_execute($stmt_c);
        mysqli_stmt_close($stmt_c);

        // 2. Insertar nueva ubicación base
        $sql_new = "INSERT INTO ml__entidades_ubicaciones (entidad_id, territorio_id, es_base, vigente_desde, creado_en, creado_por)
                    VALUES (?, ?, 1, ?, NOW(), ?)";
        $stmt_n = mysqli_prepare($conexion, $sql_new);
        mysqli_stmt_bind_param($stmt_n, "iisi", $entidad_id, $nuevo_territorio_id, $fecha_traslado, $usuario_id);
        mysqli_stmt_execute($stmt_n);
        mysqli_stmt_close($stmt_n);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Ubicación territorial actualizada con éxito.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al actualizar ubicación: ' . $e->getMessage()];
    }
}

/**
 * Cambiar estado de una entidad (Activo = 1, Inactivo = 2).
 */
function entidades_cambiar_estado(mysqli $conexion, int $entidad_id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__entidades SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE entidad_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $entidad_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado de la entidad modificado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al modificar estado: ' . mysqli_error($conexion)];
}

/**
 * Listar tipos de entidad para combo.
 */
function entidades_tipos_combo(mysqli $conexion): array
{
    $sql = "SELECT entidad_tipo_id, codigo, nombre, es_movil, es_fisica, es_logica 
            FROM ml__entidades_tipos 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY nombre ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Listar posibles padres para la jerarquía de entidades (excluyendo el nodo actual).
 */
function entidades_padres_combo(mysqli $conexion, int $excluir_id = 0): array
{
    $sql = "SELECT e.entidad_id, e.codigo, e.nombre, et.nombre AS tipo_nombre
            FROM ml__entidades e
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            WHERE e.tabla_estado_registro_id = 1";
    if ($excluir_id > 0) {
        $sql .= " AND e.entidad_id != $excluir_id";
    }
    $sql .= " ORDER BY e.nombre ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Historial de transferencias y pertenencias de empresas para una entidad (C16 / C17).
 */
function entidades_historial_empresas(mysqli $conexion, int $entidad_id): array
{
    $sql = "SELECT ee.*, emp.empresa AS empresa_nombre, emp.cuit
            FROM ml__entidades_empresas ee
            INNER JOIN conf__empresas emp ON emp.empresa_id = ee.empresa_id
            WHERE ee.entidad_id = ?
            ORDER BY ee.vigente_desde DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
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
 * Historial de ubicaciones territoriales de una entidad (C05 / C06).
 */
function entidades_historial_ubicaciones(mysqli $conexion, int $entidad_id): array
{
    $sql = "SELECT eu.*, t.nombre AS territorio_nombre, t.codigo AS territorio_codigo, tt.nombre AS tipo_nombre
            FROM ml__entidades_ubicaciones eu
            INNER JOIN ml__territorios t ON t.territorio_id = eu.territorio_id
            INNER JOIN ml__territorios_tipos tt ON tt.territorio_tipo_id = t.territorio_tipo_id
            WHERE eu.entidad_id = ?
            ORDER BY eu.vigente_desde DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}