<?php
/**
 * Cierres de períodos fiscales y cuadre contable de percepciones y retenciones (etapa 6 de
 * _local/docs/gestion/10_retenciones_percepciones_relevamiento.md). Script: _local/sql/mantenimiento/20261016_cierres_fiscales_01_estructura.sql.
 *
 * Un cierre (gestion__impuestos_periodos_cierres) bloquea un período AAAA-MM de una obligación de la empresa
 * (impuesto + jurisdicción). Mientras esté cerrado no se confirma ni se anula ningún comprobante que la afecte:
 *   Venta fiscal            IVA (7) e IIBB (9, cualquier jurisdicción: es base imponible), por f_emision
 *     + percepción IIBB efectuada (6) de la jurisdicción y percepción de IVA efectuada (22)
 *   Compra fiscal           IVA (7), por f_contabilidad
 *     + percepción sufrida: IIBB (1) -> IIBB de la jurisdicción, IVA (2) -> IVA, Ganancias (3) -> Ganancias (8)
 *   Cobranza                retención sufrida: IIBB (4) -> IIBB de la jurisdicción, IVA (5) -> IVA, Ganancias (21) -> 8,
 *                           en su período de cómputo (retencion_periodo_computo; sin dato, el mes del recibo)
 *   Orden de pago           retención practicada (14-17) de su impuesto / jurisdicción, por f_emision
 * Lo usan ventas_facturas, facturas_proveedores, cobranzas y pagos (pfExigirDocumentoAbierto). Los cierres los carga
 * el usuario (MANUAL), la liquidación de IVA (IVA_POSICION) o la de Ingresos Brutos por jurisdicción (IIBB_POSICION,
 * iibb_liquidacion_model.php): estas dos se reabren solo anulando la liquidación. La liquidación de
 * percepciones IIBB no cierra sola porque puede hacerse varias veces en el mes (quincenal): cada percepción
 * declarada ya queda protegida por su liquidacion_comprobante_id.
 */
require_once __DIR__ . '/../../core/empresa_config.php';

const PF_TABLA_CIERRES = 439;
const PF_CERRADO = 5;
const PF_REABIERTO = 38;
const PF_JUR_CUALQUIERA = -1;
const PF_ESTADOS_VENTAS = [5, 12, 13, 15, 39, 40];   // los mismos que IVA_ESTADOS_VENTAS (iva_posiciones_model.php)
// Obligaciones que se pueden cerrar => lleva jurisdicción
const PF_OBLIGACIONES = [7 => false, 9 => true, 8 => false, 6 => true, 22 => false, 14 => false, 15 => false, 16 => true, 17 => false];
// Componente del comprobante (gestion__impuestos_tipos) => obligación que lo declara
const PF_COMPONENTE_OBLIGACION = [1 => 9, 2 => 7, 3 => 8, 4 => 9, 5 => 7, 21 => 8, 6 => 6, 22 => 22, 14 => 14, 15 => 15, 16 => 16, 17 => 17];
const PF_ORIGENES = ['MANUAL' => 'Cierre manual', 'IVA_POSICION' => 'Liquidación de IVA', 'IIBB_POSICION' => 'Liquidación de IIBB'];
// Orígenes de cierre que solo se reabren anulando su liquidación
const PF_ORIGENES_LIQUIDACION = ['IVA_POSICION', 'IIBB_POSICION'];

function pfFilas($conexion, $sql, $tipos = '', ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('Cierres fiscales: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function pfFila($conexion, $sql, $tipos = '', ...$params)
{
    $rows = pfFilas($conexion, $sql, $tipos, ...$params);
    return $rows[0] ?? null;
}

function pfR($v) { return round(floatval($v), 2); }

function pfValidarPeriodo($periodo)
{
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$periodo)) throw new Exception('Período inválido (AAAA-MM).');
    return (string)$periodo;
}

/** La tabla existe (los modelos que la consultan no fallan si el script no corrió). */
function pfDisponible($conexion)
{
    static $ok = null;
    if ($ok === null) {
        $r = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__impuestos_periodos_cierres'");
        $ok = $r && mysqli_num_rows($r) > 0;
    }
    return $ok;
}

/** Obligaciones que se pueden cerrar, con su nombre y si llevan jurisdicción. */
function pfObligaciones($conexion)
{
    $ids = array_keys(PF_OBLIGACIONES);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $nombres = [];
    foreach (pfFilas($conexion, "SELECT impuesto_tipo_id, impuesto_tipo FROM gestion__impuestos_tipos WHERE impuesto_tipo_id IN ($in)",
                     str_repeat('i', count($ids)), ...$ids) as $f) {
        $nombres[intval($f['impuesto_tipo_id'])] = $f['impuesto_tipo'];
    }
    $out = [];
    foreach (PF_OBLIGACIONES as $id => $jur) {
        if (isset($nombres[$id])) $out[] = ['impuesto_tipo_id' => $id, 'impuesto_tipo' => $nombres[$id], 'requiere_jurisdiccion' => $jur];
    }
    return $out;
}

// ------------------------------------------------------------------ bloqueo
/** Agrega la obligación de un componente (impuesto del comprobante) a la lista. */
function pfAgregarComponente(array &$items, $impuesto_tipo_id, $jurisdiccion_id)
{
    $obl = PF_COMPONENTE_OBLIGACION[intval($impuesto_tipo_id)] ?? null;
    if ($obl === null) return;
    $jur = PF_OBLIGACIONES[$obl] ? intval($jurisdiccion_id) : 0;
    $items[$obl . '|' . $jur] = [$obl, $jur];
}

/** Obligaciones que afecta un comprobante y su fecha fiscal. $tipo: VENTA / COMPRA / COBRANZA / PAGO. */
function pfObligacionesDocumento($conexion, $empresa_id, $tipo, $registro_id)
{
    $empresa_id = intval($empresa_id);
    $registro_id = intval($registro_id);
    $items = [];
    $fecha = null;
    $comp = 0;
    switch ($tipo) {
        case 'VENTA':
            $d = pfFila($conexion, "SELECT v.f_emision AS fecha, v.comprobante_id, ct.comprobante_fiscal_id
                                    FROM gestion__ventas_facturas v
                                    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
                                    WHERE v.venta_factura_id = ? AND v.empresa_id = ?", 'ii', $registro_id, $empresa_id);
            if (!$d) break;
            $fecha = $d['fecha'];
            $comp = intval($d['comprobante_id']);
            if (intval($d['comprobante_fiscal_id']) > 0) {
                $items['7|0'] = [7, 0];
                $items['9|-1'] = [9, PF_JUR_CUALQUIERA];
            }
            break;
        case 'COMPRA':
            $d = pfFila($conexion, "SELECT COALESCE(f.f_contabilidad, f.f_emision) AS fecha, f.comprobante_id, ct.comprobante_fiscal_id
                                    FROM gestion__facturas_proveedores f
                                    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
                                    WHERE f.factura_proveedor_id = ? AND f.empresa_id = ?", 'ii', $registro_id, $empresa_id);
            if (!$d) break;
            $fecha = $d['fecha'];
            $comp = intval($d['comprobante_id']);
            if (intval($d['comprobante_fiscal_id']) > 0) $items['7|0'] = [7, 0];
            break;
        case 'COBRANZA':
            $d = pfFila($conexion, "SELECT COALESCE(f_contabilidad, f_emision) AS fecha FROM gestion__cobranzas
                                    WHERE cobranza_id = ? AND empresa_id = ?", 'ii', $registro_id, $empresa_id);
            if (!$d) break;
            $fecha = $d['fecha'];
            // Cada retención se controla en su período de cómputo
            $grupos = [];
            foreach (pfFilas($conexion, "SELECT DISTINCT COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id) AS impuesto_tipo_id,
                                                COALESCE(cm.jurisdiccion_id, 0) AS jurisdiccion_id, cm.retencion_periodo_computo AS periodo
                                         FROM gestion__cobranzas_medios cm
                                         JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id AND mp.medio_pago_tipo = 'RETENCION'
                                         WHERE cm.cobranza_id = ?", 'i', $registro_id) as $f) {
                $per = $f['periodo'] ?: substr((string)$fecha, 0, 7);
                $grupos[$per] = $grupos[$per] ?? [];
                pfAgregarComponente($grupos[$per], $f['impuesto_tipo_id'], $f['jurisdiccion_id']);
            }
            $out = [];
            foreach ($grupos as $per => $its) $out[] = ['fecha' => $per . '-01', 'items' => array_values($its)];
            return ['fecha' => $fecha, 'items' => [], 'grupos' => $out];
        case 'PAGO':
            $d = pfFila($conexion, "SELECT f_emision AS fecha FROM gestion__pagos WHERE pago_id = ? AND empresa_id = ?", 'ii', $registro_id, $empresa_id);
            if (!$d) break;
            $fecha = $d['fecha'];
            foreach (pfFilas($conexion, "SELECT DISTINCT impuesto_tipo_id, COALESCE(jurisdiccion_id, 0) AS jurisdiccion_id
                                         FROM gestion__pagos_retenciones
                                         WHERE pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id IN (3, 5)",
                                 'ii', $registro_id, $empresa_id) as $f) {
                pfAgregarComponente($items, $f['impuesto_tipo_id'], $f['jurisdiccion_id']);
            }
            break;
        default:
            throw new Exception("Cierres fiscales: tipo de comprobante desconocido ($tipo).");
    }
    // Percepciones del comprobante (ventas: efectuadas; compras: sufridas)
    if ($comp > 0) {
        foreach (pfFilas($conexion, "SELECT DISTINCT impuesto_tipo_id, COALESCE(jurisdiccion_id, 0) AS jurisdiccion_id
                                     FROM gestion__comprobantes_impuestos WHERE comprobante_id = ? AND tabla_estado_registro_id = 1",
                             'i', $comp) as $f) {
            pfAgregarComponente($items, $f['impuesto_tipo_id'], $f['jurisdiccion_id']);
        }
    }
    return ['fecha' => $fecha, 'items' => array_values($items)];
}

/** Cierres activos que bloquean esas obligaciones en el período de la fecha. */
function pfCierresQueBloquean($conexion, $empresa_id, $fecha, array $items)
{
    if (!$items || !$fecha || !pfDisponible($conexion)) return [];
    $periodo = substr((string)$fecha, 0, 7);
    $cond = [];
    $tipos = 'is';
    $params = [intval($empresa_id), $periodo];
    foreach ($items as [$imp, $jur]) {
        if (intval($jur) === PF_JUR_CUALQUIERA) {
            $cond[] = 'c.impuesto_tipo_id = ?';
            $tipos .= 'i';
            $params[] = intval($imp);
        } else {
            $cond[] = '(c.impuesto_tipo_id = ? AND c.jurisdiccion_id = ?)';
            $tipos .= 'ii';
            $params[] = intval($imp);
            $params[] = intval($jur);
        }
    }
    return pfFilas($conexion,
        "SELECT c.cierre_id, c.impuesto_tipo_id, c.jurisdiccion_id, c.periodo, c.origen, it.impuesto_tipo, j.jurisdiccion_nombre
         FROM gestion__impuestos_periodos_cierres c
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = c.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = c.jurisdiccion_id
         WHERE c.empresa_id = ? AND c.periodo = ? AND c.tabla_estado_registro_id = " . PF_CERRADO . "
           AND (" . implode(' OR ', $cond) . ")
         ORDER BY c.impuesto_tipo_id, c.jurisdiccion_id", $tipos, ...$params);
}

/** Texto del bloqueo para el usuario (null si el período está abierto). */
function pfMensajeBloqueo(array $cierres, $que)
{
    if (!$cierres) return null;
    $obl = [];
    $origenes = [];
    foreach ($cierres as $c) {
        $obl[] = trim($c['impuesto_tipo'] . ($c['jurisdiccion_nombre'] ? ' ' . $c['jurisdiccion_nombre'] : '')) . ' (' . (PF_ORIGENES[$c['origen']] ?? $c['origen']) . ')';
        $origenes[$c['origen']] = true;
    }
    $origenes = array_keys($origenes);
    if ($origenes === ['IVA_POSICION']) $donde = 'anule la liquidación en IVA - Posición Mensual';
    elseif ($origenes === ['IIBB_POSICION']) $donde = 'anule la liquidación en Control Fiscal > Ingresos Brutos';
    else $donde = 'reábralo en Control Fiscal > Períodos cerrados (los de una liquidación, anulándola)';
    return "No se puede $que: el período {$cierres[0]['periodo']} está cerrado para " . implode(', ', array_unique($obl)) . ". Para modificarlo, $donde.";
}

/** Lanza Exception si el comprobante afecta un período cerrado. $que: "confirmar la factura", "anular la cobranza"... */
function pfExigirDocumentoAbierto($conexion, $empresa_id, $tipo, $registro_id, $que)
{
    if (!pfDisponible($conexion)) return;
    $d = pfObligacionesDocumento($conexion, $empresa_id, $tipo, $registro_id);
    foreach ($d['grupos'] ?? [['fecha' => $d['fecha'], 'items' => $d['items']]] as $g) {
        $msg = pfMensajeBloqueo(pfCierresQueBloquean($conexion, $empresa_id, $g['fecha'], $g['items']), $que);
        if ($msg !== null) throw new Exception($msg);
    }
}

// ------------------------------------------------------------------ cerrar / reabrir
/**
 * Cierra un período. Lo usan la página (MANUAL, valida que no haya otro cierre manual activo) y la
 * liquidación de IVA (dentro de su transacción).
 */
function pfCerrar($conexion, $empresa_id, $usuario_id, $data, $origen = 'MANUAL', $referencia_id = null)
{
    $empresa_id = intval($empresa_id);
    $imp = intval($data['impuesto_tipo_id'] ?? 0);
    $jur = intval($data['jurisdiccion_id'] ?? 0);
    $periodo = pfValidarPeriodo($data['periodo'] ?? '');
    $obs = mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 255);
    if (!array_key_exists($imp, PF_OBLIGACIONES)) throw new Exception('Elija la obligación a cerrar.');
    if (!isset(PF_ORIGENES[$origen])) throw new Exception('Origen de cierre inválido.');
    if (PF_OBLIGACIONES[$imp]) {
        if ($jur <= 0 || !pfFila($conexion, "SELECT 1 FROM gestion__jurisdicciones WHERE jurisdiccion_id = ?", 'i', $jur)) {
            throw new Exception('Esa obligación se cierra por jurisdicción: elija la jurisdicción.');
        }
        // Ingresos Brutos propio: si la empresa cargó sus inscripciones, solo las jurisdicciones inscriptas en el período
        if ($imp === 9 && !pfInscriptaEnPeriodo($conexion, $empresa_id, $jur, $periodo)) {
            throw new Exception("La empresa no está inscripta en Ingresos Brutos en esa jurisdicción en $periodo (Control Fiscal > Inscripciones IIBB).");
        }
    } else {
        $jur = 0;
    }
    if ($origen === 'MANUAL') {
        if ($periodo > date('Y-m')) throw new Exception('No se puede cerrar un período futuro.');
        if (pfFila($conexion, "SELECT 1 FROM gestion__impuestos_periodos_cierres
                               WHERE empresa_id = ? AND impuesto_tipo_id = ? AND jurisdiccion_id = ? AND periodo = ?
                                 AND origen = 'MANUAL' AND tabla_estado_registro_id = ?", 'iiisi', $empresa_id, $imp, $jur, $periodo, PF_CERRADO)) {
            throw new Exception("El período $periodo ya está cerrado.");
        }
    }
    $estado = PF_CERRADO;
    $uid = intval($usuario_id);
    $ref = $referencia_id !== null ? intval($referencia_id) : null;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__impuestos_periodos_cierres
        (empresa_id, impuesto_tipo_id, jurisdiccion_id, periodo, origen, referencia_id, observaciones, usuario_id, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iiissisii', $empresa_id, $imp, $jur, $periodo, $origen, $ref, $obs, $uid, $estado);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    error_log("=== pfCerrar empresa $empresa_id impuesto $imp jur $jur período $periodo origen $origen: cierre $id ===");
    return $id;
}

/**
 * La empresa está inscripta en IIBB en la jurisdicción durante el período. Sin inscripciones cargadas (o sin la
 * tabla) no se valida: devuelve true.
 */
function pfInscriptaEnPeriodo($conexion, $empresa_id, $jurisdiccion_id, $periodo)
{
    $r = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__empresas_iibb_inscripciones'");
    if (!$r || mysqli_num_rows($r) === 0) return true;
    if (!pfFila($conexion, "SELECT 1 FROM gestion__empresas_iibb_inscripciones WHERE empresa_id = ? AND tabla_estado_registro_id = 1 LIMIT 1",
                'i', intval($empresa_id))) {
        return true;
    }
    $desde = $periodo . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    return (bool) pfFila($conexion, "SELECT 1 FROM gestion__empresas_iibb_inscripciones
                                     WHERE empresa_id = ? AND jurisdiccion_id = ? AND tabla_estado_registro_id = 1
                                       AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?) LIMIT 1",
                         'iiss', intval($empresa_id), intval($jurisdiccion_id), $hasta, $desde);
}

/** Reabre un cierre. Los de la liquidación de IVA solo se reabren anulándola ($desde_liquidacion = true). */
function pfReabrir($conexion, $empresa_id, $usuario_id, $cierre_id, $motivo, $desde_liquidacion = false)
{
    $empresa_id = intval($empresa_id);
    $cierre_id = intval($cierre_id);
    $motivo = mb_substr(trim((string)$motivo), 0, 255);
    if ($motivo === '') throw new Exception('Ingrese el motivo de la reapertura.');
    $c = pfFila($conexion, "SELECT * FROM gestion__impuestos_periodos_cierres WHERE cierre_id = ? AND empresa_id = ? FOR UPDATE",
                'ii', $cierre_id, $empresa_id);
    if (!$c) throw new Exception('Cierre no encontrado.');
    if (intval($c['tabla_estado_registro_id']) !== PF_CERRADO) throw new Exception('El período ya está reabierto.');
    if ($c['origen'] === 'IVA_POSICION' && !$desde_liquidacion) {
        throw new Exception('Este cierre es de la liquidación de IVA: se reabre anulándola en IVA - Posición Mensual.');
    }
    if ($c['origen'] === 'IIBB_POSICION' && !$desde_liquidacion) {
        throw new Exception('Este cierre es de la liquidación de Ingresos Brutos: se reabre anulándola en Control Fiscal > Ingresos Brutos.');
    }
    $estado = PF_REABIERTO;
    $uid = intval($usuario_id);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__impuestos_periodos_cierres
        SET tabla_estado_registro_id = ?, motivo_reapertura = ?, usuario_reapertura_id = ?, fecha_reapertura = NOW()
        WHERE cierre_id = ? AND tabla_estado_registro_id = ?");
    $cerrado = PF_CERRADO;
    mysqli_stmt_bind_param($stmt, 'isiii', $estado, $motivo, $uid, $cierre_id, $cerrado);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    error_log("=== pfReabrir cierre $cierre_id empresa $empresa_id: $motivo ===");
}

/** Reabre los cierres activos de una liquidación (al anularla). */
function pfReabrirPorOrigen($conexion, $empresa_id, $usuario_id, $origen, $referencia_id, $motivo)
{
    if (!pfDisponible($conexion)) return;
    foreach (pfFilas($conexion, "SELECT cierre_id FROM gestion__impuestos_periodos_cierres
                                 WHERE empresa_id = ? AND origen = ? AND referencia_id = ? AND tabla_estado_registro_id = ?",
                     'isii', intval($empresa_id), $origen, intval($referencia_id), PF_CERRADO) as $c) {
        pfReabrir($conexion, $empresa_id, $usuario_id, $c['cierre_id'], $motivo, true);
    }
}

/** Cierre automático de una liquidación (no duplica si ya hay uno de la misma liquidación). */
function pfCerrarPorLiquidacion($conexion, $empresa_id, $usuario_id, $impuesto_tipo_id, $jurisdiccion_id, $periodo, $origen, $referencia_id, $obs)
{
    if (!pfDisponible($conexion)) return null;
    if (pfFila($conexion, "SELECT 1 FROM gestion__impuestos_periodos_cierres
                           WHERE empresa_id = ? AND origen = ? AND referencia_id = ? AND jurisdiccion_id = ? AND tabla_estado_registro_id = ?",
               'isiii', intval($empresa_id), $origen, intval($referencia_id), intval($jurisdiccion_id), PF_CERRADO)) {
        return null;
    }
    return pfCerrar($conexion, $empresa_id, $usuario_id, ['impuesto_tipo_id' => $impuesto_tipo_id, 'jurisdiccion_id' => $jurisdiccion_id,
                    'periodo' => $periodo, 'observaciones' => $obs], $origen, $referencia_id);
}

/** Listado de cierres (activos y reabiertos) con filtros opcionales. */
function pfListar($conexion, $empresa_id, $filtros = [])
{
    $sql = "SELECT c.*, it.impuesto_tipo, j.jurisdiccion_nombre,
                   u.usuario_nombre AS usuario, ur.usuario_nombre AS usuario_reapertura,
                   ter.tabla_estado_registro AS estado
            FROM gestion__impuestos_periodos_cierres c
            LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = c.impuesto_tipo_id
            LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = c.jurisdiccion_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = c.usuario_id
            LEFT JOIN conf__usuarios ur ON ur.usuario_id = c.usuario_reapertura_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = " . PF_TABLA_CIERRES . " AND ter.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ?";
    $tipos = 'i';
    $params = [intval($empresa_id)];
    if (!empty($filtros['impuesto_tipo_id'])) {
        $sql .= ' AND c.impuesto_tipo_id = ?';
        $tipos .= 'i';
        $params[] = intval($filtros['impuesto_tipo_id']);
    }
    if (!empty($filtros['anio']) && preg_match('/^\d{4}$/', (string)$filtros['anio'])) {
        $sql .= ' AND c.periodo LIKE ?';
        $tipos .= 's';
        $params[] = $filtros['anio'] . '-%';
    }
    if (empty($filtros['incluir_reabiertos'])) {
        $sql .= ' AND c.tabla_estado_registro_id = ' . PF_CERRADO;
    }
    $sql .= ' ORDER BY c.periodo DESC, c.impuesto_tipo_id, j.jurisdiccion_nombre, c.cierre_id DESC';
    return pfFilas($conexion, $sql, $tipos, ...$params);
}

// ------------------------------------------------------------------ cuadre
/** ids de conf__tablas de los cuatro comprobantes con percepciones / retenciones. */
function pfTablasDocumentos($conexion)
{
    $ids = [];
    foreach (pfFilas($conexion, "SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE tabla_nombre IN
                                 ('gestion__ventas_facturas', 'gestion__facturas_proveedores', 'gestion__cobranzas', 'gestion__pagos')") as $f) {
        $ids[$f['tabla_nombre']] = intval($f['tabla_id']);
    }
    return $ids;
}

/**
 * Percepciones y retenciones de los comprobantes del rango, con la cuenta que les corresponde y el importe que
 * debería tener su asiento (Debe +, Haber -): sufridas al Debe (activo), practicadas al Haber (pasivo); el signo
 * del tipo invierte las notas de crédito.
 */
function pfComponentesCuadre($conexion, $empresa_id, $desde, $hasta)
{
    $e = intval($empresa_id);
    $estados_v = implode(',', PF_ESTADOS_VENTAS);
    $pv = emp_sql_pv_mostrar('v.punto_venta_id', 'v.empresa_id');
    $filas = [];

    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'Venta' AS origen, v.comprobante_id, v.f_emision AS fecha,
                CONCAT(ct.codigo, ' ', LPAD($pv, 4, '0'), '-', LPAD(v.comprobante_nro, 8, '0')) AS comprobante,
                en.entidad_nombre AS entidad, ci.impuesto_tipo_id, COALESCE(ci.jurisdiccion_id, 0) AS jurisdiccion_id,
                NULLIF(cfg.cont_cuenta_id, 0) AS cuenta_id,
                -IF(ct.signo = '-', -1, 1) * ci.importe AS esperado
         FROM gestion__ventas_facturas v
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = v.comprobante_tipo_id
         JOIN gestion__comprobantes_impuestos ci ON ci.comprobante_id = v.comprobante_id AND ci.tabla_estado_registro_id = 1
         LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = v.entidad_id
         WHERE v.empresa_id = ? AND v.f_emision BETWEEN ? AND ? AND v.tabla_estado_registro_id IN ($estados_v)",
        'iss', $e, $desde, $hasta));

    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'Compra' AS origen, f.comprobante_id, COALESCE(f.f_contabilidad, f.f_emision) AS fecha,
                CONCAT(ct.codigo, ' ', LPAD(f.comprobante_pv, 4, '0'), '-', LPAD(f.comprobante_nro, 8, '0')) AS comprobante,
                en.entidad_nombre AS entidad, COALESCE(ci.impuesto_tipo_id, cfg.impuesto_tipo_id) AS impuesto_tipo_id,
                COALESCE(ci.jurisdiccion_id, cfg.jurisdiccion_id, 0) AS jurisdiccion_id,
                NULLIF(cfg.cont_cuenta_id, 0) AS cuenta_id,
                IF(ct.signo = '-', -1, 1) * ci.importe AS esperado
         FROM gestion__facturas_proveedores f
         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
         JOIN gestion__comprobantes_impuestos ci ON ci.comprobante_id = f.comprobante_id AND ci.tabla_estado_registro_id = 1
         LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = f.entidad_id
         WHERE f.empresa_id = ? AND COALESCE(f.f_contabilidad, f.f_emision) BETWEEN ? AND ? AND f.tabla_estado_registro_id = 5",
        'iss', $e, $desde, $hasta));

    // Cuenta como cobCuentaRetencion(): IIBB la de la config de la jurisdicción a la fecha, el resto la del medio
    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'Cobranza' AS origen, x.comprobante_id, x.fecha, x.comprobante, x.entidad, x.impuesto_tipo_id, x.jurisdiccion_id,
                IF(x.jurisdiccion_id > 0,
                   (SELECT cfg.cont_cuenta_id FROM gestion__empresas_impuestos_config cfg
                     WHERE cfg.empresa_id = x.empresa_id AND cfg.impuesto_tipo_id = x.impuesto_tipo_id AND cfg.jurisdiccion_id = x.jurisdiccion_id
                       AND cfg.cont_cuenta_id > 0 AND cfg.tabla_estado_registro_id = 1
                       AND cfg.f_desde <= x.fecha AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= x.fecha)
                     ORDER BY cfg.prioridad ASC, cfg.empresa_impuesto_config_id ASC LIMIT 1),
                   x.cuenta_medio) AS cuenta_id,
                x.esperado
         FROM (SELECT co.empresa_id, co.comprobante_id, COALESCE(co.f_contabilidad, co.f_emision) AS fecha,
                      CONCAT('RCX ', LPAD(c.comprobante_nro, 8, '0')) AS comprobante, en.entidad_nombre AS entidad,
                      COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id) AS impuesto_tipo_id, COALESCE(cm.jurisdiccion_id, 0) AS jurisdiccion_id,
                      NULLIF(mp.cont_cuenta_id, 0) AS cuenta_medio, cm.importe_comprobante AS esperado
               FROM gestion__cobranzas co
               JOIN gestion__cobranzas_medios cm ON cm.cobranza_id = co.cobranza_id
               JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id AND mp.medio_pago_tipo = 'RETENCION'
               LEFT JOIN gestion__comprobantes c ON c.comprobante_id = co.comprobante_id
               LEFT JOIN gestion__entidades en ON en.entidad_id = co.entidad_id
               WHERE co.empresa_id = ? AND COALESCE(co.f_contabilidad, co.f_emision) BETWEEN ? AND ? AND co.tabla_estado_registro_id = 5) x",
        'iss', $e, $desde, $hasta));

    $filas = array_merge($filas, pfFilas($conexion,
        "SELECT 'Orden de pago' AS origen, p.comprobante_id, p.f_emision AS fecha,
                CONCAT('OP ', LPAD(c.comprobante_nro, 8, '0'), IF(pr.certificado_nro IS NULL, '', CONCAT(' - cert. ', LPAD(pr.certificado_nro, 8, '0')))) AS comprobante,
                en.entidad_nombre AS entidad, pr.impuesto_tipo_id, COALESCE(pr.jurisdiccion_id, 0) AS jurisdiccion_id,
                NULLIF(cfg.cont_cuenta_id, 0) AS cuenta_id, -pr.importe AS esperado
         FROM gestion__pagos_retenciones pr
         JOIN gestion__pagos p ON p.pago_id = pr.pago_id AND p.empresa_id = pr.empresa_id
         LEFT JOIN gestion__comprobantes c ON c.comprobante_id = p.comprobante_id
         LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = pr.empresa_impuesto_config_id
         LEFT JOIN gestion__entidades en ON en.entidad_id = pr.entidad_id
         WHERE pr.empresa_id = ? AND p.f_emision BETWEEN ? AND ? AND pr.tabla_estado_registro_id = 5",
        'iss', $e, $desde, $hasta));

    return $filas;
}

/** Lo contabilizado por comprobante y cuenta en los asientos vigentes (clave "comprobante|cuenta"). */
function pfContabilizado($conexion, $empresa_id, array $comprobantes, array $cuentas)
{
    $out = [];
    $comprobantes = array_values(array_unique(array_filter(array_map('intval', $comprobantes))));
    $cuentas = array_values(array_unique(array_filter(array_map('intval', $cuentas))));
    if (!$comprobantes || !$cuentas) return $out;
    $in_ctas = implode(',', array_fill(0, count($cuentas), '?'));
    foreach (array_chunk($comprobantes, 500) as $lote) {
        $in = implode(',', array_fill(0, count($lote), '?'));
        foreach (pfFilas($conexion,
            "SELECT a.comprobante_id, d.cuenta_id, SUM(d.importe_local) AS importe
             FROM gestion__cont_asientos a
             JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id
             WHERE a.empresa_id = ? AND a.tabla_estado_registro_id NOT IN (2, 4, 6, 38)
               AND a.comprobante_id IN ($in) AND d.cuenta_id IN ($in_ctas)
             GROUP BY a.comprobante_id, d.cuenta_id",
            'i' . str_repeat('i', count($lote) + count($cuentas)), intval($empresa_id), ...$lote, ...$cuentas) as $f) {
            $out[$f['comprobante_id'] . '|' . $f['cuenta_id']] = pfR($f['importe']);
        }
    }
    return $out;
}

/** Comprobantes que tienen algún asiento vigente. */
function pfComprobantesConAsiento($conexion, $empresa_id, array $comprobantes)
{
    $out = [];
    $comprobantes = array_values(array_unique(array_filter(array_map('intval', $comprobantes))));
    foreach (array_chunk($comprobantes, 500) as $lote) {
        $in = implode(',', array_fill(0, count($lote), '?'));
        foreach (pfFilas($conexion, "SELECT DISTINCT comprobante_id FROM gestion__cont_asientos
                                     WHERE empresa_id = ? AND tabla_estado_registro_id NOT IN (2, 4, 6, 38) AND comprobante_id IN ($in)",
                         'i' . str_repeat('i', count($lote)), intval($empresa_id), ...$lote) as $f) {
            $out[intval($f['comprobante_id'])] = true;
        }
    }
    return $out;
}

/**
 * Cuadre de percepciones y retenciones de los períodos desde..hasta:
 *   impuestos   por impuesto / jurisdicción / cuenta: según comprobantes, contabilizado y diferencia
 *   cuentas     por cuenta: saldo inicial, movimientos de esos comprobantes, liquidaciones y otros, saldo final
 *   diferencias comprobante por comprobante (sin cuenta configurada, sin asiento o con otro importe)
 */
function pfCuadre($conexion, $empresa_id, $periodo_desde, $periodo_hasta)
{
    $empresa_id = intval($empresa_id);
    $desde = pfValidarPeriodo($periodo_desde) . '-01';
    $hasta = date('Y-m-t', strtotime(pfValidarPeriodo($periodo_hasta) . '-01'));
    if ($desde > $hasta) throw new Exception('El período desde es posterior al hasta.');

    $comp = pfComponentesCuadre($conexion, $empresa_id, $desde, $hasta);

    // Agrupado por comprobante + cuenta (lo que se puede comparar con el asiento)
    $grupos = [];
    foreach ($comp as $f) {
        $k = intval($f['comprobante_id']) . '|' . intval($f['cuenta_id']);
        if (!isset($grupos[$k])) {
            $grupos[$k] = ['origen' => $f['origen'], 'comprobante_id' => intval($f['comprobante_id']), 'fecha' => $f['fecha'],
                           'comprobante' => $f['comprobante'], 'entidad' => $f['entidad'], 'cuenta_id' => intval($f['cuenta_id']),
                           'esperado' => 0.0, 'claves' => []];
        }
        $grupos[$k]['esperado'] += floatval($f['esperado']);
        $ck = intval($f['impuesto_tipo_id']) . '|' . intval($f['jurisdiccion_id']);
        $grupos[$k]['claves'][$ck] = ($grupos[$k]['claves'][$ck] ?? 0) + floatval($f['esperado']);
    }

    // Cuentas: las de los comprobantes + las configuradas para percepciones / retenciones + las de los medios RETENCION
    $cuentas = array_filter(array_column($grupos, 'cuenta_id'));
    foreach (pfFilas($conexion, "SELECT DISTINCT cont_cuenta_id FROM gestion__empresas_impuestos_config cfg
                                 JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
                                 WHERE cfg.empresa_id = ? AND cfg.cont_cuenta_id > 0 AND (it.es_retencion = 1 OR it.es_percepcion = 1)
                                 UNION
                                 SELECT DISTINCT cont_cuenta_id FROM gestion__medios_pago
                                 WHERE empresa_id = ? AND medio_pago_tipo = 'RETENCION' AND cont_cuenta_id > 0",
                         'ii', $empresa_id, $empresa_id) as $f) {
        $cuentas[] = intval($f['cont_cuenta_id']);
    }
    $cuentas = array_values(array_unique(array_map('intval', $cuentas)));

    $contab = pfContabilizado($conexion, $empresa_id, array_column($grupos, 'comprobante_id'), $cuentas);
    $con_asiento = pfComprobantesConAsiento($conexion, $empresa_id, array_column($grupos, 'comprobante_id'));

    $impuestos = [];
    $diferencias = [];
    foreach ($grupos as $k => $g) {
        $esp = pfR($g['esperado']);
        $con = $g['cuenta_id'] > 0 ? ($contab[$k] ?? 0.0) : 0.0;
        // Lo contabilizado se reparte entre los impuestos del grupo en proporción a lo esperado
        $n = count($g['claves']);
        foreach ($g['claves'] as $ck => $imp_esp) {
            $parte = abs($g['esperado']) >= 0.005 ? $con * ($imp_esp / $g['esperado']) : $con / $n;
            $ik = $ck . '|' . $g['cuenta_id'];
            if (!isset($impuestos[$ik])) {
                [$imp, $jur] = array_map('intval', explode('|', $ck));
                $impuestos[$ik] = ['impuesto_tipo_id' => $imp, 'jurisdiccion_id' => $jur, 'cuenta_id' => $g['cuenta_id'],
                                   'comprobantes' => 0, 'esperado' => 0.0, 'contabilizado' => 0.0, 'con_diferencia' => 0];
            }
            $impuestos[$ik]['comprobantes']++;
            $impuestos[$ik]['esperado'] += $imp_esp;
            $impuestos[$ik]['contabilizado'] += $parte;
        }
        $motivo = null;
        if ($g['cuenta_id'] <= 0) $motivo = 'Sin cuenta contable configurada';
        elseif (!isset($con_asiento[$g['comprobante_id']])) $motivo = 'Sin asiento';
        elseif (abs($con - $esp) >= 0.01) $motivo = 'Importe distinto';
        if ($motivo !== null) {
            foreach (array_keys($g['claves']) as $ck) $impuestos[$ck . '|' . $g['cuenta_id']]['con_diferencia']++;
            $diferencias[] = ['origen' => $g['origen'], 'fecha' => $g['fecha'], 'comprobante' => $g['comprobante'],
                              'entidad' => $g['entidad'], 'cuenta_id' => $g['cuenta_id'], 'claves' => array_keys($g['claves']),
                              'esperado' => $esp, 'contabilizado' => pfR($con), 'diferencia' => pfR($con - $esp), 'motivo' => $motivo];
        }
    }

    // Nombres de impuestos, jurisdicciones y cuentas
    $nom_imp = [];
    foreach (pfFilas($conexion, "SELECT impuesto_tipo_id, impuesto_tipo FROM gestion__impuestos_tipos") as $f) $nom_imp[intval($f['impuesto_tipo_id'])] = $f['impuesto_tipo'];
    $nom_jur = [];
    foreach (pfFilas($conexion, "SELECT jurisdiccion_id, jurisdiccion_nombre FROM gestion__jurisdicciones") as $f) $nom_jur[intval($f['jurisdiccion_id'])] = $f['jurisdiccion_nombre'];
    $nom_cta = [];
    if ($cuentas) {
        $in = implode(',', array_fill(0, count($cuentas), '?'));
        foreach (pfFilas($conexion, "SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas WHERE empresa_id = ? AND cont_cuenta_id IN ($in)",
                         'i' . str_repeat('i', count($cuentas)), $empresa_id, ...$cuentas) as $f) {
            $nom_cta[intval($f['cont_cuenta_id'])] = trim($f['codigo'] . ' ' . $f['nombre']);
        }
    }
    $nombreClave = function ($ck) use ($nom_imp, $nom_jur) {
        [$imp, $jur] = array_map('intval', explode('|', $ck));
        return trim(($nom_imp[$imp] ?? "Impuesto $imp") . ($jur > 0 ? ' - ' . ($nom_jur[$jur] ?? $jur) : ''));
    };

    foreach ($impuestos as &$i) {
        $i['impuesto'] = $nombreClave($i['impuesto_tipo_id'] . '|' . $i['jurisdiccion_id']);
        $i['cuenta'] = $i['cuenta_id'] > 0 ? ($nom_cta[$i['cuenta_id']] ?? '#' . $i['cuenta_id']) : null;
        $i['esperado'] = pfR($i['esperado']);
        $i['contabilizado'] = pfR($i['contabilizado']);
        $i['diferencia'] = pfR($i['contabilizado'] - $i['esperado']);
    }
    unset($i);
    $impuestos = array_values($impuestos);
    usort($impuestos, function ($a, $b) { return strcmp($a['impuesto'], $b['impuesto']); });

    foreach ($diferencias as &$d) {
        $d['impuestos'] = implode(', ', array_map($nombreClave, $d['claves']));
        $d['cuenta'] = $d['cuenta_id'] > 0 ? ($nom_cta[$d['cuenta_id']] ?? '#' . $d['cuenta_id']) : null;
        unset($d['claves']);
    }
    unset($d);
    usort($diferencias, function ($a, $b) { return strcmp($a['fecha'], $b['fecha']); });

    // Por cuenta: saldo inicial, movimientos de comprobantes con impuestos, liquidaciones y otros, saldo final
    $tablas = array_values(pfTablasDocumentos($conexion));
    $por_cuenta = [];
    if ($cuentas) {
        $in = implode(',', array_fill(0, count($cuentas), '?'));
        $in_t = $tablas ? implode(',', array_fill(0, count($tablas), '?')) : '0';
        $filas = pfFilas($conexion,
            "SELECT d.cuenta_id,
                    ROUND(SUM(IF(a.f_asiento < ?, d.importe_local, 0)), 2) AS saldo_inicial,
                    ROUND(SUM(IF(a.f_asiento BETWEEN ? AND ? AND c.tabla_origen_id IN ($in_t), d.importe_local, 0)), 2) AS mov_comprobantes,
                    ROUND(SUM(IF(a.f_asiento BETWEEN ? AND ? AND (c.tabla_origen_id IS NULL OR c.tabla_origen_id NOT IN ($in_t)), d.importe_local, 0)), 2) AS mov_otros,
                    ROUND(SUM(d.importe_local), 2) AS saldo_final
             FROM gestion__cont_asientos a
             JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id
             LEFT JOIN gestion__comprobantes c ON c.comprobante_id = a.comprobante_id AND a.comprobante_id > 0
             WHERE a.empresa_id = ? AND a.tabla_estado_registro_id NOT IN (2, 4, 6, 38) AND d.cuenta_id IN ($in) AND a.f_asiento <= ?
             GROUP BY d.cuenta_id",
            'sss' . str_repeat('i', count($tablas)) . 'ss' . str_repeat('i', count($tablas)) . 'i' . str_repeat('i', count($cuentas)) . 's',
            $desde, $desde, $hasta, ...array_merge($tablas, [$desde, $hasta], $tablas, [$empresa_id], $cuentas, [$hasta]));
        $mov = [];
        foreach ($filas as $f) $mov[intval($f['cuenta_id'])] = $f;
        foreach ($cuentas as $cta) {
            if (!isset($mov[$cta]) && !in_array($cta, array_column($impuestos, 'cuenta_id'), true)) continue;   // cuenta sin uso
            $f = $mov[$cta] ?? ['saldo_inicial' => 0, 'mov_comprobantes' => 0, 'mov_otros' => 0, 'saldo_final' => 0];
            $por_cuenta[] = ['cuenta_id' => $cta, 'cuenta' => $nom_cta[$cta] ?? '#' . $cta,
                             'impuestos' => implode(', ', array_unique(array_map(function ($i) { return $i['impuesto']; },
                                            array_filter($impuestos, function ($i) use ($cta) { return $i['cuenta_id'] === $cta; })))),
                             'saldo_inicial' => pfR($f['saldo_inicial']), 'mov_comprobantes' => pfR($f['mov_comprobantes']),
                             'mov_otros' => pfR($f['mov_otros']), 'saldo_final' => pfR($f['saldo_final'])];
        }
        usort($por_cuenta, function ($a, $b) { return strcmp($a['cuenta'], $b['cuenta']); });
    }

    return ['desde' => $desde, 'hasta' => $hasta, 'impuestos' => $impuestos, 'cuentas' => $por_cuenta, 'diferencias' => $diferencias,
            'totales' => ['esperado' => pfR(array_sum(array_column($impuestos, 'esperado'))),
                          'contabilizado' => pfR(array_sum(array_column($impuestos, 'contabilizado'))),
                          'diferencias' => count($diferencias)]];
}
