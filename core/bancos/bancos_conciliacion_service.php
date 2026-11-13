<?php
/**
 * Conciliación bancaria contra tesorería (§6 del diseño). El extracto nunca crea movimientos: se une a los que ya
 * están en gestion__tesoreria_movimientos. Cabecera + items N:M (gestion__bancos_conciliaciones, tabla 447,
 * estados 5 vigente / 38 deshecha). Una conciliación marca, en una transacción y con locks en orden ascendente:
 *   extracto.estado = CONCILIADA + conciliacion_id, y tesoreria_movimientos.conciliado = 1 + f_conciliacion + conciliacion_id.
 * Solo concilia líneas CLASIFICADA de conceptos MOVIMIENTO_PROPIO: los gastos del banco van por bancos_gastos_service.
 *
 * Movimientos de tesorería candidatos: de la cuenta, vigentes, sin conciliar, que no son una reversa ni fueron
 * revertidos (el par movimiento + contramovimiento se anula y no aparece en el banco). Importe con signo:
 * INGRESO > 0, EGRESO < 0, igual que el extracto.
 */

require_once __DIR__ . '/bancos_extracto_service.php';

const BCO_VENTANA_DIAS = 5;          // diferencia de fecha aceptada entre el banco y tesorería
const BCO_GRUPO_MAX_CANDIDATOS = 12; // agrupación N:1: candidatos y tamaño de combinación acotados
const BCO_GRUPO_MAX_TAMANIO = 4;

/** Movimientos de tesorería sin conciliar de la cuenta (o los ids pedidos), con datos para sugerir. */
function bco_tesoreria_pendientes(mysqli $c, int $empresa_id, int $cuenta_id, ?string $desde = null, ?string $hasta = null, array $ids = []): array
{
    $sql = "SELECT m.tesoreria_movimiento_id, m.f_movimiento, m.f_valor, m.tipo_movimiento,
                   IF(m.tipo_movimiento = 'INGRESO', m.importe, -m.importe) AS importe, m.concepto, m.comprobante_id,
                   e.entidad_nombre, e.cuit, mp.medio_pago, ch.numero AS cheque_numero, m.conciliado, m.conciliacion_id
            FROM gestion__tesoreria_movimientos m
            LEFT JOIN gestion__comprobantes cp ON cp.comprobante_id = m.comprobante_id AND cp.empresa_id = m.empresa_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = cp.entidad_id
            LEFT JOIN gestion__medios_pago mp ON mp.medio_pago_id = m.medio_pago_id
            LEFT JOIN gestion__cheques ch ON ch.cheque_id = m.cheque_id
            WHERE m.empresa_id = ? AND m.tesoreria_cuenta_id = ? AND m.tabla_estado_registro_id = 1
              AND m.reversa_de_movimiento_id IS NULL
              AND NOT EXISTS (SELECT 1 FROM gestion__tesoreria_movimientos r WHERE r.reversa_de_movimiento_id = m.tesoreria_movimiento_id)";
    $tipos = 'ii';
    $p = [$empresa_id, $cuenta_id];
    if ($ids) {
        $sql .= ' AND m.tesoreria_movimiento_id IN (' . bco_placeholders($ids) . ')';
        $tipos .= str_repeat('i', count($ids));
        array_push($p, ...$ids);
    } else {
        $sql .= ' AND m.conciliado = 0';
        if ($desde) { $sql .= ' AND m.f_movimiento >= ?'; $tipos .= 's'; $p[] = $desde; }
        if ($hasta) { $sql .= ' AND m.f_movimiento <= ?'; $tipos .= 's'; $p[] = $hasta; }
    }
    $sql .= ' ORDER BY m.f_movimiento, m.tesoreria_movimiento_id';
    return bco_filas($c, $sql, $tipos, ...$p);
}

/** Primer día con extracto importado de la cuenta: tesorería anterior a eso no se puede conciliar con el banco. */
function bco_inicio_extractos(mysqli $c, int $empresa_id, int $cuenta_id): string
{
    return bco_fila($c, "SELECT MIN(f_desde) d FROM gestion__bancos_importaciones WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND tabla_estado_registro_id = 5",
        'ii', $empresa_id, $cuenta_id)['d'] ?? date('Y-m-01');
}

/** Pantalla de conciliación: las dos grillas y los totales de la cuenta en el período. */
function bco_conciliacion_datos(mysqli $c, int $empresa_id, int $cuenta_id, ?string $desde, ?string $hasta, bool $con_ignoradas = false): array
{
    $cta = bco_cuenta($c, $empresa_id, $cuenta_id);
    $hasta = $hasta ?: date('Y-m-d');
    $desde = $desde ?: bco_inicio_extractos($c, $empresa_id, $cuenta_id);
    $lineas = bco_extracto_lineas($c, $empresa_id, ['cuenta_id' => $cuenta_id, 'desde' => $desde, 'hasta' => $hasta,
                                                     'estados' => $con_ignoradas ? ['PENDIENTE', 'CLASIFICADA', 'IGNORADA'] : ['PENDIENTE', 'CLASIFICADA']]);
    $movs = bco_tesoreria_pendientes($c, $empresa_id, $cuenta_id, $desde, $hasta);

    $ultimo = bco_fila($c, "SELECT saldo, f_movimiento FROM gestion__bancos_extracto WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND f_movimiento <= ?
                            AND saldo IS NOT NULL ORDER BY f_movimiento DESC, extracto_id DESC LIMIT 1", 'iis', $empresa_id, $cuenta_id, $hasta);
    $contable = bco_fila($c, "SELECT ROUND(SUM(IF(tipo_movimiento = 'INGRESO', importe, -importe)), 2) s FROM gestion__tesoreria_movimientos
                              WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND f_movimiento <= ? AND tabla_estado_registro_id = 1",
                         'iis', $empresa_id, $cuenta_id, $hasta);
    $sum = static fn(array $r, ?callable $f = null) => round(array_sum(array_column($f ? array_filter($r, $f) : $r, 'importe')), 2);
    return [
        'cuenta' => $cta,
        'lineas' => $lineas,
        'movimientos' => $movs,
        'totales' => [
            'saldo_banco' => $ultimo ? floatval($ultimo['saldo']) : null,
            'saldo_banco_fecha' => $ultimo['f_movimiento'] ?? null,
            'saldo_tesoreria' => floatval($contable['s'] ?? 0),
            'extracto_pendiente' => $sum($lineas, static fn($l) => $l['naturaleza'] !== 'GASTO_BANCARIO' && $l['estado'] !== 'IGNORADA'),
            'gastos_pendientes' => $sum($lineas, static fn($l) => $l['naturaleza'] === 'GASTO_BANCARIO' && $l['estado'] !== 'IGNORADA'),
            'tesoreria_pendiente' => $sum($movs),
            'sin_concepto' => count(array_filter($lineas, static fn($l) => $l['estado'] === 'PENDIENTE')),
        ],
    ];
}

/**
 * Solapa "Resumen": estado de la conciliación por cuenta BANCO activa con extractos importados y mes, entre los años
 * pedidos (desde el mes del primer extracto de la cuenta hasta el mes en curso). Por mes: saldo según banco (último
 * saldo del extracto hasta fin de mes) y según tesorería (libro hasta fin de mes), líneas del extracto conciliadas,
 * pendientes (sin concepto incluidas) y gastos del banco sin registrar, movimientos de tesorería sin conciliar (mismo
 * criterio que bco_tesoreria_pendientes) y estado: CONCILIADO (nada pendiente), PENDIENTE o SIN_EXTRACTO.
 */
function bco_conciliacion_resumen(mysqli $c, int $empresa_id, int $cuenta_id, int $anio_desde, int $anio_hasta): array
{
    $mes_actual = date('Y-m');
    $desde = sprintf('%04d-01-01', $anio_desde);
    $hasta = min(sprintf('%04d-12-31', $anio_hasta), date('Y-m-t'));
    if ($desde > $hasta) return [];

    $cuentas = [];
    foreach (bco_cuentas_banco($c, $empresa_id) as $cta) $cuentas[intval($cta['tesoreria_cuenta_id'])] = $cta;
    $inicios = bco_filas($c, "SELECT tesoreria_cuenta_id, MIN(f_desde) AS inicio FROM gestion__bancos_importaciones
                              WHERE empresa_id = ? AND tabla_estado_registro_id = 5 AND (? = 0 OR tesoreria_cuenta_id = ?)
                              GROUP BY tesoreria_cuenta_id", 'iii', $empresa_id, $cuenta_id, $cuenta_id);

    $por_mes = static function (array $filas): array {
        $r = [];
        foreach ($filas as $f) $r[$f['tesoreria_cuenta_id'] . '|' . $f['mes']] = $f;
        return $r;
    };
    $extracto = $por_mes(bco_filas($c,
        "SELECT x.tesoreria_cuenta_id, DATE_FORMAT(x.f_movimiento, '%Y-%m') AS mes, COUNT(*) AS total,
                SUM(x.estado = 'CONCILIADA') AS conciliadas, ROUND(SUM(IF(x.estado = 'CONCILIADA', x.importe, 0)), 2) AS importe_conciliado,
                SUM(x.estado IN ('PENDIENTE', 'CLASIFICADA') AND COALESCE(k.naturaleza, '') <> 'GASTO_BANCARIO') AS lineas,
                ROUND(SUM(IF(x.estado IN ('PENDIENTE', 'CLASIFICADA') AND COALESCE(k.naturaleza, '') <> 'GASTO_BANCARIO', x.importe, 0)), 2) AS importe_lineas,
                SUM(x.estado = 'PENDIENTE') AS sin_concepto,
                SUM(x.estado IN ('PENDIENTE', 'CLASIFICADA') AND k.naturaleza = 'GASTO_BANCARIO') AS gastos,
                ROUND(SUM(IF(x.estado IN ('PENDIENTE', 'CLASIFICADA') AND k.naturaleza = 'GASTO_BANCARIO', x.importe, 0)), 2) AS importe_gastos,
                SUM(x.estado = 'IGNORADA') AS ignoradas,
                SUBSTRING_INDEX(GROUP_CONCAT(x.saldo ORDER BY x.f_movimiento DESC, x.extracto_id DESC SEPARATOR '|'), '|', 1) AS saldo
         FROM gestion__bancos_extracto x
         LEFT JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id AND k.empresa_id = x.empresa_id
         WHERE x.empresa_id = ? AND (? = 0 OR x.tesoreria_cuenta_id = ?) AND x.f_movimiento BETWEEN ? AND ?
         GROUP BY x.tesoreria_cuenta_id, DATE_FORMAT(x.f_movimiento, '%Y-%m')", 'iiiss', $empresa_id, $cuenta_id, $cuenta_id, $desde, $hasta));
    $pend_tes = $por_mes(bco_filas($c,
        "SELECT m.tesoreria_cuenta_id, DATE_FORMAT(m.f_movimiento, '%Y-%m') AS mes, COUNT(*) AS movimientos,
                ROUND(SUM(IF(m.tipo_movimiento = 'INGRESO', m.importe, -m.importe)), 2) AS importe_movimientos
         FROM gestion__tesoreria_movimientos m
         WHERE m.empresa_id = ? AND (? = 0 OR m.tesoreria_cuenta_id = ?) AND m.tabla_estado_registro_id = 1 AND m.conciliado = 0
           AND m.reversa_de_movimiento_id IS NULL AND m.f_movimiento BETWEEN ? AND ?
           AND NOT EXISTS (SELECT 1 FROM gestion__tesoreria_movimientos r WHERE r.reversa_de_movimiento_id = m.tesoreria_movimiento_id)
         GROUP BY m.tesoreria_cuenta_id, DATE_FORMAT(m.f_movimiento, '%Y-%m')", 'iiiss', $empresa_id, $cuenta_id, $cuenta_id, $desde, $hasta));
    // Libro de tesorería: neto de cada mes del rango (el saldo anterior al rango sale por cuenta, abajo)
    $neto_tes = $por_mes(bco_filas($c,
        "SELECT tesoreria_cuenta_id, DATE_FORMAT(f_movimiento, '%Y-%m') AS mes, ROUND(SUM(IF(tipo_movimiento = 'INGRESO', importe, -importe)), 2) AS neto
         FROM gestion__tesoreria_movimientos WHERE empresa_id = ? AND (? = 0 OR tesoreria_cuenta_id = ?) AND tabla_estado_registro_id = 1
           AND f_movimiento BETWEEN ? AND ?
         GROUP BY tesoreria_cuenta_id, DATE_FORMAT(f_movimiento, '%Y-%m')", 'iiiss', $empresa_id, $cuenta_id, $cuenta_id, $desde, $hasta));

    $filas = [];
    foreach ($inicios as $ini) {
        $cta = intval($ini['tesoreria_cuenta_id']);
        if (!isset($cuentas[$cta])) continue;   // cuenta inactiva o que ya no es BANCO
        $primero = max(substr((string)$ini['inicio'], 0, 7), substr($desde, 0, 7));
        $ultimo_mes = substr($hasta, 0, 7);
        if ($primero > $ultimo_mes) continue;
        // Saldos al empezar: el último del extracto y el del libro antes del primer mes mostrado
        $sb = bco_fila($c, "SELECT saldo FROM gestion__bancos_extracto WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND f_movimiento < ?
                            AND saldo IS NOT NULL ORDER BY f_movimiento DESC, extracto_id DESC LIMIT 1", 'iis', $empresa_id, $cta, "$primero-01");
        $saldo_banco = $sb ? floatval($sb['saldo']) : null;
        $saldo_tes = floatval(bco_fila($c, "SELECT ROUND(SUM(IF(tipo_movimiento = 'INGRESO', importe, -importe)), 2) s FROM gestion__tesoreria_movimientos
                                            WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND f_movimiento < ? AND tabla_estado_registro_id = 1",
                                       'iis', $empresa_id, $cta, "$primero-01")['s'] ?? 0);
        for ($mes = $primero; $mes <= $ultimo_mes; $mes = date('Y-m', strtotime("$mes-01 +1 month"))) {
            $k = "$cta|$mes";
            $x = $extracto[$k] ?? [];
            $t = $pend_tes[$k] ?? [];
            if (($x['saldo'] ?? '') !== '') $saldo_banco = floatval($x['saldo']);
            $saldo_tes = round($saldo_tes + floatval($neto_tes[$k]['neto'] ?? 0), 2);
            $f = ['cuenta_id' => $cta, 'cuenta' => $cuentas[$cta]['tesoreria_cuenta'], 'cuenta_numero' => $cuentas[$cta]['numero'], 'mes' => $mes,
                  'en_curso' => $mes === $mes_actual,
                  'saldo_banco' => $saldo_banco, 'saldo_tesoreria' => $saldo_tes,
                  'diferencia' => $saldo_banco === null ? null : round($saldo_banco - $saldo_tes, 2),
                  'total' => intval($x['total'] ?? 0), 'conciliadas' => intval($x['conciliadas'] ?? 0), 'importe_conciliado' => floatval($x['importe_conciliado'] ?? 0),
                  'lineas' => intval($x['lineas'] ?? 0), 'importe_lineas' => floatval($x['importe_lineas'] ?? 0), 'sin_concepto' => intval($x['sin_concepto'] ?? 0),
                  'gastos' => intval($x['gastos'] ?? 0), 'importe_gastos' => floatval($x['importe_gastos'] ?? 0), 'ignoradas' => intval($x['ignoradas'] ?? 0),
                  'movimientos' => intval($t['movimientos'] ?? 0), 'importe_movimientos' => floatval($t['importe_movimientos'] ?? 0)];
            $f['estado'] = !$f['total'] ? 'SIN_EXTRACTO' : ($f['lineas'] + $f['gastos'] + $f['movimientos'] ? 'PENDIENTE' : 'CONCILIADO');
            $filas[] = $f;
        }
    }
    usort($filas, static fn($a, $b) => [$a['mes'], $a['cuenta']] <=> [$b['mes'], $b['cuenta']]);
    return $filas;
}

/**
 * Sugerencias (§6): AUTO = importe exacto + referencia (CUIT / n° de cheque) en el movimiento + ventana de fechas;
 * SUGERIDA = importe exacto y un único candidato en la ventana; GRUPO = varios movimientos que suman la línea (N:1).
 * Cada línea y cada movimiento aparece en una sola sugerencia.
 */
function bco_conciliacion_sugerir(mysqli $c, int $empresa_id, int $cuenta_id, ?string $desde = null, ?string $hasta = null): array
{
    $desde = $desde ?: bco_inicio_extractos($c, $empresa_id, $cuenta_id);
    $lineas = array_values(array_filter(
        bco_extracto_lineas($c, $empresa_id, ['cuenta_id' => $cuenta_id, 'desde' => $desde, 'hasta' => $hasta, 'estados' => ['CLASIFICADA']]),
        static fn($l) => $l['naturaleza'] === 'MOVIMIENTO_PROPIO'));
    // Ventana: los movimientos pueden ser algunos días anteriores o posteriores al período del banco
    $d = $desde ? date('Y-m-d', strtotime($desde . ' -' . BCO_VENTANA_DIAS . ' days')) : null;
    $h = $hasta ? date('Y-m-d', strtotime($hasta . ' +' . BCO_VENTANA_DIAS . ' days')) : null;
    $movs = bco_tesoreria_pendientes($c, $empresa_id, $cuenta_id, $d, $h);

    $usadosL = $usadosM = [];
    $sug = [];
    $dias = static fn($a, $b) => abs((strtotime($a) - strtotime($b)) / 86400);
    $candidatos = static function (array $l) use (&$movs, &$usadosM, $dias) {
        return array_values(array_filter($movs, static fn($m) => !isset($usadosM[$m['tesoreria_movimiento_id']])
            && abs(floatval($m['importe']) - floatval($l['importe'])) < BCO_TOLERANCIA
            && $dias($m['f_movimiento'], $l['f_movimiento']) <= BCO_VENTANA_DIAS));
    };
    $conReferencia = static function (array $l, array $m): bool {
        // Coincidencia fuerte: CUIT o n° de cheque iguales, o una referencia de 8+ dígitos dentro del concepto
        $ref = preg_replace('/\D/', '', (string)$l['referencia']);
        if (strlen($ref) < 6) return false;
        return $ref === preg_replace('/\D/', '', (string)$m['cuit'])
            || ($m['cheque_numero'] !== null && $ref === preg_replace('/\D/', '', (string)$m['cheque_numero']))
            || (strlen($ref) >= 8 && strpos(preg_replace('/\D/', '', (string)$m['concepto']), $ref) !== false);
    };
    $agregar = static function (string $tipo, array $ls, array $ms, string $motivo) use (&$sug, &$usadosL, &$usadosM) {
        foreach ($ls as $l) $usadosL[$l['extracto_id']] = true;
        foreach ($ms as $m) $usadosM[$m['tesoreria_movimiento_id']] = true;
        $sug[] = ['tipo' => $tipo, 'motivo' => $motivo,
                  'extracto_ids' => array_map('intval', array_column($ls, 'extracto_id')),
                  'movimiento_ids' => array_map('intval', array_column($ms, 'tesoreria_movimiento_id')),
                  'lineas' => $ls, 'movimientos' => $ms,
                  'importe' => round(array_sum(array_column($ls, 'importe')), 2)];
    };

    // 1) importe + referencia
    foreach ($lineas as $l) {
        foreach ($candidatos($l) as $m) {
            if ($conReferencia($l, $m)) { $agregar('AUTO', [$l], [$m], 'Mismo importe y referencia ' . $l['referencia']); break; }
        }
    }
    // 2) importe exacto y único candidato (y la línea es la única que lo reclama)
    foreach ($lineas as $l) {
        if (isset($usadosL[$l['extracto_id']])) continue;
        $cand = $candidatos($l);
        if (count($cand) !== 1) continue;
        $otras = array_filter($lineas, static fn($o) => $o['extracto_id'] !== $l['extracto_id'] && !isset($usadosL[$o['extracto_id']])
            && abs(floatval($o['importe']) - floatval($l['importe'])) < BCO_TOLERANCIA
            && $dias($o['f_movimiento'], $cand[0]['f_movimiento']) <= BCO_VENTANA_DIAS);
        if (!$otras) $agregar('SUGERIDA', [$l], $cand, 'Mismo importe, único movimiento en ±' . BCO_VENTANA_DIAS . ' días');
    }
    // 3) N:1: varios movimientos del mismo signo suman la línea (depósito de varios cheques)
    foreach ($lineas as $l) {
        if (isset($usadosL[$l['extracto_id']])) continue;
        $signo = floatval($l['importe']) > 0 ? 1 : -1;
        $cand = array_values(array_filter($movs, static fn($m) => !isset($usadosM[$m['tesoreria_movimiento_id']])
            && floatval($m['importe']) * $signo > 0 && abs(floatval($m['importe'])) < abs(floatval($l['importe']))
            && $dias($m['f_movimiento'], $l['f_movimiento']) <= BCO_VENTANA_DIAS));
        $cand = array_slice($cand, 0, BCO_GRUPO_MAX_CANDIDATOS);
        $combo = bco_subconjunto_suma($cand, floatval($l['importe']), BCO_GRUPO_MAX_TAMANIO);
        if ($combo) $agregar('GRUPO', [$l], $combo, count($combo) . ' movimientos suman el importe de la línea');
    }
    return $sug;
}

/** Primer subconjunto (2..$max elementos) cuyos importes suman $objetivo. */
function bco_subconjunto_suma(array $items, float $objetivo, int $max): ?array
{
    $n = count($items);
    $buscar = static function (int $desde, float $resto, array $elegidos) use (&$buscar, $items, $n, $max): ?array {
        if (count($elegidos) >= 2 && abs($resto) < BCO_TOLERANCIA) return $elegidos;
        if (count($elegidos) >= $max) return null;
        for ($i = $desde; $i < $n; $i++) {
            $r = $buscar($i + 1, $resto - floatval($items[$i]['importe']), array_merge($elegidos, [$items[$i]]));
            if ($r) return $r;
        }
        return null;
    };
    return $buscar(0, $objetivo, []);
}

/**
 * Concilia un conjunto de líneas con un conjunto de movimientos de la misma cuenta. Las sumas con signo tienen que
 * ser iguales. Devuelve el conciliacion_id.
 */
function bco_conciliar(mysqli $c, int $empresa_id, int $usuario_id, int $cuenta_id, array $extracto_ids, array $mov_ids, string $obs = '', bool $en_transaccion = false): int
{
    $extracto_ids = bco_ids($extracto_ids);
    $mov_ids = bco_ids($mov_ids);
    sort($extracto_ids);
    sort($mov_ids);
    if (!$extracto_ids || !$mov_ids) throw new RuntimeException('Elegí al menos una línea del banco y un movimiento de tesorería.');
    bco_cuenta($c, $empresa_id, $cuenta_id);

    if (!$en_transaccion) mysqli_begin_transaction($c);
    try {
        // Locks en orden ascendente de id: primero el extracto, después tesorería
        $ls = bco_filas($c, "SELECT x.extracto_id, x.tesoreria_cuenta_id, x.estado, x.importe, x.f_movimiento, k.naturaleza
                             FROM gestion__bancos_extracto x LEFT JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id
                             WHERE x.empresa_id = ? AND x.extracto_id IN (" . bco_placeholders($extracto_ids) . ")
                             ORDER BY x.extracto_id FOR UPDATE", 'i' . str_repeat('i', count($extracto_ids)), $empresa_id, ...$extracto_ids);
        if (count($ls) !== count($extracto_ids)) throw new RuntimeException('Alguna línea del banco no existe.');
        foreach ($ls as $l) {
            if (intval($l['tesoreria_cuenta_id']) !== $cuenta_id) throw new RuntimeException('Todas las líneas tienen que ser de la cuenta elegida.');
            if ($l['estado'] !== 'CLASIFICADA') throw new RuntimeException('Solo se concilian líneas clasificadas y sin conciliar (alguna cambió: recargá).');
            if ($l['naturaleza'] !== 'MOVIMIENTO_PROPIO') throw new RuntimeException('Los gastos del banco se registran desde Gastos Bancarios, no se concilian a mano.');
        }
        $ms = bco_filas($c, "SELECT tesoreria_movimiento_id, tesoreria_cuenta_id, conciliado, tabla_estado_registro_id, reversa_de_movimiento_id,
                                    IF(tipo_movimiento = 'INGRESO', importe, -importe) AS importe
                             FROM gestion__tesoreria_movimientos WHERE empresa_id = ? AND tesoreria_movimiento_id IN (" . bco_placeholders($mov_ids) . ")
                             ORDER BY tesoreria_movimiento_id FOR UPDATE", 'i' . str_repeat('i', count($mov_ids)), $empresa_id, ...$mov_ids);
        if (count($ms) !== count($mov_ids)) throw new RuntimeException('Algún movimiento de tesorería no existe.');
        $validos = array_map('intval', array_column(bco_tesoreria_pendientes($c, $empresa_id, $cuenta_id, null, null, $mov_ids), 'tesoreria_movimiento_id'));
        foreach ($ms as $m) {
            if (intval($m['tesoreria_cuenta_id']) !== $cuenta_id) throw new RuntimeException('Todos los movimientos tienen que ser de la cuenta elegida.');
            if (intval($m['conciliado']) === 1) throw new RuntimeException('Algún movimiento ya está conciliado: recargá.');
            if (!in_array(intval($m['tesoreria_movimiento_id']), $validos, true)) throw new RuntimeException('Un movimiento anulado o revertido no se concilia.');
        }
        $se = round(array_sum(array_column($ls, 'importe')), 2);
        $st = round(array_sum(array_column($ms, 'importe')), 2);
        if (abs($se - $st) >= BCO_TOLERANCIA) {
            throw new RuntimeException('No cierra: el banco suma ' . bco_fmt($se) . ' y tesorería ' . bco_fmt($st) . ' (diferencia ' . bco_fmt($se - $st) . ').');
        }
        $fecha = max(array_column($ls, 'f_movimiento'));
        $obs = mb_substr(trim($obs), 0, 300) ?: null;

        bco_exec($c, "INSERT INTO gestion__bancos_conciliaciones (empresa_id, tesoreria_cuenta_id, origen, f_conciliacion, importe_extracto,
                             importe_tesoreria, diferencia, observaciones, tabla_estado_registro_id, usuario_id)
                      VALUES (?, ?, 'MOVIMIENTOS', ?, ?, ?, 0, ?, 5, ?)", 'iisddsi', $empresa_id, $cuenta_id, $fecha, $se, $st, $obs, $usuario_id);
        $id = intval(mysqli_insert_id($c));
        bco_conciliacion_items($c, $id, $ls, $ms);
        bco_marcar_conciliados($c, $empresa_id, $usuario_id, $id, $fecha, $extracto_ids, $mov_ids);
        if (!$en_transaccion) mysqli_commit($c);
    } catch (Throwable $e) {
        if (!$en_transaccion) mysqli_rollback($c);
        throw $e;
    }
    error_log("=== bco_conciliar empresa $empresa_id cuenta $cuenta_id conciliacion $id: " . count($ls) . ' líneas / ' . count($ms) . ' movimientos ===');
    return $id;
}

function bco_conciliacion_items(mysqli $c, int $id, array $ls, array $ms): void
{
    $filas = [];
    $tipos = '';
    $vals = [];
    foreach ($ls as $l) { $filas[] = "(?, 'EXTRACTO', ?, NULL, ?)"; $tipos .= 'iid'; array_push($vals, $id, intval($l['extracto_id']), floatval($l['importe'])); }
    foreach ($ms as $m) { $filas[] = "(?, 'TESORERIA', NULL, ?, ?)"; $tipos .= 'iid'; array_push($vals, $id, intval($m['tesoreria_movimiento_id']), floatval($m['importe'])); }
    if ($filas) {
        bco_exec($c, "INSERT INTO gestion__bancos_conciliaciones_items (conciliacion_id, lado, extracto_id, tesoreria_movimiento_id, importe_aplicado) VALUES "
            . implode(', ', $filas), $tipos, ...$vals);
    }
}

/** Materializa la conciliación en las dos puntas (sin triggers). */
function bco_marcar_conciliados(mysqli $c, int $empresa_id, int $usuario_id, int $id, string $fecha, array $extracto_ids, array $mov_ids): void
{
    if ($extracto_ids) {
        $n = bco_exec($c, "UPDATE gestion__bancos_extracto SET estado = 'CONCILIADA', conciliacion_id = ?, usuario_modificacion_id = ?
                      WHERE empresa_id = ? AND estado = 'CLASIFICADA' AND conciliacion_id IS NULL AND extracto_id IN (" . bco_placeholders($extracto_ids) . ")",
            'iii' . str_repeat('i', count($extracto_ids)), $id, $usuario_id, $empresa_id, ...$extracto_ids);
        if ($n !== count($extracto_ids)) throw new RuntimeException('Alguna línea del banco cambió mientras se conciliaba: recargá.');
    }
    if ($mov_ids) {
        $n = bco_exec($c, "UPDATE gestion__tesoreria_movimientos SET conciliado = 1, f_conciliacion = ?, conciliacion_id = ?, usuario_modificacion_id = ?
                      WHERE empresa_id = ? AND conciliado = 0 AND tesoreria_movimiento_id IN (" . bco_placeholders($mov_ids) . ")",
            'siii' . str_repeat('i', count($mov_ids)), $fecha, $id, $usuario_id, $empresa_id, ...$mov_ids);
        if ($n !== count($mov_ids)) throw new RuntimeException('Algún movimiento de tesorería ya se concilió en otra operación: recargá.');
    }
}

/** Aplica las sugerencias AUTO de la cuenta. Devuelve cuántas conciliaciones creó. */
function bco_conciliar_auto(mysqli $c, int $empresa_id, int $usuario_id, int $cuenta_id, ?string $desde, ?string $hasta): array
{
    $hechas = 0;
    $errores = [];
    foreach (bco_conciliacion_sugerir($c, $empresa_id, $cuenta_id, $desde, $hasta) as $s) {
        if ($s['tipo'] !== 'AUTO') continue;
        try {
            bco_conciliar($c, $empresa_id, $usuario_id, $cuenta_id, $s['extracto_ids'], $s['movimiento_ids'], 'Automática: ' . $s['motivo']);
            $hechas++;
        } catch (mysqli_sql_exception $e) {
            throw $e;
        } catch (mysqli_sql_exception $e) {
            throw $e;
        } catch (mysqli_sql_exception $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }
    }
    return ['conciliadas' => $hechas, 'errores' => $errores];
}

/**
 * Deshace una conciliación (5 -> 38, motivo obligatorio). Las de gastos (origen GASTOS) se deshacen desde
 * Gastos Bancarios, porque además anulan el comprobante. $origen = null: cualquiera (lo usa la reversa de un movimiento
 * de tesorería conciliado, tesoreriaAnularMovimiento, dentro de su transacción).
 */
function bco_conciliacion_deshacer(mysqli $c, int $empresa_id, int $usuario_id, int $id, string $motivo, ?string $origen = 'MOVIMIENTOS', bool $en_transaccion = false): void
{
    $motivo = mb_substr(trim($motivo), 0, 300);
    if ($motivo === '') throw new RuntimeException('Indicá el motivo para deshacer la conciliación.');
    if (!$en_transaccion) mysqli_begin_transaction($c);
    try {
        $k = bco_fila($c, "SELECT conciliacion_id, origen, tabla_estado_registro_id FROM gestion__bancos_conciliaciones
                           WHERE conciliacion_id = ? AND empresa_id = ? FOR UPDATE", 'ii', $id, $empresa_id)
            ?? throw new RuntimeException('Conciliación no encontrada.');
        if (intval($k['tabla_estado_registro_id']) !== 5) throw new RuntimeException('La conciliación ya está deshecha.');
        if ($origen !== null && $k['origen'] !== $origen) {
            throw new RuntimeException($k['origen'] === 'GASTOS' ? 'Esta conciliación es de gastos del banco: deshacela desde Gastos Bancarios.'
                                                                  : 'Esta conciliación se deshace desde Conciliación Bancaria.');
        }
        bco_exec($c, "UPDATE gestion__bancos_extracto SET estado = IF(concepto_id IS NULL, 'PENDIENTE', 'CLASIFICADA'), conciliacion_id = NULL,
                             usuario_modificacion_id = ? WHERE empresa_id = ? AND conciliacion_id = ?", 'iii', $usuario_id, $empresa_id, $id);
        bco_exec($c, "UPDATE gestion__tesoreria_movimientos SET conciliado = 0, f_conciliacion = NULL, conciliacion_id = NULL, usuario_modificacion_id = ?
                      WHERE empresa_id = ? AND conciliacion_id = ?", 'iii', $usuario_id, $empresa_id, $id);
        bco_exec($c, "UPDATE gestion__bancos_conciliaciones SET tabla_estado_registro_id = 38, motivo_deshacer = ?, usuario_modificacion_id = ?
                      WHERE conciliacion_id = ? AND empresa_id = ?", 'siii', $motivo, $usuario_id, $id, $empresa_id);
        if (!$en_transaccion) mysqli_commit($c);
    } catch (Throwable $e) {
        if (!$en_transaccion) mysqli_rollback($c);
        throw $e;
    }
}

/** Historial de conciliaciones de la cuenta ($cuenta_id 0 = todas las de la empresa, por origen), con botones del motor de la página. */
/** $origen: MOVIMIENTOS / GASTOS, o null para las dos (historial de Conciliación Bancaria). $solo_vigentes: sin las deshechas (38). */
function bco_conciliaciones_listar(mysqli $c, int $empresa_id, int $pagina_id, int $cuenta_id, ?string $origen, ?string $desde = null, ?string $hasta = null,
                                   bool $solo_vigentes = false): array
{
    $col = bco_columna_estado($c);
    $rows = bco_filas($c,
        "SELECT k.conciliacion_id, k.origen, k.f_conciliacion, k.importe_extracto, k.importe_tesoreria, k.observaciones, k.motivo_deshacer,
                k.comprobante_id, k.factura_proveedor_id, cp.comprobante_pv, cp.comprobante_nro, k.tabla_estado_registro_id,
                COALESCE(ter.tabla_estado_registro, er.$col) AS estado_registro, er.codigo_estandar, k.fecha_creacion,
                k.tesoreria_cuenta_id, t.tesoreria_cuenta, t.numero AS cuenta_numero,
                SUM(i.lado = 'EXTRACTO') AS lineas, SUM(i.lado = 'TESORERIA') AS movimientos
         FROM gestion__bancos_conciliaciones k
         LEFT JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = k.tesoreria_cuenta_id AND t.empresa_id = k.empresa_id
         LEFT JOIN gestion__bancos_conciliaciones_items i ON i.conciliacion_id = k.conciliacion_id
         LEFT JOIN gestion__comprobantes cp ON cp.comprobante_id = k.comprobante_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = k.tabla_estado_registro_id
         LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 447 AND ter.estado_registro_id = k.tabla_estado_registro_id
         WHERE k.empresa_id = ? AND (? = 0 OR k.tesoreria_cuenta_id = ?) AND (? IS NULL OR k.origen = ?)" . ($solo_vigentes ? ' AND k.tabla_estado_registro_id <> 38' : '') . "
           AND (? IS NULL OR k.f_conciliacion >= ?) AND (? IS NULL OR k.f_conciliacion <= ?)
         GROUP BY k.conciliacion_id
         ORDER BY k.conciliacion_id DESC LIMIT 500", 'iiissssss', $empresa_id, $cuenta_id, $cuenta_id, $origen, $origen, $desde, $desde, $hasta, $hasta);
    return bco_con_botones($c, $pagina_id, $rows);
}

/** Detalle de una conciliación: líneas y movimientos (incluye los ya desvinculados de una deshecha). */
function bco_conciliacion_detalle(mysqli $c, int $empresa_id, int $id): array
{
    $k = bco_fila($c, "SELECT * FROM gestion__bancos_conciliaciones WHERE conciliacion_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id)
        ?? throw new RuntimeException('Conciliación no encontrada.');
    $k['lineas'] = bco_filas($c, "SELECT x.extracto_id, x.f_movimiento, x.descripcion, x.referencia, i.importe_aplicado AS importe, kk.concepto
                                  FROM gestion__bancos_conciliaciones_items i
                                  JOIN gestion__bancos_extracto x ON x.extracto_id = i.extracto_id AND x.empresa_id = ?
                                  LEFT JOIN gestion__bancos_conceptos kk ON kk.concepto_id = x.concepto_id
                                  WHERE i.conciliacion_id = ? AND i.lado = 'EXTRACTO' ORDER BY x.f_movimiento, x.extracto_id", 'ii', $empresa_id, $id);
    $k['movimientos'] = bco_filas($c, "SELECT m.tesoreria_movimiento_id, m.f_movimiento, m.concepto, i.importe_aplicado AS importe
                                       FROM gestion__bancos_conciliaciones_items i
                                       JOIN gestion__tesoreria_movimientos m ON m.tesoreria_movimiento_id = i.tesoreria_movimiento_id AND m.empresa_id = ?
                                       WHERE i.conciliacion_id = ? AND i.lado = 'TESORERIA' ORDER BY m.f_movimiento", 'ii', $empresa_id, $id);
    return $k;
}

/**
 * Clasificación a mano de líneas no conciliadas: 'asignar' (concepto, origen MANUAL), 'quitar' (vuelve a las reglas),
 * 'ignorar' (IGNORADA: no entra a ningún flujo) o 'restaurar'.
 * 'asignar' además aprende la relación descripción -> concepto (regla IGUAL del banco, bco_regla_aprender) y la aplica en
 * el momento a las demás líneas SIN concepto del mismo banco, con la misma descripción y el mismo sentido; 'quitar'
 * inactiva esas reglas aprendidas.
 * Devuelve ['lineas' => marcadas actualizadas, 'mismas' => otras líneas clasificadas por la regla aprendida, 'reglas' => n].
 */
function bco_lineas_clasificar(mysqli $c, int $empresa_id, int $usuario_id, array $ids, string $operacion, int $concepto_id = 0): array
{
    $ids = bco_ids($ids);
    if (!$ids) throw new RuntimeException('Elegí al menos una línea.');
    $in = bco_placeholders($ids);
    $ti = str_repeat('i', count($ids));
    switch ($operacion) {
        case 'asignar':
            return bco_lineas_asignar($c, $empresa_id, $usuario_id, $ids, $concepto_id);
        case 'quitar':
            bco_reglas_aprendidas_inactivar($c, $empresa_id, $usuario_id, $ids);
            $n = bco_exec($c, "UPDATE gestion__bancos_extracto SET concepto_id = NULL, regla_id = NULL, concepto_origen = NULL, estado = 'PENDIENTE',
                                      usuario_modificacion_id = ? WHERE empresa_id = ? AND estado IN ('PENDIENTE', 'CLASIFICADA') AND extracto_id IN ($in)",
                'ii' . $ti, $usuario_id, $empresa_id, ...$ids);
            bco_reglas_aplicar($c, $empresa_id, $usuario_id);
            return ['lineas' => $n, 'mismas' => 0, 'reglas' => 0];
        case 'ignorar':
            return ['mismas' => 0, 'reglas' => 0, 'lineas' => bco_exec($c, "UPDATE gestion__bancos_extracto SET estado = 'IGNORADA', usuario_modificacion_id = ?
                                 WHERE empresa_id = ? AND estado IN ('PENDIENTE', 'CLASIFICADA') AND extracto_id IN ($in)",
                'ii' . $ti, $usuario_id, $empresa_id, ...$ids)];
        case 'restaurar':
            return ['mismas' => 0, 'reglas' => 0, 'lineas' => bco_exec($c, "UPDATE gestion__bancos_extracto SET estado = IF(concepto_id IS NULL, 'PENDIENTE', 'CLASIFICADA'), usuario_modificacion_id = ?
                                 WHERE empresa_id = ? AND estado = 'IGNORADA' AND extracto_id IN ($in)",
                'ii' . $ti, $usuario_id, $empresa_id, ...$ids)];
    }
    throw new RuntimeException('Operación no válida.');
}

/** Líneas marcadas + su banco, sin conciliar (para asignar o quitar). */
function bco_lineas_con_banco(mysqli $c, int $empresa_id, array $ids): array
{
    return bco_filas($c, "SELECT x.extracto_id, x.descripcion, x.importe, t.banco_id FROM gestion__bancos_extracto x
                          JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = x.tesoreria_cuenta_id AND t.empresa_id = x.empresa_id
                          WHERE x.empresa_id = ? AND x.estado IN ('PENDIENTE', 'CLASIFICADA') AND x.extracto_id IN (" . bco_placeholders($ids) . ")
                          ORDER BY x.extracto_id FOR UPDATE", 'i' . str_repeat('i', count($ids)), $empresa_id, ...$ids);
}

/** Clave de una descripción para la regla aprendida: banco | sentido | texto normalizado. */
function bco_clave_descripcion(int $banco_id, float $importe, string $descripcion): string
{
    return $banco_id . '|' . ($importe < 0 ? 'D' : 'C') . '|' . mb_substr(bco_texto_normalizado($descripcion), 0, 300);
}

function bco_lineas_asignar(mysqli $c, int $empresa_id, int $usuario_id, array $ids, int $concepto_id): array
{
    if (!bco_fila($c, "SELECT 1 FROM gestion__bancos_conceptos WHERE concepto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $concepto_id, $empresa_id)) {
        throw new RuntimeException('Elegí un concepto activo.');
    }
    mysqli_begin_transaction($c);
    try {
        $marcadas = bco_lineas_con_banco($c, $empresa_id, $ids);
        if (!$marcadas) throw new RuntimeException('Las líneas marcadas ya están conciliadas o no existen: recargá.');
        $vals = array_map('intval', array_column($marcadas, 'extracto_id'));
        $n = bco_exec($c, "UPDATE gestion__bancos_extracto SET concepto_id = ?, regla_id = NULL, concepto_origen = 'MANUAL', estado = 'CLASIFICADA',
                                  usuario_modificacion_id = ? WHERE empresa_id = ? AND extracto_id IN (" . bco_placeholders($vals) . ")",
            'iii' . str_repeat('i', count($vals)), $concepto_id, $usuario_id, $empresa_id, ...$vals);

        // Una regla por descripción distinta (las cuentas sin banco no aprenden: las reglas son por banco)
        $reglas = [];
        foreach ($marcadas as $l) {
            if (!$l['banco_id']) continue;
            $k = bco_clave_descripcion(intval($l['banco_id']), floatval($l['importe']), (string)$l['descripcion']);
            if (!isset($reglas[$k])) {
                $reglas[$k] = bco_regla_aprender($c, $empresa_id, $usuario_id, intval($l['banco_id']), (string)$l['descripcion'], floatval($l['importe']), $concepto_id);
            }
        }

        // Las demás líneas sin concepto del mismo banco con la misma descripción y sentido: una lectura, un UPDATE por regla
        $mismas = 0;
        if ($reglas) {
            $bancos = array_values(array_unique(array_map(static fn($k) => intval(explode('|', $k)[0]), array_keys($reglas))));
            $cand = bco_filas($c, "SELECT x.extracto_id, x.descripcion, x.importe, t.banco_id FROM gestion__bancos_extracto x
                                   JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = x.tesoreria_cuenta_id AND t.empresa_id = x.empresa_id
                                   WHERE x.empresa_id = ? AND x.estado = 'PENDIENTE' AND x.concepto_id IS NULL AND t.banco_id IN (" . bco_placeholders($bancos) . ")
                                   ORDER BY x.extracto_id FOR UPDATE", 'i' . str_repeat('i', count($bancos)), $empresa_id, ...$bancos);
            $por_regla = [];
            foreach ($cand as $l) {
                $k = bco_clave_descripcion(intval($l['banco_id']), floatval($l['importe']), (string)$l['descripcion']);
                if (isset($reglas[$k])) $por_regla[$reglas[$k]][] = intval($l['extracto_id']);
            }
            foreach ($por_regla as $regla_id => $xs) {
                $mismas += bco_exec($c, "UPDATE gestion__bancos_extracto SET concepto_id = ?, regla_id = ?, concepto_origen = 'REGLA', estado = 'CLASIFICADA',
                                                usuario_modificacion_id = ? WHERE empresa_id = ? AND estado = 'PENDIENTE' AND extracto_id IN (" . bco_placeholders($xs) . ")",
                    'iiii' . str_repeat('i', count($xs)), $concepto_id, $regla_id, $usuario_id, $empresa_id, ...$xs);
            }
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    return ['lineas' => $n, 'mismas' => $mismas, 'reglas' => count($reglas)];
}

/** Al quitar el concepto de líneas, deja de aplicarse la relación aprendida de esas descripciones. */
function bco_reglas_aprendidas_inactivar(mysqli $c, int $empresa_id, int $usuario_id, array $ids): void
{
    foreach (bco_lineas_con_banco($c, $empresa_id, $ids) as $l) {
        if (!$l['banco_id']) continue;
        $patron = mb_substr(bco_texto_normalizado((string)$l['descripcion']), 0, 300);
        $aplica = floatval($l['importe']) < 0 ? 'DEBITO' : 'CREDITO';
        $banco = intval($l['banco_id']);
        bco_exec($c, "UPDATE gestion__bancos_reglas SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                      WHERE empresa_id = ? AND banco_id = ? AND tipo_coincidencia = 'IGUAL' AND origen = 'APRENDIDA' AND aplica_a = ? AND patron = ?
                        AND tabla_estado_registro_id = 1", 'iiiss', $usuario_id, $empresa_id, $banco, $aplica, $patron);
    }
}
