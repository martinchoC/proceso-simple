<?php
/**
 * Vista: Armado y hojas de ruta
 * Módulo 2 — Página ID 149 (sin tabla de proceso)
 * Armado del reparto, hojas de ruta, lista de carga y rendición (doc 24, pasos 5b-4 y 5b-5). Código común:
 * core/distribucion/, templates/distribucion/hojas.php y assets/js/distribucion-hojas.js.
 * Script: _local/sql/mantenimiento/20261119_distribucion_02_paginas.sql
 */
$pageTitle = "Armado y hojas de ruta";
$currentPage = 'distribucion_hojas';
$modudo_idx = 2;
$pagina_idx = 149;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/distribucion/distribucion_hojas.php';

$empresa_id = intval($current_empresa_id ?? 0);
$vdis_estructura = vdis_estructura($conn);
$vdis_activo = $vdis_estructura && vdis_empresa_usa($conn, $empresa_id);
$vdis_empresa_nombre = (string) (vcar_filas($conn, "SELECT empresa FROM conf__empresas WHERE empresa_id = ?", 'i', [$empresa_id])[0]['empresa'] ?? '');
require ROOT_PATH . '/templates/distribucion/hojas.php';
?>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/sortablejs-1.15.0/Sortable.min.js') ?>"></script>
<script src="<?= asset_local('js/distribucion-hojas.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/distribucion-hojas.js') ?>"></script>
<?php if ($vdis_activo): ?>
<script>$(function () { DistHojas.init({ ajaxUrl: 'distribucion_hojas_ajax.php', empresaIdx: <?= $empresa_id ?>, empresaNombre: <?= json_encode($vdis_empresa_nombre, JSON_UNESCAPED_UNICODE) ?> }); });</script>
<?php endif; ?>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
