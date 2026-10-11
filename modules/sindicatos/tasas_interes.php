<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Tasas de Interés Resarcitorio";
$currentPage = 'tasas_interes';
$modudo_idx = 6;
$pagina_idx = 1005;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-percentage me-2 text-primary"></i>Tasas de Interés
                    </h3>
                    <small class="text-muted">Tramos históricos y vigentes de tasas resarcitorias mensuales para emisión de volantes</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tasas de Interés</li>
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
                                <table id="tablaTasas" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Clase</th>
                                            <th class="text-end">Tasa Mensual (%)</th>
                                            <th>Vigencia Desde</th>
                                            <th>Vigencia Hasta</th>
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

    <!-- Modal Tasa -->
    <div class="modal fade" id="modalTasa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTasaTitle">
                        <i class="fas fa-percentage me-2 text-primary"></i>Tramo de Tasa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formTasa">
                    <div class="modal-body">
                        <input type="hidden" name="tasa_interes_id" id="tasa_interes_id" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Clase de Interés</label>
                            <select class="form-select" id="clase" name="clase" required>
                                <option value="1">1 - Interés Resarcitorio Mensual</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tasa Mensual (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="tasa_mensual" name="tasa_mensual" step="0.0001" min="0.0001" required placeholder="ej: 3.5000">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Tasa nominal mensual aplicada a días proporcionales (/ 30).</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Vigencia Desde <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="f_desde" name="f_desde" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Vigencia Hasta <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="f_hasta" name="f_hasta" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarTasa">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="tasas_interes.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
