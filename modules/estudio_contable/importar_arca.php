<?php
/**
 * Vista: Importar Mis Comprobantes (ARCA) en la empresa del cliente del estudio
 * Módulo 4: Estudio Contable (importar_arca.php)
 * Página ID: 419
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Importar Mis Comprobantes";
$currentPage = 'importar_arca';
$modudo_idx = 4;
$pagina_idx = 419;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0 fw-bold"><i class="fas fa-file-import me-2 text-primary"></i>Importar Mis Comprobantes</h3>
                    <small class="text-muted">Emitidos y Recibidos de ARCA a las ventas y compras de la empresa del cliente</small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Importar Mis Comprobantes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold" for="iaCliente">Cliente</label>
                            <select class="form-select form-select-sm" id="iaCliente"></select>
                            <small class="text-muted" id="iaClienteInfo"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold" for="iaArchivo">Archivo de ARCA (Emitidos o Recibidos, .xlsx o .csv)</label>
                            <input type="file" class="form-control form-control-sm" id="iaArchivo" accept=".xlsx,.xls,.csv">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold" for="iaPlantilla">Plantilla (si a la empresa le faltan alícuotas o tipos)</label>
                            <select class="form-select form-select-sm" id="iaPlantilla"></select>
                        </div>
                    </div>
                    <div class="mt-2 small" id="iaArchivoInfo"></div>
                </div>
            </div>

            <!-- Prerrequisitos de la empresa del cliente -->
            <div class="card shadow-sm border-0 mb-3 d-none" id="iaCardPrerreq">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-tools me-1"></i>Empresa del cliente</strong>
                    <button type="button" class="btn btn-sm btn-warning d-none" id="iaPreparar" data-funcion="importar_arca"><i class="fas fa-magic me-1"></i>Preparar la empresa</button>
                </div>
                <div class="card-body py-2 small" id="iaPrerreq"></div>
            </div>

            <!-- Previsualización -->
            <div class="card shadow-sm border-0 mb-3 d-none" id="iaCardPreview">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <strong id="iaResumen"></strong>
                    <button type="button" class="btn btn-sm btn-primary" id="iaImportar" data-funcion="importar_arca" disabled><i class="fas fa-file-import me-1"></i>Importar los listos</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 420px;">
                        <table class="table table-sm table-hover mb-0" id="iaTabla">
                            <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th>Número</th><th>Contraparte</th><th class="text-end">Total</th><th>Estado</th><th>Detalle</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Compras en Borrador -->
            <div class="card shadow-sm border-0 mb-3 d-none" id="iaCardBorradores">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-clipboard-check me-1"></i>Compras en Borrador del cliente <span class="text-muted fw-normal" id="iaBorrResumen"></span></strong>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="iaBorrRecargar"><i class="fas fa-sync-alt"></i></button>
                        <button type="button" class="btn btn-sm btn-success" id="iaConfirmar" data-funcion="confirmar_compras" disabled><i class="fas fa-check-double me-1"></i>Confirmar seleccionadas</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 420px;">
                        <table class="table table-sm table-hover mb-0" id="iaTablaBorr">
                            <thead class="table-light"><tr><th><input type="checkbox" id="iaBorrTodas" title="Todas las que se pueden confirmar"></th><th>Fecha</th><th>Tipo</th><th>Número</th><th>Proveedor</th><th class="text-end">Total</th><th class="text-end">Otros tributos</th><th>Estado</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>window.IA_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/xlsx-0.18.5/xlsx.full.min.js') ?>"></script>
<script src="<?= asset_local('js/arca-mis-comprobantes.js') ?>"></script>
<script src="importar_arca.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
