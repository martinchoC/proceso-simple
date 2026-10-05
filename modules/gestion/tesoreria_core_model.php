<?php
/**
 * tesoreria_core_model.php
 *
 * Módulo de Tesorería - Servicios y Helpers Centrales (Fase 0)
 * Sistema gestion_multipyme
 *
 * Implementa las decisiones de diseño D1-D36 de docs/gestion/08_proc_tesoreria_v3_11.docx:
 * - D2:  importe_pendiente como caché sincronizada bajo lock.
 * - D4:  Cuentas unificadas de tesorería y libro de movimientos.
 * - D5:  Imputaciones con estado propio, fecha, moneda y tipo de cambio.
 * - D8:  recalcularEstadoDestino() como única fuente de estados derivados.
 * - D15: Libro de tesorería inmutable con reversas para anulación.
 * - D27: Asientos automáticos de tesorería vinculados al comprobante.
 * - D35/D36: Resolución de cuentas contables sin IDs hardcodeados.
 * - RT16: Orden global de locks (ORDER BY comprobante_id ASC FOR UPDATE).
 */

if (!defined('ESTADO_CONFIRMADO'))        define('ESTADO_CONFIRMADO', 2126);
if (!defined('ESTADO_CANCELADO_PARCIAL')) define('ESTADO_CANCELADO_PARCIAL', 2122);
if (!defined('ESTADO_CANCELADO_TOTAL'))   define('ESTADO_CANCELADO_TOTAL', 2123);
if (!defined('ESTADO_ANULADO'))           define('ESTADO_ANULADO', 2127);

if (!defined('ESTADO_IMPUTACION_BORRADOR'))   define('ESTADO_IMPUTACION_BORRADOR', 2153);
if (!defined('ESTADO_IMPUTACION_CONFIRMADO')) define('ESTADO_IMPUTACION_CONFIRMADO', 2154); // Vigente
if (!defined('ESTADO_IMPUTACION_ANULADO'))    define('ESTADO_IMPUTACION_ANULADO', 2155);

if (!defined('ESTADO_TESORERIA_MOV_ACTIVO'))  define('ESTADO_TESORERIA_MOV_ACTIVO', 2156);

if (!defined('ESTADO_ILIQ_PENDIENTE')) define('ESTADO_ILIQ_PENDIENTE', 2161);
if (!defined('ESTADO_ILIQ_PAGADO'))    define('ESTADO_ILIQ_PAGADO', 2162);

if (!defined('ESTADO_RG_REND_APROBADA'))    define('ESTADO_RG_REND_APROBADA', 2167);
if (!defined('ESTADO_RG_REND_REEMBOLSADA')) define('ESTADO_RG_REND_REEMBOLSADA', 2168);

/**
 * 1. RESOLUCIÓN DE CUENTAS CONTABLES (D35 / D36 / §4.10)
 *
 * Orden de resolución estricto:
 * 1) Cuenta contable específica asignada (si se proporciona $cuenta_especifica_id).
 * 2) Concepto de cuenta contable por empresa en gestion__empresas_cuentas_conceptos.
 * 3) Si no resuelve: lanza Exception bloqueante. NUNCA devuelve un ID fijo hardcodeado.
 *
 * @param mysqli $conexion
 * @param int    $empresa_id
 * @param string $concepto_codigo Nombre del concepto (ej: 'VALORES_A_PAGAR', 'EMPLEADOS') o descriptor
 * @param int|null $cuenta_especifica_id ID opcional de cuenta ya configurada en el registro
 * @return array ['cont_cuenta_id' => int, 'codigo' => string, 'cuenta' => string]
 * @throws Exception
 */
function tesoreriaResolverCuentaContable($conexion, $empresa_id, $concepto_codigo, $cuenta_especifica_id = null)
{
    $empresa_id = intval($empresa_id);
    if ($empresa_id <= 0) {
        throw new Exception("Error contable D36: empresa_id inválido.");
    }

    // 1) Cuenta específica si fue provista
    if (!empty($cuenta_especifica_id) && intval($cuenta_especifica_id) > 0) {
        $cta_id = intval($cuenta_especifica_id);
        $sql = "SELECT cont_cuenta_id, codigo, nombre AS cuenta, es_imputable, tabla_estado_registro_id
                FROM gestion__cont_cuentas
                WHERE cont_cuenta_id = ? AND empresa_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta de cuenta contable: " . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, "ii", $cta_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cuenta = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($cuenta) {
            if (intval($cuenta['tabla_estado_registro_id']) !== 1) {
                throw new Exception("Error contable: La cuenta {$cuenta['codigo']} - {$cuenta['cuenta']} está inactiva.");
            }
            if (intval($cuenta['es_imputable']) !== 1) {
                throw new Exception("Error contable: La cuenta {$cuenta['codigo']} - {$cuenta['cuenta']} es una cuenta título (no imputable).");
            }
            return [
                'cont_cuenta_id' => intval($cuenta['cont_cuenta_id']),
                'codigo'         => $cuenta['codigo'],
                'cuenta'         => $cuenta['cuenta']
            ];
        }
    }

    // 2) Concepto por empresa en gestion__empresas_cuentas_conceptos (D35)
    $concepto_limpio = trim(strtoupper($concepto_codigo));
    $sql_concepto = "SELECT cc.cont_cuenta_id, cc.codigo, cc.nombre AS cuenta, cc.es_imputable, cc.tabla_estado_registro_id AS cta_estado
                     FROM gestion__empresas_cuentas_conceptos ecc
                     JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = ecc.cont_cuenta_id
                     WHERE ecc.empresa_id = ? 
                       AND ecc.concepto = ?
                       AND ecc.tabla_estado_registro_id = 1
                     LIMIT 1";
    $stmt_c = mysqli_prepare($conexion, $sql_concepto);
    if ($stmt_c) {
        mysqli_stmt_bind_param($stmt_c, "is", $empresa_id, $concepto_limpio);
        mysqli_stmt_execute($stmt_c);
        $res_c = mysqli_stmt_get_result($stmt_c);
        $cuenta_c = mysqli_fetch_assoc($res_c);
        mysqli_stmt_close($stmt_c);

        if ($cuenta_c) {
            if (intval($cuenta_c['cta_estado']) !== 1) {
                throw new Exception("Error contable: La cuenta asignada al concepto '$concepto_limpio' ({$cuenta_c['codigo']}) está inactiva.");
            }
            if (intval($cuenta_c['es_imputable']) !== 1) {
                throw new Exception("Error contable: La cuenta asignada al concepto '$concepto_limpio' ({$cuenta_c['codigo']}) no es imputable.");
            }
            return [
                'cont_cuenta_id' => intval($cuenta_c['cont_cuenta_id']),
                'codigo'         => $cuenta_c['codigo'],
                'cuenta'         => $cuenta_c['cuenta']
            ];
        }
    }

    // 3) No resuelve: error explícito y bloqueante (D36)
    throw new Exception("Error contable D36: No se encontró cuenta contable válida e imputable para el concepto '$concepto_codigo' en la empresa $empresa_id. Configure la cuenta en el plan contable o en Conceptos de Cuenta por Empresa.");
}

/**
 * 2. MOVIMIENTOS EN EL LIBRO DE TESORERÍA (D4 / D15 / §4.6)
 *
 * Registra un movimiento y actualiza atómicamente el saldo_actual de la cuenta.
 *
 * @param mysqli $conexion
 * @param array  $data
 * @return int ID del movimiento creado en gestion__tesoreria_movimientos
 * @throws Exception
 */
function tesoreriaRegistrarMovimiento($conexion, $data)
{
    $empresa_id          = intval($data['empresa_id'] ?? 0);
    $sucursal_id         = intval($data['sucursal_id'] ?? 0);
    $tesoreria_cuenta_id = intval($data['tesoreria_cuenta_id'] ?? 0);
    $comprobante_id      = intval($data['comprobante_id'] ?? 0);
    $tipo_movimiento     = trim(strtoupper($data['tipo_movimiento'] ?? ''));
    $importe             = round(floatval($data['importe'] ?? 0), 2);
    $f_movimiento        = !empty($data['f_movimiento']) ? $data['f_movimiento'] : date('Y-m-d');
    $f_valor             = !empty($data['f_valor']) ? $data['f_valor'] : $f_movimiento;
    $moneda_id           = intval($data['moneda_id'] ?? 1);
    $tipo_cambio         = floatval($data['tipo_cambio'] ?? 1.0);
    $medio_pago_id       = !empty($data['medio_pago_id']) ? intval($data['medio_pago_id']) : null;
    $cheque_id           = !empty($data['cheque_id']) ? intval($data['cheque_id']) : null;
    $concepto            = trim($data['concepto'] ?? 'Movimiento de tesorería');
    $tabla_origen_id     = !empty($data['tabla_origen_id']) ? intval($data['tabla_origen_id']) : null;
    $registro_origen_id  = !empty($data['registro_origen_id']) ? intval($data['registro_origen_id']) : null;
    $reversa_de_id       = !empty($data['reversa_de_movimiento_id']) ? intval($data['reversa_de_movimiento_id']) : null;
    $usuario_id          = !empty($data['usuario_id']) ? intval($data['usuario_id']) : null;

    if ($empresa_id <= 0 || $tesoreria_cuenta_id <= 0 || $comprobante_id <= 0) {
        throw new Exception("Parámetros obligatorios faltantes para movimiento de tesorería.");
    }
    if (!in_array($tipo_movimiento, ['INGRESO', 'EGRESO'], true)) {
        throw new Exception("Tipo de movimiento inválido: '$tipo_movimiento'. Debe ser INGRESO o EGRESO.");
    }
    if ($importe <= 0) {
        throw new Exception("El importe del movimiento de tesorería debe ser mayor a 0.");
    }

    // Lock de la cuenta para actualización atómica de saldo (A4 / §4.6)
    $sql_cta = "SELECT tesoreria_cuenta_id, tesoreria_cuenta, saldo_actual, permite_saldo_negativo, moneda_id, tabla_estado_registro_id
                FROM gestion__tesoreria_cuentas
                WHERE tesoreria_cuenta_id = ? AND empresa_id = ?
                FOR UPDATE";
    $stmt_c = mysqli_prepare($conexion, $sql_cta);
    if (!$stmt_c) {
        throw new Exception("Error preparando lock de cuenta de tesorería: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt_c, "ii", $tesoreria_cuenta_id, $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $cuenta = mysqli_fetch_assoc($res_c);
    mysqli_stmt_close($stmt_c);

    if (!$cuenta) {
        throw new Exception("Cuenta de tesorería ID $tesoreria_cuenta_id no encontrada para la empresa $empresa_id.");
    }
    if (intval($cuenta['tabla_estado_registro_id']) !== 1) {
        throw new Exception("La cuenta de tesorería '{$cuenta['tesoreria_cuenta']}' no está activa.");
    }

    $saldo_actual = floatval($cuenta['saldo_actual']);
    $permite_neg  = intval($cuenta['permite_saldo_negativo']) === 1;

    if ($tipo_movimiento === 'EGRESO') {
        $nuevo_saldo = round($saldo_actual - $importe, 2);
        if (!$permite_neg && $nuevo_saldo < -0.001) {
            throw new Exception("Saldo insuficiente en '{$cuenta['tesoreria_cuenta']}'. Saldo actual: " . number_format($saldo_actual, 2, ',', '.') . ", egreso requerido: " . number_format($importe, 2, ',', '.'));
        }
    } else {
        $nuevo_saldo = round($saldo_actual + $importe, 2);
    }

    // 1) Actualizar saldo_actual en gestion__tesoreria_cuentas
    $sql_upd_cta = "UPDATE gestion__tesoreria_cuentas 
                    SET saldo_actual = ?, fecha_modificacion = NOW(), usuario_modificacion_id = ?
                    WHERE tesoreria_cuenta_id = ?";
    $stmt_u = mysqli_prepare($conexion, $sql_upd_cta);
    if (!$stmt_u) {
        throw new Exception("Error preparando actualización de saldo: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt_u, "dii", $nuevo_saldo, $usuario_id, $tesoreria_cuenta_id);
    if (!mysqli_stmt_execute($stmt_u)) {
        throw new Exception("Error actualizando saldo de la cuenta: " . mysqli_stmt_error($stmt_u));
    }
    mysqli_stmt_close($stmt_u);

    // 2) Insertar en gestion__tesoreria_movimientos (inmutable)
    $estado_mov = ESTADO_TESORERIA_MOV_ACTIVO;
    $sql_mov = "INSERT INTO gestion__tesoreria_movimientos (
                    empresa_id, sucursal_id, tesoreria_cuenta_id, comprobante_id,
                    tabla_origen_id, registro_origen_id, tipo_movimiento,
                    f_movimiento, f_valor, moneda_id, tipo_cambio, importe,
                    medio_pago_id, cheque_id, concepto, reversa_de_movimiento_id,
                    tabla_estado_registro_id, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_m = mysqli_prepare($conexion, $sql_mov);
    if (!$stmt_m) {
        throw new Exception("Error preparando inserción en libro de tesorería: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param(
        $stmt_m,
        "iiiiissssidiiisiii",
        $empresa_id,
        $sucursal_id,
        $tesoreria_cuenta_id,
        $comprobante_id,
        $tabla_origen_id,
        $registro_origen_id,
        $tipo_movimiento,
        $f_movimiento,
        $f_valor,
        $moneda_id,
        $tipo_cambio,
        $importe,
        $medio_pago_id,
        $cheque_id,
        $concepto,
        $reversa_de_id,
        $estado_mov,
        $usuario_id
    );
    if (!mysqli_stmt_execute($stmt_m)) {
        throw new Exception("Error insertando movimiento de tesorería: " . mysqli_stmt_error($stmt_m));
    }
    $movimiento_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_m);

    return $movimiento_id;
}

/**
 * Anula un movimiento de tesorería mediante un contramovimiento (D15 libro inmutable).
 *
 * @param mysqli $conexion
 * @param int    $movimiento_id_original
 * @param int    $usuario_id
 * @param string $motivo
 * @return int ID del contramovimiento generado
 * @throws Exception
 */
function tesoreriaAnularMovimiento($conexion, $movimiento_id_original, $usuario_id, $motivo = '')
{
    $mov_id = intval($movimiento_id_original);
    if ($mov_id <= 0) {
        throw new Exception("ID de movimiento original inválido.");
    }

    // Lock del movimiento original
    $sql = "SELECT * FROM gestion__tesoreria_movimientos WHERE tesoreria_movimiento_id = ? FOR UPDATE";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $mov_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $orig = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$orig) {
        throw new Exception("Movimiento de tesorería ID $mov_id no encontrado.");
    }

    // Verificar si ya fue revertido
    $sql_check = "SELECT tesoreria_movimiento_id FROM gestion__tesoreria_movimientos WHERE reversa_de_movimiento_id = ? LIMIT 1";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "i", $mov_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $ya_revertido = mysqli_fetch_assoc($res_c);
    mysqli_stmt_close($stmt_c);

    if ($ya_revertido) {
        throw new Exception("El movimiento de tesorería ID $mov_id ya fue revertido por el movimiento ID {$ya_revertido['tesoreria_movimiento_id']}.");
    }

    // Crear contramovimiento inverso
    $tipo_inverso = ($orig['tipo_movimiento'] === 'INGRESO') ? 'EGRESO' : 'INGRESO';
    $concepto_reversa = "Reversa de mov #" . $mov_id . ($motivo ? ": $motivo" : "");

    return tesoreriaRegistrarMovimiento($conexion, [
        'empresa_id'               => $orig['empresa_id'],
        'sucursal_id'              => $orig['sucursal_id'],
        'tesoreria_cuenta_id'      => $orig['tesoreria_cuenta_id'],
        'comprobante_id'           => $orig['comprobante_id'],
        'tipo_movimiento'          => $tipo_inverso,
        'importe'                  => $orig['importe'],
        'f_movimiento'             => date('Y-m-d'),
        'f_valor'                  => date('Y-m-d'),
        'moneda_id'                => $orig['moneda_id'],
        'tipo_cambio'              => $orig['tipo_cambio'],
        'medio_pago_id'            => $orig['medio_pago_id'],
        'cheque_id'                => $orig['cheque_id'],
        'concepto'                 => $concepto_reversa,
        'tabla_origen_id'          => $orig['tabla_origen_id'],
        'registro_origen_id'       => $orig['registro_origen_id'],
        'reversa_de_movimiento_id' => $mov_id,
        'usuario_id'               => $usuario_id
    ]);
}

/**
 * 3. IMPUTACIONES UNIVERSALES Y RECÁLCULO DE SALDOS (C4 / C5 / C6 / D2 / D5 / D8)
 *
 * Registra una imputación en gestion__comprobantes_imputaciones bajo lock global
 * ordenado (RT16) y actualiza la caché importe_pendiente del comprobante destino y origen.
 *
 * @param mysqli $conexion
 * @param array  $data
 * @return int ID de la imputación creada
 * @throws Exception
 */
function tesoreriaRegistrarImputacion($conexion, $data)
{
    $empresa_id       = intval($data['empresa_id'] ?? 0);
    $comp_origen_id   = intval($data['comp_origen_id'] ?? 0);
    $comp_destino_id  = intval($data['comp_destino_id'] ?? 0);
    $importe_imputado = round(floatval($data['importe_imputado'] ?? 0), 2); // Moneda destino
    $importe_origen   = round(floatval($data['importe_origen'] ?? $importe_imputado), 2); // Moneda origen
    $moneda_origen_id = intval($data['moneda_origen_id'] ?? 1);
    $tipo_cambio      = floatval($data['tipo_cambio'] ?? 1.0);
    $f_imputacion     = !empty($data['f_imputacion']) ? $data['f_imputacion'] : date('Y-m-d');
    $usuario_id       = !empty($data['usuario_id']) ? intval($data['usuario_id']) : null;

    if ($empresa_id <= 0 || $comp_origen_id <= 0 || $comp_destino_id <= 0) {
        throw new Exception("Parámetros obligatorios faltantes para registrar imputación.");
    }
    if ($importe_imputado <= 0 || $importe_origen <= 0) {
        throw new Exception("El importe a imputar debe ser estrictamente positivo.");
    }

    // RT16 / §2.5: Lock global ordenado por comprobante_id ASC para evitar deadlocks
    $id_menor = min($comp_origen_id, $comp_destino_id);
    $id_mayor = max($comp_origen_id, $comp_destino_id);

    $sql_lock = "SELECT comprobante_id, comprobante_tipo_id, importe_total, importe_pendiente, tabla_origen_id, registro_origen_id, tabla_estado_registro_id
                 FROM gestion__comprobantes
                 WHERE comprobante_id IN (?, ?) AND empresa_id = ?
                 ORDER BY comprobante_id ASC
                 FOR UPDATE";
    $stmt_l = mysqli_prepare($conexion, $sql_lock);
    if (!$stmt_l) {
        throw new Exception("Error preparando lock de comprobantes: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt_l, "iii", $id_menor, $id_mayor, $empresa_id);
    mysqli_stmt_execute($stmt_l);
    $res_l = mysqli_stmt_get_result($stmt_l);

    $comprobantes = [];
    while ($r = mysqli_fetch_assoc($res_l)) {
        $comprobantes[intval($r['comprobante_id'])] = $r;
    }
    mysqli_stmt_close($stmt_l);

    if (!isset($comprobantes[$comp_destino_id])) {
        throw new Exception("Comprobante destino ID $comp_destino_id no encontrado.");
    }
    if (!isset($comprobantes[$comp_origen_id])) {
        throw new Exception("Comprobante origen ID $comp_origen_id no encontrado.");
    }

    $dest = $comprobantes[$comp_destino_id];
    $saldo_destino_actual = floatval($dest['importe_pendiente']);

    // Validar sobre-imputación concurrente (C4 / RT16)
    if ($importe_imputado > round($saldo_destino_actual + 0.001, 2)) {
        throw new Exception("Sobre-imputación rechazada: el importe a imputar (" . number_format($importe_imputado, 2) . ") supera el saldo pendiente del comprobante destino (" . number_format($saldo_destino_actual, 2) . ").");
    }

    // 1) Insertar en gestion__comprobantes_imputaciones con estado CONFIRMADO (2154)
    $estado_vigente = ESTADO_IMPUTACION_CONFIRMADO;
    $sql_ins = "INSERT INTO gestion__comprobantes_imputaciones (
                    empresa_id, comp_origen_id, comp_destino_id, f_imputacion,
                    importe_imputado, moneda_origen_id, tipo_cambio, importe_origen,
                    tabla_estado_registro_id, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_i = mysqli_prepare($conexion, $sql_ins);
    if (!$stmt_i) {
        throw new Exception("Error preparando inserción de imputación: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param(
        $stmt_i,
        "iiisdidisi",
        $empresa_id,
        $comp_origen_id,
        $comp_destino_id,
        $f_imputacion,
        $importe_imputado,
        $moneda_origen_id,
        $tipo_cambio,
        $importe_origen,
        $estado_vigente,
        $usuario_id
    );
    if (!mysqli_stmt_execute($stmt_i)) {
        throw new Exception("Error insertando imputación: " . mysqli_stmt_error($stmt_i));
    }
    $imputacion_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_i);

    // 2) Actualizar importe_pendiente en el destino
    $nuevo_pendiente_dest = max(0.0, round($saldo_destino_actual - $importe_imputado, 2));
    $sql_upd_dest = "UPDATE gestion__comprobantes 
                     SET importe_pendiente = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                     WHERE comprobante_id = ?";
    $stmt_ud = mysqli_prepare($conexion, $sql_upd_dest);
    mysqli_stmt_bind_param($stmt_ud, "dii", $nuevo_pendiente_dest, $usuario_id, $comp_destino_id);
    mysqli_stmt_execute($stmt_ud);
    mysqli_stmt_close($stmt_ud);

    // 3) Si el origen también maneja saldo pendiente (cobro a cuenta, anticipo RG_ANT, NC)
    $orig = $comprobantes[$comp_origen_id];
    $saldo_orig_actual = floatval($orig['importe_pendiente']);
    if ($saldo_orig_actual > 0) {
        $nuevo_pendiente_orig = max(0.0, round($saldo_orig_actual - $importe_origen, 2));
        $sql_upd_orig = "UPDATE gestion__comprobantes 
                         SET importe_pendiente = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                         WHERE comprobante_id = ?";
        $stmt_uo = mysqli_prepare($conexion, $sql_upd_orig);
        mysqli_stmt_bind_param($stmt_uo, "dii", $nuevo_pendiente_orig, $usuario_id, $comp_origen_id);
        mysqli_stmt_execute($stmt_uo);
        mysqli_stmt_close($stmt_uo);
    }

    // 4) Recalcular estado del comprobante destino (§3.4, D8)
    recalcularEstadoDestino($conexion, $comp_destino_id, $usuario_id);

    return $imputacion_id;
}

/**
 * Anula una imputación y restaura los saldos pendientes de los comprobantes afectados.
 *
 * @param mysqli $conexion
 * @param int    $imputacion_id
 * @param int    $usuario_id
 * @param string $motivo
 * @return bool
 * @throws Exception
 */
function tesoreriaAnularImputacion($conexion, $imputacion_id, $usuario_id, $motivo = '')
{
    $imp_id = intval($imputacion_id);
    if ($imp_id <= 0) {
        throw new Exception("ID de imputación inválido.");
    }

    // Lock de la imputación
    $sql_imp = "SELECT * FROM gestion__comprobantes_imputaciones WHERE imputacion_id = ? FOR UPDATE";
    $stmt = mysqli_prepare($conexion, $sql_imp);
    mysqli_stmt_bind_param($stmt, "i", $imp_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $imp = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$imp) {
        throw new Exception("Imputación ID $imp_id no encontrada.");
    }
    if (intval($imp['tabla_estado_registro_id']) !== ESTADO_IMPUTACION_CONFIRMADO) {
        throw new Exception("La imputación no está vigente (estado actual: {$imp['tabla_estado_registro_id']}).");
    }

    $comp_origen_id   = intval($imp['comp_origen_id']);
    $comp_destino_id  = intval($imp['comp_destino_id']);
    $importe_imputado = floatval($imp['importe_imputado']);
    $importe_origen   = floatval($imp['importe_origen']);
    $empresa_id       = intval($imp['empresa_id']);

    // RT16: Lock ordenado de comprobantes
    $id_menor = min($comp_origen_id, $comp_destino_id);
    $id_mayor = max($comp_origen_id, $comp_destino_id);

    $sql_lock = "SELECT comprobante_id, importe_total, importe_pendiente, tabla_origen_id, registro_origen_id
                 FROM gestion__comprobantes
                 WHERE comprobante_id IN (?, ?) AND empresa_id = ?
                 ORDER BY comprobante_id ASC
                 FOR UPDATE";
    $stmt_l = mysqli_prepare($conexion, $sql_lock);
    mysqli_stmt_bind_param($stmt_l, "iii", $id_menor, $id_mayor, $empresa_id);
    mysqli_stmt_execute($stmt_l);
    $res_l = mysqli_stmt_get_result($stmt_l);
    $comprobantes = [];
    while ($r = mysqli_fetch_assoc($res_l)) {
        $comprobantes[intval($r['comprobante_id'])] = $r;
    }
    mysqli_stmt_close($stmt_l);

    // 1) Marcar imputación como ANULADA (2155)
    $estado_anulado = ESTADO_IMPUTACION_ANULADO;
    $sql_upd_imp = "UPDATE gestion__comprobantes_imputaciones
                    SET tabla_estado_registro_id = ?,
                        f_anulacion = CURDATE(),
                        motivo_anulacion = ?,
                        usuario_modificacion_id = ?,
                        fecha_modificacion = NOW()
                    WHERE imputacion_id = ?";
    $stmt_ui = mysqli_prepare($conexion, $sql_upd_imp);
    mysqli_stmt_bind_param($stmt_ui, "isii", $estado_anulado, $motivo, $usuario_id, $imp_id);
    mysqli_stmt_execute($stmt_ui);
    mysqli_stmt_close($stmt_ui);

    // 2) Restaurar importe_pendiente en el destino
    if (isset($comprobantes[$comp_destino_id])) {
        $dest = $comprobantes[$comp_destino_id];
        $nuevo_pend_dest = min(floatval($dest['importe_total']), round(floatval($dest['importe_pendiente']) + $importe_imputado, 2));
        $sql_ud = "UPDATE gestion__comprobantes SET importe_pendiente = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW() WHERE comprobante_id = ?";
        $stmt_ud = mysqli_prepare($conexion, $sql_ud);
        mysqli_stmt_bind_param($stmt_ud, "dii", $nuevo_pend_dest, $usuario_id, $comp_destino_id);
        mysqli_stmt_execute($stmt_ud);
        mysqli_stmt_close($stmt_ud);
    }

    // 3) Restaurar importe_pendiente en el origen si aplica
    if (isset($comprobantes[$comp_origen_id])) {
        $orig = $comprobantes[$comp_origen_id];
        $nuevo_pend_orig = min(floatval($orig['importe_total']), round(floatval($orig['importe_pendiente']) + $importe_origen, 2));
        $sql_uo = "UPDATE gestion__comprobantes SET importe_pendiente = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW() WHERE comprobante_id = ?";
        $stmt_uo = mysqli_prepare($conexion, $sql_uo);
        mysqli_stmt_bind_param($stmt_uo, "dii", $nuevo_pend_orig, $usuario_id, $comp_origen_id);
        mysqli_stmt_execute($stmt_uo);
        mysqli_stmt_close($stmt_uo);
    }

    // 4) Recalcular estado del destino
    recalcularEstadoDestino($conexion, $comp_destino_id, $usuario_id);

    return true;
}

/**
 * 4. RECÁLCULO DE ESTADOS DERIVADOS (D8 / §3.4)
 *
 * Es la ÚNICA función que asigna estados derivados de documentos destino al confirmarse
 * o anularse imputaciones:
 * - Liquidación Impositiva (I_LIQ): PENDIENTE (2161) o PAGADA (2162).
 * - Rendición de Gastos (RG_REND): APROBADA (2167) o REEMBOLSADA (2168).
 *
 * @param mysqli $conexion
 * @param int    $comprobante_id
 * @param int|null $usuario_id
 * @return array ['comprobante_id' => int, 'saldo' => float, 'estado_actualizado' => bool]
 */
function recalcularEstadoDestino($conexion, $comprobante_id, $usuario_id = null)
{
    $comp_id = intval($comprobante_id);
    if ($comp_id <= 0) return ['comprobante_id' => 0, 'saldo' => 0, 'estado_actualizado' => false];

    // Obtener datos del comprobante y su tabla de origen
    $sql = "SELECT c.comprobante_id, c.importe_total, c.importe_pendiente, c.tabla_origen_id, c.registro_origen_id,
                   t.tabla_nombre
            FROM gestion__comprobantes c
            LEFT JOIN conf__tablas t ON t.tabla_id = c.tabla_origen_id
            WHERE c.comprobante_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $comp_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $c = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$c) return ['comprobante_id' => $comp_id, 'saldo' => 0, 'estado_actualizado' => false];

    $saldo = floatval($c['importe_pendiente']);
    $tabla_nombre = trim($c['tabla_nombre'] ?? '');
    $registro_origen_id = intval($c['registro_origen_id']);
    $actualizado = false;

    // Caso 1: Liquidaciones Impositivas (gestion__obligaciones_impositivas)
    if ($tabla_nombre === 'gestion__obligaciones_impositivas' && $registro_origen_id > 0) {
        $nuevo_estado = ($saldo <= 0.001) ? ESTADO_ILIQ_PAGADO : ESTADO_ILIQ_PENDIENTE;
        $sql_upd = "UPDATE gestion__obligaciones_impositivas 
                    SET tabla_estado_registro_id = ?, fecha_modificacion = NOW(), usuario_modificacion_id = ?
                    WHERE obligacion_impositiva_id = ? AND tabla_estado_registro_id != ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        if ($stmt_u) {
            mysqli_stmt_bind_param($stmt_u, "iiii", $nuevo_estado, $usuario_id, $registro_origen_id, $nuevo_estado);
            mysqli_stmt_execute($stmt_u);
            $actualizado = (mysqli_stmt_affected_rows($stmt_u) > 0);
            mysqli_stmt_close($stmt_u);
        }
    }

    // Caso 2: Rendiciones de Gastos (gestion__rendiciones)
    if ($tabla_nombre === 'gestion__rendiciones' && $registro_origen_id > 0) {
        $nuevo_estado = ($saldo <= 0.001) ? ESTADO_RG_REND_REEMBOLSADA : ESTADO_RG_REND_APROBADA;
        $sql_upd = "UPDATE gestion__rendiciones 
                    SET tabla_estado_registro_id = ?, fecha_modificacion = NOW(), usuario_modificacion_id = ?
                    WHERE rendicion_id = ? AND tabla_estado_registro_id != ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        if ($stmt_u) {
            mysqli_stmt_bind_param($stmt_u, "iiii", $nuevo_estado, $usuario_id, $registro_origen_id, $nuevo_estado);
            mysqli_stmt_execute($stmt_u);
            $actualizado = (mysqli_stmt_affected_rows($stmt_u) > 0);
            mysqli_stmt_close($stmt_u);
        }
    }

    // Actualizar estado estándar en gestion__comprobantes según D8
    $total_comp = floatval($c['importe_total']);
    if ($saldo <= 0.001) {
        $estado_comp = ESTADO_CANCELADO_TOTAL; // 2123
    } elseif ($saldo < $total_comp - 0.001) {
        $estado_comp = ESTADO_CANCELADO_PARCIAL; // 2122
    } else {
        $estado_comp = ESTADO_CONFIRMADO; // 2126
    }
    $conexion->query("UPDATE gestion__comprobantes SET tabla_estado_registro_id = $estado_comp WHERE comprobante_id = $comp_id");

    return [
        'comprobante_id'     => $comp_id,
        'saldo'              => $saldo,
        'estado_actualizado' => $actualizado
    ];
}

/**
 * 5. GENERADOR DE ASIENTOS CONTABLES DE TESORERÍA (D27 / D36 / §4.10 / Anexo D)
 *
 * Crea o actualiza un asiento en gestion__cont_asientos y gestion__cont_asientos_detalles.
 *
 * @param mysqli $conexion
 * @param array  $cabecera   ['empresa_id', 'sucursal_id', 'comprobante_id', 'entidad_id', 'f_asiento', 'descripcion', 'usuario_id', 'moneda_id', 'tipo_cambio', 'cont_tipo_asiento_id']
 * @param array  $detalles   Array de arrays: [['cuenta_id', 'debe' => float, 'haber' => float, 'descripcion' => string, 'entidad_id' => int]]
 * @return int cont_asiento_id
 * @throws Exception
 */
function tesoreriaGenerarAsiento($conexion, $cabecera, $detalles)
{
    $empresa_id     = intval($cabecera['empresa_id'] ?? 0);
    $sucursal_id    = intval($cabecera['sucursal_id'] ?? 1);
    $comprobante_id = intval($cabecera['comprobante_id'] ?? 0);
    $entidad_id     = !empty($cabecera['entidad_id']) ? intval($cabecera['entidad_id']) : 0;
    $f_asiento      = !empty($cabecera['f_asiento']) ? $cabecera['f_asiento'] : date('Y-m-d');
    $descripcion    = trim($cabecera['descripcion'] ?? 'Asiento de tesorería');
    $usuario_id     = !empty($cabecera['usuario_id']) ? intval($cabecera['usuario_id']) : null;
    $moneda_id      = intval($cabecera['moneda_id'] ?? 1);
    $tipo_cambio    = floatval($cabecera['tipo_cambio'] ?? 1.0);
    $tipo_asiento   = intval($cabecera['cont_tipo_asiento_id'] ?? 2); // 2 = AUT (D27)

    if ($empresa_id <= 0 || $comprobante_id <= 0) {
        throw new Exception("Parámetros obligatorios faltantes para generar asiento de tesorería.");
    }
    if (empty($detalles)) {
        throw new Exception("El asiento no posee líneas de detalle contable.");
    }

    $total_debe  = 0.0;
    $total_haber = 0.0;
    $movimientos = [];

    foreach ($detalles as $linea) {
        $cuenta_id = intval($linea['cuenta_id'] ?? 0);
        if ($cuenta_id <= 0) {
            throw new Exception("Línea de asiento sin cuenta_id contable asignada.");
        }
        $debe  = round(floatval($linea['debe'] ?? 0), 2);
        $haber = round(floatval($linea['haber'] ?? 0), 2);

        if ($debe > 0 && $haber > 0) {
            throw new Exception("Una misma línea contable no puede tener Debe y Haber simultáneos.");
        }

        $total_debe  += $debe;
        $total_haber += $haber;

        $importe_local = ($debe > 0) ? $debe : -$haber;
        $movimientos[] = [
            'cuenta_id'     => $cuenta_id,
            'entidad_id'    => !empty($linea['entidad_id']) ? intval($linea['entidad_id']) : $entidad_id,
            'importe_local' => $importe_local,
            'descripcion'   => trim($linea['descripcion'] ?? $descripcion)
        ];
    }

    // Control de balance (diferencia no mayor a 1 centavo)
    if (abs($total_debe - $total_haber) > 0.01) {
        throw new Exception("Asiento no balanceado: Debe=" . number_format($total_debe, 2) . ", Haber=" . number_format($total_haber, 2) . ", diferencia=" . number_format($total_debe - $total_haber, 2));
    }

    $timestamp = strtotime($f_asiento);
    $anio = intval(date('Y', $timestamp));
    $mes  = intval(date('m', $timestamp));
    $deposito_id = 1;
    $estado_asiento = 1; // Activo

    // Verificar si ya existe asiento para este comprobante
    $sql_check = "SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id = ? LIMIT 1";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "i", $comprobante_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $row_c = mysqli_fetch_assoc($res_c);
    mysqli_stmt_close($stmt_c);

    $asiento_id = $row_c ? intval($row_c['cont_asiento_id']) : null;

    if ($asiento_id) {
        // UPDATE cabecera
        $sql_upd = "UPDATE gestion__cont_asientos 
                    SET sucursal_id = ?, entidad_id = ?, cont_tipo_asiento_id = ?,
                        f_asiento = ?, anio = ?, mes = ?, descripcion = ?,
                        moneda_id = ?, tipo_cambio = ?, usuario_modificacion_id = ?
                    WHERE cont_asiento_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param(
            $stmt_u, "iiisiisidii",
            $sucursal_id, $entidad_id, $tipo_asiento,
            $f_asiento, $anio, $mes, $descripcion,
            $moneda_id, $tipo_cambio, $usuario_id,
            $asiento_id
        );
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // Borrar renglones anteriores
        $sql_del = "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = ?";
        $stmt_d = mysqli_prepare($conexion, $sql_del);
        mysqli_stmt_bind_param($stmt_d, "i", $asiento_id);
        mysqli_stmt_execute($stmt_d);
        mysqli_stmt_close($stmt_d);
    } else {
        // INSERT cabecera
        $sql_ins = "INSERT INTO gestion__cont_asientos (
                        empresa_id, sucursal_id, deposito_id, comprobante_id, entidad_id,
                        cont_tipo_asiento_id, f_asiento, anio, mes, descripcion,
                        moneda_id, tipo_cambio, usuario_creacion_id, tabla_estado_registro_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_i = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param(
            $stmt_i, "iiiiisiisidiii",
            $empresa_id, $sucursal_id, $deposito_id, $comprobante_id, $entidad_id,
            $tipo_asiento, $f_asiento, $anio, $mes, $descripcion,
            $moneda_id, $tipo_cambio, $usuario_id, $estado_asiento
        );
        if (!mysqli_stmt_execute($stmt_i)) {
            throw new Exception("Error insertando cabecera de asiento: " . mysqli_stmt_error($stmt_i));
        }
        $asiento_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_i);
    }

    // Insertar líneas de detalle
    $sql_det = "INSERT INTO gestion__cont_asientos_detalles (
                    cont_asiento_id, cuenta_id, entidad_id, importe_local,
                    importe_ifrs, importe_impositivo, moneda_id, tipo_cambio,
                    descripcion, tipo, estado, comprobante_id
                ) VALUES (?, ?, ?, ?, 0.00, 0.00, ?, ?, ?, 'A', 'activo', ?)";
    $stmt_det = mysqli_prepare($conexion, $sql_det);
    if (!$stmt_det) {
        throw new Exception("Error preparando inserción de detalle de asiento: " . mysqli_error($conexion));
    }

    foreach ($movimientos as $m) {
        $ent_id = ($m['entidad_id'] > 0) ? $m['entidad_id'] : null;
        mysqli_stmt_bind_param(
            $stmt_det,
            "iiididsi",
            $asiento_id,
            $m['cuenta_id'],
            $ent_id,
            $m['importe_local'],
            $moneda_id,
            $tipo_cambio,
            $m['descripcion'],
            $comprobante_id
        );
        if (!mysqli_stmt_execute($stmt_det)) {
            throw new Exception("Error insertando renglón de asiento: " . mysqli_stmt_error($stmt_det));
        }
    }
    mysqli_stmt_close($stmt_det);

    return $asiento_id;
}

/**
 * 6. CONSULTA DE SALDO REAL / CONCILIACIÓN UNIVERSAL (§2.4 / §8.5)
 *
 * Devuelve el saldo real por la query SUM y compara contra la caché importe_pendiente.
 *
 * @param mysqli $conexion
 * @param int    $comprobante_id
 * @param int    $empresa_id
 * @return array
 */
function tesoreriaObtenerSaldoComprobante($conexion, $comprobante_id, $empresa_id)
{
    $comp_id    = intval($comprobante_id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT c.comprobante_id, c.comprobante_tipo_id, c.importe_total, c.importe_pendiente,
                   COALESCE(d.imputado, 0) AS imputado_como_destino,
                   COALESCE(o.imputado, 0) AS imputado_como_origen,
                   (c.importe_total - COALESCE(d.imputado, 0) - COALESCE(o.imputado, 0)) AS saldo_real
            FROM gestion__comprobantes c
            LEFT JOIN (
                SELECT ci.comp_destino_id AS comprobante_id, SUM(ci.importe_imputado) AS imputado
                FROM gestion__comprobantes_imputaciones ci
                JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = ci.tabla_estado_registro_id
                JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
                WHERE ci.empresa_id = ? AND ci.comp_destino_id = ? AND er.codigo_estandar = 'CONFIRMADO'
                GROUP BY ci.comp_destino_id
            ) d ON d.comprobante_id = c.comprobante_id
            LEFT JOIN (
                SELECT ci.comp_origen_id AS comprobante_id, SUM(ci.importe_origen) AS imputado
                FROM gestion__comprobantes_imputaciones ci
                JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = ci.tabla_estado_registro_id
                JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
                WHERE ci.empresa_id = ? AND ci.comp_origen_id = ? AND er.codigo_estandar = 'CONFIRMADO'
                GROUP BY ci.comp_origen_id
            ) o ON o.comprobante_id = c.comprobante_id
            WHERE c.comprobante_id = ? AND c.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiiiii", $empresa_id, $comp_id, $empresa_id, $comp_id, $comp_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$row) return null;

    $saldo_real = max(0.0, round(floatval($row['saldo_real']), 2));
    $cache_pend = round(floatval($row['importe_pendiente']), 2);

    return [
        'comprobante_id'         => $comp_id,
        'importe_total'          => floatval($row['importe_total']),
        'imputado_como_destino'  => floatval($row['imputado_como_destino']),
        'imputado_como_origen'   => floatval($row['imputado_como_origen']),
        'saldo_calculado'        => $saldo_real,
        'importe_pendiente_cache'=> $cache_pend,
        'diferencia'             => round($cache_pend - $saldo_real, 2)
    ];
}

/**
 * 7. COMPROBANTES PENDIENTES CANCELABLES POR UN TIPO (§8.1)
 *
 * Obtiene los comprobantes pendientes que pueden ser cancelados por un comprobante origen
 * según la matriz gestion__comprobantes_tipos_cancelaciones.
 *
 * @param mysqli $conexion
 * @param int    $empresa_id
 * @param string $tipo_origen_codigo Código del tipo origen (ej: 'RCX', 'OP', 'I_PAG', 'RG_ANT', 'V_NC')
 * @param int|null $entidad_id Entidad deudora/acreedora (opcional si es para un cliente/proveedor específico)
 * @return array
 */
function tesoreriaObtenerComprobantesCancelables($conexion, $empresa_id, $tipo_origen_codigo, $entidad_id = null)
{
    $empresa_id = intval($empresa_id);
    $tipo_origen_codigo = trim($tipo_origen_codigo);

    $sql = "SELECT c.comprobante_id, ct.codigo AS comprobante_tipo, ct.comprobante_tipo,
                   c.comprobante_pv, c.comprobante_nro, c.f_emision, c.f_vto,
                   c.moneda_id, m.codigo AS moneda_codigo, m.simbolo AS moneda_simbolo,
                   c.importe_total, (c.importe_pendiente * c.signo) AS importe_pendiente,
                   c.signo,
                   e.entidad_id, e.entidad_nombre
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
            JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
            JOIN gestion__comprobantes_tipos_cancelaciones tc 
                 ON tc.comprobante_tipo_destino_id = c.comprobante_tipo_id 
                AND tc.empresa_id = c.empresa_id
            JOIN gestion__comprobantes_tipos cto 
                 ON cto.comprobante_tipo_id = tc.comprobante_tipo_origen_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            LEFT JOIN conf__estados_registros er_directo ON er_directo.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ?
              AND cto.codigo = ?
              AND ABS(c.importe_pendiente * c.signo) > 0.001
              AND (er.codigo_estandar = 'CONFIRMADO' OR er_directo.codigo_estandar = 'CONFIRMADO') ";

    $params = [$empresa_id, $tipo_origen_codigo];
    $types = "is";

    if (!empty($entidad_id) && intval($entidad_id) > 0) {
        $sql .= " AND c.entidad_id = ? ";
        $params[] = intval($entidad_id);
        $types .= "i";
    }

    $sql .= " ORDER BY c.f_emision ASC, c.comprobante_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error en tesoreriaObtenerComprobantesCancelables: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}
