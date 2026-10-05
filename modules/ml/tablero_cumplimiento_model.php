<?php
/**
 * Modelo de Datos: Tablero de Cumplimiento (Página 355 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/tablero_cumplimiento_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ejecucion_seguimiento_service.php';

/**
 * Obtener KPIs en tiempo real para el mes en curso
 */
function tablero_indicadores_actuales(mysqli $conexion, int $empresa_id): array {
    $primer_dia = date('Y-m-01');
    $ultimo_dia = date('Y-m-t');

    $sql = "SELECT 
                COUNT(*) as total_ciclos,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2072 THEN 1 ELSE 0 END) as cumplidos_tiempo,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2073 THEN 1 ELSE 0 END) as cumplidos_plazo,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2074 THEN 1 ELSE 0 END) as incumplidos,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2075 THEN 1 ELSE 0 END) as eximidos,
                SUM(CASE WHEN c.tabla_estado_registro_id IN (2069, 2070, 2071) THEN 1 ELSE 0 END) as abiertos,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2072 THEN COALESCE(crit.peso, 1) ELSE 0 END) as puntos_obtenidos,
                SUM(COALESCE(crit.peso, 1)) as puntos_totales
            FROM ml__ciclos c
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__criticidades crit ON crit.criticidad_id = o.criticidad_id
            WHERE c.empresa_id = {$empresa_id}
              AND c.fecha_vencimiento BETWEEN '{$primer_dia} 00:00:00' AND '{$ultimo_dia} 23:59:59'";

    $res = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($res);

    $total = (int)($row['total_ciclos'] ?? 0);
    $tiempo = (int)($row['cumplidos_tiempo'] ?? 0);
    $plazo = (int)($row['cumplidos_plazo'] ?? 0);
    $incumplidos = (int)($row['incumplidos'] ?? 0);
    $eximidos = (int)($row['eximidos'] ?? 0);
    $abiertos = (int)($row['abiertos'] ?? 0);

    $pts_obt = (float)($row['puntos_obtenidos'] ?? 0);
    $pts_tot = (float)($row['puntos_totales'] ?? 0);

    $cumplidos_total = $tiempo + $plazo;
    $porcentaje_cumplimiento = $total > 0 ? round(($cumplidos_total / $total) * 100, 1) : 100.0;
    $porcentaje_ponderado = $pts_tot > 0 ? round(($pts_obt / $pts_tot) * 100, 1) : 100.0;

    return [
        'periodo_mes' => date('F Y'),
        'periodo_desde' => $primer_dia,
        'periodo_hasta' => $ultimo_dia,
        'total_ciclos' => $total,
        'cumplidos_tiempo' => $tiempo,
        'cumplidos_plazo' => $plazo,
        'incumplidos' => $incumplidos,
        'eximidos' => $eximidos,
        'abiertos' => $abiertos,
        'porcentaje_cumplimiento' => $porcentaje_cumplimiento,
        'porcentaje_ponderado' => $porcentaje_ponderado,
    ];
}

/**
 * Obtener distribución de cumplimiento por nivel de criticidad
 */
function tablero_cumplimiento_por_criticidad(mysqli $conexion, int $empresa_id): array {
    $primer_dia = date('Y-m-01');
    $ultimo_dia = date('Y-m-t');

    $sql = "SELECT 
                COALESCE(crit.nombre, 'Sin clasificar') as criticidad,
                COUNT(c.ciclo_id) as total,
                SUM(CASE WHEN c.tabla_estado_registro_id IN (2072, 2073) THEN 1 ELSE 0 END) as cumplidos,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2074 THEN 1 ELSE 0 END) as incumplidos
            FROM ml__ciclos c
            INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__criticidades crit ON crit.criticidad_id = o.criticidad_id
            WHERE c.empresa_id = {$empresa_id}
              AND c.fecha_vencimiento BETWEEN '{$primer_dia} 00:00:00' AND '{$ultimo_dia} 23:59:59'
            GROUP BY crit.criticidad_id, crit.nombre
            ORDER BY crit.peso DESC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $tot = (int)$row['total'];
            $cump = (int)$row['cumplidos'];
            $row['porcentaje'] = $tot > 0 ? round(($cump / $tot) * 100, 1) : 100.0;
            $lista[] = $row;
        }
    }
    return $lista;
}

/**
 * Obtener desglose de cumplimiento por entidad
 */
function tablero_cumplimiento_por_entidad(mysqli $conexion, int $empresa_id): array {
    $primer_dia = date('Y-m-01');
    $ultimo_dia = date('Y-m-t');

    $sql = "SELECT 
                e.entidad_id,
                e.nombre as entidad_nombre,
                COUNT(c.ciclo_id) as total,
                SUM(CASE WHEN c.tabla_estado_registro_id IN (2072, 2073) THEN 1 ELSE 0 END) as cumplidos,
                SUM(CASE WHEN c.tabla_estado_registro_id = 2074 THEN 1 ELSE 0 END) as incumplidos
            FROM ml__ciclos c
            INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
            WHERE c.empresa_id = {$empresa_id}
              AND c.fecha_vencimiento BETWEEN '{$primer_dia} 00:00:00' AND '{$ultimo_dia} 23:59:59'
            GROUP BY e.entidad_id, e.nombre
            ORDER BY total DESC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $tot = (int)$row['total'];
            $cump = (int)$row['cumplidos'];
            $row['porcentaje'] = $tot > 0 ? round(($cump / $tot) * 100, 1) : 100.0;
            $lista[] = $row;
        }
    }
    return $lista;
}

/**
 * Listar histórico de cierres calculados
 */
function tablero_cierres_historicos(mysqli $conexion, int $empresa_id): array {
    $sql = "SELECT 
                ic.indicador_cierre_id,
                DATE_FORMAT(ic.periodo_desde, '%d/%m/%Y') as periodo_desde_fmt,
                DATE_FORMAT(ic.periodo_hasta, '%d/%m/%Y') as periodo_hasta_fmt,
                ic.periodo_desde,
                ic.periodo_hasta,
                ic.ciclos_contados,
                ic.cumplidos_a_tiempo,
                ic.cumplidos_fuera_plazo,
                ic.incumplidos,
                ic.eximidos,
                ic.por_vencer,
                DATE_FORMAT(ic.calculado_en, '%d/%m/%Y %H:%i') as calculado_en_fmt,
                ROUND(((ic.cumplidos_a_tiempo + ic.cumplidos_fuera_plazo) / NULLIF(ic.ciclos_contados, 0)) * 100, 1) as porcentaje_cumplimiento,
                u.usuario_nombre as calculado_por_nombre
            FROM ml__indicadores_cierres ic
            LEFT JOIN conf__usuarios u ON u.usuario_id = ic.creado_por
            WHERE ic.empresa_id = {$empresa_id}
            ORDER BY ic.periodo_hasta DESC, ic.indicador_cierre_id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar cierres: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['porcentaje_cumplimiento'] === null) {
            $row['porcentaje_cumplimiento'] = 100.0;
        }
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener datos ejecutivos para Drilldown Multinivel: Global ➔ Región ➔ País ➔ Empresa ➔ Entidad
 */
function tablero_obtener_drilldown(mysqli $conexion, string $nivel = 'global', array $filtros = []): array {
    $region_id = isset($filtros['region_id']) ? (int)$filtros['region_id'] : 0;
    $territorio_id = isset($filtros['territorio_id']) ? (int)$filtros['territorio_id'] : 0;
    $empresa_id = isset($filtros['empresa_id']) ? (int)$filtros['empresa_id'] : 0;

    // 1. Where dinámico para tareas y ciclos según el scope seleccionado
    $where_ct = ["1=1"];
    if ($empresa_id > 0) {
        $where_ct[] = "ct.empresa_id = {$empresa_id}";
    }

    $where_sql = implode(' AND ', $where_ct);

    // 2. KPIs Generales del alcance seleccionado
    $sql_kpi = "SELECT 
                    COUNT(*) as total_tareas,
                    SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
                    SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
                    SUM(CASE WHEN ct.fecha_vencimiento > DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id IN (2078, 2079, 2080) THEN 1 ELSE 0 END) as en_termino,
                    SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas,
                    COUNT(DISTINCT ct.ciclo_id) as total_ciclos
                FROM ml__ciclos_tareas ct
                WHERE {$where_sql}";
    $res_kpi = mysqli_query($conexion, $sql_kpi);
    $kpis = mysqli_fetch_assoc($res_kpi) ?: [
        'total_tareas' => 0, 'vencidas' => 0, 'proximas' => 0, 'en_termino' => 0, 'cumplidas' => 0, 'total_ciclos' => 0
    ];

    $tot = (int)$kpis['total_tareas'];
    $cump = (int)$kpis['cumplidas'];
    $kpis['tasa_cumplimiento'] = $tot > 0 ? round(($cump / $tot) * 100, 1) : 100.0;

    // 3. Obtener elementos del nivel actual para la grilla de Drilldown
    $items = [];
    $siguiente_nivel = '';

    if ($nivel === 'global') {
        $siguiente_nivel = 'region';
        // Desglose por Regiones
        $sql = "SELECT 
                    rg.region_grupo_id as id,
                    rg.nombre as nombre,
                    'Región' as tipo_etiqueta,
                    'fas fa-globe-americas' as icono,
                    COUNT(DISTINCT er.empresa_id) as sub_total,
                    'Empresas' as sub_etiqueta,
                    COUNT(DISTINCT c.ciclo_id) as total_ciclos,
                    COUNT(ct.ciclo_tarea_id) as total_tareas,
                    SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
                    SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
                    SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
                FROM ml__regiones_grupo rg
                LEFT JOIN ml__empresas_regiones er ON er.region_grupo_id = rg.region_grupo_id
                LEFT JOIN ml__ciclos c ON c.empresa_id = er.empresa_id
                LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
                GROUP BY rg.region_grupo_id, rg.nombre";
        $res = mysqli_query($conexion, $sql);
        while ($r = mysqli_fetch_assoc($res)) {
            $t = (int)$r['total_tareas'];
            $c = (int)$r['cumplidas'];
            $v = (int)$r['vencidas'];
            $p = (int)$r['proximas'];
            $r['porcentaje'] = $t > 0 ? round(($c / $t) * 100, 1) : 100.0;
            $r['semaforo'] = ($v > 0) ? 'danger' : (($p > 0) ? 'warning' : 'success');
            $r['semaforo_texto'] = ($v > 0) ? 'CRÍTICO' : (($p > 0) ? 'ALERTA' : 'AL DÍA');
            $items[] = $r;
        }

    } elseif ($nivel === 'region') {
        $siguiente_nivel = 'pais';
        // Desglose por Países / Territorios dentro de la región
        $sql = "SELECT 
                    t.territorio_id as id,
                    CONCAT(t.nombre, ' (', t.codigo, ')') as nombre,
                    'País / Territorio' as tipo_etiqueta,
                    'fas fa-flag' as icono,
                    COUNT(DISTINCT e.empresa_id) as sub_total,
                    'Empresas' as sub_etiqueta,
                    COUNT(DISTINCT c.ciclo_id) as total_ciclos,
                    COUNT(ct.ciclo_tarea_id) as total_tareas,
                    SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
                    SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
                    SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
                FROM ml__territorios t
                LEFT JOIN conf__empresas e ON (e.empresa_id = 2 AND t.territorio_id = 2) -- Mapeo territorial
                LEFT JOIN ml__ciclos c ON c.empresa_id = e.empresa_id
                LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
                WHERE t.territorio_tipo_id = 3 -- Países
                GROUP BY t.territorio_id, t.nombre, t.codigo";
        $res = mysqli_query($conexion, $sql);
        while ($r = mysqli_fetch_assoc($res)) {
            $t = (int)$r['total_tareas'];
            $c = (int)$r['cumplidas'];
            $v = (int)$r['vencidas'];
            $p = (int)$r['proximas'];
            $r['porcentaje'] = $t > 0 ? round(($c / $t) * 100, 1) : 100.0;
            $r['semaforo'] = ($v > 0) ? 'danger' : (($p > 0) ? 'warning' : 'success');
            $r['semaforo_texto'] = ($v > 0) ? 'CRÍTICO' : (($p > 0) ? 'ALERTA' : 'AL DÍA');
            $items[] = $r;
        }

    } elseif ($nivel === 'pais') {
        $siguiente_nivel = 'empresa';
        // Desglose por Empresas
        $sql = "SELECT 
                    e.empresa_id as id,
                    e.empresa as nombre,
                    'Empresa Subsidiaria' as tipo_etiqueta,
                    'fas fa-building' as icono,
                    COUNT(DISTINCT c.entidad_id) as sub_total,
                    'Instalaciones' as sub_etiqueta,
                    COUNT(DISTINCT c.ciclo_id) as total_ciclos,
                    COUNT(ct.ciclo_tarea_id) as total_tareas,
                    SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
                    SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
                    SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
                FROM conf__empresas e
                LEFT JOIN ml__ciclos c ON c.empresa_id = e.empresa_id
                LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
                WHERE e.empresa_id > 1
                GROUP BY e.empresa_id, e.empresa";
        $res = mysqli_query($conexion, $sql);
        while ($r = mysqli_fetch_assoc($res)) {
            $t = (int)$r['total_tareas'];
            $c = (int)$r['cumplidas'];
            $v = (int)$r['vencidas'];
            $p = (int)$r['proximas'];
            $r['porcentaje'] = $t > 0 ? round(($c / $t) * 100, 1) : 100.0;
            $r['semaforo'] = ($v > 0) ? 'danger' : (($p > 0) ? 'warning' : 'success');
            $r['semaforo_texto'] = ($v > 0) ? 'CRÍTICO' : (($p > 0) ? 'ALERTA' : 'AL DÍA');
            $items[] = $r;
        }

    } elseif ($nivel === 'empresa') {
        $siguiente_nivel = 'detalle';
        // Desglose por Instalaciones / Entidades de la empresa
        $filtro_emp = $empresa_id > 0 ? $empresa_id : 2;
        $sql = "SELECT 
                    ent.entidad_id as id,
                    ent.nombre as nombre,
                    et.nombre as tipo_etiqueta,
                    CASE 
                        WHEN et.codigo = 'TANQUE' THEN 'fas fa-oil-can'
                        WHEN et.codigo = 'TERMINAL' THEN 'fas fa-ship'
                        ELSE 'fas fa-industry'
                    END as icono,
                    COUNT(DISTINCT c.ciclo_id) as total_ciclos,
                    COUNT(ct.ciclo_tarea_id) as total_tareas,
                    SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
                    SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
                    SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
                FROM ml__entidades ent
                JOIN ml__entidades_tipos et ON et.entidad_tipo_id = ent.entidad_tipo_id
                LEFT JOIN ml__ciclos c ON c.entidad_id = ent.entidad_id AND c.empresa_id = {$filtro_emp}
                LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
                GROUP BY ent.entidad_id, ent.nombre, et.nombre, et.codigo";
        $res = mysqli_query($conexion, $sql);
        while ($r = mysqli_fetch_assoc($res)) {
            $t = (int)$r['total_tareas'];
            $c = (int)$r['cumplidas'];
            $v = (int)$r['vencidas'];
            $p = (int)$r['proximas'];
            $r['porcentaje'] = $t > 0 ? round(($c / $t) * 100, 1) : 100.0;
            $r['semaforo'] = ($v > 0) ? 'danger' : (($p > 0) ? 'warning' : 'success');
            $r['semaforo_texto'] = ($v > 0) ? 'CRÍTICO' : (($p > 0) ? 'ALERTA' : 'AL DÍA');
            $items[] = $r;
        }
    }

    return [
        'nivel_actual' => $nivel,
        'siguiente_nivel' => $siguiente_nivel,
        'kpis' => $kpis,
        'items' => $items,
        'tareas_criticas' => tablero_obtener_tareas_criticas($conexion, $filtros),
        'ciclos_activos' => tablero_obtener_ciclos_obligaciones($conexion, $filtros)
    ];
}

/**
 * Obtener tareas críticas (vencidas y próximas a vencer con responsables)
 */
function tablero_obtener_tareas_criticas(mysqli $conexion, array $filtros = []): array {
    $empresa_id = isset($filtros['empresa_id']) ? (int)$filtros['empresa_id'] : 0;
    $where = ["ct.fecha_vencimiento < DATE_ADD(NOW(), INTERVAL 7 DAY)", "ct.tabla_estado_registro_id NOT IN (2081, 2083)"];
    if ($empresa_id > 0) {
        $where[] = "ct.empresa_id = {$empresa_id}";
    }
    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                ct.ciclo_tarea_id,
                ct.titulo,
                DATE_FORMAT(ct.fecha_vencimiento, '%d/%m/%Y') as fecha_vencimiento_fmt,
                ct.fecha_vencimiento,
                DATEDIFF(NOW(), ct.fecha_vencimiento) as dias_atraso,
                ent.nombre as entidad_nombre,
                o.codigo as obligacion_codigo,
                COALESCE(u.usuario_nombre, 'Sin asignar') as responsable_nombre,
                ter.tabla_estado_registro as estado_nombre,
                CASE 
                    WHEN ct.fecha_vencimiento < NOW() THEN 'VENCIDA'
                    ELSE 'PROXIMA'
                END as urgencia
            FROM ml__ciclos_tareas ct
            JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
            JOIN ml__entidades ent ON ent.entidad_id = c.entidad_id
            JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = ct.tabla_estado_registro_id
            LEFT JOIN ml__ciclos_tareas_raci ctr ON ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1
            LEFT JOIN conf__usuarios u ON u.usuario_id = ctr.usuario_id
            WHERE {$where_sql}
            ORDER BY ct.fecha_vencimiento ASC
            LIMIT 10";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener vencimiento de ciclos y obligaciones
 */
function tablero_obtener_ciclos_obligaciones(mysqli $conexion, array $filtros = []): array {
    $empresa_id = isset($filtros['empresa_id']) ? (int)$filtros['empresa_id'] : 0;
    $where = ["c.tabla_estado_registro_id NOT IN (2072, 2075)"]; // No cumplidos ni eximidos
    if ($empresa_id > 0) {
        $where[] = "c.empresa_id = {$empresa_id}";
    }
    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                c.ciclo_id,
                n.codigo as norma_codigo,
                o.codigo as obligacion_codigo,
                o.descripcion as obligacion_titulo,
                ent.nombre as entidad_nombre,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y') as fecha_vencimiento_fmt,
                c.fecha_vencimiento,
                DATEDIFF(c.fecha_vencimiento, NOW()) as dias_restantes,
                COUNT(ct.ciclo_tarea_id) as total_tareas,
                SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as tareas_vencidas,
                SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as tareas_cumplidas
            FROM ml__ciclos c
            JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            JOIN ml__normas n ON n.norma_id = o.norma_id
            JOIN ml__entidades ent ON ent.entidad_id = c.entidad_id
            LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
            WHERE {$where_sql}
            GROUP BY c.ciclo_id, n.codigo, o.codigo, o.descripcion, ent.nombre, c.fecha_vencimiento
            ORDER BY c.fecha_vencimiento ASC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}
