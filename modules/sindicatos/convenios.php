<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Convenios Colectivos y Escalas Salariales";
$currentPage = 'convenios';
$modudo_idx = 6;
$pagina_idx = 1002;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-contract me-2 text-primary"></i>Convenios y Escalas
                    </h3>
                    <small class="text-muted">Convenios colectivos de trabajo, categorías salariales y escalas vigentes para cálculo de pisos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Convenios</li>
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
                                <table id="tablaConvenios" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre del Convenio</th>
                                            <th>Descripción</th>
                                            <th class="text-center">Categorías</th>
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

    <!-- Modal Convenio -->
    <div class="modal fade" id="modalConvenio" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalConvenioTitle">
                        <i class="fas fa-file-contract me-2 text-primary"></i>Convenio Colectivo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formConvenio">
                    <div class="modal-body">
                        <input type="hidden" name="convenio_id" id="convenio_id" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Código CCT <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="codigo" name="codigo" required placeholder="ej: 130/75" maxlength="30">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre Descriptivo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Empleados de Comercio" maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Descripción / Alcance</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarConvenio">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Categorías y Escalas -->
    <div class="modal fade" id="modalCategorias" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-users-cog me-2 text-primary"></i>Categorías y Escalas del Convenio: <span id="spanNombreConvenio" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modal_convenio_id" value="">
                    <div class="row">
                        <!-- Panel Categorías -->
                        <div class="col-md-5 border-end">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Categorías Laborales</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnNuevaCategoria">
                                    <i class="fas fa-plus"></i> Nueva
                                </button>
                            </div>
                            <div class="list-group" id="listaCategorias" style="max-height: 450px; overflow-y: auto;"></div>
                        </div>

                        <!-- Panel Escalas Salariales -->
                        <div class="col-md-7">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Escalas Salariales por Período: <span id="spanCategoriaSeleccionada" class="text-secondary">(Seleccionar categoría)</span></h6>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnNuevaEscala" style="display: none;">
                                    <i class="fas fa-calendar-plus"></i> Cargar Escala
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                                <table class="table table-sm table-striped align-middle" id="tablaEscalas">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Período</th>
                                            <th class="text-end">Básico</th>
                                            <th class="text-end">Rem 1</th>
                                            <th class="text-end">Rem 2</th>
                                            <th class="text-end">Total Piso</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="5" class="text-center text-muted py-4">Seleccione una categoría a la izquierda para ver sus escalas.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script src="convenios.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
