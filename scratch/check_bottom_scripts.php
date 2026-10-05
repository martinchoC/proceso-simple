<?php
$html = file_get_contents(__DIR__ . '/output_gestion_index.html');
$pos = strrpos($html, '<script');
echo substr($html, $pos - 500);
