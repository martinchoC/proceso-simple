<?php
/**
 * Criterios de aceptación del Paso 3 (plan 12_conciliacion_bancaria_plan_ejecucion.md) con el extracto de muestra.
 * Uso: php _local/tests/bancos/test_extracto_parser.php [ruta del .xls]
 * Sin base de datos. El extracto real no se versiona (_local/tests/fixtures/extracto_*.xls está en .gitignore).
 */
require_once __DIR__ . '/../../../core/bancos/extracto_reglas.php';

$archivo = $argv[1] ?? __DIR__ . '/../fixtures/extracto_muestra.xls';
$fallas = 0;
function check(string $que, bool $ok, string $detalle = ''): void
{
    global $fallas;
    if (!$ok) $fallas++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $que . ($detalle !== '' ? "  [$detalle]" : '') . "\n";
}

// Formato propuesto en §10 del diseño
$formato = [
    'tipo_archivo' => 'XLS', 'hoja' => 'report', 'fila_inicio' => 8, 'formato_fecha' => 'd-M-Y', 'idioma_fecha' => 'ES',
    'orden_archivo' => 'DESC', 'saldo_posterior' => 1, 'modo_importe' => 'SIGNO', 'sep_decimal' => ',', 'sep_miles' => '.',
    'cuenta_fila' => 5, 'cuenta_columna' => null, 'cuenta_regex' => 'Cuenta:\s*([0-9\-/]+)',
    'referencia_regex' => '\(?(\d{11})\)?',
    'columnas' => ['FECHA' => 1, 'DESCRIPCION' => 2, 'IMPORTE' => 3, 'SALDO' => 4],
];
// Reglas propuestas en §10 (concepto_id numérico; $codigos lo traduce para los controles)
$codigos = [1 => 'COMISION', 2 => 'LEY25413', 3 => 'SIRCREB', 4 => 'DEBIN', 5 => 'PAGO_SERVICIOS', 6 => 'FCI', 7 => 'TRANSFERENCIA'];
$reglas = [];
foreach ([
    [10, 'EMPIEZA', 'COMISION', 'DEBITO', 'COMISION'],
    [20, 'REGEX', '^IMPUESTO (DEBITO|CREDITO)\s*-\s*LEY 25413', 'DEBITO', 'LEY25413'],
    [30, 'CONTIENE', 'SIRCREB', 'DEBITO', 'SIRCREB'],
    [40, 'EMPIEZA', 'DB.DEBIN', 'DEBITO', 'DEBIN'],
    [50, 'EMPIEZA', 'P.SERV', 'DEBITO', 'PAGO_SERVICIOS'],
    [60, 'CONTIENE', 'FONDO COMUN DE INVERSION', 'AMBOS', 'FCI'],
    [70, 'EMPIEZA', 'TRANSF', 'AMBOS', 'TRANSFERENCIA'],
    [70, 'EMPIEZA', 'CR.TRAN.', 'AMBOS', 'TRANSFERENCIA'],
] as $i => [$prio, $tipo, $patron, $aplica, $concepto]) {
    check("regla $patron válida", bco_regla_validar($tipo, $patron, $aplica) === null);
    $reglas[] = ['regla_id' => $i + 1, 'prioridad' => $prio, 'tipo_coincidencia' => $tipo, 'patron' => $patron, 'aplica_a' => $aplica, 'concepto_id' => array_search($concepto, $codigos, true)];
}
check('regex inválida rechazada', bco_regla_validar('REGEX', '(sin cerrar', 'AMBOS') !== null);

echo "Lectura y parser\n";
$x = bco_extracto_desde_archivo($archivo, $formato);
$m = $x['movimientos'];
check('49 movimientos', count($m) === 49, (string)count($m));
check('desde 2026-10-02', $x['f_desde'] === '2026-10-02', $x['f_desde']);
check('hasta 2026-10-21', $x['f_hasta'] === '2026-10-21', $x['f_hasta']);
check('orden cronológico (primera fila = última del archivo)', $m[0]['nro_linea'] === 56 && end($m)['nro_linea'] === 8);
check('cadena de saldos 48/48', $x['control']['pares'] === 48 && $x['control']['pares_ok'] === 48,
    $x['control']['pares_ok'] . '/' . $x['control']['pares']);
check('sin errores de control', !$x['control']['errores'], implode(' ', $x['control']['errores']));
check('saldo inicial 499.268,35', abs($x['saldo_inicial'] - 499268.35) < 0.005, bco_fmt($x['saldo_inicial']));
check('saldo final 90.321,62', abs($x['saldo_final'] - 90321.62) < 0.005, bco_fmt($x['saldo_final']));
check('Σ importes −408.946,73', abs($x['total'] + 408946.73) < 0.005, bco_fmt($x['total']));
check('cuenta del encabezado 6229-69485/0', $x['cuenta_archivo'] === '6229-69485/0', (string)$x['cuenta_archivo']);
check('cuenta coincide ignorando separadores', bco_cuenta_coincide($x['cuenta_archivo'], '6229 69485 0'));
check('cuenta distinta no coincide', !bco_cuenta_coincide($x['cuenta_archivo'], '1234-5678/9'));
check('referencia CUIT extraída de las transferencias', count(array_filter($m, fn($r) => $r['referencia'] !== null && strlen($r['referencia']) === 11)) > 0);

echo "Clasificación\n";
$c = bco_reglas_clasificar($m, $reglas);
foreach ($c as &$r) $r['concepto_id'] = $codigos[$r['concepto_id']] ?? '';
unset($r);
$por = array_count_values(array_map(fn($r) => (string)$r['concepto_id'], $c));
ksort($por);
check('4 COMISION', ($por['COMISION'] ?? 0) === 4, (string)($por['COMISION'] ?? 0));
check('23 LEY25413', ($por['LEY25413'] ?? 0) === 23, (string)($por['LEY25413'] ?? 0));
check('8 SIRCREB', ($por['SIRCREB'] ?? 0) === 8, (string)($por['SIRCREB'] ?? 0));
$propios = array_sum(array_intersect_key($por, array_flip(['DEBIN', 'PAGO_SERVICIOS', 'FCI', 'TRANSFERENCIA'])));
check('14 movimientos propios', $propios === 14, json_encode($por));
check('ninguna línea sin concepto', !isset($por['']), (string)($por[''] ?? 0));
check('estado CLASIFICADA en todas', count(array_filter($c, fn($r) => $r['estado'] === 'CLASIFICADA')) === 49);
$ley = array_filter($c, fn($r) => $r['concepto_id'] === 'LEY25413');
check('Ley 25.413 suma −31.854,87', abs(array_sum(array_column($ley, 'importe')) + 31854.87) < 0.005, bco_fmt(array_sum(array_column($ley, 'importe'))));
$sir = array_filter($c, fn($r) => $r['concepto_id'] === 'SIRCREB');
check('SIRCREB suma −38.131,40', abs(array_sum(array_column($sir, 'importe')) + 38131.40) < 0.005, bco_fmt(array_sum(array_column($sir, 'importe'))));
$com = array_filter($c, fn($r) => $r['concepto_id'] === 'COMISION');
check('comisiones suman −1.874,45', abs(array_sum(array_column($com, 'importe')) + 1874.45) < 0.005, bco_fmt(array_sum(array_column($com, 'importe'))));

echo "Hash de línea\n";
$y = bco_extracto_desde_archivo($archivo, $formato);
check('hash estable entre lecturas', array_column($m, 'hash_mov') === array_column($y['movimientos'], 'hash_mov'));
check('hashes únicos en el archivo', count(array_unique(array_column($m, 'hash_mov'))) === 49);
$dup = [$m[0], $m[0]];
unset($dup[0]['hash_mov'], $dup[1]['hash_mov']);
bco_extracto_hashes($dup);
check('dos movimientos idénticos tienen hash distinto (ordinal)', $dup[0]['hash_mov'] !== $dup[1]['hash_mov']);

echo "Casos de formato\n";
check('fecha 21-oct-2026', bco_extracto_fecha('21-oct-2026', 'd-M-Y', 'ES') === '2026-10-21');
check('fecha 05-set-2026', bco_extracto_fecha('05-set-2026', 'd-M-Y', 'ES') === '2026-09-05');
check('fecha 03/01/2026', bco_extracto_fecha('03/01/2026', 'd/m/Y', 'ES') === '2026-01-03');
check('fecha inválida 32/01/2026', bco_extracto_fecha('32/01/2026', 'd/m/Y', 'ES') === null);
check('texto de pie no es fecha', bco_extracto_fecha('Esta información es la que consta', 'd-M-Y', 'ES') === null);
check('serial de Excel 46316 = 2026-10-21', bco_extracto_fecha(46316.0, 'd/m/Y', 'ES') === '2026-10-21');
$f = ['sep_decimal' => ',', 'sep_miles' => '.'];
check('importe 1.234,56', bco_extracto_importe('1.234,56', $f) === 1234.56);
check('importe (1.234,56)', bco_extracto_importe('(1.234,56)', $f) === -1234.56);
check('importe 1.234,56-', bco_extracto_importe('1.234,56-', $f) === -1234.56);
check('importe $ -12,30', bco_extracto_importe('$ -12,30', $f) === -12.30);
check('importe texto inválido', bco_extracto_importe('abc', $f) === null);

echo "CSV (mismos datos, columnas Débito / Crédito)\n";
$tmp = tempnam(sys_get_temp_dir(), 'bco') . '.csv';
$fh = fopen($tmp, 'w');
fwrite($fh, "Fecha;Descripcion;Debito;Credito;Saldo\n");
foreach ($m as $r) {
    fwrite($fh, implode(';', [
        date('d/m/Y', strtotime($r['f_movimiento'])), str_replace(';', ' ', $r['descripcion']),
        $r['importe'] < 0 ? number_format(-$r['importe'], 2, ',', '.') : '',
        $r['importe'] > 0 ? number_format($r['importe'], 2, ',', '.') : '',
        number_format($r['saldo'], 2, ',', '.'),
    ]) . "\n");
}
fclose($fh);
$csv = bco_extracto_desde_archivo($tmp, [
    'tipo_archivo' => 'CSV', 'delimitador' => ';', 'fila_inicio' => 2, 'formato_fecha' => 'd/m/Y', 'idioma_fecha' => 'ES',
    'orden_archivo' => 'ASC', 'saldo_posterior' => 1, 'modo_importe' => 'DEBITO_CREDITO', 'sep_decimal' => ',', 'sep_miles' => '.',
    'columnas' => ['FECHA' => 0, 'DESCRIPCION' => 1, 'DEBITO' => 2, 'CREDITO' => 3, 'SALDO' => 4],
]);
unlink($tmp);
check('CSV: 49 movimientos y 48/48', count($csv['movimientos']) === 49 && $csv['control']['pares_ok'] === 48);
check('CSV: mismo total', abs($csv['total'] - $x['total']) < 0.005);
check('CSV: mismos hashes que el XLS', array_column($csv['movimientos'], 'hash_mov') === array_column($m, 'hash_mov'));

echo $fallas ? "\n$fallas FALLAS\n" : "\nTodo OK\n";
exit($fallas ? 1 : 0);
