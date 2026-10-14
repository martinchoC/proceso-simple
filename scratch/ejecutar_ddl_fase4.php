<?php
require_once __DIR__ . '/../db.php';

$sqlFile = __DIR__ . '/../sql/mantenimiento/20261011_sindicatos_07_ddl_fase4.sql';
if (!file_exists($sqlFile)) {
    die("Archivo no encontrado: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

try {
    $lines = file($sqlFile);
    $query = '';
    $errores = [];
    $ok = 0;
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
            continue;
        }
        $query .= $line;
        if (substr(rtrim($trimmed), -1) === ';') {
            if (!$conn->query($query)) {
                $errores[] = "Error: " . $conn->error . "\nSQL: " . substr($query, 0, 100) . "...";
            } else {
                $ok++;
            }
            $query = '';
        }
    }
    
    echo "Sentencias exitosas: $ok\n";
    if (!empty($errores)) {
        echo "Errores encontrados (" . count($errores) . "):\n";
        foreach ($errores as $err) {
            echo "$err\n";
        }
    } else {
        echo "Todas las sentencias se ejecutaron correctamente.\n";
    }
} catch (Throwable $e) {
    echo "Excepcion: " . $e->getMessage() . "\n";
}
