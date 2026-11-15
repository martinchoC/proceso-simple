<?php
/**
 * Vista: Escalas y promociones
 * Módulo 2 — Página ID 146 (sin tabla de proceso)
 * Escalas por cantidad, promociones y topes de descuento (doc 22, paso 4b). Código común: core/precios_venta_abm.php,
 * templates/precios_reglas_venta/pagina.php y assets/js/precios-reglas-venta.js.
 * Script: _local/sql/mantenimiento/20261117_reglas_precio_venta_02_pagina.sql
 */
$pageTitle = "Escalas y promociones";
$currentPage = 'precios_reglas_venta';
$modudo_idx = 2;
$pagina_idx = 146;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/precios_venta_abm.php';

$empresa_id = intval($current_empresa_id ?? 0);
$prva_estructura = prv_estructura($conn);
$prva_activo = $prva_estructura && prv_empresa_usa($conn, $empresa_id);
require ROOT_PATH . '/templates/precios_reglas_venta/pagina.php';
?>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/precios-reglas-venta.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/precios-reglas-venta.js') ?>"></script>
<?php if ($prva_estructura): ?>
<script>$(function () { PrecioReglas.init({ ajaxUrl: 'precios_reglas_venta_ajax.php', empresaIdx: <?= $empresa_id ?> }); });</script>
<?php endif; ?>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
