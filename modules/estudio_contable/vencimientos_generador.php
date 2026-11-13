<?php
/**
 * Vista: Generador Masivo de Vencimientos por Calendario
 * Módulo 4: Estudio Contable (vencimientos_generador.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Generador de Vencimientos";
$currentPage = 'vencimientos_generador';
$modudo_idx = 4;
$pagina_idx = 404;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-cogs me-2 text-primary"></i>Generador Masivo de Vencimientos
                    </h3>
                    <small class="text-muted">Generación en lote mensual cruzando calendario oficial, terminación de CUIT y matriz impositiva</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item"><a href="vencimientos_tablero.php">Vencimientos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Generador</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Parámetros de Generación -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-header py-2 bg-light">
                    <h6 class="card-title fw-bold text-primary mb-0 fs-7">
                        <i class="fas fa-filter me-1"></i>Parámetros de Emisión por Lote
                    </h6>
                </div>
                <div class="card-body py-3">
                    <form id="formGenerador">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3 col-sm-6">
                                <label for="gen_periodo_fiscal" class="form-label form-label-sm fw-semibold">Período Fiscal (YYYYMM) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm fw-bold" id="gen_periodo_fiscal" name="periodo_fiscal" required value="<?= date('Ym') ?>" min="200001" max="209912">
                                <div class="form-text fs-8">Ej: 202610 para Octubre 2026</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <label for="gen_impuesto_obligacion_id" class="form-label form-label-sm fw-semibold">Obligación / Tributo</label>
                                <select class="form-select form-select-sm" id="gen_impuesto_obligacion_id" name="impuesto_obligacion_id">
                                    <option value="0">-- Todas las obligaciones del padrón --</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="gen_cliente_id" class="form-label form-label-sm fw-semibold">Cliente Específico (Opcional)</label>
                                <select class="form-select form-select-sm" id="gen_cliente_id" name="cliente_id">
                                    <option value="0">-- Todo el padrón de clientes --</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <button type="button" class="btn btn-sm btn-info w-100 text-white" id="btnPreview">
                                    <i class="fas fa-eye me-1"></i>Previsualizar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Previsualización -->
            <div class="card shadow-sm" id="cardResultados" style="display: none;">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold fs-7" id="resumen_conteo">0 vencimientos a evaluar</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-success fw-semibold" id="btnEjecutarLote" data-funcion="agregar">
                            <i class="fas fa-bolt me-1"></i>Generar Vencimientos en Firme
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" id="tablaPreview">
                            <thead class="table-light">
                                <tr>
                                    <th>Estado Lote</th>
                                    <th>Cliente</th>
                                    <th>CUIT</th>
                                    <th>Obligación</th>
                                    <th>Jurisdicción</th>
                                    <th>Período</th>
                                    <th>Fecha Límite Calendario</th>
                                </tr>
                            </thead>
                            <tbody id="preview_tbody">
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="vencimientos_generador.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
