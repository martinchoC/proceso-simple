<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/estudio_contable/estudio_clientes_model.php';
$list = estudio_clientes_listar($conn, 2);
echo "Clientes en empresa 2: " . count($list) . PHP_EOL;
$combos = estudio_clientes_combos($conn, 2);
echo "Combos tipos: " . count($combos['tipos']) . PHP_EOL;
echo "Combos empresas ERP: " . count($combos['empresas_erp']) . PHP_EOL;
foreach ($combos['empresas_erp'] as $e) {
    echo "  Empresa ERP: ID=" . $e['empresa_id'] . ", Nombre=" . $e['empresa'] . PHP_EOL;
}
