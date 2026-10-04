<?php
/**
 * Modelo de Datos para Excepciones de Cumplimiento (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/excepciones_model.php
 * Caso Cubierto: C09 (Excepción con motivo, fundamento y aprobación legal/regional con fecha de vencimiento)
 */

declare(strict_types=1);

/**
 * Listar excepciones registradas.
 */
function excepciones_listar(mysqli $conexion, array $filtros = []): array
{
    $sql = "SELECT exc.excepcion_id, exc.obligacion_asignada_id, exc.motivo, exc.fundamento, exc.fundamento_ref,
                   exc.solicitada_por, u_sol.nombre_usuario AS solicitada_por_nombre,
                   exc.aprobada_por, u_apr.nombre_usuario AS aprobada_por_nombre,
                   exc.aprobada_legal_por, u_leg.nombre_usuario AS aprobada_legal_por_nombre,
                   exc.aprobada_en, exc.vigente_desde, exc.vigente_hasta,
                   exc.tabla_estado_registro_id, ter.tabla_estado_registro AS estado_nombre,
                   exc.empresa_id, emp.empresa AS empresa_nombre,
                   e.codigo AS entidad_codigo, e.nombre AS entidad_nombre,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo, nt.es_legal,
                   CASE 
                       WHEN exc.tabla_estado_registro_id = 2089 AND CURRENT_DATE > exc.vigente_hasta THEN 1
                       ELSE 0 
                   END AS es_expirada
            FROM ml__excepciones exc
            INNER JOIN ml__obligaciones_asignadas oas ON oas.obligacion_asignada_id = exc.obligacion_asignada_id
            INNER JOIN ml__entidades e ON e.entidad_id = oas.entidad_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            INNER JOIN conf__empresas emp ON emp.empresa_id = exc.empresa_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = exc.tabla_estado_registro_id
            LEFT JOIN conf__usuarios u_sol ON u_sol.usuario_id = exc.solicitada_por
            LEFT JOIN conf__usuarios u_apr ON u_apr.usuario_id = exc.aprobada_por
            LEFT JOIN conf__usuarios u_leg ON u_leg.usuario_id = exc.aprobada_legal_por
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filtros['empresa_id'])) {
        $sql .= " AND exc.empresa_id = ?";
        $params[] = intval($filtros['empresa_id']);
        $types .= "i";
    }

    if (!empty($filtros['obligacion_asignada_id'])) {
        $sql .= " AND exc.obligacion_asignada_id = ?";
        $params[] = intval($filtros['obligacion_asignada_id']);
        $types .= "i";
    }

    if (!empty($filtros['tabla_estado_registro_id'])) {
        $sql .= " AND exc.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY exc.excepcion_id DESC";

    if (!empty($params)) {
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    $res = mysqli_query($conexion, $sql);
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

/**
 * Obtener detalle de una excepción por ID.
 */
function excepcion_obtener(mysqli $conexion, int $id): ?array
{
    $sql = "SELECT exc.*,
                   ter.tabla_estado_registro AS estado_nombre,
                   e.nombre AS entidad_nombre, e.codigo AS entidad_codigo,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo, nt.es_legal
            FROM ml__excepciones exc
            INNER JOIN ml__obligaciones_asignadas oas ON oas.obligacion_asignada_id = exc.obligacion_asignada_id
            INNER JOIN ml__entidades e ON e.entidad_id = oas.entidad_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = exc.tabla_estado_registro_id
            WHERE exc.excepcion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Solicitar una nueva excepción temporal (Estado inicial: 2087 Solicitada).
 * Caso C09: Motivo obligatorio, fechas obligatorias, y si es norma legal requiere fundamento.
 */
function excepcion_solicitar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $asig_id = intval($datos['obligacion_asignada_id'] ?? 0);
    $motivo = trim($datos['motivo'] ?? '');
    $fundamento = trim($datos['fundamento'] ?? '');
    $fundamento_ref = trim($datos['fundamento_ref'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = trim($datos['vigente_hasta'] ?? '');

    if ($asig_id <= 0 || empty($motivo) || empty($vigente_desde) || empty($vigente_hasta)) {
        return ['success' => false, 'message' => 'Debe indicar la obligación asignada, motivo y rango de vigencia (desde y hasta).'];
    }

    if ($vigente_hasta < $vigente_desde) {
        return ['success' => false, 'message' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.'];
    }

    // Verificar si la norma es de tipo legal
    $sql_chk = "SELECT oas.empresa_id, nt.es_legal, n.codigo AS norma_codigo
                FROM ml__obligaciones_asignadas oas
                INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
                INNER JOIN ml__normas n ON n.norma_id = o.norma_id
                INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
                WHERE oas.obligacion_asignada_id = ?";
    $stmt_chk = mysqli_prepare($conexion, $sql_chk);
    mysqli_stmt_bind_param($stmt_chk, "i", $asig_id);
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);
    $info = mysqli_fetch_assoc($res_chk);
    mysqli_stmt_close($stmt_chk);

    if (!$info) {
        return ['success' => false, 'message' => 'La obligación asignada no existe.'];
    }

    $empresa_id = intval($info['empresa_id']);
    $es_legal = intval($info['es_legal'] ?? 0) === 1;

    // Caso C09: Si la norma es legal, exige fundamento de inaplicabilidad o resolución de autoridad
    if ($es_legal && empty($fundamento)) {
        return ['success' => false, 'message' => "La norma '{$info['norma_codigo']}' es de carácter legal. El Caso C09 exige detallar el fundamento jurídico o resolución de autoridad correspondiente."];
    }

    $sql = "INSERT INTO ml__excepciones 
            (obligacion_asignada_id, motivo, fundamento, fundamento_ref, solicitada_por, 
             vigente_desde, vigente_hasta, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, 2087, ?, CURRENT_TIMESTAMP, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "isssissii", 
        $asig_id, $motivo, $fundamento, $fundamento_ref, $usuario_id,
        $vigente_desde, $vigente_hasta, $empresa_id, $usuario_id
    );
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    $nuevo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => "Error al registrar solicitud de excepción: {$err}"];
    }

    return ['success' => true, 'message' => 'Solicitud de excepción registrada con éxito.', 'excepcion_id' => $nuevo_id];
}

/**
 * Escalar excepción al área legal para dictamen (2087 -> 2088).
 */
function excepcion_escalar_legal(mysqli $conexion, int $id, int $usuario_id): array
{
    $sql = "UPDATE ml__excepciones 
            SET tabla_estado_registro_id = 2088, actualizado_en = CURRENT_TIMESTAMP, actualizado_por = ?
            WHERE excepcion_id = ? AND tabla_estado_registro_id = 2087";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    $filas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok || $filas === 0) {
        return ['success' => false, 'message' => 'No se pudo escalar la excepción (debe estar en estado Solicitada).'];
    }

    return ['success' => true, 'message' => 'Excepción escalada al área legal para dictamen.'];
}

/**
 * Aprobar excepción (2088 o 2087 -> 2089 Aprobada).
 * Caso C09: Si la norma es legal, requiere perfil legal (aprobada_legal_por).
 */
function excepcion_aprobar(mysqli $conexion, int $id, int $usuario_id, bool $es_abogado = false): array
{
    $item = excepcion_obtener($conexion, $id);
    if (!$item) {
        return ['success' => false, 'message' => 'Excepción no encontrada.'];
    }

    if (!in_array(intval($item['tabla_estado_registro_id']), [2087, 2088], true)) {
        return ['success' => false, 'message' => 'La excepción no está en un estado que admita aprobación.'];
    }

    $es_legal = intval($item['es_legal'] ?? 0) === 1;

    // Si es legal y no es abogado/área legal, rechazar aprobación
    if ($es_legal && !$es_abogado) {
        return ['success' => false, 'message' => 'La excepción aplica a una norma legal. Solo un usuario con perfil de Asesor Legal (303) puede dictaminar la aprobación.'];
    }

    $sql = "UPDATE ml__excepciones 
            SET tabla_estado_registro_id = 2089,
                aprobada_por = ?,
                aprobada_legal_por = ?,
                aprobada_en = CURRENT_TIMESTAMP,
                actualizado_en = CURRENT_TIMESTAMP,
                actualizado_por = ?
            WHERE excepcion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    $legal_user = $es_legal ? $usuario_id : null;
    mysqli_stmt_bind_param($stmt, "iiii", $usuario_id, $legal_user, $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => "Error al aprobar excepción: {$err}"];
    }

    return ['success' => true, 'message' => 'Excepción aprobada exitosamente. La entidad quedará exenta temporalmente durante su vigencia.'];
}

/**
 * Rechazar solicitud de excepción (-> 2090 Rechazada).
 */
function excepcion_rechazar(mysqli $conexion, int $id, int $usuario_id, string $motivo_rechazo = ''): array
{
    $sql = "UPDATE ml__excepciones 
            SET tabla_estado_registro_id = 2090,
                fundamento = CONCAT(COALESCE(fundamento, ''), '\n[RECHAZO]: ', ?),
                actualizado_en = CURRENT_TIMESTAMP,
                actualizado_por = ?
            WHERE excepcion_id = ? AND tabla_estado_registro_id IN (2087, 2088)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $motivo_rechazo, $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    $filas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok || $filas === 0) {
        return ['success' => false, 'message' => 'No se pudo rechazar la excepción (debe estar pendiente de aprobación).'];
    }

    return ['success' => true, 'message' => 'Solicitud de excepción rechazada.'];
}

/**
 * Revocar anticipadamente una excepción aprobada (2089 -> 2091 Vencida/Revocada).
 */
function excepcion_revocar(mysqli $conexion, int $id, int $usuario_id): array
{
    $sql = "UPDATE ml__excepciones 
            SET tabla_estado_registro_id = 2091,
                vigente_hasta = CURRENT_DATE,
                actualizado_en = CURRENT_TIMESTAMP,
                actualizado_por = ?
            WHERE excepcion_id = ? AND tabla_estado_registro_id = 2089";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    $filas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok || $filas === 0) {
        return ['success' => false, 'message' => 'No se pudo revocar la excepción (debe estar en estado Aprobada).'];
    }

    return ['success' => true, 'message' => 'Excepción revocada. La obligación vuelve a regir plenamente para la entidad.'];
}

/**
 * Combos para selector de obligaciones asignadas candidatas a excepción.
 */
function excepciones_combos(mysqli $conexion, int $empresa_id): array
{
    $sql = "SELECT oas.obligacion_asignada_id, e.nombre AS entidad_nombre, e.codigo AS entidad_codigo,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion,
                   n.codigo AS norma_codigo, nt.es_legal
            FROM ml__obligaciones_asignadas oas
            INNER JOIN ml__entidades e ON e.entidad_id = oas.entidad_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            WHERE oas.empresa_id = ?
              AND oas.tabla_estado_registro_id = 1
            ORDER BY e.nombre ASC, o.codigo ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $asignadas = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return [
        'obligaciones_asignadas' => $asignadas
    ];
}
