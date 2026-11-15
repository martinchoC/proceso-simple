<?php
/**
 * Vista: Vendedores y carteras
 * Módulo 5 — Página ID 647 (sin tabla de proceso)
 * Vendedores, usuario de cada uno y cartera de clientes con vigencia (doc 23, paso 5a-2). Código común:
 * core/vendedores.php, core/vendedores_abm.php, templates/vendedores/pagina.php y assets/js/vendedores.js.
 * Script: _local/sql/mantenimiento/20261118_vendedores_carteras_02_pagina.sql
 */
$pageTitle = "Vendedores y carteras";
$currentPage = 'vendedores';
$modudo_idx = 5;
$pagina_idx = 647;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/vendedores_abm.php';

$empresa_id = intval($current_empresa_id ?? 0);
$vabm_estructura = vcar_estructura($conn);
$vabm_activo = $vabm_estructura && vcar_empresa_usa($conn, $empresa_id);
require ROOT_PATH . '/templates/vendedores/pagina.php';
?>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/vendedores.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/vendedores.js') ?>"></script>
<?php if ($vabm_estructura): ?>
<script>$(function () { Vendedores.init({ ajaxUrl: 'vendedores_ajax.php', empresaIdx: <?= $empresa_id ?> }); });</script>
<?php endif; ?>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
