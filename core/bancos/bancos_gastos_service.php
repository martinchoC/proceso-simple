<?php
/**
 * Conciliación bancaria: gastos que origina el banco (§7 del diseño). Botón "Generar comprobante": toma las líneas
 * GASTO_BANCARIO clasificadas de una cuenta y un mes y emite un Resumen bancario (C_RB, ARCA 039) al proveedor del
 * banco por el circuito existente de facturas de proveedor: agregarFacturaProveedor -> aprobar -> confirmar
 * (ejecutarTransicionEstado, que dispara fpPostConfirmacion y la OP automática DEB_CTA desde la cuenta). Después
 * concilia las líneas con el movimiento de tesorería de esa OP (origen GASTOS). No escribe SQL contable propio.
 *
 * El llamador (bancos_gastos_model.php de cada módulo) incluye ANTES su facturas_proveedores_model.php y pasa la
 * página de facturas de proveedor de su módulo (57 / 557).
 *
 * Transacciones: las funciones de facturas de proveedor abren y confirman la suya, así que el proceso no es una
 * única transacción. Se serializa por empresa + cuenta con GET_LOCK y, si un paso falla, se compensa: el borrador se
 * elimina (3 -> 6) y un comprobante ya confirmado se anula (5 -> 38, revierte OP, asiento e imputaciones).
 *
 * Percepciones (IVA, Ganancias, IIBB) y SIRCREB: se vinculan a la configuración de impuestos de compras de la empresa
 * (impuesto del rol, BCO_ROL_IMPUESTO) y, las de IIBB, a la provincia del concepto. SIRCREB va como percepción IIBB.
 *
 * Pendiente de definición con el contador (no se generan; quedan a la vista con el motivo):
 *   - Los créditos del banco (intereses a favor, reintegros: C8, nota de crédito).
 *
 * IVA: solo el que el extracto declara en líneas de IVA (rol IVA). Se reparte entre los gastos con producto gravado y
 * la base gravada sale de ese IVA (IVA / alícuota); el resto del gasto va como no gravado. Sin líneas de IVA no hubo
 * IVA liquidado: todo el gasto es no gravado. No se controla contra la alícuota del producto (2026-10-30).
 */

require_once __DIR__ . '/bancos_conciliacion_service.php';
require_once __DIR__ . '/../entidades_roles.php';

/** Proveedores (rol PRO o BAN) de la empresa para elegir el del banco. */
function bco_proveedores_buscar(mysqli $c, int $empresa_id, string $q): array
{
    $q = '%' . addcslashes(trim($q), '%_\\') . '%';
    return bco_filas($c, "SELECT e.entidad_id, e.entidad_nombre, e.cuit FROM gestion__entidades e
                          WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1
                            AND " . ent_sql_tiene_rol('e.entidad_id', 'PRO') . "
                            AND (e.entidad_nombre LIKE ? OR CAST(e.cuit AS CHAR) LIKE ?)
                          ORDER BY (e.entidad_nombre LIKE '%BANCO%') DESC, e.entidad_nombre LIMIT 30", 'iss', $empresa_id, $q, $q);
}

/** Guarda el proveedor del banco de la cuenta (tiene que ser proveedor de la empresa). */
function bco_gastos_proveedor_guardar(mysqli $c, int $empresa_id, int $cuenta_id, int $entidad_id): void
{
    bco_cuenta($c, $empresa_id, $cuenta_id);
    $e = bco_fila($c, "SELECT entidad_id FROM gestion__entidades e WHERE e.entidad_id = ? AND e.empresa_id = ? AND e.tabla_estado_registro_id = 1
                       AND " . ent_sql_tiene_rol('e.entidad_id', 'PRO'), 'ii', $entidad_id, $empresa_id);
    if (!$e) throw new RuntimeException('Elegí una entidad activa con rol de proveedor (si es solo banco, agregale el rol Proveedor).');
    bco_exec($c, "UPDATE gestion__tesoreria_cuentas SET proveedor_entidad_id = ? WHERE tesoreria_cuenta_id = ? AND empresa_id = ?",
        'iii', $entidad_id, $cuenta_id, $empresa_id);
}

/**
 * Base gravada para un IVA declarado: el neto (hasta $tope) cuyo round(neto × p, 2) más se acerca a $iva,
 * exacto cuando se puede (con 21 % cada centavo de neto mueve el IVA 0,21 centavos).
 */
function bco_gastos_neto_de_iva(float $iva, float $porcentaje, float $tope): float
{
    $p = $porcentaje / 100;
    $base = min(round($iva / $p, 2), $tope);
    $mejor = $base;
    for ($d = -10; $d <= 10; $d++) {
        $n = round($base + $d / 100, 2);
        if ($n <= 0 || $n > $tope) continue;
        if (abs(round($n * $p, 2) - $iva) < abs(round($mejor * $p, 2) - $iva)) $mejor = $n;
    }
    return $mejor;
}

/**
 * Meses con gastos del banco sin registrar, por cuenta bancaria activa: débitos clasificados con concepto de gasto
 * y sin conciliar (lo que tomaría bco_gastos_resumen), más las líneas sin concepto del mes, que podrían ser gastos.
 */
function bco_gastos_pendientes(mysqli $c, int $empresa_id, int $cuenta_id = 0, ?string $desde = null, ?string $hasta = null): array
{
    return bco_filas($c,
        "SELECT x.tesoreria_cuenta_id, t.tesoreria_cuenta, t.numero, DATE_FORMAT(x.f_movimiento, '%Y-%m') AS mes,
                SUM(x.estado = 'CLASIFICADA' AND k.naturaleza = 'GASTO_BANCARIO' AND x.importe < 0) AS lineas,
                COALESCE(SUM(CASE WHEN x.estado = 'CLASIFICADA' AND k.naturaleza = 'GASTO_BANCARIO' AND x.importe < 0 THEN -x.importe END), 0) AS importe,
                SUM(x.estado = 'PENDIENTE') AS sin_concepto,
                MAX(CASE WHEN x.estado = 'CLASIFICADA' AND k.naturaleza = 'GASTO_BANCARIO' AND x.importe < 0 THEN x.f_movimiento END) AS ultimo
         FROM gestion__bancos_extracto x
         JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = x.tesoreria_cuenta_id AND t.empresa_id = x.empresa_id
                                          AND t.cuenta_tipo = 'BANCO' AND t.tabla_estado_registro_id = 1
         LEFT JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id AND k.empresa_id = x.empresa_id
         WHERE x.empresa_id = ? AND x.conciliacion_id IS NULL AND x.estado IN ('PENDIENTE', 'CLASIFICADA')
           AND (? = 0 OR x.tesoreria_cuenta_id = ?) AND (? IS NULL OR x.f_movimiento >= ?) AND (? IS NULL OR x.f_movimiento <= ?)
         GROUP BY x.tesoreria_cuenta_id, t.tesoreria_cuenta, t.numero, DATE_FORMAT(x.f_movimiento, '%Y-%m')
         HAVING lineas > 0 OR sin_concepto > 0
         ORDER BY t.tesoreria_cuenta, mes", 'iiissss', $empresa_id, $cuenta_id, $cuenta_id, $desde, $desde, $hasta, $hasta);
}

/**
 * Lo que generaría el botón para la cuenta y el mes (sin escribir): líneas incluidas y excluidas con el motivo,
 * ítems por producto, impuestos, control de IVA, bloqueos y avisos.
 */
function bco_gastos_resumen(mysqli $c, int $empresa_id, int $cuenta_id, string $mes, array $excluir = []): array
{
    $cta = bco_cuenta($c, $empresa_id, $cuenta_id);
    $mes = bco_mes_valido($mes);
    $desde = $mes . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    $excluir = array_flip(bco_ids($excluir));
    $bloqueos = [];
    $avisos = [];

    $lineas = bco_filas($c,
        "SELECT x.extracto_id, x.f_movimiento, x.descripcion, x.importe, x.concepto_id, k.codigo AS concepto_codigo, k.concepto, k.rol,
                k.producto_id, k.impuesto_tipo_id, k.jurisdiccion_id, j.jurisdiccion_nombre, p.producto_nombre, p.iva_alicuota_id, p.cont_cuenta_compra_id,
                i.porcentaje AS iva_porcentaje, i.es_gravado, it.impuesto_tipo
         FROM gestion__bancos_extracto x
         JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id AND k.empresa_id = x.empresa_id AND k.naturaleza = 'GASTO_BANCARIO'
         LEFT JOIN gestion__productos p ON p.producto_id = k.producto_id AND p.empresa_id = x.empresa_id AND p.tabla_estado_registro_id = 1
         LEFT JOIN gestion__impuestos__iva_alicuotas i ON i.iva_alicuota_id = p.iva_alicuota_id
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = k.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = k.jurisdiccion_id
         WHERE x.empresa_id = ? AND x.tesoreria_cuenta_id = ? AND x.estado = 'CLASIFICADA' AND x.conciliacion_id IS NULL
           AND x.f_movimiento BETWEEN ? AND ?
         ORDER BY x.f_movimiento, x.extracto_id", 'iiss', $empresa_id, $cuenta_id, $desde, $hasta);

    $items = [];          // por producto
    $impuestos = [];      // por impuesto + provincia (percepciones y SIRCREB)
    $iva_extracto = 0.0;
    $incluidas = [];
    foreach ($lineas as &$l) {
        $l['importe'] = floatval($l['importe']);
        $l['incluida'] = false;
        $l['motivo'] = null;
        if (isset($excluir[intval($l['extracto_id'])])) { $l['motivo'] = 'Excluida por el usuario'; continue; }
        if ($l['importe'] > 0) { $l['motivo'] = 'Crédito del banco (intereses, reintegros): el tratamiento lo define el contador'; continue; }
        $monto = -$l['importe'];
        switch ($l['rol']) {
            case 'ITEM':
                if (!$l['producto_id'] || !$l['producto_nombre']) { $l['motivo'] = 'El concepto no tiene un producto activo'; $bloqueos[] = 'El concepto ' . $l['concepto'] . ' no tiene producto activo (Conceptos Bancarios).'; continue 2; }
                if (!$l['cont_cuenta_compra_id']) { $l['motivo'] = 'El producto no tiene cuenta contable de compra'; $bloqueos[] = 'El producto ' . $l['producto_nombre'] . ' no tiene cuenta contable de compra.'; continue 2; }
                $pid = intval($l['producto_id']);
                $items[$pid] = $items[$pid] ?? ['producto_id' => $pid, 'producto_nombre' => $l['producto_nombre'], 'iva_alicuota_id' => intval($l['iva_alicuota_id']),
                                                'iva_porcentaje' => intval($l['es_gravado']) ? floatval($l['iva_porcentaje']) : 0.0,
                                                'importe' => 0.0, 'lineas' => 0];
                $items[$pid]['importe'] = round($items[$pid]['importe'] + $monto, 2);
                $items[$pid]['lineas']++;
                break;
            case 'IVA':
                $iva_extracto = round($iva_extracto + $monto, 2);
                break;
            case 'PERCEPCION_IVA':
            case 'PERCEPCION_IIBB':
            case 'PERCEPCION_GANANCIAS':
            case 'RETENCION_IIBB':
                if (!$l['impuesto_tipo_id']) { $l['motivo'] = 'El concepto no tiene el impuesto'; $bloqueos[] = 'El concepto ' . $l['concepto'] . ' no tiene impuesto (Conceptos Bancarios).'; continue 2; }
                $jur = in_array($l['rol'], BCO_ROLES_JURISDICCION, true) ? intval($l['jurisdiccion_id'] ?? 0) : 0;
                if (in_array($l['rol'], BCO_ROLES_JURISDICCION, true) && !$jur) {
                    $l['motivo'] = 'El concepto no tiene la provincia';
                    $bloqueos[] = 'Elegí la provincia del concepto ' . $l['concepto'] . ' (Conceptos Bancarios).';
                    continue 2;
                }
                $t = intval($l['impuesto_tipo_id']);
                $clave = $t . '|' . $jur;
                $impuestos[$clave] = $impuestos[$clave] ?? ['impuesto_tipo_id' => $t, 'jurisdiccion_id' => $jur, 'jurisdiccion' => $jur ? $l['jurisdiccion_nombre'] : null,
                                                           'impuesto_tipo' => $l['impuesto_tipo'], 'importe' => 0.0];
                $impuestos[$clave]['importe'] = round($impuestos[$clave]['importe'] + $monto, 2);
                break;
            default:
                $l['motivo'] = 'Concepto sin rol en el comprobante';
                continue 2;
        }
        $l['incluida'] = true;
        $incluidas[] = $l;
    }
    unset($l);

    // IVA: el que declara el extracto, repartido entre los gastos con producto gravado según su IVA posible (importe × alícuota)
    $alic_ng = bco_fila($c, "SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ? AND es_no_gravado = 1
                             AND tabla_estado_registro_id = 1 ORDER BY iva_alicuota_id LIMIT 1", 'i', $empresa_id);
    $capacidad = [];
    foreach ($items as $pid => $it) {
        if ($it['iva_porcentaje'] > 0) $capacidad[$pid] = round($it['importe'] * $it['iva_porcentaje'] / 100, 2);
    }
    $cap_total = array_sum($capacidad);
    if ($iva_extracto > 0 && !$capacidad) {
        $bloqueos[] = 'El extracto tiene IVA (' . bco_fmt($iva_extracto) . ') y ningún gasto con producto gravado al que asignarlo: revisá los productos de los conceptos.';
    } elseif ($iva_extracto > $cap_total + 0.01 * count($capacidad)) {
        $bloqueos[] = 'El IVA del extracto (' . bco_fmt($iva_extracto) . ') es mayor que el que admiten los gastos incluidos (' . bco_fmt($cap_total)
            . '): falta incluir la comisión de ese IVA (¿está excluida o en otro concepto?).';
    }

    // Líneas del comprobante: con IVA, neto gravado = IVA / alícuota y el resto del gasto no gravado; sin IVA, todo no gravado
    $detalles = [];
    $iva_asignado = 0.0;
    $ultimo = $capacidad ? array_key_last($capacidad) : null;
    foreach ($items as $pid => $it) {
        $parte = 0.0;
        if ($iva_extracto > 0 && isset($capacidad[$pid]) && $cap_total > 0) {
            $parte = $pid === $ultimo ? round($iva_extracto - $iva_asignado, 2) : round($iva_extracto * $capacidad[$pid] / $cap_total, 2);
            $iva_asignado = round($iva_asignado + $parte, 2);
        }
        $total = round($it['importe'] + $parte, 2);
        if ($parte > 0) {
            $neto = bco_gastos_neto_de_iva($parte, $it['iva_porcentaje'], $it['importe']);
            $iva = round($neto * $it['iva_porcentaje'] / 100, 2);
            while ($neto + $iva > $total && $neto > 0.01) {   // el IVA redondeado no puede dejar un no gravado negativo
                $neto = round($neto - 0.01, 2);
                $iva = round($neto * $it['iva_porcentaje'] / 100, 2);
            }
            $detalles[] = $it + ['neto' => $neto, 'iva' => $iva, 'no_gravado' => round($total - $neto - $iva, 2), 'total' => $total];
        } elseif ($it['iva_porcentaje'] > 0) {
            if (!$alic_ng) { $bloqueos[] = 'La empresa no tiene la alícuota "No gravado" activa (Alícuotas de IVA).'; continue; }
            $detalles[] = ['iva_alicuota_id' => intval($alic_ng['iva_alicuota_id']), 'iva_porcentaje' => 0.0] + $it
                        + ['neto' => $it['importe'], 'iva' => 0.0, 'no_gravado' => 0.0, 'total' => $total, 'sin_iva' => 1];
        } else {
            $detalles[] = $it + ['neto' => $it['importe'], 'iva' => 0.0, 'no_gravado' => 0.0, 'total' => $total];
        }
    }

    // Configuración de cada percepción: impuesto de compras activo y vigente de la empresa, de la provincia del concepto (IIBB)
    foreach ($impuestos as &$imp) {
        $jur = intval($imp['jurisdiccion_id']);
        $cfg = bco_filas($c, "SELECT cfg.empresa_impuesto_config_id FROM gestion__empresas_impuestos_config cfg
                              JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id AND it.aplica_compra = 1
                              WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND (? = 0 OR cfg.jurisdiccion_id = ?) AND cfg.tabla_estado_registro_id = 1
                                AND (cfg.f_desde IS NULL OR cfg.f_desde <= ?) AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)",
            'iiiiss', $empresa_id, $imp['impuesto_tipo_id'], $jur, $jur, $hasta, $hasta);
        $nombre = $imp['impuesto_tipo'] . ($imp['jurisdiccion'] ? ' ' . $imp['jurisdiccion'] : '');
        if (count($cfg) !== 1) {
            $bloqueos[] = count($cfg) ? $nombre . ': la empresa tiene más de una configuración vigente; revisá Impuestos de la empresa.'
                                      : $nombre . ': la empresa no lo tiene configurado como impuesto de compras vigente (Impuestos de la empresa).';
        }
        $imp['empresa_impuesto_config_id'] = $cfg ? intval($cfg[0]['empresa_impuesto_config_id']) : 0;
    }
    unset($imp);

    // Moneda, proveedor, tipo C_RB, período fiscal y comprobante ya generado
    if (intval($cta['moneda_id']) !== intval(emp_moneda_base_id($c, $empresa_id))) {
        $bloqueos[] = 'La cuenta no está en la moneda base de la empresa: los gastos de una cuenta en otra moneda se cargan a mano, con su cotización.';
    }
    if (!$cta['proveedor_entidad_id']) $bloqueos[] = 'Elegí el proveedor del banco de la cuenta ' . $cta['tesoreria_cuenta'] . '.';
    $tipo = bco_fila($c, "SELECT comprobante_tipo_id, comprobante_tipo FROM gestion__comprobantes_tipos
                          WHERE empresa_id = ? AND codigo = 'C_RB' AND tabla_estado_registro_id = 1", 'i', $empresa_id);
    if (!$tipo) $bloqueos[] = 'La empresa no tiene el tipo de comprobante "Resumen bancario" (C_RB).';
    $f_emision = $incluidas ? max(array_column($incluidas, 'f_movimiento')) : null;
    if ($f_emision && function_exists('pfCierresQueBloquean')) {
        $obl = ['7|0' => [7, 0]];   // IVA, como una compra fiscal en pfObligacionesDocumento
        foreach ($impuestos as $imp) pfAgregarComponente($obl, $imp['impuesto_tipo_id'], $imp['jurisdiccion_id'] ?: PF_JUR_CUALQUIERA);
        $obl = array_values($obl);
        $msg = pfMensajeBloqueo(pfCierresQueBloquean($c, $empresa_id, $f_emision, $obl), 'generar el resumen bancario');
        if ($msg) $bloqueos[] = $msg;
    }
    // Se puede generar en cualquier momento con las líneas pendientes: varios comprobantes en el mismo mes (AAAAMM01, 02...),
    // cada uno con la fecha de su último movimiento. Lo que no se permite es cruzar de mes (C5).
    $previos = intval(bco_fila($c, "SELECT COUNT(*) n FROM gestion__bancos_conciliaciones k
                                    WHERE k.empresa_id = ? AND k.tesoreria_cuenta_id = ? AND k.origen = 'GASTOS' AND k.tabla_estado_registro_id = 5
                                      AND k.f_conciliacion BETWEEN ? AND ?", 'iiss', $empresa_id, $cuenta_id, $desde, $hasta)['n'] ?? 0);
    if ($previos && $incluidas) {
        $avisos[] = "Ya hay $previos resumen(es) bancario(s) del mes para la cuenta: este será otro comprobante, con las líneas que quedaron pendientes.";
    }
    // Un C_RB de la cuenta y el mes vigente pero sin su conciliación (generación interrumpida): regularizarlo antes
    if ($tipo) {
        $base = intval(str_replace('-', '', $mes)) * 100;
        $huerfano = bco_fila($c, "SELECT f.factura_proveedor_id, f.comprobante_nro FROM gestion__facturas_proveedores f
                                  WHERE f.empresa_id = ? AND f.comprobante_tipo_id = ? AND f.comprobante_pv = ? AND f.comprobante_nro BETWEEN ? AND ?
                                    AND f.tabla_estado_registro_id NOT IN (6, 38)
                                    AND NOT EXISTS (SELECT 1 FROM gestion__bancos_conciliaciones k WHERE k.empresa_id = f.empresa_id
                                                    AND k.factura_proveedor_id = f.factura_proveedor_id AND k.tabla_estado_registro_id = 5)
                                  LIMIT 1",
            'iiiii', $empresa_id, intval($tipo['comprobante_tipo_id']), $cuenta_id, $base + 1, $base + 99);
        if ($huerfano) {
            $bloqueos[] = 'Hay un resumen bancario del mes sin conciliar (Facturas de Proveedores, n° ' . $huerfano['comprobante_nro']
                . ', id ' . $huerfano['factura_proveedor_id'] . '): anulalo o eliminalo allí antes de generar otro.';
        }
    }
    $cobertura = bco_fila($c, "SELECT MAX(f_hasta) h FROM gestion__bancos_importaciones WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND tabla_estado_registro_id = 5",
        'ii', $empresa_id, $cuenta_id)['h'] ?? null;
    if ($cobertura && $cobertura < $hasta && $incluidas) {
        $avisos[] = 'El extracto importado llega hasta el ' . date('d/m/Y', strtotime($cobertura)) . ': el comprobante sale con fecha '
            . date('d/m/Y', strtotime($f_emision)) . ' (último movimiento incluido); los gastos posteriores del mes irán en otro comprobante.';
    }
    if (!$incluidas && !$bloqueos) $bloqueos[] = 'No hay gastos del banco para generar en ese mes.';
    // Facturas de proveedor (detalle y asiento) necesita al menos una línea con producto: las percepciones solas no forman un comprobante
    if ($impuestos && !$detalles) {
        $bloqueos[] = 'Las percepciones / SIRCREB no pueden ir solas: el resumen bancario necesita al menos un gasto con producto (comisión, ley 25.413). '
            . ($previos ? 'Deshacé el resumen ya generado del mes (Comprobantes generados) y volvé a generarlo con todas las líneas.'
                        : 'Incluí los gastos del mes en el mismo comprobante.');
    }

    $total = round(-array_sum(array_column($incluidas, 'importe')), 2);
    return [
        'cuenta' => $cta, 'mes' => $mes, 'f_emision' => $f_emision,
        'lineas' => $lineas, 'incluidas' => array_map('intval', array_column($incluidas, 'extracto_id')),
        'detalles' => $detalles, 'impuestos' => array_values($impuestos),
        'iva_extracto' => $iva_extracto, 'total' => $total,
        'tipo' => $tipo, 'bloqueos' => array_values(array_unique($bloqueos)), 'avisos' => $avisos,
    ];
}

/**
 * Genera el Resumen bancario y lo concilia. $fp_pagina_id: página de facturas de proveedor del módulo (57 / 557).
 * $op_punto_venta_id: PV de la OP automática, uno con la OP habilitada (0 = lo resuelve pagoResolverPuntoVenta).
 * Devuelve ['conciliacion_id', 'factura_proveedor_id', 'comprobante', 'total', 'mensajes'].
 */
function bco_gastos_generar(mysqli $c, int $empresa_id, int $usuario_id, int $cuenta_id, string $mes, array $excluir, int $fp_pagina_id,
                            int $op_punto_venta_id = 0): array
{
    foreach (['agregarFacturaProveedor', 'ejecutarTransicionEstado', 'pagoPuntosVentaOp'] as $fn) {
        if (!function_exists($fn)) throw new LogicException("bco_gastos_generar: falta $fn (incluir facturas_proveedores_model.php y pagos_model.php)");
    }
    if ($op_punto_venta_id > 0
        && !in_array($op_punto_venta_id, array_map('intval', array_column(pagoPuntosVentaOp($c, $empresa_id), 'punto_venta_id')), true)) {
        throw new RuntimeException('El punto de venta elegido para la orden de pago no tiene habilitada la OP o no tenés acceso a él.');
    }
    $lock = "bco_gastos_{$empresa_id}_{$cuenta_id}";
    if (intval(bco_fila($c, "SELECT GET_LOCK(?, 5) l", 's', $lock)['l'] ?? 0) !== 1) {
        throw new RuntimeException('Otro usuario está generando los gastos de esta cuenta: probá en unos segundos.');
    }
    $fp_id = 0;
    $estado_fp = 0;
    try {
        $r = bco_gastos_resumen($c, $empresa_id, $cuenta_id, $mes, $excluir);
        if ($r['bloqueos']) throw new RuntimeException(implode(' ', $r['bloqueos']));
        $cta = $r['cuenta'];
        $entidad_id = intval($cta['proveedor_entidad_id']);
        $tipo_id = intval($r['tipo']['comprobante_tipo_id']);

        // Número: AAAAMM + correlativo de 2 dígitos, PV = id de la cuenta (único por proveedor, tipo y cuenta, aun con anulados)
        $pv = $cuenta_id;
        $base = intval(str_replace('-', '', $mes)) * 100;
        $ult = bco_fila($c, "SELECT MAX(comprobante_nro) n FROM gestion__facturas_proveedores
                             WHERE empresa_id = ? AND entidad_id = ? AND comprobante_tipo_id = ? AND comprobante_pv = ? AND comprobante_nro BETWEEN ? AND ?",
            'iiiiii', $empresa_id, $entidad_id, $tipo_id, $pv, $base + 1, $base + 99)['n'] ?? null;
        $nro = $ult ? intval($ult) + 1 : $base + 1;
        if ($nro > $base + 99) throw new RuntimeException('Se agotó la numeración del resumen bancario del mes.');

        $suc_ent = bco_fila($c, "SELECT sucursal_id FROM gestion__entidades_sucursales WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                                 ORDER BY sucursal_id LIMIT 1", 'ii', $entidad_id, $empresa_id);
        $sucursal_id = intval($cta['sucursal_id'] ?? 0) ?: intval(bco_fila($c, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = ?
                                 AND tabla_estado_registro_id = 1 ORDER BY sucursal_id LIMIT 1", 'i', $empresa_id)['sucursal_id'] ?? 0);
        $contado = bco_fila($c, "SELECT condicion_pago_id FROM gestion__condiciones_pago WHERE empresa_id = ? AND tipo = 'CONTADO' AND tabla_estado_registro_id = 1
                                 ORDER BY condicion_pago_id LIMIT 1", 'i', $empresa_id);

        $detalles = [];
        $subtotal = $no_gravado = $iva = 0.0;
        foreach ($r['detalles'] as $d) {
            // neto_gravado como lo manda la pantalla: si la alícuota es No gravado, insertarDetallesFactura pasa la base a
            // no_gravado y fpAjustarTotalesReclasificados la descuenta del subtotal de la cabecera
            $detalles[] = ['producto_id' => $d['producto_id'], 'cantidad' => 1, 'precio_unitario' => $d['neto'], 'neto_gravado' => $d['neto'],
                           'iva_alicuota_id' => $d['iva_alicuota_id'], 'iva_porcentaje' => $d['iva_porcentaje'],
                           'no_gravado' => $d['no_gravado'], 'exento' => 0];
            $subtotal += $d['neto'];
            $no_gravado += $d['no_gravado'];
            $iva += $d['iva'];
        }
        $impuestos = array_map(static fn($i) => ['empresa_impuesto_config_id' => $i['empresa_impuesto_config_id'], 'importe' => $i['importe'],
                                                 'tipo_origen' => 'manual', 'detalle_origen' => 'Extracto bancario'], $r['impuestos']);
        $res = agregarFacturaProveedor($c, [
            'empresa_idx' => $empresa_id, 'pagina_idx' => $fp_pagina_id, 'comprobante_tipo_id' => $tipo_id,
            'comprobante_pv' => $pv, 'comprobante_nro' => $nro, 'entidad_id' => $entidad_id,
            'entidad_sucursal_id' => $suc_ent ? intval($suc_ent['sucursal_id']) : null, 'sucursal_id' => $sucursal_id ?: null,
            'f_emision' => $r['f_emision'], 'f_contabilidad' => $r['f_emision'], 'f_vencimiento' => $r['f_emision'],
            'condicion_pago_id' => $contado ? intval($contado['condicion_pago_id']) : null,
            'moneda_id' => intval($cta['moneda_id']), 'tipo_cambio' => 1,
            'subtotal' => round($subtotal, 2), 'no_gravado' => round($no_gravado, 2), 'exento' => 0, 'descuentos' => 0, 'impuestos' => round($iva, 2),
            'otros_impuestos' => 0, 'detalles' => $detalles, 'impuestos_adicionales' => $impuestos,
            'tesoreria_cuenta_debito_id' => $cuenta_id,
            'observaciones' => 'Resumen bancario ' . $cta['tesoreria_cuenta'] . ' ' . $mes . ' (generado desde Gastos Bancarios)',
        ]);
        if (empty($res['resultado'])) throw new RuntimeException('No se pudo crear el resumen bancario: ' . ($res['error'] ?? 'error desconocido'));
        $fp_id = intval($res['factura_proveedor_id']);
        $estado_fp = 3;
        if ($op_punto_venta_id > 0) {
            bco_exec($c, "UPDATE gestion__facturas_proveedores SET op_punto_venta_id = ? WHERE factura_proveedor_id = ? AND empresa_id = ?",
                'iii', $op_punto_venta_id, $fp_id, $empresa_id);
        }
        $tot = floatval(bco_fila($c, "SELECT total FROM gestion__facturas_proveedores WHERE factura_proveedor_id = ? AND empresa_id = ?", 'ii', $fp_id, $empresa_id)['total'] ?? 0);
        if (abs($tot - $r['total']) >= BCO_TOLERANCIA) {
            throw new RuntimeException('El total del comprobante (' . bco_fmt($tot) . ') no coincide con los gastos del extracto (' . bco_fmt($r['total']) . ').');
        }

        foreach (['aprobar' => 4, 'confirmar' => 5] as $accion => $destino) {
            $t = ejecutarTransicionEstado($c, $fp_id, $accion, $empresa_id, $fp_pagina_id);
            if (empty($t['success'])) throw new RuntimeException('No se pudo ' . $accion . ' el resumen bancario: ' . ($t['error'] ?? ''));
            $estado_fp = $destino;
            $mensajes = $t['message'] ?? '';
        }

        // Movimiento de tesorería de la OP automática (débito en la cuenta)
        $fp = bco_fila($c, "SELECT f.comprobante_id, f.total, f.pago_automatico_id, p.comprobante_id AS op_comprobante_id
                            FROM gestion__facturas_proveedores f LEFT JOIN gestion__pagos p ON p.pago_id = f.pago_automatico_id AND p.empresa_id = f.empresa_id
                            WHERE f.factura_proveedor_id = ? AND f.empresa_id = ?", 'ii', $fp_id, $empresa_id);
        mysqli_begin_transaction($c);
        try {
            // Se vuelve a validar todo con lock (extracto -> tesorería): mientras se confirmaba el comprobante (con sus
            // propias transacciones) otro usuario pudo anular el lote, reclasificar una línea o conciliar el movimiento
            $ids = $r['incluidas'];
            sort($ids);
            $conceptos = array_column($r['lineas'], 'concepto_id', 'extracto_id');
            $ls = bco_filas($c, "SELECT x.extracto_id, x.importe, x.estado, x.concepto_id, k.naturaleza FROM gestion__bancos_extracto x
                                 LEFT JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id
                                 WHERE x.empresa_id = ? AND x.extracto_id IN (" . bco_placeholders($ids) . ")
                                 ORDER BY x.extracto_id FOR UPDATE", 'i' . str_repeat('i', count($ids)), $empresa_id, ...$ids);
            if (count($ls) !== count($ids)) throw new RuntimeException('Alguna línea del extracto se quitó mientras se generaba el comprobante.');
            foreach ($ls as $l) {
                if ($l['estado'] !== 'CLASIFICADA' || $l['naturaleza'] !== 'GASTO_BANCARIO'
                    || intval($l['concepto_id']) !== intval($conceptos[$l['extracto_id']] ?? 0)) {
                    throw new RuntimeException('Alguna línea cambió mientras se generaba el comprobante.');
                }
            }
            $movs = $fp['op_comprobante_id'] ? bco_filas($c, "SELECT tesoreria_movimiento_id, IF(tipo_movimiento = 'INGRESO', importe, -importe) AS importe
                                FROM gestion__tesoreria_movimientos WHERE empresa_id = ? AND comprobante_id = ? AND tesoreria_cuenta_id = ?
                                  AND tabla_estado_registro_id = 1 AND conciliado = 0 AND reversa_de_movimiento_id IS NULL
                                ORDER BY tesoreria_movimiento_id FOR UPDATE",
                'iii', $empresa_id, intval($fp['op_comprobante_id']), $cuenta_id) : [];
            $comprobante_id = intval($fp['comprobante_id']);
            $te = round(array_sum(array_column($ls, 'importe')), 2);
            $tt = round(array_sum(array_column($movs, 'importe')), 2);
            if (!$movs || abs($te - $tt) >= BCO_TOLERANCIA || abs($te + $r['total']) >= BCO_TOLERANCIA) {
                throw new RuntimeException('La orden de pago automática no debitó el total de las líneas en la cuenta (banco ' . bco_fmt($te)
                    . ', tesorería ' . bco_fmt($tt) . '): revisá el medio "Débito en cuenta" (DEB_CTA).');
            }
            $obs = 'Resumen bancario ' . $mes;
            bco_exec($c, "INSERT INTO gestion__bancos_conciliaciones (empresa_id, tesoreria_cuenta_id, origen, comprobante_id, factura_proveedor_id, f_conciliacion,
                                 importe_extracto, importe_tesoreria, diferencia, observaciones, tabla_estado_registro_id, usuario_id)
                          VALUES (?, ?, 'GASTOS', ?, ?, ?, ?, ?, 0, ?, 5, ?)",
                'iiiisddsi', $empresa_id, $cuenta_id, $comprobante_id, $fp_id, $r['f_emision'], $te, $tt, $obs, $usuario_id);
            $conc_id = intval(mysqli_insert_id($c));
            bco_conciliacion_items($c, $conc_id, $ls, $movs);
            bco_marcar_conciliados($c, $empresa_id, $usuario_id, $conc_id, $r['f_emision'], $ids,
                array_map('intval', array_column($movs, 'tesoreria_movimiento_id')));
            mysqli_commit($c);
        } catch (Throwable $e) {
            mysqli_rollback($c);
            throw $e;
        }
        error_log("=== bco_gastos_generar empresa $empresa_id cuenta $cuenta_id $mes: FP $fp_id, conciliacion $conc_id, total {$r['total']} ===");
        return ['conciliacion_id' => $conc_id, 'factura_proveedor_id' => $fp_id,
                'comprobante' => $r['tipo']['comprobante_tipo'] . ' ' . sprintf('%05d-%08d', $pv, $nro),
                'total' => $r['total'], 'mensajes' => $mensajes ?? ''];
    } catch (Throwable $e) {
        if ($fp_id > 0 && !bco_gastos_compensar($c, $empresa_id, $fp_id, $estado_fp, $fp_pagina_id, $e->getMessage())) {
            throw new RuntimeException($e->getMessage() . " Atención: el resumen bancario (Facturas de Proveedores, id $fp_id) no se pudo dejar sin efecto: anulalo o eliminalo allí.");
        }
        throw $e;
    } finally {
        bco_fila($c, "SELECT RELEASE_LOCK(?) l", 's', $lock);
    }
}

/** Deja sin efecto el comprobante a medio generar: borrador o pendiente -> eliminado; confirmado -> anulado. */
function bco_gastos_compensar(mysqli $c, int $empresa_id, int $fp_id, int $estado, int $fp_pagina_id, string $motivo): bool
{
    try {
        if ($estado === 5) {
            $t = ejecutarTransicionEstado($c, $fp_id, 'anular', $empresa_id, $fp_pagina_id, 'Generación de gastos bancarios fallida: ' . mb_substr($motivo, 0, 150));
        } else {
            if ($estado === 4) {   // "Pendiente de aprobación" no tiene vuelta atrás en el motor: vuelve a borrador para eliminarlo
                bco_exec($c, "UPDATE gestion__facturas_proveedores SET tabla_estado_registro_id = 3 WHERE factura_proveedor_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 4",
                    'ii', $fp_id, $empresa_id);
            }
            $t = ejecutarTransicionEstado($c, $fp_id, 'eliminar', $empresa_id, $fp_pagina_id);
        }
        if (empty($t['success'])) {
            error_log("bco_gastos_compensar: FP $fp_id quedó en estado $estado: " . ($t['error'] ?? ''));
            return false;
        }
        return true;
    } catch (Throwable $e) {
        error_log("bco_gastos_compensar: FP $fp_id quedó en estado $estado: " . $e->getMessage());
        return false;
    }
}

/** Deshace los gastos: anula el comprobante (revierte la OP del débito) y libera las líneas. */
function bco_gastos_deshacer(mysqli $c, int $empresa_id, int $usuario_id, int $conciliacion_id, string $motivo, int $fp_pagina_id): string
{
    $motivo = mb_substr(trim($motivo), 0, 300);
    if ($motivo === '') throw new RuntimeException('Indicá el motivo.');
    $cta = bco_fila($c, "SELECT tesoreria_cuenta_id FROM gestion__bancos_conciliaciones WHERE conciliacion_id = ? AND empresa_id = ?", 'ii', $conciliacion_id, $empresa_id)
        ?? throw new RuntimeException('Conciliación no encontrada.');
    $lock = "bco_gastos_{$empresa_id}_{$cta['tesoreria_cuenta_id']}";
    if (intval(bco_fila($c, "SELECT GET_LOCK(?, 5) l", 's', $lock)['l'] ?? 0) !== 1) {
        throw new RuntimeException('Otro usuario está trabajando con los gastos de esta cuenta: probá en unos segundos.');
    }
    try {
        return bco_gastos_deshacer_bloqueado($c, $empresa_id, $usuario_id, $conciliacion_id, $motivo, $fp_pagina_id);
    } finally {
        bco_fila($c, "SELECT RELEASE_LOCK(?) l", 's', $lock);
    }
}

function bco_gastos_deshacer_bloqueado(mysqli $c, int $empresa_id, int $usuario_id, int $conciliacion_id, string $motivo, int $fp_pagina_id): string
{
    $k = bco_fila($c, "SELECT origen, factura_proveedor_id, tabla_estado_registro_id FROM gestion__bancos_conciliaciones WHERE conciliacion_id = ? AND empresa_id = ?",
        'ii', $conciliacion_id, $empresa_id) ?? throw new RuntimeException('Conciliación no encontrada.');
    if ($k['origen'] !== 'GASTOS' || intval($k['tabla_estado_registro_id']) !== 5) throw new RuntimeException('Solo se deshacen gastos bancarios vigentes.');
    $fp_id = intval($k['factura_proveedor_id']);
    $fp = bco_fila($c, "SELECT tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = ? AND empresa_id = ?", 'ii', $fp_id, $empresa_id);
    $mensaje = '';
    if ($fp && intval($fp['tabla_estado_registro_id']) === 5) {
        $t = ejecutarTransicionEstado($c, $fp_id, 'anular', $empresa_id, $fp_pagina_id, $motivo);
        if (empty($t['success'])) throw new RuntimeException('No se pudo anular el resumen bancario: ' . ($t['error'] ?? ''));
        $mensaje = $t['message'] ?? '';
    }
    try {
        // Al anular el comprobante se revierte la OP del débito y tesoreriaAnularMovimiento ya deshace la conciliación
        if (bco_fila($c, "SELECT 1 FROM gestion__bancos_conciliaciones WHERE conciliacion_id = ? AND tabla_estado_registro_id = 5", 'i', $conciliacion_id)) {
            bco_conciliacion_deshacer($c, $empresa_id, $usuario_id, $conciliacion_id, $motivo, 'GASTOS');
        }
    } catch (Throwable $e) {
        error_log("bco_gastos_deshacer: FP $fp_id anulado pero la conciliación $conciliacion_id no se deshizo: " . $e->getMessage());
        throw $e;
    }
    return $mensaje;
}
