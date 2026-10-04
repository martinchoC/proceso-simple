<?php
/**
 * Modelo de Datos para Ámbitos de Usuarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/usuarios_ambitos_model.php
 */

declare(strict_types=1);

/**
 * Listar asignaciones de ámbitos territoriales / corporativos a usuarios.
 */
function usuarios_ambitos_listar(mysqli $conexion, int $usuario_id = 0, int $tipo_id = 0, bool $solo_activos = true): array
{
    $sql = "SELECT ua.usuario_ambito_id, ua.usuario_id, ua.empresa_perfil_id, ua.ambito_tipo_id,
                   ua.grupo_id, ua.region_grupo_id, ua.empresa_id, ua.territorio_id,
                   ua.vigente_desde, ua.vigente_hasta,
                   u.usuario AS usuario_login,
                   ep.empresa_perfil_nombre,
                   at.codigo AS ambito_tipo_codigo, at.nombre AS ambito_tipo_nombre,
                   CASE at.codigo
                       WHEN 'GRUPO' THEN g.nombre
                       WHEN 'REGION_GRUPO' THEN rg.nombre
                       WHEN 'EMPRESA' THEN emp.empresa
                       WHEN 'TERRITORIO' THEN t.nombre
                       ELSE 'Sin especificar'
                   END AS alcance_nombre,
                   CASE 
                       WHEN ua.vigente_hasta IS NULL OR ua.vigente_hasta >= CURDATE() THEN 1
                       ELSE 0
                   END AS es_activo
            FROM ml__usuarios_ambitos ua
            INNER JOIN conf__usuarios u ON u.usuario_id = ua.usuario_id
            INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = ua.empresa_perfil_id
            INNER JOIN ml__ambitos_tipos at ON at.ambito_tipo_id = ua.ambito_tipo_id
            LEFT JOIN ml__grupos g ON g.grupo_id = ua.grupo_id
            LEFT JOIN ml__regiones_grupo rg ON rg.region_grupo_id = ua.region_grupo_id
            LEFT JOIN conf__empresas emp ON emp.empresa_id = ua.empresa_id
            LEFT JOIN ml__territorios t ON t.territorio_id = ua.territorio_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($usuario_id > 0) {
        $sql .= " AND ua.usuario_id = ?";
        $params[] = $usuario_id;
        $types .= "i";
    }

    if ($tipo_id > 0) {
        $sql .= " AND ua.ambito_tipo_id = ?";
        $params[] = $tipo_id;
        $types .= "i";
    }

    if ($solo_activos) {
        $sql .= " AND (ua.vigente_hasta IS NULL OR ua.vigente_hasta >= CURDATE())";
    }

    $sql .= " ORDER BY u.usuario ASC, ua.vigente_desde DESC";

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
 * Asignar un ámbito a un usuario con validación estricta de exclusividad de ID (ck_usam_ambito).
 */
function usuarios_ambitos_crear(mysqli $conexion, array $datos, int $usuario_creador_id): array
{
    $usuario_id = intval($datos['usuario_id'] ?? 0);
    $empresa_perfil_id = intval($datos['empresa_perfil_id'] ?? 0);
    $ambito_tipo_id = intval($datos['ambito_tipo_id'] ?? 0);
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    $grupo_id = !empty($datos['grupo_id']) ? intval($datos['grupo_id']) : null;
    $region_grupo_id = !empty($datos['region_grupo_id']) ? intval($datos['region_grupo_id']) : null;
    $empresa_id = !empty($datos['empresa_id']) ? intval($datos['empresa_id']) : null;
    $territorio_id = !empty($datos['territorio_id']) ? intval($datos['territorio_id']) : null;

    if ($usuario_id <= 0 || $empresa_perfil_id <= 0 || $ambito_tipo_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Usuario, perfil, tipo de ámbito y fecha de vigencia son obligatorios.'];
    }

    // Validar que exactamente UNO de los 4 IDs tenga valor según el tipo
    $cont = ($grupo_id ? 1 : 0) + ($region_grupo_id ? 1 : 0) + ($empresa_id ? 1 : 0) + ($territorio_id ? 1 : 0);
    if ($cont !== 1) {
        return ['success' => false, 'message' => 'Debe indicar exactamente un destino para el ámbito (Grupo, Región, Empresa o Territorio).'];
    }

    $sql = "INSERT INTO ml__usuarios_ambitos (usuario_id, empresa_perfil_id, ambito_tipo_id, grupo_id, region_grupo_id, empresa_id, territorio_id, vigente_desde, vigente_hasta, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiiiiiissi", $usuario_id, $empresa_perfil_id, $ambito_tipo_id, $grupo_id, $region_grupo_id, $empresa_id, $territorio_id, $vigente_desde, $vigente_hasta, $usuario_creador_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $id, 'message' => 'Ámbito de usuario asignado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al asignar ámbito: ' . mysqli_error($conexion)];
}

/**
 * Cerrar la vigencia de un ámbito de usuario.
 */
function usuarios_ambitos_cerrar(mysqli $conexion, int $usuario_ambito_id, string $fecha_cierre, int $usuario_id): array
{
    if ($usuario_ambito_id <= 0 || empty($fecha_cierre)) {
        return ['success' => false, 'message' => 'Parámetros inválidos para cerrar ámbito.'];
    }

    $sql = "UPDATE ml__usuarios_ambitos SET vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ? WHERE usuario_ambito_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $fecha_cierre, $usuario_id, $usuario_ambito_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Vigencia del ámbito cerrada con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al cerrar vigencia: ' . mysqli_error($conexion)];
}

/**
 * Obtener catálogos para combos de la pantalla de ámbitos.
 */
function usuarios_ambitos_combos(mysqli $conexion): array
{
    // Usuarios
    $q_u = mysqli_query($conexion, "SELECT usuario_id, usuario FROM conf__usuarios WHERE tabla_estado_registro_id = 1 ORDER BY usuario ASC");
    $usuarios = [];
    while ($r = mysqli_fetch_assoc($q_u)) $usuarios[] = $r;

    // Perfiles de Matriz Legal (modulo_id = 3)
    $q_p = mysqli_query($conexion, "SELECT empresa_perfil_id, empresa_perfil_nombre, empresa_id FROM conf__empresas_perfiles WHERE modulo_id = 3 AND tabla_estado_registro_id = 1 ORDER BY empresa_perfil_nombre ASC");
    $perfiles = [];
    while ($r = mysqli_fetch_assoc($q_p)) $perfiles[] = $r;

    // Tipos de Ámbitos
    $q_a = mysqli_query($conexion, "SELECT ambito_tipo_id, codigo, nombre FROM ml__ambitos_tipos ORDER BY ambito_tipo_id ASC");
    $ambitos_tipos = [];
    while ($r = mysqli_fetch_assoc($q_a)) $ambitos_tipos[] = $r;

    return [
        'usuarios' => $usuarios,
        'perfiles' => $perfiles,
        'ambitos_tipos' => $ambitos_tipos
    ];
}
