<?php
$html = file_get_contents(__DIR__ . '/output_gestion_index.html');
$pos = strpos($html, '<ul class="nav sidebar-menu');
if ($pos !== false) {
    $end = strpos($html, '</ul>', $pos);
    echo substr($html, $pos, 4000);
}
