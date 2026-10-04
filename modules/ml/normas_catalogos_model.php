<?php
/**
 * Modelo de Datos para Catálogos Normativos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_catalogos_model.php
 */

declare(strict_types=1);

/**
 * Listar organismos emisores.
 */
function organismos_listar(mysqli $conexion, int $territorio_id = 0, int $estado = 1): array
{
    $sql = "SELECT o.organismo_id, o.nombre, o.territorio_id, o.tabla_estado_registro_id,
                   COALESCE(t.nombre, 'Internacional / Multinacional') AS territorio_nombre,
                   o.creado_en
            FROM ml__organismos o
            LEFT JOIN ml__territorios t ON t.territorio_id = o.territorio_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($territorio_id > 0) {
        $sql .= " AND o.territorio_id = ?";
        $params[] = $territorio_id;
        $types .= "i";
    }

    if ($estado >= 0) {
        $sql .= " AND o.tabla_estado_registro_id = ?";
        $params[] = $estado;
        $types .= "i";
    }

    $sql .= " ORDER BY o.nombre ASC";

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
 * Guardar organismo (crear o editar).
 */
function organismo_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $organismo_id = intval($datos['organismo_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $territorio_id = !empty($datos['territorio_id']) ? intval($datos['territorio_id']) : null;
    $tabla_estado_registro_id = isset($datos['tabla_estado_registro_id']) ? intval($datos['tabla_estado_registro_id']) : 1;

    if (empty($nombre)) {
        return ['success' => false, 'message' => 'El nombre del organismo es obligatorio.'];
    }

    if ($organismo_id > 0) {
        $sql = "UPDATE ml__organismos 
                SET nombre = ?, territorio_id = ?, tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? 
                WHERE organismo_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "siiii", $nombre, $territorio_id, $tabla_estado_registro_id, $usuario_id, $organismo_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Organismo actualizado correctamente.'];
        }
        return ['success' => false, 'message' => 'Error al actualizar organismo: ' . mysqli_error($conexion)];
    } else {
        $sql = "INSERT INTO ml__organismos (nombre, territorio_id, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "siii", $nombre, $territorio_id, $tabla_estado_registro_id, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'id' => $nuevo_id, 'message' => 'Organismo creado exitosamente.'];
        }
        return ['success' => false, 'message' => 'Error al crear organismo: ' . mysqli_error($conexion)];
    }
}

/**
 * Listar tipos de normas.
 */
function normas_tipos_listar(mysqli $conexion, int $estado = 1): array
{
    $sql = "SELECT nt.norma_tipo_id, nt.codigo, nt.nombre, nt.es_legal, nt.genera_obligaciones,
                   nt.tabla_estado_registro_id, nt.creado_en
            FROM ml__normas_tipos nt
            WHERE 1=1";

    if ($estado >= 0) {
        $sql .= " AND nt.tabla_estado_registro_id = " . intval($estado);
    }
    $sql .= " ORDER BY nt.norma_tipo_id ASC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Guardar tipo de norma.
 */
function norma_tipo_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $norma_tipo_id = intval($datos['norma_tipo_id'] ?? 0);
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $es_legal = !empty($datos['es_legal']) ? 1 : 0;
    $genera_obligaciones = isset($datos['genera_obligaciones']) ? intval($datos['genera_obligaciones']) : 1;
    $tabla_estado_registro_id = isset($datos['tabla_estado_registro_id']) ? intval($datos['tabla_estado_registro_id']) : 1;

    if (empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'El código y el nombre del tipo de norma son obligatorios.'];
    }

    if ($norma_tipo_id > 0) {
        $sql = "UPDATE ml__normas_tipos 
                SET codigo = ?, nombre = ?, es_legal = ?, genera_obligaciones = ?, tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? 
                WHERE norma_tipo_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssiiiii", $codigo, $nombre, $es_legal, $genera_obligaciones, $tabla_estado_registro_id, $usuario_id, $norma_tipo_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Tipo de norma actualizado con éxito.'];
        }
        return ['success' => false, 'message' => 'Error al actualizar tipo de norma: ' . mysqli_error($conexion)];
    } else {
        $sql = "INSERT INTO ml__normas_tipos (codigo, nombre, es_legal, genera_obligaciones, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssiiii", $codigo, $nombre, $es_legal, $genera_obligaciones, $tabla_estado_registro_id, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'id' => $nuevo_id, 'message' => 'Tipo de norma creado exitosamente.'];
        }
        return ['success' => false, 'message' => 'Error al registrar tipo de norma: ' . mysqli_error($conexion)];
    }
}

/**
 * Listar jerarquías normativas.
 */
function normas_jerarquias_listar(mysqli $conexion, int $estado = 1): array
{
    $sql = "SELECT nj.norma_jerarquia_id, nj.nombre, nj.nivel, nj.tabla_estado_registro_id, nj.creado_en
            FROM ml__normas_jerarquias nj
            WHERE 1=1";

    if ($estado >= 0) {
        $sql .= " AND nj.tabla_estado_registro_id = " . intval($estado);
    }
    $sql .= " ORDER BY nj.nivel ASC, nj.norma_jerarquia_id ASC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Guardar jerarquía normativa.
 */
function norma_jerarquia_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $norma_jerarquia_id = intval($datos['norma_jerarquia_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $nivel = intval($datos['nivel'] ?? 1);
    $tabla_estado_registro_id = isset($datos['tabla_estado_registro_id']) ? intval($datos['tabla_estado_registro_id']) : 1;

    if (empty($nombre) || $nivel <= 0) {
        return ['success' => false, 'message' => 'El nombre y el nivel jerárquico (> 0) son obligatorios.'];
    }

    if ($norma_jerarquia_id > 0) {
        $sql = "UPDATE ml__normas_jerarquias 
                SET nombre = ?, nivel = ?, tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? 
                WHERE norma_jerarquia_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "siiii", $nombre, $nivel, $tabla_estado_registro_id, $usuario_id, $norma_jerarquia_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Nivel jerárquico actualizado.'];
        }
        return ['success' => false, 'message' => 'Error al actualizar jerarquía: ' . mysqli_error($conexion)];
    } else {
        $sql = "INSERT INTO ml__normas_jerarquias (nombre, nivel, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "siii", $nombre, $nivel, $tabla_estado_registro_id, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'id' => $nuevo_id, 'message' => 'Jerarquía creada exitosamente.'];
        }
        return ['success' => false, 'message' => 'Error al registrar jerarquía: ' . mysqli_error($conexion)];
    }
}

/**
 * Listar tipos de relaciones inter-normas.
 */
function normas_relaciones_tipos_listar(mysqli $conexion): array
{
    $sql = "SELECT nrt.norma_relacion_tipo_id, nrt.codigo, nrt.nombre, nrt.tabla_estado_registro_id, nrt.creado_en
            FROM ml__normas_relaciones_tipos nrt
            ORDER BY nrt.norma_relacion_tipo_id ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}
