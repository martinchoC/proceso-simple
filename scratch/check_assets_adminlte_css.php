<?php
$c = file_get_contents('assets/css/adminlte.min.css');
preg_match_all('/[^{}]*(?:nav-treeview|menu-open)[^{}]*\{[^}]*\}/i', $c, $m);
foreach ($m[0] as $rule) {
    echo trim($rule) . "\n---\n";
}
