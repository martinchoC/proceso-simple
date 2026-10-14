<?php
require __DIR__ . '/../modules/gestion/entidades_model.php';

echo "Probando obtenerEntidades con empresa_id = 2:\n";
$lista = obtenerEntidades($conexion, 2, 50);
echo "Total entidades empresa 2: " . count($lista) . "\n";
if (!empty($lista)) {
    $e = $lista[0];
    echo "Entidad: {$e['entidad_nombre']}\n";
    echo "Roles Info: " . json_encode($e['roles_info']) . "\n";
}
