<?php
/**
 * Control Fiscal (página 125 / 625 en gestion_normal, archivos cierres_fiscales*): registro de los cuatro circuitos de
 * retenciones y percepciones, períodos fiscales cerrados, cuadre contable e inscripciones IIBB de la empresa.
 * Los cierres y el cuadre están en periodos_fiscales_core_model.php (lo usan también ventas, compras, cobranzas,
 * pagos y la liquidación de IVA). Scripts: _local/sql/mantenimiento/20261016_cierres_fiscales_01_estructura.sql y _02_registro_inscripciones.sql.
 */
require_once __DIR__ . '/periodos_fiscales_core_model.php';

/** Jurisdicciones activas para los combos: las provincias (tipo 2). */
function cfJurisdicciones($conexion)
{
    return pfFilas($conexion, "SELECT jurisdiccion_id, jurisdiccion_nombre FROM gestion__jurisdicciones
                               WHERE tabla_estado_registro_id = 1 AND jurisdiccion_tipo_id = 2 ORDER BY jurisdiccion_nombre");
}

const CF_CIRCUITOS = [
    'PERC_PRACTICADA' => 'Percepciones practicadas (ventas)',
    'PERC_SUFRIDA'    => 'Percepciones sufridas (compras)',
    'RET_PRACTICADA'  => 'Retenciones practicadas (órdenes de pago)',
    'RET_SUFRIDA'     => 'Retenciones sufridas (cobranzas)',
];

/**
 * Registro común de los cuatro circuitos en un rango de períodos: una fila por percepción / retención, con importe
 * firmado (las notas de crédito restan), base, alícuota, certificado y si ya está declarada. Filtros: circuito,
 * impuesto, jurisdicción y tercero (nombre o CUIT). Devuelve filas, resumen por circuito / impuesto / jurisdicción
 * y resumen por tercero.
 */
function cfRegistro($conexion, $empresa_id, $filtros)
{
    $e = intval($empresa_id);
    $desde = pfValidarPeriodo($filtros['desde'] ?? '') . '-01';
    $hasta = date('Y-m-t', strtotime(pfValidarPeriodo($filtros['hasta'] ?? '') . '-01'));
    if ($desde > $hasta) throw new Exception('El período desde es posterior al hasta.');
    $estados_v = implode(',', PF_ESTADOS_VENTAS);
    $pv = emp_sql_pv_mostrar('v.punto_venta_id', 'v.empresa_id');
    $comunes = "it.impuesto_tipo AS impuesto, j.jurisdiccion_nombre AS jurisdiccion";
    $filas = [];

    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'PERC_PRACTICADA' AS circuito, v.f_emision AS fecha,
                CONCAT(ct.codigo, ' ', LPAD($pv, 4, '0'), '-', LPAD(v.comprobante_nro, 8, '0')) AS comprobante,
                v.entidad_id, COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre, v.consumidor_final_nombre) AS entidad, en.cuit,
                ci.impuesto_tipo_id, COALESCE(ci.jurisdiccion_id, 0) AS jurisdiccion_id, $comunes,
                IF(ct.signo = '-', -1, 1) * ci.base_imponible AS base, ci.alicuota, IF(ct.signo = '-', -1, 1) * ci.importe AS importe,
                NULL AS certificado, IF(ci.liquidacion_comprobante_id IS NULL, 0, 1) AS declarada
         FROM gestion__ventas_facturas v
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
         JOIN gestion__comprobantes_impuestos ci ON ci.comprobante_id = v.comprobante_id AND ci.tabla_estado_registro_id = 1
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = ci.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = ci.jurisdiccion_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = v.entidad_id
         WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados_v)",
        'iss', $e, $desde, $hasta));

    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'PERC_SUFRIDA' AS circuito, COALESCE(f.f_contabilidad, f.f_emision) AS fecha,
                CONCAT(ct.codigo, ' ', LPAD(f.comprobante_pv, 4, '0'), '-', LPAD(f.comprobante_nro, 8, '0')) AS comprobante,
                f.entidad_id, COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS entidad, en.cuit,
                COALESCE(ci.impuesto_tipo_id, cfg.impuesto_tipo_id) AS impuesto_tipo_id,
                COALESCE(ci.jurisdiccion_id, cfg.jurisdiccion_id, 0) AS jurisdiccion_id, $comunes,
                IF(ct.signo = '-', -1, 1) * ci.base_imponible AS base, ci.alicuota, IF(ct.signo = '-', -1, 1) * ci.importe AS importe,
                NULL AS certificado, 0 AS declarada
         FROM gestion__facturas_proveedores f
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
         JOIN gestion__comprobantes_impuestos ci ON ci.comprobante_id = f.comprobante_id AND ci.tabla_estado_registro_id = 1
         LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = COALESCE(ci.impuesto_tipo_id, cfg.impuesto_tipo_id)
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = COALESCE(ci.jurisdiccion_id, cfg.jurisdiccion_id)
         LEFT JOIN gestion__entidades en ON en.entidad_id = f.entidad_id
         WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5",
        'iss', $e, $desde, $hasta));

    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'RET_PRACTICADA' AS circuito, p.f_emision AS fecha, CONCAT('OP ', LPAD(c.comprobante_nro, 8, '0')) AS comprobante,
                pr.entidad_id, COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS entidad, en.cuit, pr.impuesto_tipo_id, COALESCE(pr.jurisdiccion_id, 0) AS jurisdiccion_id,
                $comunes, pr.base_imponible AS base, pr.alicuota, pr.importe,
                IF(pr.certificado_nro IS NULL, NULL, LPAD(pr.certificado_nro, 8, '0')) AS certificado,
                IF(pr.comp_liquidacion_id IS NULL, 0, 1) AS declarada
         FROM gestion__pagos_retenciones pr
         JOIN gestion__pagos p ON p.pago_id = pr.pago_id AND p.empresa_id = pr.empresa_id
         LEFT JOIN gestion__comprobantes c ON c.comprobante_id = p.comprobante_id
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = pr.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = pr.jurisdiccion_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = pr.entidad_id
         WHERE pr.empresa_id = ? AND p.f_emision BETWEEN ? AND ? AND pr.tabla_estado_registro_id = 5",
        'iss', $e, $desde, $hasta));

    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'RET_SUFRIDA' AS circuito, COALESCE(co.f_contabilidad, co.f_emision) AS fecha,
                CONCAT('RCX ', LPAD(c.comprobante_nro, 8, '0')) AS comprobante, co.entidad_id, COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS entidad, en.cuit,
                COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id) AS impuesto_tipo_id, COALESCE(cm.jurisdiccion_id, 0) AS jurisdiccion_id,
                $comunes, cm.retencion_base_imponible AS base, cm.retencion_alicuota AS alicuota, cm.importe_comprobante AS importe,
                cm.retencion_certificado_nro AS certificado, 0 AS declarada,
                cm.cobranza_medio_id, COALESCE(cm.retencion_certificado_estado, IF(COALESCE(cm.retencion_certificado_nro, '') = '', 'PENDIENTE', 'RECIBIDO')) AS certificado_estado,
                cm.retencion_f_certificado AS f_certificado, cm.retencion_regimen AS regimen,
                COALESCE(cm.retencion_periodo_computo, DATE_FORMAT(COALESCE(co.f_contabilidad, co.f_emision), '%Y-%m')) AS periodo_computo,
                IF(cm.retencion_adjunto IS NULL, 0, 1) AS tiene_adjunto
         FROM gestion__cobranzas co
         JOIN gestion__cobranzas_medios cm ON cm.cobranza_id = co.cobranza_id
         JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id AND mp.medio_pago_tipo = 'RETENCION'
         LEFT JOIN gestion__comprobantes c ON c.comprobante_id = co.comprobante_id
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id)
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cm.jurisdiccion_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = co.entidad_id
         WHERE co.empresa_id = ? AND co.tabla_estado_registro_id = 5
           AND COALESCE(cm.retencion_periodo_computo, DATE_FORMAT(COALESCE(co.f_contabilidad, co.f_emision), '%Y-%m')) BETWEEN ? AND ?",
        'iss', $e, substr($desde, 0, 7), substr($hasta, 0, 7)));

    // Filtros (retenciones sufridas por período de cómputo; estado del certificado solo en ese circuito)
    $circuito = (string)($filtros['circuito'] ?? '');
    $imp = intval($filtros['impuesto_tipo_id'] ?? 0);
    $jur = intval($filtros['jurisdiccion_id'] ?? 0);
    $tercero = mb_strtolower(trim((string)($filtros['tercero'] ?? '')));
    $tercero_cuit = preg_replace('/\D/', '', $tercero);
    $estado_cert = (string)($filtros['certificado_estado'] ?? '');
    $filas = array_values(array_filter($filas, function ($f) use ($circuito, $imp, $jur, $tercero, $tercero_cuit, $estado_cert) {
        if ($circuito !== '' && $f['circuito'] !== $circuito) return false;
        if ($estado_cert !== '' && ($f['certificado_estado'] ?? null) !== $estado_cert) return false;
        if ($imp > 0 && intval($f['impuesto_tipo_id']) !== $imp) return false;
        if ($jur > 0 && intval($f['jurisdiccion_id']) !== $jur) return false;
        if ($tercero !== '') {
            $en_nombre = mb_strpos(mb_strtolower((string)$f['entidad']), $tercero) !== false;
            $en_cuit = $tercero_cuit !== '' && strpos(preg_replace('/\D/', '', (string)$f['cuit']), $tercero_cuit) !== false;
            if (!$en_nombre && !$en_cuit) return false;
        }
        return true;
    }));
    usort($filas, function ($a, $b) { return [$a['circuito'], $a['fecha'], $a['comprobante']] <=> [$b['circuito'], $b['fecha'], $b['comprobante']]; });

    $resumen = [];
    $terceros = [];
    foreach ($filas as &$f) {
        $f['importe'] = pfR($f['importe']);
        $f['base'] = $f['base'] !== null ? pfR($f['base']) : null;
        $f['circuito_nombre'] = CF_CIRCUITOS[$f['circuito']];
        $f['periodo'] = $f['periodo_computo'] ?? substr($f['fecha'], 0, 7);
        $k = $f['circuito'] . '|' . $f['impuesto_tipo_id'] . '|' . $f['jurisdiccion_id'];
        if (!isset($resumen[$k])) {
            $resumen[$k] = ['circuito' => $f['circuito'], 'circuito_nombre' => $f['circuito_nombre'], 'impuesto' => $f['impuesto'],
                            'jurisdiccion' => intval($f['jurisdiccion_id']) > 0 ? $f['jurisdiccion'] : null,
                            'registros' => 0, 'base' => 0.0, 'importe' => 0.0, 'declarado' => 0.0];
        }
        $resumen[$k]['registros']++;
        $resumen[$k]['base'] += floatval($f['base']);
        $resumen[$k]['importe'] += $f['importe'];
        if (intval($f['declarada'])) $resumen[$k]['declarado'] += $f['importe'];
        $tk = $f['circuito'] . '|' . intval($f['entidad_id']);
        if (!isset($terceros[$tk])) {
            $terceros[$tk] = ['circuito_nombre' => $f['circuito_nombre'], 'entidad' => $f['entidad'], 'cuit' => $f['cuit'], 'registros' => 0, 'importe' => 0.0];
        }
        $terceros[$tk]['registros']++;
        $terceros[$tk]['importe'] += $f['importe'];
    }
    unset($f);
    foreach ($resumen as &$r) { $r['base'] = pfR($r['base']); $r['importe'] = pfR($r['importe']); $r['declarado'] = pfR($r['declarado']); }
    unset($r);
    foreach ($terceros as &$t) $t['importe'] = pfR($t['importe']);
    unset($t);
    $terceros = array_values($terceros);
    usort($terceros, function ($a, $b) { return [$a['circuito_nombre'], -abs($a['importe'])] <=> [$b['circuito_nombre'], -abs($b['importe'])]; });
    return ['desde' => $desde, 'hasta' => $hasta, 'filas' => $filas, 'resumen' => array_values($resumen), 'terceros' => $terceros];
}

// ------------------------------------------------------------------ inscripciones IIBB de la empresa
function cfRegimenesIibb($conexion)
{
    return pfFilas($conexion, "SELECT iibb_regimen_id, iibb_regimen_codigo, iibb_regimen_nombre FROM gestion__iibb_regimenes
                               WHERE tabla_estado_registro_id = 1 ORDER BY orden");
}

function cfInscripciones($conexion, $empresa_id, $incluir_inactivas = false)
{
    $r = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__empresas_iibb_actividades'");
    $actividades = $r && mysqli_num_rows($r) > 0
        ? "(SELECT COUNT(*) FROM gestion__empresas_iibb_actividades a WHERE a.inscripcion_id = i.inscripcion_id AND a.tabla_estado_registro_id = 1)"
        : 'NULL';
    return pfFilas($conexion,
        "SELECT i.*, j.jurisdiccion_nombre, r.iibb_regimen_codigo, r.iibb_regimen_nombre, $actividades AS actividades
         FROM gestion__empresas_iibb_inscripciones i
         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = i.jurisdiccion_id
         LEFT JOIN gestion__iibb_regimenes r ON r.iibb_regimen_id = i.iibb_regimen_id
         WHERE i.empresa_id = ?" . ($incluir_inactivas ? '' : ' AND i.tabla_estado_registro_id = 1') . "
         ORDER BY i.tabla_estado_registro_id, i.es_sede DESC, j.jurisdiccion_nombre, i.f_desde DESC", 'i', intval($empresa_id));
}

/** Alta de una inscripción: provincia, régimen, vigencia sin superponerse con otra de la misma jurisdicción, una sola sede. */
function cfAgregarInscripcion($conexion, $empresa_id, $usuario_id, $data)
{
    $e = intval($empresa_id);
    $jur = intval($data['jurisdiccion_id'] ?? 0);
    $reg = intval($data['iibb_regimen_id'] ?? 0);
    $nro = mb_substr(trim((string)($data['nro_inscripcion'] ?? '')), 0, 30);
    $sede = !empty($data['es_sede']) && $data['es_sede'] !== '0' ? 1 : 0;
    $coef_txt = trim(str_replace(',', '.', (string)($data['coeficiente_cm'] ?? '')));
    $desde = trim((string)($data['f_desde'] ?? ''));
    $hasta = trim((string)($data['f_hasta'] ?? '')) ?: null;
    $obs = mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 255);

    if (!pfFila($conexion, "SELECT 1 FROM gestion__jurisdicciones WHERE jurisdiccion_id = ? AND jurisdiccion_tipo_id = 2 AND tabla_estado_registro_id = 1", 'i', $jur)) {
        throw new Exception('Elija la provincia.');
    }
    $regimen = pfFila($conexion, "SELECT iibb_regimen_codigo FROM gestion__iibb_regimenes WHERE iibb_regimen_id = ?", 'i', $reg);
    if (!$regimen) throw new Exception('Elija el régimen (local, convenio multilateral o exento).');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) throw new Exception('Indique la fecha desde.');
    if ($hasta !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) || $hasta < $desde)) throw new Exception('La fecha hasta es anterior a la desde.');
    $coef = null;
    if ($coef_txt !== '') {
        if ($regimen['iibb_regimen_codigo'] !== 'CONVENIO_MULTILATERAL') throw new Exception('El coeficiente solo corresponde a Convenio Multilateral.');
        if (!is_numeric($coef_txt) || floatval($coef_txt) < 0 || floatval($coef_txt) > 1) throw new Exception('El coeficiente va de 0 a 1 (p. ej. 0,4523).');
        $coef = floatval($coef_txt);
    }
    $fin = $hasta ?? '9999-12-31';
    if (pfFila($conexion, "SELECT 1 FROM gestion__empresas_iibb_inscripciones
                           WHERE empresa_id = ? AND jurisdiccion_id = ? AND tabla_estado_registro_id = 1
                             AND f_desde <= ? AND COALESCE(f_hasta, '9999-12-31') >= ?", 'iiss', $e, $jur, $fin, $desde)) {
        throw new Exception('Ya hay una inscripción activa en esa jurisdicción con vigencia superpuesta: inhabilítela o ciérrele la fecha hasta.');
    }
    if ($sede && pfFila($conexion, "SELECT 1 FROM gestion__empresas_iibb_inscripciones
                                    WHERE empresa_id = ? AND es_sede = 1 AND tabla_estado_registro_id = 1
                                      AND f_desde <= ? AND COALESCE(f_hasta, '9999-12-31') >= ?", 'iss', $e, $fin, $desde)) {
        throw new Exception('Ya hay otra jurisdicción sede en esas fechas.');
    }
    $uid = intval($usuario_id);
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__empresas_iibb_inscripciones
        (empresa_id, jurisdiccion_id, iibb_regimen_id, nro_inscripcion, es_sede, coeficiente_cm, f_desde, f_hasta, observaciones, usuario_id, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt, 'iiisidsssi', $e, $jur, $reg, $nro, $sede, $coef, $desde, $hasta, $obs, $uid);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    error_log("=== cfAgregarInscripcion empresa $e jurisdicción $jur: $id ===");
    return ['inscripcion_id' => $id, 'message' => 'Inscripción agregada.'];
}

function cfInhabilitarInscripcion($conexion, $empresa_id, $usuario_id, $inscripcion_id)
{
    $e = intval($empresa_id);
    $id = intval($inscripcion_id);
    $uid = intval($usuario_id);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_iibb_inscripciones
        SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?, fecha_modificacion = NOW()
        WHERE inscripcion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, 'iii', $uid, $id, $e);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($n !== 1) throw new Exception('Inscripción no encontrada o ya inhabilitada.');
    return ['message' => 'Inscripción inhabilitada.'];
}

// ------------------------------------------------------------------ actividades de cada inscripción (liquidación de IIBB)
/** Actividades de una inscripción de la empresa (activas, o todas). Script 20261111_iibb_liquidacion_mensual_01.sql. */
function cfActividades($conexion, $empresa_id, $inscripcion_id, $incluir_inactivas = false)
{
    return pfFilas($conexion,
        "SELECT a.* FROM gestion__empresas_iibb_actividades a
         WHERE a.empresa_id = ? AND a.inscripcion_id = ?" . ($incluir_inactivas ? '' : ' AND a.tabla_estado_registro_id = 1') . "
         ORDER BY a.tabla_estado_registro_id, a.es_principal DESC, a.f_desde DESC, a.actividad_id",
        'ii', intval($empresa_id), intval($inscripcion_id));
}

/** Alta de una actividad: alícuota de 0 a 100 %, mínimo mensual opcional, vigencia dentro de la inscripción. */
function cfAgregarActividad($conexion, $empresa_id, $usuario_id, $data)
{
    $e = intval($empresa_id);
    $ins_id = intval($data['inscripcion_id'] ?? 0);
    $codigo = mb_substr(trim((string)($data['codigo'] ?? '')), 0, 10);
    $desc = mb_substr(trim((string)($data['descripcion'] ?? '')), 0, 150);
    $alic_txt = trim(str_replace(',', '.', (string)($data['alicuota'] ?? '')));
    $min_txt = trim(str_replace(',', '.', (string)($data['minimo_mensual'] ?? '')));
    $desde = trim((string)($data['f_desde'] ?? ''));
    $hasta = trim((string)($data['f_hasta'] ?? '')) ?: null;
    $principal = !empty($data['es_principal']) && $data['es_principal'] !== '0' ? 1 : 0;
    $obs = mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 255);

    $ins = pfFila($conexion, "SELECT * FROM gestion__empresas_iibb_inscripciones WHERE inscripcion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
                  'ii', $ins_id, $e);
    if (!$ins) throw new Exception('Inscripción no encontrada o inhabilitada.');
    if ($desc === '') throw new Exception('Escriba la descripción de la actividad.');
    if ($alic_txt === '' || !is_numeric($alic_txt) || floatval($alic_txt) < 0 || floatval($alic_txt) > 100) {
        throw new Exception('La alícuota va en porcentaje, de 0 a 100 (p. ej. 3,5).');
    }
    $min = null;
    if ($min_txt !== '') {
        if (!is_numeric($min_txt) || floatval($min_txt) < 0) throw new Exception('El mínimo mensual no es válido.');
        $min = round(floatval($min_txt), 2);
    }
    if ($desde === '') $desde = $ins['f_desde'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) throw new Exception('Indique la fecha desde.');
    if ($hasta !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) || $hasta < $desde)) throw new Exception('La fecha hasta es anterior a la desde.');
    if ($desde < $ins['f_desde'] || ($ins['f_hasta'] && $desde > $ins['f_hasta'])) throw new Exception('La vigencia tiene que estar dentro de la de la inscripción.');
    if ($codigo !== '' && pfFila($conexion, "SELECT 1 FROM gestion__empresas_iibb_actividades
                                             WHERE empresa_id = ? AND inscripcion_id = ? AND codigo = ? AND tabla_estado_registro_id = 1
                                               AND f_desde <= ? AND COALESCE(f_hasta, '9999-12-31') >= ?",
                                 'iisss', $e, $ins_id, $codigo, $hasta ?? '9999-12-31', $desde)) {
        throw new Exception("La actividad $codigo ya está cargada con vigencia superpuesta: cierre la anterior (inhabilítela) antes de cambiar la alícuota.");
    }
    $alic = round(floatval($alic_txt), 4);
    $uid = intval($usuario_id);

    mysqli_begin_transaction($conexion);
    try {
        // La primera actividad de la inscripción es la principal; si se marca otra, deja de serlo la anterior
        $hay = pfFila($conexion, "SELECT 1 FROM gestion__empresas_iibb_actividades WHERE empresa_id = ? AND inscripcion_id = ? AND es_principal = 1
                                  AND tabla_estado_registro_id = 1", 'ii', $e, $ins_id);
        if (!$hay) $principal = 1;
        if ($principal && $hay) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_iibb_actividades SET es_principal = 0, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                                               WHERE empresa_id = ? AND inscripcion_id = ? AND es_principal = 1");
            mysqli_stmt_bind_param($stmt, 'iii', $uid, $e, $ins_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $cod = $codigo !== '' ? $codigo : null;
        $obs_v = $obs !== '' ? $obs : null;
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__empresas_iibb_actividades
            (empresa_id, inscripcion_id, codigo, descripcion, alicuota, minimo_mensual, es_principal, f_desde, f_hasta, observaciones, usuario_id, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'iissddisssi', $e, $ins_id, $cod, $desc, $alic, $min, $principal, $desde, $hasta, $obs_v, $uid);
        mysqli_stmt_execute($stmt);
        $id = intval(mysqli_insert_id($conexion));
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        error_log('ERROR cfAgregarActividad: ' . $ex->getMessage());
        throw $ex;
    }
    error_log("=== cfAgregarActividad empresa $e inscripción $ins_id: $id ===");
    return ['actividad_id' => $id, 'message' => 'Actividad agregada.'];
}

/** Baja de una actividad (las liquidaciones hechas guardan su copia). Si era la principal, pasa a serlo la más vieja activa. */
function cfInhabilitarActividad($conexion, $empresa_id, $usuario_id, $actividad_id)
{
    $e = intval($empresa_id);
    $id = intval($actividad_id);
    $uid = intval($usuario_id);
    mysqli_begin_transaction($conexion);
    try {
        $a = pfFila($conexion, "SELECT * FROM gestion__empresas_iibb_actividades WHERE actividad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 FOR UPDATE",
                    'ii', $id, $e);
        if (!$a) throw new Exception('Actividad no encontrada o ya inhabilitada.');
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_iibb_actividades SET tabla_estado_registro_id = 2, es_principal = 0,
                                                  usuario_modificacion_id = ?, fecha_modificacion = NOW() WHERE actividad_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (intval($a['es_principal']) === 1) {
            $ins = intval($a['inscripcion_id']);
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_iibb_actividades SET es_principal = 1
                                               WHERE empresa_id = ? AND inscripcion_id = ? AND tabla_estado_registro_id = 1
                                               ORDER BY f_desde, actividad_id LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ii', $e, $ins);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conexion);
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        throw $ex;
    }
    return ['message' => 'Actividad inhabilitada.'];
}

/** El usuario tiene asignada la función (accion_js) de la página en alguno de sus perfiles de la empresa. */
function cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_id, $accion_js)
{
    if (in_array(intval($usuario_id), USUARIOS_ACCESO_TOTAL, true)) {
        return (bool) pfFila($conexion, "SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_id = 1",
                             'is', intval($pagina_id), $accion_js);
    }
    $perfiles = array_column(usuario_obtener_perfiles_empresa($conexion, intval($usuario_id), intval($empresa_id)), 'empresa_perfil_id');
    if (!$perfiles) return false;
    $perfiles = array_map('intval', $perfiles);
    $in = implode(',', array_fill(0, count($perfiles), '?'));
    return (bool) pfFila($conexion,
        "SELECT 1 FROM conf__empresas_perfiles_funciones epf
         JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
         WHERE epf.empresa_perfil_id IN ($in) AND epf.asignado = 1 AND pf.pagina_id = ? AND pf.accion_js = ? AND pf.tabla_estado_registro_id = 1
         LIMIT 1", str_repeat('i', count($perfiles)) . 'is', ...array_merge($perfiles, [intval($pagina_id), $accion_js]));
}

/** Cierre manual, en su propia transacción. */
function cfCerrar($conexion, $empresa_id, $usuario_id, $data)
{
    mysqli_begin_transaction($conexion);
    try {
        $id = pfCerrar($conexion, $empresa_id, $usuario_id, $data, 'MANUAL');
        mysqli_commit($conexion);
        return ['cierre_id' => $id, 'message' => 'Período ' . $data['periodo'] . ' cerrado.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR cfCerrar: ' . $e->getMessage());
        throw $e;
    }
}

function cfReabrir($conexion, $empresa_id, $usuario_id, $data)
{
    mysqli_begin_transaction($conexion);
    try {
        pfReabrir($conexion, $empresa_id, $usuario_id, intval($data['cierre_id'] ?? 0), (string)($data['motivo'] ?? ''));
        mysqli_commit($conexion);
        return ['message' => 'Período reabierto.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR cfReabrir: ' . $e->getMessage());
        throw $e;
    }
}

// ------------------------------------------------------------------ certificados de retenciones sufridas (etapa 4)
const CF_CERT_ESTADOS = ['PENDIENTE' => 'Pendiente', 'RECIBIDO' => 'Recibido', 'CONCILIADO' => 'Conciliado'];
const CF_CERT_DIR_REL = 'uploads/retenciones';
const CF_CERT_MAX_BYTES = 5242880;   // 5 MB

/** Línea RETENCION de una cobranza confirmada de la empresa (con lo necesario para validar). */
function cfMedioRetencion($conexion, $empresa_id, $cobranza_medio_id, $bloquear = false)
{
    return pfFila($conexion,
        "SELECT cm.*, co.entidad_id, co.tabla_estado_registro_id AS estado_cobranza, COALESCE(co.f_contabilidad, co.f_emision) AS fecha_recibo,
                COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id) AS impuesto
         FROM gestion__cobranzas_medios cm
         JOIN gestion__cobranzas co ON co.cobranza_id = cm.cobranza_id
         JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id AND mp.medio_pago_tipo = 'RETENCION'
         WHERE cm.cobranza_medio_id = ? AND co.empresa_id = ?" . ($bloquear ? ' FOR UPDATE' : ''), 'ii', intval($cobranza_medio_id), intval($empresa_id));
}

/**
 * Completa o corrige los datos del certificado de una retención sufrida de un recibo confirmado: número, fecha,
 * régimen, estado y período de cómputo (no toca importes ni el asiento). Recibido / conciliado exigen número y fecha;
 * el número sigue siendo único por cliente + impuesto + jurisdicción. Cambiar el período de cómputo desde o hacia un
 * período cerrado (Control Fiscal) no se permite.
 */
function cfActualizarCertificado($conexion, $empresa_id, $usuario_id, $data)
{
    $e = intval($empresa_id);
    $id = intval($data['cobranza_medio_id'] ?? 0);
    $nro = mb_substr(trim((string)($data['retencion_certificado_nro'] ?? '')), 0, 50);
    $f_cert = trim((string)($data['retencion_f_certificado'] ?? ''));
    $regimen = mb_substr(trim((string)($data['retencion_regimen'] ?? '')), 0, 100);
    $estado = (string)($data['retencion_certificado_estado'] ?? '');
    $periodo = trim((string)($data['retencion_periodo_computo'] ?? ''));

    if (!isset(CF_CERT_ESTADOS[$estado])) throw new Exception('Elija el estado del certificado.');
    if ($f_cert !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_cert)) throw new Exception('La fecha del certificado no es válida.');
    if ($estado !== 'PENDIENTE' && ($nro === '' || $f_cert === '')) throw new Exception('Un certificado recibido o conciliado lleva número y fecha.');
    pfValidarPeriodo($periodo);

    mysqli_begin_transaction($conexion);
    try {
        $m = cfMedioRetencion($conexion, $e, $id, true);
        if (!$m) throw new Exception('Retención no encontrada.');
        if (intval($m['estado_cobranza']) !== 5) throw new Exception('Solo se actualiza el certificado de recibos confirmados (en borrador, desde Cobranzas).');
        $imp = intval($m['impuesto']);
        $jur = intval($m['jurisdiccion_id'] ?? 0);
        if ($nro !== '' && pfFila($conexion,
                "SELECT 1 FROM gestion__cobranzas_medios cm JOIN gestion__cobranzas co ON co.cobranza_id = cm.cobranza_id
                 WHERE co.empresa_id = ? AND co.entidad_id = ? AND cm.cobranza_medio_id <> ? AND co.tabla_estado_registro_id NOT IN (6, 38)
                   AND cm.impuesto_tipo_id = ? AND COALESCE(cm.jurisdiccion_id, 0) = ? AND cm.retencion_certificado_nro = ? LIMIT 1",
                'iiiiis', $e, intval($m['entidad_id']), $id, $imp, $jur, $nro)) {
            throw new Exception("El certificado $nro ya está registrado para este cliente en ese impuesto.");
        }
        // Período de cómputo: ni sacarlo de un período cerrado ni llevarlo a uno
        $anterior = $m['retencion_periodo_computo'] ?: substr((string)$m['fecha_recibo'], 0, 7);
        if ($anterior !== $periodo) {
            $items = [];
            pfAgregarComponente($items, $imp, $jur);
            foreach ([$anterior, $periodo] as $per) {
                $msg = pfMensajeBloqueo(pfCierresQueBloquean($conexion, $e, $per . '-01', array_values($items)), 'cambiar el período de cómputo');
                if ($msg !== null) throw new Exception($msg);
            }
        }
        $nro_v = $nro !== '' ? $nro : null;
        $f_v = $f_cert !== '' ? $f_cert : null;
        $reg_v = $regimen !== '' ? $regimen : null;
        $uid = intval($usuario_id);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__cobranzas_medios
            SET retencion_certificado_nro = ?, retencion_f_certificado = ?, retencion_regimen = ?, retencion_certificado_estado = ?,
                retencion_periodo_computo = ?, usuario_modificacion_id = ?
            WHERE cobranza_medio_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssssiii', $nro_v, $f_v, $reg_v, $estado, $periodo, $uid, $id, $e);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
        error_log("=== cfActualizarCertificado medio $id empresa $e: $estado $nro período $periodo ===");
        return ['message' => 'Certificado actualizado.'];
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        error_log('ERROR cfActualizarCertificado: ' . $ex->getMessage());
        throw $ex;
    }
}

/** Guarda el archivo del certificado (PDF / JPG / PNG, hasta 5 MB; el tipo se toma del contenido). */
function cfSubirCertificado($conexion, $empresa_id, $usuario_id, $cobranza_medio_id, $archivo)
{
    $e = intval($empresa_id);
    $m = cfMedioRetencion($conexion, $e, $cobranza_medio_id);
    if (!$m || intval($m['estado_cobranza']) !== 5) throw new Exception('Retención no encontrada en un recibo confirmado.');
    if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new Exception('No se recibió el archivo.');
    if ($archivo['size'] > CF_CERT_MAX_BYTES) throw new Exception('El archivo no puede superar 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
    if (!$ext) throw new Exception('El certificado debe ser PDF, JPG o PNG.');
    $dir = __DIR__ . '/../../' . CF_CERT_DIR_REL . '/' . $e;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new Exception('No se pudo crear la carpeta de certificados.');
    $raiz = __DIR__ . '/../../' . CF_CERT_DIR_REL . '/.htaccess';
    if (!file_exists($raiz)) @file_put_contents($raiz, "Require all denied\nDeny from all\n");
    $nombre = 'ret_' . intval($cobranza_medio_id) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($archivo['tmp_name'], $dir . '/' . $nombre)) throw new Exception('No se pudo guardar el archivo.');
    $ruta = CF_CERT_DIR_REL . '/' . $e . '/' . $nombre;
    $id = intval($cobranza_medio_id);
    $uid = intval($usuario_id);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__cobranzas_medios SET retencion_adjunto = ?, usuario_modificacion_id = ?
                                       WHERE cobranza_medio_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'siii', $ruta, $uid, $id, $e);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return ['message' => 'Archivo del certificado guardado.'];
}

/** Ruta absoluta del archivo del certificado de la empresa (null si no hay). */
function cfArchivoCertificado($conexion, $empresa_id, $cobranza_medio_id)
{
    $m = cfMedioRetencion($conexion, intval($empresa_id), $cobranza_medio_id);
    if (!$m || empty($m['retencion_adjunto'])) return null;
    $base = realpath(__DIR__ . '/../../' . CF_CERT_DIR_REL . '/' . intval($empresa_id));
    $ruta = realpath(__DIR__ . '/../../' . $m['retencion_adjunto']);
    if (!$base || !$ruta || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0) return null;   // solo dentro de la carpeta de la empresa
    return $ruta;
}
