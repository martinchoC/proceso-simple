<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 639;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/asistente_ia/asistente_ajax.php';

asis_despachar($conn, $ctx, $pagina_idx, __DIR__);
