<?php
/**
 * AJAX: Mostrador — Módulo 5, página 650. Factura con la página Facturas de venta (556) del módulo.
 * Código común en core/ventas_mostrador/mostrador_ajax.php.
 */
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 650;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);
ob_start();   // un aviso al incluir los modelos no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/ventas_facturas_model.php';
require_once __DIR__ . '/tesoreria_core_model.php';   // como ventas_facturas_ajax.php: cobro de contado
require_once __DIR__ . '/cobranzas_model.php';
require_once __DIR__ . '/../../core/ventas_mostrador/mostrador_ajax.php';

vmos_despachar($conn, $ctx, $pagina_idx, 556);
