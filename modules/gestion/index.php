<?php
/**
 * Pantalla de inicio del módulo: indicadores, accesos directos y su personalización por usuario.
 * Sin SQL: la grilla la arma assets/js/dashboard/widgets.js con lo que devuelve inicio_ajax.php
 * (motor en core/dashboard/). Diseño: _local/docs/gestion/11_dashboard_inicio.md
 */
$pageTitle = "Inicio";
$modudo_idx = 2;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';

$dash_config = [
    'endpoint' => 'inicio_ajax.php',
    'csrf'     => csrf_token(),
    'login'    => url('login.php'),
];
?>

<main class="app-main">
    <?php ui_encabezado(
        'Inicio',
        'bi bi-house-door',
        'Tus indicadores y accesos directos. Con Personalizar elegís qué ver y en qué orden.',
        [],
        '<button type="button" class="btn btn-outline-secondary btn-sm" id="dash-personalizar"><i class="bi bi-sliders me-1"></i>Personalizar</button>'
    ); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row g-3" id="dash-grilla" aria-live="polite"></div>
            <div class="dash-vacio d-none" id="dash-vacio">
                <i class="bi bi-grid-3x3-gap"></i>
                <p class="mb-2">No tenés indicadores para mostrar.</p>
                <button type="button" class="btn btn-primary btn-sm" data-dash-abrir-config>Elegir indicadores</button>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="dash-modal" tabindex="-1" aria-labelledby="dash-modal-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dash-modal-titulo">Personalizar inicio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav ui-solapas mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dash-tab-widgets" type="button" role="tab">Indicadores</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#dash-tab-accesos" type="button" role="tab">Accesos directos</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="dash-tab-widgets" role="tabpanel">
                        <p class="ui-sub mb-2">Arrastrá para ordenar. Desmarcá los que no quieras ver.</p>
                        <ul class="dash-orden list-unstyled mb-0" id="dash-lista-widgets"></ul>
                    </div>
                    <div class="tab-pane fade" id="dash-tab-accesos" role="tabpanel">
                        <div id="dash-bloque-obligatorios">
                            <div class="ui-seccion-titulo">Fijos de tu perfil <?= ui_ayuda('Los define tu perfil y no se pueden quitar.') ?></div>
                            <ul class="dash-orden list-unstyled mb-3" id="dash-lista-obligatorios"></ul>
                        </div>
                        <div class="ui-seccion-titulo">Tus accesos</div>
                        <ul class="dash-orden list-unstyled mb-1" id="dash-lista-elegidos"></ul>
                        <p class="ui-sub d-none" id="dash-elegidos-vacio">Todavía no elegiste accesos.</p>
                        <div class="ui-seccion-titulo mt-3">Disponibles</div>
                        <ul class="dash-orden list-unstyled mb-0" id="dash-lista-disponibles"></ul>
                        <p class="ui-sub d-none" id="dash-disponibles-vacio">No hay más páginas para agregar.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-secondary me-auto" id="dash-restablecer">Restablecer a los valores del perfil</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="dash-guardar">Guardar</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= asset_local('css/dashboard.css') ?>?v=<?= @filemtime(ROOT_PATH . '/assets/css/dashboard.css') ?>">
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>window.DASH_CONFIG = <?= json_encode($dash_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= asset_local('js/dashboard/widgets.js') ?>?v=<?= @filemtime(ROOT_PATH . '/assets/js/dashboard/widgets.js') ?>"></script>
<script src="<?= asset_local('js/dashboard/config.js') ?>?v=<?= @filemtime(ROOT_PATH . '/assets/js/dashboard/config.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
