<?php
/**
 * Conciliación bancaria: importación del extracto (gestion__bancos_importaciones, tabla 445, estados 5 / 38, y
 * gestion__bancos_extracto, tabla 446). Dos pasos (§4 del diseño):
 *   1) previsualizar: parsea con el formato, controla la cadena de saldos y el n° de cuenta, cuenta duplicados.
 *      No persiste nada.
 *   2) confirmar: vuelve a leer el mismo archivo (el navegador lo manda otra vez), y en una transacción guarda el
 *      lote y las líneas nuevas (los hashes existentes se precargan en bloque: sin N+1), ya clasificadas.
 * Duplicados: el archivo (archivo_hash) y cada línea (hash_mov con ordinal de ocurrencia) son únicos por cuenta.
 */

require_once __DIR__ . '/bancos_config_service.php';
require_once __DIR__ . '/extracto_ia.php';

/** Lee el archivo con IA (o con un formato si se indica $formato_id > 0) y arma el resumen; no escribe. */
function bco_importar_analizar(mysqli $c, int $empresa_id, int $cuenta_id, int $formato_id, string $ruta): array
{
    $cta = bco_cuenta($c, $empresa_id, $cuenta_id);
    $formato = null;
    if ($formato_id > 0) {
        $formato = bco_formato_obtener($c, $empresa_id, $formato_id);
        if (!$formato || intval($formato['tabla_estado_registro_id']) !== 1) throw new RuntimeException('Elegí un formato activo.');
        if ($cta['banco_id'] && intval($formato['banco_id']) !== intval($cta['banco_id'])) {
            throw new RuntimeException('El formato es de ' . $formato['banco'] . ' y la cuenta es de ' . $cta['banco'] . '.');
        }
        $x = bco_extracto_desde_archivo($ruta, $formato);
    } else {
        $x = bco_extracto_desde_archivo_ia($c, $empresa_id, $ruta);
    }

    $x['archivo_hash'] = hash_file('sha256', $ruta);
    $x['bloqueos'] = $x['control']['errores'];

    // N° de cuenta del encabezado contra la cuenta elegida
    $x['cuenta_numero'] = $cta['numero'];
    if ($x['cuenta_archivo'] !== null && trim((string)$cta['numero']) !== '') {
        if (!bco_cuenta_coincide($x['cuenta_archivo'], (string)$cta['numero'])) {
            $x['bloqueos'][] = 'El archivo es de la cuenta ' . $x['cuenta_archivo'] . ' y elegiste ' . $cta['tesoreria_cuenta'] . ' (' . $cta['numero'] . ').';
        }
    } elseif ($x['cuenta_archivo'] !== null) {
        $x['avisos'][] = 'La cuenta ' . $cta['tesoreria_cuenta'] . ' no tiene número cargado: al confirmar se le asigna ' . $x['cuenta_archivo'] . '.';
    } elseif ($formato && !empty($formato['cuenta_fila'])) {
        $x['avisos'][] = 'No se encontró el número de cuenta en el encabezado del archivo.';
    }

    if (bco_fila($c, "SELECT 1 FROM gestion__bancos_importaciones WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND archivo_hash = ?",
                 'iis', $empresa_id, $cuenta_id, $x['archivo_hash'])) {
        $x['bloqueos'][] = 'Este archivo ya se importó en esta cuenta.';
    }

    // Duplicados por línea (superposición con extractos anteriores): una sola consulta por el rango de fechas
    $existentes = array_flip(array_column(bco_filas($c,
        "SELECT hash_mov FROM gestion__bancos_extracto WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND f_movimiento BETWEEN ? AND ?",
        'iiss', $empresa_id, $cuenta_id, $x['f_desde'], $x['f_hasta']), 'hash_mov'));
    $nuevas = array_values(array_filter($x['movimientos'], static fn($m) => !isset($existentes[$m['hash_mov']])));
    $x['duplicadas'] = count($x['movimientos']) - count($nuevas);
    // Los días que ya tienen líneas importadas: una línea con la misma fecha e importe que una existente se toma como la
    // misma aunque su hash difiera (el banco reordenó el día y cambió el saldo, o cambió el texto)
    $ya = [];
    foreach (bco_filas($c, "SELECT f_movimiento, importe FROM gestion__bancos_extracto WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND f_movimiento BETWEEN ? AND ?",
                 'iiss', $empresa_id, $cuenta_id, $x['f_desde'], $x['f_hasta']) as $f) {
        $k = $f['f_movimiento'] . '|' . number_format((float)$f['importe'], 2, '.', '');
        $ya[$k] = ($ya[$k] ?? 0) + 1;
    }
    foreach ($x['movimientos'] as $m) {   // las que coincidieron por hash consumen su par
        if (!isset($existentes[$m['hash_mov']])) continue;
        $k = $m['f_movimiento'] . '|' . number_format($m['importe'], 2, '.', '');
        if (!empty($ya[$k])) $ya[$k]--;
    }
    $omitidas = 0;
    $nuevas = array_values(array_filter($nuevas, static function ($m) use (&$ya, &$omitidas) {
        $k = $m['f_movimiento'] . '|' . number_format($m['importe'], 2, '.', '');
        if (!empty($ya[$k])) { $ya[$k]--; $omitidas++; return false; }
        return true;
    }));
    if ($omitidas) {
        $x['duplicadas'] += $omitidas;
        $x['avisos'][] = "$omitidas movimientos tienen la misma fecha e importe que otros ya importados (con otro saldo o texto): se toman como los mismos y se omiten.";
    }
    if (!$nuevas && !$x['bloqueos']) $x['bloqueos'][] = 'Todos los movimientos del archivo ya están importados.';
    elseif ($x['duplicadas'] > 0) $x['avisos'][] = $x['duplicadas'] . ' movimientos ya estaban importados (se superpone con otro extracto): se omiten.';

    // Clasificación con las reglas del banco de la cuenta (o del formato si se indicó)
    $banco_id = intval($cta['banco_id'] ?: ($formato['banco_id'] ?? 0));
    $nuevas = bco_reglas_clasificar($nuevas, bco_reglas_activas($c, $empresa_id, $banco_id));
    $conceptos = [];
    foreach (bco_conceptos_listar($c, $empresa_id) as $k) $conceptos[intval($k['concepto_id'])] = $k;
    $resumen = [];
    foreach ($nuevas as &$m) {
        $k = $m['concepto_id'] ? ($conceptos[$m['concepto_id']] ?? null) : null;
        $m['concepto'] = $k ? $k['concepto'] : null;
        $m['naturaleza'] = $k ? $k['naturaleza'] : null;
        $clave = $k ? $k['concepto'] : 'Sin concepto';
        $resumen[$clave] = $resumen[$clave] ?? ['concepto' => $clave, 'naturaleza' => $k['naturaleza'] ?? null, 'cantidad' => 0, 'importe' => 0.0];
        $resumen[$clave]['cantidad']++;
        $resumen[$clave]['importe'] = round($resumen[$clave]['importe'] + $m['importe'], 2);
    }
    unset($m);
    $x['nuevas'] = $nuevas;
    $x['clasificacion'] = array_values($resumen);
    $x['sin_concepto'] = count(array_filter($nuevas, static fn($m) => $m['estado'] === 'PENDIENTE'));
    $x['cuenta'] = $cta;
    $x['formato'] = $formato
        ? ['formato_id' => intval($formato['formato_id']), 'formato' => $formato['formato'], 'banco' => $formato['banco']]
        : ['formato_id' => null, 'formato' => 'Lectura IA', 'banco' => $cta['banco'] ?? ''];
    unset($x['movimientos']);
    return $x;
}

function bco_importar_previsualizar(mysqli $c, int $empresa_id, int $cuenta_id, int $formato_id, string $ruta): array
{
    $x = bco_importar_analizar($c, $empresa_id, $cuenta_id, $formato_id, $ruta);
    $x['cantidad_nuevas'] = count($x['nuevas']);
    return $x;
}

/** Guarda el lote. Devuelve ['importacion_id', 'lineas', 'sin_concepto', 'duplicadas', 'avisos']. */
function bco_importar_confirmar(mysqli $c, int $empresa_id, int $usuario_id, int $cuenta_id, int $formato_id, string $tmp, string $nombre): array
{
    $x = bco_importar_analizar($c, $empresa_id, $cuenta_id, $formato_id, $tmp);
    if ($x['bloqueos']) throw new RuntimeException(implode(' ', $x['bloqueos']));
    $nuevas = $x['nuevas'];
    $ruta = bco_archivo_guardar($tmp, $empresa_id, $nombre);
    $fmt_db = $formato_id > 0 ? $formato_id : null;

    mysqli_begin_transaction($c);
    try {
        bco_exec($c, "INSERT INTO gestion__bancos_importaciones (empresa_id, tesoreria_cuenta_id, formato_id, archivo_nombre, archivo_hash, archivo_ruta,
                             f_desde, f_hasta, saldo_inicial, saldo_final, cantidad_movimientos, tabla_estado_registro_id, usuario_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 5, ?)",
            'iiisssssddii', $empresa_id, $cuenta_id, $fmt_db, $nombre, $x['archivo_hash'], $ruta, $x['f_desde'], $x['f_hasta'],
            $x['saldo_inicial'], $x['saldo_final'], count($nuevas), $usuario_id);
        $imp_id = intval(mysqli_insert_id($c));

        // INSERT multi-fila de a 200
        foreach (array_chunk($nuevas, 200) as $bloque) {
            $sql = "INSERT INTO gestion__bancos_extracto (empresa_id, importacion_id, tesoreria_cuenta_id, nro_linea, f_movimiento, f_valor, descripcion,
                           referencia, importe, saldo, hash_mov, concepto_id, concepto_origen, regla_id, estado, usuario_modificacion_id) VALUES ";
            $tipos = '';
            $vals = [];
            foreach ($bloque as $m) {
                $sql .= '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?),';
                $tipos .= 'iiiissssddsisisi';
                array_push($vals, $empresa_id, $imp_id, $cuenta_id, $m['nro_linea'], $m['f_movimiento'], $m['f_valor'], $m['descripcion'],
                    $m['referencia'], $m['importe'], $m['saldo'], $m['hash_mov'], $m['concepto_id'], $m['concepto_origen'], $m['regla_id'],
                    $m['estado'], $usuario_id);
            }
            bco_exec($c, rtrim($sql, ','), $tipos, ...$vals);
        }

        if ($x['cuenta_archivo'] !== null && trim((string)$x['cuenta_numero']) === '') {
            $numero = mb_substr($x['cuenta_archivo'], 0, 50);
            bco_exec($c, "UPDATE gestion__tesoreria_cuentas SET numero = ? WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND (numero IS NULL OR numero = '')",
                'sii', $numero, $cuenta_id, $empresa_id);
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        @unlink(dirname(__DIR__, 2) . '/' . $ruta);
        if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
            throw new RuntimeException('Otro usuario importó este extracto al mismo tiempo: recargá la página.');
        }
        throw $e;
    }
    error_log("=== bco_importar_confirmar empresa $empresa_id cuenta $cuenta_id lote $imp_id: " . count($nuevas) . " líneas ===");
    return ['importacion_id' => $imp_id, 'lineas' => count($nuevas), 'sin_concepto' => $x['sin_concepto'],
            'duplicadas' => $x['duplicadas'], 'avisos' => $x['avisos']];
}

function bco_importaciones_listar(mysqli $c, int $empresa_id, int $pagina_id, int $cuenta_id = 0): array
{
    $col = bco_columna_estado($c);
    $rows = bco_filas($c,
        "SELECT i.importacion_id, i.tesoreria_cuenta_id, t.tesoreria_cuenta, COALESCE(f.formato, 'Lectura IA') AS formato, i.archivo_nombre, i.f_desde, i.f_hasta,
                i.saldo_inicial, i.saldo_final, i.cantidad_movimientos, i.fecha_creacion, i.tabla_estado_registro_id,
                COALESCE(ter.tabla_estado_registro, er.$col) AS estado_registro, er.codigo_estandar,
                SUM(x.estado = 'PENDIENTE') AS sin_concepto, SUM(x.estado = 'CONCILIADA') AS conciliadas, SUM(x.estado = 'IGNORADA') AS ignoradas
         FROM gestion__bancos_importaciones i
         JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = i.tesoreria_cuenta_id AND t.empresa_id = i.empresa_id
         LEFT JOIN gestion__bancos_formatos f ON f.formato_id = i.formato_id
         LEFT JOIN gestion__bancos_extracto x ON x.importacion_id = i.importacion_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = i.tabla_estado_registro_id
         LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 445 AND ter.estado_registro_id = i.tabla_estado_registro_id
         WHERE i.empresa_id = ? AND (? = 0 OR i.tesoreria_cuenta_id = ?)
         GROUP BY i.importacion_id
         ORDER BY i.importacion_id DESC", 'iii', $empresa_id, $cuenta_id, $cuenta_id);
    return bco_con_botones($c, $pagina_id, $rows);
}

/** Líneas del extracto con su concepto (de un lote, o de una cuenta y período). */
function bco_extracto_lineas(mysqli $c, int $empresa_id, array $filtro): array
{
    $sql = "SELECT x.extracto_id, x.importacion_id, x.tesoreria_cuenta_id, x.nro_linea, x.f_movimiento, x.descripcion, x.referencia, x.importe, x.saldo,
                   x.concepto_id, k.codigo AS concepto_codigo, k.concepto, k.naturaleza, k.rol, x.concepto_origen, x.estado, x.conciliacion_id
            FROM gestion__bancos_extracto x
            LEFT JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id AND k.empresa_id = x.empresa_id
            WHERE x.empresa_id = ?";
    $tipos = 'i';
    $p = [$empresa_id];
    if (!empty($filtro['importacion_id'])) { $sql .= ' AND x.importacion_id = ?'; $tipos .= 'i'; $p[] = intval($filtro['importacion_id']); }
    if (!empty($filtro['cuenta_id'])) { $sql .= ' AND x.tesoreria_cuenta_id = ?'; $tipos .= 'i'; $p[] = intval($filtro['cuenta_id']); }
    if (!empty($filtro['desde'])) { $sql .= ' AND x.f_movimiento >= ?'; $tipos .= 's'; $p[] = $filtro['desde']; }
    if (!empty($filtro['hasta'])) { $sql .= ' AND x.f_movimiento <= ?'; $tipos .= 's'; $p[] = $filtro['hasta']; }
    if (!empty($filtro['estados'])) {
        $sql .= ' AND x.estado IN (' . bco_placeholders($filtro['estados']) . ')';
        $tipos .= str_repeat('s', count($filtro['estados']));
        array_push($p, ...$filtro['estados']);
    }
    $sql .= ' ORDER BY x.f_movimiento, x.extracto_id';   // las líneas se insertan en orden cronológico
    return bco_filas($c, $sql, $tipos, ...$p);
}

/** Tope de filas de la solapa Movimientos de Extractos Bancarios (los totales se calculan sobre todo el filtro). */
const BCO_MOVIMIENTOS_MAX = 5000;

/**
 * WHERE de las solapas Movimientos y Resumen de la página 129 / 629 sobre gestion__bancos_extracto x. Filtros:
 * cuenta_id, desde / hasta (f_movimiento), concepto_id (-1 = sin concepto), estado, sentido (DEBITO / CREDITO), texto
 * (descripción o referencia). Devuelve [$where, $tipos, $params].
 */
function bco_extracto_filtro_sql(int $empresa_id, array $filtro): array
{
    $where = ' WHERE x.empresa_id = ?';
    $tipos = 'i';
    $p = [$empresa_id];
    if (!empty($filtro['cuenta_id'])) { $where .= ' AND x.tesoreria_cuenta_id = ?'; $tipos .= 'i'; $p[] = intval($filtro['cuenta_id']); }
    if ($d = bco_fecha_valida($filtro['desde'] ?? '')) { $where .= ' AND x.f_movimiento >= ?'; $tipos .= 's'; $p[] = $d; }
    if ($h = bco_fecha_valida($filtro['hasta'] ?? '')) { $where .= ' AND x.f_movimiento <= ?'; $tipos .= 's'; $p[] = $h; }
    $concepto = intval($filtro['concepto_id'] ?? 0);
    if ($concepto === -1) $where .= ' AND x.concepto_id IS NULL';
    elseif ($concepto > 0) { $where .= ' AND x.concepto_id = ?'; $tipos .= 'i'; $p[] = $concepto; }
    $estado = (string)($filtro['estado'] ?? '');
    if (in_array($estado, ['PENDIENTE', 'CLASIFICADA', 'CONCILIADA', 'IGNORADA'], true)) { $where .= ' AND x.estado = ?'; $tipos .= 's'; $p[] = $estado; }
    $sentido = (string)($filtro['sentido'] ?? '');
    if ($sentido === 'DEBITO') $where .= ' AND x.importe < 0';
    elseif ($sentido === 'CREDITO') $where .= ' AND x.importe > 0';
    $texto = trim((string)($filtro['texto'] ?? ''));
    if ($texto !== '') {
        $like = '%' . addcslashes(mb_substr($texto, 0, 100), '%_\\') . '%';
        $where .= ' AND (x.descripcion LIKE ? OR x.referencia LIKE ?)';
        $tipos .= 'ss';
        array_push($p, $like, $like);
    }
    return [$where, $tipos, $p];
}

/** true si el filtro restringe algo más que cuenta y fechas (débitos y créditos ya no explican la variación del saldo). */
function bco_extracto_filtro_parcial(array $filtro): bool
{
    return intval($filtro['concepto_id'] ?? 0) !== 0 || (string)($filtro['estado'] ?? '') !== ''
        || (string)($filtro['sentido'] ?? '') !== '' || trim((string)($filtro['texto'] ?? '')) !== '';
}

/**
 * Saldos del banco por cuenta y por mes entre desde y hasta. Solo cuentan la cuenta y las fechas: el saldo es el de la
 * cuenta, los otros filtros no lo cambian (las líneas ignoradas también están en el banco). Recorre las líneas en orden
 * cronológico: cada una deja el saldo que trae (pasado a saldo posterior según el formato de su lote) o, si no trae, el
 * anterior más su importe; el inicial de cada mes es el saldo antes de su primera línea, y para las cuentas sin
 * movimientos en el rango, el de la última línea anterior a desde. null = saldo desconocido.
 * Devuelve [cuenta_id => ['inicial' => ?float, 'final' => ?float, 'meses' => ['AAAA-MM' => ['inicial' => ?float, 'final' => ?float]]]].
 */
function bco_extracto_saldos(mysqli $c, int $empresa_id, array $filtro): array
{
    $cuenta_id = intval($filtro['cuenta_id'] ?? 0);
    $desde = bco_fecha_valida($filtro['desde'] ?? '');
    $hasta = bco_fecha_valida($filtro['hasta'] ?? '');
    $saldoPost = "CASE WHEN x.saldo IS NULL THEN NULL WHEN COALESCE(f.saldo_posterior, 1) = 1 THEN x.saldo ELSE x.saldo + x.importe END";
    $from = " FROM gestion__bancos_extracto x
              JOIN gestion__bancos_importaciones i ON i.importacion_id = x.importacion_id
              LEFT JOIN gestion__bancos_formatos f ON f.formato_id = i.formato_id";

    // Punto de partida de cada cuenta: la última línea anterior a desde (también para las cuentas sin movimientos en el rango)
    $res = [];
    if ($desde) {
        $cuentas = bco_filas($c, "SELECT DISTINCT x.tesoreria_cuenta_id FROM gestion__bancos_extracto x
                                  WHERE x.empresa_id = ? AND (? = 0 OR x.tesoreria_cuenta_id = ?) AND x.f_movimiento < ?",
                             'iiis', $empresa_id, $cuenta_id, $cuenta_id, $desde);
        foreach ($cuentas as $k) {
            $id = intval($k['tesoreria_cuenta_id']);
            $previa = bco_fila($c, "SELECT $saldoPost AS saldo $from
                                    WHERE x.empresa_id = ? AND x.tesoreria_cuenta_id = ? AND x.f_movimiento < ?
                                    ORDER BY x.f_movimiento DESC, x.extracto_id DESC LIMIT 1", 'iis', $empresa_id, $id, $desde);
            $s = ($previa && $previa['saldo'] !== null) ? round((float)$previa['saldo'], 2) : null;
            $res[$id] = ['inicial' => $s, 'final' => $s, 'meses' => []];
        }
    }

    $sql = "SELECT x.tesoreria_cuenta_id, DATE_FORMAT(x.f_movimiento, '%Y-%m') AS mes, x.importe, $saldoPost AS saldo $from
            WHERE x.empresa_id = ? AND (? = 0 OR x.tesoreria_cuenta_id = ?)";
    $tipos = 'iii';
    $p = [$empresa_id, $cuenta_id, $cuenta_id];
    if ($desde) { $sql .= ' AND x.f_movimiento >= ?'; $tipos .= 's'; $p[] = $desde; }
    if ($hasta) { $sql .= ' AND x.f_movimiento <= ?'; $tipos .= 's'; $p[] = $hasta; }
    $sql .= ' ORDER BY x.tesoreria_cuenta_id, x.f_movimiento, x.extracto_id';
    foreach (bco_filas($c, $sql, $tipos, ...$p) as $l) {
        $id = intval($l['tesoreria_cuenta_id']);
        $importe = (float)$l['importe'];
        $saldo = $l['saldo'] === null ? null : round((float)$l['saldo'], 2);
        // Saldo antes de la línea: el que dice el banco si la línea trae saldo (si falta un extracto, el hueco queda a la
        // vista entre el final de un mes y el inicial del siguiente), si no el que se venía arrastrando
        $previo = $res[$id]['final'] ?? null;
        $antes = $saldo === null ? $previo : round($saldo - $importe, 2);
        if (!isset($res[$id])) $res[$id] = ['inicial' => $antes, 'final' => $antes, 'meses' => []];
        if (!$res[$id]['meses']) $res[$id]['inicial'] = $antes;
        if (!isset($res[$id]['meses'][$l['mes']])) $res[$id]['meses'][$l['mes']] = ['inicial' => $antes, 'final' => $antes];
        $final = $saldo ?? ($previo === null ? null : round($previo + $importe, 2));
        $res[$id]['final'] = $final;
        $res[$id]['meses'][$l['mes']]['final'] = $final;
    }
    return $res;
}

/**
 * Movimientos de los extractos importados (solapa Movimientos de la página 129 / 629), con los filtros de
 * bco_extracto_filtro_sql(). Devuelve ['lineas' => [...], 'totales' => [...], 'limitado' => bool]; los totales llevan
 * el saldo inicial y final del banco (de las cuentas del filtro, entre las fechas) y débitos / créditos del filtro.
 */
function bco_extracto_movimientos(mysqli $c, int $empresa_id, array $filtro): array
{
    [$where, $tipos, $p] = bco_extracto_filtro_sql($empresa_id, $filtro);
    $tot = bco_fila($c, "SELECT COUNT(*) AS cantidad, COALESCE(SUM(CASE WHEN x.importe < 0 THEN x.importe END), 0) AS debitos,
                                COALESCE(SUM(CASE WHEN x.importe > 0 THEN x.importe END), 0) AS creditos,
                                SUM(x.concepto_id IS NULL) AS sin_concepto
                         FROM gestion__bancos_extracto x" . $where, $tipos, ...$p);
    $lineas = bco_filas($c,
        "SELECT x.extracto_id, x.importacion_id, x.tesoreria_cuenta_id, t.tesoreria_cuenta, x.nro_linea, x.f_movimiento, x.f_valor,
                x.descripcion, x.referencia, x.importe, x.saldo, x.concepto_id, k.concepto, k.naturaleza, x.concepto_origen, x.estado, x.conciliacion_id
         FROM gestion__bancos_extracto x
         JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = x.tesoreria_cuenta_id AND t.empresa_id = x.empresa_id
         LEFT JOIN gestion__bancos_conceptos k ON k.concepto_id = x.concepto_id AND k.empresa_id = x.empresa_id"
        . $where . ' ORDER BY x.f_movimiento DESC, x.extracto_id DESC LIMIT ' . BCO_MOVIMIENTOS_MAX, $tipos, ...$p);

    // Suma de las cuentas del filtro: null si alguna no tiene saldo conocido
    $saldos = bco_extracto_saldos($c, $empresa_id, $filtro);
    $inicial = $final = $saldos ? 0.0 : null;
    foreach ($saldos as $s) {
        $inicial = ($inicial === null || $s['inicial'] === null) ? null : $inicial + $s['inicial'];
        $final = ($final === null || $s['final'] === null) ? null : $final + $s['final'];
    }
    return [
        'lineas' => $lineas,
        'totales' => ['cantidad' => intval($tot['cantidad'] ?? 0), 'debitos' => (float)($tot['debitos'] ?? 0),
                      'creditos' => (float)($tot['creditos'] ?? 0), 'sin_concepto' => intval($tot['sin_concepto'] ?? 0),
                      'saldo_inicial' => $inicial === null ? null : round($inicial, 2),
                      'saldo_final' => $final === null ? null : round($final, 2),
                      'parcial' => bco_extracto_filtro_parcial($filtro)],
        'limitado' => intval($tot['cantidad'] ?? 0) > BCO_MOVIMIENTOS_MAX,
        'maximo' => BCO_MOVIMIENTOS_MAX,
    ];
}

/**
 * Resumen mensual del extracto (solapa Resumen de la página 129 / 629): una fila por cuenta y mes con el saldo inicial y
 * final del banco (bco_extracto_saldos) y débitos / créditos / cantidad de los movimientos que cumplen el filtro.
 */
function bco_extracto_resumen_mensual(mysqli $c, int $empresa_id, array $filtro): array
{
    [$where, $tipos, $p] = bco_extracto_filtro_sql($empresa_id, $filtro);
    $sumas = [];
    foreach (bco_filas($c, "SELECT x.tesoreria_cuenta_id, DATE_FORMAT(x.f_movimiento, '%Y-%m') AS mes, COUNT(*) AS cantidad,
                                   COALESCE(SUM(CASE WHEN x.importe < 0 THEN x.importe END), 0) AS debitos,
                                   COALESCE(SUM(CASE WHEN x.importe > 0 THEN x.importe END), 0) AS creditos,
                                   SUM(x.concepto_id IS NULL) AS sin_concepto
                            FROM gestion__bancos_extracto x" . $where . ' GROUP BY x.tesoreria_cuenta_id, mes', $tipos, ...$p) as $s) {
        $sumas[$s['tesoreria_cuenta_id'] . '|' . $s['mes']] = $s;
    }
    $nombres = [];
    foreach (bco_filas($c, "SELECT tesoreria_cuenta_id, tesoreria_cuenta FROM gestion__tesoreria_cuentas WHERE empresa_id = ?", 'i', $empresa_id) as $t) {
        $nombres[intval($t['tesoreria_cuenta_id'])] = $t['tesoreria_cuenta'];
    }

    // Todos los meses con movimientos de la cuenta, aunque ninguno cumpla los otros filtros (el saldo igual se ve)
    $filas = [];
    foreach (bco_extracto_saldos($c, $empresa_id, $filtro) as $cuenta => $s) {
        foreach ($s['meses'] as $mes => $m) {
            $x = $sumas[$cuenta . '|' . $mes] ?? [];
            $filas[] = ['tesoreria_cuenta_id' => $cuenta, 'tesoreria_cuenta' => $nombres[$cuenta] ?? '', 'mes' => $mes,
                        'saldo_inicial' => $m['inicial'], 'debitos' => (float)($x['debitos'] ?? 0), 'creditos' => (float)($x['creditos'] ?? 0),
                        'saldo_final' => $m['final'], 'cantidad' => intval($x['cantidad'] ?? 0), 'sin_concepto' => intval($x['sin_concepto'] ?? 0)];
        }
    }
    return ['meses' => $filas, 'parcial' => bco_extracto_filtro_parcial($filtro)];
}

/** Anular un lote (5 -> 38): solo sin líneas conciliadas. Borra sus líneas para que el archivo se pueda volver a importar. */
function bco_importacion_anular(mysqli $c, int $empresa_id, int $usuario_id, int $pagina_id, int $id, string $accion_js): int
{
    mysqli_begin_transaction($c);
    try {
        $i = bco_fila($c, "SELECT importacion_id, tabla_estado_registro_id, archivo_hash FROM gestion__bancos_importaciones
                           WHERE importacion_id = ? AND empresa_id = ? FOR UPDATE", 'ii', $id, $empresa_id)
            ?? throw new RuntimeException('Lote no encontrado.');
        $destino = bco_destino_accion($c, $pagina_id, $accion_js, intval($i['tabla_estado_registro_id']));
        if ($destino !== 38) throw new RuntimeException('Acción no válida.');
        $n = intval(bco_fila($c, "SELECT COUNT(*) n FROM gestion__bancos_extracto WHERE importacion_id = ? AND estado = 'CONCILIADA' FOR UPDATE", 'i', $id)['n']);
        if ($n > 0) throw new RuntimeException("El lote tiene $n líneas conciliadas: deshacé esas conciliaciones antes de anularlo.");
        // Las conciliaciones deshechas conservan sus items (auditoría): un lote con líneas que pasaron por una no se borra
        if (bco_fila($c, "SELECT 1 FROM gestion__bancos_conciliaciones_items it JOIN gestion__bancos_extracto x ON x.extracto_id = it.extracto_id
                          WHERE x.importacion_id = ? LIMIT 1", 'i', $id)) {
            throw new RuntimeException('El lote tiene líneas que estuvieron en conciliaciones (ya deshechas): no se anula, para conservar ese historial. Ignorá las líneas que sobren.');
        }
        // Otro lote vigente que se superpone omitió esas líneas como duplicadas: borrarlas le dejaría un hueco
        $otro = bco_fila($c, "SELECT o.importacion_id FROM gestion__bancos_importaciones i JOIN gestion__bancos_importaciones o
                                ON o.empresa_id = i.empresa_id AND o.tesoreria_cuenta_id = i.tesoreria_cuenta_id AND o.importacion_id <> i.importacion_id
                               AND o.tabla_estado_registro_id = 5 AND o.f_desde <= i.f_hasta AND o.f_hasta >= i.f_desde
                              WHERE i.importacion_id = ? LIMIT 1", 'i', $id);
        if ($otro) throw new RuntimeException('El lote se superpone en fechas con el lote ' . $otro['importacion_id'] . ', que tomó de este los movimientos en común: anulá primero el más nuevo.');
        bco_exec($c, "DELETE FROM gestion__bancos_extracto WHERE importacion_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id);
        // El hash del archivo deja de bloquear una nueva importación del mismo archivo (queda el nombre y la ruta)
        $hash = hash('sha256', $i['archivo_hash'] . '#anulado#' . $id);
        bco_exec($c, "UPDATE gestion__bancos_importaciones SET tabla_estado_registro_id = 38, archivo_hash = ?, usuario_modificacion_id = ?
                      WHERE importacion_id = ? AND empresa_id = ?", 'siii', $hash, $usuario_id, $id, $empresa_id);
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    return 38;
}
