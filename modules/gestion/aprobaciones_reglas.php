<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Reglas de Aprobación";
$currentPage = 'aprobaciones_reglas';
$modudo_idx = 2;
$pagina_idx = 137; // ID en conf__paginas para Reglas de Aprobación
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <?php ui_encabezado('Reglas de Aprobación', 'bi bi-check2-square', 'Qué obligaciones de pago y qué órdenes de pago tienen que aprobarse antes de pagar, desde qué importe, cuándo hacen falta dos aprobaciones y qué perfiles pueden aprobar hasta qué importe. Sin reglas, nada requiere aprobación: quien carga una obligación la confirma y la paga. Para aprobar hace falta además la función "Aprobar" en el perfil.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <ul class="nav nav-tabs ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#solapaReglas" type="button" data-que="regla">Reglas</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#solapaTopes" type="button" data-que="tope">Quién aprueba</button></li>
            </ul>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom ui-barra">
                    <div id="contenedor-boton-agregar" class="d-inline"></div>
                </div>
                <div class="card-body p-3 tab-content">
                    <div class="tab-pane fade show active" id="solapaReglas">
                        <table id="tablaReglas" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-light text-nowrap">
                                <tr><th>Documento</th><th>Tipo</th><th>Requiere</th><th class="text-end">Desde</th><th class="text-end">Doble desde</th><th class="ui-detalle">Autoaprobación</th><th>Vigencia</th><th class="text-center">Estado</th><th class="text-center">Acciones</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="solapaTopes">
                        <table id="tablaTopes" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-light text-nowrap">
                                <tr><th>Documento</th><th>Tipo</th><th>Perfil</th><th class="text-end">Aprueba hasta</th><th>Vigencia</th><th class="text-center">Estado</th><th class="text-center">Acciones</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalRegla" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalReglaLabel">Regla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formRegla" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="regla_id" name="regla_id">
                                <input type="hidden" id="tope_id" name="tope_id">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label ui-requerido" for="documento">Se aplica a</label>
                                        <select class="form-select" id="documento" name="documento" required>
                                            <option value="OBLIGACION">Obligaciones de pago</option>
                                            <option value="OP">Órdenes de pago</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8 solo-obligacion">
                                        <label class="form-label" for="obligacion_tipo_id">Tipo de obligación <?= ui_ayuda('Vacío = todos los tipos. La regla de un tipo gana sobre la general.') ?></label>
                                        <select class="form-select" id="obligacion_tipo_id" name="obligacion_tipo_id"></select>
                                    </div>
                                    <div class="col-md-4 solo-regla">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="requiere_aprobacion" name="requiere_aprobacion" value="1" checked>
                                            <label class="form-check-label" for="requiere_aprobacion">Requiere aprobación <?= ui_ayuda('Destildado sirve para que un tipo no requiera aprobación aunque la regla general sí.') ?></label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 solo-regla">
                                        <label class="form-label" for="importe_desde">Desde importe <?= ui_ayuda('0 = siempre.') ?></label>
                                        <input type="number" step="0.01" min="0" class="form-control text-end" id="importe_desde" name="importe_desde" value="0">
                                    </div>
                                    <div class="col-md-4 solo-regla">
                                        <label class="form-label" for="doble_aprobacion_desde">Dos aprobaciones desde <?= ui_ayuda('Vacío = una sola aprobación. Las dos tienen que ser de usuarios distintos.') ?></label>
                                        <input type="number" step="0.01" min="0" class="form-control text-end" id="doble_aprobacion_desde" name="doble_aprobacion_desde">
                                    </div>
                                    <div class="col-12 solo-regla">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="permite_autoaprobar" name="permite_autoaprobar" value="1">
                                            <label class="form-check-label" for="permite_autoaprobar">Quien la carga también la puede aprobar</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 solo-tope">
                                        <label class="form-label ui-requerido" for="empresa_perfil_id">Perfil</label>
                                        <select class="form-select" id="empresa_perfil_id" name="empresa_perfil_id"></select>
                                    </div>
                                    <div class="col-md-6 solo-tope">
                                        <label class="form-label" for="importe_maximo">Aprueba hasta <?= ui_ayuda('Vacío = sin tope.') ?></label>
                                        <input type="number" step="0.01" min="0" class="form-control text-end" id="importe_maximo" name="importe_maximo">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label ui-requerido" for="f_desde">Vigente desde</label>
                                        <input type="date" class="form-control" id="f_desde" name="f_desde" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="f_hasta">Hasta</label>
                                        <input type="date" class="form-control" id="f_hasta" name="f_hasta">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnGuardar"><i class="fas fa-save me-1"></i>Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        window.PAGINA_ID = <?= (int)$pagina_idx ?>;
        window.BCO_CSRF = <?= json_encode(csrf_token()) ?>;
    </script>
    <script src="<?= asset_local('js/bancos-comun.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/bancos-comun.js') ?>"></script>
    <script src="aprobaciones_reglas.js?v=<?= filemtime(__DIR__ . '/aprobaciones_reglas.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
