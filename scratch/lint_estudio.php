<?php
$dir = __DIR__ . '/../modules/estudio_contable';
$files = glob($dir . '/*.php');

$errors = 0;
foreach ($files as $file) {
    $out = [];
    $ret = 0;
    exec("C:\\xampp\\php\\php.exe -l \"{$file}\"", $out, $ret);
    if ($ret !== 0) {
        echo "ERROR en " . basename($file) . ":\n" . implode("\n", $out) . "\n";
        $errors++;
    } else {
        echo "OK: " . basename($file) . "\n";
    }
}

echo "\nTotal analizados: " . count($files) . " archivos. Errores: {$errors}\n";
