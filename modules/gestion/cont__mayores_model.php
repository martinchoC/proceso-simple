<?php
require_once __DIR__ . '/../../db.php';

/**
 * Obtener todas las cuentas contables activas
 */
function obtenerCuentasContables($conexion, $empresa_idx)
{
    $sql = "SELECT cont_cuenta_id, codigo, nombre, naturaleza, nivel, es_imputable
            FROM gestion__cont_cuentas 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1
            ORDER BY codigo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando query cuentas: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $cuentas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $cuentas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $cuentas;
}

/**
 * Obtener saldos de cuentas con movimientos en el período
 */
function obtenerSaldosCuentas($conexion, $empresa_idx, $fecha_desde = null, $fecha_hasta = null, $cuenta_id = null)
{
    // Establecer fechas por defecto (mes actual)
    if (empty($fecha_desde)) {
        $fecha_desde = date('Y-m-01');
    }
    if (empty($fecha_hasta)) {
        $fecha_hasta = date('Y-m-t');
    }
    
    // Primero, obtener todas las cuentas activas
    $sql_cuentas = "SELECT cont_cuenta_id, codigo, nombre, naturaleza 
                    FROM gestion__cont_cuentas 
                    WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $params_cuentas = [$empresa_idx];
    $types_cuentas = "i";
    
    if (!empty($cuenta_id)) {
        $sql_cuentas .= " AND cont_cuenta_id = ?";
        $params_cuentas[] = $cuenta_id;
        $types_cuentas .= "i";
    }
    
    $sql_cuentas .= " ORDER BY codigo";
    
    $stmt_cuentas = mysqli_prepare($conexion, $sql_cuentas);
    if (!$stmt_cuentas) {
        error_log("Error preparando query cuentas: " . mysqli_error($conexion));
        return [];
    }
    
    $bind_names = [];
    $bind_names[] = $types_cuentas;
    for ($i = 0; $i < count($params_cuentas); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params_cuentas[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt_cuentas, 'bind_param'], $bind_names);
    
    mysqli_stmt_execute($stmt_cuentas);
    $result_cuentas = mysqli_stmt_get_result($stmt_cuentas);
    
    $cuentas = [];
    while ($row = mysqli_fetch_assoc($result_cuentas)) {
        $cuentas[$row['cont_cuenta_id']] = $row;
        $cuentas[$row['cont_cuenta_id']]['saldo_inicial'] = 0;
        $cuentas[$row['cont_cuenta_id']]['total_debe'] = 0;
        $cuentas[$row['cont_cuenta_id']]['total_haber'] = 0;
        $cuentas[$row['cont_cuenta_id']]['saldo_final'] = 0;
    }
    mysqli_stmt_close($stmt_cuentas);
    
    if (empty($cuentas)) {
        return [];
    }
    
    // Obtener saldos iniciales (movimientos antes de fecha_desde)
    $cuenta_ids = array_keys($cuentas);
    $placeholders = implode(',', array_fill(0, count($cuenta_ids), '?'));
    $types_saldo = str_repeat('i', count($cuenta_ids));
    
    $sql_saldo_inicial = "
        SELECT 
            cad.cuenta_id,
            SUM(cad.importe_local) as total_movimiento
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.asiento_id = ca.id
        WHERE ca.empresa_id = ?
        AND ca.estado = 'registrado'
        AND cad.estado = 'activo'
        AND cad.cuenta_id IN ($placeholders)
        AND ca.fecha < ?
        GROUP BY cad.cuenta_id
    ";
    
    $params_saldo = array_merge([$empresa_idx], $cuenta_ids, [$fecha_desde]);
    $types_saldo_full = "i" . $types_saldo . "s";
    
    $stmt_saldo = mysqli_prepare($conexion, $sql_saldo_inicial);
    if ($stmt_saldo) {
        $bind_names = [];
        $bind_names[] = $types_saldo_full;
        for ($i = 0; $i < count($params_saldo); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params_saldo[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt_saldo, 'bind_param'], $bind_names);
        
        mysqli_stmt_execute($stmt_saldo);
        $result_saldo = mysqli_stmt_get_result($stmt_saldo);
        while ($row = mysqli_fetch_assoc($result_saldo)) {
            $cuenta_id_key = $row['cuenta_id'];
            $naturaleza = $cuentas[$cuenta_id_key]['naturaleza'];
            $importe = floatval($row['total_movimiento']);
            
            if ($naturaleza == 'D') {
                $cuentas[$cuenta_id_key]['saldo_inicial'] = $importe;
            } else {
                $cuentas[$cuenta_id_key]['saldo_inicial'] = -$importe;
            }
        }
        mysqli_stmt_close($stmt_saldo);
    }
    
    // Obtener movimientos del período
    $sql_movimientos = "
        SELECT 
            cad.cuenta_id,
            SUM(CASE 
                WHEN cc.naturaleza = 'D' THEN cad.importe_local
                ELSE -cad.importe_local
            END) as debe,
            SUM(CASE 
                WHEN cc.naturaleza = 'D' THEN -cad.importe_local
                ELSE cad.importe_local
            END) as haber
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.asiento_id = ca.id
        INNER JOIN gestion__cont_cuentas cc ON cad.cuenta_id = cc.cont_cuenta_id
        WHERE ca.empresa_id = ?
        AND ca.estado = 'registrado'
        AND cad.estado = 'activo'
        AND cad.cuenta_id IN ($placeholders)
        AND ca.fecha BETWEEN ? AND ?
        GROUP BY cad.cuenta_id
    ";
    
    $params_mov = array_merge([$empresa_idx], $cuenta_ids, [$fecha_desde, $fecha_hasta]);
    $types_mov = "i" . $types_saldo . "ss";
    
    $stmt_mov = mysqli_prepare($conexion, $sql_movimientos);
    if ($stmt_mov) {
        $bind_names = [];
        $bind_names[] = $types_mov;
        for ($i = 0; $i < count($params_mov); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params_mov[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt_mov, 'bind_param'], $bind_names);
        
        mysqli_stmt_execute($stmt_mov);
        $result_mov = mysqli_stmt_get_result($stmt_mov);
        while ($row = mysqli_fetch_assoc($result_mov)) {
            $cuenta_id_key = $row['cuenta_id'];
            $cuentas[$cuenta_id_key]['total_debe'] = floatval($row['debe']);
            $cuentas[$cuenta_id_key]['total_haber'] = floatval($row['haber']);
        }
        mysqli_stmt_close($stmt_mov);
    }
    
    // Calcular saldo final
    $data = [];
    foreach ($cuentas as $cuenta) {
        $cuenta['saldo_final'] = $cuenta['saldo_inicial'] + $cuenta['total_debe'] + $cuenta['total_haber'];
        $data[] = $cuenta;
    }
    
    return $data;
}

/**
 * Obtener detalle de movimientos de una cuenta específica
 */
function obtenerDetalleCuenta($conexion, $empresa_idx, $cuenta_id, $fecha_desde, $fecha_hasta)
{
    if (empty($fecha_desde)) {
        $fecha_desde = date('Y-m-01');
    }
    if (empty($fecha_hasta)) {
        $fecha_hasta = date('Y-m-t');
    }
    
    // Obtener información de la cuenta
    $sql_cuenta = "SELECT codigo, nombre, naturaleza 
                   FROM gestion__cont_cuentas 
                   WHERE cont_cuenta_id = ? AND empresa_id = ?";
    $stmt_cuenta = mysqli_prepare($conexion, $sql_cuenta);
    $cuenta_info = ['codigo' => '', 'nombre' => '', 'naturaleza' => 'D'];
    
    if ($stmt_cuenta) {
        mysqli_stmt_bind_param($stmt_cuenta, "ii", $cuenta_id, $empresa_idx);
        mysqli_stmt_execute($stmt_cuenta);
        $result_cuenta = mysqli_stmt_get_result($stmt_cuenta);
        $cuenta_info = mysqli_fetch_assoc($result_cuenta);
        if (!$cuenta_info) {
            $cuenta_info = ['codigo' => '', 'nombre' => '', 'naturaleza' => 'D'];
        }
        mysqli_stmt_close($stmt_cuenta);
    }
    
    // Obtener saldo inicial
    $sql_saldo_inicial = "
        SELECT COALESCE(SUM(cad.importe_local), 0) as saldo_inicial
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.asiento_id = ca.id
        WHERE ca.empresa_id = ?
        AND ca.estado = 'registrado'
        AND cad.estado = 'activo'
        AND cad.cuenta_id = ?
        AND ca.fecha < ?
    ";
    
    $stmt_saldo = mysqli_prepare($conexion, $sql_saldo_inicial);
    $saldo_inicial = 0;
    $naturaleza = $cuenta_info['naturaleza'];
    
    if ($stmt_saldo) {
        mysqli_stmt_bind_param($stmt_saldo, "iis", $empresa_idx, $cuenta_id, $fecha_desde);
        mysqli_stmt_execute($stmt_saldo);
        $result_saldo = mysqli_stmt_get_result($stmt_saldo);
        $row_saldo = mysqli_fetch_assoc($result_saldo);
        $importe_inicial = floatval($row_saldo['saldo_inicial']);
        
        if ($naturaleza == 'D') {
            $saldo_inicial = $importe_inicial;
        } else {
            $saldo_inicial = -$importe_inicial;
        }
        mysqli_stmt_close($stmt_saldo);
    }
    
    // Obtener movimientos detallados
    $sql_detalle = "
        SELECT 
            ca.fecha,
            ca.numero_asiento,
            ca.descripcion as asiento_descripcion,
            COALESCE(c.nombre, '') as comprobante_nombre,
            cad.importe_local,
            COALESCE(cad.descripcion, '') as detalle_descripcion
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.asiento_id = ca.id
        LEFT JOIN gestion__comprobantes c ON ca.comprobante_id = c.comprobante_id
        WHERE ca.empresa_id = ?
        AND ca.estado = 'registrado'
        AND cad.estado = 'activo'
        AND cad.cuenta_id = ?
        AND ca.fecha BETWEEN ? AND ?
        ORDER BY ca.fecha ASC, ca.id ASC
    ";
    
    $stmt = mysqli_prepare($conexion, $sql_detalle);
    if (!$stmt) {
        error_log("Error preparando query detalle: " . mysqli_error($conexion));
        return ['detalle' => [], 'saldo_inicial' => $saldo_inicial, 'total_debe' => 0, 'total_haber' => 0, 'saldo_final' => $saldo_inicial, 'cuenta_info' => $cuenta_info];
    }
    
    mysqli_stmt_bind_param($stmt, "iiss", $empresa_idx, $cuenta_id, $fecha_desde, $fecha_hasta);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $movimientos = [];
    $saldo_actual = $saldo_inicial;
    $total_debe = 0;
    $total_haber = 0;
    
    while ($fila = mysqli_fetch_assoc($result)) {
        $importe = floatval($fila['importe_local']);
        
        // Determinar debe y haber según naturaleza
        if ($naturaleza == 'D') {
            $debe = $importe > 0 ? $importe : 0;
            $haber = $importe < 0 ? -$importe : 0;
            $saldo_actual += $importe;
        } else {
            $debe = $importe < 0 ? -$importe : 0;
            $haber = $importe > 0 ? $importe : 0;
            $saldo_actual += -$importe;
        }
        
        $total_debe += $debe;
        $total_haber += $haber;
        
        $descripcion = !empty($fila['detalle_descripcion']) ? $fila['detalle_descripcion'] : $fila['asiento_descripcion'];
        
        $movimientos[] = [
            'fecha' => date('d/m/Y', strtotime($fila['fecha'])),
            'numero_asiento' => $fila['numero_asiento'],
            'comprobante' => !empty($fila['comprobante_nombre']) ? $fila['comprobante_nombre'] : '-',
            'descripcion' => !empty($descripcion) ? $descripcion : '-',
            'debe' => $debe,
            'haber' => $haber,
            'saldo' => $saldo_actual
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    return [
        'detalle' => $movimientos,
        'saldo_inicial' => $saldo_inicial,
        'total_debe' => $total_debe,
        'total_haber' => $total_haber,
        'saldo_final' => $saldo_actual,
        'cuenta_info' => $cuenta_info
    ];
}

/**
 * Obtener resumen para exportación
 */
function obtenerResumenExportacion($conexion, $empresa_idx, $fecha_desde, $fecha_hasta)
{
    $saldos = obtenerSaldosCuentas($conexion, $empresa_idx, $fecha_desde, $fecha_hasta);
    
    $totales = [
        'saldo_inicial' => 0,
        'total_debe' => 0,
        'total_haber' => 0,
        'saldo_final' => 0
    ];
    
    foreach ($saldos as $cuenta) {
        $totales['saldo_inicial'] += $cuenta['saldo_inicial'];
        $totales['total_debe'] += $cuenta['total_debe'];
        $totales['total_haber'] += $cuenta['total_haber'];
        $totales['saldo_final'] += $cuenta['saldo_final'];
    }
    
    return [
        'saldos' => $saldos,
        'totales' => $totales,
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta
    ];
}
?>