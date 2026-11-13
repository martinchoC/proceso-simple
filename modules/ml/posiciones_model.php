<?php
/**
 * Modelo de Datos: Posiciones, Estructura Jerárquica y Supervisores (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/posiciones_model.php
 */

declare(strict_types=1);

/**
 * Listar posiciones con información jerárquica y ocupantes vigentes.
 */
function posiciones_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $where = ["p.empresa_id = ?"];
    $params = [$empresa_id];
    $types = "i";

    if (!empty($filtros['area'])) {
        $where[] = "p.area_departamento = ?";
        $params[] = $filtros['area'];
        $types .= "s";
    }

    if (!empty($filtros['estado_id'])) {
        $where[] = "p.tabla_estado_registro_id = ?";
        $params[] = (int)$filtros['estado_id'];
        $types .= "i";
    }

    if (!empty($filtros['buscar'])) {
        $busq = '%' . $filtros['buscar'] . '%';
        $where[] = "(p.nombre LIKE ? OR p.codigo LIKE ? OR p.area_departamento LIKE ?)";
        $params[] = $busq;
        $params[] = $busq;
        $params[] = $busq;
        $types .= "sss";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                p.posicion_id,
                p.empresa_id,
                p.codigo,
                p.nombre,
                p.area_departamento,
                p.descripcion,
                p.posicion_superior_id,
                sup.codigo AS superior_codigo,
                sup.nombre AS superior_nombre,
                p.tabla_estado_registro_id AS estado_id,
                CASE p.tabla_estado_registro_id
                    WHEN 1 THEN 'Activo'
                    WHEN 2 THEN 'Inactivo'
                    ELSE 'Desconocido'
                END AS estado_nombre,
                -- Ocupante titular vigente
                u.usuario_id AS ocupante_id,
                u.usuario_nombre AS ocupante_nombre,
                u.email AS ocupante_email,
                up.es_titular,
                DATE_FORMAT(up.vigente_desde, '%d/%m/%Y') AS ocupante_desde_fmt,
                -- Supervisor directo (ocupante titular del puesto superior)
                sup_u.usuario_id AS supervisor_id,
                sup_u.usuario_nombre AS supervisor_nombre,
                sup.nombre AS supervisor_puesto,
                -- Métricas del organigrama
                (SELECT COUNT(*) FROM ml__usuarios_posiciones up_cnt 
                 WHERE up_cnt.posicion_id = p.posicion_id 
                   AND (up_cnt.vigente_hasta IS NULL OR up_cnt.vigente_hasta >= CURDATE())) AS total_ocupantes,
                (SELECT COUNT(*) FROM ml__posiciones p_sub 
                 WHERE p_sub.posicion_superior_id = p.posicion_id 
                   AND p_sub.tabla_estado_registro_id = 1) AS total_subordinados
            FROM ml__posiciones p
            LEFT JOIN ml__posiciones sup ON sup.posicion_id = p.posicion_superior_id
            -- Ocupante titular actual
            LEFT JOIN ml__usuarios_posiciones up ON up.posicion_id = p.posicion_id 
                 AND up.es_titular = 1 
                 AND (up.vigente_hasta IS NULL OR up.vigente_hasta >= CURDATE())
            LEFT JOIN conf__usuarios u ON u.usuario_id = up.usuario_id
            -- Supervisor (titular del puesto superior)
            LEFT JOIN ml__usuarios_posiciones sup_up ON sup_up.posicion_id = sup.posicion_id 
                 AND sup_up.es_titular = 1 
                 AND (sup_up.vigente_hasta IS NULL OR sup_up.vigente_hasta >= CURDATE())
            LEFT JOIN conf__usuarios sup_u ON sup_u.usuario_id = sup_up.usuario_id
            WHERE {$where_sql}
            ORDER BY 
                CASE WHEN p.posicion_superior_id IS NULL THEN 0 ELSE 1 END ASC,
                p.area_departamento ASC,
                p.nombre ASC";

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
 * Obtener detalle completo de una posición con historial de ocupantes.
 */
function posicion_obtener(mysqli $conexion, int $posicion_id, int $empresa_id): ?array
{
    $sql = "SELECT 
                p.posicion_id,
                p.empresa_id,
                p.codigo,
                p.nombre,
                p.area_departamento,
                p.descripcion,
                p.posicion_superior_id,
                sup.codigo AS superior_codigo,
                sup.nombre AS superior_nombre,
                p.tabla_estado_registro_id AS estado_id
            FROM ml__posiciones p
            LEFT JOIN ml__posiciones sup ON sup.posicion_id = p.posicion_superior_id
            WHERE p.posicion_id = ? AND p.empresa_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $posicion_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $posicion = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$posicion) {
        return null;
    }

    // Historial de ocupantes
    $sql_oc = "SELECT 
                    up.usuario_posicion_id,
                    up.usuario_id,
                    u.usuario_nombre,
                    u.email,
                    up.es_titular,
                    DATE_FORMAT(up.vigente_desde, '%d/%m/%Y') AS vigente_desde_fmt,
                    DATE_FORMAT(up.vigente_hasta, '%d/%m/%Y') AS vigente_hasta_fmt,
                    CASE 
                        WHEN up.vigente_hasta IS NULL OR up.vigente_hasta >= CURDATE() THEN 1 
                        ELSE 0 
                    END AS es_activo
               FROM ml__usuarios_posiciones up
               INNER JOIN conf__usuarios u ON u.usuario_id = up.usuario_id
               WHERE up.posicion_id = ?
               ORDER BY (up.vigente_hasta IS NULL) DESC, up.vigente_desde DESC";

    $stmt_oc = mysqli_prepare($conexion, $sql_oc);
    mysqli_stmt_bind_param($stmt_oc, "i", $posicion_id);
    mysqli_stmt_execute($stmt_oc);
    $res_oc = mysqli_stmt_get_result($stmt_oc);

    $ocupantes = [];
    while ($oc = mysqli_fetch_assoc($res_oc)) {
        $ocupantes[] = $oc;
    }
    mysqli_stmt_close($stmt_oc);
    $posicion['ocupantes'] = $ocupantes;

    // Puestos que le reportan directamente
    $sql_sub = "SELECT posicion_id, codigo, nombre, area_departamento 
                FROM ml__posiciones 
                WHERE posicion_superior_id = ? AND tabla_estado_registro_id = 1
                ORDER BY nombre ASC";
    $stmt_sub = mysqli_prepare($conexion, $sql_sub);
    mysqli_stmt_bind_param($stmt_sub, "i", $posicion_id);
    mysqli_stmt_execute($stmt_sub);
    $res_sub = mysqli_stmt_get_result($stmt_sub);

    $subordinados = [];
    while ($sb = mysqli_fetch_assoc($res_sub)) {
        $subordinados[] = $sb;
    }
    mysqli_stmt_close($stmt_sub);
    $posicion['subordinados'] = $subordinados;

    return $posicion;
}

/**
 * Validar si asignar un superior crearía un bucle/ciclo infinito en la jerarquía.
 */
function posicion_validar_ciclo(mysqli $conexion, int $posicion_id, int $posicion_superior_id): bool
{
    if ($posicion_id <= 0 || $posicion_superior_id <= 0) {
        return false;
    }
    if ($posicion_id === $posicion_superior_id) {
        return true;
    }

    $cursor = $posicion_superior_id;
    $visitados = [$posicion_id => true];

    while ($cursor > 0) {
        if (isset($visitados[$cursor])) {
            return true; // Bucle detectado
        }
        $visitados[$cursor] = true;

        $sql = "SELECT posicion_superior_id FROM ml__posiciones WHERE posicion_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $cursor);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $r = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        $cursor = !empty($r['posicion_superior_id']) ? (int)$r['posicion_superior_id'] : 0;
    }

    return false;
}

/**
 * Crear o editar una posición.
 */
function posicion_guardar(mysqli $conexion, int $empresa_id, array $datos, int $usuario_accion_id): array
{
    $posicion_id = intval($datos['posicion_id'] ?? 0);
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $area_departamento = trim($datos['area_departamento'] ?? '');
    $descripcion = !empty($datos['descripcion']) ? trim($datos['descripcion']) : null;
    $posicion_superior_id = !empty($datos['posicion_superior_id']) ? intval($datos['posicion_superior_id']) : null;

    if (empty($codigo) || empty($nombre) || empty($area_departamento)) {
        return ['success' => false, 'message' => 'Código, nombre y área/departamento son obligatorios.'];
    }

    // Validar código único en la empresa
    $sql_chk = "SELECT posicion_id FROM ml__posiciones WHERE empresa_id = ? AND codigo = ? AND posicion_id != ? LIMIT 1";
    $stmt_chk = mysqli_prepare($conexion, $sql_chk);
    mysqli_stmt_bind_param($stmt_chk, "isi", $empresa_id, $codigo, $posicion_id);
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);
    if (mysqli_fetch_assoc($res_chk)) {
        mysqli_stmt_close($stmt_chk);
        return ['success' => false, 'message' => "El código de posición '{$codigo}' ya existe en esta empresa."];
    }
    mysqli_stmt_close($stmt_chk);

    // Validar jerarquía circular
    if ($posicion_id > 0 && $posicion_superior_id !== null) {
        if (posicion_validar_ciclo($conexion, $posicion_id, $posicion_superior_id)) {
            return ['success' => false, 'message' => 'No se puede asignar como superior a sí mismo ni a un puesto subordinado (evitar ciclo en el organigrama).'];
        }
    }

    if ($posicion_id > 0) {
        // Actualizar
        $sql = "UPDATE ml__posiciones 
                SET codigo = ?, nombre = ?, area_departamento = ?, descripcion = ?, posicion_superior_id = ?, actualizado_por = ?
                WHERE posicion_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssssiiii", $codigo, $nombre, $area_departamento, $descripcion, $posicion_superior_id, $usuario_accion_id, $posicion_id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Error al actualizar posición: ' . mysqli_error($conexion)];
        }

        return ['success' => true, 'posicion_id' => $posicion_id, 'message' => 'Posición actualizada correctamente.'];
    } else {
        // Crear
        $sql = "INSERT INTO ml__posiciones 
                (empresa_id, codigo, nombre, area_departamento, descripcion, posicion_superior_id, tabla_estado_registro_id, creado_por, actualizado_por)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "issssiii", $empresa_id, $codigo, $nombre, $area_departamento, $descripcion, $posicion_superior_id, $usuario_accion_id, $usuario_accion_id);
        $ok = mysqli_stmt_execute($stmt);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Error al crear posición: ' . mysqli_error($conexion)];
        }

        return ['success' => true, 'posicion_id' => $nuevo_id, 'message' => 'Posición creada con éxito en el organigrama.'];
    }
}

/**
 * Asignar un usuario a una posición (ocupación del puesto).
 */
function posicion_asignar_usuario(
    mysqli $conexion,
    int $posicion_id,
    int $usuario_id,
    string $vigente_desde,
    int $es_titular,
    int $usuario_accion_id
): array {
    if ($posicion_id <= 0 || $usuario_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Posición, usuario y fecha de vigencia son obligatorios.'];
    }

    // Verificar si ya está asignado activamente
    $sql_chk = "SELECT usuario_posicion_id FROM ml__usuarios_posiciones 
                WHERE posicion_id = ? AND usuario_id = ? AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE()) LIMIT 1";
    $stmt_chk = mysqli_prepare($conexion, $sql_chk);
    mysqli_stmt_bind_param($stmt_chk, "ii", $posicion_id, $usuario_id);
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);
    if (mysqli_fetch_assoc($res_chk)) {
        mysqli_stmt_close($stmt_chk);
        return ['success' => false, 'message' => 'El usuario ya ocupa activamente esta posición.'];
    }
    mysqli_stmt_close($stmt_chk);

    mysqli_begin_transaction($conexion);
    try {
        // Si entra como titular, cerrar la vigencia del titular previo si existiera
        if ($es_titular === 1) {
            $ayer = date('Y-m-d', strtotime($vigente_desde . ' -1 day'));
            $sql_close = "UPDATE ml__usuarios_posiciones 
                          SET vigente_hasta = ?, actualizado_por = ?
                          WHERE posicion_id = ? AND es_titular = 1 AND vigente_hasta IS NULL";
            $stmt_cl = mysqli_prepare($conexion, $sql_close);
            mysqli_stmt_bind_param($stmt_cl, "sii", $ayer, $usuario_accion_id, $posicion_id);
            mysqli_stmt_execute($stmt_cl);
            mysqli_stmt_close($stmt_cl);
        }

        // Insertar nueva asignación
        $sql_ins = "INSERT INTO ml__usuarios_posiciones 
                    (posicion_id, usuario_id, es_titular, vigente_desde, vigente_hasta, tabla_estado_registro_id, creado_por, actualizado_por)
                    VALUES (?, ?, ?, ?, NULL, 1, ?, ?)";
        $stmt_ins = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param($stmt_ins, "iiisii", $posicion_id, $usuario_id, $es_titular, $vigente_desde, $usuario_accion_id, $usuario_accion_id);
        mysqli_stmt_execute($stmt_ins);
        $up_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_ins);

        mysqli_commit($conexion);
        return ['success' => true, 'usuario_posicion_id' => $up_id, 'message' => 'Usuario asignado exitosamente al puesto.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al asignar usuario a la posición: ' . $e->getMessage()];
    }
}

/**
 * Finalizar la ocupación de un usuario en un puesto.
 */
function posicion_finalizar_ocupacion(
    mysqli $conexion,
    int $usuario_posicion_id,
    string $vigente_hasta,
    int $usuario_accion_id
): array {
    if ($usuario_posicion_id <= 0 || empty($vigente_hasta)) {
        return ['success' => false, 'message' => 'ID de ocupación y fecha de finalización requeridos.'];
    }

    $sql = "UPDATE ml__usuarios_posiciones 
            SET vigente_hasta = ?, actualizado_por = ?
            WHERE usuario_posicion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $vigente_hasta, $usuario_accion_id, $usuario_posicion_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => 'Error al finalizar ocupación: ' . mysqli_error($conexion)];
    }

    return ['success' => true, 'message' => 'Ocupación de puesto finalizada correctamente.'];
}

/**
 * Obtener la jerarquía del organigrama en formato de árbol anidado para visualización interactiva.
 */
function organigrama_obtener_arbol(mysqli $conexion, int $empresa_id): array
{
    $posiciones = posiciones_listar($conexion, $empresa_id, ['estado_id' => 1]);

    // Indexar por ID
    $index = [];
    $hijos_por_padre = [];

    foreach ($posiciones as $p) {
        $id = (int)$p['posicion_id'];
        $padre_id = !empty($p['posicion_superior_id']) ? (int)$p['posicion_superior_id'] : 0;
        $p['subordinados_nodos'] = [];
        $index[$id] = $p;
        $hijos_por_padre[$padre_id][] = $id;
    }

    // Función recursiva para armar el árbol
    $construir_nodo = function ($pos_id) use (&$construir_nodo, &$index, &$hijos_por_padre) {
        $nodo = $index[$pos_id];
        $hijos_ids = $hijos_por_padre[$pos_id] ?? [];
        foreach ($hijos_ids as $hid) {
            $nodo['subordinados_nodos'][] = $construir_nodo($hid);
        }
        return $nodo;
    };

    $arbol = [];
    $raices = $hijos_por_padre[0] ?? [];
    foreach ($raices as $rid) {
        $arbol[] = $construir_nodo($rid);
    }

    return $arbol;
}

/**
 * Obtener el supervisor directo y la cadena de mando completa de un usuario.
 */
function usuario_obtener_supervisor(mysqli $conexion, int $usuario_id, int $empresa_id = 0): ?array
{
    if ($usuario_id <= 0) {
        return null;
    }

    $where_empresa = $empresa_id > 0 ? "AND p.empresa_id = {$empresa_id}" : "";

    $sql = "SELECT 
                p.posicion_id,
                p.codigo AS puesto_codigo,
                p.nombre AS puesto_nombre,
                p.area_departamento,
                p.posicion_superior_id,
                sup_p.codigo AS superior_puesto_codigo,
                sup_p.nombre AS superior_puesto_nombre,
                sup_u.usuario_id AS supervisor_id,
                sup_u.usuario_nombre AS supervisor_nombre,
                sup_u.email AS supervisor_email
            FROM ml__usuarios_posiciones up
            INNER JOIN ml__posiciones p ON p.posicion_id = up.posicion_id
            LEFT JOIN ml__posiciones sup_p ON sup_p.posicion_id = p.posicion_superior_id
            LEFT JOIN ml__usuarios_posiciones sup_up ON sup_up.posicion_id = sup_p.posicion_id 
                 AND sup_up.es_titular = 1 
                 AND (sup_up.vigente_hasta IS NULL OR sup_up.vigente_hasta >= CURDATE())
            LEFT JOIN conf__usuarios sup_u ON sup_u.usuario_id = sup_up.usuario_id
            WHERE up.usuario_id = ? 
              AND (up.vigente_hasta IS NULL OR up.vigente_hasta >= CURDATE())
              {$where_empresa}
            ORDER BY up.es_titular DESC, up.vigente_desde DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $datos = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $datos;
}

/**
 * Obtener el usuario titular activo de una posición (o el ocupante vigente más reciente).
 */
function posicion_obtener_titular(mysqli $conexion, int $posicion_id): ?array
{
    if ($posicion_id <= 0) {
        return null;
    }

    $sql = "SELECT 
                u.usuario_id,
                u.usuario_nombre,
                u.email,
                up.es_titular,
                up.usuario_posicion_id,
                p.posicion_id,
                p.codigo AS puesto_codigo,
                p.nombre AS puesto_nombre,
                p.area_departamento,
                p.posicion_superior_id
            FROM ml__usuarios_posiciones up
            INNER JOIN conf__usuarios u ON u.usuario_id = up.usuario_id
            INNER JOIN ml__posiciones p ON p.posicion_id = up.posicion_id
            WHERE up.posicion_id = ?
              AND (up.vigente_hasta IS NULL OR up.vigente_hasta >= CURDATE())
              AND u.tabla_estado_registro_id = 1
            ORDER BY up.es_titular DESC, up.vigente_desde DESC, up.usuario_posicion_id DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $posicion_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Obtener la posición superior y el supervisor activo de una posición dada.
 */
function posicion_obtener_supervisor_de_posicion(mysqli $conexion, int $posicion_id): ?array
{
    if ($posicion_id <= 0) {
        return null;
    }

    $sql = "SELECT 
                sup_p.posicion_id AS superior_posicion_id,
                sup_p.codigo AS superior_codigo,
                sup_p.nombre AS superior_nombre,
                sup_p.area_departamento AS superior_area,
                sup_u.usuario_id AS supervisor_id,
                sup_u.usuario_nombre AS supervisor_nombre,
                sup_u.email AS supervisor_email
            FROM ml__posiciones p
            INNER JOIN ml__posiciones sup_p ON sup_p.posicion_id = p.posicion_superior_id
            LEFT JOIN ml__usuarios_posiciones sup_up ON sup_up.posicion_id = sup_p.posicion_id 
                 AND sup_up.es_titular = 1 
                 AND (sup_up.vigente_hasta IS NULL OR sup_up.vigente_hasta >= CURDATE())
            LEFT JOIN conf__usuarios sup_u ON sup_u.usuario_id = sup_up.usuario_id
            WHERE p.posicion_id = ?
              AND sup_p.tabla_estado_registro_id = 1
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $posicion_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Obtener la posición activa en la que se desempeña un usuario.
 */
function usuario_obtener_posicion_activa(mysqli $conexion, int $usuario_id, int $empresa_id = 0): ?array
{
    if ($usuario_id <= 0) {
        return null;
    }

    $where_empresa = $empresa_id > 0 ? "AND p.empresa_id = {$empresa_id}" : "";

    $sql = "SELECT 
                p.posicion_id,
                p.codigo AS puesto_codigo,
                p.nombre AS puesto_nombre,
                p.area_departamento,
                p.posicion_superior_id,
                up.usuario_posicion_id,
                up.es_titular
            FROM ml__usuarios_posiciones up
            INNER JOIN ml__posiciones p ON p.posicion_id = up.posicion_id
            WHERE up.usuario_id = ?
              AND (up.vigente_hasta IS NULL OR up.vigente_hasta >= CURDATE())
              AND p.tabla_estado_registro_id = 1
              {$where_empresa}
            ORDER BY up.es_titular DESC, up.vigente_desde DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

