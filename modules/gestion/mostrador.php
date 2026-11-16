<?php
/**
 * Vista: Mostrador — venta rápida de mostrador (lector de códigos, cajas y unidades, cobro y factura en el momento).
 * Módulo 2 — Página ID 150. Código común: core/ventas_mostrador/, templates/mostrador/ y assets/js/mostrador.js.
 * Script: _local/sql/mantenimiento/20261124_mostrador_01.sql. Diseño: _local/docs/gestion/29_mostrador_minorista.md
 */
$pageTitle = "Mostrador";
$currentPage = 'mostrador';
$modudo_idx = 2;
$pagina_idx = 150;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';

require ROOT_PATH . '/templates/mostrador/mostrador_vista.php';
?>
<script>
    window.VMOS = { ajax: 'mostrador_ajax.php', imprimir: 'ventas_facturas_print.php', csrf: <?= json_encode(csrf_token()) ?>, empresa: <?= (int) $current_empresa_id ?> };
</script>
<script src="<?= asset_local('js/mostrador.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/mostrador.js') ?>"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
