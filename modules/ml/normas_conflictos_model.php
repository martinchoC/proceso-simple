<?php
/**
 * Modelo de Datos para Conflictos Normativos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_conflictos_model.php
 */

declare(strict_types=1);

/**
 * Listar conflictos entre normas incompatibles (Caso C19).
 */
function normas_conflictos_listar(mysqli $conexion, int $estado = 0): array
{
    $sql = "SELECT nc.norma_conflicto_id, nc.norma_a_id, nc.norma_b_id, nc.descripcion,
                   nc.escalado_a, u_esc.usuario AS escalado_a_login,
                   nc.decision, nc.decidido_por, u_dec.usuario AS decidido_por_login, nc.decidido_en,
                   nc.tabla_estado_registro_id,
                   er.estado_registro,
                   na.codigo AS norma_a_codigo, na.titulo AS norma_a_titulo,
                   nb.codigo AS norma_b_codigo, nb.titulo AS norma_b_titulo,
                   nc.creado_en
            FROM ml__normas_conflictos nc
            INNER JOIN ml__normas na ON na.norma_id = nc.norma_a_id
            INNER JOIN ml__normas nb ON nb.norma_id = nc.norma_b_id
            LEFT JOIN conf__usuarios u_esc ON u_esc.usuario_id = nc.escalado_a
            LEFT JOIN conf__usuarios u_dec ON u_dec.usuario_id = nc.decidido_por
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = nc.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE 1=1";

    if ($estado > 0) {
        $sql .= " AND nc.tabla_estado_registro_id = " . intval($estado);
    }

    $sql .= " ORDER BY nc.creado_en DESC, nc.norma_conflicto_id DESC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Registrar un nuevo conflicto normativo (Estado inicial 2084 Abierto / Pendiente).
 */
function normas_conflictos_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $norma_a_id = intval($datos['norma_a_id'] ?? 0);
    $norma_b_id = intval($datos['norma_b_id'] ?? 0);
    $descripcion = trim($datos['descripcion'] ?? '');
    $escalar_inmediato = !empty($datos['escalar_a']);
    $escalado_a = $escalar_inmediato ? intval($datos['escalar_a']) : null;

    if ($norma_a_id <= 0 || $norma_b_id <= 0) {
        return ['success' => false, 'message' => 'Debe seleccionar ambas normas en conflicto.'];
    }

    if ($norma_a_id === $norma_b_id) {
        return ['success' => false, 'message' => 'Una norma no puede tener conflicto consigo misma.'];
    }

    if (empty($descripcion)) {
        return ['success' => false, 'message' => 'La descripción del conflicto técnico o legal es obligatoria.'];
    }

    // Estado: 2085 si se escala de inmediato, o 2084 inicial
    $estado = $escalado_a ? 2085 : 2084;

    $sql = "INSERT INTO ml__normas_conflictos (norma_a_id, norma_b_id, descripcion, escalado_a, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iisiii", $norma_a_id, $norma_b_id, $descripcion, $escalado_a, $estado, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $conflicto_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $conflicto_id, 'message' => 'Conflicto normativo registrado exitosamente.'];
    }
    return ['success' => false, 'message' => 'Error al registrar conflicto: ' . mysqli_error($conexion)];
}

/**
 * Escalar conflicto a abogado o especialista legal (Estado 2085).
 */
function normas_conflictos_escalar(mysqli $conexion, int $conflicto_id, int $abogado_id, int $usuario_id): array
{
    if ($conflicto_id <= 0 || $abogado_id <= 0) {
        return ['success' => false, 'message' => 'Debe indicar el conflicto y el usuario del área legal al que se escala.'];
    }

    $sql = "UPDATE ml__normas_conflictos 
            SET escalado_a = ?, tabla_estado_registro_id = 2085, actualizado_en = NOW(), actualizado_por = ? 
            WHERE norma_conflicto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $abogado_id, $usuario_id, $conflicto_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Conflicto escalado al área legal exitosamente.'];
    }
    return ['success' => false, 'message' => 'Error al escalar conflicto: ' . mysqli_error($conexion)];
}

/**
 * Emitir dictamen y resolver conflicto normativo (Estado final 2086 Resuelto).
 */
function normas_conflictos_resolver(mysqli $conexion, int $conflicto_id, string $decision, int $usuario_id): array
{
    $decision = trim($decision);
    if ($conflicto_id <= 0 || empty($decision)) {
        return ['success' => false, 'message' => 'Debe ingresar el fundamento y dictamen legal de la resolución.'];
    }

    $sql = "UPDATE ml__normas_conflictos 
            SET decision = ?, decidido_por = ?, decidido_en = NOW(), tabla_estado_registro_id = 2086,
                actualizado_en = NOW(), actualizado_por = ? 
            WHERE norma_conflicto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "siii", $decision, $usuario_id, $usuario_id, $conflicto_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Dictamen legal registrado y conflicto resuelto.'];
    }
    return ['success' => false, 'message' => 'Error al resolver conflicto: ' . mysqli_error($conexion)];
}

/**
 * Obtener abogados y usuarios con perfil legal para combo de escalado.
 */
function normas_conflictos_abogados(mysqli $conexion): array
{
    // Perfiles 301 (Admin) y 303 (Área Legal)
    $sql = "SELECT DISTINCT u.usuario_id, u.usuario 
            FROM conf__usuarios u
            INNER JOIN conf__usuarios_perfiles up ON up.usuario_id = u.usuario_id
            WHERE up.perfil_id IN (301, 303) AND u.tabla_estado_registro_id = 1
            ORDER BY u.usuario ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    return $lista;
}
