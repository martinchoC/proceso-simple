<?php
/**
 * AJAX: Bandeja de Documentos — Módulo 2, página 141. Código común en core/bandeja/bandeja_ajax.php.
 * Usa el modelo de Facturas de proveedores y el de lectura de este módulo (página 57).
 */
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 141;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // un aviso al incluir los modelos no rompe el JSON
ini_set('display_errors', '0');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/facturas_proveedores_model.php';
require_once __DIR__ . '/pagos_model.php';
require_once __DIR__ . '/facturas_proveedores_lectura_model.php';
require_once __DIR__ . '/../../core/bandeja/bandeja_ajax.php';

bdj_despachar($conexion, $ctx, $pagina_idx);
