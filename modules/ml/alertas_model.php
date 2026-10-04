<?php
/**
 * Modelo de Datos: Consola de Alertas (Página 354 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/alertas_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ejecucion_seguimiento_service.php';

/**
 * Listar alertas preventivas y notificaciones
 */
function alertas_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    $where = ["a.empresa_id = {$empresa_id}"];

    if (!empty($filtros['canal'])) {
        $canal = mysqli_real_escape_string($conexion, $filtros['canal']);
        $where[] = "a.canal = '{$canal}'";
    }

    if (!empty($filtros['estado_id'])) {
        $estado_id = (int)$filtros['estado_id'];
        $where[] = "a.tabla_estado_registro_id = {$estado_id}";
    }

    if (!empty($filtros['usuario_id'])) {
        $usuario_id = (int)$filtros['usuario_id'];
        $where[] = "a.usuario_id = {$usuario_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                a.alerta_id,
                a.ciclo_id,
                a.ciclo_tarea_id,
                a.evidencia_id,
                a.usuario_id,
                a.canal,
                a.programada_para,
                DATE_FORMAT(a.programada_para, '%d/%m/%Y %H:%i') as programada_para_fmt,
                a.enviada_en,
                DATE_FORMAT(a.enviada_en, '%d/%m/%Y %H:%i') as enviada_en_fmt,
                a.tabla_estado_registro_id as estado_id,
                CASE a.tabla_estado_registro_id
                    WHEN 1 THEN 'Pendiente / Programada'
                    WHEN 2 THEN 'Enviada'
                    ELSE 'Inactiva'
                END as estado_nombre,
                u.usuario_nombre as destinatario_nombre,
                ct.titulo as tarea_titulo,
                e.titulo as evidencia_titulo,
                e.valida_hasta as evidencia_valida_hasta,
                DATE_FORMAT(e.valida_hasta, '%d/%m/%Y') as evidencia_valida_hasta_fmt,
                c.fecha_vencimiento as ciclo_vencimiento,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y') as ciclo_vencimiento_fmt,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                ent.nombre as entidad_nombre,
                CASE 
                    WHEN a.evidencia_id IS NOT NULL THEN 'Vencimiento Evidencia (C30)'
                    WHEN a.ciclo_tarea_id IS NOT NULL THEN 'Vencimiento Tarea (C04)'
                    ELSE 'Vencimiento Ciclo (C05)'
                END as motivo_alerta
            FROM ml__alertas a
            INNER JOIN conf__usuarios u ON u.usuario_id = a.usuario_id
            LEFT JOIN ml__ciclos c ON c.ciclo_id = a.ciclo_id
            LEFT JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            LEFT JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__entidades ent ON ent.entidad_id = c.entidad_id
            LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_tarea_id = a.ciclo_tarea_id
            LEFT JOIN ml__evidencias e ON e.evidencia_id = a.evidencia_id
            WHERE {$where_sql}
            ORDER BY a.alerta_id DESC
            LIMIT 500";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar alertas: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Resumen de KPIs de Alertas
 */
function alertas_kpis_resumen(mysqli $conexion, int $empresa_id): array {
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN tabla_estado_registro_id = 1 THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN tabla_estado_registro_id = 2 THEN 1 ELSE 0 END) as enviadas,
                SUM(CASE WHEN canal = 'EMAIL' THEN 1 ELSE 0 END) as canal_email,
                SUM(CASE WHEN canal = 'SISTEMA' THEN 1 ELSE 0 END) as canal_sistema
            FROM ml__alertas
            WHERE empresa_id = {$empresa_id}";
    $res = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($res);
    return [
        'total' => (int)($row['total'] ?? 0),
        'pendientes' => (int)($row['pendientes'] ?? 0),
        'enviadas' => (int)($row['enviadas'] ?? 0),
        'canal_email' => (int)($row['canal_email'] ?? 0),
        'canal_sistema' => (int)($row['canal_sistema'] ?? 0),
    ];
}

/**
 * Reenviar o forzar despacho de una alerta específica
 */
function alertas_reenviar(mysqli $conexion, int $alerta_id, int $empresa_id, int $usuario_id): array {
    $sql = "UPDATE ml__alertas 
            SET enviada_en = CURRENT_TIMESTAMP,
                tabla_estado_registro_id = 2,
                actualizado_en = CURRENT_TIMESTAMP,
                actualizado_por = ?
            WHERE alerta_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $alerta_id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => "Alerta #{$alerta_id} despachada / notificada exitosamente."];
    }
    return ['success' => false, 'message' => 'Error al actualizar alerta.'];
}
