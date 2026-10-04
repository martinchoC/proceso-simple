<?php
/**
 * Modelo de Datos para Reglas, Periodicidades y Criticidades (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_reglas_model.php
 */

declare(strict_types=1);

/**
 * Listar criticidades.
 */
function criticidades_listar(mysqli $conexion): array
{
    $sql = "SELECT c.* FROM ml__criticidades c ORDER BY c.peso ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Guardar criticidad.
 */
function criticidad_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $criticidad_id = intval($datos['criticidad_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $peso = intval($datos['peso'] ?? 1);

    if (empty($nombre) || $peso <= 0) {
        return ['success' => false, 'message' => 'Nombre y peso numérico mayor a cero son obligatorios.'];
    }

    if ($criticidad_id > 0) {
        $sql = "UPDATE ml__criticidades SET nombre = ?, peso = ?, actualizado_en = NOW(), actualizado_por = ? WHERE criticidad_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "siii", $nombre, $peso, $usuario_id, $criticidad_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) return ['success' => true, 'message' => 'Criticidad actualizada.'];
        return ['success' => false, 'message' => 'Error al actualizar criticidad: ' . mysqli_error($conexion)];
    } else {
        $sql = "INSERT INTO ml__criticidades (nombre, peso, tabla_estado_registro_id, creado_en, creado_por) VALUES (?, ?, 1, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $nombre, $peso, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) return ['success' => true, 'id' => $id, 'message' => 'Criticidad creada exitosamente.'];
        return ['success' => false, 'message' => 'Error al crear criticidad: ' . mysqli_error($conexion)];
    }
}

/**
 * Listar periodicidades (Casos C07, C26).
 */
function periodicidades_listar(mysqli $conexion): array
{
    $sql = "SELECT p.* FROM ml__periodicidades p ORDER BY p.periodicidad_id ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Guardar periodicidad.
 */
function periodicidad_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $periodicidad_id = intval($datos['periodicidad_id'] ?? 0);
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $intervalo_cantidad = !empty($datos['intervalo_cantidad']) ? intval($datos['intervalo_cantidad']) : null;
    $intervalo_unidad = !empty($datos['intervalo_unidad']) ? trim($datos['intervalo_unidad']) : null;
    $es_evento = !empty($datos['es_evento']) ? 1 : 0;
    $fecha_fija_autoridad = !empty($datos['fecha_fija_autoridad']) ? 1 : 0;

    if (empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'Código y nombre de periodicidad son obligatorios.'];
    }

    if ($periodicidad_id > 0) {
        $sql = "UPDATE ml__periodicidades 
                SET codigo = ?, nombre = ?, intervalo_cantidad = ?, intervalo_unidad = ?, es_evento = ?, fecha_fija_autoridad = ?,
                    actualizado_en = NOW(), actualizado_por = ? 
                WHERE periodicidad_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssisiiii", $codigo, $nombre, $intervalo_cantidad, $intervalo_unidad, $es_evento, $fecha_fija_autoridad, $usuario_id, $periodicidad_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) return ['success' => true, 'message' => 'Periodicidad actualizada.'];
        return ['success' => false, 'message' => 'Error al actualizar periodicidad: ' . mysqli_error($conexion)];
    } else {
        $sql = "INSERT INTO ml__periodicidades (codigo, nombre, intervalo_cantidad, intervalo_unidad, es_evento, fecha_fija_autoridad, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssisiii", $codigo, $nombre, $intervalo_cantidad, $intervalo_unidad, $es_evento, $fecha_fija_autoridad, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) return ['success' => true, 'id' => $id, 'message' => 'Periodicidad creada exitosamente.'];
        return ['success' => false, 'message' => 'Error al registrar periodicidad: ' . mysqli_error($conexion)];
    }
}

/**
 * Listar reglas de vencimiento (Casos C07, C22, C23).
 */
function vencimientos_reglas_listar(mysqli $conexion): array
{
    $sql = "SELECT vr.* FROM ml__vencimientos_reglas vr ORDER BY vr.vencimiento_regla_id ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Guardar regla de vencimiento.
 */
function vencimiento_regla_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $vencimiento_regla_id = intval($datos['vencimiento_regla_id'] ?? 0);
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $tipo = trim($datos['tipo'] ?? 'CALENDARIO');
    $desplazamiento_dias = isset($datos['desplazamiento_dias']) ? intval($datos['desplazamiento_dias']) : 0;
    $parametros = trim($datos['parametros'] ?? '');

    if (empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'Código y denominación de la regla son obligatorios.'];
    }

    if ($vencimiento_regla_id > 0) {
        $sql = "UPDATE ml__vencimientos_reglas 
                SET codigo = ?, nombre = ?, tipo = ?, desplazamiento_dias = ?, parametros = ?, actualizado_en = NOW(), actualizado_por = ? 
                WHERE vencimiento_regla_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssissi", $codigo, $nombre, $tipo, $desplazamiento_dias, $parametros, $usuario_id, $vencimiento_regla_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) return ['success' => true, 'message' => 'Regla de vencimiento actualizada.'];
        return ['success' => false, 'message' => 'Error al actualizar regla: ' . mysqli_error($conexion)];
    } else {
        $sql = "INSERT INTO ml__vencimientos_reglas (codigo, nombre, tipo, desplazamiento_dias, parametros, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssisi", $codigo, $nombre, $tipo, $desplazamiento_dias, $parametros, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) return ['success' => true, 'id' => $id, 'message' => 'Regla de vencimiento creada exitosamente.'];
        return ['success' => false, 'message' => 'Error al registrar regla: ' . mysqli_error($conexion)];
    }
}
