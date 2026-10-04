<?php
/**
 * Modelo de Datos: Consola del Generador y Eventos (Página 345 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_generador_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ciclos_generador_service.php';

/**
 * Obtener métricas para la consola del generador
 */
function ciclos_generador_obtener_metricas(mysqli $conexion, int $empresa_id): array {
    // Total obligaciones asignadas activas
    $res_oa = mysqli_query($conexion, "SELECT COUNT(*) as total FROM ml__obligaciones_asignadas WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 1");
    $total_oa = (int)(mysqli_fetch_assoc($res_oa)['total'] ?? 0);

    // Total ciclos generados abiertos (2069, 2070, 2071)
    $res_c_ab = mysqli_query($conexion, "SELECT COUNT(*) as total FROM ml__ciclos WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id IN (2069, 2070, 2071)");
    $total_ciclos_abiertos = (int)(mysqli_fetch_assoc($res_c_ab)['total'] ?? 0);

    // Total ciclos cumplidos (2072, 2073)
    $res_c_cu = mysqli_query($conexion, "SELECT COUNT(*) as total FROM ml__ciclos WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id IN (2072, 2073)");
    $total_ciclos_cumplidos = (int)(mysqli_fetch_assoc($res_c_cu)['total'] ?? 0);

    // Total tareas operativas instanciadas
    $res_t = mysqli_query($conexion, "SELECT COUNT(*) as total FROM ml__ciclos_tareas WHERE empresa_id = {$empresa_id}");
    $total_tareas = (int)(mysqli_fetch_assoc($res_t)['total'] ?? 0);

    return [
        'total_obligaciones_asignadas' => $total_oa,
        'total_ciclos_abiertos' => $total_ciclos_abiertos,
        'total_ciclos_cumplidos' => $total_ciclos_cumplidos,
        'total_tareas_instanciadas' => $total_tareas
    ];
}

/**
 * Proyectar y generar masivamente ciclos para obligaciones asignadas de la empresa
 */
function ciclos_generador_ejecutar_proyeccion(
    mysqli $conexion, 
    int $empresa_id, 
    ?int $entidad_id, 
    int $meses_adelante, 
    int $usuario_id
): array {
    $where = ["oa.empresa_id = {$empresa_id}", "oa.tabla_estado_registro_id = 1"];
    if ($entidad_id && $entidad_id > 0) {
        $where[] = "oa.entidad_id = {$entidad_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT oa.obligacion_asignada_id 
            FROM ml__obligaciones_asignadas oa
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            WHERE {$where_sql}";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al consultar obligaciones: " . mysqli_error($conexion));
    }

    $total_ciclos_nuevos = 0;
    $total_tareas_nuevas = 0;
    $obligaciones_procesadas = 0;

    while ($r = mysqli_fetch_assoc($res)) {
        $oa_id = (int)$r['obligacion_asignada_id'];
        $res_gen = ciclos_generar_para_obligacion_asignada($conexion, $oa_id, $usuario_id);
        $total_ciclos_nuevos += (int)($res_gen['ciclos_creados'] ?? 0);
        $obligaciones_procesadas++;
    }

    // Contar tareas creadas en esta empresa
    $res_ct = mysqli_query($conexion, "SELECT COUNT(*) as t FROM ml__ciclos_tareas WHERE empresa_id = {$empresa_id}");
    $total_tareas_nuevas = (int)(mysqli_fetch_assoc($res_ct)['t'] ?? 0);

    return [
        'obligaciones_procesadas' => $obligaciones_procesadas,
        'ciclos_creados' => $total_ciclos_nuevos,
        'tareas_creadas' => $total_tareas_nuevas
    ];
}

/**
 * Listar obligaciones que aplican por evento para el formulario de disparo
 */
function ciclos_generador_listar_obligaciones_evento(mysqli $conexion, int $empresa_id): array {
    $sql = "SELECT 
                oa.obligacion_asignada_id,
                oa.entidad_id,
                e.nombre as entidad_nombre,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                cr.codigo as cadencia_codigo,
                cr.nombre as cadencia_nombre
            FROM ml__obligaciones_asignadas oa
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__periodicidades cr ON cr.periodicidad_id = o.periodicidad_id
            INNER JOIN ml__entidades e ON e.entidad_id = oa.entidad_id
            WHERE oa.empresa_id = {$empresa_id} AND oa.tabla_estado_registro_id = 1
              AND cr.es_evento = 1
            ORDER BY e.nombre ASC, o.descripcion ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $lista[] = $r;
        }
    }
    return $lista;
}
