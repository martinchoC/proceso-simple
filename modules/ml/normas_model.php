<?php
/**
 * Modelo de Datos para Matriz de Normas y Versiones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_model.php
 */

declare(strict_types=1);

/**
 * Listar normas con filtros y alcance institucional/territorial.
 */
function normas_listar(mysqli $conexion, array $filtros = []): array
{
    $sql = "SELECT n.norma_id, n.codigo, n.titulo, n.descripcion,
                   n.idioma_original, n.titulo_traducido, n.idioma_traducido,
                   n.norma_tipo_id, nt.nombre AS norma_tipo_nombre, nt.codigo AS norma_tipo_codigo, nt.es_legal,
                   n.norma_jerarquia_id, nj.nombre AS jerarquia_nombre, nj.nivel AS jerarquia_nivel,
                   n.organismo_id, o.nombre AS organismo_nombre,
                   n.ambito_tipo_id, at.codigo AS ambito_tipo_codigo, at.nombre AS ambito_tipo_nombre,
                   n.grupo_id, n.region_grupo_id, n.empresa_id, n.territorio_id,
                   CASE at.codigo
                       WHEN 'GRUPO' THEN g.nombre
                       WHEN 'REGION_GRUPO' THEN rg.nombre
                       WHEN 'EMPRESA' THEN emp.empresa
                       WHEN 'TERRITORIO' THEN t.nombre
                       ELSE 'Sin alcance'
                   END AS alcance_nombre,
                   n.requiere_norma_local, n.confidencial,
                   n.vigente_desde, n.vigente_hasta,
                   n.tabla_estado_registro_id,
                   er.estado_registro,
                   (SELECT nv.version FROM ml__normas_versiones nv WHERE nv.norma_id = n.norma_id AND nv.es_vigente = 1 LIMIT 1) AS version_vigente,
                   (SELECT COUNT(*) FROM ml__normas_versiones nv WHERE nv.norma_id = n.norma_id) AS total_versiones,
                   (SELECT COUNT(*) FROM ml__normas_relaciones nr WHERE nr.norma_origen_id = n.norma_id OR nr.norma_destino_id = n.norma_id) AS total_relaciones
            FROM ml__normas n
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            LEFT JOIN ml__normas_jerarquias nj ON nj.norma_jerarquia_id = n.norma_jerarquia_id
            LEFT JOIN ml__organismos o ON o.organismo_id = n.organismo_id
            INNER JOIN ml__ambitos_tipos at ON at.ambito_tipo_id = n.ambito_tipo_id
            LEFT JOIN ml__grupos g ON g.grupo_id = n.grupo_id
            LEFT JOIN ml__regiones_grupo rg ON rg.region_grupo_id = n.region_grupo_id
            LEFT JOIN conf__empresas emp ON emp.empresa_id = n.empresa_id
            LEFT JOIN ml__territorios t ON t.territorio_id = n.territorio_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = n.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filtros['norma_tipo_id'])) {
        $sql .= " AND n.norma_tipo_id = ?";
        $params[] = intval($filtros['norma_tipo_id']);
        $types .= "i";
    }

    if (!empty($filtros['organismo_id'])) {
        $sql .= " AND n.organismo_id = ?";
        $params[] = intval($filtros['organismo_id']);
        $types .= "i";
    }

    if (!empty($filtros['ambito_tipo_id'])) {
        $sql .= " AND n.ambito_tipo_id = ?";
        $params[] = intval($filtros['ambito_tipo_id']);
        $types .= "i";
    }

    if (!empty($filtros['tabla_estado_registro_id'])) {
        $sql .= " AND n.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    if (isset($filtros['confidencial']) && $filtros['confidencial'] !== '') {
        $sql .= " AND n.confidencial = ?";
        $params[] = intval($filtros['confidencial']);
        $types .= "i";
    }

    if (!empty($filtros['solo_vigentes'])) {
        $sql .= " AND n.tabla_estado_registro_id = 2047 AND (n.vigente_hasta IS NULL OR n.vigente_hasta >= CURDATE())";
    }

    $sql .= " ORDER BY n.vigente_desde DESC, n.norma_id DESC";

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
 * Obtener norma por ID con todos sus atributos.
 */
function normas_obtener_por_id(mysqli $conexion, int $norma_id): ?array
{
    $sql = "SELECT n.*, nt.nombre AS norma_tipo_nombre, o.nombre AS organismo_nombre,
                   at.codigo AS ambito_tipo_codigo, at.nombre AS ambito_tipo_nombre,
                   er.estado_registro
            FROM ml__normas n
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            LEFT JOIN ml__organismos o ON o.organismo_id = n.organismo_id
            INNER JOIN ml__ambitos_tipos at ON at.ambito_tipo_id = n.ambito_tipo_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = n.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE n.norma_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $norma_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear nueva norma jurídica o corporativa con versión inicial (Caso C10).
 */
function normas_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $titulo = trim($datos['titulo'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $idioma_original = trim($datos['idioma_original'] ?? 'ES');
    $titulo_traducido = trim($datos['titulo_traducido'] ?? '');
    $idioma_traducido = trim($datos['idioma_traducido'] ?? '');

    $norma_tipo_id = intval($datos['norma_tipo_id'] ?? 0);
    $norma_jerarquia_id = !empty($datos['norma_jerarquia_id']) ? intval($datos['norma_jerarquia_id']) : null;
    $organismo_id = !empty($datos['organismo_id']) ? intval($datos['organismo_id']) : null;
    $ambito_tipo_id = intval($datos['ambito_tipo_id'] ?? 0);

    $grupo_id = !empty($datos['grupo_id']) ? intval($datos['grupo_id']) : null;
    $region_grupo_id = !empty($datos['region_grupo_id']) ? intval($datos['region_grupo_id']) : null;
    $empresa_id = !empty($datos['empresa_id']) ? intval($datos['empresa_id']) : null;
    $territorio_id = !empty($datos['territorio_id']) ? intval($datos['territorio_id']) : null;

    $requiere_norma_local = !empty($datos['requiere_norma_local']) ? 1 : 0;
    $confidencial = !empty($datos['confidencial']) ? 1 : 0;
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if (empty($codigo) || empty($titulo) || $norma_tipo_id <= 0 || $ambito_tipo_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Código, título, tipo de norma, alcance y fecha de vigencia son obligatorios.'];
    }

    // Constraint ck_nor_ambito: exactamente uno debe tener valor
    $cont_ambito = ($grupo_id ? 1 : 0) + ($region_grupo_id ? 1 : 0) + ($empresa_id ? 1 : 0) + ($territorio_id ? 1 : 0);
    if ($cont_ambito !== 1) {
        return ['success' => false, 'message' => 'Debe indicar exactamente un destino para el ámbito (Grupo, Región, Empresa o Territorio).'];
    }

    // Validar unicidad de código
    $check_q = mysqli_prepare($conexion, "SELECT norma_id FROM ml__normas WHERE codigo = ?");
    mysqli_stmt_bind_param($check_q, "s", $codigo);
    mysqli_stmt_execute($check_q);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check_q))) {
        mysqli_stmt_close($check_q);
        return ['success' => false, 'message' => "Ya existe una norma registrada con el código '{$codigo}'."];
    }
    mysqli_stmt_close($check_q);

    // Estado inicial: 2045 (Borrador)
    $estado_inicial = 2045;

    $sql = "INSERT INTO ml__normas 
            (norma_tipo_id, norma_jerarquia_id, organismo_id, codigo, titulo, descripcion,
             idioma_original, titulo_traducido, idioma_traducido, ambito_tipo_id,
             grupo_id, region_grupo_id, empresa_id, territorio_id,
             requiere_norma_local, confidencial, vigente_desde, vigente_hasta,
             tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "iiissssssiiiiiiissii",
        $norma_tipo_id, $norma_jerarquia_id, $organismo_id, $codigo, $titulo, $descripcion,
        $idioma_original, $titulo_traducido, $idioma_traducido, $ambito_tipo_id,
        $grupo_id, $region_grupo_id, $empresa_id, $territorio_id,
        $requiere_norma_local, $confidencial, $vigente_desde, $vigente_hasta,
        $estado_inicial, $usuario_id
    );
    $ok = mysqli_stmt_execute($stmt);
    $norma_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => 'Error al crear norma: ' . mysqli_error($conexion)];
    }

    // Crear versión inicial por defecto (v1.0)
    $version_inicial = '1.0';
    $hash_inicial = hash('sha256', $codigo . '|' . $version_inicial . '|' . $vigente_desde);
    $sql_v = "INSERT INTO ml__normas_versiones 
              (norma_id, version, fecha_publicacion, hash, es_vigente, confidencial, vigente_desde, vigente_hasta, tabla_estado_registro_id, creado_en, creado_por)
              VALUES (?, ?, ?, ?, 1, ?, ?, ?, 2049, NOW(), ?)";
    $stmt_v = mysqli_prepare($conexion, $sql_v);
    mysqli_stmt_bind_param($stmt_v, "isssissi", $norma_id, $version_inicial, $vigente_desde, $hash_inicial, $confidencial, $vigente_desde, $vigente_hasta, $usuario_id);
    mysqli_stmt_execute($stmt_v);
    mysqli_stmt_close($stmt_v);

    return ['success' => true, 'id' => $norma_id, 'message' => 'Norma y versión inicial registradas con éxito (Borrador).'];
}

/**
 * Actualizar datos de cabecera de una norma.
 */
function normas_actualizar(mysqli $conexion, int $norma_id, array $datos, int $usuario_id): array
{
    if ($norma_id <= 0) {
        return ['success' => false, 'message' => 'ID de norma inválido.'];
    }

    $titulo = trim($datos['titulo'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $idioma_original = trim($datos['idioma_original'] ?? 'ES');
    $titulo_traducido = trim($datos['titulo_traducido'] ?? '');
    $idioma_traducido = trim($datos['idioma_traducido'] ?? '');

    $norma_tipo_id = intval($datos['norma_tipo_id'] ?? 0);
    $norma_jerarquia_id = !empty($datos['norma_jerarquia_id']) ? intval($datos['norma_jerarquia_id']) : null;
    $organismo_id = !empty($datos['organismo_id']) ? intval($datos['organismo_id']) : null;

    $requiere_norma_local = !empty($datos['requiere_norma_local']) ? 1 : 0;
    $confidencial = !empty($datos['confidencial']) ? 1 : 0;
    $vigente_desde = trim($datos['vigente_desde'] ?? '');
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if (empty($titulo) || $norma_tipo_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Título, tipo y fecha de vigencia son obligatorios.'];
    }

    $sql = "UPDATE ml__normas 
            SET titulo = ?, descripcion = ?, idioma_original = ?, titulo_traducido = ?, idioma_traducido = ?,
                norma_tipo_id = ?, norma_jerarquia_id = ?, organismo_id = ?,
                requiere_norma_local = ?, confidencial = ?, vigente_desde = ?, vigente_hasta = ?,
                actualizado_en = NOW(), actualizado_por = ?
            WHERE norma_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "sssssiiiiissii",
        $titulo, $descripcion, $idioma_original, $titulo_traducido, $idioma_traducido,
        $norma_tipo_id, $norma_jerarquia_id, $organismo_id,
        $requiere_norma_local, $confidencial, $vigente_desde, $vigente_hasta,
        $usuario_id, $norma_id
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Norma actualizada correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al actualizar norma: ' . mysqli_error($conexion)];
}

/**
 * Cambiar estado en el flujo de Proceso Corto (Borrador -> Pendiente -> Confirmado / Vigente -> Eliminado / Derogada).
 */
function normas_cambiar_estado(mysqli $conexion, int $norma_id, int $nuevo_estado, int $usuario_id, string $motivo = ''): array
{
    $estados_validos = [2045, 2046, 2047, 2048];
    if (!in_array($nuevo_estado, $estados_validos, true)) {
        return ['success' => false, 'message' => 'Estado destino no válido para el flujo de normas.'];
    }

    // Consultar estado actual
    $sql_act = "SELECT tabla_estado_registro_id, vigente_desde, vigente_hasta FROM ml__normas WHERE norma_id = ?";
    $stmt_act = mysqli_prepare($conexion, $sql_act);
    mysqli_stmt_bind_param($stmt_act, "i", $norma_id);
    mysqli_stmt_execute($stmt_act);
    $res_act = mysqli_stmt_get_result($stmt_act);
    $row = mysqli_fetch_assoc($res_act);
    mysqli_stmt_close($stmt_act);

    if (!$row) {
        return ['success' => false, 'message' => 'Norma no encontrada.'];
    }

    $estado_actual = intval($row['tabla_estado_registro_id']);

    // Si se pasa a Derogada (2048), se ajusta vigente_hasta si es nula (Caso C12)
    $sql_upd = "UPDATE ml__normas SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ?";
    $params = [$nuevo_estado, $usuario_id];
    $types = "ii";

    if ($nuevo_estado === 2048 && empty($row['vigente_hasta'])) {
        $sql_upd .= ", vigente_hasta = CURDATE()";
    }

    // Si se pasa a Confirmada / Vigente (2047), asegurar que la versión activa pase a Confirmada (2051)
    if ($nuevo_estado === 2047) {
        mysqli_query($conexion, "UPDATE ml__normas_versiones SET tabla_estado_registro_id = 2051 WHERE norma_id = {$norma_id} AND es_vigente = 1");
    }

    $sql_upd .= " WHERE norma_id = ?";
    $params[] = $norma_id;
    $types .= "i";

    $stmt_u = mysqli_prepare($conexion, $sql_upd);
    mysqli_stmt_bind_param($stmt_u, $types, ...$params);
    $ok = mysqli_stmt_execute($stmt_u);
    mysqli_stmt_close($stmt_u);

    if ($ok) {
        $etiquetas = [
            2045 => 'Borrador',
            2046 => 'Pendiente de Aprobación',
            2047 => 'Confirmada y Vigente',
            2048 => 'Derogada'
        ];
        return ['success' => true, 'message' => "La norma ha pasado al estado: {$etiquetas[$nuevo_estado]}."];
    }
    return ['success' => false, 'message' => 'Error al cambiar estado: ' . mysqli_error($conexion)];
}

/**
 * Listar versiones de una norma.
 */
function normas_versiones_listar(mysqli $conexion, int $norma_id): array
{
    $sql = "SELECT nv.*, er.estado_registro
            FROM ml__normas_versiones nv
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = nv.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE nv.norma_id = ?
            ORDER BY nv.vigente_desde DESC, nv.norma_version_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $norma_id);
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
 * Registrar una nueva versión documental de una norma (Caso C02).
 */
function normas_versiones_crear(mysqli $conexion, int $norma_id, array $datos, int $usuario_id): array
{
    $version = trim($datos['version'] ?? '');
    $fecha_publicacion = !empty($datos['fecha_publicacion']) ? trim($datos['fecha_publicacion']) : date('Y-m-d');
    $archivo_ref = trim($datos['archivo_ref'] ?? '');
    $hash = trim($datos['hash'] ?? '');
    $confidencial = !empty($datos['confidencial']) ? 1 : 0;
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;
    $activar_inmediata = !empty($datos['activar_inmediata']);

    if (empty($version) || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'El número de versión y la fecha de vigencia son obligatorios.'];
    }

    if (empty($hash)) {
        $hash = hash('sha256', $norma_id . '|' . $version . '|' . $vigente_desde);
    }

    $es_vigente = $activar_inmediata ? 1 : 0;
    $estado_v = $activar_inmediata ? 2051 : 2049; // 2051 Confirmada / 2049 Borrador

    if ($activar_inmediata) {
        // Desmarcar vigencia de versiones anteriores
        mysqli_query($conexion, "UPDATE ml__normas_versiones SET es_vigente = 0, vigente_hasta = '{$vigente_desde}' WHERE norma_id = {$norma_id}");
    }

    $sql = "INSERT INTO ml__normas_versiones 
            (norma_id, version, fecha_publicacion, archivo_ref, hash, es_vigente, confidencial, vigente_desde, vigente_hasta, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "issssiissii", $norma_id, $version, $fecha_publicacion, $archivo_ref, $hash, $es_vigente, $confidencial, $vigente_desde, $vigente_hasta, $estado_v, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $version_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $version_id, 'message' => 'Nueva versión registrada con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al registrar versión: ' . mysqli_error($conexion)];
}

/**
 * Listar relaciones de una norma (origen y destino) (Caso C18).
 */
function normas_relaciones_listar(mysqli $conexion, int $norma_id): array
{
    $sql = "SELECT nr.norma_relacion_id, nr.norma_origen_id, nr.norma_destino_id,
                   nrt.codigo AS relacion_codigo, nrt.nombre AS relacion_nombre,
                   no.codigo AS origen_codigo, no.titulo AS origen_titulo,
                   nd.codigo AS destino_codigo, nd.titulo AS destino_titulo,
                   nr.descripcion, nr.vigente_desde, nr.vigente_hasta,
                   CASE 
                       WHEN nr.norma_origen_id = ? THEN 'SALIENTE'
                       ELSE 'ENTRANTE'
                   END AS sentido
            FROM ml__normas_relaciones nr
            INNER JOIN ml__normas_relaciones_tipos nrt ON nrt.norma_relacion_tipo_id = nr.norma_relacion_tipo_id
            INNER JOIN ml__normas no ON no.norma_id = nr.norma_origen_id
            INNER JOIN ml__normas nd ON nd.norma_id = nr.norma_destino_id
            WHERE nr.norma_origen_id = ? OR nr.norma_destino_id = ?
            ORDER BY nr.vigente_desde DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $norma_id, $norma_id, $norma_id);
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
 * Vincular una nueva relación entre normas.
 */
function normas_relaciones_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $norma_origen_id = intval($datos['norma_origen_id'] ?? 0);
    $norma_destino_id = intval($datos['norma_destino_id'] ?? 0);
    $norma_relacion_tipo_id = intval($datos['norma_relacion_tipo_id'] ?? 0);
    $descripcion = trim($datos['descripcion'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if ($norma_origen_id <= 0 || $norma_destino_id <= 0 || $norma_relacion_tipo_id <= 0) {
        return ['success' => false, 'message' => 'Debe indicar norma de origen, norma de destino y tipo de relación.'];
    }

    if ($norma_origen_id === $norma_destino_id) {
        return ['success' => false, 'message' => 'Una norma no puede relacionarse consigo misma.'];
    }

    $sql = "INSERT INTO ml__normas_relaciones 
            (norma_origen_id, norma_destino_id, norma_relacion_tipo_id, descripcion, vigente_desde, vigente_hasta, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiisssi", $norma_origen_id, $norma_destino_id, $norma_relacion_tipo_id, $descripcion, $vigente_desde, $vigente_hasta, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $rel_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $rel_id, 'message' => 'Vínculo jurídico establecido exitosamente.'];
    }
    return ['success' => false, 'message' => 'Error al vincular normas: ' . mysqli_error($conexion)];
}

/**
 * Eliminar relación entre normas.
 */
function normas_relaciones_eliminar(mysqli $conexion, int $norma_relacion_id): array
{
    if ($norma_relacion_id <= 0) {
        return ['success' => false, 'message' => 'ID de relación inválido.'];
    }

    $sql = "DELETE FROM ml__normas_relaciones WHERE norma_relacion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $norma_relacion_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Relación eliminada correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al eliminar relación: ' . mysqli_error($conexion)];
}

/**
 * Obtener catálogos para combos de la pantalla de normas.
 */
function normas_combos(mysqli $conexion): array
{
    $tipos = normas_tipos_listar($conexion, 1);
    $jerarquias = normas_jerarquias_listar($conexion, 1);
    $organismos = organismos_listar($conexion, 0, 1);
    $relaciones_tipos = normas_relaciones_tipos_listar($conexion);

    // Tipos de Ámbitos
    $q_at = mysqli_query($conexion, "SELECT ambito_tipo_id, codigo, nombre FROM ml__ambitos_tipos ORDER BY ambito_tipo_id ASC");
    $ambitos_tipos = [];
    while ($r = mysqli_fetch_assoc($q_at)) $ambitos_tipos[] = $r;

    // Grupos
    $q_g = mysqli_query($conexion, "SELECT grupo_id, nombre FROM ml__grupos WHERE tabla_estado_registro_id = 1 ORDER BY nombre ASC");
    $grupos = [];
    while ($r = mysqli_fetch_assoc($q_g)) $grupos[] = $r;

    // Regiones Grupo
    $q_rg = mysqli_query($conexion, "SELECT rg.region_grupo_id, rg.nombre, g.nombre AS grupo_nombre FROM ml__regiones_grupo rg INNER JOIN ml__grupos g ON g.grupo_id = rg.grupo_id WHERE rg.tabla_estado_registro_id = 1 ORDER BY g.nombre, rg.nombre ASC");
    $regiones = [];
    while ($r = mysqli_fetch_assoc($q_rg)) $regiones[] = $r;

    // Empresas
    $q_e = mysqli_query($conexion, "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
    $empresas = [];
    while ($r = mysqli_fetch_assoc($q_e)) $empresas[] = $r;

    // Territorios
    $q_t = mysqli_query($conexion, "SELECT t.territorio_id, t.nombre, t.codigo, tt.nombre AS tipo_nombre FROM ml__territorios t INNER JOIN ml__territorios_tipos tt ON tt.territorio_tipo_id = t.territorio_tipo_id WHERE t.tabla_estado_registro_id = 1 ORDER BY t.nombre ASC");
    $territorios = [];
    while ($r = mysqli_fetch_assoc($q_t)) $territorios[] = $r;

    return [
        'tipos' => $tipos,
        'jerarquias' => $jerarquias,
        'organismos' => $organismos,
        'relaciones_tipos' => $relaciones_tipos,
        'ambitos_tipos' => $ambitos_tipos,
        'grupos' => $grupos,
        'regiones' => $regiones,
        'empresas' => $empresas,
        'territorios' => $territorios
    ];
}
