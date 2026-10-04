<?php
/**
 * Vista de Consola de Alertas (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/alertas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Consola de Alertas";
$currentPage = 'alertas';
$modudo_idx = 3;
$pagina_idx = 354; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-bell me-2 text-danger"></i>Consola de Alertas Preventivas
                    </h3>
                    <small class="text-muted">Monitoreo de notificaciones de vencimiento, tareas próximas y validez de evidencias (C04, C05, C30)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ejecución y Seguimiento</li>
                        <li class="breadcrumb-item active" aria-current="page">Consola de Alertas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Tarjetas de KPIs -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card card-outline card-secondary mb-0 shadow-sm">
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small d-block">Total Alertas</span>
                                <h4 class="mb-0 fw-bold" id="kpiTotal">0</h4>
                            </div>
                            <div class="fs-2 text-secondary opacity-50"><i class="fas fa-bell"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card card-outline card-warning mb-0 shadow-sm">
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small d-block">Pendientes en Cola</span>
                                <h4 class="mb-0 fw-bold text-warning" id="kpiPendientes">0</h4>
                            </div>
                            <div class="fs-2 text-warning opacity-50"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card card-outline card-success mb-0 shadow-sm">
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small d-block">Despachadas / Notificadas</span>
                                <h4 class="mb-0 fw-bold text-success" id="kpiEnviadas">0</h4>
                            </div>
                            <div class="fs-2 text-success opacity-50"><i class="fas fa-check-double"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card card-outline card-info mb-0 shadow-sm">
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small d-block">Canales (Email / Sistema)</span>
                                <h5 class="mb-0 fw-bold text-info"><span id="kpiEmail">0</span> / <span id="kpiSistema">0</span></h5>
                            </div>
                            <div class="fs-2 text-info opacity-50"><i class="fas fa-envelope-open-text"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros y Acciones -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Canal</label>
                            <select id="filtroCanal" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <option value="EMAIL">EMAIL</option>
                                <option value="SISTEMA">SISTEMA</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado Notificación</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <option value="1">Pendientes / Programadas</option>
                                <option value="2">Despachadas / Enviadas</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Destinatario</label>
                            <select id="filtroUsuario" class="form-select form-select-sm">
                                <option value="">-- Todos los usuarios --</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-12 text-md-end text-center pt-md-3 pt-1">
                            <button id="btnDisparar" class="btn btn-sm btn-primary me-1" title="Chequear y generar alertas automáticas">
                                <i class="fas fa-cogs me-1"></i>Generar Preventivas
                            </button>
                            <button id="btnDespacharCola" class="btn btn-sm btn-success me-1" title="Despachar notificaciones pendientes en cola">
                                <i class="fas fa-paper-plane me-1"></i>Despachar Cola
                            </button>
                            <button id="btnRecargar" class="btn btn-sm btn-outline-secondary" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Alertas -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaAlertas" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Motivo / Evento</th>
                                    <th>Entidad / Obligación</th>
                                    <th>Elemento Monitoreado</th>
                                    <th>Destinatario</th>
                                    <th>Canal</th>
                                    <th>Programada Para</th>
                                    <th>Enviada En</th>
                                    <th>Estado</th>
                                    <th style="width: 60px;" class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Cargado vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
<script src="alertas.js?v=<?= time() ?>"></script>
