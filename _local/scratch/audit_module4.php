<?php
$dir = 'c:/xampp/htdocs/casalucho/modules/estudio_contable';
$php_views = glob("$dir/*.php");
$php_views = array_filter($php_views, fn($f) => !strpos($f, '_ajax') && !strpos($f, '_model') && !strpos($f, 'index.php'));

echo "=== 1. AUDITORIA CONVENCIONES: \$modudo_idx = 4 ===\n";
foreach ($php_views as $v) {
    $content = file_get_contents($v);
    $has_modudo = (strpos($content, '$modudo_idx = 4;') !== false || strpos($content, '$modudo_idx = 4 ;') !== false);
    $has_pagina = (strpos($content, '$pagina_idx') !== false);
    echo basename($v) . " -> modudo_idx: " . ($has_modudo ? 'OK' : 'MISSING') . " | pagina_idx: " . ($has_pagina ? 'OK' : 'MISSING') . "\n";
}

echo "\n=== 2. AUDITORIA AJAX: FORMATO CANONICO JSON ===\n";
$ajax_files = glob("$dir/*_ajax.php");
foreach ($ajax_files as $a) {
    $content = file_get_contents($a);
    $has_header = (strpos($content, 'application/json') !== false);
    $has_success = (strpos($content, "'success'") !== false || strpos($content, '"success"') !== false);
    echo basename($a) . " -> JSON header: " . ($has_header ? 'OK' : 'MISSING') . " | Format: " . ($has_success ? 'OK' : 'MISSING') . "\n";
}

echo "\n=== 3. AUDITORIA SQL: WHERE empresa_id = ? EN MODELOS ===\n";
$model_files = glob("$dir/*_model.php");
foreach ($model_files as $m) {
    $content = file_get_contents($m);
    $has_empresa = (strpos($content, 'empresa_id') !== false);
    $has_prep = (strpos($content, 'mysqli_prepare') !== false || strpos($content, 'mysqli_query') !== false);
    echo basename($m) . " -> empresa_id filter: " . ($has_empresa ? 'OK' : 'MISSING') . " | prepared stmts: " . ($has_prep ? 'OK' : 'MISSING') . "\n";
}
