<?php
/**
 * Modelo de Datos para Matriz de Obligaciones y Aplicabilidad (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_model.php
 */

declare(strict_types=1);

/**
 * Listar obligaciones legales y corporativas.
 */
function obligaciones_listar(mysqli $conexion, array $filtros = []): array
{
    $sql = "SELECT o.obligacion_id, o.norma_id, n.codigo AS norma_codigo, n.titulo AS norma_titulo,
                   o.empresa_id, emp.empresa AS empresa_nombre,
                   o.articulo_referencia, o.codigo, o.descripcion,
                   o.periodicidad_id, p.codigo AS periodicidad_codigo, p.nombre AS periodicidad_nombre, p.es_evento,
                   o.vencimiento_regla_id, vr.codigo AS vencimiento_regla_codigo, vr.nombre AS vencimiento_regla_nombre,
                   o.criticidad_id, c.nombre AS criticidad_nombre, c.peso AS criticidad_peso,
                   o.requiere_revision, o.requiere_evidencia, o.retencion_evidencias_meses,
                   o.vigente_desde, o.vigente_hasta,
                   o.tabla_estado_registro_id,
                   er.estado_registro,
                   (SELECT COUNT(*) FROM ml__obligaciones_tipos_entidades ote WHERE ote.obligacion_id = o.obligacion_id) AS total_tipos_entidad,
                   (SELECT COUNT(*) FROM ml__obligaciones_dependencias od WHERE od.obligacion_id = o.obligacion_id) AS total_predecesoras,
                   (SELECT COUNT(*) FROM ml__tareas_plantillas tp WHERE tp.obligacion_id = o.obligacion_id) AS total_plantillas
            FROM ml__obligaciones o
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            LEFT JOIN conf__empresas emp ON emp.empresa_id = o.empresa_id
            INNER JOIN ml__periodicidades p ON p.periodicidad_id = o.periodicidad_id
            INNER JOIN ml__vencimientos_reglas vr ON vr.vencimiento_regla_id = o.vencimiento_regla_id
            LEFT JOIN ml__criticidades c ON c.criticidad_id = o.criticidad_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = o.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filtros['norma_id'])) {
        $sql .= " AND o.norma_id = ?";
        $params[] = intval($filtros['norma_id']);
        $types .= "i";
    }

    if (!empty($filtros['periodicidad_id'])) {
        $sql .= " AND o.periodicidad_id = ?";
        $params[] = intval($filtros['periodicidad_id']);
        $types .= "i";
    }

    if (!empty($filtros['criticidad_id'])) {
        $sql .= " AND o.criticidad_id = ?";
        $params[] = intval($filtros['criticidad_id']);
        $types .= "i";
    }

    if (!empty($filtros['tabla_estado_registro_id'])) {
        $sql .= " AND o.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY o.vigente_desde DESC, o.obligacion_id DESC";

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
 * Obtener obligación por ID.
 */
function obligaciones_obtener_por_id(mysqli $conexion, int $obligacion_id): ?array
{
    $sql = "SELECT o.*, n.codigo AS norma_codigo, n.titulo AS norma_titulo, er.estado_registro
            FROM ml__obligaciones o
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = o.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE o.obligacion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $obligacion_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Crear nueva obligación (Estado inicial 2061 Borrador).
 */
function obligaciones_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $norma_id = intval($datos['norma_id'] ?? 0);
    $empresa_id = !empty($datos['empresa_id']) ? intval($datos['empresa_id']) : null;
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $articulo_referencia = trim($datos['articulo_referencia'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $periodicidad_id = intval($datos['periodicidad_id'] ?? 0);
    $vencimiento_regla_id = intval($datos['vencimiento_regla_id'] ?? 0);
    $criticidad_id = !empty($datos['criticidad_id']) ? intval($datos['criticidad_id']) : null;
    $requiere_revision = !empty($datos['requiere_revision']) ? 1 : 0;
    $requiere_evidencia = !empty($datos['requiere_evidencia']) ? 1 : 0;
    $retencion_meses = !empty($datos['retencion_evidencias_meses']) ? intval($datos['retencion_evidencias_meses']) : 60;
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if ($norma_id <= 0 || empty($codigo) || empty($descripcion) || $periodicidad_id <= 0 || $vencimiento_regla_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Norma, código, descripción, periodicidad, regla de vencimiento y fecha son obligatorios.'];
    }

    // Validar unicidad uk_obl_norma_codigo
    $check_q = mysqli_prepare($conexion, "SELECT obligacion_id FROM ml__obligaciones WHERE norma_id = ? AND codigo = ?");
    mysqli_stmt_bind_param($check_q, "is", $norma_id, $codigo);
    mysqli_stmt_execute($check_q);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check_q))) {
        mysqli_stmt_close($check_q);
        return ['success' => false, 'message' => "Ya existe una obligación con el código '{$codigo}' en esta norma."];
    }
    mysqli_stmt_close($check_q);

    // Estado inicial: 2061 (Borrador)
    $sql = "INSERT INTO ml__obligaciones 
            (norma_id, empresa_id, articulo_referencia, codigo, descripcion, periodicidad_id, vencimiento_regla_id,
             criticidad_id, requiere_revision, requiere_evidencia, retencion_evidencias_meses, vigente_desde, vigente_hasta,
             tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 2061, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "iisssiiiiissii",
        $norma_id, $empresa_id, $articulo_referencia, $codigo, $descripcion,
        $periodicidad_id, $vencimiento_regla_id, $criticidad_id,
        $requiere_revision, $requiere_evidencia, $retencion_meses,
        $vigente_desde, $vigente_hasta, $usuario_id
    );
    $ok = mysqli_stmt_execute($stmt);
    $obl_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $obl_id, 'message' => 'Obligación creada exitosamente (Borrador).'];
    }
    return ['success' => false, 'message' => 'Error al crear obligación: ' . mysqli_error($conexion)];
}

/**
 * Actualizar datos de obligación.
 */
function obligaciones_actualizar(mysqli $conexion, int $obligacion_id, array $datos, int $usuario_id): array
{
    $articulo_referencia = trim($datos['articulo_referencia'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $periodicidad_id = intval($datos['periodicidad_id'] ?? 0);
    $vencimiento_regla_id = intval($datos['vencimiento_regla_id'] ?? 0);
    $criticidad_id = !empty($datos['criticidad_id']) ? intval($datos['criticidad_id']) : null;
    $requiere_revision = !empty($datos['requiere_revision']) ? 1 : 0;
    $requiere_evidencia = !empty($datos['requiere_evidencia']) ? 1 : 0;
    $retencion_meses = !empty($datos['retencion_evidencias_meses']) ? intval($datos['retencion_evidencias_meses']) : 60;
    $vigente_desde = trim($datos['vigente_desde'] ?? '');
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if (empty($descripcion) || $periodicidad_id <= 0 || $vencimiento_regla_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Descripción, periodicidad, regla de vencimiento y fecha son obligatorios.'];
    }

    $sql = "UPDATE ml__obligaciones 
            SET articulo_referencia = ?, descripcion = ?, periodicidad_id = ?, vencimiento_regla_id = ?,
                criticidad_id = ?, requiere_revision = ?, requiere_evidencia = ?, retencion_evidencias_meses = ?,
                vigente_desde = ?, vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
            WHERE obligacion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssiiiiissiii",
        $articulo_referencia, $descripcion, $periodicidad_id, $vencimiento_regla_id,
        $criticidad_id, $requiere_revision, $requiere_evidencia, $retencion_meses,
        $vigente_desde, $vigente_hasta, $usuario_id, $obligacion_id
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'message' => 'Obligación actualizada.'];
    return ['success' => false, 'message' => 'Error al actualizar obligación: ' . mysqli_error($conexion)];
}

/**
 * Cambiar estado de la obligación (2061 Borrador -> 2062 Pendiente -> 2063 Confirmado -> 2064 Eliminado/Suspendida).
 */
function obligaciones_cambiar_estado(mysqli $conexion, int $obligacion_id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__obligaciones SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE obligacion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $obligacion_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'message' => 'Estado de la obligación actualizado.'];
    return ['success' => false, 'message' => 'Error al cambiar estado: ' . mysqli_error($conexion)];
}

/**
 * Listar aplicabilidad a tipos de entidad (Caso C20).
 */
function obligaciones_tipos_entidades_listar(mysqli $conexion, int $obligacion_id): array
{
    $sql = "SELECT ote.*, et.nombre AS entidad_tipo_nombre
            FROM ml__obligaciones_tipos_entidades ote
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = ote.entidad_tipo_id
            WHERE ote.obligacion_id = ?
            ORDER BY et.nombre ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $obligacion_id);
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
 * Asignar aplicabilidad a un tipo de entidad con regla condicional opcional (Caso C20).
 */
function obligaciones_tipo_entidad_asignar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $obligacion_id = intval($datos['obligacion_id'] ?? 0);
    $entidad_tipo_id = intval($datos['entidad_tipo_id'] ?? 0);
    $regla_json = trim($datos['regla_json'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if ($obligacion_id <= 0 || $entidad_tipo_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Obligación, tipo de entidad y fecha de vigencia son obligatorios.'];
    }

    $sql = "INSERT INTO ml__obligaciones_tipos_entidades (obligacion_id, entidad_tipo_id, regla_json, vigente_desde, vigente_hasta, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iisssi", $obligacion_id, $entidad_tipo_id, $regla_json, $vigente_desde, $vigente_hasta, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'id' => $id, 'message' => 'Tipo de entidad asignado correctamente.'];
    return ['success' => false, 'message' => 'Error al asignar tipo de entidad: ' . mysqli_error($conexion)];
}

/**
 * Eliminar aplicabilidad de tipo de entidad.
 */
function obligaciones_tipo_entidad_eliminar(mysqli $conexion, int $id): array
{
    $sql = "DELETE FROM ml__obligaciones_tipos_entidades WHERE obligacion_tipo_entidad_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'message' => 'Tipo de entidad desvinculado.'];
    return ['success' => false, 'message' => 'Error al desvincular: ' . mysqli_error($conexion)];
}

/**
 * Listar dependencias entre obligaciones (Caso C24).
 */
function obligaciones_dependencias_listar(mysqli $conexion, int $obligacion_id): array
{
    $sql = "SELECT od.*, op.codigo AS predecesora_codigo, op.descripcion AS predecesora_descripcion
            FROM ml__obligaciones_dependencias od
            INNER JOIN ml__obligaciones op ON op.obligacion_id = od.obligacion_predecesora_id
            WHERE od.obligacion_id = ?
            ORDER BY od.obligacion_dependencia_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $obligacion_id);
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
 * Vincular dependencia de obligación (Caso C24).
 */
function obligaciones_dependencia_vincular(mysqli $conexion, array $datos, int $usuario_id): array
{
    $obligacion_id = intval($datos['obligacion_id'] ?? 0);
    $predecesora_id = intval($datos['obligacion_predecesora_id'] ?? 0);
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));

    if ($obligacion_id <= 0 || $predecesora_id <= 0) {
        return ['success' => false, 'message' => 'Debe indicar ambas obligaciones.'];
    }
    if ($obligacion_id === $predecesora_id) {
        return ['success' => false, 'message' => 'Una obligación no puede ser predecesora de sí misma.'];
    }

    $sql = "INSERT INTO ml__obligaciones_dependencias (obligacion_id, obligacion_predecesora_id, vigente_desde, creado_en, creado_por)
            VALUES (?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iisi", $obligacion_id, $predecesora_id, $vigente_desde, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'id' => $id, 'message' => 'Dependencia jurídica vinculada (Lógica Y).'];
    return ['success' => false, 'message' => 'Error al vincular dependencia: ' . mysqli_error($conexion)];
}

/**
 * Eliminar dependencia.
 */
function obligaciones_dependencia_eliminar(mysqli $conexion, int $id): array
{
    $sql = "DELETE FROM ml__obligaciones_dependencias WHERE obligacion_dependencia_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'message' => 'Dependencia eliminada.'];
    return ['success' => false, 'message' => 'Error al eliminar dependencia: ' . mysqli_error($conexion)];
}

/**
 * Listar alertas configuradas para una obligación.
 */
function obligaciones_alertas_listar(mysqli $conexion, int $obligacion_id): array
{
    $sql = "SELECT oa.* FROM ml__obligaciones_alertas oa WHERE oa.obligacion_id = ? ORDER BY oa.dias_antes ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $obligacion_id);
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
 * Guardar alerta previa.
 */
function obligaciones_alerta_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $obligacion_id = intval($datos['obligacion_id'] ?? 0);
    $dias_antes = intval($datos['dias_antes'] ?? 1);
    $destinatario = trim($datos['destinatario'] ?? 'R');
    $canal = trim($datos['canal'] ?? 'AMBOS');

    if ($obligacion_id <= 0 || $dias_antes < 0) {
        return ['success' => false, 'message' => 'Datos de alerta inválidos.'];
    }

    $sql = "INSERT INTO ml__obligaciones_alertas (obligacion_id, dias_antes, destinatario, canal, creado_en, creado_por)
            VALUES (?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iissi", $obligacion_id, $dias_antes, $destinatario, $canal, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'id' => $id, 'message' => 'Alerta anticipada configurada.'];
    return ['success' => false, 'message' => 'Error al configurar alerta: ' . mysqli_error($conexion)];
}

/**
 * Eliminar alerta.
 */
function obligaciones_alerta_eliminar(mysqli $conexion, int $id): array
{
    $sql = "DELETE FROM ml__obligaciones_alertas WHERE obligacion_alerta_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) return ['success' => true, 'message' => 'Alerta eliminada.'];
    return ['success' => false, 'message' => 'Error al eliminar alerta: ' . mysqli_error($conexion)];
}

/**
 * Combos para formulario de obligaciones.
 */
function obligaciones_combos(mysqli $conexion): array
{
    $normas = mysqli_query($conexion, "SELECT norma_id, codigo, titulo FROM ml__normas WHERE tabla_estado_registro_id = 2047 ORDER BY codigo ASC");
    $lista_n = [];
    while ($r = mysqli_fetch_assoc($normas)) $lista_n[] = $r;

    $periodicidades = periodicidades_listar($conexion);
    $reglas = vencimientos_reglas_listar($conexion);
    $criticidades = criticidades_listar($conexion);

    $entidades_tipos = mysqli_query($conexion, "SELECT entidad_tipo_id, codigo, nombre FROM ml__entidades_tipos WHERE tabla_estado_registro_id = 1 ORDER BY nombre ASC");
    $lista_et = [];
    while ($r = mysqli_fetch_assoc($entidades_tipos)) $lista_et[] = $r;

    return [
        'normas' => $lista_n,
        'periodicidades' => $periodicidades,
        'vencimientos_reglas' => $reglas,
        'criticidades' => $criticidades,
        'entidades_tipos' => $lista_et
    ];
}
