<?php
/**
 * cont__balances_model.php
 *
 * Modelo de negocio y consultas contables para el Dashboard de Balance Contable
 * y Listados de Sumas y Saldos.
 *
 * Desarrollado para gestion_multipyme - Módulo 2 Gestión
 */

require_once __DIR__ . '/../../db.php';

/**
 * Obtener ejercicios fiscales / años disponibles con asientos
 */
function obtenerEjerciciosContables($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    $sql = "SELECT DISTINCT anio 
            FROM gestion__cont_asientos 
            WHERE empresa_id = ? AND anio > 0
            ORDER BY anio DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [intval(date('Y'))];
    }
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $anios = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $anios[] = intval($row['anio']);
    }
    mysqli_stmt_close($stmt);

    $current_year = intval(date('Y'));
    if (!in_array($current_year, $anios)) {
        array_unshift($anios, $current_year);
    }
    return $anios;
}

/**
 * Obtener el balance general, árbol de cuentas con rollup y listados de sumas y saldos
 */
function obtenerBalanceCompleto($conexion, $empresa_idx, $fecha_desde = null, $fecha_hasta = null, $incluir_borradores = true)
{
    $empresa_idx = intval($empresa_idx);
    
    if (empty($fecha_desde)) {
        $fecha_desde = date('Y-01-01');
    }
    if (empty($fecha_hasta)) {
        $fecha_hasta = date('Y-12-31');
    }

    // 1. Obtener todas las cuentas del plan de cuentas de la empresa
    $sql_cuentas = "SELECT cont_cuenta_id, cuenta_padre_id, codigo, nombre, naturaleza, nivel, es_imputable, es_caja_banco
                    FROM gestion__cont_cuentas
                    WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                    ORDER BY nivel DESC, codigo ASC";
    $stmt_c = mysqli_prepare($conexion, $sql_cuentas);
    if (!$stmt_c) {
        error_log("Error al preparar query de cuentas en cont__balances_model: " . mysqli_error($conexion));
        return ['success' => false, 'error' => 'Error al consultar cuentas contables'];
    }
    mysqli_stmt_bind_param($stmt_c, "i", $empresa_idx);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);

    $cuentas = [];
    $orden_nivel_desc = [];
    while ($r = mysqli_fetch_assoc($res_c)) {
        $id = (int)$r['cont_cuenta_id'];
        $orden_nivel_desc[] = $id;
        $cuentas[$id] = [
            'cont_cuenta_id' => $id,
            'cuenta_padre_id' => $r['cuenta_padre_id'] ? (int)$r['cuenta_padre_id'] : null,
            'codigo' => trim($r['codigo']),
            'nombre' => trim($r['nombre']),
            'naturaleza' => strtoupper(trim($r['naturaleza'] ?? 'D')),
            'nivel' => (int)$r['nivel'],
            'es_imputable' => (int)$r['es_imputable'],
            'es_caja_banco' => (int)($r['es_caja_banco'] ?? 0),
            'saldo_ant_debe' => 0.0,
            'saldo_ant_haber' => 0.0,
            'saldo_ant_neto' => 0.0,
            'periodo_debe' => 0.0,
            'periodo_haber' => 0.0,
            'periodo_neto' => 0.0,
            'final_debe' => 0.0,
            'final_haber' => 0.0,
            'final_neto' => 0.0,
            'saldo_deudor' => 0.0,
            'saldo_acreedor' => 0.0,
            'tiene_movimientos' => false
        ];
    }
    mysqli_stmt_close($stmt_c);

    if (empty($cuentas)) {
        return [
            'success' => true,
            'cuentas' => [],
            'totales' => [],
            'kpis' => [],
            'graficos' => [],
            'auditoria' => []
        ];
    }

    // 2. Filtro de estados de asientos
    // 1 = Confirmado / Registrado, 3 = Borrador
    $filtro_estados = $incluir_borradores ? "(1, 3)" : "(1)";

    // 3. Movimientos agrupados por cuenta imputable
    $sql_movs = "SELECT 
                    cad.cuenta_id,
                    COALESCE(SUM(CASE WHEN ca.f_asiento < ? AND cad.importe_local > 0 THEN cad.importe_local ELSE 0 END), 0) as ant_debe,
                    COALESCE(SUM(CASE WHEN ca.f_asiento < ? AND cad.importe_local < 0 THEN ABS(cad.importe_local) ELSE 0 END), 0) as ant_haber,
                    COALESCE(SUM(CASE WHEN ca.f_asiento BETWEEN ? AND ? AND cad.importe_local > 0 THEN cad.importe_local ELSE 0 END), 0) as per_debe,
                    COALESCE(SUM(CASE WHEN ca.f_asiento BETWEEN ? AND ? AND cad.importe_local < 0 THEN ABS(cad.importe_local) ELSE 0 END), 0) as per_haber
                 FROM gestion__cont_asientos_detalles cad
                 INNER JOIN gestion__cont_asientos ca ON cad.cont_asiento_id = ca.cont_asiento_id
                 WHERE ca.empresa_id = ?
                   AND ca.tabla_estado_registro_id IN $filtro_estados
                   AND cad.estado = 'activo'
                 GROUP BY cad.cuenta_id";

    $stmt_m = mysqli_prepare($conexion, $sql_movs);
    if (!$stmt_m) {
        error_log("Error al preparar query de movimientos en cont__balances_model: " . mysqli_error($conexion));
        return ['success' => false, 'error' => 'Error al consultar movimientos'];
    }

    mysqli_stmt_bind_param($stmt_m, "ssssssi", $fecha_desde, $fecha_desde, $fecha_desde, $fecha_hasta, $fecha_desde, $fecha_hasta, $empresa_idx);
    mysqli_stmt_execute($stmt_m);
    $res_m = mysqli_stmt_get_result($stmt_m);

    // Sumatorias directas de cuentas hoja (imputables) para los totales de sumas y saldos
    $tot_ant_debe_directo = 0.0;
    $tot_ant_haber_directo = 0.0;
    $tot_per_debe_directo = 0.0;
    $tot_per_haber_directo = 0.0;
    $tot_final_debe_directo = 0.0;
    $tot_final_haber_directo = 0.0;
    $tot_deudor_directo = 0.0;
    $tot_acreedor_directo = 0.0;

    while ($m = mysqli_fetch_assoc($res_m)) {
        $cid = (int)$m['cuenta_id'];
        if (isset($cuentas[$cid])) {
            $adebe = (float)$m['ant_debe'];
            $ahaber = (float)$m['ant_haber'];
            $pdebe = (float)$m['per_debe'];
            $phaber = (float)$m['per_haber'];

            $cuentas[$cid]['saldo_ant_debe'] = $adebe;
            $cuentas[$cid]['saldo_ant_haber'] = $ahaber;
            $cuentas[$cid]['periodo_debe'] = $pdebe;
            $cuentas[$cid]['periodo_haber'] = $phaber;

            if ($adebe > 0 || $ahaber > 0 || $pdebe > 0 || $phaber > 0) {
                $cuentas[$cid]['tiene_movimientos'] = true;
            }

            // Totales directos
            $tot_ant_debe_directo += $adebe;
            $tot_ant_haber_directo += $ahaber;
            $tot_per_debe_directo += $pdebe;
            $tot_per_haber_directo += $phaber;

            $f_debe = $adebe + $pdebe;
            $f_haber = $ahaber + $phaber;
            $tot_final_debe_directo += $f_debe;
            $tot_final_haber_directo += $f_haber;

            if ($f_debe > $f_haber) {
                $tot_deudor_directo += ($f_debe - $f_haber);
            } elseif ($f_haber > $f_debe) {
                $tot_acreedor_directo += ($f_haber - $f_debe);
            }
        }
    }
    mysqli_stmt_close($stmt_m);

    // 4. Rollup de hijos a padres en orden descendente de nivel
    foreach ($orden_nivel_desc as $id) {
        $padre_id = $cuentas[$id]['cuenta_padre_id'];
        if ($padre_id && isset($cuentas[$padre_id])) {
            $cuentas[$padre_id]['saldo_ant_debe'] += $cuentas[$id]['saldo_ant_debe'];
            $cuentas[$padre_id]['saldo_ant_haber'] += $cuentas[$id]['saldo_ant_haber'];
            $cuentas[$padre_id]['periodo_debe'] += $cuentas[$id]['periodo_debe'];
            $cuentas[$padre_id]['periodo_haber'] += $cuentas[$id]['periodo_haber'];
            if ($cuentas[$id]['tiene_movimientos']) {
                $cuentas[$padre_id]['tiene_movimientos'] = true;
            }
        }
    }

    // 5. Calcular saldos finales y saldo neto de cada cuenta
    $cuentas_saldo_contrario = [];

    foreach ($cuentas as $id => &$c) {
        $ant_debe = $c['saldo_ant_debe'];
        $ant_haber = $c['saldo_ant_haber'];
        $per_debe = $c['periodo_debe'];
        $per_haber = $c['periodo_haber'];

        $c['saldo_ant_neto'] = round($ant_debe - $ant_haber, 2);
        $c['periodo_neto'] = round($per_debe - $per_haber, 2);

        $tot_debe = round($ant_debe + $per_debe, 2);
        $tot_haber = round($ant_haber + $per_haber, 2);
        $c['final_debe'] = $tot_debe;
        $c['final_haber'] = $tot_haber;
        $c['final_neto'] = round($tot_debe - $tot_haber, 2);

        if ($tot_debe > $tot_haber) {
            $c['saldo_deudor'] = round($tot_debe - $tot_haber, 2);
            $c['saldo_acreedor'] = 0.0;
        } elseif ($tot_haber > $tot_debe) {
            $c['saldo_deudor'] = 0.0;
            $c['saldo_acreedor'] = round($tot_haber - $tot_debe, 2);
        } else {
            $c['saldo_deudor'] = 0.0;
            $c['saldo_acreedor'] = 0.0;
        }

        // Auditoría: detectar saldo contrario a la naturaleza en cuentas imputables con saldo
        if ($c['es_imputable'] == 1 && ($c['saldo_deudor'] > 0 || $c['saldo_acreedor'] > 0)) {
            $nat = $c['naturaleza'];
            if ($nat === 'D' && $c['saldo_acreedor'] > 0) {
                $cuentas_saldo_contrario[] = [
                    'codigo' => $c['codigo'],
                    'nombre' => $c['nombre'],
                    'naturaleza' => 'Deudora (D)',
                    'saldo' => $c['saldo_acreedor'],
                    'tipo_saldo' => 'Acreedor'
                ];
            } elseif (($nat === 'H' || $nat === 'A') && $c['saldo_deudor'] > 0) {
                $cuentas_saldo_contrario[] = [
                    'codigo' => $c['codigo'],
                    'nombre' => $c['nombre'],
                    'naturaleza' => 'Acreedora (H)',
                    'saldo' => $c['saldo_deudor'],
                    'tipo_saldo' => 'Deudor'
                ];
            }
        }
    }
    unset($c);

    // Ordenar cuentas por código contable natural
    uasort($cuentas, function($a, $b) {
        return strnatcmp($a['codigo'], $b['codigo']);
    });

    // 6. Extraer valores clave para los KPIs del Dashboard y Estados Financieros
    $total_activo = 0.0;
    $total_activo_corriente = 0.0;
    $total_activo_no_corriente = 0.0;

    $total_pasivo = 0.0;
    $total_pasivo_corriente = 0.0;
    $total_pasivo_no_corriente = 0.0;

    $total_patrimonio_neto_base = 0.0;

    $total_ingresos = 0.0;
    $total_costos = 0.0;
    $total_gastos = 0.0;

    // Desglose de rubros para mini-tablas y gráficos
    $rubros_activo = [];
    $rubros_pasivo = [];
    $top_activos = [];
    $top_pasivos = [];
    $top_gastos = [];

    foreach ($cuentas as $c) {
        $cod = $c['codigo'];
        $nivel = $c['nivel'];
        $saldo_deudor = $c['saldo_deudor'];
        $saldo_acreedor = $c['saldo_acreedor'];

        // Nivel 1: Grandes Rubros
        if ($nivel === 1) {
            if ($cod === '1') {
                $total_activo = $saldo_deudor > 0 ? $saldo_deudor : -$saldo_acreedor;
            } elseif ($cod === '2') {
                $total_pasivo = $saldo_acreedor > 0 ? $saldo_acreedor : -$saldo_deudor;
            } elseif ($cod === '3') {
                $total_patrimonio_neto_base = $saldo_acreedor > 0 ? $saldo_acreedor : -$saldo_deudor;
            } elseif ($cod === '4') {
                $total_ingresos = $saldo_acreedor > 0 ? $saldo_acreedor : -$saldo_deudor;
            } elseif ($cod === '5') {
                $total_costos = $saldo_deudor > 0 ? $saldo_deudor : -$saldo_acreedor;
            } elseif ($cod === '6') {
                $total_gastos = $saldo_deudor > 0 ? $saldo_deudor : -$saldo_acreedor;
            }
        }

        // Nivel 2: Capítulos
        if ($nivel === 2) {
            if ($cod === '1.1') {
                $total_activo_corriente = $saldo_deudor > 0 ? $saldo_deudor : -$saldo_acreedor;
            } elseif ($cod === '1.2') {
                $total_activo_no_corriente = $saldo_deudor > 0 ? $saldo_deudor : -$saldo_acreedor;
            } elseif ($cod === '2.1') {
                $total_pasivo_corriente = $saldo_acreedor > 0 ? $saldo_acreedor : -$saldo_deudor;
            } elseif ($cod === '2.2') {
                $total_pasivo_no_corriente = $saldo_acreedor > 0 ? $saldo_acreedor : -$saldo_deudor;
            }
        }

        // Nivel 3: Rubros principales para gráficos y desglose
        if ($nivel === 3 && $c['tiene_movimientos']) {
            if (strpos($cod, '1.') === 0) {
                $rubros_activo[] = [
                    'codigo' => $cod,
                    'nombre' => $c['nombre'],
                    'saldo' => $saldo_deudor
                ];
            } elseif (strpos($cod, '2.') === 0) {
                $rubros_pasivo[] = [
                    'codigo' => $cod,
                    'nombre' => $c['nombre'],
                    'saldo' => $saldo_acreedor
                ];
            }
        }

        // Top cuentas imputables con mayor saldo
        if ($c['es_imputable'] == 1 && $c['tiene_movimientos']) {
            if (strpos($cod, '1.') === 0 && $saldo_deudor > 0) {
                $top_activos[] = ['codigo' => $cod, 'nombre' => $c['nombre'], 'saldo' => $saldo_deudor];
            } elseif (strpos($cod, '2.') === 0 && $saldo_acreedor > 0) {
                $top_pasivos[] = ['codigo' => $cod, 'nombre' => $c['nombre'], 'saldo' => $saldo_acreedor];
            } elseif ((strpos($cod, '5.') === 0 || strpos($cod, '6.') === 0) && $saldo_deudor > 0) {
                $top_gastos[] = ['codigo' => $cod, 'nombre' => $c['nombre'], 'saldo' => $saldo_deudor];
            }
        }
    }

    // Ordenar tops
    usort($top_activos, fn($a, $b) => $b['saldo'] <=> $a['saldo']);
    usort($top_pasivos, fn($a, $b) => $b['saldo'] <=> $a['saldo']);
    usort($top_gastos, fn($a, $b) => $b['saldo'] <=> $a['saldo']);

    $top_activos = array_slice($top_activos, 0, 5);
    $top_pasivos = array_slice($top_pasivos, 0, 5);
    $top_gastos = array_slice($top_gastos, 0, 5);

    // Cálculos de resultados y patrimonio neto consolidado
    $resultado_bruto = round($total_ingresos - $total_costos, 2);
    $resultado_ejercicio = round($total_ingresos - ($total_costos + $total_gastos), 2);
    $patrimonio_neto_total = round($total_patrimonio_neto_base + $resultado_ejercicio, 2);

    $pasivo_mas_patrimonio = round($total_pasivo + $patrimonio_neto_total, 2);
    $diferencia_ecuacion = round($total_activo - $pasivo_mas_patrimonio, 2);
    $ecuacion_cuadrada = abs($diferencia_ecuacion) < 0.01;

    $margen_neto_pct = ($total_ingresos > 0) ? round(($resultado_ejercicio / $total_ingresos) * 100, 1) : 0.0;

    // Validación de cuadre de sumas y saldos
    $diferencia_sumas = round($tot_per_debe_directo - $tot_per_haber_directo, 2);
    $diferencia_saldos = round($tot_deudor_directo - $tot_acreedor_directo, 2);
    $sumas_cuadradas = abs($diferencia_sumas) < 0.01 && abs($diferencia_saldos) < 0.01;

    // 7. Evolución mensual para gráfico
    $evolucion_mensual = obtenerEvolucionMensualDetallada($conexion, $empresa_idx, $fecha_desde, $fecha_hasta, $filtro_estados);

    // 8. Estadísticas de auditoría
    $auditoria_asientos = obtenerAuditoriaAsientos($conexion, $empresa_idx, $fecha_desde, $fecha_hasta);

    $clean_zero = function($val) {
        $r = round($val, 2);
        return abs($r) < 0.001 ? 0.0 : $r;
    };

    return [
        'success' => true,
        'periodo' => [
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta,
            'incluir_borradores' => $incluir_borradores
        ],
        'cuentas' => array_values($cuentas),
        'totales_sumas_saldos' => [
            'total_ant_debe' => $clean_zero($tot_ant_debe_directo),
            'total_ant_haber' => $clean_zero($tot_ant_haber_directo),
            'total_periodo_debe' => $clean_zero($tot_per_debe_directo),
            'total_periodo_haber' => $clean_zero($tot_per_haber_directo),
            'total_final_debe' => $clean_zero($tot_final_debe_directo),
            'total_final_haber' => $clean_zero($tot_final_haber_directo),
            'total_saldos_deudores' => $clean_zero($tot_deudor_directo),
            'total_saldos_acreedores' => $clean_zero($tot_acreedor_directo),
            'diferencia_sumas' => $clean_zero($diferencia_sumas),
            'diferencia_saldos' => $clean_zero($diferencia_saldos),
            'esta_cuadrado' => $sumas_cuadradas
        ],
        'kpis' => [
            'total_activo' => $clean_zero($total_activo),
            'total_activo_corriente' => $clean_zero($total_activo_corriente),
            'total_activo_no_corriente' => $clean_zero($total_activo_no_corriente),
            'total_pasivo' => $clean_zero($total_pasivo),
            'total_pasivo_corriente' => $clean_zero($total_pasivo_corriente),
            'total_pasivo_no_corriente' => $clean_zero($total_pasivo_no_corriente),
            'total_patrimonio_neto_base' => $clean_zero($total_patrimonio_neto_base),
            'total_patrimonio_neto_total' => $clean_zero($patrimonio_neto_total),
            'total_ingresos' => $clean_zero($total_ingresos),
            'total_costos' => $clean_zero($total_costos),
            'total_gastos' => $clean_zero($total_gastos),
            'resultado_bruto' => $clean_zero($resultado_bruto),
            'resultado_ejercicio' => $clean_zero($resultado_ejercicio),
            'pasivo_mas_patrimonio' => $clean_zero($pasivo_mas_patrimonio),
            'diferencia_ecuacion' => $clean_zero($diferencia_ecuacion),
            'ecuacion_cuadrada' => $ecuacion_cuadrada,
            'margen_neto_pct' => $margen_neto_pct
        ],
        'desglose' => [
            'rubros_activo' => $rubros_activo,
            'rubros_pasivo' => $rubros_pasivo,
            'top_activos' => $top_activos,
            'top_pasivos' => $top_pasivos,
            'top_gastos' => $top_gastos
        ],
        'graficos' => [
            'estructura_patrimonial' => [
                'labels' => ['Activo Cte.', 'Activo No Cte.', 'Pasivo Cte.', 'Pasivo No Cte.', 'Patrimonio Neto', 'Resultado Ej.'],
                'data' => [
                    round($total_activo_corriente, 2),
                    round($total_activo_no_corriente, 2),
                    round($total_pasivo_corriente, 2),
                    round($total_pasivo_no_corriente, 2),
                    round($total_patrimonio_neto_base, 2),
                    round($resultado_ejercicio, 2)
                ]
            ],
            'evolucion_mensual' => $evolucion_mensual
        ],
        'auditoria' => [
            'asientos_totales' => $auditoria_asientos['totales'],
            'asientos_registrados' => $auditoria_asientos['registrados'],
            'asientos_borradores' => $auditoria_asientos['borradores'],
            'asientos_descuadrados' => $auditoria_asientos['descuadrados'],
            'cuentas_saldo_contrario' => $cuentas_saldo_contrario
        ]
    ];
}

/**
 * Evolución mensual detallada de ingresos y egresos para gráfico
 */
function obtenerEvolucionMensualDetallada($conexion, $empresa_idx, $fecha_desde, $fecha_hasta, $filtro_estados)
{
    $sql = "SELECT 
                DATE_FORMAT(ca.f_asiento, '%Y-%m') as mes_clave,
                DATE_FORMAT(ca.f_asiento, '%b %Y') as mes_label,
                MONTH(ca.f_asiento) as num_mes,
                YEAR(ca.f_asiento) as num_anio,
                COALESCE(SUM(CASE WHEN cc.codigo LIKE '4%' AND cad.importe_local < 0 THEN ABS(cad.importe_local) ELSE 0 END), 0) as ingresos,
                COALESCE(SUM(CASE WHEN (cc.codigo LIKE '5%' OR cc.codigo LIKE '6%') AND cad.importe_local > 0 THEN cad.importe_local ELSE 0 END), 0) as egresos
            FROM gestion__cont_asientos_detalles cad
            INNER JOIN gestion__cont_asientos ca ON cad.cont_asiento_id = ca.cont_asiento_id
            INNER JOIN gestion__cont_cuentas cc ON cad.cuenta_id = cc.cont_cuenta_id
            WHERE ca.empresa_id = ?
              AND ca.f_asiento BETWEEN ? AND ?
              AND ca.tabla_estado_registro_id IN $filtro_estados
              AND cad.estado = 'activo'
            GROUP BY mes_clave, mes_label, num_mes, num_anio
            ORDER BY mes_clave ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['labels' => [], 'ingresos' => [], 'egresos' => [], 'resultados' => []];
    }
    mysqli_stmt_bind_param($stmt, "iss", $empresa_idx, $fecha_desde, $fecha_hasta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $labels = [];
    $ingresos = [];
    $egresos = [];
    $resultados = [];

    $meses_nombres = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
    ];

    while ($r = mysqli_fetch_assoc($res)) {
        $m_num = (int)$r['num_mes'];
        $a_num = (int)$r['num_anio'];
        $label = ($meses_nombres[$m_num] ?? 'Mes ' . $m_num) . ' ' . substr((string)$a_num, 2);

        $ing = round((float)$r['ingresos'], 2);
        $egr = round((float)$r['egresos'], 2);
        $neto = round($ing - $egr, 2);

        $labels[] = $label;
        $ingresos[] = $ing;
        $egresos[] = $egr;
        $resultados[] = $neto;
    }
    mysqli_stmt_close($stmt);

    return [
        'labels' => $labels,
        'ingresos' => $ingresos,
        'egresos' => $egresos,
        'resultados' => $resultados
    ];
}

/**
 * Conteo de auditoría de asientos en el período
 */
function obtenerAuditoriaAsientos($conexion, $empresa_idx, $fecha_desde, $fecha_hasta)
{
    $sql = "SELECT 
                COUNT(*) as totales,
                COALESCE(SUM(CASE WHEN tabla_estado_registro_id = 1 THEN 1 ELSE 0 END), 0) as registrados,
                COALESCE(SUM(CASE WHEN tabla_estado_registro_id = 3 THEN 1 ELSE 0 END), 0) as borradores
            FROM gestion__cont_asientos
            WHERE empresa_id = ?
              AND f_asiento BETWEEN ? AND ?
              AND tabla_estado_registro_id != 2";

    $stmt = mysqli_prepare($conexion, $sql);
    $totales = 0;
    $registrados = 0;
    $borradores = 0;

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iss", $empresa_idx, $fecha_desde, $fecha_hasta);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($r = mysqli_fetch_assoc($res)) {
            $totales = (int)$r['totales'];
            $registrados = (int)$r['registrados'];
            $borradores = (int)$r['borradores'];
        }
        mysqli_stmt_close($stmt);
    }

    // Verificar si hay asientos individuales descuadrados en el período
    $sql_desc = "SELECT ca.cont_asiento_id
                 FROM gestion__cont_asientos ca
                 INNER JOIN gestion__cont_asientos_detalles cad ON ca.cont_asiento_id = cad.cont_asiento_id
                 WHERE ca.empresa_id = ?
                   AND ca.f_asiento BETWEEN ? AND ?
                   AND ca.tabla_estado_registro_id != 2
                   AND cad.estado = 'activo'
                 GROUP BY ca.cont_asiento_id
                 HAVING ABS(SUM(cad.importe_local)) > 0.01";

    $stmt_desc = mysqli_prepare($conexion, $sql_desc);
    $descuadrados = 0;
    if ($stmt_desc) {
        mysqli_stmt_bind_param($stmt_desc, "iss", $empresa_idx, $fecha_desde, $fecha_hasta);
        mysqli_stmt_execute($stmt_desc);
        mysqli_stmt_store_result($stmt_desc);
        $descuadrados = mysqli_stmt_num_rows($stmt_desc);
        mysqli_stmt_close($stmt_desc);
    }

    return [
        'totales' => $totales,
        'registrados' => $registrados,
        'borradores' => $borradores,
        'descuadrados' => $descuadrados
    ];
}
