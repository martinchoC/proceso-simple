<?php
$c = file_get_contents('templates/adminlte/js/adminlte.js');
$pos = strpos($c, 'class Treeview');
if ($pos !== false) {
    echo substr($c, $pos, 4500);
}
