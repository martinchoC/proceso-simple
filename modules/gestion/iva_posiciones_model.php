<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/formato.php';
/**
 * IVA - Posición Mensual (página 117 / 617 en gestion_normal): IVA Ventas, IVA Compras, percepciones y retenciones
 * por mes y liquidación de la posición del período. Script: _local/sql/mantenimiento/20261011_iva_posiciones_01.sql.
 *
 * Fuentes (solo comprobantes fiscales, comprobante_fiscal_id > 0; el signo del tipo resta las NC):
 *   Débito fiscal       gestion__ventas_facturas(_detalles) por f_emision, estados confirmados / derivados
 *   Crédito fiscal      gestion__facturas_proveedores(_detalle) por f_contabilidad, confirmadas (5); computable solo A / M
 *   Percepciones        gestion__comprobantes_impuestos de las compras (sufridas: IVA 2, IIBB 1, Ganancias 3) y de las
 *                       ventas (practicadas: IIBB 6)
 *   Retenciones         medios de tipo RETENCION de las cobranzas confirmadas (IVA 5, IIBB 4, Ganancias 21)
 * Posición: débito - crédito - percepciones IVA - retenciones IVA - saldo a favor anterior. A pagar: I_LIQ
 * (obligación 7 "IVA - Posición mensual", se paga desde Órdenes de Pago); a favor: pasa al período siguiente.
 */
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/periodos_fiscales_core_model.php';
require_once __DIR__ . '/obligaciones_rendiciones_model.php';   // registrarObligacionImpositiva (hace $conexion = $conn: incluir a nivel archivo)

const IVA_TABLA_POSICIONES = 435;
const IVA_IMP_PERCEPCION = 2;          // gestion__impuestos_tipos: Percepción IVA recibida
const IVA_IMP_RETENCION = 5;           // Retención IVA (sufrida en cobranzas)
const IVA_IMP_POSICION = 7;            // IVA - Posición mensual (obligación)
const IVA_IMP_PERC_PRACTICADA = 6;     // Percepción IIBB efectuada (ventas)
const IVA_ESTADOS_VENTAS = [5, 12, 13, 15, 39, 40];
const IVA_POS_VIGENTE = 5;
const IVA_POS_ANULADA = 38;

function ivaFilas($conexion, $sql, $tipos = '', ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('IVA: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function ivaFila($conexion, $sql, $tipos = '', ...$params)
{
    $rows = ivaFilas($conexion, $sql, $tipos, ...$params);
    return $rows[0] ?? null;
}

function ivaValidarPeriodo($periodo)
{
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$periodo)) throw new Exception('Período inválido (AAAA-MM).');
    return $periodo;
}

function ivaRango($periodo)
{
    $desde = ivaValidarPeriodo($periodo) . '-01';
    return [$desde, date('Y-m-t', strtotime($desde))];
}

function ivaR($v) { return round(floatval($v), 2); }

// ------------------------------------------------------------------ agregados del período
/** IVA Ventas por alícuota (débito fiscal). */
function ivaVentasAlicuotas($conexion, $empresa_id, $periodo)
{
    [$desde, $hasta] = ivaRango($periodo);
    $estados = implode(',', IVA_ESTADOS_VENTAS);
    return ivaFilas($conexion,
        "SELECT a.iva_alicuota_id, COALESCE(a.iva_alicuota, 'Sin alícuota') AS iva_alicuota, a.porcentaje,
                NULLIF(MAX(a.cont_cuenta_venta_id), 0) AS cuenta_id,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * IF(COALESCE(a.es_gravado, 1) = 1, d.importe_neto, 0)), 2) AS neto_gravado,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * d.importe_iva), 2) AS iva,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * (d.importe_no_gravado + IF(a.es_no_gravado = 1 AND d.importe_no_gravado = 0, d.importe_neto, 0))), 2) AS no_gravado,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * (d.importe_exento + IF(a.es_exento = 1 AND d.importe_exento = 0, d.importe_neto, 0))), 2) AS exento
         FROM gestion__ventas_facturas v
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
         JOIN gestion__ventas_facturas_detalles d ON d.venta_factura_id = v.venta_factura_id AND COALESCE(d.tabla_estado_registro_id, 1) <> 6
         LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = d.iva_alicuota_id AND a.empresa_id = v.empresa_id
         WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados)
           AND ct.comprobante_fiscal_id > 0
         GROUP BY a.iva_alicuota_id, a.iva_alicuota, a.porcentaje
         ORDER BY a.porcentaje DESC", 'iss', intval($empresa_id), $desde, $hasta);
}

/** IVA Compras por alícuota; computable = letra A / M (en B / C el IVA no se discrimina). */
function ivaComprasAlicuotas($conexion, $empresa_id, $periodo)
{
    [$desde, $hasta] = ivaRango($periodo);
    return ivaFilas($conexion,
        "SELECT a.iva_alicuota_id, COALESCE(a.iva_alicuota, 'Sin alícuota') AS iva_alicuota, a.porcentaje,
                IF(ct.letra IN ('A', 'M'), 1, 0) AS computable,
                NULLIF(MAX(a.cont_cuenta_compra_id), 0) AS cuenta_id,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * IF(COALESCE(a.es_gravado, 1) = 1, d.neto_gravado, 0)), 2) AS neto_gravado,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * d.iva_importe), 2) AS iva,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * (d.no_gravado + IF(a.es_no_gravado = 1 AND d.no_gravado = 0, d.neto_gravado, 0))), 2) AS no_gravado,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * (d.exento + IF(a.es_exento = 1 AND d.exento = 0, d.neto_gravado, 0))), 2) AS exento
         FROM gestion__facturas_proveedores f
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
         JOIN gestion__facturas_proveedores_detalle d ON d.factura_proveedor_id = f.factura_proveedor_id
         LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = d.iva_alicuota_id AND a.empresa_id = f.empresa_id
         WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5
           AND ct.comprobante_fiscal_id > 0
         GROUP BY a.iva_alicuota_id, a.iva_alicuota, a.porcentaje, IF(ct.letra IN ('A', 'M'), 1, 0)
         ORDER BY computable DESC, a.porcentaje DESC", 'iss', intval($empresa_id), $desde, $hasta);
}

/** Percepciones sufridas en compras (IVA, IIBB por jurisdicción, Ganancias), con la cuenta de su config. */
function ivaPercepcionesSufridas($conexion, $empresa_id, $periodo)
{
    [$desde, $hasta] = ivaRango($periodo);
    return ivaFilas($conexion,
        "SELECT COALESCE(ci.impuesto_tipo_id, cfg.impuesto_tipo_id) AS impuesto_tipo_id, it.impuesto_tipo,
                COALESCE(ci.jurisdiccion_id, cfg.jurisdiccion_id) AS jurisdiccion_id, j.jurisdiccion_nombre,
                NULLIF(cfg.cont_cuenta_id, 0) AS cuenta_id, COUNT(DISTINCT f.factura_proveedor_id) AS comprobantes,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * ci.importe), 2) AS importe
         FROM gestion__facturas_proveedores f
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
         JOIN gestion__comprobantes_impuestos ci ON ci.comprobante_id = f.comprobante_id AND ci.tabla_estado_registro_id = 1
         LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = COALESCE(ci.impuesto_tipo_id, cfg.impuesto_tipo_id)
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = COALESCE(ci.jurisdiccion_id, cfg.jurisdiccion_id)
         WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5
           AND ct.comprobante_fiscal_id > 0
         GROUP BY 1, 2, 3, 4, 5
         ORDER BY 1, 4", 'iss', intval($empresa_id), $desde, $hasta);
}

/** Percepciones IIBB practicadas en ventas (informativo: se liquidan en Percepciones IIBB). */
function ivaPercepcionesPracticadas($conexion, $empresa_id, $periodo)
{
    [$desde, $hasta] = ivaRango($periodo);
    $estados = implode(',', IVA_ESTADOS_VENTAS);
    return ivaFilas($conexion,
        "SELECT ci.jurisdiccion_id, j.jurisdiccion_nombre, COUNT(DISTINCT v.venta_factura_id) AS comprobantes,
                ROUND(SUM(IF(ct.signo = '-', -1, 1) * ci.importe), 2) AS importe
         FROM gestion__ventas_facturas v
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
         JOIN gestion__comprobantes_impuestos ci ON ci.comprobante_id = v.comprobante_id AND ci.tabla_estado_registro_id = 1
                                               AND ci.impuesto_tipo_id = ?
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = ci.jurisdiccion_id
         WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados)
         GROUP BY ci.jurisdiccion_id, j.jurisdiccion_nombre ORDER BY j.jurisdiccion_nombre",
        'iiss', IVA_IMP_PERC_PRACTICADA, intval($empresa_id), $desde, $hasta);
}

/**
 * Retenciones sufridas: medios de tipo RETENCION de las cobranzas confirmadas, con la cuenta del medio, por su período
 * de cómputo (retencion_periodo_computo; sin dato, el mes del recibo). Script 20261016_retenciones_sufridas_02_certificados.sql.
 */
function ivaRetencionesSufridas($conexion, $empresa_id, $periodo)
{
    ivaValidarPeriodo($periodo);
    return ivaFilas($conexion,
        "SELECT COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id) AS impuesto_tipo_id, it.impuesto_tipo, mp.medio_pago,
                NULLIF(mp.cont_cuenta_id, 0) AS cuenta_id, COUNT(DISTINCT co.cobranza_id) AS comprobantes,
                ROUND(SUM(cm.importe_comprobante), 2) AS importe
         FROM gestion__cobranzas co
         JOIN gestion__cobranzas_medios cm ON cm.cobranza_id = co.cobranza_id
         JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id AND mp.medio_pago_tipo = 'RETENCION'
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id)
         WHERE co.empresa_id = ? AND co.tabla_estado_registro_id = 5
           AND COALESCE(cm.retencion_periodo_computo, DATE_FORMAT(COALESCE(co.f_contabilidad, co.f_emision), '%Y-%m')) = ?
         GROUP BY 1, 2, 3, 4 ORDER BY 1", 'is', intval($empresa_id), $periodo);
}

// ------------------------------------------------------------------ posición
/** Liquidación vigente del período (o null). */
function ivaPosicionVigente($conexion, $empresa_id, $periodo)
{
    return ivaFila($conexion,
        "SELECT p.*, c.comprobante_nro, c.comprobante_pv AS liq_pv, c.importe_total AS liq_total, c.importe_pendiente AS liq_pendiente
         FROM gestion__iva_posiciones p
         LEFT JOIN gestion__comprobantes c ON c.comprobante_id = p.comprobante_id
         WHERE p.empresa_id = ? AND p.periodo = ? AND p.tabla_estado_registro_id = ?
         ORDER BY p.iva_posicion_id DESC LIMIT 1", 'isi', intval($empresa_id), $periodo, IVA_POS_VIGENTE);
}

/** Saldo a favor que deja la última liquidación vigente anterior al período. */
function ivaSaldoFavorAnterior($conexion, $empresa_id, $periodo)
{
    $f = ivaFila($conexion,
        "SELECT periodo, saldo_a_favor FROM gestion__iva_posiciones
         WHERE empresa_id = ? AND periodo < ? AND tabla_estado_registro_id = ?
         ORDER BY periodo DESC, iva_posicion_id DESC LIMIT 1", 'isi', intval($empresa_id), $periodo, IVA_POS_VIGENTE);
    return $f ? ['periodo' => $f['periodo'], 'importe' => ivaR($f['saldo_a_favor'])] : ['periodo' => null, 'importe' => 0.0];
}

function ivaSuma($filas, $campo, $filtro = null)
{
    $t = 0.0;
    foreach ($filas as $f) {
        if ($filtro && !$filtro($f)) continue;
        $t += floatval($f[$campo]);
    }
    return ivaR($t);
}

/** Componentes de la posición del período calculados con los comprobantes actuales. */
function ivaCalcularPosicion($conexion, $empresa_id, $periodo, $datos = null)
{
    $datos = $datos ?: [
        'ventas' => ivaVentasAlicuotas($conexion, $empresa_id, $periodo),
        'compras' => ivaComprasAlicuotas($conexion, $empresa_id, $periodo),
        'percepciones' => ivaPercepcionesSufridas($conexion, $empresa_id, $periodo),
        'retenciones' => ivaRetencionesSufridas($conexion, $empresa_id, $periodo),
    ];
    $ant = ivaSaldoFavorAnterior($conexion, $empresa_id, $periodo);
    $df = ivaSuma($datos['ventas'], 'iva');
    $cf = ivaSuma($datos['compras'], 'iva', function ($f) { return intval($f['computable']) === 1; });
    $perc = ivaSuma($datos['percepciones'], 'importe', function ($f) { return intval($f['impuesto_tipo_id']) === IVA_IMP_PERCEPCION; });
    $ret = ivaSuma($datos['retenciones'], 'importe', function ($f) { return intval($f['impuesto_tipo_id']) === IVA_IMP_RETENCION; });
    $resultado = ivaR($df - $cf - $perc - $ret - $ant['importe']);
    return [
        'periodo' => $periodo,
        'debito_fiscal' => $df,
        'credito_fiscal' => $cf,
        'percepciones_iva' => $perc,
        'retenciones_iva' => $ret,
        'saldo_favor_anterior' => $ant['importe'],
        'saldo_favor_anterior_periodo' => $ant['periodo'],
        'saldo_a_pagar' => $resultado > 0 ? $resultado : 0.0,
        'saldo_a_favor' => $resultado < 0 ? -$resultado : 0.0,
    ];
}

/** Resumen de los últimos $meses períodos hasta $hasta_periodo (inclusive), del más reciente al más viejo. */
function ivaResumenMeses($conexion, $empresa_id, $hasta_periodo, $meses = 12)
{
    ivaValidarPeriodo($hasta_periodo);
    $out = [];
    $base = strtotime($hasta_periodo . '-01');
    for ($i = 0; $i < max(1, min(36, intval($meses))); $i++) {
        $periodo = date('Y-m', strtotime("-$i month", $base));
        $ventas = ivaVentasAlicuotas($conexion, $empresa_id, $periodo);
        $compras = ivaComprasAlicuotas($conexion, $empresa_id, $periodo);
        $perc = ivaPercepcionesSufridas($conexion, $empresa_id, $periodo);
        $ret = ivaRetencionesSufridas($conexion, $empresa_id, $periodo);
        $pract = ivaPercepcionesPracticadas($conexion, $empresa_id, $periodo);
        $pos = ivaPosicionVigente($conexion, $empresa_id, $periodo);
        $df = ivaSuma($ventas, 'iva');
        $cf = ivaSuma($compras, 'iva', function ($f) { return intval($f['computable']) === 1; });
        $piva = ivaSuma($perc, 'importe', function ($f) { return intval($f['impuesto_tipo_id']) === IVA_IMP_PERCEPCION; });
        $riva = ivaSuma($ret, 'importe', function ($f) { return intval($f['impuesto_tipo_id']) === IVA_IMP_RETENCION; });
        $out[] = [
            'periodo' => $periodo,
            'ventas_neto' => ivaSuma($ventas, 'neto_gravado'),
            'debito_fiscal' => $df,
            'compras_neto' => ivaSuma($compras, 'neto_gravado', function ($f) { return intval($f['computable']) === 1; }),
            'credito_fiscal' => $cf,
            'percepciones_iva' => $piva,
            'retenciones_iva' => $riva,
            'percepciones_otras' => ivaR(ivaSuma($perc, 'importe') - $piva),
            'retenciones_otras' => ivaR(ivaSuma($ret, 'importe') - $riva),
            'percepciones_practicadas' => ivaSuma($pract, 'importe'),
            'saldo_tecnico_mes' => ivaR($df - $cf - $piva - $riva),
            'posicion' => $pos ? [
                'iva_posicion_id' => intval($pos['iva_posicion_id']),
                'saldo_a_pagar' => ivaR($pos['saldo_a_pagar']), 'saldo_a_favor' => ivaR($pos['saldo_a_favor']),
                'liquidacion' => $pos['comprobante_id'] ? emp_numero_comprobante($conexion, $empresa_id, $pos['liq_pv'], $pos['comprobante_nro']) : null,
                'pagada' => $pos['comprobante_id'] ? (floatval($pos['liq_pendiente']) < 0.01) : null,
                'pago_parcial' => $pos['comprobante_id'] ? (floatval($pos['liq_pendiente']) < floatval($pos['liq_total']) - 0.009) : null,
            ] : null,
        ];
    }
    return $out;
}

/** Detalle completo del período: agregados, posición calculada y la liquidación vigente (con diferencias). */
function ivaDetallePeriodo($conexion, $empresa_id, $periodo)
{
    ivaValidarPeriodo($periodo);
    $datos = [
        'ventas' => ivaVentasAlicuotas($conexion, $empresa_id, $periodo),
        'compras' => ivaComprasAlicuotas($conexion, $empresa_id, $periodo),
        'percepciones' => ivaPercepcionesSufridas($conexion, $empresa_id, $periodo),
        'retenciones' => ivaRetencionesSufridas($conexion, $empresa_id, $periodo),
    ];
    $calculo = ivaCalcularPosicion($conexion, $empresa_id, $periodo, $datos);
    $vigente = ivaPosicionVigente($conexion, $empresa_id, $periodo);
    $diferencias = [];
    if ($vigente) {
        foreach (['debito_fiscal', 'credito_fiscal', 'percepciones_iva', 'retenciones_iva'] as $k) {
            if (abs(floatval($vigente[$k]) - $calculo[$k]) >= 0.01) {
                $diferencias[] = ['concepto' => $k, 'liquidado' => ivaR($vigente[$k]), 'actual' => $calculo[$k]];
            }
        }
        $vigente['liquidacion'] = $vigente['comprobante_id']
            ? emp_numero_comprobante($conexion, $empresa_id, $vigente['liq_pv'], $vigente['comprobante_nro']) : null;
        $vigente['pagada'] = $vigente['comprobante_id'] ? (floatval($vigente['liq_pendiente']) < floatval($vigente['liq_total']) - 0.009) : false;
    }
    $posterior = ivaFila($conexion, "SELECT periodo FROM gestion__iva_posiciones WHERE empresa_id = ? AND periodo > ? AND tabla_estado_registro_id = ?
                                     ORDER BY periodo LIMIT 1", 'isi', intval($empresa_id), $periodo, IVA_POS_VIGENTE);
    return $datos + [
        'practicadas' => ivaPercepcionesPracticadas($conexion, $empresa_id, $periodo),
        'calculo' => $calculo,
        'vigente' => $vigente,
        'diferencias' => $diferencias,
        'liquidacion_posterior' => $posterior ? $posterior['periodo'] : null,
    ];
}

/** Comprobantes del período (libro IVA): ventas y compras con sus importes firmados. */
function ivaComprobantesPeriodo($conexion, $empresa_id, $periodo)
{
    [$desde, $hasta] = ivaRango($periodo);
    $estados = implode(',', IVA_ESTADOS_VENTAS);
    $ventas = ivaFilas($conexion,
        "SELECT 'VENTA' AS libro, v.f_emision AS fecha, ct.comprobante_tipo, ct.letra, cf.codigo AS codigo_arca,
                " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS pv, v.comprobante_nro AS numero,
                COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre, v.consumidor_final_nombre, 'Consumidor final') AS entidad, e.cuit,
                IF(ct.signo = '-', -1, 1) AS signo, v.importe_neto AS neto, v.importe_iva AS iva, v.importe_no_gravado AS no_gravado,
                v.importe_exento AS exento, v.importe_otros_impuestos AS otros, v.importe_total AS total
         FROM gestion__ventas_facturas v
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
         LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
         LEFT JOIN gestion__comprobantes c ON c.comprobante_id = v.comprobante_id
         LEFT JOIN gestion__entidades e ON e.entidad_id = v.entidad_id
         WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados) AND ct.comprobante_fiscal_id > 0
         ORDER BY v.f_emision, v.comprobante_nro", 'iss', intval($empresa_id), $desde, $hasta);
    $compras = ivaFilas($conexion,
        "SELECT 'COMPRA' AS libro, COALESCE(f.f_contabilidad, f.f_emision) AS fecha, ct.comprobante_tipo, ct.letra, cf.codigo AS codigo_arca,
                f.comprobante_pv AS pv, f.comprobante_nro AS numero, COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad, e.cuit,
                IF(ct.signo = '-', -1, 1) AS signo, f.subtotal AS neto, f.impuestos AS iva, f.no_gravado, f.exento,
                f.otros_impuestos AS otros, f.total
         FROM gestion__facturas_proveedores f
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
         LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
         LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
         WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5
           AND ct.comprobante_fiscal_id > 0
         ORDER BY fecha, f.comprobante_pv, f.comprobante_nro", 'iss', intval($empresa_id), $desde, $hasta);
    return array_merge($ventas, $compras);
}

/**
 * Libro IVA Ventas o Compras del período con columnas dinámicas: neto e IVA por alícuota (de las líneas) y una columna
 * por percepción / impuesto adicional y jurisdicción (de gestion__comprobantes_impuestos). Importes firmados (las NC
 * restan). Devuelve ['columnas' => [{clave, titulo, grupo}], 'filas' => [... + valores{clave: importe}], 'totales' => {clave: importe}].
 * No gravado y exento salen de las líneas, como en la posición. La columna "control" es total - (componentes): distinta de
 * cero señala un comprobante cuyo total no cierra con sus líneas y percepciones (unos centavos en las B por redondeo).
 */
function ivaLibro($conexion, $empresa_id, $periodo, $libro)
{
    [$desde, $hasta] = ivaRango($periodo);
    $empresa_id = intval($empresa_id);
    if ($libro === 'VENTAS') {
        $estados = implode(',', IVA_ESTADOS_VENTAS);
        $filas = ivaFilas($conexion,
            "SELECT v.venta_factura_id AS id, v.comprobante_id, v.f_emision AS fecha, ct.codigo AS tipo, ct.comprobante_tipo, ct.letra,
                    cf.codigo AS codigo_arca, " . emp_sql_pv_mostrar('v.punto_venta_id', 'v.empresa_id') . " AS pv, v.comprobante_nro AS numero,
                    COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre, v.consumidor_final_nombre, 'Consumidor final') AS entidad, e.cuit,
                    IF(ct.signo = '-', -1, 1) AS signo, v.importe_no_gravado AS no_gravado, v.importe_exento AS exento, v.importe_total AS total
             FROM gestion__ventas_facturas v
             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
             LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
             LEFT JOIN gestion__entidades e ON e.entidad_id = v.entidad_id
             WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados) AND ct.comprobante_fiscal_id > 0
             ORDER BY v.f_emision, v.punto_venta_id, v.comprobante_nro", 'iss', $empresa_id, $desde, $hasta);
        $lineas = ivaFilas($conexion,
            "SELECT d.venta_factura_id AS id, a.porcentaje, COALESCE(a.es_gravado, 1) AS es_gravado,
                    SUM(IF(COALESCE(a.es_gravado, 1) = 1, d.importe_neto, 0)) AS neto, SUM(d.importe_iva) AS iva,
                    SUM(d.importe_no_gravado + IF(a.es_no_gravado = 1 AND d.importe_no_gravado = 0, d.importe_neto, 0)) AS no_gravado,
                    SUM(d.importe_exento + IF(a.es_exento = 1 AND d.importe_exento = 0, d.importe_neto, 0)) AS exento
             FROM gestion__ventas_facturas v
             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
             JOIN gestion__ventas_facturas_detalles d ON d.venta_factura_id = v.venta_factura_id AND COALESCE(d.tabla_estado_registro_id, 1) <> 6
             LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = d.iva_alicuota_id AND a.empresa_id = v.empresa_id
             WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados) AND ct.comprobante_fiscal_id > 0
             GROUP BY d.venta_factura_id, a.porcentaje, COALESCE(a.es_gravado, 1)", 'iss', $empresa_id, $desde, $hasta);
    } elseif ($libro === 'COMPRAS') {
        $filas = ivaFilas($conexion,
            "SELECT f.factura_proveedor_id AS id, f.comprobante_id, COALESCE(f.f_contabilidad, f.f_emision) AS fecha, ct.codigo AS tipo,
                    ct.comprobante_tipo, ct.letra, cf.codigo AS codigo_arca, f.comprobante_pv AS pv, f.comprobante_nro AS numero,
                    COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad, e.cuit, IF(ct.signo = '-', -1, 1) AS signo, f.no_gravado, f.exento, f.total
             FROM gestion__facturas_proveedores f
             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
             LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
             LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
             WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5
               AND ct.comprobante_fiscal_id > 0
             ORDER BY fecha, f.comprobante_pv, f.comprobante_nro", 'iss', $empresa_id, $desde, $hasta);
        $lineas = ivaFilas($conexion,
            "SELECT d.factura_proveedor_id AS id, a.porcentaje, COALESCE(a.es_gravado, 1) AS es_gravado,
                    SUM(IF(COALESCE(a.es_gravado, 1) = 1, d.neto_gravado, 0)) AS neto, SUM(d.iva_importe) AS iva,
                    SUM(d.no_gravado + IF(a.es_no_gravado = 1 AND d.no_gravado = 0, d.neto_gravado, 0)) AS no_gravado,
                    SUM(d.exento + IF(a.es_exento = 1 AND d.exento = 0, d.neto_gravado, 0)) AS exento
             FROM gestion__facturas_proveedores f
             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
             JOIN gestion__facturas_proveedores_detalle d ON d.factura_proveedor_id = f.factura_proveedor_id
             LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = d.iva_alicuota_id AND a.empresa_id = f.empresa_id
             WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5
               AND ct.comprobante_fiscal_id > 0
             GROUP BY d.factura_proveedor_id, a.porcentaje, COALESCE(a.es_gravado, 1)", 'iss', $empresa_id, $desde, $hasta);
    } else {
        throw new Exception('Libro inválido (VENTAS / COMPRAS).');
    }

    // Percepciones / impuestos adicionales de los comprobantes del libro
    $comps = array_values(array_filter(array_map(function ($f) { return intval($f['comprobante_id']); }, $filas)));
    $percs = [];
    foreach (array_chunk($comps, 500) as $lote) {
        $in = implode(',', array_fill(0, count($lote), '?'));
        $percs = array_merge($percs, ivaFilas($conexion,
            "SELECT ci.comprobante_id, ci.impuesto_tipo_id, COALESCE(ci.jurisdiccion_id, 0) AS jurisdiccion_id, it.impuesto_tipo,
                    j.jurisdiccion_nombre, SUM(ci.importe) AS importe
             FROM gestion__comprobantes_impuestos ci
             LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = ci.impuesto_tipo_id
             LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = ci.jurisdiccion_id
             WHERE ci.tabla_estado_registro_id = 1 AND ci.comprobante_id IN ($in)
             GROUP BY ci.comprobante_id, ci.impuesto_tipo_id, COALESCE(ci.jurisdiccion_id, 0), it.impuesto_tipo, j.jurisdiccion_nombre",
            str_repeat('i', count($lote)), ...$lote));
    }

    $cols_alic = [];
    $cols_perc = [];
    $por_id = [];
    foreach ($lineas as $l) {
        // No gravado y exento salen de las líneas, igual que en la posición (ivaVentasAlicuotas / ivaComprasAlicuotas)
        $por_id[$l['id']]['no_gravado'] = ($por_id[$l['id']]['no_gravado'] ?? 0) + floatval($l['no_gravado']);
        $por_id[$l['id']]['exento'] = ($por_id[$l['id']]['exento'] ?? 0) + floatval($l['exento']);
        if (intval($l['es_gravado']) !== 1 || $l['porcentaje'] === null) continue;
        $p = rtrim(rtrim(number_format(floatval($l['porcentaje']), 2, '.', ''), '0'), '.');
        $cols_alic[$p] = floatval($l['porcentaje']);
        $por_id[$l['id']]["neto_$p"] = ($por_id[$l['id']]["neto_$p"] ?? 0) + floatval($l['neto']);
        $por_id[$l['id']]["iva_$p"] = ($por_id[$l['id']]["iva_$p"] ?? 0) + floatval($l['iva']);
    }
    $perc_por_comp = [];
    foreach ($percs as $p) {
        $k = 'perc_' . intval($p['impuesto_tipo_id']) . '_' . intval($p['jurisdiccion_id']);
        $cols_perc[$k] = trim(($p['impuesto_tipo'] ?: 'Impuesto ' . $p['impuesto_tipo_id'])
                              . (intval($p['jurisdiccion_id']) > 0 && $p['jurisdiccion_nombre'] ? ' - ' . $p['jurisdiccion_nombre'] : ''));
        $perc_por_comp[intval($p['comprobante_id'])][$k] = ($perc_por_comp[intval($p['comprobante_id'])][$k] ?? 0) + floatval($p['importe']);
    }
    arsort($cols_alic);
    asort($cols_perc);

    $columnas = [];
    foreach ($cols_alic as $p => $v) $columnas[] = ['clave' => "neto_$p", 'titulo' => "Neto $p %", 'grupo' => 'neto'];
    foreach ($cols_alic as $p => $v) $columnas[] = ['clave' => "iva_$p", 'titulo' => "IVA $p %", 'grupo' => 'iva'];
    $columnas[] = ['clave' => 'no_gravado', 'titulo' => 'No gravado', 'grupo' => 'otros'];
    $columnas[] = ['clave' => 'exento', 'titulo' => 'Exento', 'grupo' => 'otros'];
    foreach ($cols_perc as $k => $t) $columnas[] = ['clave' => $k, 'titulo' => $t, 'grupo' => 'percepcion'];
    $columnas[] = ['clave' => 'total', 'titulo' => 'Total', 'grupo' => 'total'];
    $columnas[] = ['clave' => 'control', 'titulo' => 'Control', 'grupo' => 'control'];

    $totales = array_fill_keys(array_column($columnas, 'clave'), 0.0);
    $salida = [];
    foreach ($filas as $f) {
        $s = intval($f['signo']);
        $val = array_fill_keys(array_column($columnas, 'clave'), 0.0);
        foreach ($por_id[$f['id']] ?? [] as $k => $v) $val[$k] = ivaR($s * $v);
        foreach ($perc_por_comp[intval($f['comprobante_id'])] ?? [] as $k => $v) $val[$k] = ivaR($s * $v);
        $val['total'] = ivaR($s * floatval($f['total']));
        $suma = 0.0;
        foreach ($val as $k => $v) if ($k !== 'total' && $k !== 'control') $suma += $v;
        $val['control'] = ivaR($val['total'] - $suma);
        foreach ($val as $k => $v) $totales[$k] += $v;
        $salida[] = ['fecha' => $f['fecha'], 'tipo' => $f['tipo'], 'comprobante_tipo' => $f['comprobante_tipo'], 'letra' => $f['letra'],
                     'codigo_arca' => $f['codigo_arca'], 'pv' => intval($f['pv']), 'numero' => intval($f['numero']),
                     'entidad' => $f['entidad'], 'cuit' => $f['cuit'], 'valores' => $val];
    }
    foreach ($totales as $k => $v) $totales[$k] = ivaR($v);
    return ['libro' => $libro, 'periodo' => $periodo, 'columnas' => $columnas, 'filas' => $salida, 'totales' => $totales];
}

// ------------------------------------------------------------------ liquidación
/** Líneas del asiento de liquidación (importe con signo: > 0 Debe, < 0 Haber), agrupadas por cuenta. */
function ivaLineasAsiento($conexion, $empresa_id, $datos, $calculo)
{
    $lineas = [];
    $faltan = [];
    $sumar = function ($cuenta, $importe, $texto) use (&$lineas, &$faltan) {
        if (abs($importe) < 0.005) return;
        if (intval($cuenta) <= 0) { $faltan[] = $texto; return; }
        $lineas[$cuenta] = ($lineas[$cuenta] ?? ['importe' => 0.0, 'descripcion' => $texto]);
        $lineas[$cuenta]['importe'] += $importe;
    };
    $cta_debito = emp_cuenta_concepto_id($conexion, $empresa_id, 'IVA_DEBITO');
    foreach ($datos['ventas'] as $v) {
        $sumar($v['cuenta_id'] ?: $cta_debito, floatval($v['iva']), 'IVA débito fiscal ' . $v['iva_alicuota']);
    }
    foreach ($datos['compras'] as $c) {
        if (intval($c['computable']) !== 1) continue;
        $sumar($c['cuenta_id'], -floatval($c['iva']), 'IVA crédito fiscal ' . $c['iva_alicuota'] . ' (cuenta de compra de la alícuota)');
    }
    foreach ($datos['percepciones'] as $p) {
        if (intval($p['impuesto_tipo_id']) !== IVA_IMP_PERCEPCION) continue;
        $sumar($p['cuenta_id'], -floatval($p['importe']), 'Percepciones de IVA sufridas (cuenta de la configuración de impuestos)');
    }
    foreach ($datos['retenciones'] as $r) {
        if (intval($r['impuesto_tipo_id']) !== IVA_IMP_RETENCION) continue;
        $sumar($r['cuenta_id'], -floatval($r['importe']), 'Retenciones de IVA sufridas (cuenta del medio de cobro ' . $r['medio_pago'] . ')');
    }
    $cta_favor = emp_cuenta_concepto_id($conexion, $empresa_id, 'IVA_SALDO_FAVOR');
    $sumar($cta_favor, -$calculo['saldo_favor_anterior'], 'IVA saldo a favor del período anterior (concepto IVA_SALDO_FAVOR)');
    $sumar(emp_cuenta_concepto_id($conexion, $empresa_id, 'IVA_A_PAGAR'), -$calculo['saldo_a_pagar'], 'IVA a pagar (concepto IVA_A_PAGAR)');
    $sumar($cta_favor, $calculo['saldo_a_favor'], 'IVA saldo a favor (concepto IVA_SALDO_FAVOR)');
    if ($faltan) {
        throw new Exception('Falta configurar la cuenta contable de: ' . implode('; ', array_unique($faltan)));
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

/**
 * Liquida la posición del período: I_LIQ si da a pagar, asiento de liquidación y fila en gestion__iva_posiciones.
 * Una liquidación vigente por período, y no puede haber liquidaciones vigentes posteriores (arrastran el saldo).
 */
function ivaLiquidar($conexion, $empresa_id, $usuario_id, $data)
{
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    $periodo = ivaValidarPeriodo($data['periodo'] ?? '');
    [, $hasta] = ivaRango($periodo);
    if ($periodo > date('Y-m')) throw new Exception('No se puede liquidar un período futuro.');
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    error_log("=== INICIO ivaLiquidar empresa $empresa_id período $periodo ===");
    if ($sucursal_id <= 0 || !ivaFila($conexion, "SELECT 1 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", 'ii', $sucursal_id, $empresa_id)) {
        throw new Exception('Elija la sucursal de la liquidación.');
    }

    $lock = 'iva_posicion_' . $empresa_id;
    $l = ivaFila($conexion, "SELECT GET_LOCK(?, 10) AS ok", 's', $lock);
    if (!$l || intval($l['ok']) !== 1) throw new Exception('Otra liquidación de IVA está en curso. Intente de nuevo.');
    mysqli_begin_transaction($conexion);
    try {
        if (ivaPosicionVigente($conexion, $empresa_id, $periodo)) {
            throw new Exception("El período $periodo ya está liquidado: anule la liquidación para volver a hacerla.");
        }
        $post = ivaFila($conexion, "SELECT periodo FROM gestion__iva_posiciones WHERE empresa_id = ? AND periodo > ? AND tabla_estado_registro_id = ? LIMIT 1",
                        'isi', $empresa_id, $periodo, IVA_POS_VIGENTE);
        if ($post) throw new Exception("Hay una liquidación posterior ({$post['periodo']}) que arrastra el saldo: anúlela primero.");

        $datos = [
            'ventas' => ivaVentasAlicuotas($conexion, $empresa_id, $periodo),
            'compras' => ivaComprasAlicuotas($conexion, $empresa_id, $periodo),
            'percepciones' => ivaPercepcionesSufridas($conexion, $empresa_id, $periodo),
            'retenciones' => ivaRetencionesSufridas($conexion, $empresa_id, $periodo),
        ];
        $calc = ivaCalcularPosicion($conexion, $empresa_id, $periodo, $datos);
        if (abs($calc['debito_fiscal']) + abs($calc['credito_fiscal']) + abs($calc['percepciones_iva']) + abs($calc['retenciones_iva'])
            + abs($calc['saldo_favor_anterior']) < 0.01) {
            throw new Exception("El período $periodo no tiene movimientos de IVA.");
        }
        $detalles = ivaLineasAsiento($conexion, $empresa_id, $datos, $calc);

        $comprobante_id = null;
        $numero = null;
        if ($calc['saldo_a_pagar'] > 0) {
            if ($entidad_id <= 0 || !ivaFila($conexion, "SELECT 1 FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', $entidad_id, $empresa_id)) {
                throw new Exception('Da a pagar: elija el organismo recaudador (entidad proveedora de la empresa, p. ej. ARCA).');
            }
            $res = registrarObligacionImpositiva($conexion, [
                'en_transaccion' => true,
                'sin_asiento' => true,
                'entidad_id' => $entidad_id,
                'sucursal_id' => $sucursal_id,
                'f_emision' => $hasta,
                'f_vto' => trim((string)($data['f_vto'] ?? '')) ?: date('Y-m-d', strtotime('+10 days')),
                'periodo_fiscal' => $periodo,
                'descripcion' => 'IVA - Posición mensual ' . $periodo,
                'nro_ddjj' => trim((string)($data['nro_ddjj'] ?? '')),
                'importe_total' => $calc['saldo_a_pagar'],
                'cont_cuenta_id' => emp_cuenta_concepto_id($conexion, $empresa_id, 'IVA_A_PAGAR'),
                'impuesto_tipo_id' => IVA_IMP_POSICION,
            ], $empresa_id, $usuario_id);
            $comprobante_id = intval($res['comprobante_id']);
            $numero = $res['numero'];
        }

        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id' => $empresa_id,
            'sucursal_id' => $sucursal_id,
            'comprobante_id' => $comprobante_id ?: 0,
            'sin_comprobante' => !$comprobante_id,
            'entidad_id' => $entidad_id,
            'f_asiento' => $hasta,
            'descripcion' => 'Liquidación IVA ' . $periodo . ($numero ? ' - I_LIQ ' . $numero : ' - saldo a favor'),
            'usuario_id' => $usuario_id,
        ], $detalles);

        $obs = trim((string)($data['observaciones'] ?? ''));
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__iva_posiciones
            (empresa_id, periodo, debito_fiscal, credito_fiscal, percepciones_iva, retenciones_iva, saldo_favor_anterior,
             saldo_a_pagar, saldo_a_favor, comprobante_id, cont_asiento_id, observaciones, tabla_estado_registro_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $estado = IVA_POS_VIGENTE;
        mysqli_stmt_bind_param($stmt, "isdddddddiisii", $empresa_id, $periodo, $calc['debito_fiscal'], $calc['credito_fiscal'],
                               $calc['percepciones_iva'], $calc['retenciones_iva'], $calc['saldo_favor_anterior'], $calc['saldo_a_pagar'],
                               $calc['saldo_a_favor'], $comprobante_id, $asiento_id, $obs, $estado, $usuario_id);
        mysqli_stmt_execute($stmt);
        $posicion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        // El período de IVA queda cerrado (Cierres Fiscales) hasta anular la liquidación
        pfCerrarPorLiquidacion($conexion, $empresa_id, $usuario_id, IVA_IMP_POSICION, 0, $periodo, 'IVA_POSICION', $posicion_id,
                               'Liquidación de IVA ' . $periodo);

        mysqli_commit($conexion);
        ivaFila($conexion, "SELECT RELEASE_LOCK(?) AS ok", 's', $lock);
        $msg = $calc['saldo_a_pagar'] > 0
            ? "Período $periodo liquidado: a pagar $" . fmt_numero($calc['saldo_a_pagar'], 2) . " (I_LIQ $numero). Se paga desde Órdenes de Pago."
            : "Período $periodo liquidado: saldo a favor $" . fmt_numero($calc['saldo_a_favor'], 2) . " (pasa al período siguiente).";
        return $calc + ['iva_posicion_id' => $posicion_id, 'comprobante_id' => $comprobante_id, 'numero' => $numero,
                        'cont_asiento_id' => $asiento_id, 'message' => $msg];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        ivaFila($conexion, "SELECT RELEASE_LOCK(?) AS ok", 's', $lock);
        error_log('ERROR ivaLiquidar: ' . $e->getMessage());
        throw $e;
    }
}

/** Anula la liquidación vigente del período (la última, y con la I_LIQ sin pagar): I_LIQ y asiento anulados. */
function ivaAnularLiquidacion($conexion, $empresa_id, $usuario_id, $data)
{
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    $periodo = ivaValidarPeriodo($data['periodo'] ?? '');
    $motivo = trim((string)($data['motivo'] ?? ''));
    if ($motivo === '') throw new Exception('Ingrese el motivo de la anulación.');

    mysqli_begin_transaction($conexion);
    try {
        $pos = ivaFila($conexion, "SELECT * FROM gestion__iva_posiciones WHERE empresa_id = ? AND periodo = ? AND tabla_estado_registro_id = ?
                                   ORDER BY iva_posicion_id DESC LIMIT 1 FOR UPDATE", 'isi', $empresa_id, $periodo, IVA_POS_VIGENTE);
        if (!$pos) throw new Exception("El período $periodo no tiene una liquidación vigente.");
        $post = ivaFila($conexion, "SELECT periodo FROM gestion__iva_posiciones WHERE empresa_id = ? AND periodo > ? AND tabla_estado_registro_id = ? LIMIT 1",
                        'isi', $empresa_id, $periodo, IVA_POS_VIGENTE);
        if ($post) throw new Exception("Hay una liquidación posterior ({$post['periodo']}) que usa este saldo: anúlela primero.");
        cont_periodo_exigir_asiento($conexion, $empresa_id, intval($pos['cont_asiento_id'] ?? 0), 'anular la liquidación');

        $comp_id = intval($pos['comprobante_id'] ?? 0);
        if ($comp_id > 0) {
            $c = ivaFila($conexion, "SELECT importe_total, importe_pendiente, tabla_estado_registro_id FROM gestion__comprobantes
                                     WHERE comprobante_id = ? AND empresa_id = ? FOR UPDATE", 'ii', $comp_id, $empresa_id);
            $imp = ivaFila($conexion, "SELECT COUNT(*) AS n FROM gestion__comprobantes_imputaciones WHERE comp_destino_id = ? AND tabla_estado_registro_id = 5",
                           'i', $comp_id);
            if (!$c || abs(floatval($c['importe_pendiente']) - floatval($c['importe_total'])) >= 0.01 || intval($imp['n']) > 0) {
                throw new Exception('La liquidación (I_LIQ) ya tiene pagos: anule antes la orden de pago que la cancela.');
            }
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET tabla_estado_registro_id = 38, importe_pendiente = 0 WHERE comprobante_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $comp_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__obligaciones_pago SET tabla_estado_registro_id = 38, motivo_anulacion = ?,
                                                      usuario_modificacion_id = ? WHERE comprobante_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, "siii", $motivo, $usuario_id, $comp_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        if (!empty($pos['cont_asiento_id'])) {
            $asiento = intval($pos['cont_asiento_id']);
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                                               WHERE cont_asiento_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $asiento, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $estado = IVA_POS_ANULADA;
        $pid = intval($pos['iva_posicion_id']);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__iva_posiciones SET tabla_estado_registro_id = ?, motivo_anulacion = ?,
                                                  usuario_modificacion_id = ? WHERE iva_posicion_id = ?");
        mysqli_stmt_bind_param($stmt, "isii", $estado, $motivo, $usuario_id, $pid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        pfReabrirPorOrigen($conexion, $empresa_id, $usuario_id, 'IVA_POSICION', $pid, 'Liquidación anulada: ' . $motivo);
        mysqli_commit($conexion);
        return ['iva_posicion_id' => $pid, 'message' => "Liquidación de IVA $periodo anulada" . ($comp_id ? ' (I_LIQ y asiento anulados).' : ' (asiento anulado).')];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR ivaAnularLiquidacion: ' . $e->getMessage());
        throw $e;
    }
}

/** Para otros procesos: el período de IVA de esa fecha ya está liquidado (null si no). */
function ivaPeriodoLiquidado($conexion, $empresa_id, $fecha)
{
    $r = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__iva_posiciones'");
    if (!$r || mysqli_num_rows($r) === 0 || !$fecha) return null;
    $periodo = substr((string)$fecha, 0, 7);
    $f = ivaFila($conexion, "SELECT periodo FROM gestion__iva_posiciones WHERE empresa_id = ? AND periodo = ? AND tabla_estado_registro_id = ? LIMIT 1",
                 'isi', intval($empresa_id), $periodo, IVA_POS_VIGENTE);
    return $f ? $f['periodo'] : null;
}

function ivaSucursales($conexion, $empresa_id)
{
    return ivaFilas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales
                                WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id", 'i', intval($empresa_id));
}

function ivaOrganismos($conexion, $empresa_id)
{
    return ivaFilas($conexion, "SELECT entidad_id, entidad_nombre FROM gestion__entidades
                                WHERE empresa_id = ? AND " . ent_sql_tiene_rol('gestion__entidades.entidad_id', 'PRO') . " AND tabla_estado_registro_id = 1
                                ORDER BY (entidad_nombre LIKE '%ARCA%' OR entidad_nombre LIKE '%AFIP%') DESC, entidad_nombre", 'i', intval($empresa_id));
}
