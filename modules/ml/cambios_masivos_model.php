<?php
/**
 * Modelo de Datos: Cambios Masivos e Impacto (Página 363 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/cambios_masivos_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/cargas_masivas_service.php';

/**
 * Listar solicitudes de cambios masivos
 */
function cambios_masivos_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    $where = ["n.empresa_id = {$empresa_id}"];

    if (!empty($filtros['estado_id'])) {
        $estado_id = (int)$filtros['estado_id'];
        $where[] = "cm.tabla_estado_registro_id = {$estado_id}";
    }

    if (!empty($filtros['norma_id'])) {
        $norma_id = (int)$filtros['norma_id'];
        $where[] = "n.norma_id = {$norma_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                cm.cambio_masivo_id,
                cm.norma_version_id,
                n.norma_id,
                n.codigo as norma_codigo,
                n.titulo as norma_titulo,
                nv.version as numero_version,
                cm.descripcion as cambio_descripcion,
                cm.requiere_aprobacion,
                cm.tabla_estado_registro_id as estado_id,
                CASE cm.tabla_estado_registro_id
                    WHEN 1 THEN 'Pendiente Aprobación'
                    WHEN 2 THEN 'Aprobado'
                    WHEN 3 THEN 'Aplicado'
                    ELSE 'Rechazado'
                END as estado_nombre,
                DATE_FORMAT(cm.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                DATE_FORMAT(cm.aprobado_en, '%d/%m/%Y %H:%i') as aprobado_en_fmt,
                DATE_FORMAT(cm.aplicado_en, '%d/%m/%Y %H:%i') as aplicado_en_fmt,
                us.usuario_nombre as solicitado_por_nombre,
                ua.usuario_nombre as aprobado_por_nombre,
                COUNT(cmd.cambio_masivo_detalle_id) as total_entidades,
                COALESCE(SUM(cmd.ciclos_afectados), 0) as total_ciclos_afectados,
                COALESCE(SUM(cmd.tareas_afectadas), 0) as total_tareas_afectadas
            FROM ml__cambios_masivos cm
            LEFT JOIN ml__normas_versiones nv ON nv.norma_version_id = cm.norma_version_id
            LEFT JOIN ml__normas n ON n.norma_id = nv.norma_id
            LEFT JOIN conf__usuarios us ON us.usuario_id = cm.solicitado_por
            LEFT JOIN conf__usuarios ua ON ua.usuario_id = cm.aprobado_por
            LEFT JOIN ml__cambios_masivos_detalles cmd ON cmd.cambio_masivo_id = cm.cambio_masivo_id
            WHERE {$where_sql}
            GROUP BY cm.cambio_masivo_id
            ORDER BY cm.cambio_masivo_id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar cambios masivos: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener desglose por entidad de un cambio masivo
 */
function cambios_masivos_obtener_detalles(mysqli $conexion, int $cambio_masivo_id): array {
    $sql = "SELECT 
                cmd.cambio_masivo_detalle_id,
                cmd.cambio_masivo_id,
                cmd.entidad_id,
                e.nombre as entidad_nombre,
                cmd.ciclos_afectados,
                cmd.tareas_afectadas
            FROM ml__cambios_masivos_detalles cmd
            INNER JOIN ml__entidades e ON e.entidad_id = cmd.entidad_id
            WHERE cmd.cambio_masivo_id = {$cambio_masivo_id}
            ORDER BY cmd.ciclos_afectados DESC, e.nombre ASC";

    $res = mysqli_query($conexion, $sql);
    $detalles = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $detalles[] = $r;
    }
    return $detalles;
}
