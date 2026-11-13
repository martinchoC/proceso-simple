<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/formato.php';
/**
 * Liquidación mensual de Ingresos Brutos (impuesto propio), solapa "Ingresos Brutos" de Control Fiscal (página 125 / 625).
 * Script: _local/sql/mantenimiento/20261111_iibb_liquidacion_mensual_01.sql.
 *
 * Una posición por empresa + jurisdicción inscripta + período (gestion__iibb_posiciones):
 *   Base total          ventas fiscales confirmadas del mes (f_emision, las NC restan) sin IVA: neto gravado + no gravado
 *                       + exento, como el Libro IVA Ventas
 *   Base calculada      Local: la base total. Convenio Multilateral: base total × coeficiente de la inscripción
 *   Base declarada      la que carga el usuario por actividad (por defecto, la calculada en la actividad principal)
 *   Impuesto            por actividad: el mayor entre base × alícuota y el mínimo mensual de la actividad (si tiene)
 *   Menos               retenciones IIBB sufridas (impuesto 4, período de cómputo) y percepciones IIBB sufridas
 *                       (impuesto 1, compras por f_contabilidad) de la jurisdicción, y el saldo a favor anterior
 * A pagar: I_LIQ (tipo de obligación IMP_IIBB, se paga desde Órdenes de Pago). A favor: pasa al período siguiente.
 * Asiento: Debe IIBB_GASTO (impuesto determinado) / Haber cuentas de retenciones y percepciones, saldo a favor anterior
 * (IIBB_SALDO_FAVOR) e IIBB_A_PAGAR, o Debe IIBB_SALDO_FAVOR si queda a favor. Cierra el período de IIBB de la
 * jurisdicción (origen IIBB_POSICION): se reabre solo anulando la liquidación.
 */
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/cont_periodos.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/periodos_fiscales_core_model.php';
require_once __DIR__ . '/obligaciones_rendiciones_model.php';   // registrarObligacionImpositiva (hace $conexion = $conn: incluir a nivel archivo)

const IIBB_IMP_OBLIGACION = 9;          // gestion__impuestos_tipos: Ingresos Brutos
const IIBB_IMP_PERCEPCION = 1;          // Percepción IIBB recibida (compras)
const IIBB_IMP_RETENCION = 4;           // Retención IIBB (sufrida en cobranzas)
const IIBB_POS_VIGENTE = 5;
const IIBB_POS_ANULADA = 38;
const IIBB_ORIGEN = 'IIBB_POSICION';

function iibbRango($periodo)
{
    $desde = pfValidarPeriodo($periodo) . '-01';
    return [$desde, date('Y-m-t', strtotime($desde))];
}

/** Ventas fiscales del mes sin IVA (neto gravado + no gravado + exento, firmadas). */
function iibbBaseTotal($conexion, $empresa_id, $periodo)
{
    [$desde, $hasta] = iibbRango($periodo);
    $estados = implode(',', PF_ESTADOS_VENTAS);
    $f = pfFila($conexion,
        "SELECT ROUND(SUM(IF(ct.signo = '-', -1, 1) * (
                    IF(COALESCE(a.es_gravado, 1) = 1, d.importe_neto, 0)
                  + d.importe_no_gravado + IF(a.es_no_gravado = 1 AND d.importe_no_gravado = 0, d.importe_neto, 0)
                  + d.importe_exento + IF(a.es_exento = 1 AND d.importe_exento = 0, d.importe_neto, 0))), 2) AS base,
                COUNT(DISTINCT v.venta_factura_id) AS comprobantes
         FROM gestion__ventas_facturas v
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
         JOIN gestion__ventas_facturas_detalles d ON d.venta_factura_id = v.venta_factura_id AND COALESCE(d.tabla_estado_registro_id, 1) <> 6
         LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = d.iva_alicuota_id AND a.empresa_id = v.empresa_id
         WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados)
           AND ct.comprobante_fiscal_id > 0", 'iss', intval($empresa_id), $desde, $hasta);
    return ['base' => pfR($f['base'] ?? 0), 'comprobantes' => intval($f['comprobantes'] ?? 0)];
}

/** Inscripciones IIBB vigentes en algún día del período (sin las exentas), con su régimen. */
function iibbInscripcionesPeriodo($conexion, $empresa_id, $periodo, $jurisdiccion_id = 0)
{
    [$desde, $hasta] = iibbRango($periodo);
    $sql = "SELECT i.inscripcion_id, i.jurisdiccion_id, i.nro_inscripcion, i.es_sede, i.coeficiente_cm, j.jurisdiccion_nombre,
                   r.iibb_regimen_codigo, r.iibb_regimen_nombre
            FROM gestion__empresas_iibb_inscripciones i
            JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = i.jurisdiccion_id
            LEFT JOIN gestion__iibb_regimenes r ON r.iibb_regimen_id = i.iibb_regimen_id
            WHERE i.empresa_id = ? AND i.tabla_estado_registro_id = 1 AND i.f_desde <= ? AND (i.f_hasta IS NULL OR i.f_hasta >= ?)
              AND COALESCE(r.iibb_regimen_codigo, '') <> 'EXENTO'";
    $tipos = 'iss';
    $params = [intval($empresa_id), $hasta, $desde];
    if (intval($jurisdiccion_id) > 0) {
        $sql .= ' AND i.jurisdiccion_id = ?';
        $tipos .= 'i';
        $params[] = intval($jurisdiccion_id);
    }
    $sql .= ' ORDER BY i.es_sede DESC, j.jurisdiccion_nombre, i.f_desde DESC';
    return pfFilas($conexion, $sql, $tipos, ...$params);
}

/** Actividades de una inscripción vigentes en el período (la principal primero). */
function iibbActividadesPeriodo($conexion, $empresa_id, $inscripcion_id, $periodo)
{
    [$desde, $hasta] = iibbRango($periodo);
    return pfFilas($conexion,
        "SELECT actividad_id, codigo, descripcion, alicuota, minimo_mensual, es_principal
         FROM gestion__empresas_iibb_actividades
         WHERE empresa_id = ? AND inscripcion_id = ? AND tabla_estado_registro_id = 1 AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
         ORDER BY es_principal DESC, actividad_id", 'iiss', intval($empresa_id), intval($inscripcion_id), $hasta, $desde);
}

/** Base propuesta de la jurisdicción: total (local) o total × coeficiente (CM; null si falta el coeficiente). */
function iibbBaseCalculada($inscripcion, $base_total)
{
    if (($inscripcion['iibb_regimen_codigo'] ?? '') !== 'CONVENIO_MULTILATERAL') return pfR($base_total);
    if ($inscripcion['coeficiente_cm'] === null) return null;
    return pfR($base_total * floatval($inscripcion['coeficiente_cm']));
}

/** Impuesto de una actividad: el mayor entre base × alícuota y el mínimo mensual. */
function iibbImpuestoActividad($base, $alicuota, $minimo)
{
    $calc = pfR(floatval($base) * floatval($alicuota) / 100);
    $min = $minimo !== null ? pfR($minimo) : 0.0;
    return ['impuesto_calculado' => $calc, 'impuesto_determinado' => max($calc, $min)];
}

/** Retenciones IIBB sufridas de la jurisdicción en su período de cómputo, por cuenta contable. */
function iibbRetenciones($conexion, $empresa_id, $periodo, $jurisdiccion_id, $detalle = false)
{
    [, $hasta] = iibbRango($periodo);
    $cuenta = "COALESCE((SELECT NULLIF(cfg.cont_cuenta_id, 0) FROM gestion__empresas_impuestos_config cfg
                         WHERE cfg.empresa_id = co.empresa_id AND cfg.impuesto_tipo_id = " . IIBB_IMP_RETENCION . " AND cfg.jurisdiccion_id = cm.jurisdiccion_id
                           AND cfg.tabla_estado_registro_id = 1 AND cfg.f_desde <= ? AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)
                         ORDER BY cfg.prioridad, cfg.empresa_impuesto_config_id LIMIT 1), NULLIF(mp.cont_cuenta_id, 0))";
    $base = "FROM gestion__cobranzas co
             JOIN gestion__cobranzas_medios cm ON cm.cobranza_id = co.cobranza_id
             JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id AND mp.medio_pago_tipo = 'RETENCION'
             LEFT JOIN gestion__comprobantes c ON c.comprobante_id = co.comprobante_id
             LEFT JOIN gestion__entidades en ON en.entidad_id = co.entidad_id
             WHERE co.empresa_id = ? AND co.tabla_estado_registro_id = 5
               AND COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id) = " . IIBB_IMP_RETENCION . " AND cm.jurisdiccion_id = ?
               AND COALESCE(cm.retencion_periodo_computo, DATE_FORMAT(COALESCE(co.f_contabilidad, co.f_emision), '%Y-%m')) = ?";
    if ($detalle) {
        return pfFilas($conexion,
            "SELECT COALESCE(co.f_contabilidad, co.f_emision) AS fecha, CONCAT('RCX ', LPAD(c.comprobante_nro, 8, '0')) AS comprobante,
                    COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS tercero, en.cuit,
                    cm.retencion_certificado_nro AS certificado, ROUND(cm.importe_comprobante, 2) AS importe
             $base ORDER BY fecha", 'iis', intval($empresa_id), intval($jurisdiccion_id), $periodo);
    }
    return pfFilas($conexion,
        "SELECT $cuenta AS cuenta_id, COUNT(*) AS registros, ROUND(SUM(cm.importe_comprobante), 2) AS importe
         $base GROUP BY 1", 'ssiis', $hasta, $hasta, intval($empresa_id), intval($jurisdiccion_id), $periodo);
}

/** Percepciones IIBB sufridas de la jurisdicción en compras confirmadas del mes, por cuenta contable. */
function iibbPercepciones($conexion, $empresa_id, $periodo, $jurisdiccion_id, $detalle = false)
{
    [$desde, $hasta] = iibbRango($periodo);
    $base = "FROM gestion__facturas_proveedores f
             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
             JOIN gestion__comprobantes_impuestos ci ON ci.comprobante_id = f.comprobante_id AND ci.tabla_estado_registro_id = 1
             LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
             LEFT JOIN gestion__entidades en ON en.entidad_id = f.entidad_id
             WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5
               AND ct.comprobante_fiscal_id > 0
               AND COALESCE(ci.impuesto_tipo_id, cfg.impuesto_tipo_id) = " . IIBB_IMP_PERCEPCION . "
               AND COALESCE(ci.jurisdiccion_id, cfg.jurisdiccion_id) = ?";
    if ($detalle) {
        return pfFilas($conexion,
            "SELECT COALESCE(f.f_contabilidad, f.f_emision) AS fecha,
                    CONCAT(ct.codigo, ' ', LPAD(f.comprobante_pv, 4, '0'), '-', LPAD(f.comprobante_nro, 8, '0')) AS comprobante,
                    COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS tercero, en.cuit, NULL AS certificado,
                    ROUND(IF(ct.signo = '-', -1, 1) * ci.importe, 2) AS importe
             $base ORDER BY fecha", 'issi', intval($empresa_id), $desde, $hasta, intval($jurisdiccion_id));
    }
    return pfFilas($conexion,
        "SELECT NULLIF(cfg.cont_cuenta_id, 0) AS cuenta_id, COUNT(*) AS registros, ROUND(SUM(IF(ct.signo = '-', -1, 1) * ci.importe), 2) AS importe
         $base GROUP BY 1", 'issi', intval($empresa_id), $desde, $hasta, intval($jurisdiccion_id));
}

/** Liquidación vigente del período y la jurisdicción (o null). */
function iibbPosicionVigente($conexion, $empresa_id, $periodo, $jurisdiccion_id, $bloquear = false)
{
    return pfFila($conexion,
        "SELECT p.*, c.comprobante_nro, c.comprobante_pv AS liq_pv, c.importe_total AS liq_total, c.importe_pendiente AS liq_pendiente
         FROM gestion__iibb_posiciones p
         LEFT JOIN gestion__comprobantes c ON c.comprobante_id = p.comprobante_id
         WHERE p.empresa_id = ? AND p.periodo = ? AND p.jurisdiccion_id = ? AND p.tabla_estado_registro_id = ?
         ORDER BY p.iibb_posicion_id DESC LIMIT 1" . ($bloquear ? ' FOR UPDATE' : ''),
        'isii', intval($empresa_id), $periodo, intval($jurisdiccion_id), IIBB_POS_VIGENTE);
}

/** Saldo a favor que deja la última liquidación vigente anterior de la jurisdicción. */
function iibbSaldoFavorAnterior($conexion, $empresa_id, $periodo, $jurisdiccion_id)
{
    $f = pfFila($conexion,
        "SELECT periodo, saldo_a_favor FROM gestion__iibb_posiciones
         WHERE empresa_id = ? AND jurisdiccion_id = ? AND periodo < ? AND tabla_estado_registro_id = ?
         ORDER BY periodo DESC, iibb_posicion_id DESC LIMIT 1", 'iisi', intval($empresa_id), intval($jurisdiccion_id), $periodo, IIBB_POS_VIGENTE);
    return $f ? ['periodo' => $f['periodo'], 'importe' => pfR($f['saldo_a_favor'])] : ['periodo' => null, 'importe' => 0.0];
}

function iibbLiquidacionPosterior($conexion, $empresa_id, $periodo, $jurisdiccion_id)
{
    $f = pfFila($conexion, "SELECT periodo FROM gestion__iibb_posiciones WHERE empresa_id = ? AND jurisdiccion_id = ? AND periodo > ?
                            AND tabla_estado_registro_id = ? ORDER BY periodo LIMIT 1",
                'iisi', intval($empresa_id), intval($jurisdiccion_id), $periodo, IIBB_POS_VIGENTE);
    return $f ? $f['periodo'] : null;
}

/** Cierres del período de IIBB de la jurisdicción que no son de una liquidación de IIBB (manuales). */
function iibbCierresManuales($conexion, $empresa_id, $periodo, $jurisdiccion_id)
{
    return array_values(array_filter(pfCierresQueBloquean($conexion, $empresa_id, $periodo . '-01', [[IIBB_IMP_OBLIGACION, intval($jurisdiccion_id)]]),
                                     function ($c) { return $c['origen'] !== IIBB_ORIGEN; }));
}

/** Datos para liquidar una jurisdicción: inscripción, base, actividades propuestas, deducciones y resultado. */
function iibbCalcular($conexion, $empresa_id, $periodo, $inscripcion, $base_total = null, $bases_actividades = null)
{
    $e = intval($empresa_id);
    $jur = intval($inscripcion['jurisdiccion_id']);
    if ($base_total === null) $base_total = iibbBaseTotal($conexion, $e, $periodo)['base'];
    $base_calc = iibbBaseCalculada($inscripcion, $base_total);
    $actividades = [];
    $primera = true;
    foreach (iibbActividadesPeriodo($conexion, $e, $inscripcion['inscripcion_id'], $periodo) as $a) {
        $id = intval($a['actividad_id']);
        $base = $bases_actividades !== null
            ? pfR($bases_actividades[$id] ?? 0)
            : ($primera ? floatval($base_calc ?? 0) : 0.0);
        $primera = false;
        $imp = iibbImpuestoActividad($base, $a['alicuota'], $a['minimo_mensual']);
        $actividades[] = ['actividad_id' => $id, 'codigo' => $a['codigo'], 'descripcion' => $a['descripcion'],
                          'alicuota' => floatval($a['alicuota']), 'minimo_mensual' => $a['minimo_mensual'] !== null ? pfR($a['minimo_mensual']) : null,
                          'es_principal' => intval($a['es_principal']), 'base_imponible' => pfR($base)] + $imp;
    }
    $ret = iibbRetenciones($conexion, $e, $periodo, $jur);
    $perc = iibbPercepciones($conexion, $e, $periodo, $jur);
    $ant = iibbSaldoFavorAnterior($conexion, $e, $periodo, $jur);
    $determinado = pfR(array_sum(array_column($actividades, 'impuesto_determinado')));
    $retenciones = pfR(array_sum(array_column($ret, 'importe')));
    $percepciones = pfR(array_sum(array_column($perc, 'importe')));
    $resultado = pfR($determinado - $retenciones - $percepciones - $ant['importe']);
    return [
        'inscripcion_id' => intval($inscripcion['inscripcion_id']),
        'jurisdiccion_id' => $jur,
        'jurisdiccion' => $inscripcion['jurisdiccion_nombre'],
        'regimen' => $inscripcion['iibb_regimen_nombre'],
        'regimen_codigo' => $inscripcion['iibb_regimen_codigo'],
        'nro_inscripcion' => $inscripcion['nro_inscripcion'],
        'coeficiente_cm' => $inscripcion['coeficiente_cm'] !== null ? floatval($inscripcion['coeficiente_cm']) : null,
        'base_total' => pfR($base_total),
        'base_calculada' => $base_calc,
        'base_imponible' => pfR(array_sum(array_column($actividades, 'base_imponible'))),
        'actividades' => $actividades,
        'impuesto_determinado' => $determinado,
        'retenciones' => $retenciones,
        'retenciones_cuentas' => $ret,
        'percepciones' => $percepciones,
        'percepciones_cuentas' => $perc,
        'saldo_favor_anterior' => $ant['importe'],
        'saldo_favor_anterior_periodo' => $ant['periodo'],
        'saldo_a_pagar' => $resultado > 0 ? $resultado : 0.0,
        'saldo_a_favor' => $resultado < 0 ? -$resultado : 0.0,
    ];
}

/** Disponible: corrió el script de la liquidación de IIBB. */
function iibbDisponible($conexion)
{
    static $ok = null;
    if ($ok === null) {
        $r = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__iibb_posiciones'");
        $ok = $r && mysqli_num_rows($r) > 0;
    }
    return $ok;
}

/** Situación del período: una fila por jurisdicción inscripta, con el cálculo propuesto y la liquidación vigente. */
function iibbResumenPeriodo($conexion, $empresa_id, $periodo)
{
    $e = intval($empresa_id);
    pfValidarPeriodo($periodo);
    if (!iibbDisponible($conexion)) throw new Exception('Falta correr el script 20261111_iibb_liquidacion_mensual_01.sql.');
    $total = iibbBaseTotal($conexion, $e, $periodo);
    $filas = [];
    foreach (iibbInscripcionesPeriodo($conexion, $e, $periodo) as $ins) {
        $jur = intval($ins['jurisdiccion_id']);
        if (isset($filas[$jur])) continue;   // una inscripción por jurisdicción en el período (la más reciente)
        $c = iibbCalcular($conexion, $e, $periodo, $ins, $total['base']);
        $vig = iibbPosicionVigente($conexion, $e, $periodo, $jur);
        $c['liquidacion'] = $vig ? [
            'iibb_posicion_id' => intval($vig['iibb_posicion_id']),
            'base_imponible' => pfR($vig['base_imponible']), 'impuesto_determinado' => pfR($vig['impuesto_determinado']),
            'retenciones' => pfR($vig['retenciones']), 'percepciones' => pfR($vig['percepciones']),
            'saldo_favor_anterior' => pfR($vig['saldo_favor_anterior']),
            'saldo_a_pagar' => pfR($vig['saldo_a_pagar']), 'saldo_a_favor' => pfR($vig['saldo_a_favor']), 'nro_ddjj' => $vig['nro_ddjj'],
            'numero' => $vig['comprobante_id'] ? emp_numero_comprobante($conexion, $e, intval($vig['liq_pv']), intval($vig['comprobante_nro'])) : null,
            'pagada' => $vig['comprobante_id'] ? abs(floatval($vig['liq_pendiente']) - floatval($vig['liq_total'])) >= 0.01 : false,
        ] : null;
        $c['posterior'] = iibbLiquidacionPosterior($conexion, $e, $periodo, $jur);
        $c['cerrado_manual'] = (bool) iibbCierresManuales($conexion, $e, $periodo, $jur);
        unset($c['retenciones_cuentas'], $c['percepciones_cuentas']);
        $filas[$jur] = $c;
    }
    return ['periodo' => $periodo, 'base_total' => $total['base'], 'comprobantes' => $total['comprobantes'], 'filas' => array_values($filas)];
}

/** Retenciones y percepciones de la jurisdicción en el período (para ver el detalle). */
function iibbDetalleDeducciones($conexion, $empresa_id, $periodo, $jurisdiccion_id)
{
    pfValidarPeriodo($periodo);
    return ['retenciones' => iibbRetenciones($conexion, $empresa_id, $periodo, $jurisdiccion_id, true),
            'percepciones' => iibbPercepciones($conexion, $empresa_id, $periodo, $jurisdiccion_id, true)];
}

/** Líneas del asiento (Debe +, Haber -), agrupadas por cuenta. */
function iibbLineasAsiento($conexion, $empresa_id, $calc, $jurisdiccion)
{
    $lineas = [];
    $faltan = [];
    $sumar = function ($cuenta, $importe, $texto) use (&$lineas, &$faltan) {
        if (abs($importe) < 0.005) return;
        if (intval($cuenta) <= 0) { $faltan[] = $texto; return; }
        $lineas[$cuenta] = $lineas[$cuenta] ?? ['importe' => 0.0, 'descripcion' => $texto];
        $lineas[$cuenta]['importe'] += $importe;
    };
    $sumar(emp_cuenta_concepto_id($conexion, $empresa_id, 'IIBB_GASTO'), $calc['impuesto_determinado'],
           'Impuesto sobre los Ingresos Brutos (concepto IIBB_GASTO)');
    foreach ($calc['retenciones_cuentas'] as $r) {
        $sumar($r['cuenta_id'], -floatval($r['importe']), "Retenciones IIBB $jurisdiccion (configuración de impuestos o medio de cobro)");
    }
    foreach ($calc['percepciones_cuentas'] as $p) {
        $sumar($p['cuenta_id'], -floatval($p['importe']), "Percepciones IIBB $jurisdiccion (configuración de impuestos)");
    }
    $cta_favor = emp_cuenta_concepto_id($conexion, $empresa_id, 'IIBB_SALDO_FAVOR');
    $sumar($cta_favor, -$calc['saldo_favor_anterior'], 'IIBB saldo a favor anterior (concepto IIBB_SALDO_FAVOR)');
    $sumar(emp_cuenta_concepto_id($conexion, $empresa_id, 'IIBB_A_PAGAR'), -$calc['saldo_a_pagar'], 'IIBB a pagar (concepto IIBB_A_PAGAR)');
    $sumar($cta_favor, $calc['saldo_a_favor'], 'IIBB saldo a favor (concepto IIBB_SALDO_FAVOR)');
    if ($faltan) {
        throw new Exception('Falta la cuenta contable de: ' . implode('; ', array_unique($faltan))
                            . '. Los conceptos se configuran en Tesorería > Conceptos Contables.');
    }
    $detalles = [];
    foreach ($lineas as $cuenta => $l) {
        $imp = round($l['importe'], 2);
        if (abs($imp) < 0.005) continue;
        $detalles[] = ['cuenta_id' => $cuenta, 'debe' => $imp > 0 ? $imp : 0.0, 'haber' => $imp < 0 ? -$imp : 0.0,
                       'descripcion' => preg_replace('/ \(.*\)$/', '', $l['descripcion'])];
    }
    return $detalles;
}

/** El tipo de obligación IMP_IIBB de la empresa (lo crea si falta, como el script). */
function iibbAsegurarTipoObligacion($conexion, $empresa_id)
{
    if (pfFila($conexion, "SELECT 1 FROM gestion__obligaciones_tipos WHERE empresa_id = ? AND codigo = 'IMP_IIBB'", 'i', intval($empresa_id))) return;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__obligaciones_tipos (empresa_id, codigo, nombre, clase, rol_entidad_codigo, comprobante_tipo_codigo,
                                              permite_manual, origen_sistema, impuesto_tipo_id, orden, tabla_estado_registro_id)
                                       VALUES (?, 'IMP_IIBB', 'Ingresos Brutos - DDJJ mensual', 'OBLIGACION', NULL, 'I_LIQ', 0, ?, ?, 53, 1)");
    $origen = IIBB_ORIGEN;
    $imp = IIBB_IMP_OBLIGACION;
    $e = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'isi', $e, $origen, $imp);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Liquida el período de una jurisdicción. $data: periodo, jurisdiccion_id, actividades (JSON [{actividad_id, base_imponible}]),
 * entidad_id (organismo, si da a pagar), sucursal_id, f_vto, nro_ddjj, observaciones.
 */
function iibbLiquidar($conexion, $empresa_id, $usuario_id, $data)
{
    $e = intval($empresa_id);
    $uid = intval($usuario_id);
    $periodo = pfValidarPeriodo($data['periodo'] ?? '');
    [, $hasta] = iibbRango($periodo);
    $jur = intval($data['jurisdiccion_id'] ?? 0);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    if ($periodo > date('Y-m')) throw new Exception('No se puede liquidar un período futuro.');
    if ($sucursal_id <= 0 || !pfFila($conexion, "SELECT 1 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", 'ii', $sucursal_id, $e)) {
        throw new Exception('Elija la sucursal de la liquidación.');
    }
    $ins = iibbInscripcionesPeriodo($conexion, $e, $periodo, $jur)[0] ?? null;
    if (!$ins) throw new Exception("La empresa no está inscripta en Ingresos Brutos en esa jurisdicción en $periodo (solapa Inscripciones IIBB).");

    // Bases por actividad que cargó el usuario
    $bases = [];
    $entrada = json_decode((string)($data['actividades'] ?? '[]'), true);
    if (!is_array($entrada)) throw new Exception('Las bases por actividad no son válidas.');
    foreach ($entrada as $a) {
        $base = is_numeric($a['base_imponible'] ?? null) ? floatval($a['base_imponible']) : null;
        if ($base === null) throw new Exception('Indique la base imponible de cada actividad (0 si no tuvo ingresos).');
        $bases[intval($a['actividad_id'] ?? 0)] = $base;
    }
    error_log("=== INICIO iibbLiquidar empresa $e jurisdicción $jur período $periodo ===");

    $lock = 'iibb_posicion_' . $e;
    $l = pfFila($conexion, "SELECT GET_LOCK(?, 10) AS ok", 's', $lock);
    if (!$l || intval($l['ok']) !== 1) throw new Exception('Otra liquidación de Ingresos Brutos está en curso. Intente de nuevo.');
    mysqli_begin_transaction($conexion);
    try {
        if (iibbPosicionVigente($conexion, $e, $periodo, $jur, true)) {
            throw new Exception("El período $periodo ya está liquidado en {$ins['jurisdiccion_nombre']}: anule la liquidación para volver a hacerla.");
        }
        $post = iibbLiquidacionPosterior($conexion, $e, $periodo, $jur);
        if ($post) throw new Exception("Hay una liquidación posterior ($post) que arrastra el saldo: anúlela primero.");
        if (iibbCierresManuales($conexion, $e, $periodo, $jur)) {
            throw new Exception("El período $periodo de Ingresos Brutos {$ins['jurisdiccion_nombre']} está cerrado a mano: reábralo en Períodos cerrados para liquidarlo.");
        }

        $calc = iibbCalcular($conexion, $e, $periodo, $ins, null, $bases);
        if (!$calc['actividades']) {
            throw new Exception("La inscripción de {$ins['jurisdiccion_nombre']} no tiene actividades vigentes en $periodo: cárguelas en Inscripciones IIBB.");
        }
        foreach ($bases as $id => $b) {
            if (!in_array($id, array_column($calc['actividades'], 'actividad_id'), true)) throw new Exception('Una actividad no corresponde a la inscripción.');
            if ($b < 0) throw new Exception('La base imponible no puede ser negativa.');
        }
        if ($calc['impuesto_determinado'] + $calc['retenciones'] + $calc['percepciones'] + $calc['saldo_favor_anterior'] < 0.01) {
            throw new Exception('No hay impuesto ni pagos a cuenta para liquidar en el período.');
        }
        $detalles = iibbLineasAsiento($conexion, $e, $calc, $ins['jurisdiccion_nombre']);

        $comprobante_id = null;
        $numero = null;
        if ($calc['saldo_a_pagar'] > 0) {
            if ($entidad_id <= 0 || !pfFila($conexion, "SELECT 1 FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', $entidad_id, $e)) {
                throw new Exception('Da a pagar: elija el organismo recaudador (entidad proveedora de la empresa, p. ej. ARBA o la Dirección de Rentas).');
            }
            iibbAsegurarTipoObligacion($conexion, $e);
            $res = registrarObligacionImpositiva($conexion, [
                'en_transaccion' => true,
                'sin_asiento' => true,
                'tipo_codigo' => 'IMP_IIBB',
                'origen' => IIBB_ORIGEN,
                'entidad_id' => $entidad_id,
                'sucursal_id' => $sucursal_id,
                'f_emision' => $hasta,
                'f_vto' => trim((string)($data['f_vto'] ?? '')) ?: date('Y-m-d', strtotime('+10 days')),
                'periodo_fiscal' => $periodo,
                'descripcion' => mb_substr('Ingresos Brutos ' . $ins['jurisdiccion_nombre'] . ' ' . $periodo, 0, 200),
                'nro_ddjj' => trim((string)($data['nro_ddjj'] ?? '')),
                'importe_total' => $calc['saldo_a_pagar'],
                'cont_cuenta_id' => emp_cuenta_concepto_id($conexion, $e, 'IIBB_A_PAGAR'),
                'impuesto_tipo_id' => IIBB_IMP_OBLIGACION,
                'jurisdiccion_id' => $jur,
            ], $e, $uid);
            $comprobante_id = intval($res['comprobante_id']);
            $numero = $res['numero'];
        }

        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id' => $e,
            'sucursal_id' => $sucursal_id,
            'comprobante_id' => $comprobante_id ?: 0,
            'sin_comprobante' => !$comprobante_id,
            'entidad_id' => $entidad_id,
            'f_asiento' => $hasta,
            'descripcion' => 'Liquidación IIBB ' . $ins['jurisdiccion_nombre'] . ' ' . $periodo . ($numero ? ' - I_LIQ ' . $numero : ' - saldo a favor'),
            'usuario_id' => $uid,
        ], $detalles);

        $nro_ddjj = mb_substr(trim((string)($data['nro_ddjj'] ?? '')), 0, 50) ?: null;
        $obs = mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 255) ?: null;
        $estado = IIBB_POS_VIGENTE;
        $ins_id = $calc['inscripcion_id'];
        $reg = $calc['regimen_codigo'];
        $coef = $calc['coeficiente_cm'];
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__iibb_posiciones
            (empresa_id, periodo, jurisdiccion_id, inscripcion_id, iibb_regimen_codigo, coeficiente_cm, base_total, base_calculada, base_imponible,
             impuesto_determinado, retenciones, percepciones, saldo_favor_anterior, saldo_a_pagar, saldo_a_favor, comprobante_id, cont_asiento_id,
             nro_ddjj, observaciones, tabla_estado_registro_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'isiisddddddddddiissii', $e, $periodo, $jur, $ins_id, $reg, $coef, $calc['base_total'], $calc['base_calculada'],
                               $calc['base_imponible'], $calc['impuesto_determinado'], $calc['retenciones'], $calc['percepciones'],
                               $calc['saldo_favor_anterior'], $calc['saldo_a_pagar'], $calc['saldo_a_favor'], $comprobante_id, $asiento_id,
                               $nro_ddjj, $obs, $estado, $uid);
        mysqli_stmt_execute($stmt);
        $pos_id = intval(mysqli_insert_id($conexion));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__iibb_posiciones_actividades
            (iibb_posicion_id, actividad_id, codigo, descripcion, base_imponible, alicuota, impuesto_calculado, minimo_mensual, impuesto_determinado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($calc['actividades'] as $a) {
            mysqli_stmt_bind_param($stmt, 'iissddddd', $pos_id, $a['actividad_id'], $a['codigo'], $a['descripcion'], $a['base_imponible'],
                                   $a['alicuota'], $a['impuesto_calculado'], $a['minimo_mensual'], $a['impuesto_determinado']);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);

        // El período de IIBB de la jurisdicción queda cerrado hasta anular la liquidación
        pfCerrarPorLiquidacion($conexion, $e, $uid, IIBB_IMP_OBLIGACION, $jur, $periodo, IIBB_ORIGEN, $pos_id,
                               'Liquidación de IIBB ' . $periodo . ($nro_ddjj ? ' - DDJJ ' . $nro_ddjj : ''));

        mysqli_commit($conexion);
        pfFila($conexion, "SELECT RELEASE_LOCK(?) AS ok", 's', $lock);
        $msg = $calc['saldo_a_pagar'] > 0
            ? "IIBB {$ins['jurisdiccion_nombre']} $periodo liquidado: a pagar $" . fmt_numero($calc['saldo_a_pagar'], 2) . " (I_LIQ $numero). Se paga desde Órdenes de Pago."
            : "IIBB {$ins['jurisdiccion_nombre']} $periodo liquidado: saldo a favor $" . fmt_numero($calc['saldo_a_favor'], 2) . ' (pasa al período siguiente).';
        return ['iibb_posicion_id' => $pos_id, 'comprobante_id' => $comprobante_id, 'numero' => $numero, 'cont_asiento_id' => $asiento_id, 'message' => $msg];
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        pfFila($conexion, "SELECT RELEASE_LOCK(?) AS ok", 's', $lock);
        error_log('ERROR iibbLiquidar: ' . $ex->getMessage());
        throw $ex;
    }
}

/** Anula la liquidación vigente (la última de la jurisdicción y con la I_LIQ sin pagar): I_LIQ y asiento anulados, período reabierto. */
function iibbAnular($conexion, $empresa_id, $usuario_id, $data)
{
    $e = intval($empresa_id);
    $uid = intval($usuario_id);
    $pid = intval($data['iibb_posicion_id'] ?? 0);
    $motivo = mb_substr(trim((string)($data['motivo'] ?? '')), 0, 255);
    if ($motivo === '') throw new Exception('Ingrese el motivo de la anulación.');

    mysqli_begin_transaction($conexion);
    try {
        $pos = pfFila($conexion, "SELECT * FROM gestion__iibb_posiciones WHERE iibb_posicion_id = ? AND empresa_id = ? FOR UPDATE", 'ii', $pid, $e);
        if (!$pos || intval($pos['tabla_estado_registro_id']) !== IIBB_POS_VIGENTE) throw new Exception('Liquidación no encontrada o ya anulada.');
        $post = iibbLiquidacionPosterior($conexion, $e, $pos['periodo'], intval($pos['jurisdiccion_id']));
        if ($post) throw new Exception("Hay una liquidación posterior ($post) que usa este saldo: anúlela primero.");
        if (!empty($pos['cont_asiento_id'])) cont_periodo_exigir_asiento($conexion, $e, intval($pos['cont_asiento_id']), 'anular la liquidación');

        $comp_id = intval($pos['comprobante_id'] ?? 0);
        if ($comp_id > 0) {
            $c = pfFila($conexion, "SELECT importe_total, importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = ? AND empresa_id = ? FOR UPDATE",
                        'ii', $comp_id, $e);
            $imp = pfFila($conexion, "SELECT COUNT(*) AS n FROM gestion__comprobantes_imputaciones WHERE comp_destino_id = ? AND tabla_estado_registro_id = 5", 'i', $comp_id);
            if (!$c || abs(floatval($c['importe_pendiente']) - floatval($c['importe_total'])) >= 0.01 || intval($imp['n']) > 0) {
                throw new Exception('La liquidación (I_LIQ) ya tiene pagos: anule antes la orden de pago que la cancela.');
            }
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET tabla_estado_registro_id = 38, importe_pendiente = 0 WHERE comprobante_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $comp_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__obligaciones_pago SET tabla_estado_registro_id = 38, motivo_anulacion = ?, usuario_modificacion_id = ?
                                               WHERE comprobante_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'siii', $motivo, $uid, $comp_id, $e);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        if (!empty($pos['cont_asiento_id'])) {
            $asiento = intval($pos['cont_asiento_id']);
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                                               WHERE cont_asiento_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'iii', $uid, $asiento, $e);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $estado = IIBB_POS_ANULADA;
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__iibb_posiciones SET tabla_estado_registro_id = ?, motivo_anulacion = ?, usuario_modificacion_id = ?
                                           WHERE iibb_posicion_id = ?");
        mysqli_stmt_bind_param($stmt, 'isii', $estado, $motivo, $uid, $pid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        pfReabrirPorOrigen($conexion, $e, $uid, IIBB_ORIGEN, $pid, 'Liquidación anulada: ' . $motivo);
        mysqli_commit($conexion);
        error_log("=== iibbAnular posición $pid empresa $e: $motivo ===");
        return ['message' => 'Liquidación de IIBB ' . $pos['periodo'] . ' anulada' . ($comp_id ? ' (I_LIQ y asiento anulados).' : ' (asiento anulado).')];
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        error_log('ERROR iibbAnular: ' . $ex->getMessage());
        throw $ex;
    }
}

/** Actividades declaradas en una liquidación (para verla). */
function iibbActividadesLiquidacion($conexion, $empresa_id, $posicion_id)
{
    return pfFilas($conexion,
        "SELECT pa.* FROM gestion__iibb_posiciones_actividades pa
         JOIN gestion__iibb_posiciones p ON p.iibb_posicion_id = pa.iibb_posicion_id AND p.empresa_id = ?
         WHERE pa.iibb_posicion_id = ? ORDER BY pa.posicion_actividad_id", 'ii', intval($empresa_id), intval($posicion_id));
}
