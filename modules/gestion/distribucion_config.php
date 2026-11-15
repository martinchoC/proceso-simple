<?php
/**
 * Vista: Distribución - Configuración
 * Módulo 2 — Página ID 148 (sin tabla de proceso)
 * Zonas de reparto, zona de cada cliente y camiones (doc 24, paso 5b-2). Código común: core/distribucion/,
 * templates/distribucion/config.php y assets/js/distribucion-config.js.
 * Script: _local/sql/mantenimiento/20261119_distribucion_02_paginas.sql
 */
$pageTitle = "Distribución - Configuración";
$currentPage = 'distribucion_config';
$modudo_idx = 2;
$pagina_idx = 148;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/distribucion/distribucion_config_abm.php';

$empresa_id = intval($current_empresa_id ?? 0);
$vdis_estructura = vdis_estructura($conn);
$vdis_activo = $vdis_estructura && vdis_empresa_usa($conn, $empresa_id);
require ROOT_PATH . '/templates/distribucion/config.php';
?>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/distribucion-config.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/distribucion-config.js') ?>"></script>
<?php if ($vdis_estructura): ?>
<script>$(function () { DistConfig.init({ ajaxUrl: 'distribucion_config_ajax.php', empresaIdx: <?= $empresa_id ?> }); });</script>
<?php endif; ?>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
