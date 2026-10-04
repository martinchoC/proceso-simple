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
