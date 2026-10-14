<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Estudios Contables y Vinculaciones";
$currentPage = 'estudios';
$modudo_idx = 6;
$pagina_idx = 1011;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-briefcase me-2 text-primary"></i>Estudios Contables
                    </h3>
                    <small class="text-muted">Estudios contables autorizados y vinculación con empleadores representados</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Estudios</li>
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
                                <table id="tablaEstudios" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>CUIT</th>
                                            <th>Razón Social / Nombre</th>
                                            <th>Matrícula</th>
                                            <th>Contacto</th>
                                            <th>Email / Teléfono</th>
                                            <th class="text-center">Empleadores</th>
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

    <!-- Modal Estudio -->
    <div class="modal fade" id="modalEstudio" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEstudioTitle">
                        <i class="fas fa-briefcase me-2 text-primary"></i>Estudio Contable
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEstudio">
                    <div class="modal-body">
                        <input type="hidden" name="estudio_id" id="estudio_id" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">CUIT <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cuit" name="cuit" required maxlength="11" placeholder="20123456789">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Razón Social / Denominación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="razon_social" name="razon_social" required maxlength="150">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Matrícula Profesional</label>
                                <input type="text" class="form-control" id="matricula" name="matricula" placeholder="T° XX F° YY">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contacto / Responsable</label>
                                <input type="text" class="form-control" id="contacto" name="contacto">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarEst">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Vinculaciones -->
    <div class="modal fade" id="modalVinculaciones" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-link me-2 text-primary"></i>Empleadores Vinculados al Estudio: <span id="spanNombreEstudio" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modal_estudio_id" value="">

                    <!-- Formulario de vinculación rápida -->
                    <div class="card bg-light border mb-3">
                        <div class="card-body py-2">
                            <form id="formNuevaVinculacion" class="row g-2 align-items-center">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold mb-1">Vincular nuevo empleador:</label>
                                    <select class="form-select form-select-sm" id="combo_empleadores_vincular" required>
                                        <option value="">(Seleccionar empleador a representar)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 pt-3">
                                    <button type="submit" class="btn btn-sm btn-primary w-100" id="btnVincularEmp">
                                        <i class="fas fa-plus me-1"></i> Asignar Vínculo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de vinculaciones existentes -->
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle" id="tablaVinculaciones">
                            <thead class="table-light">
                                <tr>
                                    <th>CUIT</th>
                                    <th>Razón Social Empleador</th>
                                    <th>Iniciado Por</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script src="estudios.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
