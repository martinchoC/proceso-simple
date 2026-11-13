<?php
/**
 * Vista: Asistente IA
 * Módulo 5: Gestión Normal — Página ID 639 (sin tabla propia)
 * Pedidos en lenguaje natural (facturas y remitos de venta, altas de clientes y proveedores, también desde la
 * constancia de inscripción de ARCA). La IA propone y el usuario confirma; se graba en Borrador.
 * Código común: core/asistente_ia/ y assets/js/asistente-ia.js. Script: _local/sql/mantenimiento/20261104_asistente_ia_01.sql
 */
$pageTitle = "Asistente IA";
$currentPage = 'asistente_ia';
$modudo_idx = 5;
$pagina_idx = 639;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';

$empresa_id = intval($current_empresa_id ?? 0);
require ROOT_PATH . '/templates/asistente_ia/asistente_ia_vista.php';
?>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>
    window.EMPRESA_ID = <?= $empresa_id ?>;
    window.ASIS_CSRF = <?= json_encode(csrf_token()) ?>;
    window.ASIS_AJAX = 'asistente_ia_ajax.php';
</script>
<script src="<?= asset_local('js/asistente-ia.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/asistente-ia.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
