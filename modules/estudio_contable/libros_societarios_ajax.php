<?php
/**
 * AJAX: Libros Societarios — Módulo 4, página 424. Código común en core/libros_societarios/libros_societarios_ajax.php.
 */
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 424;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // un aviso al incluir los modelos no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/libros_societarios/libros_societarios_ajax.php';

lsoc_despachar($conn, $ctx, $pagina_idx, 'estudio');
