<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Conceptos de Aporte Sindical";
$currentPage = 'conceptos';
$modudo_idx = 6;
$pagina_idx = 1003;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tags me-2 text-primary"></i>Conceptos de Aporte
                    </h3>
                    <small class="text-muted">Conceptos de recaudación sindical (Fondo Social, Cuota Sindical) y reglas de cálculo</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Conceptos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <div id="contenedor-boton-agregar" class="d-inline"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm float-end" id="btnRecargar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tablaConceptos" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Convenio</th>
                                            <th>Base</th>
                                            <th class="text-end">Porcentaje</th>
                                            <th class="text-center">Solo Afiliados</th>
                                            <th class="text-center">Piso Escala</th>
                                            <th class="text-center">SAC</th>
                                            <th>Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Concepto -->
    <div class="modal fade" id="modalConcepto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalConceptoTitle">
                        <i class="fas fa-tag me-2 text-primary"></i>Concepto de Aporte
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formConcepto">
                    <div class="modal-body">
                        <input type="hidden" name="concepto_id" id="concepto_id" value="">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Convenio CCT <span class="text-danger">*</span></label>
                                <select class="form-select" id="convenio_id" name="convenio_id" required>
                                    <option value="">(Seleccionar)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase" id="codigo" name="codigo" required placeholder="ART100" maxlength="30">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Descripción <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="descripcion" name="descripcion" required placeholder="Art. 100 Fondo Social" maxlength="150">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Base de Cálculo</label>
                                <select class="form-select" id="base_calculo" name="base_calculo" required>
                                    <option value="REM_TOTAL">Remuneración Total (Rem 1 + Rem 2)</option>
                                    <option value="REMUNERATIVO">Solo Remunerativo (Rem 1)</option>
                                    <option value="FIJO">Importe Fijo</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Porcentaje (%)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="porcentaje" name="porcentaje" step="0.01" min="0" max="100" value="2.00">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Valor Fijo ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="valor_fijo" name="valor_fijo" step="0.01" min="0" value="0.00">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="solo_afiliados" name="solo_afiliados" value="1">
                                    <label class="form-check-label fw-semibold" for="solo_afiliados">Solo Afiliados</label>
                                </div>
                                <small class="text-muted d-block">Exige relación sindical activa en el período.</small>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="aplica_piso_escala" name="aplica_piso_escala" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="aplica_piso_escala">Aplica Piso por Escala</label>
                                </div>
                                <small class="text-muted d-block">Aplica el mínimo según categoría CCT.</small>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="incluye_sac" name="incluye_sac" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="incluye_sac">Incluye SAC en Junio/Dic</label>
                                </div>
                                <small class="text-muted d-block">Suma el proporcional de aguinaldo.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Concepto Contable (ERP)</label>
                                <input type="text" class="form-control" id="cuenta_concepto_codigo" name="cuenta_concepto_codigo" value="SINDI_DEUDORES_APORTES" placeholder="SINDI_DEUDORES_APORTES">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Vigencia Desde</label>
                                <input type="date" class="form-control" id="f_desde" name="f_desde">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Vigencia Hasta</label>
                                <input type="date" class="form-control" id="f_hasta" name="f_hasta">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarConcepto">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="conceptos.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
