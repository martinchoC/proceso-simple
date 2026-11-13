<?php
/**
 * Vista: Bandeja de Documentos — comprobantes recibidos desde archivo (pantalla o carpeta de entrada) y su lectura.
 * Módulo 2 — Página ID 141. Código común: core/bandeja/, templates/bandeja/ y assets/js/bandeja-documentos.js.
 * Script: _local/sql/mantenimiento/20261109_bandeja_documentos_01.sql
 */
$pageTitle = "Bandeja de Documentos";
$currentPage = 'bandeja_documentos';
$modudo_idx = 2;
$pagina_idx = 141;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';

require ROOT_PATH . '/templates/bandeja/bandeja_documentos_vista.php';
?>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>
    window.BDJ_AJAX = 'bandeja_documentos_ajax.php';
    window.BDJ_CSRF = <?= json_encode(csrf_token()) ?>;
</script>
<script src="<?= asset_local('js/bandeja-documentos.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/bandeja-documentos.js') ?>"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
