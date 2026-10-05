<?php
$c = file_get_contents('templates/adminlte/js/adminlte.js');
preg_match_all('/(?:const|let|var)\s+Default\b[^\n]*/', $c, $m);
print_r($m[0]);
