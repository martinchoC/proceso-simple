<?php
/**
 * Vista de Configuración del Grupo y Roles RACI (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/configuracion.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Configuración y Opciones Corporativas";
$currentPage = 'configuracion';
$modudo_idx = 3;
$pagina_idx = 308; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>Configuración y Opciones del Grupo
                    </h3>
                    <small class="text-muted">Parámetros corporativos de cálculo normativo y catálogo RACI</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Configuración</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Pestañas de Navegación -->
            <div class="card card-primary card-outline card-outline-tabs">
                <div class="card-header p-0 border-bottom-0">
                    <ul class="nav nav-tabs" id="tabsConfiguracion" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="tab-opciones-link" data-bs-toggle="tab" data-bs-target="#tab-opciones" type="button" role="tab" aria-controls="tab-opciones" aria-selected="true">
                                <i class="fas fa-cogs me-1"></i>Las 10 Opciones del Grupo
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="tab-raci-link" data-bs-toggle="tab" data-bs-target="#tab-raci" type="button" role="tab" aria-controls="tab-raci" aria-selected="false">
                                <i class="fas fa-users-cog me-1"></i>Roles de la Matriz RACI
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="tabsContentConfiguracion">

                        <!-- TAB 1: LAS 10 OPCIONES -->
                        <div class="tab-pane fade show active" id="tab-opciones" role="tabpanel" aria-labelledby="tab-opciones-link">
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-4 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Grupo Corporativo</label>
                                    <select id="filtroGrupo" class="form-select form-select-sm">
                                        <option value="">-- Seleccionar Grupo --</option>
                                    </select>
                                </div>
                                <div class="col-md-8 col-sm-6 text-md-end mt-3 mt-md-auto">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarOpciones" title="Refrescar">
                                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaOpciones">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">#</th>
                                            <th style="width: 320px;">Directriz Corporativa</th>
                                            <th>Valor Activo / Opciones Admisibles</th>
                                            <th style="width: 140px;" class="text-center">Vigente Desde</th>
                                            <th style="width: 120px;" class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoOpciones">
                                        <tr><td colspan="5" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando configuración...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: ROLES RACI -->
                        <div class="tab-pane fade" id="tab-raci" role="tabpanel" aria-labelledby="tab-raci-link">
                            <p class="text-muted small mb-3">
                                La metodología RACI define las cuatro responsabilidades posibles para cada obligación y tarea del sistema normativo:
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover align-middle mb-0" id="tablaRaci">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 80px;" class="text-center">Código</th>
                                            <th style="width: 250px;">Rol</th>
                                            <th>Descripción y Alcance en el Flujo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center"><span class="badge bg-primary fs-6 px-3">R</span></td>
                                            <td class="fw-bold">Responsable</td>
                                            <td>Quien ejecuta la tarea o prepara la evidencia de cumplimiento. Puede haber uno o varios por tarea.</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center"><span class="badge bg-success fs-6 px-3">A</span></td>
                                            <td class="fw-bold">Accountable / Aprobador</td>
                                            <td>Quien responde legalmente y aprueba el cumplimiento de la obligación. Debe ser único por ciclo.</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center"><span class="badge bg-info fs-6 px-3">C</span></td>
                                            <td class="fw-bold">Consultado</td>
                                            <td>Especialista o área técnica/legal consultada previamente durante la ejecución o elaboración.</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center"><span class="badge bg-secondary fs-6 px-3">I</span></td>
                                            <td class="fw-bold">Informado</td>
                                            <td>Persona o cargo que recibe notificaciones automáticas del avance o cierre del ciclo.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="configuracion.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
