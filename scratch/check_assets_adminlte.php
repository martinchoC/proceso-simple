<?php
$c = file_get_contents('assets/js/adminlte.min.js');
echo substr($c, 0, 500) . "\n";
echo "Length: " . strlen($c) . "\n";
