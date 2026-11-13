<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/formato.php';
/**
 * Liquidación de las retenciones practicadas y de la percepción de IVA practicada (etapas 3 y 7 de _local/docs/gestion/10_retenciones_percepciones_relevamiento.md,
 * sin exportación: el archivo para el organismo se define después; hoy se presenta a mano desde el registro de
 * Control Fiscal). Se usa en la solapa "Liquidación de retenciones" de Control Fiscal (página 125 / 625).
 *
 * Por empresa + impuesto (14 Ganancias, 15 IVA, 16 IIBB, 17 SUSS) + jurisdicción (IIBB) + período: junta las retenciones
 * confirmadas (gestion__pagos_retenciones en 5, por f_retencion) sin liquidar y genera la I_LIQ (obligación
 * impositiva, se paga desde Órdenes de Pago) por el total, con la cuenta de la config del agente como
 * pasivo: la retención ya está en esa cuenta desde la OP, así que la I_LIQ no lleva asiento y la orden de pago la
 * cancela contra el banco. Marca cada retención con comp_liquidacion_id (la OP ya no se puede anular).
 * La liquidación se anula mientras la I_LIQ no tenga pagos. Con el período cerrado (Control Fiscal) no se liquida
 * ni se anula. Puede haber más de una liquidación por período (las retenciones confirmadas después).
 * Percepción de IVA (22): igual, con las filas de gestion__comprobantes_impuestos de las ventas confirmadas del mes
 * (por f_emision, las NC restan) y su liquidacion_comprobante_id, como la liquidación de percepciones IIBB (página 116).
 */
require_once __DIR__ . '/retenciones_practicadas_core_model.php';
require_once __DIR__ . '/periodos_fiscales_core_model.php';
require_once __DIR__ . '/../../core/cont_periodos.php';
require_once __DIR__ . '/obligaciones_rendiciones_model.php';   // registrarObligacionImpositiva (hace $conexion = $conn: incluir a nivel archivo)

const RLIQ_IMPUESTOS = [14, 15, 16, 17, 22];
const RLIQ_PERC_IVA = 22;

/**
 * Filas pendientes (sin liquidar) o de una liquidación, de retenciones (pagos_retenciones) o de percepción de IVA
 * (comprobantes_impuestos de ventas), con un formato común: id, importe firmado y datos para el detalle.
 */
function rliqFilas($conexion, $e, $imp, $jur, $desde, $hasta, $liquidacion_id = 0, $bloquear = false)
{
    $lock = $bloquear ? ' FOR UPDATE' : '';
    if ($imp === RLIQ_PERC_IVA) {
        $estados = implode(',', PF_ESTADOS_VENTAS);
        $cond = $liquidacion_id > 0 ? 'ci.liquidacion_comprobante_id = ?' : 'ci.liquidacion_comprobante_id IS NULL AND v.f_emision BETWEEN ? AND ?';
        $tipos = $liquidacion_id > 0 ? 'ii' : 'iiss';
        $params = $liquidacion_id > 0 ? [$e, RLIQ_PERC_IVA, $liquidacion_id] : [$e, RLIQ_PERC_IVA, $desde, $hasta];
        if ($liquidacion_id > 0) $tipos .= 'i';
        return pfFilas($conexion,
            "SELECT ci.comprobante_impuesto_id AS id, v.f_emision AS fecha, NULL AS certificado, COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS tercero, en.cuit,
                    ci.situacion AS regimen, IF(ct.signo = '-', -1, 1) * ci.base_imponible AS base_imponible, ci.alicuota,
                    IF(ct.signo = '-', -1, 1) * ci.importe AS importe, CONCAT(ct.codigo, ' ', LPAD(v.comprobante_nro, 8, '0')) AS comprobante
             FROM gestion__comprobantes_impuestos ci
             JOIN gestion__ventas_facturas v ON v.comprobante_id = ci.comprobante_id AND v.empresa_id = ? AND v.tabla_estado_registro_id IN ($estados)
             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
             LEFT JOIN gestion__entidades en ON en.entidad_id = v.entidad_id
             WHERE ci.impuesto_tipo_id = ? AND ci.tabla_estado_registro_id = 1 AND $cond
             ORDER BY ci.comprobante_impuesto_id$lock", $tipos, ...$params);
    }
    $cond = $liquidacion_id > 0 ? 'pr.comp_liquidacion_id = ?' : 'pr.comp_liquidacion_id IS NULL AND pr.impuesto_tipo_id = ? AND COALESCE(pr.jurisdiccion_id, 0) = ? AND pr.f_retencion BETWEEN ? AND ?';
    $params = $liquidacion_id > 0 ? [$e, $liquidacion_id] : [$e, $imp, $jur, $desde, $hasta];
    $tipos = $liquidacion_id > 0 ? 'ii' : 'iiiss';
    return pfFilas($conexion,
        "SELECT pr.retencion_id AS id, pr.f_retencion AS fecha, LPAD(pr.certificado_nro, 8, '0') AS certificado, COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS tercero, en.cuit,
                pr.regimen, pr.base_imponible, pr.alicuota, pr.importe, CONCAT('OP ', LPAD(c.comprobante_nro, 8, '0')) AS comprobante
         FROM gestion__pagos_retenciones pr
         JOIN gestion__pagos p ON p.pago_id = pr.pago_id
         LEFT JOIN gestion__comprobantes c ON c.comprobante_id = p.comprobante_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = pr.entidad_id
         WHERE pr.empresa_id = ? AND pr.tabla_estado_registro_id = 5 AND $cond
         ORDER BY pr.retencion_id$lock", $tipos, ...$params);
}

/** Config vigente del agente para el impuesto (retenciones: retPracConfig; percepción de IVA: la de impuesto 22). */
function rliqConfig($conexion, $e, $imp, $jur, $fecha)
{
    if ($imp !== RLIQ_PERC_IVA) return retPracConfig($conexion, $e, $imp, $jur, $fecha);
    return pfFila($conexion, "SELECT * FROM gestion__empresas_impuestos_config
                              WHERE empresa_id = ? AND impuesto_tipo_id = ? AND tabla_estado_registro_id = 1
                                AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
                              ORDER BY prioridad, empresa_impuesto_config_id LIMIT 1", 'iiss', $e, RLIQ_PERC_IVA, $fecha, $fecha);
}

/** Impuesto + jurisdicción válidos para liquidar (jurisdicción solo en IIBB). */
function rliqClave($impuesto_tipo_id, $jurisdiccion_id)
{
    $imp = intval($impuesto_tipo_id);
    if (!in_array($imp, RLIQ_IMPUESTOS, true)) throw new Exception('Elija el impuesto de la retención.');
    $jur = ($imp === 16) ? intval($jurisdiccion_id) : 0;
    if (($imp === 16) && $jur <= 0) throw new Exception('Las retenciones de IIBB se liquidan por jurisdicción: elíjala.');
    return [$imp, $jur];
}

/**
 * Situación del período por impuesto / jurisdicción: retenciones pendientes de liquidar (cantidad e importe),
 * liquidaciones vigentes (I_LIQ, total, pagada) y si el período está cerrado. Incluye toda config de agente de
 * retención de la empresa vigente en algún día del período, aunque no tenga movimientos.
 */
function rliqResumenPeriodo($conexion, $empresa_id, $periodo)
{
    $e = intval($empresa_id);
    $desde = pfValidarPeriodo($periodo) . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    $in = implode(',', RLIQ_IMPUESTOS);
    $filas = [];
    foreach (pfFilas($conexion,
        "SELECT DISTINCT cfg.impuesto_tipo_id, IF(cfg.impuesto_tipo_id = 16, cfg.jurisdiccion_id, 0) AS jurisdiccion_id
         FROM gestion__empresas_impuestos_config cfg
         WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id IN ($in) AND cfg.tabla_estado_registro_id = 1
           AND cfg.f_desde <= ? AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)
         UNION
         SELECT DISTINCT impuesto_tipo_id, COALESCE(jurisdiccion_id, 0) FROM gestion__pagos_retenciones
         WHERE empresa_id = ? AND tabla_estado_registro_id = 5 AND f_retencion BETWEEN ? AND ?",
        'ississ', $e, $hasta, $desde, $e, $desde, $hasta) as $k) {
        if (intval($k['impuesto_tipo_id']) === RLIQ_PERC_IVA) $k['jurisdiccion_id'] = 0;
        $filas[intval($k['impuesto_tipo_id']) . '|' . intval($k['jurisdiccion_id'])] = [
            'impuesto_tipo_id' => intval($k['impuesto_tipo_id']), 'jurisdiccion_id' => intval($k['jurisdiccion_id']),
            'pendientes' => 0, 'importe_pendiente' => 0.0, 'liquidado' => 0.0, 'liquidaciones' => []];
    }
    foreach (pfFilas($conexion,
        "SELECT impuesto_tipo_id, COALESCE(jurisdiccion_id, 0) AS jurisdiccion_id, COUNT(*) AS n, SUM(importe) AS importe
         FROM gestion__pagos_retenciones
         WHERE empresa_id = ? AND tabla_estado_registro_id = 5 AND comp_liquidacion_id IS NULL AND f_retencion BETWEEN ? AND ?
         GROUP BY impuesto_tipo_id, COALESCE(jurisdiccion_id, 0)", 'iss', $e, $desde, $hasta) as $p) {
        $k = intval($p['impuesto_tipo_id']) . '|' . intval($p['jurisdiccion_id']);
        if (!isset($filas[$k])) continue;
        $filas[$k]['pendientes'] = intval($p['n']);
        $filas[$k]['importe_pendiente'] = pfR($p['importe']);
    }
    foreach (pfFilas($conexion,
        "SELECT pr.impuesto_tipo_id, COALESCE(pr.jurisdiccion_id, 0) AS jurisdiccion_id, pr.comp_liquidacion_id,
                COUNT(*) AS retenciones, SUM(pr.importe) AS importe,
                c.comprobante_pv, c.comprobante_nro, c.importe_total, c.importe_pendiente, c.f_emision,
                o.f_vto, o.nro_ddjj, en.entidad_nombre AS organismo
         FROM gestion__pagos_retenciones pr
         JOIN gestion__comprobantes c ON c.comprobante_id = pr.comp_liquidacion_id AND c.tabla_estado_registro_id <> 38
         LEFT JOIN gestion__obligaciones_pago o ON o.comprobante_id = c.comprobante_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = c.entidad_id
         WHERE pr.empresa_id = ? AND pr.tabla_estado_registro_id = 5 AND pr.f_retencion BETWEEN ? AND ?
         GROUP BY pr.impuesto_tipo_id, COALESCE(pr.jurisdiccion_id, 0), pr.comp_liquidacion_id,
                  c.comprobante_pv, c.comprobante_nro, c.importe_total, c.importe_pendiente, c.f_emision, o.f_vto, o.nro_ddjj, en.entidad_nombre
         ORDER BY pr.comp_liquidacion_id", 'iss', $e, $desde, $hasta) as $l) {
        $k = intval($l['impuesto_tipo_id']) . '|' . intval($l['jurisdiccion_id']);
        if (!isset($filas[$k])) continue;
        $filas[$k]['liquidado'] = pfR($filas[$k]['liquidado'] + floatval($l['importe']));
        $filas[$k]['liquidaciones'][] = [
            'comprobante_id' => intval($l['comp_liquidacion_id']),
            'numero' => emp_numero_comprobante($conexion, $e, intval($l['comprobante_pv']), intval($l['comprobante_nro'])),
            'f_emision' => $l['f_emision'], 'f_vto' => $l['f_vto'], 'nro_ddjj' => $l['nro_ddjj'], 'organismo' => $l['organismo'],
            'retenciones' => intval($l['retenciones']), 'total' => pfR($l['importe_total']),
            'pagada' => abs(floatval($l['importe_pendiente']) - floatval($l['importe_total'])) >= 0.01];
    }
    // Percepción de IVA: pendientes y liquidaciones de las ventas del mes
    if (isset($filas[RLIQ_PERC_IVA . '|0'])) {
        $pend = rliqFilas($conexion, $e, RLIQ_PERC_IVA, 0, $desde, $hasta);
        $filas[RLIQ_PERC_IVA . '|0']['pendientes'] = count($pend);
        $filas[RLIQ_PERC_IVA . '|0']['importe_pendiente'] = pfR(array_sum(array_column($pend, 'importe')));
        $estados = implode(',', PF_ESTADOS_VENTAS);
        foreach (pfFilas($conexion,
            "SELECT ci.liquidacion_comprobante_id, COUNT(*) AS retenciones, c.comprobante_pv, c.comprobante_nro, c.importe_total, c.importe_pendiente,
                    c.f_emision, o.f_vto, o.nro_ddjj, en.entidad_nombre AS organismo
             FROM gestion__comprobantes_impuestos ci
             JOIN gestion__ventas_facturas v ON v.comprobante_id = ci.comprobante_id AND v.empresa_id = ? AND v.tabla_estado_registro_id IN ($estados)
             JOIN gestion__comprobantes c ON c.comprobante_id = ci.liquidacion_comprobante_id AND c.tabla_estado_registro_id <> 38
             LEFT JOIN gestion__obligaciones_pago o ON o.comprobante_id = c.comprobante_id
             LEFT JOIN gestion__entidades en ON en.entidad_id = c.entidad_id
             WHERE ci.impuesto_tipo_id = ? AND ci.tabla_estado_registro_id = 1 AND v.f_emision BETWEEN ? AND ?
             GROUP BY ci.liquidacion_comprobante_id, c.comprobante_pv, c.comprobante_nro, c.importe_total, c.importe_pendiente, c.f_emision,
                      o.f_vto, o.nro_ddjj, en.entidad_nombre", 'iiss', $e, RLIQ_PERC_IVA, $desde, $hasta) as $l) {
            $filas[RLIQ_PERC_IVA . '|0']['liquidado'] = pfR($filas[RLIQ_PERC_IVA . '|0']['liquidado'] + floatval($l['importe_total']));
            $filas[RLIQ_PERC_IVA . '|0']['liquidaciones'][] = [
                'comprobante_id' => intval($l['liquidacion_comprobante_id']),
                'numero' => emp_numero_comprobante($conexion, $e, intval($l['comprobante_pv']), intval($l['comprobante_nro'])),
                'f_emision' => $l['f_emision'], 'f_vto' => $l['f_vto'], 'nro_ddjj' => $l['nro_ddjj'], 'organismo' => $l['organismo'],
                'retenciones' => intval($l['retenciones']), 'total' => pfR($l['importe_total']),
                'pagada' => abs(floatval($l['importe_pendiente']) - floatval($l['importe_total'])) >= 0.01];
        }
    }

    // Nombres, cierre y cuenta de la config
    $nom_imp = [];
    foreach (pfFilas($conexion, "SELECT impuesto_tipo_id, impuesto_tipo FROM gestion__impuestos_tipos WHERE impuesto_tipo_id IN ($in)") as $f) {
        $nom_imp[intval($f['impuesto_tipo_id'])] = $f['impuesto_tipo'];
    }
    foreach ($filas as &$f) {
        $f['impuesto'] = $nom_imp[$f['impuesto_tipo_id']] ?? ('Impuesto ' . $f['impuesto_tipo_id']);
        $f['jurisdiccion'] = $f['jurisdiccion_id'] > 0
            ? (pfFila($conexion, "SELECT jurisdiccion_nombre FROM gestion__jurisdicciones WHERE jurisdiccion_id = ?", 'i', $f['jurisdiccion_id'])['jurisdiccion_nombre'] ?? null)
            : null;
        $f['cerrado'] = (bool) pfCierresQueBloquean($conexion, $e, $desde, [[$f['impuesto_tipo_id'], $f['jurisdiccion_id']]]);
        $cfg = rliqConfig($conexion, $e, $f['impuesto_tipo_id'], $f['jurisdiccion_id'], $hasta);
        $f['tiene_cuenta'] = $cfg && intval($cfg['cont_cuenta_id']) > 0;
    }
    unset($f);
    $filas = array_values($filas);
    usort($filas, function ($a, $b) { return [$a['impuesto_tipo_id'], (string)$a['jurisdiccion']] <=> [$b['impuesto_tipo_id'], (string)$b['jurisdiccion']]; });
    return ['periodo' => $periodo, 'filas' => $filas];
}

/** Retenciones / percepciones de una liquidación o pendientes (para el detalle). */
function rliqDetalle($conexion, $empresa_id, $data)
{
    $e = intval($empresa_id);
    $liq = intval($data['comprobante_id'] ?? 0);
    if ($liq > 0) {
        $o = pfFila($conexion, "SELECT impuesto_tipo_id FROM gestion__obligaciones_pago WHERE comprobante_id = ? AND empresa_id = ?", 'ii', $liq, $e);
        if (!$o) throw new Exception('Liquidación no encontrada.');
        return rliqFilas($conexion, $e, intval($o['impuesto_tipo_id']), 0, null, null, $liq);
    }
    [$imp, $jur] = rliqClave($data['impuesto_tipo_id'] ?? 0, $data['jurisdiccion_id'] ?? 0);
    $desde = pfValidarPeriodo($data['periodo'] ?? '') . '-01';
    return rliqFilas($conexion, $e, $imp, $jur, $desde, date('Y-m-t', strtotime($desde)));
}

/** Genera la I_LIQ por las retenciones pendientes del período y las marca, en una transacción. */
function rliqLiquidar($conexion, $empresa_id, $usuario_id, $data)
{
    $e = intval($empresa_id);
    [$imp, $jur] = rliqClave($data['impuesto_tipo_id'] ?? 0, $data['jurisdiccion_id'] ?? 0);
    $periodo = pfValidarPeriodo($data['periodo'] ?? '');
    $desde = $periodo . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    if ($periodo > date('Y-m')) throw new Exception('No se puede liquidar un período futuro.');
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    error_log("=== INICIO rliqLiquidar empresa $e impuesto $imp jur $jur período $periodo ===");

    if ($entidad_id <= 0 || !pfFila($conexion, "SELECT 1 FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', $entidad_id, $e)) {
        throw new Exception('Elija el organismo recaudador (entidad proveedora de la empresa).');
    }
    if ($sucursal_id <= 0 || !pfFila($conexion, "SELECT 1 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", 'ii', $sucursal_id, $e)) {
        throw new Exception('Elija la sucursal de la liquidación.');
    }
    $msg = pfMensajeBloqueo(pfCierresQueBloquean($conexion, $e, $desde, [[$imp, $jur]]), 'liquidar');
    if ($msg !== null) throw new Exception($msg);
    $cfg = rliqConfig($conexion, $e, $imp, $jur, $hasta);
    $cuenta = intval($cfg['cont_cuenta_id'] ?? 0);
    if ($cuenta <= 0) throw new Exception('La configuración del agente no tiene cuenta contable (Impuestos Adic. - Conf).');

    mysqli_begin_transaction($conexion);
    try {
        $rets = rliqFilas($conexion, $e, $imp, $jur, $desde, $hasta, 0, true);
        if (!$rets) throw new Exception('No hay ' . ($imp === RLIQ_PERC_IVA ? 'percepciones' : 'retenciones') . ' pendientes de liquidar en el período.');
        $total = pfR(array_sum(array_column($rets, 'importe')));
        if ($total <= 0) throw new Exception('El total a liquidar no es positivo: revise las notas de crédito.');
        $nombre = trim((pfFila($conexion, "SELECT impuesto_tipo FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = ?", 'i', $imp)['impuesto_tipo'] ?? '') . ($jur > 0 ? ' ' . (pfFila($conexion, "SELECT jurisdiccion_nombre FROM gestion__jurisdicciones WHERE jurisdiccion_id = ?", 'i', $jur)['jurisdiccion_nombre'] ?? '') : ''));

        $res = registrarObligacionImpositiva($conexion, [
            'en_transaccion' => true,
            'sin_asiento' => true,   // la retención ya está en el pasivo desde la OP; la OP la cancela
            'entidad_id' => $entidad_id,
            'sucursal_id' => $sucursal_id,
            'f_emision' => date('Y-m-d'),
            'f_vto' => trim((string)($data['f_vto'] ?? '')) ?: date('Y-m-d', strtotime('+10 days')),
            'periodo_fiscal' => $periodo,
            'descripcion' => mb_substr($nombre . ' ' . $periodo, 0, 200),
            'nro_ddjj' => trim((string)($data['nro_ddjj'] ?? '')),
            'importe_total' => $total,
            'cont_cuenta_id' => $cuenta,
            'impuesto_tipo_id' => $imp,
        ], $e, $usuario_id);
        $liq_id = intval($res['comprobante_id']);
        if ($jur > 0) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__obligaciones_pago SET jurisdiccion_id = ? WHERE comprobante_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'iii', $jur, $liq_id, $e);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $ids = array_map(function ($r) { return intval($r['id']); }, $rets);
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($imp === RLIQ_PERC_IVA) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_impuestos SET liquidacion_comprobante_id = ?
                                               WHERE comprobante_impuesto_id IN ($in) AND liquidacion_comprobante_id IS NULL");
            mysqli_stmt_bind_param($stmt, 'i' . str_repeat('i', count($ids)), $liq_id, ...$ids);
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__pagos_retenciones SET comp_liquidacion_id = ?, usuario_modificacion_id = ?
                                               WHERE retencion_id IN ($in) AND comp_liquidacion_id IS NULL");
            $uid = intval($usuario_id);
            mysqli_stmt_bind_param($stmt, 'ii' . str_repeat('i', count($ids)), $liq_id, $uid, ...$ids);
        }
        mysqli_stmt_execute($stmt);
        $marcadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($marcadas !== count($ids)) throw new Exception('No se pudieron marcar todas las filas como liquidadas.');

        mysqli_commit($conexion);
        return ['comprobante_id' => $liq_id, 'numero' => $res['numero'], 'total' => $total, 'registros' => count($ids),
                'message' => 'Liquidación ' . $res['numero'] . ' por $' . fmt_numero($total, 2) . ' (' . count($ids)
                             . ($imp === RLIQ_PERC_IVA ? ' percepciones' : ' retenciones') . '). Se paga desde Órdenes de Pago.'];
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        error_log('ERROR rliqLiquidar: ' . $ex->getMessage());
        throw $ex;
    }
}

/** Anula una liquidación sin pagos: I_LIQ y obligación anuladas, retenciones otra vez pendientes. */
function rliqAnular($conexion, $empresa_id, $usuario_id, $data)
{
    $e = intval($empresa_id);
    $liq = intval($data['comprobante_id'] ?? 0);
    $motivo = mb_substr(trim((string)($data['motivo'] ?? '')), 0, 255);
    $uid = intval($usuario_id);
    if ($motivo === '') throw new Exception('Ingrese el motivo de la anulación.');

    mysqli_begin_transaction($conexion);
    try {
        $c = pfFila($conexion, "SELECT c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id, o.periodo_fiscal, o.impuesto_tipo_id,
                                       COALESCE(o.jurisdiccion_id, 0) AS jurisdiccion_id
                                FROM gestion__comprobantes c
                                JOIN gestion__obligaciones_pago o ON o.comprobante_id = c.comprobante_id
                                WHERE c.comprobante_id = ? AND c.empresa_id = ? FOR UPDATE", 'ii', $liq, $e);
        $imp = $c ? intval($c['impuesto_tipo_id']) : 0;
        if (!$c || !in_array($imp, RLIQ_IMPUESTOS, true)) throw new Exception('Liquidación de retenciones / percepción de IVA no encontrada.');
        $rets = rliqFilas($conexion, $e, $imp, 0, null, null, $liq, true);
        if (!$rets) throw new Exception('Liquidación de retenciones / percepción de IVA no encontrada.');
        if (intval($c['tabla_estado_registro_id']) === 38) throw new Exception('La liquidación ya está anulada.');
        $pagos = pfFila($conexion, "SELECT COUNT(*) AS n FROM gestion__comprobantes_imputaciones WHERE comp_destino_id = ? AND tabla_estado_registro_id = 5", 'i', $liq);
        if (abs(floatval($c['importe_pendiente']) - floatval($c['importe_total'])) >= 0.01 || intval($pagos['n']) > 0) {
            throw new Exception('La liquidación ya tiene pagos: anule antes la orden de pago que la cancela.');
        }
        $msg = pfMensajeBloqueo(pfCierresQueBloquean($conexion, $e, $c['periodo_fiscal'] . '-01',
                                                     [[$imp, $imp === 16 ? intval($c['jurisdiccion_id']) : 0]]), 'anular la liquidación');
        if ($msg !== null) throw new Exception($msg);
        cont_periodo_exigir_comprobante($conexion, $e, $liq, 'anular la liquidación');

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET tabla_estado_registro_id = 38, importe_pendiente = 0 WHERE comprobante_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $liq);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__obligaciones_pago SET tabla_estado_registro_id = 38, motivo_anulacion = ?,
                                                  usuario_modificacion_id = ? WHERE comprobante_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'siii', $motivo, $uid, $liq, $e);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                                           WHERE comprobante_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $uid, $liq, $e);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($imp === RLIQ_PERC_IVA) {
            $ids = array_map(function ($r) { return intval($r['id']); }, $rets);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_impuestos SET liquidacion_comprobante_id = NULL
                                               WHERE comprobante_impuesto_id IN ($in) AND liquidacion_comprobante_id = ?");
            mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)) . 'i', ...array_merge($ids, [$liq]));
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__pagos_retenciones SET comp_liquidacion_id = NULL, usuario_modificacion_id = ?
                                               WHERE comp_liquidacion_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'iii', $uid, $liq, $e);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== rliqAnular liquidación $liq empresa $e: $motivo ===");
        return ['message' => 'Liquidación anulada: ' . count($rets) . ($imp === RLIQ_PERC_IVA ? ' percepciones' : ' retenciones') . ' quedan pendientes.'];
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        error_log('ERROR rliqAnular: ' . $ex->getMessage());
        throw $ex;
    }
}

/** Organismos recaudadores posibles (entidades proveedoras de la empresa) y sucursales, para el modal. */
function rliqMaestros($conexion, $empresa_id)
{
    $e = intval($empresa_id);
    return [
        'organismos' => pfFilas($conexion, "SELECT entidad_id, entidad_nombre FROM gestion__entidades
                                            WHERE empresa_id = ? AND " . ent_sql_tiene_rol('gestion__entidades.entidad_id', 'PRO') . " AND tabla_estado_registro_id = 1
                                            ORDER BY (entidad_nombre LIKE '%ARCA%' OR entidad_nombre LIKE '%AFIP%' OR entidad_nombre LIKE '%ARBA%'
                                                      OR entidad_nombre LIKE '%Rentas%' OR entidad_nombre LIKE '%DGR%') DESC, entidad_nombre", 'i', $e),
        'sucursales' => pfFilas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales
                                            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id", 'i', $e),
    ];
}
