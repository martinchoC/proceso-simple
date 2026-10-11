<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Obtener todas las cuentas contables imputables activas
 */
function obtenerCuentasContables($conexion, $empresa_idx, $search = null)
{
    $empresa_idx = intval($empresa_idx);
    $sql = "SELECT cont_cuenta_id, codigo, nombre, naturaleza, nivel, es_imputable
            FROM gestion__cont_cuentas 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1
            AND es_imputable = 1";

    $params = [$empresa_idx];
    $types = "i";

    if (!empty($search)) {
        $sql .= " AND (codigo LIKE ? OR nombre LIKE ?)";
        $search_param = '%' . trim($search) . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    $sql .= " ORDER BY codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando query cuentas: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
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
    $empresa_idx = intval($empresa_idx);

    // Establecer fechas por defecto (mes actual)
    if (empty($fecha_desde)) {
        $fecha_desde = date('Y-m-01');
    }
    if (empty($fecha_hasta)) {
        $fecha_hasta = date('Y-m-t');
    }

    // Primero, obtener cuentas activas e imputables
    $sql_cuentas = "SELECT cont_cuenta_id, codigo, nombre, naturaleza 
                    FROM gestion__cont_cuentas 
                    WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND es_imputable = 1";
    $params_cuentas = [$empresa_idx];
    $types_cuentas = "i";

    if (!empty($cuenta_id)) {
        $sql_cuentas .= " AND cont_cuenta_id = ?";
        $params_cuentas[] = intval($cuenta_id);
        $types_cuentas .= "i";
    }

    $sql_cuentas .= " ORDER BY codigo";

    $stmt_cuentas = mysqli_prepare($conexion, $sql_cuentas);
    if (!$stmt_cuentas) {
        error_log("Error preparando query cuentas: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt_cuentas, $types_cuentas, ...$params_cuentas);
    mysqli_stmt_execute($stmt_cuentas);
    $result_cuentas = mysqli_stmt_get_result($stmt_cuentas);

    $cuentas = [];
    while ($row = mysqli_fetch_assoc($result_cuentas)) {
        $cid = (int) $row['cont_cuenta_id'];
        $cuentas[$cid] = [
            'cont_cuenta_id' => $cid,
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'naturaleza' => $row['naturaleza'],
            'saldo_inicial' => 0.0,
            'total_debe' => 0.0,
            'total_haber' => 0.0,
            'saldo_final' => 0.0
        ];
    }
    mysqli_stmt_close($stmt_cuentas);

    if (empty($cuentas)) {
        return [];
    }

    // Obtener saldos iniciales (movimientos antes de fecha_desde)
    $sql_saldo_inicial = "
        SELECT 
            cad.cuenta_id,
            SUM(cad.importe_local) as total_movimiento
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.cont_asiento_id = ca.cont_asiento_id
        WHERE ca.empresa_id = ?
        AND ca.tabla_estado_registro_id != 2
        AND cad.estado = 'activo'
        AND ca.f_asiento < ?
    ";
    $params_saldo = [$empresa_idx, $fecha_desde];
    $types_saldo = "is";

    if (!empty($cuenta_id)) {
        $sql_saldo_inicial .= " AND cad.cuenta_id = ?";
        $params_saldo[] = intval($cuenta_id);
        $types_saldo .= "i";
    }

    $sql_saldo_inicial .= " GROUP BY cad.cuenta_id";

    $stmt_saldo = mysqli_prepare($conexion, $sql_saldo_inicial);
    if ($stmt_saldo) {
        mysqli_stmt_bind_param($stmt_saldo, $types_saldo, ...$params_saldo);
        mysqli_stmt_execute($stmt_saldo);
        $result_saldo = mysqli_stmt_get_result($stmt_saldo);
        while ($row = mysqli_fetch_assoc($result_saldo)) {
            $cid = (int) $row['cuenta_id'];
            if (isset($cuentas[$cid])) {
                $importe = floatval($row['total_movimiento']);
                $nat = $cuentas[$cid]['naturaleza'];
                $cuentas[$cid]['saldo_inicial'] = ($nat === 'D') ? $importe : -$importe;
            }
        }
        mysqli_stmt_close($stmt_saldo);
    }

    // Obtener movimientos del período
    $sql_mov = "
        SELECT 
            cad.cuenta_id,
            SUM(CASE WHEN cad.importe_local > 0 THEN cad.importe_local ELSE 0 END) as total_debe,
            SUM(CASE WHEN cad.importe_local < 0 THEN ABS(cad.importe_local) ELSE 0 END) as total_haber
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.cont_asiento_id = ca.cont_asiento_id
        WHERE ca.empresa_id = ?
        AND ca.tabla_estado_registro_id != 2
        AND cad.estado = 'activo'
        AND ca.f_asiento BETWEEN ? AND ?
    ";
    $params_mov = [$empresa_idx, $fecha_desde, $fecha_hasta];
    $types_mov = "iss";

    if (!empty($cuenta_id)) {
        $sql_mov .= " AND cad.cuenta_id = ?";
        $params_mov[] = intval($cuenta_id);
        $types_mov .= "i";
    }

    $sql_mov .= " GROUP BY cad.cuenta_id";

    $stmt_mov = mysqli_prepare($conexion, $sql_mov);
    if ($stmt_mov) {
        mysqli_stmt_bind_param($stmt_mov, $types_mov, ...$params_mov);
        mysqli_stmt_execute($stmt_mov);
        $result_mov = mysqli_stmt_get_result($stmt_mov);
        while ($row = mysqli_fetch_assoc($result_mov)) {
            $cid = (int) $row['cuenta_id'];
            if (isset($cuentas[$cid])) {
                $cuentas[$cid]['total_debe'] = floatval($row['total_debe']);
                $cuentas[$cid]['total_haber'] = floatval($row['total_haber']);
            }
        }
        mysqli_stmt_close($stmt_mov);
    }

    // Calcular saldo final y devolver cuentas
    $data = [];
    foreach ($cuentas as $cuenta) {
        $nat = $cuenta['naturaleza'];
        if ($nat === 'D') {
            $cuenta['saldo_final'] = $cuenta['saldo_inicial'] + $cuenta['total_debe'] - $cuenta['total_haber'];
        } else {
            $cuenta['saldo_final'] = $cuenta['saldo_inicial'] + $cuenta['total_haber'] - $cuenta['total_debe'];
        }

        // Si se filtró por una cuenta en específico se incluye siempre; de lo contrario solo las que tengan movimientos/saldo
        if (!empty($cuenta_id) || abs($cuenta['saldo_inicial']) > 0.0001 || $cuenta['total_debe'] > 0.0001 || $cuenta['total_haber'] > 0.0001) {
            $data[] = $cuenta;
        }
    }

    return $data;
}

/**
 * Obtener detalle de movimientos de una cuenta específica
 */
function obtenerDetalleCuenta($conexion, $empresa_idx, $cuenta_id, $fecha_desde, $fecha_hasta)
{
    $empresa_idx = intval($empresa_idx);
    $cuenta_id = intval($cuenta_id);

    if (empty($fecha_desde)) {
        $fecha_desde = date('Y-m-01');
    }
    if (empty($fecha_hasta)) {
        $fecha_hasta = date('Y-m-t');
    }

    // Obtener información de la cuenta
    $sql_cuenta = "SELECT cont_cuenta_id, codigo, nombre, naturaleza 
                   FROM gestion__cont_cuentas 
                   WHERE cont_cuenta_id = ? AND empresa_id = ?";
    $stmt_cuenta = mysqli_prepare($conexion, $sql_cuenta);
    $cuenta_info = ['codigo' => '', 'nombre' => '', 'naturaleza' => 'D'];

    if ($stmt_cuenta) {
        mysqli_stmt_bind_param($stmt_cuenta, "ii", $cuenta_id, $empresa_idx);
        mysqli_stmt_execute($stmt_cuenta);
        $result_cuenta = mysqli_stmt_get_result($stmt_cuenta);
        $row_info = mysqli_fetch_assoc($result_cuenta);
        if ($row_info) {
            $cuenta_info = $row_info;
        }
        mysqli_stmt_close($stmt_cuenta);
    }

    $naturaleza = $cuenta_info['naturaleza'];

    // Obtener saldo inicial (movimientos antes de fecha_desde)
    $sql_saldo_inicial = "
        SELECT COALESCE(SUM(cad.importe_local), 0) as total_movimiento
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.cont_asiento_id = ca.cont_asiento_id
        WHERE ca.empresa_id = ?
        AND ca.tabla_estado_registro_id != 2
        AND cad.estado = 'activo'
        AND cad.cuenta_id = ?
        AND ca.f_asiento < ?
    ";

    $stmt_saldo = mysqli_prepare($conexion, $sql_saldo_inicial);
    $saldo_inicial = 0.0;

    if ($stmt_saldo) {
        mysqli_stmt_bind_param($stmt_saldo, "iis", $empresa_idx, $cuenta_id, $fecha_desde);
        mysqli_stmt_execute($stmt_saldo);
        $result_saldo = mysqli_stmt_get_result($stmt_saldo);
        $row_saldo = mysqli_fetch_assoc($result_saldo);
        $importe_inicial = floatval($row_saldo['total_movimiento'] ?? 0);

        if ($naturaleza === 'D') {
            $saldo_inicial = $importe_inicial;
        } else {
            $saldo_inicial = -$importe_inicial;
        }
        mysqli_stmt_close($stmt_saldo);
    }

    // Obtener movimientos detallados
    $sql_detalle = "
        SELECT 
            ca.f_asiento as fecha,
            ca.cont_asiento_id,
            ca.anio,
            ca.descripcion as asiento_descripcion,
            COALESCE(ct.comprobante_tipo, c.comprobante_codigo, '-') as comprobante_nombre,
            cad.importe_local,
            COALESCE(cad.descripcion, '') as detalle_descripcion
        FROM gestion__cont_asientos_detalles cad
        INNER JOIN gestion__cont_asientos ca ON cad.cont_asiento_id = ca.cont_asiento_id
        LEFT JOIN gestion__comprobantes c ON ca.comprobante_id = c.comprobante_id
        LEFT JOIN gestion__comprobantes_tipos ct ON c.comprobante_tipo_id = ct.comprobante_tipo_id
        WHERE ca.empresa_id = ?
        AND ca.tabla_estado_registro_id != 2
        AND cad.estado = 'activo'
        AND cad.cuenta_id = ?
        AND ca.f_asiento BETWEEN ? AND ?
        ORDER BY ca.f_asiento ASC, ca.cont_asiento_id ASC
    ";

    $stmt = mysqli_prepare($conexion, $sql_detalle);
    if (!$stmt) {
        error_log("Error preparando query detalle: " . mysqli_error($conexion));
        return [
            'detalle' => [],
            'saldo_inicial' => $saldo_inicial,
            'total_debe' => 0.0,
            'total_haber' => 0.0,
            'saldo_final' => $saldo_inicial,
            'cuenta_info' => $cuenta_info
        ];
    }

    mysqli_stmt_bind_param($stmt, "iiss", $empresa_idx, $cuenta_id, $fecha_desde, $fecha_hasta);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $movimientos = [];
    $saldo_actual = $saldo_inicial;
    $total_debe = 0.0;
    $total_haber = 0.0;

    while ($fila = mysqli_fetch_assoc($result)) {
        $importe = floatval($fila['importe_local']);

        $debe = $importe > 0 ? $importe : 0.0;
        $haber = $importe < 0 ? abs($importe) : 0.0;

        if ($naturaleza === 'D') {
            $saldo_actual += ($debe - $haber);
        } else {
            $saldo_actual += ($haber - $debe);
        }

        $total_debe += $debe;
        $total_haber += $haber;

        $descripcion = !empty($fila['detalle_descripcion']) ? $fila['detalle_descripcion'] : $fila['asiento_descripcion'];
        $num_asiento = ($fila['anio'] ?? date('Y', strtotime($fila['fecha']))) . str_pad($fila['cont_asiento_id'], 6, '0', STR_PAD_LEFT);

        $movimientos[] = [
            'fecha' => date('d/m/Y', strtotime($fila['fecha'])),
            'numero_asiento' => $num_asiento,
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
        'saldo_inicial' => 0.0,
        'total_debe' => 0.0,
        'total_haber' => 0.0,
        'saldo_final' => 0.0
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