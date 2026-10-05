<?php
$c = file_get_contents('templates/adminlte/js/adminlte.js');
preg_match_all('/class\s+(\w+)/', $c, $m);
print_r($m[1]);

preg_match_all('/data-lte-toggle="([^"]+)"/', $c, $m2);
print_r(array_unique($m2[1]));
