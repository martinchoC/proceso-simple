<?php
/**
 * Modelo de Datos para Delegaciones y Suplencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/delegaciones_model.php
 */

declare(strict_types=1);

/**
 * Listar delegaciones y suplencias con filtros y estado dinámico de vigencia.
 */
function delegaciones_listar(mysqli $conexion, int $titular_id = 0, int $suplente_id = 0, string $estado = ''): array
{
    $sql = "SELECT d.delegacion_id, d.usuario_id, d.usuario_suplente_id, d.empresa_perfil_id,
                   d.motivo, d.vigente_desde, d.vigente_hasta, d.creado_en, d.creado_por,
                   u1.usuario AS titular_login,
                   u2.usuario AS suplente_login,
                   COALESCE(ep.empresa_perfil_nombre, 'Todos los perfiles') AS perfil_nombre,
                   CASE 
                       WHEN CURDATE() BETWEEN d.vigente_desde AND d.vigente_hasta THEN 'ACTIVA'
                       WHEN d.vigente_desde > CURDATE() THEN 'FUTURA'
                       ELSE 'FINALIZADA'
                   END AS estado_vigencia
            FROM ml__delegaciones d
            INNER JOIN conf__usuarios u1 ON u1.usuario_id = d.usuario_id
            INNER JOIN conf__usuarios u2 ON u2.usuario_id = d.usuario_suplente_id
            LEFT JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = d.empresa_perfil_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($titular_id > 0) {
        $sql .= " AND d.usuario_id = ?";
        $params[] = $titular_id;
        $types .= "i";
    }

    if ($suplente_id > 0) {
        $sql .= " AND d.usuario_suplente_id = ?";
        $params[] = $suplente_id;
        $types .= "i";
    }

    if ($estado === 'ACTIVA') {
        $sql .= " AND CURDATE() BETWEEN d.vigente_desde AND d.vigente_hasta";
    } elseif ($estado === 'FUTURA') {
        $sql .= " AND d.vigente_desde > CURDATE()";
    } elseif ($estado === 'FINALIZADA') {
        $sql .= " AND d.vigente_hasta < CURDATE()";
    }

    $sql .= " ORDER BY d.vigente_desde DESC, d.delegacion_id DESC";

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
 * Registrar una nueva delegación / suplencia temporal (Caso C34).
 */
function delegaciones_crear(mysqli $conexion, array $datos, int $usuario_creador_id): array
{
    $usuario_id = intval($datos['usuario_id'] ?? 0);
    $usuario_suplente_id = intval($datos['usuario_suplente_id'] ?? 0);
    $empresa_perfil_id = !empty($datos['empresa_perfil_id']) ? intval($datos['empresa_perfil_id']) : null;
    $motivo = trim($datos['motivo'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? '');
    $vigente_hasta = trim($datos['vigente_hasta'] ?? '');

    if ($usuario_id <= 0 || $usuario_suplente_id <= 0) {
        return ['success' => false, 'message' => 'Debe especificar el usuario titular y el suplente.'];
    }

    if ($usuario_id === $usuario_suplente_id) {
        return ['success' => false, 'message' => 'El usuario titular y el usuario suplente no pueden ser la misma persona.'];
    }

    if (empty($vigente_desde) || empty($vigente_hasta)) {
        return ['success' => false, 'message' => 'Las fechas de inicio y fin de vigencia son obligatorias.'];
    }

    if ($vigente_hasta < $vigente_desde) {
        return ['success' => false, 'message' => 'La fecha de fin de vigencia no puede ser anterior a la de inicio.'];
    }

    // Validar solapamiento con otra delegación activa del mismo titular y suplente
    $sql_check = "SELECT delegacion_id FROM ml__delegaciones 
                  WHERE usuario_id = ? AND usuario_suplente_id = ?
                    AND (? IS NULL OR empresa_perfil_id = ? OR empresa_perfil_id IS NULL)
                    AND NOT (vigente_hasta < ? OR vigente_desde > ?)";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "iiisss", $usuario_id, $usuario_suplente_id, $empresa_perfil_id, $empresa_perfil_id, $vigente_desde, $vigente_hasta);
    mysqli_stmt_execute($stmt_check);
    $res_check = mysqli_stmt_get_result($stmt_check);
    if (mysqli_fetch_assoc($res_check)) {
        mysqli_stmt_close($stmt_check);
        return ['success' => false, 'message' => 'Ya existe una delegación registrada que se solapa con el rango de fechas indicado para este titular y suplente.'];
    }
    mysqli_stmt_close($stmt_check);

    $sql = "INSERT INTO ml__delegaciones (usuario_id, usuario_suplente_id, empresa_perfil_id, motivo, vigente_desde, vigente_hasta, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiisssi", $usuario_id, $usuario_suplente_id, $empresa_perfil_id, $motivo, $vigente_desde, $vigente_hasta, $usuario_creador_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Suplencia / delegación registrada exitosamente.'];
    }
    return ['success' => false, 'message' => 'Error al registrar la suplencia: ' . mysqli_error($conexion)];
}

/**
 * Cancelar / Finalizar anticipadamente una delegación.
 */
function delegaciones_cancelar(mysqli $conexion, int $delegacion_id, int $usuario_id): array
{
    if ($delegacion_id <= 0) {
        return ['success' => false, 'message' => 'Identificador de delegación inválido.'];
    }

    // Consultar vigencia actual
    $sql_sel = "SELECT vigente_desde, vigente_hasta FROM ml__delegaciones WHERE delegacion_id = ?";
    $stmt_sel = mysqli_prepare($conexion, $sql_sel);
    mysqli_stmt_bind_param($stmt_sel, "i", $delegacion_id);
    mysqli_stmt_execute($stmt_sel);
    $res_sel = mysqli_stmt_get_result($stmt_sel);
    $row = mysqli_fetch_assoc($res_sel);
    mysqli_stmt_close($stmt_sel);

    if (!$row) {
        return ['success' => false, 'message' => 'Delegación no encontrada.'];
    }

    $hoy = date('Y-m-d');
    $desde = $row['vigente_desde'];

    // Si es futura (aún no arrancó), se elimina
    if ($desde > $hoy) {
        return delegaciones_eliminar($conexion, $delegacion_id);
    }

    // Si ya arrancó, cerramos la vigencia a ayer (si es posterior a desde) o a hoy
    $nueva_hasta = date('Y-m-d', strtotime('-1 day'));
    if ($nueva_hasta < $desde) {
        $nueva_hasta = $desde;
    }

    $sql = "UPDATE ml__delegaciones 
            SET vigente_hasta = ?, 
                motivo = CONCAT('[FINALIZADA ANTICIPADA] ', COALESCE(motivo, '')),
                actualizado_en = NOW(), 
                actualizado_por = ? 
            WHERE delegacion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $nueva_hasta, $usuario_id, $delegacion_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Delegación dada de baja anticipadamente con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al dar de baja la delegación: ' . mysqli_error($conexion)];
}

/**
 * Eliminar registro de delegación.
 */
function delegaciones_eliminar(mysqli $conexion, int $delegacion_id): array
{
    if ($delegacion_id <= 0) {
        return ['success' => false, 'message' => 'Identificador inválido.'];
    }

    $sql = "DELETE FROM ml__delegaciones WHERE delegacion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $delegacion_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Registro de delegación eliminado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al eliminar delegación: ' . mysqli_error($conexion)];
}

/**
 * Obtener catálogos para combos (Usuarios y Perfiles).
 */
function delegaciones_combos(mysqli $conexion): array
{
    $q_u = mysqli_query($conexion, "SELECT usuario_id, usuario FROM conf__usuarios WHERE tabla_estado_registro_id = 1 ORDER BY usuario ASC");
    $usuarios = [];
    while ($r = mysqli_fetch_assoc($q_u)) {
        $usuarios[] = $r;
    }

    $q_p = mysqli_query($conexion, "SELECT empresa_perfil_id, empresa_perfil_nombre FROM conf__empresas_perfiles WHERE modulo_id = 3 AND tabla_estado_registro_id = 1 ORDER BY empresa_perfil_nombre ASC");
    $perfiles = [];
    while ($r = mysqli_fetch_assoc($q_p)) {
        $perfiles[] = $r;
    }

    return [
        'usuarios' => $usuarios,
        'perfiles' => $perfiles
    ];
}
