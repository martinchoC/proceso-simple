<?php
// Uso: php _local/tests/bancos/volcar_planilla.php <archivo> [XLS|XLSX|CSV]
require_once __DIR__ . '/../../../core/bancos/planilla_lector.php';
$r = bco_planilla_leer($argv[1], $argv[2] ?? 'XLS');
foreach ($r['hojas'] as $nombre => $filas) {
    echo "== hoja '$nombre': " . count($filas) . " filas\n";
    foreach ($filas as $f => $celdas) {
        echo str_pad((string)($f + 1), 4, ' ', STR_PAD_LEFT) . ' | ';
        foreach ($celdas as $c => $v) echo chr(65 + $c) . '=' . var_export($v, true) . '  ';
        echo "\n";
    }
}
