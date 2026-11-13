<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 137;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/aprobaciones_reglas_model.php';
require_once __DIR__ . '/../../core/obligaciones/obligaciones_ajax.php';

obl_ajax_reglas($conexion, $ctx, $pagina_idx);
