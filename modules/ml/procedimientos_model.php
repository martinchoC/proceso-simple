<?php
/**
 * Modelo de Datos para Procedimientos e Instrucciones de Soporte (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/procedimientos_model.php
 */

declare(strict_types=1);

/**
 * Listar procedimientos de soporte.
 */
function procedimientos_listar(mysqli $conexion, int $empresa_id = 0, int $estado = 0): array
{
    $sql = "SELECT p.procedimiento_id, p.codigo, p.nombre, p.descripcion, p.confidencial,
                   p.empresa_id, emp.empresa AS empresa_nombre,
                   p.norma_id, n.codigo AS norma_codigo, n.titulo AS norma_titulo,
                   p.tabla_estado_registro_id,
                   er.estado_registro,
                   (SELECT pv.version FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = p.procedimiento_id AND pv.tabla_estado_registro_id = 2059 ORDER BY pv.vigente_desde DESC LIMIT 1) AS version_vigente,
                   (SELECT COUNT(*) FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = p.procedimiento_id) AS total_versiones,
                   p.creado_en
            FROM ml__procedimientos p
            LEFT JOIN conf__empresas emp ON emp.empresa_id = p.empresa_id
            LEFT JOIN ml__normas n ON n.norma_id = p.norma_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = p.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE 1=1";

    if ($empresa_id > 0) {
        $sql .= " AND (p.empresa_id = " . intval($empresa_id) . " OR p.empresa_id IS NULL)";
    }

    if ($estado > 0) {
        $sql .= " AND p.tabla_estado_registro_id = " . intval($estado);
    }

    $sql .= " ORDER BY p.codigo ASC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Guardar procedimiento (crear o editar cabecera).
 */
function procedimiento_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $procedimiento_id = intval($datos['procedimiento_id'] ?? 0);
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $empresa_id = !empty($datos['empresa_id']) ? intval($datos['empresa_id']) : null;
    $norma_id = !empty($datos['norma_id']) ? intval($datos['norma_id']) : null;
    $confidencial = !empty($datos['confidencial']) ? 1 : 0;

    if (empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'Código y nombre de procedimiento son obligatorios.'];
    }

    if ($procedimiento_id > 0) {
        $sql = "UPDATE ml__procedimientos 
                SET codigo = ?, nombre = ?, descripcion = ?, empresa_id = ?, norma_id = ?, confidencial = ?,
                    actualizado_en = NOW(), actualizado_por = ? 
                WHERE procedimiento_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssiiiii", $codigo, $nombre, $descripcion, $empresa_id, $norma_id, $confidencial, $usuario_id, $procedimiento_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Procedimiento actualizado con éxito.'];
        }
        return ['success' => false, 'message' => 'Error al actualizar procedimiento: ' . mysqli_error($conexion)];
    } else {
        // Estado inicial: 2053 (Borrador)
        $sql = "INSERT INTO ml__procedimientos (codigo, nombre, descripcion, empresa_id, norma_id, confidencial, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, 2053, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssiiii", $codigo, $nombre, $descripcion, $empresa_id, $norma_id, $confidencial, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) {
            // Versión inicial v1.0
            $hash = hash('sha256', $nuevo_id . '|1.0|' . date('Y-m-d'));
            mysqli_query($conexion, "INSERT INTO ml__procedimientos_versiones (procedimiento_id, version, hash, resumen_cambios, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
                                    VALUES ({$nuevo_id}, '1.0', '{$hash}', 'Versión inicial', CURDATE(), 2057, NOW(), {$usuario_id})");

            return ['success' => true, 'id' => $nuevo_id, 'message' => 'Procedimiento y versión inicial registrados correctamente.'];
        }
        return ['success' => false, 'message' => 'Error al crear procedimiento: ' . mysqli_error($conexion)];
    }
}

/**
 * Cambiar estado de procedimiento (2053 Borrador -> 2054 Pendiente -> 2055 Confirmado -> 2056 Eliminado).
 */
function procedimiento_cambiar_estado(mysqli $conexion, int $procedimiento_id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__procedimientos SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE procedimiento_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $procedimiento_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado del procedimiento actualizado.'];
    }
    return ['success' => false, 'message' => 'Error al cambiar estado: ' . mysqli_error($conexion)];
}

/**
 * Listar versiones de un procedimiento.
 */
function procedimiento_versiones_listar(mysqli $conexion, int $procedimiento_id): array
{
    $sql = "SELECT pv.*, er.estado_registro, u_apr.usuario AS aprobado_por_login,
                   (SELECT COUNT(*) FROM ml__procedimientos_instrucciones pi WHERE pi.procedimiento_version_id = pv.procedimiento_version_id) AS total_instrucciones
            FROM ml__procedimientos_versiones pv
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = pv.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            LEFT JOIN conf__usuarios u_apr ON u_apr.usuario_id = pv.aprobado_por
            WHERE pv.procedimiento_id = ?
            ORDER BY pv.vigente_desde DESC, pv.procedimiento_version_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $procedimiento_id);
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
 * Registrar nueva versión de procedimiento.
 */
function procedimiento_version_crear(mysqli $conexion, int $procedimiento_id, array $datos, int $usuario_id): array
{
    $version = trim($datos['version'] ?? '');
    $resumen_cambios = trim($datos['resumen_cambios'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;
    $aprobar_directo = !empty($datos['aprobar_directo']);

    if (empty($version) || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Versión y fecha de vigencia son obligatorias.'];
    }

    $hash = hash('sha256', $procedimiento_id . '|' . $version . '|' . $vigente_desde);
    $estado_v = $aprobar_directo ? 2059 : 2057; // 2059 Confirmado / 2057 Borrador
    $aprobado_por = $aprobar_directo ? $usuario_id : null;
    $aprobado_en = $aprobar_directo ? date('Y-m-d H:i:s') : null;

    $sql = "INSERT INTO ml__procedimientos_versiones 
            (procedimiento_id, version, hash, resumen_cambios, aprobado_por, aprobado_en, vigente_desde, vigente_hasta, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "isssisssii", $procedimiento_id, $version, $hash, $resumen_cambios, $aprobado_por, $aprobado_en, $vigente_desde, $vigente_hasta, $estado_v, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $v_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $v_id, 'message' => 'Versión de procedimiento creada exitosamente.'];
    }
    return ['success' => false, 'message' => 'Error al crear versión: ' . mysqli_error($conexion)];
}

/**
 * Listar instrucciones secuenciales de una versión de procedimiento.
 */
function procedimiento_instrucciones_listar(mysqli $conexion, int $procedimiento_version_id): array
{
    $sql = "SELECT pi.* 
            FROM ml__procedimientos_instrucciones pi
            WHERE pi.procedimiento_version_id = ?
            ORDER BY pi.orden ASC, pi.instruccion_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $procedimiento_version_id);
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
 * Guardar instrucción de trabajo.
 */
function procedimiento_instruccion_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $procedimiento_version_id = intval($datos['procedimiento_version_id'] ?? 0);
    $orden = intval($datos['orden'] ?? 1);
    $titulo = trim($datos['titulo'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $es_obligatoria = !empty($datos['es_obligatoria']) ? 1 : 0;

    if ($procedimiento_version_id <= 0 || empty($titulo)) {
        return ['success' => false, 'message' => 'Título y versión de procedimiento son obligatorios.'];
    }

    $sql = "INSERT INTO ml__procedimientos_instrucciones 
            (procedimiento_version_id, orden, titulo, descripcion, es_obligatoria, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iissii", $procedimiento_version_id, $orden, $titulo, $descripcion, $es_obligatoria, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $ins_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $ins_id, 'message' => 'Instrucción agregada al procedimiento.'];
    }
    return ['success' => false, 'message' => 'Error al agregar instrucción: ' . mysqli_error($conexion)];
}

/**
 * Eliminar instrucción de procedimiento.
 */
function procedimiento_instruccion_eliminar(mysqli $conexion, int $instruccion_id): array
{
    $sql = "DELETE FROM ml__procedimientos_instrucciones WHERE instruccion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $instruccion_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Instrucción eliminada.'];
    }
    return ['success' => false, 'message' => 'Error al eliminar instrucción: ' . mysqli_error($conexion)];
}
