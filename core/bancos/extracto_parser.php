<?php
/**
 * Extracto bancario: planilla leída (planilla_lector.php) + formato del banco -> movimientos normalizados.
 * Sin base de datos: el modelo de la página arma $formato desde gestion__bancos_formatos / _formatos_columnas
 * y persiste el resultado. Diseño en _local/docs/gestion/12_conciliacion_bancaria.md §4 y §10.
 *
 * $formato: claves de gestion__bancos_formatos (hoja, fila_inicio, formato_fecha, idioma_fecha, orden_archivo,
 *   saldo_posterior, cuenta_fila, cuenta_columna, cuenta_regex, referencia_regex, sep_decimal, sep_miles,
 *   modo_importe) + 'columnas' => ['FECHA' => 1, 'DESCRIPCION' => 2, ...] (0 = columna A).
 *
 * Resultado de bco_extracto_parsear():
 *   movimientos   en orden cronológico: nro_linea (fila del archivo, 1 = primera), f_movimiento, f_valor (Y-m-d),
 *                 descripcion, referencia, importe (con signo, < 0 débito), saldo, hash_mov
 *   cuenta_archivo n° de cuenta leído del encabezado (o null)
 *   f_desde, f_hasta, saldo_inicial, saldo_final, total (Σ importes), debitos, creditos
 *   control       ['pares' => n, 'pares_ok' => n, 'errores' => [...]]: cadena de saldos línea a línea
 *   avisos        textos para mostrar en la previsualización
 */

require_once __DIR__ . '/planilla_lector.php';

const BCO_MESES_ES = [
    'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
    'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'SET' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
];
const BCO_MESES_EN = [
    'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
    'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
];

/** Lee el archivo con el formato y devuelve el extracto normalizado. @throws RuntimeException */
function bco_extracto_desde_archivo(string $ruta, array $formato): array
{
    $planilla = bco_planilla_leer($ruta, (string)$formato['tipo_archivo'], [
        'delimitador' => (string)($formato['delimitador'] ?? ''),
        'codificacion' => (string)($formato['codificacion'] ?? 'UTF-8'),
    ]);
    return bco_extracto_parsear($planilla, $formato);
}

/** @throws RuntimeException */
function bco_extracto_parsear(array $planilla, array $formato): array
{
    $hojas = $planilla['hojas'] ?? [];
    if (!$hojas) throw new RuntimeException('El archivo no tiene hojas con datos.');
    $hoja = trim((string)($formato['hoja'] ?? ''));
    if ($hoja === '') {
        $filas = reset($hojas);
    } else {
        $filas = null;
        foreach ($hojas as $nombre => $f) {
            if (strcasecmp((string)$nombre, $hoja) === 0) { $filas = $f; break; }
        }
        if ($filas === null) {
            throw new RuntimeException('El archivo no tiene la hoja "' . $hoja . '" (tiene: ' . implode(', ', array_keys($hojas)) . ').');
        }
    }

    $col = $formato['columnas'] ?? [];
    if (!isset($col['FECHA'], $col['DESCRIPCION'])) throw new RuntimeException('El formato no tiene las columnas Fecha y Descripción.');
    $modo = (string)($formato['modo_importe'] ?? 'SIGNO');
    if ($modo === 'SIGNO' && !isset($col['IMPORTE'])) throw new RuntimeException('El formato no tiene la columna Importe.');
    if ($modo === 'DEBITO_CREDITO' && !isset($col['DEBITO'], $col['CREDITO'])) throw new RuntimeException('El formato no tiene las columnas Débito y Crédito.');

    $avisos = [];
    $cuentaArchivo = bco_extracto_cuenta($filas, $formato);

    // Filas de datos: desde fila_inicio hasta la primera fila con contenido y sin fecha válida
    $inicio = max(1, (int)($formato['fila_inicio'] ?? 2)) - 1;
    $movs = [];
    foreach ($filas as $f => $celdas) {
        if ($f < $inicio || !$celdas) continue;
        $fecha = bco_extracto_fecha($celdas[$col['FECHA']] ?? null, (string)($formato['formato_fecha'] ?? 'd/m/Y'), (string)($formato['idioma_fecha'] ?? 'ES'));
        if ($fecha === null) break;

        if ($modo === 'SIGNO') {
            $importe = bco_extracto_importe($celdas[$col['IMPORTE']] ?? null, $formato);
        } else {
            $deb = bco_extracto_importe($celdas[$col['DEBITO']] ?? null, $formato);
            $cre = bco_extracto_importe($celdas[$col['CREDITO']] ?? null, $formato);
            $importe = ($deb === null && $cre === null) ? null : round(abs((float)$cre) - abs((float)$deb), 2);
        }
        if ($importe === null) throw new RuntimeException('Fila ' . ($f + 1) . ': no se pudo leer el importe.');

        $descripcion = preg_replace('/\s+/u', ' ', trim((string)($celdas[$col['DESCRIPCION']] ?? '')));
        $referencia = isset($col['REFERENCIA']) ? trim((string)($celdas[$col['REFERENCIA']] ?? '')) : '';
        if ($referencia === '' && !empty($formato['referencia_regex'])) {
            $m = bco_regex_coincide((string)$formato['referencia_regex'], $descripcion, true);
            if (is_array($m)) $referencia = (string)($m[1] ?? $m[0]);
        }
        $fValor = isset($col['FECHA_VALOR'])
            ? bco_extracto_fecha($celdas[$col['FECHA_VALOR']] ?? null, (string)($formato['formato_fecha'] ?? 'd/m/Y'), (string)($formato['idioma_fecha'] ?? 'ES'))
            : null;
        $saldo = isset($col['SALDO']) ? bco_extracto_importe($celdas[$col['SALDO']] ?? null, $formato) : null;

        $movs[] = [
            'nro_linea' => $f + 1,
            'f_movimiento' => $fecha,
            'f_valor' => $fValor,
            'descripcion' => mb_substr($descripcion, 0, 300),
            'referencia' => $referencia === '' ? null : mb_substr($referencia, 0, 100),
            'importe' => round($importe, 2),
            'saldo' => $saldo === null ? null : round($saldo, 2),
        ];
    }
    if (!$movs) throw new RuntimeException('No se encontraron movimientos: revisá la fila de inicio y la columna de fecha del formato.');

    if (($formato['orden_archivo'] ?? 'ASC') === 'DESC') $movs = array_reverse($movs);
    for ($i = 1, $n = count($movs); $i < $n; $i++) {
        if ($movs[$i]['f_movimiento'] < $movs[$i - 1]['f_movimiento']) {
            $avisos[] = 'Las fechas no vienen en orden: revisá el orden del archivo en el formato.';
            break;
        }
    }

    // Cadena de saldos
    $control = ['pares' => 0, 'pares_ok' => 0, 'errores' => []];
    $saldoInicial = $saldoFinal = null;
    $conSaldo = isset($col['SALDO']) && !in_array(null, array_column($movs, 'saldo'), true);
    $posterior = (int)($formato['saldo_posterior'] ?? 1) === 1;
    if ($conSaldo) {
        $ult = count($movs) - 1;
        if ($posterior) {
            $saldoInicial = round($movs[0]['saldo'] - $movs[0]['importe'], 2);
            $saldoFinal = $movs[$ult]['saldo'];
        } else {
            $saldoInicial = $movs[0]['saldo'];
            $saldoFinal = round($movs[$ult]['saldo'] + $movs[$ult]['importe'], 2);
        }
        for ($i = 1; $i <= $ult; $i++) {
            $esperado = $posterior
                ? round($movs[$i - 1]['saldo'] + $movs[$i]['importe'], 2)
                : round($movs[$i - 1]['saldo'] + $movs[$i - 1]['importe'], 2);
            $control['pares']++;
            if (abs($esperado - $movs[$i]['saldo']) < 0.005) {
                $control['pares_ok']++;
            } else {
                $control['errores'][] = 'Fila ' . $movs[$i]['nro_linea'] . ': el saldo es ' . bco_fmt($movs[$i]['saldo'])
                    . ' y debería ser ' . bco_fmt($esperado) . ' (falta o sobra un movimiento cerca de esa fila).';
            }
        }
        // Si no cierra, probar el orden y el tipo de saldo invertidos: casi siempre es un error de configuración del formato
        if ($control['errores']) {
            $orden = ($formato['orden_archivo'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            foreach ([[true, $posterior], [false, !$posterior], [true, !$posterior]] as [$invertir, $post]) {
                if (bco_cadena_saldos_cierra($invertir ? array_reverse($movs) : $movs, $post)) {
                    $o = $invertir ? ($orden === 'DESC' ? 'ASC' : 'DESC') : $orden;
                    $avisos[] = 'Con "Orden de los movimientos: ' . ($o === 'DESC' ? 'del más nuevo al más viejo' : 'del más viejo al más nuevo')
                        . '" y el saldo ' . ($post ? 'posterior' : 'anterior') . ' al movimiento la cadena de saldos cierra: corregí esas opciones del formato.';
                    break;
                }
            }
        }
    } elseif (isset($col['SALDO'])) {
        $avisos[] = 'Hay filas sin saldo: no se pudo controlar la cadena de saldos.';
    } else {
        $avisos[] = 'El formato no tiene columna de saldo: no se puede controlar que no falten movimientos.';
    }

    $total = round(array_sum(array_column($movs, 'importe')), 2);
    if ($saldoInicial !== null && abs($saldoInicial + $total - $saldoFinal) >= 0.005) {
        $control['errores'][] = 'Saldo inicial + movimientos (' . bco_fmt($saldoInicial + $total) . ') no da el saldo final (' . bco_fmt($saldoFinal) . ').';
    }
    if (count($control['errores']) > 5) {   // en la pantalla alcanza con los primeros
        $resto = count($control['errores']) - 5;
        $control['errores'] = array_slice($control['errores'], 0, 5);
        $control['errores'][] = "Y $resto diferencias más en la cadena de saldos.";
    }

    bco_extracto_hashes($movs);
    $importes = array_column($movs, 'importe');
    return [
        'movimientos' => $movs,
        'cuenta_archivo' => $cuentaArchivo,
        'f_desde' => $movs[0]['f_movimiento'],
        'f_hasta' => $movs[count($movs) - 1]['f_movimiento'],
        'saldo_inicial' => $saldoInicial,
        'saldo_final' => $saldoFinal,
        'total' => $total,
        'debitos' => round(array_sum(array_filter($importes, static fn($v) => $v < 0)), 2),
        'creditos' => round(array_sum(array_filter($importes, static fn($v) => $v > 0)), 2),
        'control' => $control,
        'avisos' => $avisos,
    ];
}

/**
 * hash_mov = SHA-256 de fecha|descripción|importe|saldo|ordinal. El ordinal cuenta las apariciones previas de la
 * misma combinación dentro del archivo: dos movimientos legítimos idénticos no se descartan como duplicados, y
 * reimportar el mismo archivo (o uno que se superpone) da los mismos hashes.
 */
function bco_extracto_hashes(array &$movs): void
{
    $vistos = [];
    foreach ($movs as &$m) {
        $clave = $m['f_movimiento'] . '|' . mb_strtoupper($m['descripcion']) . '|' . number_format($m['importe'], 2, '.', '')
            . '|' . ($m['saldo'] === null ? '' : number_format($m['saldo'], 2, '.', ''));
        $ordinal = $vistos[$clave] = ($vistos[$clave] ?? 0) + 1;
        $m['hash_mov'] = hash('sha256', $clave . '|' . $ordinal);
    }
    unset($m);
}

/** N° de cuenta del encabezado según cuenta_fila / cuenta_columna / cuenta_regex del formato. */
function bco_extracto_cuenta(array $filas, array $formato): ?string
{
    if (empty($formato['cuenta_fila'])) return null;
    $celdas = $filas[(int)$formato['cuenta_fila'] - 1] ?? [];
    if ($formato['cuenta_columna'] !== null && $formato['cuenta_columna'] !== '') {
        $celdas = [$celdas[(int)$formato['cuenta_columna']] ?? ''];
    }
    foreach ($celdas as $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        if (empty($formato['cuenta_regex'])) return $v;
        $m = bco_regex_coincide((string)$formato['cuenta_regex'], $v, true);
        if (is_array($m)) return trim((string)($m[1] ?? $m[0]));
    }
    return null;
}

/** Compara n° de cuenta ignorando separadores: "6229-69485/0" = "6229 69485 0". */
function bco_cuenta_coincide(string $archivo, string $cuenta): bool
{
    $a = preg_replace('/\D/', '', $archivo);
    $b = preg_replace('/\D/', '', $cuenta);
    return $a !== '' && $b !== '' && ($a === $b || substr($a, -strlen($b)) === $b || substr($b, -strlen($a)) === $a);
}

/** true si los movimientos (en ese orden) encadenan sus saldos. */
function bco_cadena_saldos_cierra(array $movs, bool $posterior): bool
{
    for ($i = 1, $n = count($movs); $i < $n; $i++) {
        $esperado = $posterior ? $movs[$i - 1]['saldo'] + $movs[$i]['importe'] : $movs[$i - 1]['saldo'] + $movs[$i - 1]['importe'];
        if (abs(round($esperado, 2) - $movs[$i]['saldo']) >= 0.005) return false;
    }
    return count($movs) > 1;
}

/**
 * Formato de fecha al de PHP: acepta también el estilo Excel / planilla (dd-mmm-aaaa, dd/mm/yyyy).
 * d / dd = día, m / mm = mes, mmm / M = mes en letras, yyyy / aaaa / Y = año, yy = año de dos cifras.
 */
function bco_formato_fecha_normalizar(string $f): string
{
    return preg_replace_callback('/d+|m+|M+|y+|Y+|a+|j+|n+/', static function ($m) {
        $t = $m[0];
        $n = strlen($t);
        switch ($t[0]) {
            case 'd': case 'j': return 'd';
            case 'm': case 'n': return $n >= 3 ? 'M' : 'm';
            case 'M': return 'M';
            default: return ($n === 2 && $t[0] !== 'Y') ? 'y' : 'Y';   // y / a / Y
        }
    }, trim($f));
}

/** Fecha a Y-m-d, o null si no es una fecha. Acepta texto con el formato del banco o serial de Excel. */
function bco_extracto_fecha($valor, string $formato, string $idioma): ?string
{
    $formato = bco_formato_fecha_normalizar($formato);
    if ($valor === null || $valor === '') return null;
    if (is_float($valor) || is_int($valor)) {
        if ($valor < 20000 || $valor > 80000) return null;      // serial de Excel ~1954..2119
        return gmdate('Y-m-d', (int)round(($valor - 25569) * 86400));
    }
    $texto = trim((string)$valor);
    // Meses en texto (21-oct-2026): se traducen a número y el formato M pasa a m
    if (strpos($formato, 'M') !== false) {
        $meses = strtoupper($idioma) === 'EN' ? BCO_MESES_EN : BCO_MESES_ES;
        $texto = preg_replace_callback('/\p{L}+\.?/u', static function ($m) use ($meses) {
            $k = mb_strtoupper(mb_substr(rtrim($m[0], '.'), 0, 3));
            return isset($meses[$k]) ? sprintf('%02d', $meses[$k]) : $m[0];
        }, $texto);
        $formato = str_replace('M', 'm', $formato);
    }
    $d = DateTime::createFromFormat('!' . $formato, $texto);
    $errores = DateTime::getLastErrors();
    if (!$d || ($errores && ($errores['warning_count'] || $errores['error_count']))) return null;
    $anio = (int)$d->format('Y');
    if ($anio < 1990 || $anio > 2100) return null;
    return $d->format('Y-m-d');
}

/** Importe a float con signo, o null si la celda está vacía o no es un número. */
function bco_extracto_importe($valor, array $formato): ?float
{
    if ($valor === null || $valor === '') return null;
    if (is_float($valor) || is_int($valor)) return (float)$valor;
    $t = trim(str_replace(["\xC2\xA0", ' ', '$'], '', (string)$valor));
    if ($t === '' || $t === '-') return null;
    $negativo = false;
    if (preg_match('/^\((.*)\)$/', $t, $m)) { $t = $m[1]; $negativo = true; }
    if (substr($t, -1) === '-') { $t = substr($t, 0, -1); $negativo = true; }
    $miles = (string)($formato['sep_miles'] ?? '');
    $decimal = (string)($formato['sep_decimal'] ?? ',');
    if ($miles !== '') $t = str_replace($miles, '', $t);
    if ($decimal !== '.') $t = str_replace($decimal, '.', $t);
    if (!preg_match('/^[+-]?\d+(\.\d+)?$/', $t)) return null;
    $v = (float)$t;
    return $negativo ? -abs($v) : $v;
}

/**
 * Expresión regular de un usuario (formato o regla): sin delimitadores, case-insensitive, UTF-8.
 * Devuelve true/false, o los grupos si $grupos; null si la expresión no es válida o se pasó del límite.
 */
function bco_regex_coincide(string $patron, string $texto, bool $grupos = false)
{
    $re = '~' . str_replace('~', '\~', $patron) . '~iu';
    $limite = ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', '100000');
    $r = @preg_match($re, $texto, $m);
    ini_set('pcre.backtrack_limit', (string)$limite);
    if ($r === false) return null;
    if (!$grupos) return $r === 1;
    return $r === 1 ? $m : false;
}

/** Valida una expresión de usuario antes de guardarla. Devuelve el error o null. */
function bco_regex_validar(string $patron): ?string
{
    if ($patron === '') return 'La expresión está vacía.';
    if (mb_strlen($patron) > 200) return 'La expresión no puede superar los 200 caracteres.';
    if (bco_regex_coincide($patron, 'PRUEBA 123') === null) return 'La expresión regular no es válida.';
    return null;
}

function bco_fmt(float $v): string
{
    return number_format($v, 2, ',', '.');
}
