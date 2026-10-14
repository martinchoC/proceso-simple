<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Solicitudes de Afiliación";
$currentPage = 'afiliaciones_solicitudes';
$modudo_idx = 6;
$pagina_idx = 1051;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3 class="mb-0"><i class="fas fa-user-plus me-2 text-primary"></i>Solicitudes de Afiliación</h3>
                <small class="text-muted">Afiliación voluntaria, de jubilados y de familiares a cargo: carga, envío y aprobación</small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Solicitudes de Afiliación</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-primary btn-sm d-none" id="btnNuevaSolicitud"><i class="fas fa-plus me-1"></i> Nueva solicitud</button>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" id="filtroEstado">
                            <option value="">Todos los estados</option>
                            <option value="3">Borrador</option>
                            <option value="4" selected>Pendiente de aprobación</option>
                            <option value="30">Aprobada</option>
                            <option value="25">Rechazada</option>
                        </select>
                    </div>
                    <div class="col-md-auto ms-auto">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecargar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaSolicitudes" class="table table-hover table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>N°</th>
                                <th>Fecha</th>
                                <th>Solicitante</th>
                                <th>Tipo</th>
                                <th>Titular / Empleador</th>
                                <th>Afiliado N°</th>
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

<div class="modal fade" id="modalSolicitud" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalSolicitudTitulo">Nueva solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSolicitud">
                <div class="modal-body">
                    <input type="hidden" name="solicitud_id" id="solicitud_id" value="">
                    <input type="hidden" name="titular_afiliacion_id" id="titular_afiliacion_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipo de afiliación <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_codigo" id="tipo_codigo" required></select>
                        </div>
                        <div class="col-md-6 bloque-empleador d-none">
                            <label class="form-label fw-semibold">Empleador <span class="text-danger">*</span></label>
                            <select class="form-select" name="empleador_id" id="empleador_id"><option value="">(Seleccionar)</option></select>
                        </div>
                        <div class="col-md-6 bloque-familiar d-none">
                            <label class="form-label fw-semibold">Titular <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titular_texto" list="listaTitulares" autocomplete="off" placeholder="CUIL, apellido o N° de afiliado">
                            <datalist id="listaTitulares"></datalist>
                        </div>
                        <div class="col-md-6 bloque-familiar d-none">
                            <label class="form-label fw-semibold">Parentesco <span class="text-danger">*</span></label>
                            <select class="form-select" name="parentesco_id" id="parentesco_id"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CUIL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="cuil" id="cuil" maxlength="11" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="apellido" id="apellido" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="nombre">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de nacimiento</label>
                            <input type="date" class="form-control" name="f_nacimiento" id="f_nacimiento">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Sexo</label>
                            <select class="form-select" name="sexo" id="sexo"><option value="">-</option><option value="F">F</option><option value="M">M</option><option value="X">X</option></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Correo</label>
                            <input type="email" class="form-control" name="email" id="email">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="telefono">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Observaciones</label>
                            <input type="text" class="form-control" name="observaciones" id="observaciones" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Guardar borrador</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="afiliaciones_solicitudes.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
