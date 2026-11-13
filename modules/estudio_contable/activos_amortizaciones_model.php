<?php
/**
 * Modelo de Datos: Mini-Gestión y Amortización de Activos Fijos
 * Módulo 4: Estudio Contable (activos_amortizaciones_model.php)
 * Tablas: gestion__activos_fijos (423), gestion__activos_fijos_amortizaciones (424), gestion__cont_asientos, gestion__cont_asientos_detalles
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/cont_periodos.php';
require_once __DIR__ . '/../../core/formato.php';
require_once __DIR__ . '/../gestion/tesoreria_core_model.php';

/**
 * Carga de combos para activos fijos
 */
function activos_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'categorias' => ['INMUEBLE', 'RODADO', 'MAQUINARIA', 'MUEBLES_UTILES', 'EQUIPO_COMPUTACION', 'INSTALACION', 'OTRO'],
        'metodos' => ['LINEAL_MENSUAL', 'LINEAL_ANUAL'],
        'cuentas' => []
    ];

    // Cuentas contables imputables
    $sql = "SELECT cont_cuenta_id, codigo, nombre, naturaleza 
            FROM gestion__cont_cuentas 
            WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1 
            ORDER BY codigo";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $combos['cuentas'][] = $r;
    }
    mysqli_stmt_close($stmt);

    return $combos;
}

/**
 * Listar activos fijos de la empresa
 */
function activos_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $sql = "SELECT a.activo_id, a.empresa_id, a.codigo, a.descripcion, a.categoria_activo,
                   a.f_adquisicion, a.valor_origen, a.valor_residual, a.vida_util_meses,
                   a.meses_amortizados, a.metodo_amortizacion, a.cuenta_activo_id,
                   a.cuenta_amort_acum_id, a.cuenta_gasto_amort_id, a.tabla_estado_registro_id,
                   c_act.nombre AS cuenta_activo_nombre,
                   c_acum.nombre AS cuenta_acum_nombre,
                   c_gst.nombre AS cuenta_gasto_nombre,
                   (a.vida_util_meses - a.meses_amortizados) AS meses_restantes,
                   ROUND((a.valor_origen - a.valor_residual) / a.vida_util_meses, 2) AS cuota_mensual_estimada,
                   COALESCE((SELECT SUM(am.importe_amortizacion) 
                             FROM gestion__activos_fijos_amortizaciones am 
                             WHERE am.empresa_id = a.empresa_id AND am.activo_id = a.activo_id), 0) AS total_amortizado_real
            FROM gestion__activos_fijos a
            LEFT JOIN gestion__cont_cuentas c_act ON c_act.cont_cuenta_id = a.cuenta_activo_id
            LEFT JOIN gestion__cont_cuentas c_acum ON c_acum.cont_cuenta_id = a.cuenta_amort_acum_id
            LEFT JOIN gestion__cont_cuentas c_gst ON c_gst.cont_cuenta_id = a.cuenta_gasto_amort_id
            WHERE a.empresa_id = ?";

    $params = [$empresa_id];
    $types = "i";

    if (!empty($filtros['categoria_activo'])) {
        $sql .= " AND a.categoria_activo = ?";
        $params[] = trim($filtros['categoria_activo']);
        $types .= "s";
    }

    if (!empty($filtros['buscar'])) {
        $busq = '%' . trim($filtros['buscar']) . '%';
        $sql .= " AND (a.codigo LIKE ? OR a.descripcion LIKE ?)";
        $params[] = $busq;
        $params[] = $busq;
        $types .= "ss";
    }

    $sql .= " ORDER BY a.activo_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['valor_residual_contable'] = round(floatval($r['valor_origen']) - floatval($r['total_amortizado_real']), 2);
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Guardar o actualizar activo fijo con los 8 campos universales de proceso
 */
function activo_guardar(mysqli $conexion, int $empresa_id, array $datos): array
{
    $activo_id = intval($datos['activo_id'] ?? 0);
    $codigo = trim($datos['codigo'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $categoria = trim($datos['categoria_activo'] ?? 'MUEBLES_UTILES');
    $f_adquisicion = !empty($datos['f_adquisicion']) ? $datos['f_adquisicion'] : date('Y-m-d');
    $valor_origen = floatval($datos['valor_origen'] ?? 0.0);
    $valor_residual = floatval($datos['valor_residual'] ?? 0.0);
    $vida_util_meses = intval($datos['vida_util_meses'] ?? 60);
    $metodo = trim($datos['metodo_amortizacion'] ?? 'LINEAL_MENSUAL');
    $cuenta_activo_id = intval($datos['cuenta_activo_id'] ?? 0);
    $cuenta_amort_acum_id = intval($datos['cuenta_amort_acum_id'] ?? 0);
    $cuenta_gasto_amort_id = intval($datos['cuenta_gasto_amort_id'] ?? 0);

    if (empty($codigo) || empty($descripcion) || $valor_origen <= 0 || $vida_util_meses <= 0) {
        return ['success' => false, 'message' => 'Código, descripción, valor de origen (>0) y vida útil en meses (>0) son requeridos.', 'data' => null, 'error' => 'VALIDATION'];
    }

    mysqli_begin_transaction($conexion);

    try {
        if ($activo_id > 0) {
            $sql = "UPDATE gestion__activos_fijos SET
                        codigo = ?, descripcion = ?, categoria_activo = ?, f_adquisicion = ?,
                        valor_origen = ?, valor_residual = ?, vida_util_meses = ?, metodo_amortizacion = ?,
                        cuenta_activo_id = ?, cuenta_amort_acum_id = ?, cuenta_gasto_amort_id = ?,
                        updated_at = NOW()
                    WHERE empresa_id = ? AND activo_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "ssssddisiiiii",
                $codigo,
                $descripcion,
                $categoria,
                $f_adquisicion,
                $valor_origen,
                $valor_residual,
                $vida_util_meses,
                $metodo,
                $cuenta_activo_id,
                $cuenta_amort_acum_id,
                $cuenta_gasto_amort_id,
                $empresa_id,
                $activo_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            // Alta con los 8 campos universales de proceso:
            // empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro, comprobante_id, entidad_id, f_emision
            $sql = "INSERT INTO gestion__activos_fijos (
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv,
                        comprobante_nro, comprobante_id, entidad_id, f_emision,
                        codigo, descripcion, categoria_activo, f_adquisicion,
                        valor_origen, valor_residual, vida_util_meses, meses_amortizados,
                        metodo_amortizacion, cuenta_activo_id, cuenta_amort_acum_id, cuenta_gasto_amort_id,
                        tabla_estado_registro_id, created_at, updated_at
                    ) VALUES (
                        ?, 0, 0, 0,
                        0, NULL, 0, CURDATE(),
                        ?, ?, ?, ?,
                        ?, ?, ?, 0,
                        ?, ?, ?, ?,
                        1, NOW(), NOW()
                    )";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "issssddisiii",
                $empresa_id,
                $codigo,
                $descripcion,
                $categoria,
                $f_adquisicion,
                $valor_origen,
                $valor_residual,
                $vida_util_meses,
                $metodo,
                $cuenta_activo_id,
                $cuenta_amort_acum_id,
                $cuenta_gasto_amort_id
            );
            mysqli_stmt_execute($stmt);
            $activo_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Activo fijo registrado correctamente.', 'data' => ['activo_id' => $activo_id], 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Listar amortizaciones históricas de un activo
 */
function activo_amortizaciones_listar(mysqli $conexion, int $empresa_id, int $activo_id): array
{
    $sql = "SELECT am.*, a.cont_asiento_id, a.descripcion AS asiento_descripcion
            FROM gestion__activos_fijos_amortizaciones am
            LEFT JOIN gestion__cont_asientos a ON a.cont_asiento_id = am.asiento_id AND a.empresa_id = am.empresa_id
            WHERE am.empresa_id = ? AND am.activo_id = ?
            ORDER BY am.periodo DESC, am.cuota_nro DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $activo_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Motor de Amortización Mensual Automática y Generación de Asientos Contables
 * @param int $periodo Formato AAAAMM (ej: 202610)
 */
function activos_generar_amortizacion_mensual(mysqli $conexion, int $empresa_id, int $periodo, int $usuario_id): array
{
    if ($periodo < 200001 || $periodo > 209912) {
        return ['success' => false, 'message' => 'Período no válido. Debe tener formato AAAAMM (ej: 202610).', 'data' => null, 'error' => 'INVALID_PERIOD'];
    }

    $anio = intval(substr((string)$periodo, 0, 4));
    $mes = intval(substr((string)$periodo, 4, 2));
    $f_asiento = date("Y-m-t", strtotime("{$anio}-{$mes}-01")); // Último día del mes

    try {
        cont_periodo_exigir_abierto($conexion, $empresa_id, $f_asiento, 'generar el asiento de amortizaciones');
    } catch (RuntimeException $e) {
        return ['success' => false, 'message' => $e->getMessage(), 'data' => null, 'error' => 'PERIODO_CERRADO'];
    }

    mysqli_begin_transaction($conexion);

    try {
        // 1. Obtener activos vigentes pendientes de amortizar en este período
        $sql = "SELECT a.activo_id, a.codigo, a.descripcion, a.valor_origen, a.valor_residual,
                       a.vida_util_meses, a.meses_amortizados, a.cuenta_amort_acum_id, a.cuenta_gasto_amort_id
                FROM gestion__activos_fijos a
                WHERE a.empresa_id = ? 
                  AND a.tabla_estado_registro_id = 1
                  AND a.meses_amortizados < a.vida_util_meses
                  AND NOT EXISTS (
                      SELECT 1 FROM gestion__activos_fijos_amortizaciones am 
                      WHERE am.empresa_id = a.empresa_id AND am.activo_id = a.activo_id AND am.periodo = ?
                  )
                FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $periodo);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $activos = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $activos[] = $r;
        }
        mysqli_stmt_close($stmt);

        if (empty($activos)) {
            mysqli_commit($conexion);
            return [
                'success' => true,
                'message' => "No hay activos pendientes de amortizar para el período {$periodo} (o ya fueron amortizados).",
                'data' => ['procesados' => 0, 'asiento_id' => null, 'total_amortizado' => 0.0],
                'error' => null
            ];
        }

        // 2. Calcular cuotas individuales y total
        $total_mes = 0.0;
        $detalles_amortizar = [];

        foreach ($activos as $act) {
            $amortizable = floatval($act['valor_origen']) - floatval($act['valor_residual']);
            $vida = intval($act['vida_util_meses']);
            $cuota = round($amortizable / $vida, 2);
            // La última cuota absorbe el redondeo, así el activo queda amortizado hasta el valor residual
            if (intval($act['meses_amortizados']) + 1 === $vida) {
                $cuota = round($amortizable - $cuota * ($vida - 1), 2);
            }
            if ($cuota > 0) {
                $detalles_amortizar[] = [
                    'activo' => $act,
                    'cuota' => $cuota,
                    'cuota_nro' => intval($act['meses_amortizados']) + 1
                ];
                $total_mes += $cuota;
            }
        }

        if ($total_mes <= 0 || empty($detalles_amortizar)) {
            mysqli_commit($conexion);
            return [
                'success' => true,
                'message' => 'No se encontraron cuotas válidas mayores a cero para procesar.',
                'data' => ['procesados' => 0],
                'error' => null
            ];
        }

        // 3. Asiento automático por el generador común de gestión (tesoreriaGenerarAsiento, sin comprobante): tipo de
        // asiento AUT y moneda base de la empresa, cuentas del plan de la empresa, período abierto y Debe = Haber.
        // Cada cuota: Debe gasto de amortización, Haber amortización acumulada (importe con signo).
        $lineas = [];
        foreach ($detalles_amortizar as $det) {
            $act = $det['activo'];
            if (intval($act['cuenta_gasto_amort_id']) <= 0 || intval($act['cuenta_amort_acum_id']) <= 0) {
                throw new RuntimeException("El activo {$act['codigo']} no tiene cargadas las cuentas de gasto y de amortización acumulada.");
            }
            $desc_linea = "Amort. " . $act['codigo'] . " Cuota {$det['cuota_nro']}";
            $lineas[] = ['cuenta_id' => intval($act['cuenta_gasto_amort_id']), 'debe' => $det['cuota'], 'haber' => 0, 'descripcion' => $desc_linea];
            $lineas[] = ['cuenta_id' => intval($act['cuenta_amort_acum_id']), 'debe' => 0, 'haber' => $det['cuota'], 'descripcion' => $desc_linea];
        }
        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id' => $empresa_id,
            'sin_comprobante' => true,
            'f_asiento' => $f_asiento,
            'descripcion' => "Amortizaciones Activos Fijos - Período {$periodo} (AUTO)",
            'usuario_id' => $usuario_id,
        ], $lineas);

        // 4. Cuotas en gestion__activos_fijos_amortizaciones
        foreach ($detalles_amortizar as $det) {
            $act = $det['activo'];
            $cuota = $det['cuota'];
            $cuota_nro = $det['cuota_nro'];
            $activo_id = intval($act['activo_id']);

            // 4.1 Inserción en gestion__activos_fijos_amortizaciones con 8 campos universales
            $sql_am = "INSERT INTO gestion__activos_fijos_amortizaciones (
                           empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                           comprobante_id, entidad_id, f_emision,
                           activo_id, periodo, cuota_nro, importe_amortizacion, asiento_id, f_asiento,
                           tabla_estado_registro_id, created_at
                       ) VALUES (
                           ?, 0, 0, 0, 0,
                           NULL, 0, ?,
                           ?, ?, ?, ?, ?, ?,
                           1, NOW()
                       )";
            $stmt_am = mysqli_prepare($conexion, $sql_am);
            mysqli_stmt_bind_param($stmt_am, "isiiidis", $empresa_id, $f_asiento, $activo_id, $periodo, $cuota_nro, $cuota, $asiento_id, $f_asiento);
            mysqli_stmt_execute($stmt_am);
            mysqli_stmt_close($stmt_am);

            // 4.2 Actualizar meses_amortizados en el activo
            $sql_act_up = "UPDATE gestion__activos_fijos SET meses_amortizados = meses_amortizados + 1, updated_at = NOW() WHERE empresa_id = ? AND activo_id = ?";
            $stmt_act_up = mysqli_prepare($conexion, $sql_act_up);
            mysqli_stmt_bind_param($stmt_act_up, "ii", $empresa_id, $activo_id);
            mysqli_stmt_execute($stmt_act_up);
            mysqli_stmt_close($stmt_act_up);
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Proceso exitoso: Se amortizaron " . count($detalles_amortizar) . " activos fijos por un total de " . fmt_moneda($total_mes) . ". Asiento contable N° {$asiento_id} generado.",
            'data' => [
                'procesados' => count($detalles_amortizar),
                'total_amortizado' => $total_mes,
                'asiento_id' => $asiento_id
            ],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al amortizar período: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}
