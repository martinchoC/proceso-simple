<?php
$c = file_get_contents('assets/js/adminlte.min.js');
echo "Has treeview: " . (strpos($c, 'treeview') !== false ? 'YES' : 'NO') . "\n";
echo "Has data-lte-toggle: " . (strpos($c, 'data-lte-toggle') !== false ? 'YES' : 'NO') . "\n";
preg_match_all('/data-lte-toggle="?[a-zA-Z0-9_-]+"?/', $c, $m);
print_r(array_unique($m[0]));
