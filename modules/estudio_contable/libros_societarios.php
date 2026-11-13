<?php
/**
 * Vista: Libros Societarios — libros rubricados, actas y demás actos societarios, autoridades e inscripciones.
 * Módulo 4 — Página ID 424. Código común: core/libros_societarios/, templates/libros_societarios/ y
 * assets/js/libros-societarios.js. Script: _local/sql/mantenimiento/20261108_libros_societarios_01.sql
 */
$pageTitle = "Libros Societarios";
$currentPage = 'libros_societarios';
$modudo_idx = 4;
$pagina_idx = 424;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';

$lsoc_modo = 'estudio';
$lsoc_seccion = 'Gestión de Clientes';
require ROOT_PATH . '/templates/libros_societarios/libros_societarios_vista.php';
require_once __DIR__ . '/estudio_cliente_activo.php';
estudio_cliente_activo_script((int) $current_empresa_id);
?>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>
    window.LSOC_AJAX = 'libros_societarios_ajax.php';
    window.LSOC_MODO = <?= json_encode($lsoc_modo) ?>;
    window.LSOC_CSRF = <?= json_encode(csrf_token()) ?>;
</script>
<script src="<?= asset_local('js/libros-societarios.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/libros-societarios.js') ?>"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
