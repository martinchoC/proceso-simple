<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/pagos_model.php';

$funcs = get_defined_functions()['user'];
foreach ($funcs as $f) {
    $rf = new ReflectionFunction($f);
    if (strpos($rf->getFileName(), 'pagos_model.php') !== false) {
        $params = [];
        foreach ($rf->getParameters() as $p) {
            $params[] = '$' . $p->getName();
        }
        echo $f . '(' . implode(', ', $params) . ") [Línea {$rf->getStartLine()}]\n";
    }
}
