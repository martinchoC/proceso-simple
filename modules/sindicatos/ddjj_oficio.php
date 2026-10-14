<?php
/**
 * modules/sindicatos/ddjj_oficio.php
 * Vista de Generación de DDJJ de Oficio para Empleadores Omisos (Página 1022).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Declaraciones Juradas de Oficio";
$currentPage = 'ddjj_oficio';
$modudo_idx = 6;
$pagina_idx = 1022;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-gavel me-2 text-danger"></i>Declaraciones Juradas de Oficio
                    </h3>
                    <small class="text-muted">Determinación presuntiva y liquidación forzosa para empleadores que no presentaron DDJJ</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">DDJJ de Oficio</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Pestañas -->
            <ul class="nav nav-pills mb-3" id="tabOficio" role="tablist">
                <li class="nav nav-item" role="presentation">
                    <button class="nav-link active btn-sm" id="tabOmisos-btn" data-bs-toggle="pill" data-bs-target="#tabOmisos" type="button" role="tab">
                        <i class="fas fa-user-clock me-1"></i> Empleadores Omisos en Período
                    </button>
                </li>
                <li class="nav nav-item" role="presentation">
                    <button class="nav-link btn-sm ms-2" id="tabEmitidas-btn" data-bs-toggle="pill" data-bs-target="#tabEmitidas" type="button" role="tab">
                        <i class="fas fa-list-check me-1"></i> DDJJ de Oficio Emitidas
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="tabOficioContent">
                <!-- Pestaña 1: Empleadores Omisos -->
                <div class="tab-pane fade show active" id="tabOmisos" role="tabpanel">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted mb-1">Período a Inspeccionar (AAAAMM)</label>
                                    <input type="number" class="form-control form-control-sm" id="filtroPeriodo" placeholder="202610">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-muted mb-1">Buscar por Razón Social o CUIT</label>
                                    <input type="text" class="form-control form-control-sm" id="busquedaOmisos" placeholder="Filtro rápido...">
                                </div>
                                <div class="col-md-5 text-md-end mt-md-4">
                                    <button type="button" class="btn btn-primary btn-sm" id="btnBuscarOmisos">
                                        <i class="fas fa-search me-1"></i> Consultar Empleadores Omisos
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold">
                                <i class="fas fa-exclamation-circle text-danger me-2"></i>Empleadores sin DDJJ Presentada
                            </h5>
                            <span class="badge bg-light text-muted border" id="badgeTotalOmisos">0 encontrados</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="tablaOmisos">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Empleador / Razón Social</th>
                                            <th>CUIT</th>
                                            <th>Categoría Mora</th>
                                            <th class="text-center">Trabajadores Activos</th>
                                            <th class="text-center">Situación Período</th>
                                            <th class="text-end" style="min-width: 180px;">Acción de Fiscalización</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyOmisos">
                                        <tr><td colspan="6" class="text-center py-5 text-muted">Seleccione un período para consultar</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 2: DDJJ Emitidas -->
                <div class="tab-pane fade" id="tabEmitidas" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="tablaEmitidas">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Período</th>
                                            <th>Empleador / CUIT</th>
                                            <th class="text-center">Nómina Base</th>
                                            <th class="text-end">Total Determinado</th>
                                            <th>Cód. Verificación</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyEmitidas">
                                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script src="modules/sindicatos/ddjj_oficio.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
