<?php
/**
 * Modelo de Datos para Obligaciones Asignadas y Orígenes Consolidados (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_asignadas_model.php
 */

declare(strict_types=1);

/**
 * Listar obligaciones asignadas a entidades por el motor de herencia.
 */
function obligaciones_asignadas_listar(mysqli $conexion, array $filtros = []): array
{
    $sql = "SELECT oas.obligacion_asignada_id, oas.obligacion_prevaleciente_id, oas.entidad_id, oas.empresa_id,
                   oas.vigente_desde, oas.vigente_hasta, oas.tabla_estado_registro_id,
                   CASE WHEN oas.tabla_estado_registro_id = 1 THEN 'Activa' ELSE 'Inactiva' END AS estado_nombre,
                   e.codigo AS entidad_codigo, e.nombre AS entidad_nombre, et.nombre AS entidad_tipo_nombre,
                   emp.empresa AS empresa_nombre,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion, o.articulo_referencia,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo, nt.es_legal,
                   p.codigo AS periodicidad_codigo, p.nombre AS periodicidad_nombre,
                   c.nombre AS criticidad_nombre, COALESCE(c.peso, 1) AS criticidad_peso,
                   (SELECT COUNT(*) 
                    FROM ml__obligaciones_asignadas_origenes oaso 
                    WHERE oaso.obligacion_asignada_id = oas.obligacion_asignada_id) AS total_origenes,
                   (SELECT exc.excepcion_id 
                    FROM ml__excepciones exc 
                    WHERE exc.obligacion_asignada_id = oas.obligacion_asignada_id 
                      AND exc.tabla_estado_registro_id = 2089 
                      AND CURRENT_DATE BETWEEN exc.vigente_desde AND exc.vigente_hasta 
                    LIMIT 1) AS excepcion_activa_id
            FROM ml__obligaciones_asignadas oas
            INNER JOIN ml__entidades e ON e.entidad_id = oas.entidad_id
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            INNER JOIN conf__empresas emp ON emp.empresa_id = oas.empresa_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            INNER JOIN ml__periodicidades p ON p.periodicidad_id = o.periodicidad_id
            LEFT JOIN ml__criticidades c ON c.criticidad_id = o.criticidad_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filtros['empresa_id'])) {
        $sql .= " AND oas.empresa_id = ?";
        $params[] = intval($filtros['empresa_id']);
        $types .= "i";
    }

    if (!empty($filtros['entidad_id'])) {
        $sql .= " AND oas.entidad_id = ?";
        $params[] = intval($filtros['entidad_id']);
        $types .= "i";
    }

    if (!empty($filtros['norma_id'])) {
        $sql .= " AND o.norma_id = ?";
        $params[] = intval($filtros['norma_id']);
        $types .= "i";
    }

    if (isset($filtros['tabla_estado_registro_id']) && $filtros['tabla_estado_registro_id'] !== '') {
        $sql .= " AND oas.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY e.nombre ASC, o.codigo ASC";

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
 * Obtener detalle de una obligación asignada.
 */
function obligacion_asignada_obtener(mysqli $conexion, int $id): ?array
{
    $sql = "SELECT oas.*,
                   e.codigo AS entidad_codigo, e.nombre AS entidad_nombre, et.nombre AS entidad_tipo_nombre,
                   emp.empresa AS empresa_nombre,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion, o.articulo_referencia,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo, nt.es_legal,
                   p.nombre AS periodicidad_nombre, c.nombre AS criticidad_nombre
            FROM ml__obligaciones_asignadas oas
            INNER JOIN ml__entidades e ON e.entidad_id = oas.entidad_id
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            INNER JOIN conf__empresas emp ON emp.empresa_id = oas.empresa_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            INNER JOIN ml__periodicidades p ON p.periodicidad_id = o.periodicidad_id
            LEFT JOIN ml__criticidades c ON c.criticidad_id = o.criticidad_id
            WHERE oas.obligacion_asignada_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Listar todos los orígenes que contribuyen y sustentan una obligación asignada (Caso C01, Caso C11).
 */
function obligacion_asignada_origenes_listar(mysqli $conexion, int $obligacion_asignada_id): array
{
    $sql = "SELECT oaso.obligacion_asignada_origen_id, oaso.obligacion_id, oaso.creado_en,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion, o.articulo_referencia,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo, nt.nombre AS norma_tipo_nombre, nt.es_legal,
                   COALESCE(c.nombre, 'Estándar') AS criticidad_nombre, COALESCE(c.peso, 1) AS criticidad_peso,
                   p.nombre AS periodicidad_nombre
            FROM ml__obligaciones_asignadas_origenes oaso
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oaso.obligacion_id
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            INNER JOIN ml__periodicidades p ON p.periodicidad_id = o.periodicidad_id
            LEFT JOIN ml__criticidades c ON c.criticidad_id = o.criticidad_id
            WHERE oaso.obligacion_asignada_id = ?
            ORDER BY nt.es_legal DESC, c.peso DESC, o.codigo ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $obligacion_asignada_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Desactivar o suspender manualmente una asignación (con fecha de fin).
 */
function obligacion_asignada_desactivar(mysqli $conexion, int $id, int $usuario_id): array
{
    $sql = "UPDATE ml__obligaciones_asignadas 
            SET tabla_estado_registro_id = 2, vigente_hasta = CURRENT_DATE, actualizado_en = CURRENT_TIMESTAMP, actualizado_por = ?
            WHERE obligacion_asignada_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => "Error al desactivar asignación: {$err}"];
    }

    return ['success' => true, 'message' => 'Obligación asignada desactivada con éxito.'];
}

/**
 * Combos para filtros de obligaciones asignadas.
 */
function obligaciones_asignadas_combos(mysqli $conexion, int $empresa_id): array
{
    // Entidades de la empresa
    $sql_e = "SELECT DISTINCT e.entidad_id, e.codigo, e.nombre, et.nombre AS tipo_nombre
              FROM ml__entidades e
              INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
              INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id AND ee.empresa_id = ?
              WHERE e.tabla_estado_registro_id = 1
              ORDER BY e.es_raiz DESC, e.nombre ASC";
    $stmt_e = mysqli_prepare($conexion, $sql_e);
    mysqli_stmt_bind_param($stmt_e, "i", $empresa_id);
    mysqli_stmt_execute($stmt_e);
    $res_e = mysqli_stmt_get_result($stmt_e);
    $entidades = mysqli_fetch_all($res_e, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_e);

    // Normas presentes en las asignaciones
    $sql_n = "SELECT DISTINCT n.norma_id, n.codigo, n.titulo
              FROM ml__obligaciones_asignadas oas
              INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
              INNER JOIN ml__normas n ON n.norma_id = o.norma_id
              WHERE oas.empresa_id = ?
              ORDER BY n.codigo ASC";
    $stmt_n = mysqli_prepare($conexion, $sql_n);
    mysqli_stmt_bind_param($stmt_n, "i", $empresa_id);
    mysqli_stmt_execute($stmt_n);
    $res_n = mysqli_stmt_get_result($stmt_n);
    $normas = mysqli_fetch_all($res_n, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_n);

    return [
        'entidades' => $entidades,
        'normas' => $normas
    ];
}
