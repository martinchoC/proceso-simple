<?php
/**
 * Conciliación bancaria: recibos de cobranza generados desde el extracto. Una línea de crédito sin conciliar cuya
 * referencia (o, si no, la descripción) tiene el CUIT de un cliente de la empresa ofrece "Generar recibo" cuando:
 *   - el CUIT es de una sola entidad activa con rol cliente y no es el de la propia empresa;
 *   - no hay en tesorería un movimiento sin conciliar de ese cliente con el mismo importe en ±BCO_VENTANA_DIAS
 *     (el recibo ya se cargó: se concilia, RECIBO_CARGADO);
 *   - no hay un recibo en borrador del cliente con el mismo importe en esa ventana (BORRADOR).
 * Las cobranzas del cliente en el mes de la línea son solo un aviso (no bloquean: puede pagar dos facturas).
 *
 * El recibo es una cobranza común (guardarCobranzaBorrador / confirmarCobranza del módulo): medio de cobro de
 * tipo banco hacia la cuenta del extracto, fecha = la de la línea, punto de venta y tipo elegidos por el usuario.
 * Imputación propuesta: la factura pendiente más vieja (emitida hasta la fecha de la línea) cuyo saldo es igual al
 * importe o, si no hay, las más viejas cuyos saldos suman exactamente el importe; si no, a cuenta. El usuario la
 * puede cambiar. A la línea se le asigna el concepto de cobranzas de la empresa (es_cobranza en Conceptos Bancarios).
 * Confirmado: en la misma transacción se confirma el recibo y se concilia la línea con su movimiento de tesorería.
 * Sin confirmar: queda en Borrador y la línea clasificada; al confirmarlo en Cobranzas se concilia con Sugerencias.
 *
 * El llamador (bancos_conciliacion_model.php de cada módulo) incluye ANTES su cobranzas_model.php y pasa la
 * página de Cobranzas de su módulo (105 / 605, bco_pagina_hermana).
 */

require_once __DIR__ . '/bancos_conciliacion_service.php';
require_once __DIR__ . '/../entidades_roles.php';
require_once __DIR__ . '/../empresa_config.php';

const BCO_CUIT_REGEX = '/(?<!\d)(?:20|23|24|25|26|27|30|33|34)\d{9}(?!\d)/';

/** Dígito verificador del CUIT / CUIL. */
function bco_cuit_valido(string $cuit): bool
{
    if (!preg_match('/^\d{11}$/', $cuit)) return false;
    $suma = 0;
    foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $p) $suma += intval($cuit[$i]) * $p;
    $dv = 11 - $suma % 11;
    $dv = $dv === 11 ? 0 : ($dv === 10 ? 9 : $dv);
    return $dv === intval($cuit[10]);
}

/** CUITs de la línea: la referencia si es un CUIT y, si no, los que aparezcan en la descripción. */
function bco_linea_cuits(array $l): array
{
    $ref = preg_replace('/\D/', '', (string)($l['referencia'] ?? ''));
    if (bco_cuit_valido($ref)) return [$ref];
    preg_match_all(BCO_CUIT_REGEX, (string)($l['descripcion'] ?? ''), $m);
    return array_values(array_unique(array_filter($m[0], 'bco_cuit_valido')));
}

/** Concepto de cobranzas activo de la empresa (Conceptos Bancarios, es_cobranza), o null. */
function bco_concepto_cobranzas(mysqli $c, int $empresa_id): ?array
{
    return bco_fila($c, "SELECT concepto_id, codigo, concepto FROM gestion__bancos_conceptos
                         WHERE empresa_id = ? AND es_cobranza = 1 AND naturaleza = 'MOVIMIENTO_PROPIO' AND tabla_estado_registro_id = 1
                         ORDER BY concepto_id LIMIT 1", 'i', $empresa_id);
}

/**
 * Para las líneas dadas (las de la grilla del banco), el cliente y qué se puede hacer: extracto_id => [estado, entidad_id,
 * entidad_nombre, cuit, cobranza_id]. estado: GENERAR / RECIBO_CARGADO / BORRADOR. Las líneas sin cliente no aparecen.
 */
function bco_cobranzas_marcar(mysqli $c, int $empresa_id, int $cuenta_id, array $lineas): array
{
    $propio = preg_replace('/\D/', '', emp_cuit($c, $empresa_id));
    $cand = [];
    foreach ($lineas as $l) {
        if (floatval($l['importe']) <= 0 || !in_array($l['estado'], ['PENDIENTE', 'CLASIFICADA'], true) || ($l['naturaleza'] ?? '') === 'GASTO_BANCARIO') continue;
        $cuits = array_values(array_diff(bco_linea_cuits($l), [$propio]));
        if ($cuits) $cand[] = ['l' => $l, 'cuits' => $cuits];
    }
    if (!$cand) return [];

    $todos = array_values(array_unique(array_merge(...array_column($cand, 'cuits'))));
    $por_cuit = [];
    foreach (bco_filas($c, "SELECT e.entidad_id, e.entidad_nombre, e.cuit FROM gestion__entidades e
                            WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND e.cuit IN (" . bco_placeholders($todos) . ")
                              AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI'), 'i' . str_repeat('s', count($todos)), $empresa_id, ...$todos) as $e) {
        $por_cuit[(string)$e['cuit']][] = $e;
    }
    $con_cliente = [];
    foreach ($cand as $k) {
        $ents = [];
        foreach ($k['cuits'] as $cuit) foreach ($por_cuit[$cuit] ?? [] as $e) $ents[intval($e['entidad_id'])] = $e;
        if (count($ents) === 1) $con_cliente[] = ['l' => $k['l'], 'e' => reset($ents)];   // CUIT de varias entidades: no se adivina
    }
    if (!$con_cliente) return [];

    $fechas = array_map(static fn($k) => $k['l']['f_movimiento'], $con_cliente);
    $d = date('Y-m-d', strtotime(min($fechas) . ' -' . BCO_VENTANA_DIAS . ' days'));
    $h = date('Y-m-d', strtotime(max($fechas) . ' +' . BCO_VENTANA_DIAS . ' days'));
    $movs = bco_tesoreria_pendientes($c, $empresa_id, $cuenta_id, $d, $h);
    $ents = array_values(array_unique(array_map(static fn($k) => intval($k['e']['entidad_id']), $con_cliente)));
    $borradores = bco_filas($c, "SELECT cobranza_id, entidad_id, f_emision, importe_total FROM gestion__cobranzas
                                 WHERE empresa_id = ? AND tabla_estado_registro_id = 3 AND f_emision BETWEEN ? AND ?
                                   AND entidad_id IN (" . bco_placeholders($ents) . ") ORDER BY cobranza_id",
        'iss' . str_repeat('i', count($ents)), $empresa_id, $d, $h, ...$ents);

    $dias = static fn($a, $b) => abs((strtotime($a) - strtotime($b)) / 86400);
    $usadosM = $usadosB = [];
    $out = [];
    foreach ($con_cliente as $k) {
        $l = $k['l'];
        $e = $k['e'];
        $info = ['estado' => 'GENERAR', 'entidad_id' => intval($e['entidad_id']), 'entidad_nombre' => $e['entidad_nombre'], 'cuit' => (string)$e['cuit'], 'cobranza_id' => null];
        foreach ($movs as $m) {
            if (isset($usadosM[$m['tesoreria_movimiento_id']]) || (string)$m['cuit'] !== (string)$e['cuit']) continue;
            if (abs(floatval($m['importe']) - floatval($l['importe'])) < BCO_TOLERANCIA && $dias($m['f_movimiento'], $l['f_movimiento']) <= BCO_VENTANA_DIAS) {
                $usadosM[$m['tesoreria_movimiento_id']] = true;
                $info['estado'] = 'RECIBO_CARGADO';
                break;
            }
        }
        if ($info['estado'] === 'GENERAR') {
            foreach ($borradores as $b) {
                if (isset($usadosB[$b['cobranza_id']]) || intval($b['entidad_id']) !== $info['entidad_id']) continue;
                if (abs(floatval($b['importe_total']) - floatval($l['importe'])) < BCO_TOLERANCIA && $dias($b['f_emision'], $l['f_movimiento']) <= BCO_VENTANA_DIAS) {
                    $usadosB[$b['cobranza_id']] = true;
                    $info['estado'] = 'BORRADOR';
                    $info['cobranza_id'] = intval($b['cobranza_id']);
                    break;
                }
            }
        }
        $out[intval($l['extracto_id'])] = $info;
    }
    return $out;
}

/** Línea del extracto con su concepto ($bloquear: FOR UPDATE, dentro de una transacción). */
function bco_cobranza_linea(mysqli $c, int $empresa_id, int $extracto_id, bool $bloquear = false): array
{
    $l = bco_fila($c, "SELECT x.extracto_id, x.tesoreria_cuenta_id, x.f_movimiento, x.descripcion, x.referencia, x.importe, x.estado, x.concepto_id, k.naturaleza
                       FROM gestion__bancos_extracto x LEFT JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id AND k.empresa_id = x.empresa_id
                       WHERE x.extracto_id = ? AND x.empresa_id = ?" . ($bloquear ? ' FOR UPDATE' : ''), 'ii', $extracto_id, $empresa_id)
        ?? throw new RuntimeException('La línea del banco no existe.');
    bco_cuenta($c, $empresa_id, intval($l['tesoreria_cuenta_id']));
    return $l;
}

/** Cliente de la línea si se le puede generar el recibo; si no, excepción con el motivo. */
function bco_cobranza_cliente(mysqli $c, int $empresa_id, array $l): array
{
    if (!in_array($l['estado'], ['PENDIENTE', 'CLASIFICADA'], true)) throw new RuntimeException('La línea ya está conciliada o ignorada: recargá.');
    if (floatval($l['importe']) <= 0) throw new RuntimeException('Solo se generan recibos de los créditos del banco.');
    $info = bco_cobranzas_marcar($c, $empresa_id, intval($l['tesoreria_cuenta_id']), [$l])[intval($l['extracto_id'])] ?? null;
    if (!$info) throw new RuntimeException('La línea no tiene el CUIT de un cliente de la empresa (o el CUIT es de más de una entidad).');
    if ($info['estado'] === 'RECIBO_CARGADO') throw new RuntimeException('El recibo de esta transferencia ya está cargado en tesorería: conciliala con Sugerencias.');
    if ($info['estado'] === 'BORRADOR') throw new RuntimeException("Ya hay un recibo en borrador de este cliente por el mismo importe (cobranza {$info['cobranza_id']}): confirmalo en Cobranzas.");
    return $info;
}

/** Medios de cobro que van a una cuenta bancaria (transferencia primero). */
function bco_cobranza_medios(mysqli $c, int $empresa_id): array
{
    return bco_filas($c, "SELECT medio_pago_id, codigo, medio_pago, medio_pago_tipo FROM gestion__medios_pago
                          WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND aplica_cobro = 1 AND tesoreria_cuenta_tipo = 'BANCO'
                            AND medio_pago_tipo NOT IN ('RETENCION', 'CHEQUE_PROPIO', 'CHEQUE_TERCERO')
                          ORDER BY (medio_pago_tipo = 'TRANSFERENCIA') DESC, orden, medio_pago", 'i', $empresa_id);
}

/** Comprobantes del cliente que puede cancelar un recibo, emitidos hasta la fecha (más viejo primero). */
function bco_cobranza_pendientes(mysqli $c, int $empresa_id, int $entidad_id, string $hasta): array
{
    return array_values(array_filter(tesoreriaObtenerComprobantesCancelables($c, $empresa_id, 'RCX', $entidad_id),
        static fn($p) => substr((string)$p['f_emision'], 0, 10) <= $hasta && intval($p['signo'] ?? 1) >= 0));
}

/**
 * Imputación propuesta: la pendiente más vieja con saldo igual al importe o, si no hay, las más viejas cuyos saldos
 * suman exactamente el importe (en orden, sin saltear). Sin coincidencia: [] (a cuenta).
 */
function bco_cobranza_propuesta(array $pendientes, float $importe): array
{
    foreach ($pendientes as $p) {
        if (abs(floatval($p['importe_pendiente']) - $importe) < BCO_TOLERANCIA) {
            return [['comprobante_destino_id' => intval($p['comprobante_id']), 'importe_imputado' => round(floatval($p['importe_pendiente']), 2)]];
        }
    }
    $acum = 0.0;
    $imps = [];
    foreach ($pendientes as $p) {
        $acum = round($acum + floatval($p['importe_pendiente']), 2);
        $imps[] = ['comprobante_destino_id' => intval($p['comprobante_id']), 'importe_imputado' => round(floatval($p['importe_pendiente']), 2)];
        if (abs($acum - $importe) < BCO_TOLERANCIA) return $imps;
        if ($acum > $importe) break;
    }
    return [];
}

/** Datos del modal "Generar recibo": línea, cliente, aviso del mes, puntos de venta, medios, pendientes y propuesta. */
function bco_cobranza_preparar(mysqli $c, int $empresa_id, int $extracto_id, int $pagina_cobranzas): array
{
    $l = bco_cobranza_linea($c, $empresa_id, $extracto_id);
    $cli = bco_cobranza_cliente($c, $empresa_id, $l);
    $concepto = bco_concepto_cobranzas($c, $empresa_id)
        ?? throw new RuntimeException('Marcá en Conceptos Bancarios cuál es el concepto de cobranzas (un movimiento propio, por ejemplo Transferencias).');
    $mes = substr($l['f_movimiento'], 0, 7);
    $del_mes = bco_filas($c, "SELECT cobranza_id, f_emision, importe_total, comprobante_pv, comprobante_nro, tabla_estado_registro_id
                              FROM gestion__cobranzas WHERE empresa_id = ? AND entidad_id = ? AND f_emision BETWEEN ? AND ?
                                AND tabla_estado_registro_id NOT IN (6, 38) ORDER BY f_emision, cobranza_id",
        'iiss', $empresa_id, $cli['entidad_id'], "$mes-01", date('Y-m-t', strtotime("$mes-01")));
    foreach ($del_mes as &$k) {
        $k['numero'] = intval($k['comprobante_nro']) > 0 ? emp_numero_comprobante($c, $empresa_id, intval($k['comprobante_pv']), intval($k['comprobante_nro'])) : null;
        $k['borrador'] = intval($k['tabla_estado_registro_id']) === 3;
    }
    unset($k);
    $pendientes = bco_cobranza_pendientes($c, $empresa_id, $cli['entidad_id'], $l['f_movimiento']);
    return [
        'linea' => $l,
        'cliente' => $cli,
        'concepto' => $concepto,
        'cobranzas_mes' => $del_mes,
        'puntos_venta' => obtenerPuntosVentaCobranza($c, $empresa_id, $pagina_cobranzas),
        'medios' => bco_cobranza_medios($c, $empresa_id),
        'pendientes' => $pendientes,
        'propuesta' => bco_cobranza_propuesta($pendientes, floatval($l['importe'])),
    ];
}

/** Tipos de recibo de un punto de venta de cobranzas. */
function bco_cobranza_tipos(mysqli $c, int $empresa_id, int $punto_venta_id, int $pagina_cobranzas): array
{
    return obtenerTiposComprobanteCobranza($c, $empresa_id, $punto_venta_id, $pagina_cobranzas);
}

/**
 * Genera el recibo de la línea. $d: punto_venta_id, comprobante_tipo_id, medio_pago_id, observaciones,
 * imputaciones [{comprobante_destino_id, importe_imputado}], confirmar (confirma y concilia; si no, queda en Borrador).
 * Una sola transacción. Devuelve [cobranza_id, confirmado, numero, conciliacion_id].
 */
function bco_cobranza_generar(mysqli $c, int $empresa_id, int $usuario_id, int $extracto_id, array $d, int $pagina_cobranzas): array
{
    $pv = intval($d['punto_venta_id'] ?? 0);
    $tipo = intval($d['comprobante_tipo_id'] ?? 0);
    $medio = intval($d['medio_pago_id'] ?? 0);
    $confirmar = !empty($d['confirmar']);
    $obs = mb_substr(trim((string)($d['observaciones'] ?? '')), 0, 500);

    mysqli_begin_transaction($c);
    try {
        $l = bco_cobranza_linea($c, $empresa_id, $extracto_id, true);
        $cli = bco_cobranza_cliente($c, $empresa_id, $l);
        $concepto = bco_concepto_cobranzas($c, $empresa_id) ?? throw new RuntimeException('Marcá en Conceptos Bancarios cuál es el concepto de cobranzas.');
        $importe = round(floatval($l['importe']), 2);
        $cuenta_id = intval($l['tesoreria_cuenta_id']);

        $pv_fila = null;
        foreach (obtenerPuntosVentaCobranza($c, $empresa_id, $pagina_cobranzas) as $p) {
            if (intval($p['punto_venta_id']) === $pv) $pv_fila = $p;
        }
        if (!$pv_fila) throw new RuntimeException('Elegí el punto de venta del recibo.');
        if (!in_array($tipo, array_map('intval', array_column(obtenerTiposComprobanteCobranza($c, $empresa_id, $pv, $pagina_cobranzas), 'comprobante_tipo_id')), true)) {
            throw new RuntimeException('Elegí el tipo de recibo (habilitado en ese punto de venta).');
        }
        if (!in_array($medio, array_map('intval', array_column(bco_cobranza_medios($c, $empresa_id), 'medio_pago_id')), true)) {
            throw new RuntimeException('Elegí el medio de cobro.');
        }

        // Imputaciones: solo a comprobantes pendientes del cliente, sin superar su saldo ni el importe de la línea
        $pend = [];
        foreach (bco_cobranza_pendientes($c, $empresa_id, $cli['entidad_id'], $l['f_movimiento']) as $p) $pend[intval($p['comprobante_id'])] = floatval($p['importe_pendiente']);
        $imps = [];
        $total = 0.0;
        foreach ((array)($d['imputaciones'] ?? []) as $i) {
            $dest = intval($i['comprobante_destino_id'] ?? 0);
            $monto = round(floatval($i['importe_imputado'] ?? 0), 2);
            if ($monto <= 0) continue;
            if (!isset($pend[$dest]) || isset($imps[$dest])) throw new RuntimeException('Alguna factura elegida ya no está pendiente para este cliente: volvé a abrir el recibo.');
            if ($monto - $pend[$dest] >= BCO_TOLERANCIA) throw new RuntimeException('Lo imputado a una factura supera su saldo.');
            $imps[$dest] = ['comprobante_destino_id' => $dest, 'importe_imputado' => $monto];
            $total += $monto;
        }
        if (round($total, 2) - $importe >= BCO_TOLERANCIA) throw new RuntimeException('Lo imputado supera el importe de la transferencia.');

        $ref = trim((string)$l['referencia']) !== '' ? (string)$l['referencia'] : (string)$l['descripcion'];
        $cobranza_id = bco_cobranza_llamar(static fn() => guardarCobranzaBorrador($c, [
            'empresa_id' => $empresa_id,
            'sucursal_id' => intval($pv_fila['sucursal_id']),
            'entidad_id' => $cli['entidad_id'],
            'f_emision' => $l['f_movimiento'],
            'comprobante_pv' => $pv,
            'comprobante_tipo_id' => $tipo,
            'observaciones' => $obs !== '' ? $obs : 'Transferencia del extracto bancario: ' . mb_substr((string)$l['descripcion'], 0, 200),
            'usuario_id' => $usuario_id,
            'medios' => [['medio_pago_id' => $medio, 'tesoreria_cuenta_id' => $cuenta_id, 'importe' => $importe, 'importe_comprobante' => $importe,
                          'nro_operacion' => mb_substr($ref, 0, 50), 'f_valor' => $l['f_movimiento']]],
            'imputaciones' => array_values($imps),
        ]));

        // La línea queda con el concepto de cobranzas (movimiento propio): así se puede conciliar
        bco_exec($c, "UPDATE gestion__bancos_extracto SET concepto_id = ?, regla_id = NULL, concepto_origen = 'MANUAL', estado = 'CLASIFICADA', usuario_modificacion_id = ?
                      WHERE extracto_id = ? AND empresa_id = ?", 'iiii', intval($concepto['concepto_id']), $usuario_id, $extracto_id, $empresa_id);

        $r = ['cobranza_id' => intval($cobranza_id), 'confirmado' => false, 'numero' => null, 'conciliacion_id' => null];
        if ($confirmar) {
            $conf = bco_cobranza_llamar(static fn() => confirmarCobranza($c, $cobranza_id, $empresa_id, $usuario_id, true));
            $r['confirmado'] = true;
            $r['numero'] = emp_numero_comprobante($c, $empresa_id, $pv, intval($conf['comprobante_nro']));
            $mov = bco_fila($c, "SELECT tesoreria_movimiento_id FROM gestion__tesoreria_movimientos
                                 WHERE empresa_id = ? AND comprobante_id = ? AND tesoreria_cuenta_id = ? AND tipo_movimiento = 'INGRESO'
                                   AND conciliado = 0 AND tabla_estado_registro_id = 1 ORDER BY tesoreria_movimiento_id LIMIT 1",
                'iii', $empresa_id, intval($conf['comprobante_id']), $cuenta_id)
                ?? throw new RuntimeException('El recibo se confirmó pero no generó el movimiento en la cuenta bancaria.');
            $r['conciliacion_id'] = bco_conciliar($c, $empresa_id, $usuario_id, $cuenta_id, [$extracto_id], [intval($mov['tesoreria_movimiento_id'])],
                'Recibo ' . $r['numero'] . ' generado desde el extracto', true);
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    error_log("=== bco_cobranza_generar empresa $empresa_id línea $extracto_id: cobranza {$r['cobranza_id']}" . ($r['confirmado'] ? " confirmada, conciliación {$r['conciliacion_id']}" : ' en borrador') . ' ===');
    return $r;
}

/** Las funciones de cobranzas lanzan Exception con mensajes para el usuario: se pasan como RuntimeException. */
function bco_cobranza_llamar(callable $fn)
{
    try {
        return $fn();
    } catch (mysqli_sql_exception $e) {
        throw $e;
    } catch (RuntimeException $e) {
        throw $e;
    } catch (Exception $e) {
        throw new RuntimeException($e->getMessage(), 0, $e);
    }
}
