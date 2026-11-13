<?php
/**
 * Modelo de Datos para Grupos Corporativos y Regiones de Gestión (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/grupos_regiones_model.php
 */

declare(strict_types=1);

/**
 * Listar grupos corporativos.
 */
function grupos_listar(mysqli $conexion, int $estado_id = 0): array
{
    $sql = "SELECT g.grupo_id, g.empresa_titular_id, g.nombre, g.tabla_estado_registro_id,
                   e.empresa AS empresa_titular_nombre,
                   (SELECT COUNT(*) FROM ml__regiones_grupo r WHERE r.grupo_id = g.grupo_id) AS total_regiones
            FROM ml__grupos g
            INNER JOIN conf__empresas e ON e.empresa_id = g.empresa_titular_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($estado_id > 0) {
        $sql .= " AND g.tabla_estado_registro_id = ?";
        $params[] = $estado_id;
        $types .= "i";
    }

    $sql .= " ORDER BY g.nombre ASC";

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
 * Obtener grupo por ID.
 */
function grupos_obtener_por_id(mysqli $conexion, int $grupo_id): ?array
{
    $sql = "SELECT g.*, e.empresa AS empresa_titular_nombre
            FROM ml__grupos g
            INNER JOIN conf__empresas e ON e.empresa_id = g.empresa_titular_id
            WHERE g.grupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $grupo_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear un grupo corporativo.
 */
function grupos_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $empresa_titular_id = intval($datos['empresa_titular_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');

    if ($empresa_titular_id <= 0) {
        return ['success' => false, 'message' => 'Debe seleccionar una empresa titular de cabecera.'];
    }
    if (empty($nombre)) {
        return ['success' => false, 'message' => 'El nombre del grupo es obligatorio.'];
    }

    $sql = "INSERT INTO ml__grupos (empresa_titular_id, nombre, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, 1, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "isi", $empresa_titular_id, $nombre, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Grupo corporativo creado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al crear el grupo: ' . mysqli_error($conexion)];
}

/**
 * Actualizar datos de un grupo.
 */
function grupos_actualizar(mysqli $conexion, int $grupo_id, array $datos, int $usuario_id): array
{
    $empresa_titular_id = intval($datos['empresa_titular_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');

    if ($grupo_id <= 0 || $empresa_titular_id <= 0 || empty($nombre)) {
        return ['success' => false, 'message' => 'Datos incompletos para actualizar el grupo.'];
    }

    $sql = "UPDATE ml__grupos 
            SET empresa_titular_id = ?, nombre = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE grupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "isii", $empresa_titular_id, $nombre, $usuario_id, $grupo_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Grupo actualizado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar el grupo: ' . mysqli_error($conexion)];
}

/**
 * Cambiar estado de grupo (1 = Activo, 2 = Inactivo).
 */
function grupos_cambiar_estado(mysqli $conexion, int $grupo_id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__grupos SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE grupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $grupo_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado del grupo modificado con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al cambiar estado: ' . mysqli_error($conexion)];
}

// =============================================================================
// REGIONES DEL GRUPO
// =============================================================================

/**
 * Listar regiones de un grupo o de todos.
 */
function regiones_listar(mysqli $conexion, int $grupo_id = 0, int $estado_id = 0): array
{
    $sql = "SELECT r.region_grupo_id, r.grupo_id, r.nombre, r.vigente_desde, r.vigente_hasta,
                   r.tabla_estado_registro_id, g.nombre AS grupo_nombre,
                   (SELECT COUNT(*) FROM ml__empresas_regiones er 
                    WHERE er.region_grupo_id = r.region_grupo_id 
                      AND (er.vigente_hasta IS NULL OR er.vigente_hasta >= CURDATE())) AS total_empresas_activas
            FROM ml__regiones_grupo r
            INNER JOIN ml__grupos g ON g.grupo_id = r.grupo_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($grupo_id > 0) {
        $sql .= " AND r.grupo_id = ?";
        $params[] = $grupo_id;
        $types .= "i";
    }

    if ($estado_id > 0) {
        $sql .= " AND r.tabla_estado_registro_id = ?";
        $params[] = $estado_id;
        $types .= "i";
    }

    $sql .= " ORDER BY g.nombre ASC, r.nombre ASC";

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
 * Obtener región por ID.
 */
function regiones_obtener_por_id(mysqli $conexion, int $region_id): ?array
{
    $sql = "SELECT r.*, g.nombre AS grupo_nombre
            FROM ml__regiones_grupo r
            INNER JOIN ml__grupos g ON g.grupo_id = r.grupo_id
            WHERE r.region_grupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $region_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear región de gestión.
 */
function regiones_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $grupo_id = intval($datos['grupo_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if ($grupo_id <= 0 || empty($nombre) || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Grupo, nombre y fecha vigente desde son obligatorios.'];
    }

    $sql = "INSERT INTO ml__regiones_grupo (grupo_id, nombre, vigente_desde, vigente_hasta, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, 1, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "isssi", $grupo_id, $nombre, $vigente_desde, $vigente_hasta, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Región de gestión creada correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al crear la región: ' . mysqli_error($conexion)];
}

/**
 * Actualizar región de gestión.
 */
function regiones_actualizar(mysqli $conexion, int $region_id, array $datos, int $usuario_id): array
{
    $grupo_id = intval($datos['grupo_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? '');
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if ($region_id <= 0 || $grupo_id <= 0 || empty($nombre) || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Datos incompletos para actualizar la región.'];
    }

    $sql = "UPDATE ml__regiones_grupo 
            SET grupo_id = ?, nombre = ?, vigente_desde = ?, vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE region_grupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "isssii", $grupo_id, $nombre, $vigente_desde, $vigente_hasta, $usuario_id, $region_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Región de gestión actualizada con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar región: ' . mysqli_error($conexion)];
}

// =============================================================================
// ASIGNACIÓN DE EMPRESAS A REGIONES (ml__empresas_regiones)
// =============================================================================

/**
 * Listar asignaciones de empresas a regiones.
 */
function empresas_regiones_listar(mysqli $conexion, int $region_grupo_id = 0, int $empresa_id = 0): array
{
    $sql = "SELECT er.empresa_region_id, er.empresa_id, er.region_grupo_id,
                   er.vigente_desde, er.vigente_hasta,
                   e.empresa AS empresa_nombre, CAST(e.documento_numero AS UNSIGNED) AS cuit,
                   r.nombre AS region_nombre, g.nombre AS grupo_nombre,
                   CASE 
                       WHEN er.vigente_hasta IS NULL OR er.vigente_hasta >= CURDATE() THEN 1
                       ELSE 0
                   END AS es_activa
            FROM ml__empresas_regiones er
            INNER JOIN conf__empresas e ON e.empresa_id = er.empresa_id
            INNER JOIN ml__regiones_grupo r ON r.region_grupo_id = er.region_grupo_id
            INNER JOIN ml__grupos g ON g.grupo_id = r.grupo_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($region_grupo_id > 0) {
        $sql .= " AND er.region_grupo_id = ?";
        $params[] = $region_grupo_id;
        $types .= "i";
    }

    if ($empresa_id > 0) {
        $sql .= " AND er.empresa_id = ?";
        $params[] = $empresa_id;
        $types .= "i";
    }

    $sql .= " ORDER BY r.nombre ASC, er.vigente_desde DESC";

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
 * Obtener asignación de empresa a región por ID.
 */
function empresas_regiones_obtener_por_id(mysqli $conexion, int $empresa_region_id): ?array
{
    $sql = "SELECT er.*, e.empresa AS empresa_nombre, CAST(e.documento_numero AS UNSIGNED) AS cuit,
                   r.nombre AS region_nombre, r.grupo_id, g.nombre AS grupo_nombre
            FROM ml__empresas_regiones er
            INNER JOIN conf__empresas e ON e.empresa_id = er.empresa_id
            INNER JOIN ml__regiones_grupo r ON r.region_grupo_id = er.region_grupo_id
            INNER JOIN ml__grupos g ON g.grupo_id = r.grupo_id
            WHERE er.empresa_region_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_region_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Asignar una empresa a una región con control de vigencia (Caso C01 / C03).
 */
function empresas_regiones_asignar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $empresa_id = intval($datos['empresa_id'] ?? 0);
    $region_grupo_id = intval($datos['region_grupo_id'] ?? 0);
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if ($empresa_id <= 0 || $region_grupo_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Empresa, región y fecha vigente desde son obligatorios.'];
    }

    if ($vigente_hasta !== null && $vigente_hasta < $vigente_desde) {
        return ['success' => false, 'message' => 'La fecha Vigente Hasta no puede ser anterior a Vigente Desde.'];
    }

    // Verificar si ya tiene asignación abierta en esta misma región
    $sql_check = "SELECT empresa_region_id FROM ml__empresas_regiones 
                  WHERE empresa_id = ? AND region_grupo_id = ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "iis", $empresa_id, $region_grupo_id, $vigente_desde);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    if (mysqli_num_rows($res_c) > 0) {
        mysqli_stmt_close($stmt_c);
        return ['success' => false, 'message' => 'La empresa ya posee una asignación activa o solapada en esta región.'];
    }
    mysqli_stmt_close($stmt_c);

    $sql = "INSERT INTO ml__empresas_regiones (empresa_id, region_grupo_id, vigente_desde, vigente_hasta, creado_en, creado_por)
            VALUES (?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iissi", $empresa_id, $region_grupo_id, $vigente_desde, $vigente_hasta, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Empresa asignada a la región correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al asignar la empresa: ' . mysqli_error($conexion)];
}

/**
 * Actualizar asignación de empresa a región.
 */
function empresas_regiones_actualizar(mysqli $conexion, int $empresa_region_id, array $datos, int $usuario_id): array
{
    $empresa_id = intval($datos['empresa_id'] ?? 0);
    $region_grupo_id = intval($datos['region_grupo_id'] ?? 0);
    $vigente_desde = trim($datos['vigente_desde'] ?? '');
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if ($empresa_region_id <= 0 || $empresa_id <= 0 || $region_grupo_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Empresa, región y fecha vigente desde son obligatorios.'];
    }

    if ($vigente_hasta !== null && $vigente_hasta < $vigente_desde) {
        return ['success' => false, 'message' => 'La fecha Vigente Hasta no puede ser anterior a Vigente Desde.'];
    }

    // Verificar si ya tiene otra asignación activa o solapada en esta misma región (excluyendo la actual)
    $sql_check = "SELECT empresa_region_id FROM ml__empresas_regiones 
                  WHERE empresa_id = ? AND region_grupo_id = ? AND empresa_region_id != ? 
                    AND (vigente_hasta IS NULL OR vigente_hasta >= ?)";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "iiis", $empresa_id, $region_grupo_id, $empresa_region_id, $vigente_desde);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    if (mysqli_num_rows($res_c) > 0) {
        mysqli_stmt_close($stmt_c);
        return ['success' => false, 'message' => 'La empresa ya posee otra asignación activa o solapada en esta región.'];
    }
    mysqli_stmt_close($stmt_c);

    $sql = "UPDATE ml__empresas_regiones 
            SET empresa_id = ?, region_grupo_id = ?, vigente_desde = ?, vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE empresa_region_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iissii", $empresa_id, $region_grupo_id, $vigente_desde, $vigente_hasta, $usuario_id, $empresa_region_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Asignación de empresa a región actualizada correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar asignación: ' . mysqli_error($conexion)];
}

/**
 * Cerrar vigencia de asignación de empresa a región (Caso C04).
 */
function empresas_regiones_cerrar_vigencia(mysqli $conexion, int $empresa_region_id, string $fecha_cierre, int $usuario_id): array
{
    if ($empresa_region_id <= 0 || empty($fecha_cierre)) {
        return ['success' => false, 'message' => 'Parámetros inválidos para cerrar vigencia.'];
    }

    $sql = "UPDATE ml__empresas_regiones 
            SET vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE empresa_region_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $fecha_cierre, $usuario_id, $empresa_region_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Vigencia de la empresa en la región cerrada correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al cerrar vigencia: ' . mysqli_error($conexion)];
}

/**
 * Eliminar asignación de empresa a región.
 */
function empresas_regiones_eliminar(mysqli $conexion, int $empresa_region_id): array
{
    if ($empresa_region_id <= 0) {
        return ['success' => false, 'message' => 'ID de asignación no válido.'];
    }

    $sql = "DELETE FROM ml__empresas_regiones WHERE empresa_region_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_region_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Asignación eliminada correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al eliminar asignación: ' . mysqli_error($conexion)];
}

/**
 * Obtener empresas disponibles en conf__empresas para combos.
 */
function obtener_empresas_combo(mysqli $conexion): array
{
    $sql = "SELECT empresa_id, empresa, CAST(documento_numero AS UNSIGNED) AS cuit FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}
