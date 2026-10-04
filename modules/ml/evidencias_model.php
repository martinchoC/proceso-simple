<?php
/**
 * Modelo de Datos: Bandeja de Evidencias (Página 352 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/evidencias_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ejecucion_seguimiento_service.php';

/**
 * Listar evidencias adjuntas con filtros
 */
function evidencias_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    $where = ["evi.empresa_id = {$empresa_id}"];

    if (!empty($filtros['entidad_id'])) {
        $entidad_id = (int)$filtros['entidad_id'];
        $where[] = "c.entidad_id = {$entidad_id}";
    }

    if (!empty($filtros['ciclo_id'])) {
        $ciclo_id = (int)$filtros['ciclo_id'];
        $where[] = "ct.ciclo_id = {$ciclo_id}";
    }

    if (!empty($filtros['es_comprobante'])) {
        $where[] = "evi.es_comprobante = 1";
    }

    if (!empty($filtros['estado_id'])) {
        $estado_id = (int)$filtros['estado_id'];
        $where[] = "evi.tabla_estado_registro_id = {$estado_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                evi.evidencia_id,
                evi.ciclo_tarea_id,
                evi.titulo,
                evi.descripcion,
                evi.archivo_ref,
                evi.hash,
                evi.es_comprobante,
                DATE_FORMAT(evi.valida_hasta, '%d/%m/%Y') as valida_hasta_fmt,
                evi.valida_hasta,
                DATE_FORMAT(evi.retener_hasta, '%d/%m/%Y') as retener_hasta_fmt,
                evi.retener_hasta,
                evi.confidencial,
                evi.tabla_estado_registro_id as estado_id,
                est.tabla_estado_registro as estado_nombre,
                DATE_FORMAT(evi.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                u.usuario_nombre as cargado_por_nombre,
                ct.titulo as tarea_titulo,
                c.ciclo_id,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                e.nombre as entidad_nombre,
                -- Control de vigencia: vencida o vigente
                CASE 
                    WHEN evi.valida_hasta IS NOT NULL AND evi.valida_hasta < CURRENT_DATE THEN 'Vencida'
                    WHEN evi.valida_hasta IS NOT NULL THEN 'Vigente'
                    ELSE 'Permanente'
                END as vigencia_estado
            FROM ml__evidencias evi
            INNER JOIN ml__ciclos_tareas ct ON ct.ciclo_tarea_id = evi.ciclo_tarea_id
            INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = evi.tabla_estado_registro_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = evi.creado_por
            WHERE {$where_sql}
            ORDER BY evi.evidencia_id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar evidencias: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener detalle de una evidencia
 */
function evidencias_obtener_por_id(mysqli $conexion, int $evidencia_id, int $empresa_id): ?array {
    $sql = "SELECT 
                evi.*,
                DATE_FORMAT(evi.valida_hasta, '%d/%m/%Y') as valida_hasta_fmt,
                DATE_FORMAT(evi.retener_hasta, '%d/%m/%Y') as retener_hasta_fmt,
                DATE_FORMAT(evi.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                est.tabla_estado_registro as estado_nombre,
                u.usuario_nombre as cargado_por_nombre,
                ct.titulo as tarea_titulo,
                c.ciclo_id,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                e.nombre as entidad_nombre
            FROM ml__evidencias evi
            INNER JOIN ml__ciclos_tareas ct ON ct.ciclo_tarea_id = evi.ciclo_tarea_id
            INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = evi.tabla_estado_registro_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = evi.creado_por
            WHERE evi.evidencia_id = ? AND evi.empresa_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $evidencia_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $evidencia = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $evidencia ?: null;
}
