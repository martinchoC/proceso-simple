<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Regímenes de Retención";
$currentPage = 'regimenes_retencion';
$modudo_idx = 5;
$pagina_idx = 624; // Regímenes de Retención (catálogo global; solo superadmin)
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Regímenes de retención','fas fa-hand-holding-usd', 'Catálogo global: lo usan todas las empresas agentes de retención', ['Tablas']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <div id="contenedor-boton-agregar" class="d-inline"></div>
                    <div class="float-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 small mb-2">
                        Los valores (alícuotas, mínimos, escalas) son normativos: cárguelos según indique el contador. Una alícuota vacía
                        queda "a confirmar" y el cálculo de la orden de pago no retiene con ella. Para cambiar un valor desde una fecha,
                        cierre la vigencia del régimen y cargue uno nuevo con el mismo código.
                    </div>
                    <div class="table-responsive">
                        <table id="tablaRegimenes" class="table table-striped table-bordered table-sm" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Impuesto</th>
                                    <th>Jurisdicción</th>
                                    <th>Código</th>
                                    <th>Concepto</th>
                                    <th class="text-end">% Inscripto</th>
                                    <th class="text-end">% No inscripto</th>
                                    <th class="text-end">Mínimo no sujeto</th>
                                    <th>Mínimo</th>
                                    <th>Vigencia</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRegimen" tabindex="-1" aria-labelledby="modalRegimenLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegimenLabel">Régimen de retención</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formRegimen" novalidate>
                        <input type="hidden" id="regimen_id" name="regimen_id">
                        <div class="row g-2">
                            <div class="col-md-4"><label class="form-label small mb-0">Impuesto *</label>
                                <select class="form-select form-select-sm" id="impuesto_tipo_id" name="impuesto_tipo_id" required></select></div>
                            <div class="col-md-4 rreg-iibb"><label class="form-label small mb-0">Jurisdicción *</label>
                                <select class="form-select form-select-sm" id="jurisdiccion_id" name="jurisdiccion_id"></select></div>
                            <div class="col-md-2"><label class="form-label small mb-0">Código *</label>
                                <input type="text" class="form-control form-control-sm" id="codigo" name="codigo" maxlength="10" required></div>
                            <div class="col-md-6"><label class="form-label small mb-0">Concepto *</label>
                                <input type="text" class="form-control form-control-sm" id="regimen" name="regimen" maxlength="150" required></div>
                            <div class="col-md-6"><label class="form-label small mb-0">Base de cálculo (de las facturas pagadas)</label>
                                <select class="form-select form-select-sm" id="base_calculo" name="base_calculo"></select></div>
                            <div class="col-md-3"><label class="form-label small mb-0">Alícuota inscripto %</label>
                                <input type="number" step="0.0001" min="0" max="100" class="form-control form-control-sm" id="alicuota_inscripto" name="alicuota_inscripto" placeholder="a confirmar"></div>
                            <div class="col-md-3"><label class="form-label small mb-0">Alícuota no inscripto %</label>
                                <input type="number" step="0.0001" min="0" max="100" class="form-control form-control-sm" id="alicuota_no_inscripto" name="alicuota_no_inscripto" placeholder="a confirmar"></div>
                            <div class="col-md-3"><label class="form-label small mb-0">Mínimo no sujeto</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="minimo_no_sujeto" name="minimo_no_sujeto"></div>
                            <div class="col-md-3"><label class="form-label small mb-0">Retención mínima</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="minimo_retencion" name="minimo_retencion"></div>
                            <div class="col-md-6"><label class="form-label small mb-0">El mínimo se compara con</label>
                                <select class="form-select form-select-sm" id="tipo_minimo" name="tipo_minimo"></select></div>
                            <div class="col-md-6"><label class="form-label small mb-0">Si se supera el mínimo</label>
                                <select class="form-select form-select-sm" id="modo_minimo" name="modo_minimo"></select></div>
                            <div class="col-md-3"><label class="form-label small mb-0">Vigencia desde *</label>
                                <input type="date" class="form-control form-control-sm" id="f_desde" name="f_desde" required></div>
                            <div class="col-md-3"><label class="form-label small mb-0">Vigencia hasta</label>
                                <input type="date" class="form-control form-control-sm" id="f_hasta" name="f_hasta"></div>
                            <div class="col-md-6"><label class="form-label small mb-0">Observaciones</label>
                                <input type="text" class="form-control form-control-sm" id="observaciones" name="observaciones" maxlength="255"></div>
                            <div class="col-12">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="usa_escala" name="usa_escala" value="1">
                                    <label class="form-check-label small" for="usa_escala">Los inscriptos retienen por escala (importe fijo + % sobre el excedente del tramo)</label>
                                </div>
                            </div>
                        </div>
                        <div id="bloqueEscala" class="mt-2 d-none">
                            <table class="table table-sm table-bordered align-middle mb-1" id="tablaEscala">
                                <thead class="table-light"><tr>
                                    <th>Base sujeta desde</th><th>Hasta (vacío = sin tope)</th><th>Importe fijo</th><th>% sobre el excedente</th><th></th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                            <button type="button" class="btn btn-xs btn-outline-primary" id="btnAgregarTramo"><i class="fas fa-plus me-1"></i>Agregar tramo</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarRegimen"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script src="regimenes_retencion.js?v=<?= filemtime(__DIR__ . '/regimenes_retencion.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
