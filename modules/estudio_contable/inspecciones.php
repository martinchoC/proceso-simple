<?php
/**
 * Vista: Seguimiento de inspecciones y fiscalizaciones de los clientes
 * Módulo 4: Estudio Contable (inspecciones.php)
 * Página ID: 418
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Inspecciones de Clientes";
$currentPage = 'inspecciones';
$modudo_idx = 4;
$pagina_idx = 418;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold"><i class="fas fa-search-dollar me-2 text-primary"></i>Inspecciones de Clientes</h3>
                    <small class="text-muted">Actas, requerimientos, plazos de descargo, montos y tareas asociadas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Inspecciones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2">
                    <small class="text-muted text-uppercase fw-bold">Abiertas</small><h3 class="mb-0" id="kpiAbiertas">–</h3></div></div></div>
                <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2">
                    <small class="text-muted text-uppercase fw-bold">Plazos vencidos</small><h3 class="mb-0 text-danger" id="kpiVencidos">–</h3></div></div></div>
                <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2">
                    <small class="text-muted text-uppercase fw-bold">Monto en juego (reclamado - pagado)</small><h3 class="mb-0" id="kpiMonto">–</h3></div></div></div>
            </div>

            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Cliente</label>
                            <select id="filtroCliente" class="form-select form-select-sm"><option value="">-- Todos --</option></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm"><option value="">-- Todos --</option></select>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="filtroAbiertas" checked>
                                <label class="form-check-label form-label-sm" for="filtroAbiertas">Solo abiertas</label>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-success btn-sm" id="btnNuevaInsp" data-funcion="agregar"><i class="fas fa-plus-circle me-1"></i>Nueva</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="tablaInsp">
                            <thead class="table-light">
                                <tr><th>Cliente</th><th>Organismo / Acta</th><th>Inicio</th><th>Estado</th><th class="text-end">Reclamado</th>
                                    <th class="text-end">Pagado</th><th>Próximo plazo</th><th>Responsable</th><th></th></tr>
                            </thead>
                            <tbody><tr><td colspan="9" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal: inspección -->
<div class="modal fade" id="modalInsp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formInsp" autocomplete="off">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalInspTitulo">Inspección</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="inspeccion_id" id="i_id" value="0">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label form-label-sm fw-semibold">Cliente *</label>
                            <select class="form-select form-select-sm" name="cliente_id" id="i_cliente" required></select></div>
                        <div class="col-md-3"><label class="form-label form-label-sm fw-semibold">Organismo</label>
                            <select class="form-select form-select-sm" name="organismo" id="i_organismo"></select></div>
                        <div class="col-md-3"><label class="form-label form-label-sm fw-semibold">Tipo</label>
                            <select class="form-select form-select-sm" name="tipo" id="i_tipo"></select></div>
                        <div class="col-md-4"><label class="form-label form-label-sm fw-semibold">N° de acta / expediente</label>
                            <input type="text" class="form-control form-control-sm" name="numero_acta" id="i_acta" maxlength="60"></div>
                        <div class="col-md-4"><label class="form-label form-label-sm fw-semibold">Impuesto (opcional)</label>
                            <select class="form-select form-select-sm" name="impuesto_obligacion_id" id="i_impuesto"></select></div>
                        <div class="col-md-4"><label class="form-label form-label-sm fw-semibold">Estado</label>
                            <select class="form-select form-select-sm" name="estado_proceso" id="i_estado"></select></div>
                        <div class="col-md-3"><label class="form-label form-label-sm fw-semibold">Inicio *</label>
                            <input type="date" class="form-control form-control-sm" name="f_inicio" id="i_inicio" required></div>
                        <div class="col-md-3"><label class="form-label form-label-sm fw-semibold">Cierre</label>
                            <input type="date" class="form-control form-control-sm" name="f_cierre" id="i_cierre"></div>
                        <div class="col-md-3"><label class="form-label form-label-sm fw-semibold">Período desde (AAAAMM)</label>
                            <input type="text" class="form-control form-control-sm" name="periodo_desde" id="i_pdesde" maxlength="6"></div>
                        <div class="col-md-3"><label class="form-label form-label-sm fw-semibold">Período hasta (AAAAMM)</label>
                            <input type="text" class="form-control form-control-sm" name="periodo_hasta" id="i_phasta" maxlength="6"></div>
                        <div class="col-md-4"><label class="form-label form-label-sm fw-semibold">Monto reclamado</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="monto_reclamado" id="i_reclamado" value="0"></div>
                        <div class="col-md-4"><label class="form-label form-label-sm fw-semibold">Monto aceptado</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="monto_aceptado" id="i_aceptado" value="0"></div>
                        <div class="col-md-4"><label class="form-label form-label-sm fw-semibold">Monto pagado</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="monto_pagado" id="i_pagado" value="0"></div>
                        <div class="col-md-6"><label class="form-label form-label-sm fw-semibold">Responsable</label>
                            <select class="form-select form-select-sm" name="usuario_r_id" id="i_resp"></select></div>
                        <div class="col-12"><label class="form-label form-label-sm fw-semibold">Observaciones</label>
                            <textarea class="form-control form-control-sm" name="observaciones" id="i_obs" rows="2"></textarea></div>
                    </div>

                    <div id="bloqueHitos" class="d-none mt-3">
                        <hr class="my-2">
                        <h6 class="fw-bold">Hitos y plazos</h6>
                        <div class="row g-2 align-items-end mb-2" id="formHito">
                            <div class="col-md-2"><label class="form-label form-label-sm">Tipo</label><select class="form-select form-select-sm" id="h_tipo"></select></div>
                            <div class="col-md-2"><label class="form-label form-label-sm">Fecha</label><input type="date" class="form-control form-control-sm" id="h_fecha"></div>
                            <div class="col-md-2"><label class="form-label form-label-sm">Plazo</label><input type="date" class="form-control form-control-sm" id="h_limite"></div>
                            <div class="col-md-4"><label class="form-label form-label-sm">Descripción</label><input type="text" class="form-control form-control-sm" id="h_desc" maxlength="255"></div>
                            <div class="col-md-2"><label class="form-label form-label-sm">Importe</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="h_importe"></div>
                            <div class="col-md-8">
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="h_tarea" checked>
                                <label class="form-check-label form-label-sm" for="h_tarea">Crear tarea RACI para el plazo</label></div>
                            </div>
                            <div class="col-md-4 text-end"><button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarHito" data-funcion="agregar"><i class="fas fa-plus me-1"></i>Agregar hito</button></div>
                        </div>
                        <table class="table table-sm table-bordered mb-0"><thead class="table-light">
                            <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Plazo</th><th class="text-end">Importe</th><th class="text-center">Cumplido</th></tr></thead>
                            <tbody id="tbHitos"></tbody></table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-danger me-auto d-none" id="btnAnularInsp" data-funcion="editar"><i class="fas fa-ban me-1"></i>Anular</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<?php require_once __DIR__ . '/estudio_cliente_activo.php'; estudio_cliente_activo_script((int) $current_empresa_id); ?>
<script src="inspecciones.js?v=<?= (int) @filemtime(__DIR__ . '/inspecciones.js') ?>"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
