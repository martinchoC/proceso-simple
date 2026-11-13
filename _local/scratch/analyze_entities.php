<?php
$files = [
    'gestion_normal/clientes_ajax.php',
    'gestion_normal/clientes_model.php',
    'gestion_normal/proveedores_ajax.php',
    'gestion_normal/proveedores_model.php',
    'gestion_normal/entidades_ajax.php',
    'gestion_normal/entidades_model.php',
    'gestion/clientes_ajax.php',
    'gestion/clientes_model.php',
    'gestion/proveedores_ajax.php',
    'gestion/proveedores_model.php',
    'gestion/entidades_ajax.php',
    'gestion/entidades_model.php'
];

foreach ($files as $f) {
    $path = __DIR__ . '/../../modules/' . $f;
    if (file_exists($path)) {
        echo "=== $f ===\n";
        $content = file_get_contents($path);
        preg_match_all('/function\s+([a-zA-Z0-9_]+)/', $content, $m);
        echo "Funciones: " . implode(', ', array_slice($m[1], 0, 10)) . "\n";
    }
}
